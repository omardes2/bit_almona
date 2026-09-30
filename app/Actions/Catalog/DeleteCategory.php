<?php

namespace App\Actions\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Services\Media\ImageStorage;
use Illuminate\Validation\ValidationException;

/**
 * Safe category deletion: a category that still has sub-categories or
 * products (including soft-deleted ones referenced by old orders) is never
 * deleted. The admin is asked to move them first, or to deactivate the
 * category instead.
 */
class DeleteCategory
{
    public function __construct(private readonly ImageStorage $images) {}

    public function handle(Category $category): void
    {
        if ($category->children()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'لا يمكن حذف قسم يحتوي على أقسام فرعية. انقل الأقسام الفرعية أو احذفها أولًا، أو عطّل القسم بدلًا من حذفه.',
            ]);
        }

        if (Product::withTrashed()->where('category_id', $category->id)->exists()) {
            throw ValidationException::withMessages([
                'category' => 'لا يمكن حذف قسم يحتوي على منتجات. انقل المنتجات إلى قسم آخر أولًا، أو عطّل القسم بدلًا من حذفه.',
            ]);
        }

        $image = $category->image;

        $category->delete();

        $this->images->delete($image);
    }
}
