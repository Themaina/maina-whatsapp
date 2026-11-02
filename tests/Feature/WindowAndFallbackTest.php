<?php

namespace MainaSuperintelligence\Whatsapp\Tests\Feature;

use MainaSuperintelligence\Sms\Models\SmsLog;
use MainaSuperintelligence\Sms\Services\SmsService;
use MainaSuperintelligence\Whatsapp\Jobs\SendWhatsappMessageJob;
use MainaSuperintelligence\Whatsapp\Models\WhatsappMessage;
use MainaSuperintelligence\Whatsapp\Services\WhatsappService;
use MainaSuperintelligence\Whatsapp\Tests\WhatsappTestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Mockery;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Free-form text only inside the 24 h customer service window, and the SMS fallback calling
 * SmsService::send(string $to, string $message, array $context, bool $urgent).
 */
class WindowAndFallbackTest extends WhatsappTestCase
{
    protected function service(): WhatsappService
    {
        return app(WhatsappService::class);
    }

    public function test_text_without_any_inbound_message_is_refused(): void
    {
        $this->fakeGraphAccepts();

        $message = $this->service()->sendText(self::PHONE, 'Hello');

        $this->assertSame('failed', $message->statusValue());
        $this->assertSame('outside_window', $message->error_code);
        $this->assertSame(0, $this->graphCalls);
    }

    public function test_text_inside_the_window_is_sent(): void
    {
        $this->fakeGraphAccepts();
        $this->contact(self::PHONE, Carbon::now()->subHours(23)->subMinutes(59));

        $message = $this->service()->sendText(self::PHONE, 'Yes, we open at 05:00.');

        $this->assertSame('sent', $message->statusValue());
        $this->assertSame(1, $this->graphCalls);
        $this->assertSame('text', $message->payload['type']);
    }

    public function test_window_closes_exactly_after_24_hours(): void
    {
        $this->fakeGraphAccepts();
        $this->contact(self::PHONE, Carbon::now()->subHours(24));
        $this->contact(self::OTHER_PHONE, Carbon::now()->subHours(24)->addSecond());

        $this->assertFalse($this->service()->isWithinWindow(self::PHONE));
        $this->assertTrue($this->service()->isWithinWindow(self::OTHER_PHONE));

        $this->assertSame('outside_window', $this->service()->sendText(self::PHONE, 'Late reply')->error_code);
        $this->assertSame(0, $this->graphCalls);
    }

    public function test_window_is_per_number(): void
    {
        $this->fakeGraphAccepts();
        $this->contact(self::PHONE, Carbon::now()->subHour());

        $this->assertSame('outside_window', $this->service()->sendText(self::OTHER_PHONE, 'Hi')->error_code);
        $this->assertSame(0, $this->graphCalls);
    }

    public function test_window_counts_from_the_customer_message_timestamp(): void
    {
        $this->fakeGraphAccepts();

        // Meta delivered the webhook late: the message itself was written 25 hours ago.
        $this->signedWebhook($this->inbound(self::PHONE, 'Hi', Carbon::now()->subHours(25)->getTimestamp()))->assertOk();

        $this->assertFalse($this->service()->isWithinWindow(self::PHONE));
        $this->assertSame('outside_window', $this->service()->sendText(self::PHONE, 'Reply')->error_code);
    }

    public function test_window_is_checked_again_when_the_queued_job_runs(): void
    {
        $this->fakeGraphAccepts();
        $this->contact(self::PHONE, Carbon::now()->subHours(23));

        Queue::fake();
        $message = $this->service()->sendText(self::PHONE, 'Queued reply');
        $this->assertSame('queued', $message->statusValue());

        // The worker picks it up two hours later.
        Carbon::setTestNow(Carbon::now()->addHours(2));
        (new SendWhatsappMessageJob($message->getKey()))->handle($this->service());

        $message->refresh();
        $this->assertSame('failed', $message->statusValue());
        $this->assertSame('outside_window', $message->error_code);
        $this->assertSame(0, $this->graphCalls);
    }

    public function test_templates_are_not_limited_by_the_window(): void
    {
        $this->fakeGraphAccepts();
        $this->enableTemplate('it_plugintests_tpl');

        $message = $this->service()->sendTemplate(self::PHONE, 'it_plugintests_tpl', ['customer_name' => 'Jane', 'plan_name' => 'Monthly']);

        $this->assertSame('sent', $message->statusValue());
        $this->assertSame(1, $this->graphCalls);
        $this->assertSame(['Jane', 'Monthly'], array_column($message->payload['template']['components'][0]['parameters'], 'text'));
    }

    public function test_text_outside_the_window_does_not_fall_back_to_sms_unless_asked(): void
    {
        $this->requireSms();
        setting()->set('whatsapp_sms_fallback', '1');

        $sms = Mockery::mock(SmsService::class);
        $sms->shouldNotReceive('send');
        $this->app->instance(SmsService::class, $sms);

        $message = $this->service()->sendText(self::PHONE, 'Free-form');

        $this->assertSame('outside_window', $message->error_code);
        $this->assertArrayNotHasKey('sms_fallback', (array) $message->context);
    }

