<?php

namespace MainaSuperintelligence\Whatsapp\Supports;

/**
 * Normalises phone numbers to the format the Cloud API expects: country code + number,
 * digits only, no "+" (e.g. 2547XXXXXXXX).
 *
 * Local numbers (leading 0) get the default country code. Numbers written with an explicit
 * international prefix (+ or 00) are accepted when they have 8 to 15 digits (E.164).
 * Kenyan numbers (254) are checked strictly: mobile ranges 7XXXXXXXX and 1XXXXXXXX only, and the
 * common "+254 07XX" typo and a missing trunk zero are corrected. Without a default country code,
 * only international numbers are accepted.
 */
class PhoneNumber
{
    public static function normalise(?string $input, string $defaultCountryCode = ''): ?string
    {
        if ($input === null) {
            return null;
        }

        $input = trim($input);

        if ($input === '') {
            return null;
        }

        // Reject anything containing letters (e.g. "call me", "n/a").
        if (preg_match('/[a-z]/i', $input)) {
            return null;
        }

        $international = str_starts_with($input, '+');
        $digits = preg_replace('/\D+/', '', $input) ?? '';

        if (! $international && str_starts_with($digits, '00')) {
            $international = true;
            $digits = substr($digits, 2);
        }

        if ($digits === '') {
            return null;
        }

        $defaultCountryCode = preg_replace('/\D+/', '', $defaultCountryCode) ?: '';

        if (! $international && $defaultCountryCode === '') {
            // No default country: a number without its country code cannot be completed.
            if (str_starts_with($digits, '0') || strlen($digits) < 10) {
                return null;
            }

            $international = true;
        }

        if (! $international) {
            if (str_starts_with($digits, '0')) {
                // Local trunk prefix: 07XXXXXXXX -> 2547XXXXXXXX
                $digits = $defaultCountryCode . substr($digits, 1);
            } elseif (! str_starts_with($digits, $defaultCountryCode) && $defaultCountryCode === '254' && strlen($digits) === 9) {
                // Missing trunk prefix: 7XXXXXXXX -> 2547XXXXXXXX
                $digits = $defaultCountryCode . $digits;
            }
        }

        if (str_starts_with($digits, '254')) {
            $national = substr($digits, 3);

            // People often write "+254 07XX XXX XXX".
            if (strlen($national) === 10 && str_starts_with($national, '0')) {
                $national = substr($national, 1);
            }

            if (preg_match('/^[17]\d{8}$/', $national)) {
                return '254' . $national;
            }

            return null;
        }

        if (strlen($digits) >= 8 && strlen($digits) <= 15 && $digits[0] !== '0') {
            return $international || ($defaultCountryCode !== '' && $defaultCountryCode !== '254') ? $digits : null;
        }

        return null;
    }

    /**
     * Variants of a normalised number as people tend to store them, used to match customers.
     */
    public static function variants(string $normalised): array
    {
        $variants = [$normalised, '+' . $normalised];

        if (str_starts_with($normalised, '254') && strlen($normalised) === 12) {
            $national = substr($normalised, 3);
            $variants[] = '0' . $national;
            $variants[] = $national;
            $variants[] = '+254 ' . $national;
            $variants[] = '0' . substr($national, 0, 3) . ' ' . substr($national, 3, 3) . ' ' . substr($national, 6);
        }

        return array_values(array_unique($variants));
    }

    public static function mask(?string $phone): string
    {
        if (! $phone || strlen($phone) < 7) {
            return (string) $phone;
        }

        return substr($phone, 0, 6) . str_repeat('*', max(0, strlen($phone) - 9)) . substr($phone, -3);
    }
}
