<div class="mx-auto max-w-sm">
    <h1 class="mb-1 text-2xl font-bold">تسجيل الدخول</h1>
    <p class="mb-6 text-sm text-gray-600">أدخل رقم جوالك وكلمة المرور.</p>

    @if ($this->fromCheckout())
        <div class="mb-4 flex items-start gap-2 rounded-xl bg-brand-50 p-3 text-sm text-brand-700 ring-1 ring-brand-100" role="status">
            <x-icon name="cart" class="size-5 shrink-0" />
            <span>سجّل الدخول أو أنشئ حسابًا لإتمام طلبك. سلتك محفوظة وستعود مباشرة لإتمام الطلب.</span>
        </div>
    @endif

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-brand-50 p-3 text-sm text-brand-700 ring-1 ring-brand-100" role="status">{{ session('status') }}</div>
    @endif

    <form wire:submit="login" class="space-y-4 rounded-2xl bg-white p-5 shadow-sm">
        <x-input name="phone" label="رقم الجوال" type="tel" inputmode="tel" autocomplete="tel"
                 placeholder="05XXXXXXXX" class="ltr-nums text-left" wire:model="phone" required autofocus />

        <x-input name="password" label="كلمة المرور" type="password" autocomplete="current-password"
                 wire:model="password" required />

        <div class="flex items-center justify-between gap-2">
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" wire:model="remember" class="rounded border-gray-300 text-brand-600">
                تذكرني
            </label>
            <a href="{{ route('password.forgot') }}" wire:navigate class="text-sm font-medium text-brand-700">نسيت كلمة المرور؟</a>
        </div>

        <button type="submit" wire:loading.attr="disabled"
                class="w-full rounded-lg bg-brand-600 py-3 font-bold text-white hover:bg-brand-700 disabled:opacity-60">
            <span wire:loading.remove wire:target="login">دخول</span>
            <span wire:loading wire:target="login">جارٍ الدخول...</span>
        </button>
    </form>

    <p class="mt-4 text-center text-sm text-gray-600">
        ليس لديك حساب؟
        <a href="{{ route('register') }}" wire:navigate class="font-medium text-brand-700">أنشئ حسابًا جديدًا</a>
    </p>
</div>
