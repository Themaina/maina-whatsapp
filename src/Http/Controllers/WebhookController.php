<?php

namespace MainaSuperintelligence\Whatsapp\Http\Controllers;

use Botble\Base\Facades\BaseHelper;
use MainaSuperintelligence\Whatsapp\Services\WebhookProcessor;
use MainaSuperintelligence\Whatsapp\Supports\WhatsappSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Throwable;

class WebhookController extends Controller
{
    /**
     * Meta's verification handshake: GET ?hub.mode=subscribe&hub.verify_token=...&hub.challenge=...
     * PHP turns the dots in query keys into underscores, so read both spellings from the raw query string.
     */
    public function verify(Request $request): Response
    {
        parse_str((string) $request->server('QUERY_STRING', ''), $raw);

        $mode = $request->query('hub_mode', $raw['hub_mode'] ?? null);
        $token = $request->query('hub_verify_token', $raw['hub_verify_token'] ?? null);
        $challenge = $request->query('hub_challenge', $raw['hub_challenge'] ?? null);

        $expected = WhatsappSettings::verifyToken();

        if (
            $mode === 'subscribe'
            && is_string($token)
            && $expected
            && hash_equals($expected, $token)
            && is_scalar($challenge)
        ) {
            return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request, WebhookProcessor $processor): JsonResponse|Response
    {
        $raw = $request->getContent();

        if (! WebhookProcessor::signatureIsValid($raw, $request->header('X-Hub-Signature-256'), WhatsappSettings::appSecret())) {
            return response('Invalid signature', 403)->header('Content-Type', 'text/plain');
        }

        $payload = json_decode($raw, true);

        if (! is_array($payload)) {
            return response('Invalid payload', 400)->header('Content-Type', 'text/plain');
        }

        try {
            $stats = $processor->process($payload);
        } catch (Throwable $exception) {
            // Answer 500 so Meta retries the delivery later.
            BaseHelper::logError($exception);

            return response()->json(['error' => true, 'message' => 'Processing failed'], 500);
        }

        return response()->json(['error' => false, 'data' => $stats]);
    }
}
