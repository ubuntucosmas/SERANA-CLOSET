<?php

namespace App\Support;

use App\Models\ThemeSetting;

/**
 * Shop policy the owner edits in Studio → Settings (design §2.4, §15.6, D4):
 * the cashier's discount limit (a percent and a KES cap, whichever is
 * lower), the till variance that needs a manager, how long a counter sale
 * holds stock, how long the till stays unlocked when idle, and the receipt
 * footer. One JSON value in theme_settings.
 */
class ShopPolicy
{
    public const KEY = 'shop_policy';

    public const DEFAULTS = [
        'cashier_discount_percent' => 10,
        'cashier_discount_cap' => 500,
        'till_variance_alert' => 200,
        'pos_hold_minutes' => 30,
        'pos_idle_minutes' => 10,
        'receipt_footer' => '',
    ];

    private static function saved(): array
    {
        $saved = json_decode((string) (ThemeSetting::values()[self::KEY] ?? ''), true);

        return is_array($saved) ? $saved : [];
    }

    private static function get(string $key): mixed
    {
        return self::saved()[$key] ?? self::DEFAULTS[$key];
    }

    public static function cashierDiscountPercent(): int { return (int) self::get('cashier_discount_percent'); }
    public static function cashierDiscountCap(): int { return (int) self::get('cashier_discount_cap'); }
    public static function tillVarianceAlert(): int { return (int) self::get('till_variance_alert'); }
    public static function posHoldMinutes(): int { return (int) self::get('pos_hold_minutes'); }
    public static function posIdleMinutes(): int { return (int) self::get('pos_idle_minutes'); }
    public static function receiptFooter(): string { return trim((string) self::get('receipt_footer')); }

    /** The most a cashier may take off a sale without a manager (D4): the lower of the percent and the cap. */
    public static function cashierDiscountLimit(int $subtotal): int
    {
        return min((int) floor($subtotal * self::cashierDiscountPercent() / 100), self::cashierDiscountCap());
    }

    public static function forStudio(): array
    {
        return array_combine(array_keys(self::DEFAULTS), array_map(fn ($k) => self::get($k), array_keys(self::DEFAULTS)));
    }

    public static function rules(): array
    {
        return [
            'cashier_discount_percent' => ['sometimes', 'integer', 'between:0,100'],
            'cashier_discount_cap' => ['sometimes', 'integer', 'between:0,1000000'],
            'till_variance_alert' => ['sometimes', 'integer', 'between:0,1000000'],
            'pos_hold_minutes' => ['sometimes', 'integer', 'between:5,240'],
            'pos_idle_minutes' => ['sometimes', 'integer', 'between:1,120'],
            'receipt_footer' => ['sometimes', 'nullable', 'string', 'max:300'],
        ];
    }

    /** Saves the given keys over what is saved (partial updates keep the rest). */
    public static function save(array $validated): void
    {
        $values = array_merge(self::forStudio(), $validated);
        $values['receipt_footer'] = trim((string) ($values['receipt_footer'] ?? ''));
        ThemeSetting::set(self::KEY, json_encode(array_intersect_key($values, self::DEFAULTS)));
    }
}
