<?php

namespace App\Livewire\Store;

use App\Models\Product;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Header quick search: at most 6 suggestions, full results on /search.
 */
class SearchBox extends Component
{
    public const LIMIT = 6;

    public string $q = '';

    #[Computed]
    public function results()
    {
        if (mb_strlen(trim($this->q)) < 2) {
            return collect();
        }

        return Product::query()
            ->storefront()
            ->search($this->q)
            ->with('activeOffer')
            ->availableFirst()
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get(['id', 'name', 'slug', 'sku', 'main_image', 'original_price', 'sale_price', 'status', 'unit', 'stock_quantity', 'category_id']);
    }

    public function render()
    {
        return view('livewire.store.search-box');
    }
}
