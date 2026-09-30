<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function show(Request $request, string $category): View
    {
        $category = Category::query()
            ->active()
            ->where('slug', $category)
            ->with([
                'parent' => fn ($q) => $q->active(),
                'children' => fn ($q) => $q->active()->ordered(),
            ])
            ->firstOrFail();

        $sort = array_key_exists($request->query('sort'), Product::STORE_SORTS) ? $request->query('sort') : 'latest';
        $onlyAvailable = $request->boolean('available');

        // Products of the category and all of its (active) sub-categories.
        $products = Product::query()
            ->storefront()
            ->whereIn('category_id', $category->selfAndDescendantIds())
            ->when($onlyAvailable, fn ($q) => $q->available())
            ->with('activeOffer')
            ->availableFirst()
            ->sortForStore($sort)
            ->paginate(24)
            ->withQueryString();

        return view('store.category', [
            'category' => $category,
            'products' => $products,
            'sort' => $sort,
            'onlyAvailable' => $onlyAvailable,
        ]);
    }
}
