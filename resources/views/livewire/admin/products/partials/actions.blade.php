<div x-data="{ open: false }" class="relative shrink-0">
    <button type="button" @click="open = !open" @click.outside="open = false" class="btn-ghost -mt-1 min-h-9 px-2" aria-label="خيارات">⋮</button>
    <div x-show="open" x-cloak x-transition.origin.top.left
         class="absolute end-0 z-20 mt-1 w-48 overflow-hidden rounded-xl bg-white py-1 text-sm shadow-lg ring-1 ring-gray-200">
        @if ($product->trashed())
            <button type="button" wire:click="restore({{ $product->id }})" @click="open = false" class="flex w-full items-center gap-2 px-4 py-3 hover:bg-gray-50">
                <x-admin.icon name="restore" class="size-4" /> استعادة
            </button>
        @else
            <a href="{{ route('admin.products.edit', $product) }}" wire:navigate class="flex items-center gap-2 px-4 py-3 hover:bg-gray-50">
                <x-admin.icon name="edit" class="size-4" /> تعديل
            </a>
            <button type="button" wire:click="duplicate({{ $product->id }})" @click="open = false" class="flex w-full items-center gap-2 px-4 py-3 hover:bg-gray-50">
                <x-admin.icon name="copy" class="size-4" /> نسخ المنتج
            </button>
            <a href="{{ route('admin.offers.create', ['product' => $product->id]) }}" wire:navigate class="flex items-center gap-2 px-4 py-3 hover:bg-gray-50">
                <x-admin.icon name="offers" class="size-4" /> إنشاء عرض
            </a>
            <button type="button" wire:click="delete({{ $product->id }})" @click="open = false"
                    wire:confirm="حذف المنتج «{{ $product->name }}»؟ ستتوقف عروضه، ويمكن استعادته لاحقًا من «المحذوفات»."
                    class="flex w-full items-center gap-2 px-4 py-3 text-red-600 hover:bg-red-50">
                <x-admin.icon name="trash" class="size-4" /> حذف
            </button>
        @endif
    </div>
</div>
