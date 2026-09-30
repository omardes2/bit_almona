<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\SaleUnit;
use App\Models\Concerns\Auditable;
use App\Services\Media\ImageStorage;
use App\Services\Pricing\ProductPriceResolver;
use App\Services\Pricing\ResolvedPrice;
use App\Support\ArabicText;
use App\Support\Notifications\StockAlerts;
use App\Support\StorefrontVisibility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Products are soft deleted: order_items keep their snapshot and a link to
 * the (trashed) product, and the product can be restored by an admin.
 */
#[Fillable([
    'category_id', 'name', 'slug', 'description', 'sku',
    'original_price', 'sale_price', 'stock_quantity', 'min_order_quantity', 'quantity_step',
    'low_stock_threshold', 'unit', 'status', 'is_featured', 'main_image',
    'seo_title', 'seo_description', 'sort_order',
])]
class Product extends Model
{
    public const STORE_SORTS = [
        'latest' => 'الأحدث',
        'name' => 'الاسم',
        'price_asc' => 'السعر: الأقل',
        'price_desc' => 'السعر: الأعلى',
    ];

    use Auditable, HasFactory, SoftDeletes;

    /** @var list<string> */
    protected array $auditExclude = ['search_text'];

    private ?ResolvedPrice $resolvedPrice = null;

    protected function casts(): array
    {
        return [
            'original_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'stock_quantity' => 'decimal:3',
            'min_order_quantity' => 'decimal:3',
            'quantity_step' => 'decimal:3',
            'low_stock_threshold' => 'decimal:3',
            'unit' => SaleUnit::class,
            'status' => ProductStatus::class,
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            $product->search_text = ArabicText::normalize($product->name.' '.$product->sku);
        });

        // Admin edits of stock or threshold can trigger (or re-arm) stock alerts.
        static::saved(function (Product $product) {
            if ($product->wasChanged(['stock_quantity', 'low_stock_threshold']) || $product->wasRecentlyCreated) {
                StockAlerts::check($product->id);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
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

    /**
     * Products a customer may see: not hidden, not soft deleted (global
     * scope), and in a category whose whole parent chain is active.
     * See App\Support\StorefrontVisibility.
     */
    public function scopeStorefront(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), '!=', ProductStatus::Hidden)
            ->whereIn($query->qualifyColumn('category_id'), StorefrontVisibility::categoryIds());
    }

    /** Available products first, then unavailable ones (hidden never reach here). */
    public function scopeAvailableFirst(Builder $query): void
    {
        $query->orderByRaw('case when '.$query->qualifyColumn('status').' = ? then 0 else 1 end', [ProductStatus::Available->value]);
    }

    /**
     * Storefront sort options: latest | name | price_asc | price_desc.
     */
    public function scopeSortForStore(Builder $query, ?string $sort): void
    {
        match ($sort) {
            'name' => $query->orderBy('name'),
            'price_asc' => $query->orderBy('sale_price')->orderBy('id'),
            'price_desc' => $query->orderByDesc('sale_price')->orderBy('id'),
            default => $query->latest('id'),
        };
    }

    public function scopeAvailable(Builder $query): void
    {
        $query->where('status', ProductStatus::Available);
    }

    public function scopeLowStock(Builder $query): void
    {
        $query->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
    }

    /**
     * Search by name or SKU. Arabic spelling variants (أ/ا، ة/ه، ى/ي) and
     * diacritics are ignored thanks to the normalised search_text column.
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = ArabicText::normalize($term);

        if ($term === '') {
            return;
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);

        $query->where('search_text', 'like', '%'.$escaped.'%');
    }

    public function isPurchasable(): bool
    {
        return $this->status === ProductStatus::Available
            && (float) $this->stock_quantity > 0;
    }

    /**
     * The current price, always from ProductPriceResolver (memoised per instance).
     * Eager load `activeOffer` on lists to avoid one query per product.
     */
    public function price(): ResolvedPrice
    {
        return $this->resolvedPrice ??= app(ProductPriceResolver::class)->resolve($this);
    }

    public function isVisibleInStore(): bool
    {
        return StorefrontVisibility::isProductVisible($this);
    }

    public function isLowStock(): bool
    {
        return (float) $this->stock_quantity <= (float) $this->low_stock_threshold;
    }

    public function imageDirectory(): string
    {
        return 'products/'.$this->getKey();
    }

    public function mainImageUrl(): ?string
    {
        return ImageStorage::url($this->main_image);
    }

    public function thumbnailUrl(): ?string
    {
        return ImageStorage::thumbnailUrl($this->main_image);
    }

    /** Storefront URL (slug based; admin URLs keep using the id). */
    public function url(): string
    {
        return route('product.show', ['product' => $this->slug]);
    }
}
