{{--
    Single image picker with instant preview.
    $model: wire:model name of the TemporaryUploadedFile property.
    $upload: the current temporary upload (or null). $current: URL of the saved image.
--}}
@props(['model', 'upload' => null, 'current' => null, 'label' => 'الصورة', 'required' => false, 'removeAction' => null])

<x-admin.field :label="$label" :error="$model" :required="$required" :hint="\App\Support\ImageRules::hint()">
    <div class="flex items-center gap-3">
        @php($preview = $upload && method_exists($upload, 'isPreviewable') && $upload->isPreviewable() ? $upload->temporaryUrl() : $current)
        <x-admin.thumb :url="$preview" size="size-20" />

        <div class="flex flex-1 flex-wrap items-center gap-2">
            <label class="btn-secondary cursor-pointer">
                <x-admin.icon name="photo" />
                <span>{{ $preview ? 'تغيير الصورة' : 'اختيار صورة' }}</span>
                <input type="file" wire:model="{{ $model }}" accept="image/jpeg,image/png,image/webp" class="sr-only">
            </label>

            @if ($upload)
                <button type="button" wire:click="$set('{{ $model }}', null)" class="btn-ghost text-red-600">إلغاء</button>
            @elseif ($current && $removeAction)
                <button type="button" wire:click="{{ $removeAction }}" wire:confirm="حذف الصورة الحالية؟" class="btn-ghost text-red-600">حذف الصورة</button>
            @endif

            <span wire:loading wire:target="{{ $model }}" class="text-sm text-gray-500">جارٍ الرفع...</span>
        </div>
    </div>
</x-admin.field>
