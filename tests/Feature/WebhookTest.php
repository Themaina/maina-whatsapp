<?php

namespace MainaSuperintelligence\Whatsapp\Tests\Feature;

use MainaSuperintelligence\Whatsapp\Enums\MessageDirectionEnum;
use MainaSuperintelligence\Whatsapp\Events\WhatsappMessageReceived;
use MainaSuperintelligence\Whatsapp\Models\WhatsappContact;
use MainaSuperintelligence\Whatsapp\Models\WhatsappMessage;
use MainaSuperintelligence\Whatsapp\Services\WhatsappService;
use MainaSuperintelligence\Whatsapp\Tests\WhatsappTestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Meta webhook: GET handshake with the verify token, POST authenticated by X-Hub-Signature-256
 * over the raw body. Unsigned or mis-signed requests change nothing.
 */
class WebhookTest extends WhatsappTestCase
{
    public function test_verification_handshake(): void
    {
        $this->get('/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=it-plugintests-verify&hub.challenge=424242')
            ->assertOk()
            ->assertSeeText('424242');

        $this->get('/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=424242')->assertForbidden();
        $this->get('/whatsapp/webhook?hub.mode=unsubscribe&hub.verify_token=it-plugintests-verify&hub.challenge=1')->assertForbidden();
        $this->get('/whatsapp/webhook?hub.mode=subscribe&hub.challenge=1')->assertForbidden();

        // No verify token configured: never answer the challenge.
        setting()->set('whatsapp_verify_token', '');
        $this->get('/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=&hub.challenge=1')->assertForbidden();
    }

    public function test_status_update_with_valid_signature_is_applied(): void
    {
        $message = $this->outboundSent();

        $this->signedWebhook($this->statusPayload($message, 'delivered'))
            ->assertOk()
            ->assertJson(['error' => false, 'data' => ['statuses' => 1]]);

        $message->refresh();
        $this->assertSame('delivered', $message->statusValue());
        $this->assertNotNull($message->delivered_at);

        // Out of order: "sent" after "delivered" never moves it backwards.
        $this->signedWebhook($this->statusPayload($message, 'sent'))->assertOk();
        $this->assertSame('delivered', $message->refresh()->statusValue());

        $this->signedWebhook($this->statusPayload($message, 'read'))->assertOk();
        $this->assertSame('read', $message->refresh()->statusValue());
    }

    public function test_bad_signatures_are_rejected_and_change_nothing(): void
    {
        Event::fake([WhatsappMessageReceived::class]);

        $message = $this->outboundSent();
        $payload = $this->statusPayload($message, 'failed');
        $raw = json_encode($payload);

        $signatures = [
            'missing' => null,
            'wrong secret' => 'sha256=' . hash_hmac('sha256', $raw, 'not-the-secret'),
            'no prefix' => hash_hmac('sha256', $raw, self::APP_SECRET),
            'sha1' => 'sha1=' . hash_hmac('sha1', $raw, self::APP_SECRET),
            'tampered body' => 'sha256=' . hash_hmac('sha256', $raw . ' ', self::APP_SECRET),
            'empty' => '',
        ];

        foreach ($signatures as $label => $signature) {
            $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

            if ($signature !== null) {
                $server['HTTP_X_HUB_SIGNATURE_256'] = $signature;
            }

            $this->call('POST', '/whatsapp/webhook', [], [], [], $server, $raw)
                ->assertStatus(403);

            $this->assertSame('sent', $message->refresh()->statusValue(), "status changed with {$label} signature");
        }

        // Inbound message with a bad signature is not stored and opens no window.
        $inbound = $this->inbound(self::OTHER_PHONE, 'Hi');
        $this->signedWebhook($inbound, 'sha256=' . str_repeat('0', 64))->assertForbidden();

        $this->assertFalse(WhatsappMessage::query()->where('phone', self::OTHER_PHONE)->exists());
        $this->assertFalse(app(WhatsappService::class)->isWithinWindow(self::OTHER_PHONE));
        Event::assertNotDispatched(WhatsappMessageReceived::class);
    }

    public function test_signature_header_is_case_insensitive_hex(): void
    {
        $message = $this->outboundSent();
        $raw = json_encode($this->statusPayload($message, 'delivered'));

        $this->call('POST', '/whatsapp/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . strtoupper(hash_hmac('sha256', $raw, self::APP_SECRET)),
        ], $raw)->assertOk();

