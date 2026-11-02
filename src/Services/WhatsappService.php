<?php

namespace MainaSuperintelligence\Whatsapp\Services;

use Botble\Base\Facades\BaseHelper;
use MainaSuperintelligence\Whatsapp\Enums\MessageDirectionEnum;
use MainaSuperintelligence\Whatsapp\Enums\MessageStatusEnum;
use MainaSuperintelligence\Whatsapp\Events\WhatsappMessageFailed;
use MainaSuperintelligence\Whatsapp\Events\WhatsappMessageSent;
use MainaSuperintelligence\Whatsapp\Jobs\SendWhatsappMessageJob;
use MainaSuperintelligence\Whatsapp\Models\WhatsappContact;
use MainaSuperintelligence\Whatsapp\Models\WhatsappMessage;
use MainaSuperintelligence\Whatsapp\Models\WhatsappTemplate;
use MainaSuperintelligence\Whatsapp\Supports\PhoneNumber;
use MainaSuperintelligence\Whatsapp\Supports\TemplateRegistry;
use MainaSuperintelligence\Whatsapp\Supports\WhatsappSettings;
use Carbon\Carbon;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Public entry point for sending WhatsApp messages.
 *
 * Context keys understood by sendTemplate()/sendText():
 *  - related (Model)            : stored as the message's related morph
 *  - customer_id (int)          : links the contact to an ecommerce customer
 *  - name (string)              : contact display name
 *  - fallback_sms (bool)        : force (true) or suppress (false) the SMS fallback for this message
 *  - sms_message (string)       : exact SMS text to use if falling back
 *  - urgent (bool)              : passed to the SMS plugin (bypasses quiet hours)
 *  - template_name / language   : override the mapped Meta template for this call
 *  - force (bool)               : ignore the global and per-event enable switches (admin test sends)
 *  - ignore_opt_out (bool)      : send even if the contact opted out (opt-out confirmations, inbox replies)
 * Anything else is stored as-is in the message's context column.
 */
class WhatsappService
{
    public const OUTCOME_SENT = 'sent';

    public const OUTCOME_FAILED = 'failed';

    public const OUTCOME_RETRY = 'retry';

    public const OUTCOME_SKIPPED = 'skipped';

    /**
     * Error codes for which falling back to SMS makes no sense (the number itself is unusable).
     */
    protected const NO_FALLBACK_CODES = ['invalid_phone'];

    public function __construct(protected CloudApiClient $client)
    {
    }

    public function client(): CloudApiClient
    {
        return $this->client;
    }

    public function normalisePhone(string $phone): ?string
    {
        return PhoneNumber::normalise($phone, WhatsappSettings::defaultCountryCode());
    }

    /**
     * Send a business-initiated template message (queued).
     */
    public function sendTemplate(string $to, string $key, array $vars = [], array $context = []): WhatsappMessage
    {
        $definition = TemplateRegistry::get($key);
        $mapping = WhatsappSettings::templateMapping($key, $definition ?? []);

        $templateName = $context['template_name'] ?? $mapping['name'] ?? $key;
        $language = $context['language'] ?? $mapping['language'] ?? WhatsappSettings::defaultLanguage();
        $parameters = TemplateRegistry::parameters($definition, $vars);
        $phone = $this->normalisePhone($to);

        $message = $this->createOutbound($phone, $to, [
            'type' => 'template',
            'template_key' => $key,
            'template_name' => $templateName,
            'language' => $language,
            'body' => TemplateRegistry::render($definition, $vars, $parameters['body']),
            'payload' => $phone ? $this->templatePayload($phone, $templateName, $language, $parameters['body'], $parameters['button']) : null,
        ], $context + ['vars' => $this->scalarVars($vars)]);

        if (! $phone) {
            return $this->reject($message, 'invalid_phone', 'The phone number is not a valid WhatsApp number.');
        }

        $force = (bool) Arr::get($context, 'force', false);

        if (! $force && ! WhatsappSettings::enabled()) {
            return $this->reject($message, 'disabled', 'WhatsApp notifications are disabled in settings.');
        }

        if (! WhatsappSettings::isConfigured()) {
            return $this->reject($message, 'not_configured', 'WhatsApp phone number ID or access token is not set.');
        }

        if (! $force && ! $mapping['enabled']) {
            return $this->reject($message, 'template_disabled', sprintf('The "%s" WhatsApp notification is not enabled.', $key));
        }

        if (! $templateName) {
            return $this->reject($message, 'template_not_mapped', sprintf('No Meta template is mapped to "%s".', $key));
        }

        if ($this->isOptedOut($message) && ! Arr::get($context, 'ignore_opt_out')) {
            return $this->reject($message, 'opted_out', 'The contact replied STOP and opted out of WhatsApp messages.');
        }

        return $this->queue($message);
    }

