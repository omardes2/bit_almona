<?php

namespace App\Services\Cart;

use App\Models\Product;

/**
 * Valid order quantities for a product, in integer thousandths ("milli")
 * so that 0.25 kg steps are exact without float rounding.
 *
 * A quantity is valid when: min <= q <= stock and (q - min) is a multiple of step.
 * e.g. min 0.5, step 0.25 => 0.5, 0.75, 1, 1.25 ...
 */
final readonly class QuantityRules
{
    public function __construct(
        public int $min,
        public int $step,
        public int $max,
    ) {}

    public static function forProduct(Product $product): self
    {
        $min = max(1, self::toMilli($product->min_order_quantity) ?? 1000);
        $step = max(1, self::toMilli($product->quantity_step) ?? 1000);
        $stock = max(0, self::toMilli($product->stock_quantity) ?? 0);

        return new self($min, $step, $stock);
    }

    /**
     * Parse a user-supplied quantity. Returns null for anything that is not
     * a positive number with at most 3 decimals.
     */
    public static function toMilli(mixed $quantity): ?int
    {
        if (is_int($quantity) || is_float($quantity)) {
            $quantity = (string) $quantity;
        }

        if (! is_string($quantity) || ! preg_match('/^\d{1,6}(\.\d{1,3})?$/', trim($quantity))) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', trim($quantity)), 2, '');

        return (int) $whole * 1000 + (int) str_pad($fraction, 3, '0');
    }

    public static function fromMilli(int $milli): string
    {
        $value = intdiv($milli, 1000).'.'.str_pad((string) ($milli % 1000), 3, '0', STR_PAD_LEFT);

        return rtrim(rtrim($value, '0'), '.');
    }

    public function isValid(int $quantity): bool
    {
        return $quantity >= $this->min
            && $quantity <= $this->max
            && ($quantity - $this->min) % $this->step === 0;
    }

    /** Largest valid quantity the stock allows, or null if not even the minimum. */
    public function maxAllowed(): ?int
    {
        return $this->clamp($this->max);
    }

    /**
     * The largest valid quantity <= $quantity (and <= stock), or null.
     */
    public function clamp(int $quantity): ?int
    {
        $limit = min($quantity, $this->max);

        if ($limit < $this->min) {
            return null;
        }

        return $this->min + intdiv($limit - $this->min, $this->step) * $this->step;
    }
}
