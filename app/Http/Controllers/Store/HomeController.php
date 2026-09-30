<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Product;
use App\Support\StorefrontCache;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $offerProducts = Offer::query()
            ->running()
            ->whereHas('product', fn ($q) => $q->withoutTrashed()->storefront()->available())
            ->with(['product' => fn ($q) => $q->with('activeOffer')])
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->pluck('product')
            ->unique('id')
            ->values();

        $featured = Product::query()
            ->storefront()
            ->where('is_featured', true)
            ->with('activeOffer')
            ->availableFirst()
            ->orderBy('sort_order')
            ->latest('id')
            ->limit(8)
            ->get();

        $suggested = Product::query()
            ->storefront()
            ->available()
            ->whereNotIn('id', $offerProducts->pluck('id')->merge($featured->pluck('id')))
            ->with('activeOffer')
            ->latest('id')
            ->limit(8)
            ->get();

        return view('store.home', [
            'banners' => StorefrontCache::runningBanners(),
            'categories' => StorefrontCache::categoryTree(),
            'offerProducts' => $offerProducts,
            'featured' => $featured,
            'suggested' => $suggested,
        ]);
    }
}
