<?php

namespace App\Support;

use App\Models\ThemeSetting;

/**
 * Which stock model the shop sells from (design §13.5): the old
 * product-level batch numbers, or stock per size and location (v2).
 */
final class StockMode
{
    /** The Studio setting the cutover turns on. */
    public const SETTING = 'stock_v2';

    public static function v2(): bool
    {
        return match ((string) config('serana.stock_v2', 'setting')) {
            'on', 'true', '1' => true,
            'off', 'false', '0' => false,
            default => ThemeSetting::get(self::SETTING) === '1',
        };
    }
}
