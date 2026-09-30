<?php

namespace App\Actions\Catalog;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Services\Media\ImageStorage;
use App\Support\Slug;
use Illuminate\Support\Facades\DB;

/**
 * Creates an independent copy of a product:
 * - new id and a new unique slug, name suffixed with "(نسخة)";
 * - SKU is NOT copied (it is unique) — the copy starts without one;
 * - status is "hidden" and is_featured is off until the admin reviews it;
 * - image files are physically copied into products/{new_id}/ with new
 *   names, so deleting an image of one product never affects the other;
 * - offers are not copied.
 */
class DuplicateProduct
{
    public function __construct(private readonly ImageStorage $images) {}

    public function handle(Product $product): Product
    {
        return DB::transaction(function () use ($product) {
            $copy = $product->replicate(['slug', 'sku', 'main_image', 'search_text', 'deleted_at']);
            $copy->name = $product->name.' (نسخة)';
            $copy->slug = Slug::unique(Product::class, $copy->name);
            $copy->sku = null;
            $copy->status = ProductStatus::Hidden;
            $copy->is_featured = false;
            $copy->save();

            if ($product->main_image) {
                $copy->main_image = $this->images->copy($product->main_image, $copy->imageDirectory());
                $copy->save();
            }

            foreach ($product->images as $image) {
                if ($path = $this->images->copy($image->path, $copy->imageDirectory())) {
                    $copy->images()->create([
                        'path' => $path,
                        'alt' => $image->alt,
                        'sort_order' => $image->sort_order,
                    ]);
                }
            }

            return $copy;
        });
    }
}
