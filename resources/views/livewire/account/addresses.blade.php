<div class="mx-auto max-w-2xl">
    @include('account.partials.nav', ['active' => 'addresses'])

    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-2xl font-extrabold">عناويني</h1>
        @unless ($editing)
            <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" /> إضافة عنوان</button>
        @endunless
    </div>

    @if ($editing)
        <form wire:submit="save" class="card mb-4 space-y-3 p-4">
            <h2 class="font-bold">{{ $editingId ? 'تعديل العنوان' : 'عنوان جديد' }}</h2>
            @include('livewire.forms.address-fields', ['prefix' => 'form'])
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="form.is_default" class="rounded text-brand-600"> اجعله العنوان الافتراضي</label>
            <div class="flex gap-2">
                <button type="submit" class="btn-primary flex-1 sm:flex-none" wire:loading.attr="disabled">حفظ العنوان</button>
                <button type="button" wire:click="cancel" class="btn-secondary">إلغاء</button>
            </div>
        </form>
    @endif

    @if ($addresses->isEmpty() && ! $editing)
        <div class="card p-8 text-center">
            <x-icon name="zones" class="mx-auto mb-3 size-12 text-gray-300" />
            <p class="font-bold">لا توجد عناوين محفوظة</p>
            <p class="mt-1 text-sm text-gray-500">أضف عنوانك لتسريع إتمام الطلب.</p>
        </div>
    @endif

    <div class="space-y-2">
        @foreach ($addresses as $address)
            <div wire:key="address-{{ $address->id }}" class="card p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-bold">{{ $address->label ?: 'عنوان' }}</span>
                            @if ($address->is_default)<span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-bold text-brand-700">الافتراضي</span>@endif
                        </div>
                        <p class="mt-1 text-sm text-gray-700">{{ $address->toSingleLine() }}</p>
                        @if ($address->notes)<p class="text-xs text-gray-500">{{ $address->notes }}</p>@endif
                        <p class="mt-1 text-xs text-gray-500">{{ $address->recipient_name }} · <bdi dir="ltr">{{ $address->recipient_phone }}</bdi></p>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" wire:click="edit({{ $address->id }})" class="btn-secondary min-h-9 py-1.5"><x-icon name="edit" class="size-4" /> تعديل</button>
                    @unless ($address->is_default)
                        <button type="button" wire:click="makeDefault({{ $address->id }})" class="btn-ghost min-h-9 py-1.5">تعيين كافتراضي</button>
                    @endunless
                    <button type="button" wire:click="delete({{ $address->id }})" wire:confirm="حذف هذا العنوان؟" class="btn-ghost min-h-9 py-1.5 text-red-600"><x-icon name="trash" class="size-4" /> حذف</button>
                </div>
            </div>
        @endforeach
    </div>
</div>
