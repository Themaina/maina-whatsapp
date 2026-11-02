<?php

namespace MainaSuperintelligence\Whatsapp\Services;

use MainaSuperintelligence\Whatsapp\Enums\MessageDirectionEnum;
use MainaSuperintelligence\Whatsapp\Enums\MessageStatusEnum;
use MainaSuperintelligence\Whatsapp\Events\WhatsappContactOptedOut;
use MainaSuperintelligence\Whatsapp\Events\WhatsappMessageReceived;
use MainaSuperintelligence\Whatsapp\Events\WhatsappMessageStatusUpdated;
use MainaSuperintelligence\Whatsapp\Models\WhatsappContact;
use MainaSuperintelligence\Whatsapp\Models\WhatsappMessage;
use MainaSuperintelligence\Whatsapp\Supports\WhatsappSettings;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Applies a verified webhook payload from Meta: delivery statuses, inbound messages, STOP/START.
 */
class WebhookProcessor
{
    public function __construct(protected WhatsappService $service)
    {
    }

    /**
     * Signature check: X-Hub-Signature-256 = "sha256=" . hex(HMAC-SHA256(raw body, app secret)).
     */
    public static function signatureIsValid(string $rawBody, ?string $header, ?string $appSecret): bool
    {
        if (! $appSecret || ! $header || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $appSecret);

        return hash_equals($expected, strtolower(substr($header, 7)));
    }

    /**
     * @return array{statuses: int, messages: int, ignored: int}
     */
    public function process(array $payload): array
    {
        $stats = ['statuses' => 0, 'messages' => 0, 'ignored' => 0];

        if (Arr::get($payload, 'object') !== 'whatsapp_business_account') {
            $stats['ignored']++;

            return $stats;
        }

        $ourPhoneNumberId = WhatsappSettings::phoneNumberId();

        foreach ((array) Arr::get($payload, 'entry', []) as $entry) {
            foreach ((array) Arr::get($entry, 'changes', []) as $change) {
                if (Arr::get($change, 'field', 'messages') !== 'messages') {
                    $stats['ignored']++;

                    continue;
                }

                $value = (array) Arr::get($change, 'value', []);
                $metadata = (array) Arr::get($value, 'metadata', []);

                // A WABA can host several numbers; only handle ours.
                if ($ourPhoneNumberId && ($incomingId = Arr::get($metadata, 'phone_number_id')) && (string) $incomingId !== $ourPhoneNumberId) {
                    $stats['ignored']++;

                    continue;
                }

                $names = [];

                foreach ((array) Arr::get($value, 'contacts', []) as $contact) {
                    if ($waId = Arr::get($contact, 'wa_id')) {
                        $names[$waId] = Arr::get($contact, 'profile.name');
                    }
                }

                foreach ((array) Arr::get($value, 'statuses', []) as $status) {
                    $this->applyStatus((array) $status) ? $stats['statuses']++ : $stats['ignored']++;
                }

                foreach ((array) Arr::get($value, 'messages', []) as $message) {
                    $this->storeInbound((array) $message, $names, $metadata) ? $stats['messages']++ : $stats['ignored']++;
                }
            }
        }

        return $stats;
    }

    public function applyStatus(array $status): bool
    {
        $id = Arr::get($status, 'id');
        $newStatus = strtolower((string) Arr::get($status, 'status'));

        if (! $id || ! in_array($newStatus, ['sent', 'delivered', 'read', 'failed'], true)) {
            return false;
        }

        $message = WhatsappMessage::query()
            ->where('provider_message_id', $id)
            ->where('direction', MessageDirectionEnum::OUT)
            ->first();

        if (! $message) {
            return false;
        }

        $at = ($timestamp = Arr::get($status, 'timestamp')) && is_numeric($timestamp)
            ? Carbon::createFromTimestamp((int) $timestamp, config('app.timezone'))
            : Carbon::now();

        $current = $message->statusValue();
        $currentRank = MessageStatusEnum::rank($current);

        if ($newStatus === 'failed') {
            if (in_array($current, [MessageStatusEnum::DELIVERED, MessageStatusEnum::READ, MessageStatusEnum::FAILED], true)) {
                return false;
            }

            $error = (array) Arr::get($status, 'errors.0', []);
            $code = (string) (Arr::get($error, 'code') ?: 'failed');
            $text = Arr::get($error, 'error_data.details') ?: Arr::get($error, 'message') ?: Arr::get($error, 'title') ?: 'Delivery failed';

            $this->service->markFailed($message, $code, (string) $text);
            WhatsappMessageStatusUpdated::dispatch($message);

            return true;
        }

        $changes = [];

        if ($newStatus === 'sent' && ! $message->sent_at) {
            $changes['sent_at'] = $at;
        }

        if ($newStatus === 'delivered') {
            $changes['delivered_at'] = $message->delivered_at ?: $at;
            $changes['sent_at'] = $message->sent_at ?: $at;
        }

        if ($newStatus === 'read') {
            $changes['read_at'] = $message->read_at ?: $at;
            $changes['delivered_at'] = $message->delivered_at ?: $at;
            $changes['sent_at'] = $message->sent_at ?: $at;
        }

        // Never move backwards (Meta does not guarantee ordering) and never resurrect a failed message.
        if ($current !== MessageStatusEnum::FAILED && MessageStatusEnum::rank($newStatus) > $currentRank) {
            $changes['status'] = $newStatus;
        }

        if (! $changes) {
            return true;
        }

        $message->fill($changes);
        $message->save();

        WhatsappMessageStatusUpdated::dispatch($message);

        return true;
    }

