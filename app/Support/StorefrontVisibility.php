<?php

namespace App\Support;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;

/**
 * Single source of truth for "can a customer see this?".
 *
 * A category is visible only if it AND every ancestor is active; a product
 * is visible only if it is not hidden, not soft deleted, and its category is
 * visible. Product::scopeStorefront(), Category::scopeStorefront(),
 * Product::isVisibleInStore() and the cart all delegate here.
 */
final class StorefrontVisibility
{
    /** @var list<int>|null Memoised per request (the class is bound as a scoped singleton). */
    private ?array $ids = null;

    /**
     * IDs of categories whose whole ancestor chain is active (cached, and
     * flushed with StorefrontCache on any category change).
     *
     * @return list<int>
     */
    public static function categoryIds(): array
    {
        $instance = app(self::class);

        return $instance->ids ??= StorefrontCache::visibleCategoryIds(fn () => self::compute());
    }

    public static function isCategoryVisible(Category|int|null $category): bool
    {
        $id = $category instanceof Category ? $category->id : $category;

        return $id !== null && in_array($id, self::categoryIds(), true);
    }

    public static function isProductVisible(?Product $product): bool
    {
        return $product !== null
            && ! $product->trashed()
            && $product->status !== ProductStatus::Hidden
            && self::isCategoryVisible($product->category_id);
    }

    public static function forget(): void
    {
        app(self::class)->ids = null;
    }

    /**
     * @return list<int>
     */
    private static function compute(): array
    {
        $categories = Category::query()->get(['id', 'parent_id', 'is_active'])->keyBy('id');
        $visible = [];

        foreach ($categories as $category) {
            $current = $category;
            $seen = [];

            while ($current !== null && $current->is_active && ! isset($seen[$current->id])) {
                $seen[$current->id] = true;

                if ($current->parent_id === null) {
                    $visible[] = $category->id;
                    break;
                }

                $current = $categories->get($current->parent_id);
            }
        }

        sort($visible);

        return $visible;
    }
}
