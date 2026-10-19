<?php

namespace App\Support;

use App\Models\ThemeSetting;

/**
 * Order policy defaults the owner edits in Studio → Settings (design §2.4):
 * the bespoke deposit share, how long a customer pay link works, and the
 * deposit refund rule shown on the quote, the pay page and every receipt.
 *
 * Stored as one JSON value in theme_settings under self::KEY.
 */
class OrderPolicy
{
    public const KEY = 'order_policy';

    public const DEFAULT_DEPOSIT_PERCENT = 50;
    public const DEFAULT_PAY_LINK_DAYS = 14;
    /** Working days the studio promises to send an owed refund within (Phase C). */
    public const DEFAULT_REFUND_WORKING_DAYS = 2;
    /** Days a payment problem may wait for a decision before it becomes a refund (Phase C). */
    public const DEFAULT_RESOLUTION_DAYS = 3;
    public const DEFAULT_DEPOSIT_POLICY = 'Deposit refundable until work starts (designing); non-refundable after.';

    private static function saved(): array
    {
        $saved = json_decode((string) (ThemeSetting::values()[self::KEY] ?? ''), true);

        return is_array($saved) ? $saved : [];
    }

    /** Share of a bespoke quote taken as a deposit, 0–100. */
    public static function depositPercent(): int
    {
        return (int) (self::saved()['deposit_percent'] ?? self::DEFAULT_DEPOSIT_PERCENT);
    }

    /** Days a customer pay link (/pay/{order}) stays valid. */
    public static function payLinkDays(): int
    {
        return (int) (self::saved()['pay_link_days'] ?? self::DEFAULT_PAY_LINK_DAYS);
    }

    public static function refundWorkingDays(): int
    {
        return (int) (self::saved()['refund_working_days'] ?? self::DEFAULT_REFUND_WORKING_DAYS);
    }

    public static function resolutionDays(): int
    {
        return (int) (self::saved()['resolution_days'] ?? self::DEFAULT_RESOLUTION_DAYS);
    }

    public static function depositPolicy(): string
    {
        return trim((string) (self::saved()['deposit_policy'] ?? '')) ?: self::DEFAULT_DEPOSIT_POLICY;
    }

    public static function forStudio(): array
    {
        return [
            'deposit_percent' => self::depositPercent(),
            'pay_link_days' => self::payLinkDays(),
            'deposit_policy' => self::depositPolicy(),
            'refund_working_days' => self::refundWorkingDays(),
            'resolution_days' => self::resolutionDays(),
        ];
    }

    public static function rules(): array
    {
        return [
            'deposit_percent' => ['required', 'integer', 'between:0,100'],
            'pay_link_days' => ['required', 'integer', 'between:1,90'],
            'deposit_policy' => ['nullable', 'string', 'max:300'],
            'refund_working_days' => ['nullable', 'integer', 'between:1,14'],
            'resolution_days' => ['nullable', 'integer', 'between:1,30'],
        ];
    }

    public static function save(array $validated): void
    {
        ThemeSetting::set(self::KEY, json_encode([
            'deposit_percent' => (int) $validated['deposit_percent'],
            'pay_link_days' => (int) $validated['pay_link_days'],
            'deposit_policy' => trim((string) ($validated['deposit_policy'] ?? '')),
            'refund_working_days' => (int) ($validated['refund_working_days'] ?? self::DEFAULT_REFUND_WORKING_DAYS),
            'resolution_days' => (int) ($validated['resolution_days'] ?? self::DEFAULT_RESOLUTION_DAYS),
        ]));
    }
}