    public function storeInbound(array $data, array $names = [], array $metadata = []): bool
    {
        $id = Arr::get($data, 'id');
        $from = (string) Arr::get($data, 'from');

        if (! $id || $from === '') {
            return false;
        }

        if (WhatsappMessage::query()->where('provider_message_id', $id)->exists()) {
            return false; // Meta retries webhooks; store each message once.
        }

        $phone = $this->service->normalisePhone('+' . ltrim($from, '+')) ?: Str::limit(preg_replace('/\D+/', '', $from), 20, '');

        $receivedAt = ($timestamp = Arr::get($data, 'timestamp')) && is_numeric($timestamp)
            ? Carbon::createFromTimestamp((int) $timestamp, config('app.timezone'))
            : Carbon::now();

        $contact = $this->service->contactFor($phone, Arr::get($names, $from));
        $contact->wa_id = $from;

        if (! $contact->last_inbound_at || $receivedAt->greaterThan($contact->last_inbound_at)) {
            $contact->last_inbound_at = $receivedAt;
        }

        if (($name = Arr::get($names, $from)) && $contact->name !== $name) {
            $contact->name = $name;
        }

        $contact->save();

        $type = (string) Arr::get($data, 'type', 'text');

        $message = new WhatsappMessage([
            'direction' => MessageDirectionEnum::IN,
            'contact_id' => $contact->getKey(),
            'phone' => $phone,
            'from' => $from,
            'to' => Arr::get($metadata, 'display_phone_number') ?: Arr::get($metadata, 'phone_number_id'),
            'type' => Str::limit($type, 20, ''),
            'body' => $this->extractBody($type, $data),
            'payload' => $data,
            'status' => MessageStatusEnum::RECEIVED,
            'provider_message_id' => $id,
            'context' => array_filter([
                'reply_to' => Arr::get($data, 'context.id'),
                'phone_number_id' => Arr::get($metadata, 'phone_number_id'),
            ]) ?: null,
        ]);
        $message->created_at = $receivedAt;
        $message->save();

        WhatsappMessageReceived::dispatch($message);

        $this->handleKeywords($contact, (string) $message->body, $type);

        return true;
    }

    protected function handleKeywords(WhatsappContact $contact, string $body, string $type): void
    {
        if (! in_array($type, ['text', 'button', 'interactive'], true)) {
            return;
        }

        $keyword = strtoupper(trim(preg_replace('/\s+/', ' ', $body) ?? '', " \t\n\r\0\x0B.!"));

        if ($keyword === '') {
            return;
        }

        $optOut = array_map('strtoupper', (array) config('plugins.whatsapp.general.opt_out_keywords', []));
        $optIn = array_map('strtoupper', (array) config('plugins.whatsapp.general.opt_in_keywords', []));

        if (in_array($keyword, $optOut, true)) {
            if (! $contact->opted_out) {
                $contact->forceFill(['opted_out' => true, 'opted_out_at' => Carbon::now()])->save();
                WhatsappContactOptedOut::dispatch($contact, true);
            }

            $this->confirm($contact, trans('plugins/whatsapp::whatsapp.keywords.opted_out_reply', ['site' => $this->siteName()]));

            return;
        }

        if (in_array($keyword, $optIn, true)) {
            if ($contact->opted_out) {
                $contact->forceFill(['opted_out' => false, 'opted_out_at' => null])->save();
                WhatsappContactOptedOut::dispatch($contact, false);
            }

            $this->confirm($contact, trans('plugins/whatsapp::whatsapp.keywords.opted_in_reply', ['site' => $this->siteName()]));
        }
    }

    protected function siteName(): string
    {
        return (string) (setting('site_title') ?: config('app.name'));
    }

    protected function confirm(WhatsappContact $contact, string $text): void
    {
        if (! WhatsappSettings::sendOptOutConfirmation() || ! WhatsappSettings::isConfigured()) {
            return;
        }

        $this->service->sendText($contact->phone, $text, [
            'force' => true,
            'ignore_opt_out' => true,
            'fallback_sms' => false,
            'system' => 'keyword_confirmation',
        ]);
    }

    protected function extractBody(string $type, array $data): ?string
    {
        $body = match ($type) {
            'text' => Arr::get($data, 'text.body'),
            'button' => Arr::get($data, 'button.text') ?: Arr::get($data, 'button.payload'),
            'interactive' => Arr::get($data, 'interactive.button_reply.title')
                ?: Arr::get($data, 'interactive.list_reply.title')
                ?: Arr::get($data, 'interactive.nfm_reply.body'),
            'image', 'video', 'document', 'sticker' => trim(sprintf(
                '[%s] %s',
                $type,
                Arr::get($data, $type . '.caption') ?: Arr::get($data, $type . '.filename', '')
            )),
            'audio' => '[audio]',
            'location' => sprintf(
                '[location] %s,%s %s',
                Arr::get($data, 'location.latitude'),
                Arr::get($data, 'location.longitude'),
                Arr::get($data, 'location.name', '')
            ),
            'reaction' => '[reaction] ' . Arr::get($data, 'reaction.emoji', ''),
            'contacts' => '[contact card]',
            default => '[' . $type . ']',
        };

        return $body !== null ? Str::limit(trim((string) $body), 4096, '') : null;
    }
}
