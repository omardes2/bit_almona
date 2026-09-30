<div>
    <h1 class="mb-4 text-2xl font-extrabold">إتمام الطلب</h1>

    @if ($error)
        <div class="mb-4 rounded-2xl bg-red-50 p-4 text-sm text-red-800 ring-1 ring-red-200" role="alert">
            <p class="flex items-center gap-2 font-bold"><x-icon name="alert" class="size-5" /> {{ $error }}</p>
            @if ($problems)
                <ul class="mt-2 list-disc space-y-1 ps-6">
                    @foreach ($problems as $problem)<li>{{ $problem }}</li>@endforeach
                </ul>
                <a href="{{ route('cart') }}" wire:navigate class="btn-secondary mt-3">مراجعة السلة</a>
            @endif
        </div>
    @endif

    <form wire:submit="placeOrder" class="grid gap-4 lg:grid-cols-5">
        <div class="space-y-4 lg:col-span-3">
            {{-- 1. Customer --}}
            <section class="card space-y-3 p-4" aria-labelledby="step-customer">
                <h2 id="step-customer" class="flex items-center gap-2 font-bold"><span class="flex size-6 items-center justify-center rounded-full bg-brand-600 text-xs text-white">1</span> بياناتك</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">الاسم</span>
                        <div class="rounded-xl bg-gray-50 px-3 py-2.5">{{ $user->name }}</div>
                    </div>
                    <div>
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">رقم الجوال</span>
                        <div class="rounded-xl bg-gray-50 px-3 py-2.5 text-left" dir="ltr">{{ $user->phone }}</div>
                    </div>
                    <x-admin.field label="رقم واتساب" for="whatsapp" error="whatsapp" class="sm:col-span-2">
                        <input id="whatsapp" type="tel" inputmode="tel" wire:model="whatsapp" placeholder="05XXXXXXXX" class="form-input text-left" dir="ltr">
                    </x-admin.field>
                </div>
            </section>

            {{-- 2. Address --}}
            <section class="card space-y-3 p-4" aria-labelledby="step-address">
                <h2 id="step-address" class="flex items-center gap-2 font-bold"><span class="flex size-6 items-center justify-center rounded-full bg-brand-600 text-xs text-white">2</span> عنوان التوصيل</h2>

                @if ($addresses->isNotEmpty())
                    <div class="grid gap-2" role="radiogroup" aria-label="العناوين المحفوظة">
                        @foreach ($addresses as $address)
                            <label wire:key="addr-{{ $address->id }}" class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-3 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                                <input type="radio" name="address" value="{{ $address->id }}" wire:model.live="addressId" wire:click="$set('useNewAddress', false)" @checked(! $useNewAddress && (int) $addressId === $address->id) class="mt-1 text-brand-600">
                                <span class="min-w-0 text-sm">
                                    <span class="font-bold">{{ $address->label ?: 'عنوان' }}</span>
                                    <span class="block text-gray-700">{{ $address->toSingleLine() }}</span>
                                    <span class="block text-xs text-gray-500">{{ $address->recipient_name }} · <bdi dir="ltr">{{ $address->recipient_phone }}</bdi></span>
                                </span>
                            </label>
                        @endforeach
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-dashed border-gray-300 p-3 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                            <input type="radio" name="address" wire:model.live="useNewAddress" value="1" @checked($useNewAddress) class="text-brand-600">
                            <span class="text-sm font-bold">+ عنوان جديد</span>
                        </label>
                    </div>
                @endif
                @error('addressId') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                @if ($useNewAddress)
                    <div class="rounded-xl bg-gray-50 p-3">
                        @include('livewire.forms.address-fields', ['prefix' => 'newAddress'])
                        <p class="mt-2 text-xs text-gray-500">سيُحفظ العنوان في حسابك لاستخدامه لاحقًا.</p>
                    </div>
                @endif
            </section>

            {{-- 3. Delivery zone --}}
            <section class="card space-y-3 p-4" aria-labelledby="step-zone">
                <h2 id="step-zone" class="flex items-center gap-2 font-bold"><span class="flex size-6 items-center justify-center rounded-full bg-brand-600 text-xs text-white">3</span> منطقة التوصيل</h2>
                @if ($zones->isEmpty())
                    <p class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">لا توجد مناطق توصيل متاحة حاليًا. يرجى التواصل مع المتجر.</p>
                @else
                    <div class="grid gap-2 sm:grid-cols-2" role="radiogroup" aria-label="مناطق التوصيل">
                        @foreach ($zones as $zone)
                            <label wire:key="zone-{{ $zone->id }}" class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-gray-200 p-3 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                                <span class="flex items-center gap-3">
                                    <input type="radio" name="zone" value="{{ $zone->id }}" wire:model.live="zoneId" class="text-brand-600">
                                    <span>
                                        <span class="block font-bold">{{ $zone->name }}</span>
                                        @if ($zone->min_order_amount !== null && (float) $zone->min_order_amount > 0)
                                            <span class="block text-xs text-gray-500">حد أدنى <bdi dir="ltr">{{ \App\Support\Money::short($zone->min_order_amount) }}</bdi></span>
                                        @endif
                                    </span>
                                </span>
                                <span class="text-sm font-bold"><bdi dir="ltr">{{ (float) $zone->delivery_fee > 0 ? \App\Support\Money::short($zone->delivery_fee) : 'مجاني' }}</bdi></span>
                            </label>
                        @endforeach
                    </div>
                @endif
                @error('zoneId') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </section>

            {{-- 4. Notes + payment --}}
            <section class="card space-y-3 p-4" aria-labelledby="step-payment">
                <h2 id="step-payment" class="flex items-center gap-2 font-bold"><span class="flex size-6 items-center justify-center rounded-full bg-brand-600 text-xs text-white">4</span> الدفع والملاحظات</h2>
                <div class="grid gap-2" role="radiogroup" aria-label="طريقة الدفع">
                    @foreach ($methods as $method)
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 p-3 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                            <input type="radio" name="payment" value="{{ $method->value }}" wire:model="paymentMethod" class="text-brand-600">
                            <span><span class="block font-bold">{{ $method->label() }}</span><span class="block text-xs text-gray-500">{{ $method->description() }}</span></span>
                        </label>
                    @endforeach
                </div>
                <x-admin.field label="ملاحظات الطلب (اختياري)" for="notes" error="notes">
                    <textarea id="notes" wire:model="notes" rows="2" maxlength="1000" class="form-input" placeholder="مثال: الرجاء الاتصال قبل الوصول"></textarea>
                </x-admin.field>
            </section>
        </div>

        {{-- Summary --}}
        <aside class="lg:col-span-2">
            <div class="card space-y-3 p-4 lg:sticky lg:top-24">
                <h2 class="font-bold">ملخص الطلب</h2>
                <ul class="max-h-64 divide-y divide-gray-100 overflow-y-auto text-sm">
                    @foreach ($summary->lines as $line)
                        <li class="flex items-center gap-2 py-2">
                            <x-admin.thumb :url="$line->product->thumbnailUrl()" alt="" size="size-10" />
                            <span class="min-w-0 flex-1">
                                <span class="line-clamp-1">{{ $line->product->name }}</span>
                                <span class="text-xs text-gray-500"><bdi dir="ltr">{{ $line->quantity }}</bdi> {{ $line->product->unit->label() }}</span>
                                @if ($line->issue)<span class="block text-xs font-bold text-red-600">{{ $line->issue }}</span>@endif
                            </span>
                            <span class="font-bold"><bdi dir="ltr">{{ \App\Support\Money::format($line->lineTotal()) }}</bdi></span>
                        </li>
                    @endforeach
                </ul>

                <dl class="space-y-1.5 border-t border-gray-100 pt-3 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-600">المنتجات</dt><dd><bdi dir="ltr">{{ \App\Support\Money::format($quote->subtotal()) }}</bdi></dd></div>
                    @if ($quote->discountCents > 0)
                        <div class="flex justify-between text-red-600"><dt>الخصم</dt><dd><bdi dir="ltr">- {{ \App\Support\Money::format($quote->discount()) }}</bdi></dd></div>
                    @endif
                    <div class="flex justify-between">
                        <dt class="text-gray-600">التوصيل</dt>
                        <dd>@if ($quote->zone)<bdi dir="ltr">{{ \App\Support\Money::format($quote->delivery()) }}</bdi>@else<span class="text-gray-400">اختر المنطقة</span>@endif</dd>
                    </div>
                    <div class="flex items-center justify-between border-t border-gray-100 pt-2">
                        <dt class="text-base font-bold">الإجمالي</dt>
                        <dd class="text-2xl font-extrabold text-brand-700"><bdi dir="ltr">{{ \App\Support\Money::format($quote->total()) }}</bdi></dd>
                    </div>
                </dl>

                @if ($quote->minimumCents > 0 && ! $quote->meetsMinimum())
                    <p class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900" role="status">
                        الحد الأدنى للطلب <bdi dir="ltr">{{ \App\Support\Money::format($quote->minimum()) }}</bdi>.
                        أضف منتجات بقيمة <bdi dir="ltr">{{ \App\Support\Money::format(\App\Support\Money::fromCents($quote->missingForMinimumCents())) }}</bdi>.
                    </p>
                @endif

                <button type="submit"
                        wire:loading.attr="disabled" wire:target="placeOrder"
                        @disabled($zones->isEmpty() || ($quote->zone && ! $quote->meetsMinimum()))
                        class="btn-primary w-full py-3.5 text-base">
                    <span wire:loading.remove wire:target="placeOrder">تأكيد الطلب</span>
                    <span wire:loading wire:target="placeOrder">جارٍ تأكيد طلبك...</span>
                </button>
                <p class="text-center text-xs text-gray-500">بالضغط على «تأكيد الطلب» يُرسل طلبك للمتجر. الدفع عند الاستلام.</p>
            </div>
        </aside>
    </form>
</div>
