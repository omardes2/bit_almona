<?php

namespace App\Livewire\Admin\Products;

use App\Actions\Catalog\DeleteProduct;
use App\Actions\Catalog\DuplicateProduct;
use App\Enums\ProductStatus;
use App\Enums\SaleUnit;
use App\Livewire\Admin\Concerns\AuthorizesCatalog;
use App\Livewire\Admin\Concerns\ReordersRecords;
use App\Livewire\Admin\Concerns\Toasts;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Media\ImageStorage;
use App\Support\Decimal;
use App\Support\ImageRules;
use App\Support\Slug;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.admin')]
class ProductForm extends Component
{
    use AuthorizesCatalog, ReordersRecords, Toasts, WithFileUploads;

    public const MAX_GALLERY_IMAGES = 10;

    public ?Product $product = null;

    public string $name = '';

    public string $slug = '';

    public string $sku = '';

    public int|string|null $category_id = null;

    public string $description = '';

    public string $original_price = '';

    public string $sale_price = '';

    public string $stock_quantity = '0';

    public string $min_order_quantity = '1';

    public string $quantity_step = '1';

    public string $low_stock_threshold = '5';

    public string $unit = 'piece';

    public string $status = 'available';

    public bool $is_featured = false;

    public string $seo_title = '';

    public string $seo_description = '';

    /** @var TemporaryUploadedFile|null */
    public $mainImage = null;

    /** @var array<int, TemporaryUploadedFile> */
    public array $newImages = [];

    public function mount(?Product $product = null): void
    {
        if (! $product?->exists) {
            $this->category_id = request()->integer('category') ?: null;

            return;
        }

        $this->product = $product;
        $this->fill($product->only(['name', 'slug', 'category_id']));
        $this->sku = (string) $product->sku;
        $this->description = (string) $product->description;
        $this->seo_title = (string) $product->seo_title;
        $this->seo_description = (string) $product->seo_description;
        $this->is_featured = (bool) $product->is_featured;
        $this->unit = $product->unit->value;
        $this->status = $product->status->value;

        foreach (['original_price', 'sale_price', 'stock_quantity', 'min_order_quantity', 'quantity_step', 'low_stock_threshold'] as $field) {
            $this->{$field} = Decimal::trim($product->getRawOriginal($field));
        }
    }

