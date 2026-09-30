<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\SaleUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'category_id', 'name', 'slug', 'description', 'sku',
    'original_price', 'sale_price', 'stock_quantity', 'min_order_quantity', 'quantity_step',
    'unit', 'status', 'main_image', 'seo_title', 'seo_description', 'sort_order',
])]
class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'original_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'stock_quantity' => 'decimal:3',
            'min_order_quantity' => 'decimal:3',
            'quantity_step' => 'decimal:3',
            'unit' => SaleUnit::class,
            'status' => ProductStatus::class,
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    /**
     * The offer currently running for this product (lowest price wins).
     */
    public function activeOffer(): HasOne
    {
        return $this->hasOne(Offer::class)->ofMany(
            ['offer_price' => 'min', 'id' => 'max'],
            fn (Builder $query) => $query->running(),
        );
    }

    /**
     * Products shown in the storefront (available or unavailable, never hidden).
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('status', '!=', ProductStatus::Hidden);
    }

    public function scopeAvailable(Builder $query): void
    {
        $query->where('status', ProductStatus::Available);
    }

    public function isPurchasable(): bool
    {
        return $this->status === ProductStatus::Available
            && (float) $this->stock_quantity > 0;
    }
}
