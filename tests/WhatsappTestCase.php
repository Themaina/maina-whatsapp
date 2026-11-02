<?php

namespace MainaSuperintelligence\Whatsapp\Tests;

use MainaSuperintelligence\Whatsapp\Models\WhatsappContact;
use MainaSuperintelligence\Whatsapp\Models\WhatsappMessage;
use MainaSuperintelligence\Whatsapp\Supports\WhatsappSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Boots the app against the .env database. Every test runs inside a transaction that is rolled
 * back, settings change in memory only and the Graph API is faked (stray requests fail).
 */
abstract class WhatsappTestCase extends TestCase
{
    use DatabaseTransactions;

    public const FIXTURE_PREFIX = 'it-plugintests';

    public const APP_SECRET = 'it-plugintests-app-secret';

    public const PHONE_NUMBER_ID = '109876543210987';

    /**
     * Test numbers (2547000002xx) used by no real customer.
     */
    public const PHONE = '254700000201';

    public const OTHER_PHONE = '254700000202';

    protected int $graphCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();

        if (! is_plugin_active('whatsapp')) {
            $this->markTestSkipped('The whatsapp plugin is not active.');
        }

        Http::preventStrayRequests();

        setting()->set([
            'whatsapp_enabled' => '1',
            'whatsapp_api_version' => 'v21.0',
            'whatsapp_phone_number_id' => self::PHONE_NUMBER_ID,
            'whatsapp_business_account_id' => '209876543210987',
            'whatsapp_access_token' => WhatsappSettings::encrypt('it-plugintests-token'),
            'whatsapp_app_secret' => WhatsappSettings::encrypt(self::APP_SECRET),
            'whatsapp_verify_token' => 'it-plugintests-verify',
            'whatsapp_sms_fallback' => '0',
            'whatsapp_opt_out_confirmation' => '0',
            'whatsapp_default_language' => 'en',
            'whatsapp_default_country_code' => '254',
        ]);

        // Start from a clean slate for the test numbers (rolled back with the transaction).
        WhatsappMessage::query()->whereIn('phone', [self::PHONE, self::OTHER_PHONE])->delete();
        WhatsappContact::query()->whereIn('phone', [self::PHONE, self::OTHER_PHONE])->delete();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function graphUrl(): string
    {
        return 'graph.facebook.com/v21.0/' . self::PHONE_NUMBER_ID . '/messages';
    }

    /**
     * Graph API accepts every message with a new wamid.
     */
    protected function fakeGraphAccepts(): void
    {
        Http::fake([$this->graphUrl() => function () {
            $this->graphCalls++;

            return Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => self::PHONE, 'wa_id' => self::PHONE]],
                'messages' => [['id' => 'wamid.' . self::FIXTURE_PREFIX . '.' . $this->graphCalls . '.' . uniqid()]],
            ]);
        }]);
    }

    protected function fakeGraphRejects(int $code = 132001, string $message = 'Template name does not exist in the translation'): void
    {
        Http::fake([$this->graphUrl() => function () use ($code, $message) {
            $this->graphCalls++;

            return Http::response(['error' => [
                'message' => '(#' . $code . ') ' . $message,
                'type' => 'OAuthException',
                'code' => $code,
                'fbtrace_id' => 'it-plugintests',
            ]], 400);
        }]);
    }

    protected function contact(string $phone = self::PHONE, ?Carbon $lastInboundAt = null, bool $optedOut = false): WhatsappContact
    {
        return WhatsappContact::query()->forceCreate([
            'phone' => $phone,
            'name' => 'Jane ' . self::FIXTURE_PREFIX,
            'opted_out' => $optedOut,
            'last_inbound_at' => $lastInboundAt,
        ]);
    }

    protected function signedWebhook(array $payload, ?string $signature = null): TestResponse
    {
        $raw = json_encode($payload);

        return $this->call('POST', '/whatsapp/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature ?? 'sha256=' . hash_hmac('sha256', $raw, self::APP_SECRET),
        ], $raw);
    }

    protected function webhookPayload(array $value): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => '209876543210987',
                'changes' => [[
                    'field' => 'messages',
                    'value' => array_merge([
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['display_phone_number' => '254700000000', 'phone_number_id' => self::PHONE_NUMBER_ID],
                    ], $value),
                ]],
            ]],
        ];
    }

    protected function inbound(string $from, string $text, ?int $timestamp = null, ?string $id = null): array
    {
        return $this->webhookPayload([
            'contacts' => [['profile' => ['name' => 'Jane'], 'wa_id' => $from]],
            'messages' => [[
                'from' => $from,
                'id' => $id ?? 'wamid.in.' . self::FIXTURE_PREFIX . '.' . uniqid(),
                'timestamp' => (string) ($timestamp ?? Carbon::now()->getTimestamp()),
                'type' => 'text',
                'text' => ['body' => $text],
            ]],
        ]);
    }
}
