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
    public const DEFAULT_COUNTRY_CODE = Phone::DEFAULT_COUNTRY_CODE;

    /**
     * Reduce a phone number to the bare international digits wa.me expects.
     * Delegates to the app-wide canonical normalizer.
     */
    public static function normalizeNumber(?string $number, string $countryCode = self::DEFAULT_COUNTRY_CODE): string
    {
        return Phone::normalize($number, $countryCode);
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