    /**
     * Send free-form text. Only allowed inside the 24-hour window after the customer's last message.
     */
    public function sendText(string $to, string $text, array $context = []): WhatsappMessage
    {
        $phone = $this->normalisePhone($to);
        $text = Str::limit(trim($text), 4096, '');

        $message = $this->createOutbound($phone, $to, [
            'type' => 'text',
            'body' => $text,
            'payload' => $phone ? $this->textPayload($phone, $text) : null,
        ], $context);

        if (! $phone) {
            return $this->reject($message, 'invalid_phone', 'The phone number is not a valid WhatsApp number.');
        }

        if ($text === '') {
            return $this->reject($message, 'empty', 'The message is empty.');
        }

        if (! Arr::get($context, 'force') && ! WhatsappSettings::enabled()) {
            return $this->reject($message, 'disabled', 'WhatsApp notifications are disabled in settings.');
        }

        if (! WhatsappSettings::isConfigured()) {
            return $this->reject($message, 'not_configured', 'WhatsApp phone number ID or access token is not set.');
        }

        if (! $this->isWithinWindow($phone)) {
            return $this->reject(
                $message,
                'outside_window',
                'Free-form messages can only be sent within 24 hours of the customer\'s last message. Use a template instead.'
            );
        }

        if ($this->isOptedOut($message) && ! Arr::get($context, 'ignore_opt_out')) {
            return $this->reject($message, 'opted_out', 'The contact replied STOP and opted out of WhatsApp messages.');
        }

        return $this->queue($message);
    }

    /**
     * Queue a copy of a (failed) outbound message.
     */
    public function resend(WhatsappMessage $original): WhatsappMessage
    {
        $context = Arr::except((array) $original->context, ['sms_fallback']);
        $context['resent_from'] = $original->getKey();

        $message = new WhatsappMessage([
            'direction' => MessageDirectionEnum::OUT,
            'contact_id' => $original->contact_id,
            'phone' => $original->phone,
            'to' => $original->to,
            'from' => $original->from,
            'type' => $original->type,
            'template_key' => $original->template_key,
            'template_name' => $original->template_name,
            'language' => $original->language,
            'body' => $original->body,
            'payload' => $original->payload,
            'status' => MessageStatusEnum::QUEUED,
            'context' => $context,
            'related_type' => $original->related_type,
            'related_id' => $original->related_id,
            'resent_from_id' => $original->getKey(),
        ]);
        $message->save();

        if (! $message->payload) {
            return $this->reject($message, 'invalid_phone', 'The original message has no deliverable payload.');
        }

        if (! WhatsappSettings::isConfigured()) {
            return $this->reject($message, 'not_configured', 'WhatsApp phone number ID or access token is not set.');
        }

        if ($message->type === 'text' && ! $this->isWithinWindow($message->phone)) {
            return $this->reject($message, 'outside_window', 'The 24-hour window has closed. Send a template instead.');
        }

        return $this->queue($message);
    }

    /**
     * Called by SendWhatsappMessageJob. Returns one of the OUTCOME_* constants.
     */
    public function deliver(WhatsappMessage $message, bool $finalAttempt = true): string
    {
        if ($message->statusValue() !== MessageStatusEnum::QUEUED || ! $message->payload) {
            return self::OUTCOME_SKIPPED;
        }

        $context = (array) $message->context;

        if (! Arr::get($context, 'ignore_opt_out') && $this->isOptedOut($message)) {
            $this->markFailed($message, 'opted_out', 'The contact opted out before the message was sent.');

            return self::OUTCOME_FAILED;
        }

        if ($message->type === 'text' && ! $this->isWithinWindow($message->phone)) {
            $this->markFailed($message, 'outside_window', 'The 24-hour window closed before the message was sent.');

            return self::OUTCOME_FAILED;
        }

        $message->attempts = (int) $message->attempts + 1;

        $result = $this->client->sendMessage($message->payload);

        if ($result['ok']) {
            $message->fill([
                'status' => MessageStatusEnum::SENT,
                'provider_message_id' => $result['id'],
                'error_code' => null,
                'error' => null,
                'sent_at' => $message->sent_at ?: Carbon::now(),
            ]);
            $message->save();

            $this->touchContact($message, ['last_outbound_at' => Carbon::now()] + ($result['wa_id'] ? ['wa_id' => $result['wa_id']] : []));

            WhatsappMessageSent::dispatch($message);

            return self::OUTCOME_SENT;
        }

        if ($result['retryable'] && ! $finalAttempt) {
            $message->fill([
                'error_code' => $result['error_code'],
                'error' => Str::limit((string) $result['error'], 1000),
            ]);
            $message->save();

            return self::OUTCOME_RETRY;
        }

        $this->markFailed($message, (string) $result['error_code'], (string) $result['error']);

        return self::OUTCOME_FAILED;
    }

