<?php

namespace MainaSuperintelligence\Whatsapp\Http\Controllers;

use Botble\Base\Http\Controllers\BaseController;
use Botble\Base\Http\Responses\BaseHttpResponse;
use MainaSuperintelligence\Whatsapp\Enums\MessageStatusEnum;
use MainaSuperintelligence\Whatsapp\Services\WhatsappService;
use MainaSuperintelligence\Whatsapp\Supports\TemplateRegistry;
use MainaSuperintelligence\Whatsapp\Supports\WhatsappSettings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TestController extends BaseController
{
    public function index()
    {
        $this->pageTitle(trans('plugins/whatsapp::whatsapp.test.title'));

        $definitions = TemplateRegistry::all();
        $configured = WhatsappSettings::isConfigured();

        return view('plugins/whatsapp::test.index', compact('definitions', 'configured'));
    }

    public function send(Request $request, WhatsappService $service): BaseHttpResponse
    {
        $definitions = TemplateRegistry::all();

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'mode' => ['required', Rule::in(['hello_world', 'template', 'text'])],
            'template_key' => ['nullable', 'required_if:mode,template', Rule::in(array_keys($definitions))],
            'text' => ['nullable', 'required_if:mode,text', 'string', 'max:4096'],
        ]);

        $response = $this->httpResponse()->setPreviousRoute('whatsapp.test.index')->withInput();

        if (! $service->normalisePhone($data['phone'])) {
            return $response->setError()->setMessage(trans('plugins/whatsapp::whatsapp.test.invalid_phone'));
        }

        $context = ['force' => true, 'fallback_sms' => false, 'test' => true, 'sent_by' => auth()->id()];

        $message = match ($data['mode']) {
            // Every WhatsApp Business Account has Meta's sample "hello_world" template (en_US).
            'hello_world' => $service->sendTemplate($data['phone'], 'hello_world', [], $context + [
                'template_name' => 'hello_world',
                'language' => 'en_US',
            ]),
            'template' => $service->sendTemplate(
                $data['phone'],
                $data['template_key'],
                $this->sampleValues($definitions[$data['template_key']]),
                $context
            ),
            'text' => $service->sendText($data['phone'], (string) $data['text'], $context),
        };

        if ($message->statusValue() === MessageStatusEnum::FAILED) {
            return $response
                ->setError()
                ->setMessage(trans('plugins/whatsapp::whatsapp.test.failed', [
                    'code' => $message->error_code,
                    'error' => $message->error,
                ]));
        }

        return $response->setMessage(trans('plugins/whatsapp::whatsapp.test.sent', [
            'status' => $message->status?->label(),
            'id' => $message->provider_message_id ?: '-',
        ]));
    }

    protected function sampleValues(array $definition): array
    {
        $values = [];

        foreach ($definition['variables'] as $variable) {
            $values[$variable] = $definition['sample'][$variable] ?? ('Test ' . str_replace('_', ' ', $variable));
        }

        if ($definition['button_variable']) {
            $values[$definition['button_variable']] = $definition['sample'][$definition['button_variable']] ?? 'test';
        }

        return $values;
    }
}
