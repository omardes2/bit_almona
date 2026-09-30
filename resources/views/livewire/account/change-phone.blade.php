<div class="mx-auto max-w-md space-y-4">
    @include('account.partials.nav', ['active' => 'account'])

    <div>
        <h1 class="text-2xl font-bold">تغيير رقم الجوال</h1>
        <p class="text-sm text-gray-600">رقمك الحالي: <bdi dir="ltr" class="font-bold">{{ $user->phone }}</bdi> — وهو رقم تسجيل الدخول.</p>
    </div>

    @if (! $available)
        @include('partials.otp-unavailable', ['message' => 'تغيير رقم الجوال غير متاح حاليًا لأن خدمة رسائل التحقق غير مفعلة. للتغيير تواصل مع المتجر.'])
    @elseif ($step === 'details')
        <form wire:submit="sendCode" class="space-y-4 rounded-2xl bg-white p-5 shadow-sm">
            <x-input name="current_password" label="كلمة المرور الحالية" type="password" autocomplete="current-password" wire:model="current_password" required />
            <x-input name="newPhone" label="رقم الجوال الجديد" type="tel" inputmode="tel" autocomplete="tel"
                     placeholder="05XXXXXXXX" class="ltr-nums text-left" wire:model="newPhone" required />
            <button type="submit" wire:loading.attr="disabled" class="btn-primary w-full py-3">
                <span wire:loading.remove wire:target="sendCode">إرسال رمز إلى الرقم الجديد</span>
                <span wire:loading wire:target="sendCode">جارٍ الإرسال...</span>
            </button>
        </form>
    @else
        <div class="rounded-xl bg-brand-50 p-3 text-sm text-brand-700 ring-1 ring-brand-100" role="status">
            إذا كان الرقم متاحًا فستصلك رسالة برمز التحقق على <bdi dir="ltr" class="font-bold">{{ $maskedPhone }}</bdi>.
        </div>
        <form wire:submit="verify" class="space-y-4 rounded-2xl bg-white p-5 shadow-sm">
            <x-input name="code" label="رمز التحقق" inputmode="numeric" autocomplete="one-time-code" maxlength="10"
                     class="ltr-nums text-center text-2xl tracking-[0.5em]" wire:model="code" required autofocus />
            <button type="submit" wire:loading.attr="disabled" class="btn-primary w-full py-3">تأكيد الرقم الجديد</button>
            <button type="button" wire:click="restart" class="w-full text-sm text-gray-600">البدء من جديد</button>
        </form>
        <p class="text-xs text-gray-500">بعد التغيير سيتم تسجيل خروجك من الأجهزة الأخرى.</p>
    @endif
</div>
