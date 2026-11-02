<?php

use Maina888\Whatsapp\Models\WhatsappMessage;
use Maina888\Whatsapp\Services\WhatsappService;

if (! function_exists('whatsapp_send_template')) {
    /**
     * Queue a WhatsApp template message. See WhatsappService::sendTemplate() for the context keys.
     *
     * @param  array  $vars  Variables by name (as registered in the `whatsapp_templates` filter) or positional.
     */
    function whatsapp_send_template(string $to, string $key, array $vars = [], array $context = []): WhatsappMessage
    {
        return app(WhatsappService::class)->sendTemplate($to, $key, $vars, $context);
    }
}

if (! function_exists('whatsapp_send_text')) {
    /**
     * Queue a free-form WhatsApp text (only inside the 24-hour customer service window).
     */
    function whatsapp_send_text(string $to, string $text, array $context = []): WhatsappMessage
    {
        return app(WhatsappService::class)->sendText($to, $text, $context);
    }
}

if (! function_exists('whatsapp_normalise_phone')) {
    function whatsapp_normalise_phone(string $phone): ?string
    {
        return app(WhatsappService::class)->normalisePhone($phone);
    }
}
