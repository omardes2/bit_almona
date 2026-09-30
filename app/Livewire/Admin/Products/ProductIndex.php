<?php

namespace App\Livewire\Admin\Products;

use App\Actions\Catalog\DeleteProduct;
use App\Actions\Catalog\DuplicateProduct;
use App\Enums\ProductStatus;
use App\Livewire\Admin\Concerns\AuthorizesCatalog;
use App\Livewire\Admin\Concerns\Toasts;
use App\Models\Category;
use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('المنتجات')]
class ProductIndex extends Component
{
    use AuthorizesCatalog, Toasts, WithPagination;

    public const SORTS = [
        'latest' => 'الأحدث',
        'name' => 'الاسم',
        'price_asc' => 'السعر: من الأقل',
        'price_desc' => 'السعر: من الأعلى',
        'stock' => 'المخزون: الأقل أولًا',
    ];

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: false)]
    public bool $lowStock = false;

    #[Url(except: false)]
    public bool $trashed = false;

    #[Url(except: 'latest')]
    public string $sort = 'latest';

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'category', 'status', 'lowStock', 'trashed', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'category', 'status', 'lowStock', 'trashed', 'sort');
        $this->resetPage();
    }

    public function duplicate(int $id, DuplicateProduct $duplicateProduct)
    {
        $copy = $duplicateProduct->handle(Product::findOrFail($id));

        $this->flashToast('تم نسخ المنتج. النسخة مخفية حتى تراجعها وتغيّر حالتها.');

        return $this->redirectRoute('admin.products.edit', $copy, navigate: true);
    }

    public function delete(int $id, DeleteProduct $deleteProduct): void
    {
        $product = Product::findOrFail($id);
        $deleteProduct->handle($product);

        $this->toast('تم حذف المنتج «'.$product->name.'». يمكنك استعادته من «المحذوفات».');
    }

    public function restore(int $id): void
    {
        $product = Product::onlyTrashed()->findOrFail($id);
        $product->restore();

        $this->toast('تمت استعادة المنتج «'.$product->name.'».');
    }

    public function render()
    {
        $query = Product::query()
            ->select(['id', 'category_id', 'name', 'sku', 'original_price', 'sale_price', 'stock_quantity',
                'low_stock_threshold', 'unit', 'status', 'is_featured', 'main_image', 'updated_at', 'deleted_at'])
            ->with(['category:id,name', 'activeOffer'])
            ->search($this->search)
            ->when($this->trashed, fn ($q) => $q->onlyTrashed())
            ->when($this->category !== '' && ($category = Category::find((int) $this->category)),
                fn ($q) => $q->whereIn('category_id', $category->selfAndDescendantIds()))
            ->when(ProductStatus::tryFrom($this->status), fn ($q, $status) => $q->where('status', $status))
            ->when($this->lowStock, fn ($q) => $q->lowStock());

        match ($this->sort) {
            'name' => $query->orderBy('name'),
            'price_asc' => $query->orderBy('sale_price')->orderBy('id'),
            'price_desc' => $query->orderByDesc('sale_price')->orderBy('id'),
            'stock' => $query->orderBy('stock_quantity')->orderBy('id'),
            default => $query->latest('id'),
        };

        return view('livewire.admin.products.index', [
            'products' => $query->paginate(config('store.admin_per_page')),
            'categories' => Category::flattenTree(Category::query()->orderBy('sort_order')->orderBy('id')->get(['id', 'parent_id', 'name', 'sort_order'])),
            'statuses' => ProductStatus::cases(),
            'hasAnyProduct' => Product::withTrashed()->exists(),
            'filtering' => $this->search !== '' || $this->category !== '' || $this->status !== '' || $this->lowStock || $this->trashed,
        ]);
    }
}
