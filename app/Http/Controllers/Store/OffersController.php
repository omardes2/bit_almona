<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class OffersController extends Controller
{
    public const SORTS = [
        'featured' => 'الترتيب المقترح',
        'discount' => 'الخصم الأعلى',
        'latest' => 'الأحدث',
    ];

    public function __invoke(Request $request): View
    {
        $sort = array_key_exists($request->query('sort'), self::SORTS) ? $request->query('sort') : 'featured';

        $query = Offer::query()
            ->running()
            ->whereHas('product', fn ($q) => $q->withoutTrashed()->storefront()->available())
            // Prices on the cards come from ProductPriceResolver via $product->price().
            ->with(['product' => fn ($q) => $q->with('activeOffer')]);

        match ($sort) {
            'discount' => $query->orderByRaw('(original_price - offer_price) / original_price desc')->orderByDesc('id'),
            'latest' => $query->latest('id'),
            default => $query->orderBy('sort_order')->orderByDesc('id'),
        };

        return view('store.offers', [
            'offers' => $query->paginate(24)->withQueryString(),
            'sort' => $sort,
        ]);
    }
}
