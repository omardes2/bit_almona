<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasSchedule;
use App\Services\Media\ImageStorage;
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
    use Auditable, HasFactory, HasSchedule;

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
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderByDesc('id');
    }

    /**
     * The offer image if set, otherwise the product's main image.
     */
    public function thumbnailUrl(): ?string
    {
        return ImageStorage::thumbnailUrl($this->image ?? $this->product?->main_image);
    }

    /**
     * Discount percentage, always calculated on the server.
     */
    public function discountPercentage(): int
    {
        if ((float) $this->original_price <= 0) {
            return 0;
        }

        return (int) round((1 - (float) $this->offer_price / (float) $this->original_price) * 100);
    }
}
