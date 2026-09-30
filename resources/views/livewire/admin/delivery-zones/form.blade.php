<div>
    <x-admin.page-header :title="$zone ? 'تعديل منطقة توصيل' : 'إضافة منطقة توصيل'" :back="route('admin.delivery-zones.index')" />

    <form wire:submit="save" class="space-y-4">
        <section class="card space-y-4 p-4 sm:p-6">
            <x-admin.field label="اسم المنطقة" for="name" error="name" required>
                <input id="name" type="text" wire:model="name" class="form-input" placeholder="مثال: عين سارة" required>
            </x-admin.field>
            <div class="grid grid-cols-2 gap-4">
                <x-admin.field label="سعر التوصيل (₪)" for="delivery_fee" error="delivery_fee" required>
                    <input id="delivery_fee" type="number" step="0.01" min="0" inputmode="decimal" wire:model="delivery_fee" class="form-input" required>
                </x-admin.field>
                <x-admin.field label="الحد الأدنى للطلب (₪)" for="min_order_amount" error="min_order_amount" hint="اتركه فارغًا إن لم يوجد">
                    <input id="min_order_amount" type="number" step="0.01" min="0" inputmode="decimal" wire:model="min_order_amount" class="form-input">
                </x-admin.field>
                <x-admin.field label="الترتيب" for="sort_order" error="sort_order">
                    <input id="sort_order" type="number" min="0" inputmode="numeric" wire:model="sort_order" class="form-input">
                </x-admin.field>
                <div class="pt-6"><x-admin.toggle label="المنطقة فعالة" wire:model="is_active" /></div>
            </div>
        </section>

        <div class="flex gap-2">
            <button type="submit" class="btn-primary flex-1 sm:flex-none sm:px-8" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="save">{{ $zone ? 'حفظ التعديلات' : 'إضافة المنطقة' }}</span>
                <span wire:loading wire:target="save">جارٍ الحفظ...</span>
            </button>
            <a href="{{ route('admin.delivery-zones.index') }}" wire:navigate class="btn-secondary">إلغاء</a>
        </div>
    </form>
</div>