    protected function rules(): array
    {
        $unit = SaleUnit::tryFrom($this->unit);
        // Pieces, packs and boxes are sold in whole numbers only.
        $quantity = $unit?->allowsFractions() ? 'decimal:0,3' : 'integer';

        $existingImages = $this->product ? $this->product->images()->count() : 0;

        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'slug' => ['nullable', 'string', 'max:150', 'regex:'.Slug::PATTERN, Rule::unique(Product::class, 'slug')->ignore($this->product?->id)],
            'sku' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._\-]+$/', Rule::unique(Product::class, 'sku')->ignore($this->product?->id)],
            'category_id' => ['required', 'integer', Rule::exists(Category::class, 'id')],
            'description' => ['nullable', 'string', 'max:5000'],
            'original_price' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999.99'],
            'sale_price' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999.99', 'lte:original_price'],
            'stock_quantity' => ['required', 'numeric', $quantity, 'min:0', 'max:999999'],
            'min_order_quantity' => ['required', 'numeric', $quantity, 'gt:0', 'max:9999'],
            'quantity_step' => ['required', 'numeric', $quantity, 'gt:0', 'max:9999'],
            'low_stock_threshold' => ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:999999'],
            'unit' => ['required', Rule::enum(SaleUnit::class)],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'is_featured' => ['boolean'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'mainImage' => ImageRules::rules(),
            'newImages' => ['array', 'max:'.max(0, self::MAX_GALLERY_IMAGES - $existingImages)],
            'newImages.*' => ImageRules::rules(required: true),
        ];
    }

    protected function messages(): array
    {
        return [
            'sale_price.lte' => 'سعر البيع يجب ألا يزيد عن السعر الأصلي.',
            'sku.regex' => 'SKU يقبل حروفًا إنجليزية وأرقامًا و (- _ .) فقط.',
            'slug.regex' => 'الرابط المختصر يقبل حروفًا عربية أو إنجليزية صغيرة وأرقامًا وشرطات (-) فقط.',
            'stock_quantity.integer' => 'هذه الوحدة تُباع بأعداد صحيحة فقط.',
            'min_order_quantity.integer' => 'هذه الوحدة تُباع بأعداد صحيحة فقط.',
            'quantity_step.integer' => 'هذه الوحدة تُباع بأعداد صحيحة فقط.',
            'newImages.max' => 'الحد الأقصى '.self::MAX_GALLERY_IMAGES.' صور إضافية لكل منتج.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => 'اسم المنتج',
            'mainImage' => 'الصورة الرئيسية',
            'newImages.*' => 'الصورة',
            'low_stock_threshold' => 'حد المخزون المنخفض',
            'quantity_step' => 'مقدار الزيادة',
            'seo_title' => 'عنوان SEO',
            'seo_description' => 'وصف SEO',
        ];
    }

    public function updatedMainImage(): void
    {
        $this->validateOnly('mainImage');
    }

    public function updatedNewImages(): void
    {
        $this->validateOnly('newImages');
        $this->validateOnly('newImages.*');
    }

    public function removeNewImage(int $index): void
    {
        unset($this->newImages[$index]);
        $this->newImages = array_values($this->newImages);
    }

    /* ------------------------------------------------------------------
     | Existing images (edit page): these act immediately.
     * ------------------------------------------------------------------ */

    public function deleteImage(int $imageId, ImageStorage $images): void
    {
        $image = $this->ownImage($imageId);
        $path = $image->path;
        $image->delete();
        $images->delete($path);

        $this->toast('تم حذف الصورة.');
    }

    public function deleteMainImage(ImageStorage $images): void
    {
        if (! $this->product?->main_image) {
            return;
        }

        $path = $this->product->main_image;
        $this->product->update(['main_image' => null]);
        $images->delete($path);

        $this->toast('تم حذف الصورة الرئيسية.');
    }

    /**
     * Promote a gallery image to main image. The previous main image takes
     * its place in the gallery, so no file is moved or lost.
     */
    public function makeMain(int $imageId): void
    {
        $image = $this->ownImage($imageId);

        DB::transaction(function () use ($image) {
            $previousMain = $this->product->main_image;
            $this->product->update(['main_image' => $image->path]);

            if ($previousMain) {
                $image->update(['path' => $previousMain]);
            } else {
                $image->delete();
            }
        });

        $this->toast('تم تعيين الصورة الرئيسية.');
    }

    public function moveImage(int $imageId, int $direction): void
    {
        $image = $this->ownImage($imageId);

        $this->moveInOrder(ProductImage::query()->where('product_id', $this->product->id), $image, $direction > 0 ? 1 : -1);
    }

    private function ownImage(int $imageId): ProductImage
    {
        abort_unless($this->product !== null, 404);

        return $this->product->images()->whereKey($imageId)->firstOrFail();
    }

    /* ------------------------------------------------------------------ */

    public function duplicate(DuplicateProduct $duplicateProduct)
    {
        $copy = $duplicateProduct->handle($this->product);
        $this->flashToast('تم نسخ المنتج. النسخة مخفية حتى تراجعها وتغيّر حالتها.');

        return $this->redirectRoute('admin.products.edit', $copy, navigate: true);
    }

    public function delete(DeleteProduct $deleteProduct)
    {
        $deleteProduct->handle($this->product);
        $this->flashToast('تم حذف المنتج «'.$this->product->name.'».');

        return $this->redirectRoute('admin.products.index', navigate: true);
    }

    public function save(ImageStorage $images)
    {
        $this->slug = Slug::make($this->slug);
        $this->sku = trim($this->sku);
        $data = $this->validate();

        $isNew = $this->product === null;
        $product = $this->product ?? new Product;

        DB::transaction(function () use ($product, $data) {
            $product->fill([
                'name' => trim($data['name']),
                'slug' => $data['slug'] ?: Slug::unique(Product::class, $data['name'], $product->id),
                'sku' => $data['sku'] ?: null,
                'category_id' => (int) $data['category_id'],
                'description' => $data['description'] ?: null,
                // Decimal strings go straight to DECIMAL columns — never through float.
                'original_price' => $data['original_price'],
                'sale_price' => $data['sale_price'],
                'stock_quantity' => $data['stock_quantity'],
                'min_order_quantity' => $data['min_order_quantity'],
                'quantity_step' => $data['quantity_step'],
                'low_stock_threshold' => $data['low_stock_threshold'],
                'unit' => $data['unit'],
                'status' => $data['status'],
                'is_featured' => $data['is_featured'],
                'seo_title' => $data['seo_title'] ?: null,
                'seo_description' => $data['seo_description'] ?: null,
            ])->save();
        });

        // Images are stored after the product exists (they live in products/{id}/).
        if ($this->mainImage) {
            $oldMain = $product->main_image;
            $product->update(['main_image' => $images->store($this->mainImage, $product->imageDirectory())]);
            $images->delete($oldMain);
        }

        $nextOrder = (int) $product->images()->max('sort_order');

        foreach ($this->newImages as $upload) {
            $product->images()->create([
                'path' => $images->store($upload, $product->imageDirectory()),
                'sort_order' => ++$nextOrder,
            ]);
        }

        $this->reset('mainImage', 'newImages');

        if ($isNew) {
            $this->flashToast('تم إضافة المنتج «'.$product->name.'».');

            return $this->redirectRoute('admin.products.edit', $product, navigate: true);
        }

        $this->product->refresh();
        $this->slug = $product->slug;
        $this->toast('تم حفظ التعديلات.');
    }

    public function render()
    {
        return view('livewire.admin.products.form', [
            'categories' => Category::flattenTree(Category::query()->orderBy('sort_order')->orderBy('id')->get(['id', 'parent_id', 'name', 'sort_order', 'is_active'])),
            'units' => SaleUnit::cases(),
            'statuses' => ProductStatus::cases(),
            'gallery' => $this->product?->images()->get() ?? collect(),
        ])->title($this->product ? 'تعديل منتج' : 'إضافة منتج');
    }
}
