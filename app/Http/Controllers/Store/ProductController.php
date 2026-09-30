<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;

class ProductController extends Controller
{
    public function show(string $product): View
    {
        // storefront(): hidden, soft-deleted and inactive-category products => 404.
        $product = Product::query()
            ->storefront()
            ->where('slug', $product)
            ->with(['category.parent', 'images', 'activeOffer'])
            ->firstOrFail();

        return view('store.product', [
            'product' => $product,
            'related' => $this->related($product),
        ]);
    }

    /**
     * Same category first, then the sibling categories under the same parent.
     */
    private function related(Product $product)
    {
        $categoryIds = [$product->category_id];

        if ($product->category->parent_id) {
            $categoryIds = array_merge(
                $categoryIds,
                Category::query()->storefront()->where('parent_id', $product->category->parent_id)->pluck('id')->all(),
                [$product->category->parent_id],
            );
        }

        return Product::query()
            ->storefront()
            ->available()
            ->whereKeyNot($product->id)
            ->whereIn('category_id', array_unique($categoryIds))
            ->with('activeOffer')
            ->orderByRaw('case when category_id = ? then 0 else 1 end', [$product->category_id])
            ->latest('id')
            ->limit(8)
            ->get();
    }
}
