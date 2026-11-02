<?php

namespace MainaSuperintelligence\Whatsapp\Services;

use MainaSuperintelligence\Whatsapp\Exceptions\WhatsappException;
use MainaSuperintelligence\Whatsapp\Supports\WhatsappSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Thin wrapper around the Meta WhatsApp Business Cloud API (Graph API).
 */
class CloudApiClient
{
    /**
     * POST /{version}/{phone_number_id}/messages
     *
     * @return array{ok: bool, id: ?string, wa_id: ?string, http_status: int, error_code: ?string, error: ?string, retryable: bool, response: array}
     */
    public function sendMessage(array $payload): array
    {
        $phoneNumberId = WhatsappSettings::phoneNumberId();

        if (! $phoneNumberId || ! WhatsappSettings::accessToken()) {
            return $this->failure(0, 'not_configured', 'WhatsApp phone number ID or access token is not set.', false);
        }

        try {
            $response = $this->request()->post(WhatsappSettings::graphUrl($phoneNumberId . '/messages'), $payload);
        } catch (ConnectionException $exception) {
            return $this->failure(0, 'connection', $exception->getMessage(), true);
        } catch (Throwable $exception) {
            return $this->failure(0, 'exception', $exception->getMessage(), true);
        }

        $json = (array) $response->json();

        if ($response->successful() && ($id = Arr::get($json, 'messages.0.id'))) {
            return [
                'ok' => true,
                'id' => $id,
                'wa_id' => Arr::get($json, 'contacts.0.wa_id'),
                'http_status' => $response->status(),
                'error_code' => null,
                'error' => null,
                'retryable' => false,
                'response' => $json,
            ];
        }

        return $this->errorFromResponse($response, $json);
    }

    /**
     * GET /{version}/{waba_id}/message_templates (follows paging).
     *
     * @return array<int, array>
     */
    public function listTemplates(): array
    {
        $wabaId = WhatsappSettings::businessAccountId();

        if (! $wabaId || ! WhatsappSettings::accessToken()) {
            throw new WhatsappException('Set the WhatsApp Business Account ID and access token first.');
        }

        $url = WhatsappSettings::graphUrl($wabaId . '/message_templates');
        $query = ['fields' => 'name,language,status,category,components', 'limit' => 100];
        $templates = [];
        $pages = 0;

        while ($url && $pages < 20) {
            $response = $this->request()->get($url, $query);
            $json = (array) $response->json();

            if (! $response->successful()) {
                $error = $this->errorFromResponse($response, $json);

                throw new WhatsappException(sprintf('Meta returned %s: %s', $error['error_code'], $error['error']));
            }

            foreach ((array) Arr::get($json, 'data', []) as $template) {
                $templates[] = (array) $template;
            }

            // paging.next is a full URL that already carries the query string.
            $url = Arr::get($json, 'paging.next');
            $query = [];
            $pages++;
        }

        return $templates;
    }

    /**
     * Mark an inbound message as read (blue ticks). Failures are ignored.
     */
    public function markAsRead(string $providerMessageId): bool
    {
        $phoneNumberId = WhatsappSettings::phoneNumberId();

        if (! $phoneNumberId || ! WhatsappSettings::accessToken()) {
            return false;
        }

        try {
            return $this->request()->post(WhatsappSettings::graphUrl($phoneNumberId . '/messages'), [
                'messaging_product' => 'whatsapp',
                'status' => 'read',
                'message_id' => $providerMessageId,
            ])->successful();
        } catch (Throwable) {
            return false;
        }
    }

    protected function request(): PendingRequest
    {
        return Http::withToken((string) WhatsappSettings::accessToken())
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('plugins.whatsapp.general.timeout', 15));
    }

    protected function errorFromResponse(Response $response, array $json): array
    {
        $code = Arr::get($json, 'error.code');
        $message = Arr::get($json, 'error.error_data.details')
            ?: Arr::get($json, 'error.message')
            ?: ('HTTP ' . $response->status());

        if ($title = Arr::get($json, 'error.title')) {
            $message = $title . ': ' . $message;
        }

        $code = $code !== null ? (string) $code : 'http_' . $response->status();

        $retryable = $response->serverError()
            || $response->status() === 429
            || in_array((int) $code, (array) config('plugins.whatsapp.general.retryable_error_codes', []), true);

        return $this->failure($response->status(), $code, (string) $message, $retryable, $json);
    }

    protected function failure(int $status, string $code, string $message, bool $retryable, array $response = []): array
    {
        return [
            'ok' => false,
            'id' => null,
            'wa_id' => null,
            'http_status' => $status,
            'error_code' => $code,
            'error' => $message,
            'retryable' => $retryable,
            'response' => $response,
        ];
    }
}
