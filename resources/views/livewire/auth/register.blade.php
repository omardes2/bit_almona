<div class="mx-auto max-w-sm">
    <h1 class="mb-1 text-2xl font-bold">إنشاء حساب جديد</h1>
    <p class="mb-6 text-sm text-gray-600">سجّل مرة واحدة واطلب مونة بيتك بسهولة.</p>

    @if ($this->fromCheckout())
        <div class="mb-4 flex items-start gap-2 rounded-xl bg-brand-50 p-3 text-sm text-brand-700 ring-1 ring-brand-100" role="status">
            <x-icon name="cart" class="size-5 shrink-0" />
            <span>سجّل الدخول أو أنشئ حسابًا لإتمام طلبك. سلتك محفوظة وستعود مباشرة لإتمام الطلب.</span>
        </div>
    @endif

    <form wire:submit="register" class="space-y-4 rounded-2xl bg-white p-5 shadow-sm">
        <x-input name="name" label="الاسم الكامل" autocomplete="name" wire:model="name" required autofocus />

        <x-input name="phone" label="رقم الجوال" type="tel" inputmode="tel" autocomplete="tel"
                 placeholder="05XXXXXXXX" class="ltr-nums text-left" wire:model="phone" required />

        <div x-data="{ same: $wire.entangle('whatsappSameAsPhone') }" class="space-y-3">
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" x-model="same" class="rounded border-gray-300 text-brand-600">
                رقم الواتساب نفس رقم الجوال
            </label>

            <div x-show="!same" x-cloak>
                <x-input name="whatsapp" label="رقم الواتساب" type="tel" inputmode="tel"
                         placeholder="05XXXXXXXX" class="ltr-nums text-left" wire:model="whatsapp" />
            </div>
        </div>

        <x-input name="password" label="كلمة المرور" type="password" autocomplete="new-password"
                 hint="8 أحرف على الأقل وتحتوي على حروف وأرقام." wire:model="password" required />

        <x-input name="password_confirmation" label="تأكيد كلمة المرور" type="password" autocomplete="new-password"
                 wire:model="password_confirmation" required />

        <button type="submit" wire:loading.attr="disabled"
                class="w-full rounded-lg bg-brand-600 py-3 font-bold text-white hover:bg-brand-700 disabled:opacity-60">
            <span wire:loading.remove wire:target="register">إنشاء الحساب</span>
            <span wire:loading wire:target="register">جارٍ الإنشاء...</span>
        </button>
    </form>

    <p class="mt-4 text-center text-sm text-gray-600">
        لديك حساب؟
        <a href="{{ route('login') }}" wire:navigate class="font-medium text-brand-700">سجّل دخولك</a>
    </p>
</div>
