{{-- Shared address inputs. $prefix: the Livewire form property name (e.g. "form" or "newAddress"). --}}
<div class="grid gap-3 sm:grid-cols-2">
    <x-admin.field label="اسم المستلم" :for="$prefix.'-recipient_name'" :error="$prefix.'.recipient_name'" required>
        <input id="{{ $prefix }}-recipient_name" type="text" wire:model="{{ $prefix }}.recipient_name" autocomplete="name" class="form-input">
    </x-admin.field>
    <x-admin.field label="رقم جوال المستلم" :for="$prefix.'-recipient_phone'" :error="$prefix.'.recipient_phone'" required>
        <input id="{{ $prefix }}-recipient_phone" type="tel" inputmode="tel" wire:model="{{ $prefix }}.recipient_phone" autocomplete="tel" placeholder="05XXXXXXXX" class="form-input text-left" dir="ltr">
    </x-admin.field>
    <x-admin.field label="المدينة" :for="$prefix.'-city'" :error="$prefix.'.city'" required>
        <input id="{{ $prefix }}-city" type="text" wire:model="{{ $prefix }}.city" class="form-input">
    </x-admin.field>
    <x-admin.field label="المنطقة / الحي" :for="$prefix.'-area'" :error="$prefix.'.area'" required>
        <input id="{{ $prefix }}-area" type="text" wire:model="{{ $prefix }}.area" placeholder="مثال: عين سارة" class="form-input">
    </x-admin.field>
    <x-admin.field label="العنوان التفصيلي" :for="$prefix.'-address_line'" :error="$prefix.'.address_line'" required class="sm:col-span-2">
        <input id="{{ $prefix }}-address_line" type="text" wire:model="{{ $prefix }}.address_line" autocomplete="street-address" placeholder="الشارع، البناية، الطابق" class="form-input">
    </x-admin.field>
    <x-admin.field label="ملاحظات للعنوان" :for="$prefix.'-notes'" :error="$prefix.'.notes'" hint="أقرب معلم، لون البوابة..." class="sm:col-span-2">
        <input id="{{ $prefix }}-notes" type="text" wire:model="{{ $prefix }}.notes" class="form-input">
    </x-admin.field>
    <x-admin.field label="اسم العنوان (اختياري)" :for="$prefix.'-label'" :error="$prefix.'.label'" hint="مثال: المنزل، العمل">
        <input id="{{ $prefix }}-label" type="text" wire:model="{{ $prefix }}.label" class="form-input">
    </x-admin.field>
</div>
