<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id', 'title', 'original_price', 'offer_price',
    'starts_at', 'ends_at', 'image', 'sort_order', 'is_active',
])]
class Offer extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'original_price' => 'decimal:2',
            'offer_price' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Offers that are active and inside their date window right now.
     * Expired offers stop applying immediately, even before the scheduled
     * `offers:deactivate-expired` command flips their is_active flag.
     */
    public function scopeRunning(Builder $query): void
    {
        $now = now();

        $query->where($query->qualifyColumn('is_active'), true)
            ->where(fn (Builder $q) => $q->whereNull($q->qualifyColumn('starts_at'))->orWhere($q->qualifyColumn('starts_at'), '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull($q->qualifyColumn('ends_at'))->orWhere($q->qualifyColumn('ends_at'), '>', $now));
    }

    public function scopeExpired(Builder $query): void
    {
        $query->whereNotNull('ends_at')->where('ends_at', '<=', now());
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderByDesc('id');
    }

    public function isRunning(): bool
    {
        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->lte(now()))
            && ($this->ends_at === null || $this->ends_at->gt(now()));
    }

    public function discountPercentage(): int
    {
        if ((float) $this->original_price <= 0) {
            return 0;
        }

        return (int) round((1 - (float) $this->offer_price / (float) $this->original_price) * 100);
    }
}
