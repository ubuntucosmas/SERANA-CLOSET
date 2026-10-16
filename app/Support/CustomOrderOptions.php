<?php

namespace App\Support;

use App\Models\ThemeSetting;

/**
 * The choices offered on the Custom Order form, managed in Studio → Settings.
 *
 * Garments are deliberately fixed for now to the three shapes the studio makes
 * (hoodie & jogger sets and the two single pieces). Their keys match products'
 * `garment_type`, so "Want it made to your measurements?" pre-selects the right
 * one. The owner can rename them or switch them off, not add new types.
 * Fabrics and colours are free lists the owner can grow, reorder and trim.
 *
 * Stored as one JSON value in theme_settings under self::KEY.
 */
class CustomOrderOptions
{
    public const KEY = 'custom_order_options';

    /** key => default label, in display order. */
    public const GARMENTS = [
        'set' => 'Hoodie & Jogger Set',
        'hoodie' => 'Hoodie',
        'jogger' => 'Joggers',
    ];

    private const DEFAULT_FABRICS = ['Premium Cotton', 'Cotton Fleece', 'French Terry', 'Brushed Fleece'];

    private const DEFAULT_COLOURS = [
        ['name' => 'Midnight', 'hex' => '#0a0a0a'],
        ['name' => 'Optic White', 'hex' => '#f8f8f8'],
        ['name' => 'Electric Cyan', 'hex' => '#57d5fc'],
        ['name' => 'Desert Sand', 'hex' => '#e2cba3'],
        ['name' => 'Deep Navy', 'hex' => '#0f172a'],
        ['name' => 'Charcoal', 'hex' => '#1f2937'],
    ];

    /** Everything, including switched-off garments (for the Studio editor). */
    public static function all(): array
    {
        $saved = json_decode((string) (ThemeSetting::values()[self::KEY] ?? ''), true);
        $saved = is_array($saved) ? $saved : [];

        $savedGarments = collect($saved['garments'] ?? [])->keyBy('key');
        $garments = collect(self::GARMENTS)->map(function (string $label, string $key) use ($savedGarments) {
            $row = $savedGarments->get($key, []);
            return [
                'key' => $key,
                'label' => trim((string) ($row['label'] ?? '')) ?: $label,
                'enabled' => (bool) ($row['enabled'] ?? true),
            ];
        })->values()->all();

        return [
            'garments' => $garments,
            'fabrics' => array_key_exists('fabrics', $saved)
                ? array_values(array_map(fn ($f) => (string) ($f['name'] ?? $f), $saved['fabrics']))
                : self::DEFAULT_FABRICS,
            'colours' => array_key_exists('colours', $saved)
                ? array_values($saved['colours'])
                : self::DEFAULT_COLOURS,
        ];
    }

    /** What the customer sees: only garments that are switched on. */
    public static function forCustomers(): array
    {
        $all = self::all();
        $all['garments'] = array_values(array_filter($all['garments'], fn ($g) => $g['enabled']));

        return $all;
    }

    /** Validation rules for the Studio editor. */
    public static function rules(): array
    {
        return [
            'garments' => ['required', 'array', 'size:' . count(self::GARMENTS)],
            'garments.*.key' => ['required', 'distinct', 'in:' . implode(',', array_keys(self::GARMENTS))],
            'garments.*.label' => ['required', 'string', 'max:60'],
            'garments.*.enabled' => ['required', 'boolean'],
            'fabrics' => ['present', 'array', 'max:20'],
            'fabrics.*' => ['required', 'string', 'max:60', 'distinct:ignore_case'],
            'colours' => ['present', 'array', 'max:20'],
            'colours.*.name' => ['required', 'string', 'max:40', 'distinct:ignore_case'],
            'colours.*.hex' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    public static function save(array $validated): void
    {
        ThemeSetting::set(self::KEY, json_encode([
            'garments' => array_map(fn ($g) => ['key' => $g['key'], 'label' => trim($g['label']), 'enabled' => (bool) $g['enabled']], $validated['garments']),
            'fabrics' => array_map(fn ($f) => ['name' => trim($f)], $validated['fabrics']),
            'colours' => array_map(fn ($c) => ['name' => trim($c['name']), 'hex' => strtolower($c['hex'])], $validated['colours']),
        ]));
    }

    /** Labels a customer may submit right now, per field. */
    public static function allowedValues(): array
    {
        $options = self::forCustomers();

        return [
            'outfit_type' => array_column($options['garments'], 'label'),
            'fabric_preference' => $options['fabrics'],
            'selected_color' => array_column($options['colours'], 'name'),
        ];
    }
}
