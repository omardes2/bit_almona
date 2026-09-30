<div>
    <x-admin.page-header :title="$category ? 'تعديل قسم' : 'إضافة قسم'" :subtitle="$category?->name" :back="route('admin.categories.index')" />

    <form wire:submit="save" class="space-y-4">
        <div class="card space-y-4 p-4 sm:p-6">
            <x-admin.field label="اسم القسم" for="name" error="name" required>
                <input id="name" type="text" wire:model="name" class="form-input" placeholder="مثال: ألبان وأجبان" required>
            </x-admin.field>

            <x-admin.field label="القسم الرئيسي" for="parent_id" error="parent_id" hint="اتركه «بدون» ليكون قسمًا رئيسيًا.">
                <select id="parent_id" wire:model="parent_id" class="form-input">
                    <option value="">— بدون (قسم رئيسي) —</option>
                    @foreach ($parents as $row)
                        <option value="{{ $row['category']->id }}">{{ str_repeat('— ', $row['depth']) }}{{ $row['category']->name }}</option>
                    @endforeach
                </select>
            </x-admin.field>

            <x-admin.field label="الرابط المختصر (slug)" for="slug" error="slug" hint="اتركه فارغًا ليُنشأ تلقائيًا من الاسم.">
                <input id="slug" type="text" wire:model="slug" class="form-input" dir="auto" placeholder="يُنشأ تلقائيًا">
            </x-admin.field>

            <x-admin.field label="الوصف" for="description" error="description">
                <textarea id="description" wire:model="description" rows="3" class="form-input"></textarea>
            </x-admin.field>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.field label="الترتيب" for="sort_order" error="sort_order" hint="الرقم الأصغر يظهر أولًا.">
                    <input id="sort_order" type="number" min="0" inputmode="numeric" wire:model="sort_order" class="form-input">
                </x-admin.field>

                <div class="sm:pt-6">
                    <x-admin.toggle label="القسم مفعّل" description="القسم المعطل لا يظهر للزبائن." wire:model="is_active" />
                </div>
            </div>

            <x-admin.image-input model="image" :upload="$image" :current="$category?->thumbnailUrl()" label="صورة القسم"
                                 :remove-action="$category?->image ? 'removeImage' : null" />
        </div>

        <div class="sticky bottom-0 -mx-3 flex gap-2 border-t border-gray-200 bg-gray-100/95 p-3 backdrop-blur sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:p-0">
            <button type="submit" class="btn-primary flex-1 sm:flex-none" wire:loading.attr="disabled" wire:target="save,image">
                <span wire:loading.remove wire:target="save">حفظ القسم</span>
                <span wire:loading wire:target="save">جارٍ الحفظ...</span>
            </button>
            <a href="{{ route('admin.categories.index') }}" wire:navigate class="btn-secondary">إلغاء</a>
        </div>
    </form>
</div>
