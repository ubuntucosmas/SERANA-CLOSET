<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One size of one product, per part (design §13.1): a single piece, or the
 * top or bottom of a set (D7 a). Never deleted, only deactivated, so its
 * history stays. SKU and barcode are generated once and never reused.
 */
class ProductVariant extends Model
{
    public const SINGLE = 'single';
    public const TOP = 'top';
    public const BOTTOM = 'bottom';

    /** Today's storefront default when a product doesn't list its sizes. */
    public const DEFAULT_SIZES = ['S', 'M', 'L', 'XL', 'XXL'];
    public const ONE_SIZE = 'One size';

    protected $guarded = [];

    protected $casts = ['active' => 'boolean', 'sort' => 'integer'];

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function levels()
    {
        return $this->hasMany(StockLevel::class);
    }

    /** The parts a garment is sized by, as the product page asks for them. */
    public static function partsFor(?string $garmentType): array
    {
        return match ($garmentType) {
            'set' => [self::TOP, self::BOTTOM],
            'jogger' => [self::BOTTOM],
            default => [self::SINGLE],
        };
    }

    /** The size option the cart sends for a part ("top_size", "bottom_size"). */
    public static function optionFor(string $part): array
    {
        return match ($part) {
            self::TOP => ['top_size'],
            self::BOTTOM => ['bottom_size'],
            default => ['top_size', 'bottom_size'], // a single piece is chosen in the first size row
        };
    }

    /** The sizes a product offers: its own list, else the storefront default. */
    public static function offeredSizes(Product $product): array
    {
        $specs = $product->specifications ?? [];
        $sizes = $specs['ui']['sizes'] ?? $specs['sizes'] ?? self::DEFAULT_SIZES;

        return array_values(array_unique(array_filter(array_map(fn ($s) => trim((string) $s), (array) $sizes), fn ($s) => $s !== '')));
    }

    /** "Size M", "Top M", "Bottom L" for labels and messages. */
    public function label(): string
    {
        return match ($this->part) {
            self::TOP => "Top {$this->size}",
            self::BOTTOM => "Bottom {$this->size}",
            default => $this->size === self::ONE_SIZE ? self::ONE_SIZE : "Size {$this->size}",
        };
    }

    public static function skuFor(Product $product, string $part, string $size): string
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $size)) ?: 'X';

        return sprintf('SRN-%04d-%s-%s', $product->id, ['single' => 'S', 'top' => 'T', 'bottom' => 'B'][$part], $code);
    }

    /** EAN-13 in the GS1 in-store range (prefix 2), from the variant id: scannable, never a real retail code. */
    public static function barcodeFor(int $id): string
    {
        $body = '20' . str_pad((string) $id, 10, '0', STR_PAD_LEFT);

        return $body . self::eanCheckDigit($body);
    }

    public static function eanCheckDigit(string $twelve): int
    {
        $sum = 0;
        foreach (str_split($twelve) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 === 0 ? 1 : 3);
        }

        return (10 - $sum % 10) % 10;
    }

    public static function validEan13(?string $code): bool
    {
        return is_string($code) && preg_match('/^\d{13}$/', $code) === 1 && self::eanCheckDigit(substr($code, 0, 12)) === (int) $code[12];
    }

    /**
     * Create the variants a product is missing for its garment type and
     * offered sizes. Never deletes or renames. $inactive adds a size that is
     * no longer offered (seen on a past order) as an inactive variant.
     *
     * @return int variants created
     */
    public static function ensureFor(Product $product, array $extra = []): int
    {
        $created = 0;
        $parts = self::partsFor($product->garment_type);
        $offered = self::offeredSizes($product);
        foreach ($parts as $p => $part) {
            $sizes = array_map(fn ($s) => [$s, true], $offered);
            foreach ($extra[$part] ?? [] as $size) {
                if (! in_array($size, $offered, true)) {
                    $sizes[] = [$size, false];
                }
            }
            foreach ($sizes as $i => [$size, $active]) {
                if (self::where('product_id', $product->id)->where('part', $part)->where('size', $size)->exists()) {
                    continue;
                }
                $variant = self::create(['product_id' => $product->id, 'part' => $part, 'size' => $size, 'active' => $active,
                    'sku' => self::skuFor($product, $part, $size), 'sort' => $p * 100 + $i]);
                $variant->update(['barcode' => self::barcodeFor($variant->id)]);
                $created++;
            }
        }

        return $created;
    }

    /**
     * The variants a cart line means, one per part, from its size options.
     * A one-size product needs no option. Null when any part can't be
     * resolved (no size chosen, an unknown size, or an inactive one when
     * $activeOnly).
     *
     * @return list<self>|null
     */
    public static function resolve(Product $product, ?array $options, bool $activeOnly = true): ?array
    {
        $variants = [];
        foreach (self::partsFor($product->garment_type) as $part) {
            $candidates = self::where('product_id', $product->id)->where('part', $part)->when($activeOnly, fn ($q) => $q->where('active', true))->get();
            $size = null;
            foreach (self::optionFor($part) as $key) {
                $size ??= isset($options[$key]) && trim((string) $options[$key]) !== '' ? trim((string) $options[$key]) : null;
            }
            $variant = $size !== null
                ? $candidates->firstWhere('size', $size)
                : ($candidates->count() === 1 && $candidates->first()->size === self::ONE_SIZE ? $candidates->first() : null);
            if (! $variant) {
                return null;
            }
            $variants[] = $variant;
        }

        return $variants;
    }
}
