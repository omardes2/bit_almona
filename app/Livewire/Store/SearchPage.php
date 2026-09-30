<?php

namespace App\Livewire\Store;

use App\Models\Product;
use App\Support\StorefrontCache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['noindex' => true])]
class SearchPage extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: 'latest')]
    public string $sort = 'latest';

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $term = trim($this->q);
        $sort = array_key_exists($this->sort, Product::STORE_SORTS) ? $this->sort : 'latest';

        $products = mb_strlen($term) >= 2
            ? Product::query()
                ->storefront()
                ->search($term)
                ->with('activeOffer')
                ->availableFirst()
                ->sortForStore($sort)
                ->paginate(24)
            : null;

        return view('livewire.store.search-page', [
            'products' => $products,
            'categories' => $products === null ? StorefrontCache::categoryTree() : collect(),
        ])->title($term !== '' ? 'نتائج البحث عن «'.$term.'»' : 'البحث');
    }
}
