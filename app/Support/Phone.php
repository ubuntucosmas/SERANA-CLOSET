<?php

namespace App\Support;

/**
 * Canonical phone-number handling for the whole app. Customers type numbers in
 * every imaginable format ("+254 741 589 200", "0741589200", "741 589 200");
 * everything downstream — Daraja STK pushes, order-contact matching, wa.me
 * links — needs the same bare international digits. Normalize once, here.
 */
final class Phone
{
    /** Default country code applied to local-format numbers (Kenya). */
    public const DEFAULT_COUNTRY_CODE = '254';

    /**
     * Reduce a phone number to bare international digits.
     *
     * Examples (default country 254):
     *   "+254 741 589 200" → "254741589200"
     *   "0741589200"       → "254741589200"
     *   "741589200"        → "254741589200"
     */
    public static function normalize(?string $number, string $countryCode = self::DEFAULT_COUNTRY_CODE): string
    {
        $digits = preg_replace('/\D+/', '', (string) $number);

        if ($digits === '') {
            return '';
        }

        // Local format with a trunk '0' (e.g. 0741…) → swap for country code.
        if (str_starts_with($digits, '0')) {
            return $countryCode . ltrim($digits, '0');
        }

        // Bare subscriber number (no country code, no trunk 0) → prepend it.
        if (! str_starts_with($digits, $countryCode) && strlen($digits) <= 9) {
            return $countryCode . $digits;
        }

        return $digits;
    }

    /**
     * Is this a number Daraja can actually push an STK prompt to?
     * Kenyan mobiles are 254 7XX XXX XXX or 254 1XX XXX XXX.
     */
    public static function isValidKenyanMobile(?string $number): bool
    {
        return preg_match('/^254[17]\d{8}$/', self::normalize($number)) === 1;
    }
}