    public function markFailed(WhatsappMessage $message, string $code, string $error, bool $allowFallback = true): WhatsappMessage
    {
        $message->fill([
            'status' => MessageStatusEnum::FAILED,
            'error_code' => Str::limit($code, 20, ''),
            'error' => Str::limit($error, 1000),
            'failed_at' => Carbon::now(),
        ]);
        $message->save();

        WhatsappMessageFailed::dispatch($message);

        if ($allowFallback && ! in_array($code, self::NO_FALLBACK_CODES, true)) {
            $this->fallbackToSms($message);
        }

        return $message;
    }

    /**
     * Send the message over SMS through the `sms` plugin, when that plugin is active and fallback is allowed.
     */
    public function fallbackToSms(WhatsappMessage $message): bool
    {
        if (! $message->isOutbound()) {
            return false;
        }

        $context = (array) $message->context;
        $requested = Arr::get($context, 'fallback_sms');

        if ($requested === false || Arr::has($context, 'sms_fallback.status')) {
            return false;
        }

        // Free-form text only falls back when the caller asked for it explicitly.
        if ($message->type === 'text' && $requested !== true) {
            return false;
        }

        if ($requested !== true && ! WhatsappSettings::smsFallback()) {
            return false;
        }

        if (! static::smsAvailable()) {
            $context['sms_fallback'] = ['status' => 'unavailable', 'at' => Carbon::now()->toIso8601String()];
            $message->context = $context;
            $message->save();

            return false;
        }

        $text = $this->smsText($message);

        if ($text === '') {
            return false;
        }

        try {
            $smsContext = array_filter([
                'template_key' => $message->template_key,
                'source' => 'whatsapp_fallback',
                'whatsapp_message_id' => $message->getKey(),
                'related' => $message->related_type && $message->related_id ? $message->related : null,
            ]);

            $log = app(\MainaSuperintelligence\Sms\Services\SmsService::class)->send(
                '+' . ltrim($message->phone, '+'),
                $text,
                $smsContext,
                (bool) Arr::get($context, 'urgent', false)
            );

            $logId = $log instanceof Model ? $log->getKey() : null;

            $smsStatus = $log instanceof Model ? $log->getAttribute('status') : null;

            $context['sms_fallback'] = array_filter([
                'status' => 'sent',
                'sms_log_id' => $logId,
                'sms_status' => is_object($smsStatus) && method_exists($smsStatus, 'getValue') ? $smsStatus->getValue() : $smsStatus,
                'at' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $exception) {
            BaseHelper::logError($exception);

            $context['sms_fallback'] = [
                'status' => 'error',
                'error' => Str::limit($exception->getMessage(), 300),
                'at' => Carbon::now()->toIso8601String(),
            ];
        }

        $message->context = $context;
        $message->save();

        return Arr::get($context, 'sms_fallback.status') === 'sent';
    }

    public static function smsAvailable(): bool
    {
        return function_exists('is_plugin_active')
            && is_plugin_active('sms')
            && class_exists(\MainaSuperintelligence\Sms\Services\SmsService::class);
    }

    public function isWithinWindow(string $phone): bool
    {
        $contact = WhatsappContact::query()->where('phone', $phone)->first();

        return $contact && $contact->isWithinWindow();
    }

    public function contactFor(string $phone, ?string $name = null, ?int $customerId = null): WhatsappContact
    {
        $contact = WhatsappContact::query()->firstOrNew(['phone' => $phone]);

        if ($name && ! $contact->name) {
            $contact->name = $name;
        }

        if ($customerId && ! $contact->customer_id) {
            $contact->customer_id = $customerId;
        }

        if (! $contact->customer_id) {
            $contact->customer_id = $this->findCustomerId($phone);
        }

        if ($contact->isDirty() || ! $contact->exists) {
            $contact->save();
        }

        return $contact;
    }

    /**
     * Fetch the approved templates from Meta and store them for the mapping page.
     *
     * @return array{total: int, approved: int}
     */
    public function syncTemplates(): array
    {
        $templates = $this->client->listTemplates();
        $seen = [];
        $approved = 0;

        foreach ($templates as $template) {
            $name = (string) Arr::get($template, 'name');
            $language = (string) Arr::get($template, 'language');

            if ($name === '' || $language === '') {
                continue;
            }

            $components = (array) Arr::get($template, 'components', []);
            $body = null;
            $hasUrlButton = false;

            foreach ($components as $component) {
                $type = strtoupper((string) Arr::get($component, 'type'));

                if ($type === 'BODY') {
                    $body = (string) Arr::get($component, 'text');
                }

                if ($type === 'BUTTONS') {
                    foreach ((array) Arr::get($component, 'buttons', []) as $button) {
                        if (strtoupper((string) Arr::get($button, 'type')) === 'URL' && str_contains((string) Arr::get($button, 'url'), '{{')) {
                            $hasUrlButton = true;
                        }
                    }
                }
            }

            $row = WhatsappTemplate::query()->firstOrNew(['name' => $name, 'language' => $language]);
            $row->fill([
                'meta_id' => Arr::get($template, 'id'),
                'status' => Arr::get($template, 'status'),
                'category' => Arr::get($template, 'category'),
                'body' => $body,
                'body_variables' => count(static::placeholders((string) $body)),
                'has_url_button' => $hasUrlButton,
                'components' => $components,
                'synced_at' => Carbon::now(),
            ]);
            $row->save();

            $seen[] = $row->getKey();

            if ($row->isApproved()) {
                $approved++;
            }
        }

        WhatsappTemplate::query()->whereNotIn('id', $seen ?: [0])->delete();

        return ['total' => count($seen), 'approved' => $approved];
    }

    /**
     * Placeholders in a template body in order of appearance ({{1}}, {{2}} or named {{first_name}}).
     */
    public static function placeholders(string $text): array
    {
        preg_match_all('/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/', $text, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    public function templatePayload(string $phone, string $name, string $language, array $body, ?string $button): array
    {
        $components = [];

        if ($body) {
            $named = $this->namedParameters($name, $language);

            $components[] = [
                'type' => 'body',
                'parameters' => array_map(function ($value, $index) use ($named) {
                    $parameter = ['type' => 'text', 'text' => (string) $value];

                    if ($named && isset($named[$index])) {
                        $parameter['parameter_name'] = $named[$index];
                    }

                    return $parameter;
                }, $body, array_keys($body)),
            ];
        }

        if ($button !== null && $button !== '') {
            $components[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [['type' => 'text', 'text' => $button]],
            ];
        }

        $template = [
            'name' => $name,
            'language' => ['code' => $language],
        ];

        if ($components) {
            $template['components'] = $components;
        }

        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phone,
            'type' => 'template',
            'template' => $template,
        ];
    }

    public function textPayload(string $phone, string $text): array
    {
        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phone,
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $text],
        ];
    }

    /**
     * Today's outbound counters for the dashboard widget.
     */
    public function todayStats(): array
    {
        $start = Carbon::today();
        $query = fn () => WhatsappMessage::query()->where('direction', MessageDirectionEnum::OUT);

        return [
            'sent' => $query()->where('sent_at', '>=', $start)->count(),
            'delivered' => $query()->where('delivered_at', '>=', $start)->count(),
            'read' => $query()->where('read_at', '>=', $start)->count(),
            'failed' => $query()->where('failed_at', '>=', $start)->count(),
            'queued' => $query()->where('status', MessageStatusEnum::QUEUED)->count(),
            'received' => WhatsappMessage::query()
                ->where('direction', MessageDirectionEnum::IN)
                ->where('created_at', '>=', $start)
                ->count(),
        ];
    }

    protected function createOutbound(?string $phone, string $rawTo, array $attributes, array $context): WhatsappMessage
    {
        $related = Arr::get($context, 'related');
        $storedContext = Arr::except($context, ['related']);

        $contact = $phone
            ? $this->contactFor($phone, Arr::get($context, 'name'), Arr::get($context, 'customer_id') ? (int) Arr::get($context, 'customer_id') : null)
            : null;

        $message = new WhatsappMessage(array_merge([
            'direction' => MessageDirectionEnum::OUT,
            'contact_id' => $contact?->getKey(),
            'phone' => $phone ?: Str::limit(preg_replace('/[^0-9+]/', '', $rawTo) ?: 'invalid', 20, ''),
            'to' => $phone,
            'from' => WhatsappSettings::phoneNumberId(),
            'status' => MessageStatusEnum::QUEUED,
            'context' => $storedContext ?: null,
        ], $attributes));

        if ($related instanceof Model && $related->exists) {
            $message->related()->associate($related);
        } elseif (is_string(Arr::get($context, 'related_type')) && is_scalar(Arr::get($context, 'related_id'))) {
            $message->related_type = Arr::get($context, 'related_type');
            $message->related_id = Arr::get($context, 'related_id');
        }

        $message->save();

        return $message;
    }

    protected function queue(WhatsappMessage $message): WhatsappMessage
    {
        try {
            app(Dispatcher::class)->dispatch((new SendWhatsappMessageJob($message->getKey()))->afterCommit());
        } catch (Throwable $exception) {
            // With the sync queue an unexpected error surfaces here; never break the caller.
            BaseHelper::logError($exception);

            $message->refresh();

            if ($message->statusValue() === MessageStatusEnum::QUEUED) {
                $this->markFailed($message, 'exception', $exception->getMessage());
            }
        }

        return $message->refresh();
    }

    protected function reject(WhatsappMessage $message, string $code, string $error): WhatsappMessage
    {
        return $this->markFailed($message, $code, $error)->refresh();
    }

    protected function isOptedOut(WhatsappMessage $message): bool
    {
        return WhatsappContact::query()
            ->where('phone', $message->phone)
            ->where('opted_out', true)
            ->exists();
    }

    protected function touchContact(WhatsappMessage $message, array $attributes): void
    {
        if (! $message->contact_id) {
            return;
        }

        WhatsappContact::query()->whereKey($message->contact_id)->update($attributes);
    }

    protected function findCustomerId(string $phone): ?int
    {
        if (! class_exists(\Botble\Ecommerce\Models\Customer::class) || ! function_exists('is_plugin_active') || ! is_plugin_active('ecommerce')) {
            return null;
        }

        try {
            if (! Schema::hasTable('ec_customers')) {
                return null;
            }

            $id = \Botble\Ecommerce\Models\Customer::query()
                ->whereIn('phone', PhoneNumber::variants($phone))
                ->value('id');

            return $id ? (int) $id : null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function namedParameters(string $name, string $language): ?array
    {
        try {
            $template = WhatsappTemplate::query()->where('name', $name)->where('language', $language)->first();
        } catch (Throwable) {
            return null;
        }

        if (! $template || ! $template->body) {
            return null;
        }

        $placeholders = static::placeholders($template->body);

        if (! $placeholders || ctype_digit(implode('', $placeholders))) {
            return null;
        }

        return $placeholders;
    }

    protected function smsText(WhatsappMessage $message): string
    {
        $context = (array) $message->context;

        if ($text = trim((string) Arr::get($context, 'sms_message'))) {
            return $text;
        }

        if ($message->template_key && $this->smsHasTemplate($message->template_key)) {
            try {
                $rendered = trim((string) app(\MainaSuperintelligence\Sms\Services\SmsService::class)
                    ->renderTemplate($message->template_key, (array) Arr::get($context, 'vars', [])));

                if ($rendered !== '') {
                    return $rendered;
                }
            } catch (Throwable $exception) {
                BaseHelper::logError($exception);
            }
        }

        return trim((string) $message->body);
    }

    protected function smsHasTemplate(string $key): bool
    {
        if (! method_exists(\MainaSuperintelligence\Sms\Services\SmsService::class, 'renderTemplate')) {
            return false;
        }

        $templates = (array) apply_filters('sms_templates', []);

        if (isset($templates[$key])) {
            return true;
        }

        foreach ($templates as $template) {
            if (is_array($template) && ($template['key'] ?? null) === $key) {
                return true;
            }
        }

        return false;
    }

    protected function scalarVars(array $vars): array
    {
        return array_map(
            fn ($value) => is_scalar($value) || $value === null ? $value : (string) json_encode($value),
            array_filter($vars, fn ($value) => is_scalar($value) || $value === null || is_array($value))
        );
    }
}
