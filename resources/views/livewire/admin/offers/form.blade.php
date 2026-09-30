<div>
    <x-admin.page-header :title="$offer ? 'تعديل عرض' : 'إنشاء عرض'" :back="route('admin.offers.index')">
        @if ($offer)
            <x-slot:actions>
                <x-admin.status-badge :status="$offer->scheduleStatus()" :label="$offer->scheduleStatus()->offerLabel()" class="text-sm" />
            </x-slot:actions>
        @endif
    </x-admin.page-header>

    <form wire:submit="save" class="space-y-4">
        <section class="card space-y-4 p-4 sm:p-6">
            <h2 class="font-bold">المنتج</h2>

            @if ($this->product)
                <div class="flex items-center gap-3 rounded-xl border border-brand-500 bg-brand-50 p-3">
                    <x-admin.thumb :url="$this->product->thumbnailUrl()" size="size-14" />
                    <div class="min-w-0 flex-1">
                        <div class="font-bold">{{ $this->product->name }}</div>
                        <div class="text-xs text-gray-600">
                            سعر البيع الحالي: {{ \App\Support\Money::format($this->product->sale_price) }}
                            · الأصلي: {{ \App\Support\Money::format($this->product->original_price) }}
                        </div>
                    </div>
                    <button type="button" wire:click="clearProduct" class="btn-ghost">تغيير</button>
                </div>
            @else
                <div class="relative">
                    <x-icon name="search" class="pointer-events-none absolute inset-y-0 start-3 my-auto text-gray-400" />
                    <input type="search" wire:model.live.debounce.300ms="productSearch" placeholder="ابحث عن المنتج بالاسم أو SKU..." class="form-input ps-10" autocomplete="off">
                </div>
                @if ($this->productResults->isNotEmpty())
                    <div class="divide-y divide-gray-100 overflow-hidden rounded-xl ring-1 ring-gray-200">
                        @foreach ($this->productResults as $result)
                            <button type="button" wire:key="result-{{ $result->id }}" wire:click="selectProduct({{ $result->id }})" class="flex w-full items-center gap-3 p-3 text-start hover:bg-gray-50">
                                <x-admin.thumb :url="$result->thumbnailUrl()" size="size-10" />
                                <span class="min-w-0 flex-1 truncate">{{ $result->name }}</span>
                                <span class="text-sm text-gray-500">{{ \App\Support\Money::format($result->sale_price) }}</span>
                            </button>
                        @endforeach
                    </div>
                @elseif (trim($productSearch) !== '')
                    <p class="text-sm text-gray-500">لا توجد منتجات مطابقة.</p>
                @endif
            @endif
            @error('product_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </section>

        <section class="card space-y-4 p-4 sm:p-6">
            <h2 class="font-bold">السعر والمدة</h2>

            <div class="grid grid-cols-2 gap-4">
                <x-admin.field label="السعر الأصلي (₪)" for="original_price" error="original_price" required hint="يظهر مشطوبًا.">
                    <input id="original_price" type="number" step="0.01" min="0" inputmode="decimal" wire:model.live.debounce.400ms="original_price" class="form-input" required>
                </x-admin.field>
                <x-admin.field label="سعر العرض (₪)" for="offer_price" error="offer_price" required>
                    <input id="offer_price" type="number" step="0.01" min="0" inputmode="decimal" wire:model.live.debounce.400ms="offer_price" class="form-input" required>
                </x-admin.field>
            </div>

            @if ($this->discountPercentage !== null)
                <div @class(['rounded-xl p-3 text-sm font-bold', 'bg-green-50 text-green-800' => $this->discountPercentage > 0, 'bg-red-50 text-red-700' => $this->discountPercentage <= 0])>
                    @if ($this->discountPercentage > 0)
                        نسبة الخصم: {{ $this->discountPercentage }}%
                    @else
                        سعر العرض يجب أن يكون أقل من السعر الأصلي.
                    @endif
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.field label="يبدأ في" for="starts_at" error="starts_at" hint="اتركه فارغًا ليبدأ فورًا.">
                    <input id="starts_at" type="datetime-local" wire:model="starts_at" class="form-input">
                </x-admin.field>
                <x-admin.field label="ينتهي في" for="ends_at" error="ends_at" hint="بعد هذا الوقت يتوقف العرض تلقائيًا.">
                    <input id="ends_at" type="datetime-local" wire:model="ends_at" class="form-input">
                </x-admin.field>
            </div>
        </section>

        <section class="card space-y-4 p-4 sm:p-6">
            <h2 class="font-bold">العرض في المتجر</h2>

            <x-admin.field label="عنوان العرض (اختياري)" for="title" error="title">
                <input id="title" type="text" wire:model="title" class="form-input" placeholder="مثال: عرض نهاية الأسبوع">
            </x-admin.field>

            <x-admin.image-input model="image" :upload="$image" :current="$offer?->image ? \App\Services\Media\ImageStorage::thumbnailUrl($offer->image) : null"
                                 label="صورة العرض (اختياري — وإلا تُستخدم صورة المنتج)" :remove-action="$offer?->image ? 'removeImage' : null" />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.field label="الترتيب" for="sort_order" error="sort_order">
                    <input id="sort_order" type="number" min="0" inputmode="numeric" wire:model="sort_order" class="form-input">
                </x-admin.field>
                <div class="sm:pt-6">
                    <x-admin.toggle label="العرض مفعّل" description="يمكنك إيقافه مؤقتًا دون حذفه." wire:model="is_active" />
                </div>
            </div>
        </section>

        <div class="sticky bottom-0 -mx-3 flex gap-2 border-t border-gray-200 bg-gray-100/95 p-3 backdrop-blur sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:p-0">
            <button type="submit" class="btn-primary flex-1 sm:flex-none sm:px-8" wire:loading.attr="disabled" wire:target="save,image">
                <span wire:loading.remove wire:target="save">{{ $offer ? 'حفظ التعديلات' : 'إنشاء العرض' }}</span>
                <span wire:loading wire:target="save">جارٍ الحفظ...</span>
            </button>
            <a href="{{ route('admin.offers.index') }}" wire:navigate class="btn-secondary">إلغاء</a>
        </div>
    </form>
</div>
