<div>
    <x-admin.page-header title="إعدادات المتجر" subtitle="تظهر هذه البيانات في المتجر وصفحات الطلب" />

    <form wire:submit="save" class="space-y-4">
        <section class="card space-y-4 p-4 sm:p-6">
            <h2 class="font-bold">هوية المتجر</h2>

            <x-admin.field label="اسم المتجر" for="store_name" error="store_name" required>
                <input id="store_name" type="text" wire:model="store_name" class="form-input" required>
            </x-admin.field>

            <x-admin.image-input model="logo" :upload="$logo" :current="$logoUrl" label="الشعار (يُفضّل PNG بخلفية شفافة)"
                                 :remove-action="$logoUrl ? 'removeLogo' : null" />
        </section>

        <section class="card space-y-4 p-4 sm:p-6">
            <h2 class="font-bold">التواصل</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.field label="رقم الهاتف" for="store_phone" error="store_phone">
                    <input id="store_phone" type="tel" inputmode="tel" wire:model="store_phone" class="form-input text-left" dir="ltr" placeholder="02-2221234">
                </x-admin.field>
                <x-admin.field label="رقم واتساب" for="store_whatsapp" error="store_whatsapp" hint="بالصيغة 05XXXXXXXX">
                    <input id="store_whatsapp" type="tel" inputmode="tel" wire:model="store_whatsapp" class="form-input text-left" dir="ltr" placeholder="05XXXXXXXX">
                </x-admin.field>
            </div>
            <x-admin.field label="العنوان" for="store_address" error="store_address">
                <input id="store_address" type="text" wire:model="store_address" class="form-input" placeholder="الخليل – فلسطين">
            </x-admin.field>
            <x-admin.field label="ساعات العمل" for="working_hours" error="working_hours" hint="للعرض فقط، مثال: يوميًا 8 صباحًا – 11 مساءً">
                <textarea id="working_hours" wire:model="working_hours" rows="2" class="form-input"></textarea>
            </x-admin.field>
        </section>

        <section class="card space-y-4 p-4 sm:p-6">
            <h2 class="font-bold">الطلبات</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.field label="العملة" for="currency_code" error="currency_code" required>
                    <select id="currency_code" wire:model="currency_code" class="form-input">
                        @foreach (\App\Livewire\Admin\Settings\StoreSettingsForm::CURRENCIES as $code => $currency)
                            <option value="{{ $code }}">{{ $currency['label'] }} — {{ $currency['symbol'] }}</option>
                        @endforeach
                    </select>
                </x-admin.field>
                <x-admin.field label="الحد الأدنى العام للطلب" for="min_order_amount" error="min_order_amount"
                               hint="اختياري. إذا كان لمنطقة التوصيل حد أعلى يُطبّق الأعلى.">
                    <input id="min_order_amount" type="number" step="0.01" min="0" inputmode="decimal" wire:model="min_order_amount" class="form-input" placeholder="بدون حد أدنى">
                </x-admin.field>
            </div>
        </section>

        <div class="sticky bottom-0 -mx-3 border-t border-gray-200 bg-gray-100/95 p-3 backdrop-blur sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:p-0">
            <button type="submit" class="btn-primary w-full sm:w-auto sm:px-8" wire:loading.attr="disabled" wire:target="save,logo">
                <span wire:loading.remove wire:target="save">حفظ الإعدادات</span>
                <span wire:loading wire:target="save">جارٍ الحفظ...</span>
            </button>
        </div>
    </form>
</div>