    public function test_failed_template_falls_back_with_the_sms_service_signature(): void
    {
        $this->requireSms();
        setting()->set('whatsapp_sms_fallback', '1');
        $this->fakeGraphRejects();
        $this->enableTemplate('it_plugintests_tpl');

        $log = new SmsLog(['status' => 'queued']);
        $log->id = 987654321;

        $sms = Mockery::mock(SmsService::class);
        $sms->shouldReceive('send')
            ->once()
            ->withArgs(function ($to, $message, $context, $urgent) {
                return $to === '+' . self::PHONE
                    && $message === 'Hi Jane, your Monthly plan.'
                    && $context['template_key'] === 'it_plugintests_tpl'
                    && $context['source'] === 'whatsapp_fallback'
                    && is_int($context['whatsapp_message_id'])
                    && $urgent === true;
            })
            ->andReturn($log);
        $this->app->instance(SmsService::class, $sms);

        $message = $this->service()->sendTemplate(
            self::PHONE,
            'it_plugintests_tpl',
            ['customer_name' => 'Jane', 'plan_name' => 'Monthly'],
            ['urgent' => true]
        );

        $this->assertSame('failed', $message->statusValue());
        $this->assertSame('132001', $message->error_code);
        $this->assertSame(1, $this->graphCalls);
        $this->assertSame('sent', data_get($message->context, 'sms_fallback.status'));
        $this->assertSame(987654321, data_get($message->context, 'sms_fallback.sms_log_id'));
    }

    public function test_fallback_uses_the_sms_template_when_one_is_registered(): void
    {
        $this->requireSms();
        setting()->set([
            'whatsapp_sms_fallback' => '1',
            'sms_enabled' => '1',
            'sms_quiet_hours_enabled' => '0',
            'sms_template_it_plugintests_tpl' => '',
        ]);
        $this->fakeGraphRejects();
        $this->enableTemplate('it_plugintests_tpl');

        add_filter('sms_templates', function (array $templates): array {
            $templates['it_plugintests_tpl'] = ['label' => 'Test', 'default' => 'SMS for {{customer_name}}: {{plan_name}} renews soon.'];

            return $templates;
        }, 999);

        $captured = null;
        $sms = Mockery::mock(SmsService::class)->makePartial();
        $sms->shouldReceive('send')->once()->andReturnUsing(function ($to, $message) use (&$captured) {
            $captured = $message;

            return new SmsLog(['status' => 'queued']);
        });
        $this->app->instance(SmsService::class, $sms);

        $this->service()->sendTemplate(self::PHONE, 'it_plugintests_tpl', ['customer_name' => 'Jane', 'plan_name' => 'Monthly']);

        $this->assertSame('SMS for Jane: Monthly renews soon.', $captured);
    }

    public function test_fallback_is_off_when_the_setting_is_off(): void
    {
        $this->requireSms();
        setting()->set('whatsapp_sms_fallback', '0');
        $this->fakeGraphRejects();
        $this->enableTemplate('it_plugintests_tpl');

        $sms = Mockery::mock(SmsService::class);
        $sms->shouldNotReceive('send');
        $this->app->instance(SmsService::class, $sms);

        $message = $this->service()->sendTemplate(self::PHONE, 'it_plugintests_tpl', ['customer_name' => 'Jane']);

        $this->assertSame('failed', $message->statusValue());
    }

    public function test_invalid_number_never_falls_back(): void
    {
        $this->requireSms();
        setting()->set('whatsapp_sms_fallback', '1');

        $sms = Mockery::mock(SmsService::class);
        $sms->shouldNotReceive('send');
        $this->app->instance(SmsService::class, $sms);

        $message = $this->service()->sendTemplate('12', 'it_plugintests_tpl', ['customer_name' => 'Jane']);

        $this->assertSame('invalid_phone', $message->error_code);
        $this->assertSame('failed', $message->statusValue());
    }

    public function test_fallback_runs_once_per_message(): void
    {
        $this->requireSms();
        setting()->set('whatsapp_sms_fallback', '1');
        $this->fakeGraphRejects();
        $this->enableTemplate('it_plugintests_tpl');

        $sms = Mockery::mock(SmsService::class);
        $sms->shouldReceive('send')->once()->andReturn(new SmsLog(['status' => 'queued']));
        $this->app->instance(SmsService::class, $sms);

        $message = $this->service()->sendTemplate(self::PHONE, 'it_plugintests_tpl', ['customer_name' => 'Jane']);

        // A later "failed" status webhook for the same message must not send a second SMS.
        $this->service()->fallbackToSms(WhatsappMessage::query()->find($message->getKey()));
        $this->service()->markFailed(WhatsappMessage::query()->find($message->getKey()), '131026', 'Undeliverable');
    }

    protected function requireSms(): void
    {
        if (! is_plugin_active('sms')) {
            $this->markTestSkipped('The sms plugin is not active.');
        }
    }

    protected function enableTemplate(string $key): void
    {
        add_filter('whatsapp_templates', function (array $templates) use ($key): array {
            $templates[$key] = [
                'label' => 'Test',
                'template_name' => $key,
                'variables' => ['customer_name', 'plan_name'],
                'preview' => 'Hi {{customer_name}}, your {{plan_name}} plan.',
            ];

            return $templates;
        }, 999);

        setting()->set([
            'whatsapp_tpl_' . $key . '_enabled' => '1',
            'whatsapp_tpl_' . $key . '_name' => $key,
            'whatsapp_tpl_' . $key . '_language' => 'en',
        ]);
    }
}
