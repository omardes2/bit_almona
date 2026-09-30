<?php

namespace App\Support;

use App\Models\Banner;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Small, explicitly-invalidated caches for data shown on every storefront
 * page. Any admin change to a category or banner (save, delete, reorder)
 * calls flush(), so updates appear immediately. The TTL is only a safety net.
 *
 * Only plain arrays are cached (the cache store does not unserialize
 * objects); models are re-hydrated on read.
 */
final class StorefrontCache
{
    private const CATEGORIES = 'storefront.categories';

    private const BANNERS = 'storefront.banners';

    private const TTL = 3600;

    /**
     * Active root categories, each with its active children loaded.
     *
     * @return Collection<int, Category>
     */
    public static function categoryTree(): Collection
    {
        $rows = Cache::remember(self::CATEGORIES, self::TTL, fn () => Category::query()
            ->active()
            ->ordered()
            ->get(['id', 'parent_id', 'name', 'slug', 'image', 'sort_order', 'is_active'])
            ->map->getAttributes()
            ->all());

        $categories = Category::hydrate($rows);
        $children = $categories->whereNotNull('parent_id')->groupBy('parent_id');

        return $categories
            ->whereNull('parent_id')
            ->each(fn (Category $root) => $root->setRelation('children', new Collection($children->get($root->id, collect())->values()->all())))
            ->values();
    }

    /**
     * Banners running right now, by sort_order then date. The cache holds
     * every active, not-yet-expired banner; the time window is re-checked on
     * each request, so scheduled banners appear and expired ones disappear on time.
     *
     * @return Collection<int, Banner>
     */
    public static function runningBanners(): Collection
    {
        $rows = Cache::remember(self::BANNERS, self::TTL, fn () => Banner::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->orderBy('sort_order')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get()
            ->map->getAttributes()
            ->all());

        return Banner::hydrate($rows)->filter(fn (Banner $banner) => $banner->isRunning())->values();
    }

    public static function flush(): void
    {
        Cache::forget(self::CATEGORIES);
        Cache::forget(self::BANNERS);
    }
}
