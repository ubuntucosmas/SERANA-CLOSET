<?php

namespace App\Support;

/**
 * WhatsApp click-to-chat helpers.
 *
 * The wa.me deep link is unforgiving: the number must be bare international
 * digits — no '+', spaces, dashes or leading '0'. The store number is set by
 * an admin in the studio dashboard, so it may arrive in any human format
 * ("+254 741 589 200", "0741589200", "0741-589-200"). normalizeNumber() makes
 * every link robust regardless of how it was typed.
 */
final class WhatsApp
{
    /** Default country code applied to local-format numbers (Kenya). */
    public const DEFAULT_COUNTRY_CODE = '254';

    /**
     * Reduce a phone number to the bare international digits wa.me expects.
     *
     * Examples (default country 254):
     *   "+254 741 589 200" → "254741589200"
     *   "0741589200"       → "254741589200"
     *   "741589200"        → "254741589200"
     */
    public static function normalizeNumber(?string $number, string $countryCode = self::DEFAULT_COUNTRY_CODE): string
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
     * Build a click-to-chat URL with a pre-filled, URI-safe message.
     */
    public static function link(?string $number, string $message): string
    {
        return 'https://wa.me/' . self::normalizeNumber($number)
            . '?text=' . rawurlencode($message);
    }
}
