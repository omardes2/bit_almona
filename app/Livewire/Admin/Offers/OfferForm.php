<?php

namespace App\Livewire\Admin\Offers;

use App\Livewire\Admin\Concerns\AuthorizesCatalog;
use App\Livewire\Admin\Concerns\Toasts;
use App\Models\Offer;
use App\Models\Product;
use App\Services\Media\ImageStorage;
use App\Support\Decimal;
use App\Support\ImageRules;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.admin')]
class OfferForm extends Component
{
    use AuthorizesCatalog, Toasts, WithFileUploads;

    public ?Offer $offer = null;

    public int|string|null $product_id = null;

    public string $productSearch = '';

    public string $title = '';

    public string $original_price = '';

    public string $offer_price = '';

    public string $starts_at = '';

    public string $ends_at = '';

    public int|string $sort_order = 0;

    public bool $is_active = true;

    /** @var TemporaryUploadedFile|null */
    public $image = null;

    public function mount(?Offer $offer = null): void
    {
        if ($offer?->exists) {
            $this->offer = $offer;
            $this->product_id = $offer->product_id;
            $this->title = (string) $offer->title;
            $this->original_price = Decimal::trim($offer->getRawOriginal('original_price'));
            $this->offer_price = Decimal::trim($offer->getRawOriginal('offer_price'));
            $this->starts_at = $offer->starts_at?->format('Y-m-d\TH:i') ?? '';
            $this->ends_at = $offer->ends_at?->format('Y-m-d\TH:i') ?? '';
            $this->sort_order = $offer->sort_order;
            $this->is_active = $offer->is_active;

            return;
        }

        $this->starts_at = now()->format('Y-m-d\TH:i');
        $this->ends_at = now()->addWeek()->endOfDay()->format('Y-m-d\TH:i');
        $this->sort_order = (int) Offer::max('sort_order') + 1;

        if ($productId = request()->integer('product')) {
            $this->selectProduct($productId);
        }
    }

    #[Computed]
    public function product(): ?Product
    {
        return $this->product_id ? Product::withTrashed()->find((int) $this->product_id) : null;
    }

    #[Computed]
    public function productResults()
    {
        if (mb_strlen(trim($this->productSearch)) < 1) {
            return collect();
        }

        return Product::query()
            ->search($this->productSearch)
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'sku', 'main_image', 'original_price', 'sale_price']);
    }

    /**
     * Discount percentage, calculated on the server from the entered prices.
     */
    #[Computed]
    public function discountPercentage(): ?int
    {
        if (! is_numeric($this->original_price) || ! is_numeric($this->offer_price) || (float) $this->original_price <= 0) {
            return null;
        }

        return (int) round((1 - (float) $this->offer_price / (float) $this->original_price) * 100);
    }

    public function selectProduct(int $id): void
    {
        $product = Product::find($id);

        if (! $product) {
            return;
        }

        $this->product_id = $product->id;
        $this->productSearch = '';
        unset($this->product);

        if ($this->original_price === '') {
            $this->original_price = Decimal::trim(max((float) $product->original_price, (float) $product->sale_price));
        }
    }

    public function clearProduct(): void
    {
        $this->product_id = null;
        unset($this->product);
    }

    protected function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists(Product::class, 'id')->whereNull('deleted_at')],
            'title' => ['nullable', 'string', 'max:150'],
            'original_price' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999.99'],
            'offer_price' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'lt:original_price'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => array_filter([
                'nullable', 'date',
                filled($this->starts_at) ? 'after:starts_at' : null,
                // A new (or re-dated) offer must end in the future.
                $this->offer === null || $this->ends_at !== ($this->offer->ends_at?->format('Y-m-d\TH:i') ?? '') ? 'after:now' : null,
            ]),
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['boolean'],
            'image' => ImageRules::rules(),
        ];
    }

    protected function messages(): array
    {
        return [
            'offer_price.lt' => 'سعر العرض يجب أن يكون أقل من السعر الأصلي.',
            'ends_at.after' => 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية وفي المستقبل.',
            'product_id.required' => 'اختر المنتج الذي عليه العرض.',
            'product_id.exists' => 'المنتج غير موجود أو محذوف.',
        ];
    }

    protected function validationAttributes(): array
    {
        return ['title' => 'عنوان العرض', 'image' => 'صورة العرض'];
    }

    /**
     * Business rules that need the product: the offer must actually be
     * cheaper than the product's sale price (otherwise ProductPriceResolver
     * would ignore it), and one product cannot have two overlapping active offers.
     */
    protected function withBusinessRules(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || ! ($product = Product::find((int) $this->product_id))) {
                return;
            }

            if ((float) $this->offer_price >= (float) $product->sale_price) {
                $validator->errors()->add('offer_price', 'سعر العرض يجب أن يكون أقل من سعر البيع الحالي للمنتج ('.Money::format($product->sale_price).')، وإلا لن يُطبق.');
            }

            if ($this->is_active && $this->overlappingOffer($product)) {
                $validator->errors()->add('ends_at', 'يوجد عرض آخر فعال لنفس المنتج في نفس الفترة. أوقفه أو غيّر التواريخ.');
            }
        });
    }

    private function overlappingOffer(Product $product): bool
    {
        $start = filled($this->starts_at) ? Carbon::parse($this->starts_at) : null;
        $end = filled($this->ends_at) ? Carbon::parse($this->ends_at) : null;

        return Offer::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->when($this->offer, fn ($q) => $q->whereKeyNot($this->offer->id))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->when($end, fn ($q) => $q->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<', $end)))
            ->when($start, fn ($q) => $q->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $start)))
            ->exists();
    }

    public function updatedImage(): void
    {
        $this->validateOnly('image');
    }

    public function removeImage(ImageStorage $images): void
    {
        if ($this->offer?->image) {
            $old = $this->offer->image;
            $this->offer->update(['image' => null]);
            $images->delete($old);
            $this->toast('تم حذف صورة العرض.');
        }
    }

    public function save(ImageStorage $images)
    {
        $data = $this->withValidator(fn (Validator $v) => $this->withBusinessRules($v))->validate();

        $offer = $this->offer ?? new Offer;
        $oldImage = $offer->image;

        $offer->fill([
            'product_id' => (int) $data['product_id'],
            'title' => $data['title'] ?: null,
            'original_price' => $data['original_price'],
            'offer_price' => $data['offer_price'],
            'starts_at' => filled($data['starts_at']) ? CarbonImmutable::parse($data['starts_at']) : null,
            'ends_at' => filled($data['ends_at']) ? CarbonImmutable::parse($data['ends_at']) : null,
            'sort_order' => (int) $data['sort_order'],
            'is_active' => $data['is_active'],
        ]);

        if ($this->image) {
            $offer->image = $images->store($this->image, 'offers');
        }

        $offer->save();

        if ($this->image && $oldImage) {
            $images->delete($oldImage);
        }

        $this->flashToast($this->offer ? 'تم حفظ تعديلات العرض.' : 'تم إنشاء العرض.');

        return $this->redirectRoute('admin.offers.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.offers.form')->title($this->offer ? 'تعديل عرض' : 'إنشاء عرض');
    }
}
