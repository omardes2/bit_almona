<div>
    <x-admin.page-header :title="$banner ? 'تعديل بنر' : 'إضافة بنر'" :back="route('admin.banners.index')">
        @if ($banner)
            <x-slot:actions><x-admin.status-badge :status="$banner->scheduleStatus()" class="text-sm" /></x-slot:actions>
        @endif
    </x-admin.page-header>

    <form wire:submit="save" class="space-y-4">
        <section class="card space-y-4 p-4 sm:p-6">
            <x-admin.field label="صورة البنر" error="image" :required="! $banner" hint="{{ \App\Support\ImageRules::hint() }} المقاس المقترح 1600×700.">
                @php($preview = $image && $image->isPreviewable() ? $image->temporaryUrl() : $banner?->imageUrl())
                <label class="block cursor-pointer overflow-hidden rounded-xl border-2 border-dashed border-gray-300 hover:border-brand-500">
                    @if ($preview)
                        <img src="{{ $preview }}" alt="" class="aspect-[16/7] w-full object-cover">
                    @else
                        <span class="flex aspect-[16/7] flex-col items-center justify-center gap-2 text-gray-500">
                            <x-admin.icon name="photo" class="size-10" />
                            <span class="text-sm">اضغط لاختيار صورة</span>
                        </span>
                    @endif
                    <input type="file" wire:model="image" accept="image/jpeg,image/png,image/webp" class="sr-only">
                </label>
                <div wire:loading wire:target="image" class="mt-1 text-sm text-gray-500">جارٍ الرفع...</div>
            </x-admin.field>

            <x-admin.field label="العنوان" for="title" error="title">
                <input id="title" type="text" wire:model="title" class="form-input">
            </x-admin.field>

            <x-admin.field label="الوصف" for="description" error="description">
                <textarea id="description" wire:model="description" rows="2" class="form-input"></textarea>
            </x-admin.field>

            <x-admin.field label="الرابط" for="link_url" error="link_url" hint="رابط كامل https://... أو مسار داخلي مثل /offers">
                <input id="link_url" type="text" inputmode="url" wire:model="link_url" class="form-input text-left" dir="ltr" placeholder="https://">
            </x-admin.field>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.field label="يبدأ في" for="starts_at" error="starts_at" hint="اتركه فارغًا ليظهر فورًا.">
                    <input id="starts_at" type="datetime-local" wire:model="starts_at" class="form-input">
                </x-admin.field>
                <x-admin.field label="ينتهي في" for="ends_at" error="ends_at" hint="اتركه فارغًا ليبقى بدون نهاية.">
                    <input id="ends_at" type="datetime-local" wire:model="ends_at" class="form-input">
                </x-admin.field>
                <x-admin.field label="الترتيب" for="sort_order" error="sort_order">
                    <input id="sort_order" type="number" min="0" inputmode="numeric" wire:model="sort_order" class="form-input">
                </x-admin.field>
                <div class="sm:pt-6">
                    <x-admin.toggle label="البنر مفعّل" wire:model="is_active" />
                </div>
            </div>
        </section>

        <div class="sticky bottom-0 -mx-3 flex gap-2 border-t border-gray-200 bg-gray-100/95 p-3 backdrop-blur sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:p-0">
            <button type="submit" class="btn-primary flex-1 sm:flex-none sm:px-8" wire:loading.attr="disabled" wire:target="save,image">
                <span wire:loading.remove wire:target="save">{{ $banner ? 'حفظ التعديلات' : 'إضافة البنر' }}</span>
                <span wire:loading wire:target="save">جارٍ الحفظ...</span>
            </button>
            <a href="{{ route('admin.banners.index') }}" wire:navigate class="btn-secondary">إلغاء</a>
        </div>
    </form>
</div>
