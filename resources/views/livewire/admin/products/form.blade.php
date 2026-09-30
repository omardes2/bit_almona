<div>
    <x-admin.page-header :title="$product ? 'تعديل منتج' : 'إضافة منتج'" :subtitle="$product?->name" :back="route('admin.products.index')">
        @if ($product)
            <x-slot:actions>
                <button type="button" wire:click="duplicate" wire:confirm="إنشاء نسخة من هذا المنتج؟ ستكون النسخة مخفية حتى تراجعها." class="btn-secondary">
                    <x-icon name="copy" /> نسخ المنتج
                </button>
                <button type="button" wire:click="delete" wire:confirm="حذف المنتج «{{ $product->name }}»؟ ستتوقف عروضه، ويمكن استعادته لاحقًا." class="btn-ghost text-red-600">
                    <x-icon name="trash" /> حذف
                </button>
            </x-slot:actions>
        @endif
    </x-admin.page-header>

    <form wire:submit="save" class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- Basic info --}}
            <section class="card space-y-4 p-4 sm:p-6">
                <h2 class="font-bold">المعلومات الأساسية</h2>

                <x-admin.field label="اسم المنتج" for="name" error="name" required>
                    <input id="name" type="text" wire:model="name" class="form-input" placeholder="مثال: جبنة الخيرات 24 مثلث" required>
                </x-admin.field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-admin.field label="القسم" for="category_id" error="category_id" required>
                        <select id="category_id" wire:model="category_id" class="form-input" required>
                            <option value="">اختر القسم</option>
                            @foreach ($categories as $row)
                                <option value="{{ $row['category']->id }}">{{ str_repeat('— ', $row['depth']) }}{{ $row['category']->name }}{{ $row['category']->is_active ? '' : ' (معطل)' }}</option>
                            @endforeach
                        </select>
                    </x-admin.field>

                    <x-admin.field label="SKU (رمز المنتج)" for="sku" error="sku" hint="اختياري، ويجب ألا يتكرر.">
                        <input id="sku" type="text" wire:model="sku" class="form-input text-left" dir="ltr" placeholder="مثال: CHS-024">
                    </x-admin.field>
                </div>

                <x-admin.field label="الوصف" for="description" error="description">
                    <textarea id="description" wire:model="description" rows="4" class="form-input"></textarea>
                </x-admin.field>
            </section>

            {{-- Pricing --}}
            <section class="card space-y-4 p-4 sm:p-6">
                <h2 class="font-bold">الأسعار <span class="text-sm font-normal text-gray-500">(₪ شيكل)</span></h2>
                <div class="grid grid-cols-2 gap-4">
                    <x-admin.field label="السعر الأصلي" for="original_price" error="original_price" required hint="السعر قبل الخصم.">
                        <input id="original_price" type="number" step="0.01" min="0" inputmode="decimal" wire:model="original_price" class="form-input" required>
                    </x-admin.field>
                    <x-admin.field label="سعر البيع" for="sale_price" error="sale_price" required hint="السعر الذي يدفعه الزبون.">
                        <input id="sale_price" type="number" step="0.01" min="0" inputmode="decimal" wire:model="sale_price" class="form-input" required>
                    </x-admin.field>
                </div>
                @if ($product?->activeOffer)
                    <div class="rounded-xl bg-brand-50 p-3 text-sm text-brand-700">
                        عليه عرض فعال الآن بسعر <b>{{ \App\Support\Money::format($product->activeOffer->offer_price) }}</b>
                        — <a href="{{ route('admin.offers.edit', $product->activeOffer) }}" wire:navigate class="font-bold underline">إدارة العرض</a>
                    </div>
                @endif
            </section>

            {{-- Inventory --}}
            <section class="card space-y-4 p-4 sm:p-6">
                <h2 class="font-bold">المخزون والكميات</h2>
                <div class="grid grid-cols-2 gap-4">
                    <x-admin.field label="وحدة البيع" for="unit" error="unit" required>
                        <select id="unit" wire:model.live="unit" class="form-input">
                            @foreach ($units as $u)
                                <option value="{{ $u->value }}">{{ $u->label() }}</option>
                            @endforeach
                        </select>
                    </x-admin.field>
                    <x-admin.field label="كمية المخزون" for="stock_quantity" error="stock_quantity" required>
                        <input id="stock_quantity" type="number" step="any" min="0" inputmode="decimal" wire:model="stock_quantity" class="form-input" required>
                    </x-admin.field>
                    <x-admin.field label="الحد الأدنى للطلب" for="min_order_quantity" error="min_order_quantity" required>
                        <input id="min_order_quantity" type="number" step="any" min="0" inputmode="decimal" wire:model="min_order_quantity" class="form-input" required>
                    </x-admin.field>
                    <x-admin.field label="مقدار الزيادة" for="quantity_step" error="quantity_step" required hint="مثال: 0.25 للكيلو">
                        <input id="quantity_step" type="number" step="any" min="0" inputmode="decimal" wire:model="quantity_step" class="form-input" required>
                    </x-admin.field>
                    <x-admin.field label="تنبيه المخزون المنخفض عند" for="low_stock_threshold" error="low_stock_threshold" required class="col-span-2 sm:col-span-1">
                        <input id="low_stock_threshold" type="number" step="any" min="0" inputmode="decimal" wire:model="low_stock_threshold" class="form-input" required>
                    </x-admin.field>
                </div>
            </section>

            {{-- Images --}}
            <section class="card space-y-4 p-4 sm:p-6">
                <h2 class="font-bold">الصور</h2>

                <x-admin.image-input model="mainImage" :upload="$mainImage" :current="$product?->thumbnailUrl()" label="الصورة الرئيسية"
                                     :remove-action="$product?->main_image ? 'deleteMainImage' : null" />

                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700">صور إضافية</span>
                        <span class="text-xs text-gray-500">حتى {{ \App\Livewire\Admin\Products\ProductForm::MAX_GALLERY_IMAGES }} صور</span>
                    </div>

                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                        @foreach ($gallery as $image)
                            <div wire:key="img-{{ $image->id }}" class="group relative overflow-hidden rounded-xl bg-gray-100">
                                <img src="{{ $image->thumbnailUrl() }}" alt="" loading="lazy" class="aspect-square w-full object-cover">
                                <div class="absolute inset-x-0 bottom-0 flex justify-between bg-black/55 p-1">
                                    <button type="button" wire:click="moveImage({{ $image->id }}, -1)" class="rounded p-1.5 text-white hover:bg-white/20" aria-label="تقديم"><x-icon name="arrow-right" class="size-4" /></button>
                                    <button type="button" wire:click="makeMain({{ $image->id }})" class="rounded p-1.5 text-white hover:bg-white/20" title="تعيين كصورة رئيسية" aria-label="تعيين كصورة رئيسية"><x-icon name="star" class="size-4" /></button>
                                    <button type="button" wire:click="deleteImage({{ $image->id }})" wire:confirm="حذف هذه الصورة؟" class="rounded p-1.5 text-white hover:bg-red-500" aria-label="حذف"><x-icon name="trash" class="size-4" /></button>
                                    <button type="button" wire:click="moveImage({{ $image->id }}, 1)" class="rounded p-1.5 text-white hover:bg-white/20" aria-label="تأخير"><x-icon name="arrow-right" class="size-4 rotate-180" /></button>
                                </div>
                            </div>
                        @endforeach

                        @foreach ($newImages as $index => $upload)
                            <div wire:key="new-{{ $index }}" class="relative overflow-hidden rounded-xl bg-gray-100 ring-2 ring-brand-500">
                                @if ($upload->isPreviewable())
                                    <img src="{{ $upload->temporaryUrl() }}" alt="" class="aspect-square w-full object-cover">
                                @endif
                                <span class="absolute top-1 start-1 rounded bg-brand-600 px-1.5 text-xs text-white">جديدة</span>
                                <button type="button" wire:click="removeNewImage({{ $index }})" class="absolute top-1 end-1 rounded-full bg-black/60 p-1 text-white" aria-label="إزالة"><x-icon name="close" class="size-4" /></button>
                            </div>
                        @endforeach

                        <label class="flex aspect-square cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-gray-300 text-gray-500 hover:border-brand-500 hover:text-brand-600">
                            <x-icon name="plus" class="size-7" />
                            <span class="text-xs">إضافة صور</span>
                            <input type="file" multiple wire:model="newImages" accept="image/jpeg,image/png,image/webp" class="sr-only">
                        </label>
                    </div>
                    <div wire:loading wire:target="newImages" class="mt-2 text-sm text-gray-500">جارٍ رفع الصور...</div>
                    @error('newImages') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    @foreach ($errors->get('newImages.*') as $messages)
                        <p class="mt-1 text-sm text-red-600">{{ $messages[0] }}</p>
                    @endforeach
                    <p class="mt-2 text-xs text-gray-500">{{ \App\Support\ImageRules::hint() }} الصور الجديدة تُحفظ عند الضغط على «حفظ».</p>
                </div>
            </section>

            {{-- SEO --}}
            <section x-data="{ open: @js(filled($seo_title) || filled($seo_description) || $errors->hasAny(['seo_title', 'seo_description', 'slug'])) }" class="card p-4 sm:p-6">
                <button type="button" @click="open = !open" class="flex w-full items-center justify-between font-bold">
                    <span>تحسين محركات البحث (SEO)</span>
                    <x-icon name="down" class="size-5 transition" ::class="open && 'rotate-180'" />
                </button>
                <div x-show="open" x-cloak class="mt-4 space-y-4">
                    <x-admin.field label="الرابط المختصر (slug)" for="slug" error="slug" hint="اتركه فارغًا ليُنشأ تلقائيًا من الاسم.">
                        <input id="slug" type="text" wire:model="slug" class="form-input" dir="auto" placeholder="يُنشأ تلقائيًا">
                    </x-admin.field>
                    <x-admin.field label="عنوان SEO" for="seo_title" error="seo_title" hint="يفضل أقل من 60 حرفًا.">
                        <input id="seo_title" type="text" wire:model="seo_title" class="form-input">
                    </x-admin.field>
                    <x-admin.field label="وصف SEO" for="seo_description" error="seo_description" hint="يفضل أقل من 160 حرفًا.">
                        <textarea id="seo_description" wire:model="seo_description" rows="2" class="form-input"></textarea>
                    </x-admin.field>
                </div>
            </section>
        </div>

        {{-- Side column --}}
        <div class="space-y-4">
            <section class="card space-y-4 p-4 sm:p-6">
                <h2 class="font-bold">الحالة</h2>
                <div class="grid gap-2">
                    @foreach ($statuses as $s)
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 p-3 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                            <input type="radio" wire:model="status" value="{{ $s->value }}" class="text-brand-600">
                            <span class="text-sm font-medium">{{ $s->label() }}</span>
                        </label>
                    @endforeach
                </div>
                @error('status') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <x-admin.toggle label="منتج مميز" description="يمكن إبرازه لاحقًا في واجهة المتجر." wire:model="is_featured" />
            </section>

            @if ($product)
                <section class="card p-4 text-sm text-gray-600 sm:p-6">
                    <div>أُنشئ: <bdi dir="ltr">{{ $product->created_at?->format('Y-m-d H:i') }}</bdi></div>
                    <div>آخر تعديل: <bdi dir="ltr">{{ $product->updated_at?->format('Y-m-d H:i') }}</bdi></div>
                </section>
            @endif
        </div>

        <div class="sticky bottom-0 -mx-3 flex gap-2 border-t border-gray-200 bg-gray-100/95 p-3 backdrop-blur lg:col-span-3 sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:p-0">
            <button type="submit" class="btn-primary flex-1 sm:flex-none sm:px-8" wire:loading.attr="disabled" wire:target="save,mainImage,newImages">
                <span wire:loading.remove wire:target="save">{{ $product ? 'حفظ التعديلات' : 'إضافة المنتج' }}</span>
                <span wire:loading wire:target="save">جارٍ الحفظ...</span>
            </button>
            <a href="{{ route('admin.products.index') }}" wire:navigate class="btn-secondary">إلغاء</a>
        </div>

        @if ($errors->any())
            <p class="text-sm text-red-600 lg:col-span-3">يرجى تصحيح الحقول المظللة بالأحمر.</p>
        @endif
    </form>
</div>
