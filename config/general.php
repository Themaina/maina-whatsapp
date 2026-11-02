<?php

return [
    // Base URL of the Meta Graph API. Override only for testing against a mock server.
    'graph_url' => env('WHATSAPP_GRAPH_URL', 'https://graph.facebook.com'),

    'default_api_version' => 'v21.0',

    // HTTP timeout (seconds) for Graph API calls.
    'timeout' => 15,

    // Queue used by SendWhatsappMessageJob (null = default queue).
    'queue' => env('WHATSAPP_QUEUE'),

    // Delivery attempts for transient failures (network errors, 5xx, rate limits).
    'tries' => 5,

    // Seconds to wait before each retry.
    'backoff' => [30, 120, 600, 1800],

    // Customer-service window during which free-form text is allowed.
    'window_hours' => 24,

    // Meta error codes that are worth retrying.
    'retryable_error_codes' => [1, 2, 4, 80007, 130429, 131000, 131016, 131048, 131056, 133004],

    // Inbound keywords (case-insensitive, whole message) that opt a contact out / back in.
    'opt_out_keywords' => ['STOP', 'STOPALL', 'STOP ALL', 'UNSUBSCRIBE', 'OPT OUT', 'OPTOUT'],
    'opt_in_keywords' => ['START', 'UNSTOP', 'SUBSCRIBE', 'OPT IN', 'OPTIN'],

    // Default country calling code for local numbers when the setting is empty (e.g. '254').
    // Empty: use the ecommerce store country, or require international numbers.
    'default_country_code' => env('WHATSAPP_DEFAULT_COUNTRY_CODE', ''),
];
