<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Services\Media\ImageStorage;
use App\Support\StorefrontCache;
use App\Support\StorefrontVisibility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parent_id', 'name', 'slug', 'description', 'image', 'sort_order', 'is_active'])]
class Category extends Model
{
    use Auditable, HasFactory;

    protected static function booted(): void
    {
        static::saved(fn () => StorefrontCache::flush());
        static::deleted(fn () => StorefrontCache::flush());
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Categories customers may see: active, with every ancestor active too. */
    public function scopeStorefront(Builder $query): void
    {
        $query->whereIn($query->qualifyColumn('id'), StorefrontVisibility::categoryIds());
    }

    public function scopeRoots(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * IDs of this category and all of its descendants.
     * Uses a single query for the whole (small) category table.
     *
     * @return list<int>
     */
    public function selfAndDescendantIds(): array
    {
        $childrenByParent = static::query()->get(['id', 'parent_id'])->groupBy('parent_id');

        $ids = [];
        $stack = [$this->id];

        while ($stack !== []) {
            $id = array_pop($stack);

            if (in_array($id, $ids, true)) {
                continue; // defensive: never loop on corrupted data
            }

            $ids[] = $id;

            foreach ($childrenByParent->get($id, []) as $child) {
                $stack[] = $child->id;
            }
        }

        return $ids;
    }

    /**
     * Flatten all categories into tree order with their depth, for selects
     * and the admin list: [['category' => Category, 'depth' => int], ...].
     *
     * @param  Collection<int, Category>  $categories
     * @return list<array{category: Category, depth: int}>
     */
    public static function flattenTree(Collection $categories): array
    {
        $byParent = $categories->groupBy(fn (Category $c) => $c->parent_id ?? 0);
        $rows = [];
        $visited = [];

        $walk = function (int $parentId, int $depth) use (&$walk, &$rows, &$visited, $byParent) {
            foreach ($byParent->get($parentId, []) as $category) {
                if (isset($visited[$category->id])) {
                    continue;
                }

                $visited[$category->id] = true;
                $rows[] = ['category' => $category, 'depth' => $depth];
                $walk($category->id, $depth + 1);
            }
        };

        $walk(0, 0);

        // Orphans whose parent was filtered out (e.g. by search) are shown at the top level.
        foreach ($categories as $category) {
            if (! isset($visited[$category->id])) {
                $visited[$category->id] = true;
                $rows[] = ['category' => $category, 'depth' => 0];
            }
        }

        return $rows;
    }

    /** Storefront URL (slug based; admin URLs keep using the id). */
    public function url(): string
    {
        return route('category.show', ['category' => $this->slug]);
    }

    public function thumbnailUrl(): ?string
    {
        return ImageStorage::thumbnailUrl($this->image);
    }
}
