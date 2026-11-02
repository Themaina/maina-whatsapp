<?php

namespace Maina888\Whatsapp\Supports;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Typed access to the plugin settings. The access token and app secret are stored encrypted
 * (Crypt::encryptString) in the settings table and only decrypted here, in memory.
 */
class WhatsappSettings
{
    public const ENCRYPTED_KEYS = ['whatsapp_access_token', 'whatsapp_app_secret'];

    public static function enabled(): bool
    {
        return (bool) setting('whatsapp_enabled', false);
    }

    public static function apiVersion(): string
    {
        $version = trim((string) setting('whatsapp_api_version', config('plugins.whatsapp.general.default_api_version', 'v21.0')));

        if ($version === '') {
            $version = 'v21.0';
        }

        return str_starts_with($version, 'v') ? $version : 'v' . $version;
    }

    public static function phoneNumberId(): ?string
    {
        return trim((string) setting('whatsapp_phone_number_id')) ?: null;
    }

    public static function businessAccountId(): ?string
    {
        return trim((string) setting('whatsapp_business_account_id')) ?: null;
    }

    public static function accessToken(): ?string
    {
        return static::decrypted('whatsapp_access_token');
    }

    public static function appSecret(): ?string
    {
        return static::decrypted('whatsapp_app_secret');
    }

    public static function verifyToken(): ?string
    {
        return trim((string) setting('whatsapp_verify_token')) ?: null;
    }

    public static function defaultLanguage(): string
    {
        return trim((string) setting('whatsapp_default_language', 'en')) ?: 'en';
    }

    /**
     * Calling codes used when the setting is empty and the ecommerce store country is one of these.
     */
    public const CALLING_CODES = [
        'KE' => '254', 'UG' => '256', 'TZ' => '255', 'RW' => '250', 'NG' => '234', 'GH' => '233',
        'ZA' => '27', 'ZM' => '260', 'MW' => '265', 'ET' => '251', 'EG' => '20', 'MA' => '212',
        'IN' => '91', 'PK' => '92', 'BD' => '880', 'ID' => '62', 'PH' => '63', 'MY' => '60',
        'VN' => '84', 'TH' => '66', 'BR' => '55', 'MX' => '52', 'CO' => '57', 'AR' => '54',
        'GB' => '44', 'DE' => '49', 'FR' => '33', 'ES' => '34', 'IT' => '39', 'NL' => '31',
        'TR' => '90', 'AE' => '971', 'SA' => '966', 'US' => '1', 'CA' => '1', 'AU' => '61',
    ];

    /**
     * Country calling code added to local numbers ("07XX..."): the setting, then the config
     * value, then the ecommerce store country. Empty means numbers must be international.
     */
    public static function defaultCountryCode(): string
    {
        $code = preg_replace('/\D+/', '', (string) setting('whatsapp_default_country_code', ''))
            ?: preg_replace('/\D+/', '', (string) config('plugins.whatsapp.general.default_country_code', ''));

        if ($code) {
            return $code;
        }

        if (function_exists('get_ecommerce_setting') && function_exists('is_plugin_active') && is_plugin_active('ecommerce')) {
            return self::CALLING_CODES[strtoupper((string) get_ecommerce_setting('store_country'))] ?? '';
        }

        return '';
    }

    public static function smsFallback(): bool
    {
        return (bool) setting('whatsapp_sms_fallback', true);
    }

    public static function sendOptOutConfirmation(): bool
    {
        return (bool) setting('whatsapp_opt_out_confirmation', true);
    }

    public static function isConfigured(): bool
    {
        return static::phoneNumberId() && static::accessToken();
    }

    public static function graphUrl(string $path): string
    {
        return rtrim((string) config('plugins.whatsapp.general.graph_url', 'https://graph.facebook.com'), '/')
            . '/' . static::apiVersion() . '/' . ltrim($path, '/');
    }

    public static function encrypt(string $value): string
    {
        return Crypt::encryptString($value);
    }

    public static function decrypted(string $key): ?string
    {
        $value = setting($key);

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            // A value stored without encryption (e.g. seeded directly) is still usable.
            return $value;
        }
    }

    public static function hasValue(string $key): bool
    {
        return (bool) static::decrypted($key);
    }

    /**
     * Mapping of a template key to the approved Meta template, plus whether the event is enabled.
     *
     * @return array{enabled: bool, name: ?string, language: string}
     */
    public static function templateMapping(string $key, array $definition = []): array
    {
        $name = trim((string) setting(static::templateSettingKey($key, 'name'), ''));
        $language = trim((string) setting(static::templateSettingKey($key, 'language'), ''));

        return [
            'enabled' => (bool) setting(static::templateSettingKey($key, 'enabled'), $definition['enabled'] ?? false),
            'name' => $name !== '' ? $name : ($definition['template_name'] ?? null),
            'language' => $language !== '' ? $language : ($definition['language'] ?? static::defaultLanguage()),
        ];
    }

    public static function templateSettingKey(string $key, string $field): string
    {
        return 'whatsapp_tpl_' . $key . '_' . $field;
    }
}