        $this->assertSame('delivered', $message->refresh()->statusValue());
    }

    public function test_without_app_secret_every_post_is_rejected(): void
    {
        setting()->set('whatsapp_app_secret', '');

        $message = $this->outboundSent();

        $this->signedWebhook($this->statusPayload($message, 'delivered'))->assertForbidden();
        $this->assertSame('sent', $message->refresh()->statusValue());
    }

    public function test_signed_inbound_message_is_stored_once_and_opens_the_window(): void
    {
        Event::fake([WhatsappMessageReceived::class]);

        $payload = $this->inbound(self::PHONE, 'Hello, what time do you open?', null, 'wamid.in.it-plugintests.once');

        $this->signedWebhook($payload)->assertOk()->assertJson(['data' => ['messages' => 1]]);
        // Meta retries deliveries: the same message id is stored once.
        $this->signedWebhook($payload)->assertOk()->assertJson(['data' => ['messages' => 0, 'ignored' => 1]]);

        $this->assertSame(1, WhatsappMessage::query()
            ->where('phone', self::PHONE)
            ->where('direction', MessageDirectionEnum::IN)
            ->count());

        $contact = WhatsappContact::query()->where('phone', self::PHONE)->first();
        $this->assertNotNull($contact->last_inbound_at);
        $this->assertTrue(app(WhatsappService::class)->isWithinWindow(self::PHONE));
        Event::assertDispatchedTimes(WhatsappMessageReceived::class, 1);
    }

    public function test_stop_and_start_keywords(): void
    {
        $this->signedWebhook($this->inbound(self::PHONE, ' stop. '))->assertOk();
        $this->assertTrue(WhatsappContact::query()->where('phone', self::PHONE)->value('opted_out'));

        // Opted out: templates are not sent.
        $this->fakeGraphAccepts();
        $this->enableTemplate('it_plugintests_tpl');
        $message = app(WhatsappService::class)->sendTemplate(self::PHONE, 'it_plugintests_tpl', ['customer_name' => 'Jane']);
        $this->assertSame('opted_out', $message->error_code);
        $this->assertSame(0, $this->graphCalls);

        $this->signedWebhook($this->inbound(self::PHONE, 'START'))->assertOk();
        $this->assertFalse(WhatsappContact::query()->where('phone', self::PHONE)->value('opted_out'));
    }

    public function test_events_for_another_phone_number_are_ignored(): void
    {
        $payload = $this->inbound(self::PHONE, 'Hi');
        $payload['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] = '999999999999999';

        $this->signedWebhook($payload)->assertOk()->assertJson(['data' => ['messages' => 0, 'ignored' => 1]]);
        $this->assertFalse(WhatsappMessage::query()->where('phone', self::PHONE)->exists());
    }

    protected function outboundSent(): WhatsappMessage
    {
        /** @var WhatsappMessage $message */
        $message = WhatsappMessage::query()->forceCreate([
            'direction' => MessageDirectionEnum::OUT,
            'phone' => self::PHONE,
            'to' => self::PHONE,
            'type' => 'template',
            'template_key' => 'it_plugintests',
            'body' => 'Webhook test ' . self::FIXTURE_PREFIX,
            'status' => 'sent',
            'provider_message_id' => 'wamid.out.' . self::FIXTURE_PREFIX . '.' . uniqid(),
            'sent_at' => Carbon::now(),
        ]);

        return $message->refresh();
    }

    protected function statusPayload(WhatsappMessage $message, string $status): array
    {
        return $this->webhookPayload([
            'statuses' => [[
                'id' => $message->provider_message_id,
                'status' => $status,
                'timestamp' => (string) Carbon::now()->getTimestamp(),
                'recipient_id' => self::PHONE,
                'errors' => $status === 'failed' ? [['code' => 131026, 'title' => 'Message undeliverable']] : [],
            ]],
        ]);
    }

    protected function enableTemplate(string $key): void
    {
        add_filter('whatsapp_templates', function (array $templates) use ($key): array {
            $templates[$key] = ['label' => 'Test', 'template_name' => $key, 'variables' => ['customer_name'], 'preview' => 'Hi {{customer_name}}'];

            return $templates;
        }, 999);

        setting()->set([
            'whatsapp_tpl_' . $key . '_enabled' => '1',
            'whatsapp_tpl_' . $key . '_name' => $key,
            'whatsapp_tpl_' . $key . '_language' => 'en',
        ]);
    }
}
