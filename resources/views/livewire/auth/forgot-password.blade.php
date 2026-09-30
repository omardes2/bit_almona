<div class="mx-auto max-w-sm">
    <h1 class="mb-1 text-2xl font-bold">استعادة كلمة المرور</h1>

    @if (! $available)
        <p class="mb-6 text-sm text-gray-600">نرسل رمز تحقق إلى جوالك لتعيين كلمة مرور جديدة.</p>
        @include('partials.otp-unavailable', ['message' => 'خدمة استعادة كلمة المرور غير مفعلة حاليًا'])
    @elseif ($step === 'phone')
        <p class="mb-6 text-sm text-gray-600">أدخل رقم جوالك المسجّل وسنرسل لك رمز تحقق.</p>
        <form wire:submit="sendCode" class="space-y-4 rounded-2xl bg-white p-5 shadow-sm">
            <x-input name="phone" label="رقم الجوال" type="tel" inputmode="tel" autocomplete="tel"
                     placeholder="05XXXXXXXX" class="ltr-nums text-left" wire:model="phone" required autofocus />
            <button type="submit" wire:loading.attr="disabled" class="btn-primary w-full py-3">
                <span wire:loading.remove wire:target="sendCode">إرسال الرمز</span>
                <span wire:loading wire:target="sendCode">جارٍ الإرسال...</span>
            </button>
        </form>
    @elseif ($step === 'code')
        <p class="mb-4 text-sm text-gray-600">أدخل الرمز المكوّن من {{ config('otp.length') }} أرقام.</p>
        <div class="mb-4 rounded-xl bg-brand-50 p-3 text-sm text-brand-700 ring-1 ring-brand-100" role="status">
            {{ \App\Livewire\Auth\ForgotPassword::SENT_MESSAGE }}
            @if ($maskedPhone) <bdi dir="ltr" class="font-bold">{{ $maskedPhone }}</bdi> @endif
        </div>
        @if (session('otp-status'))
            <p class="mb-3 text-sm text-brand-700" role="status">{{ session('otp-status') }}</p>
        @endif
        <form wire:submit="verify" class="space-y-4 rounded-2xl bg-white p-5 shadow-sm">
            <x-input name="code" label="رمز التحقق" inputmode="numeric" autocomplete="one-time-code" maxlength="10"
                     class="ltr-nums text-center text-2xl tracking-[0.5em]" wire:model="code" required autofocus />
            <button type="submit" wire:loading.attr="disabled" class="btn-primary w-full py-3">
                <span wire:loading.remove wire:target="verify">تحقق</span>
                <span wire:loading wire:target="verify">جارٍ التحقق...</span>
            </button>
            <div class="flex items-center justify-between text-sm">
                <button type="button" wire:click="resend" class="font-medium text-brand-700">إعادة إرسال الرمز</button>
                <button type="button" wire:click="restart" class="text-gray-600">تغيير الرقم</button>
            </div>
        </form>
    @else
        <p class="mb-6 text-sm text-gray-600">اختر كلمة مرور جديدة. سيتم تسجيل خروجك من كل الأجهزة.</p>
        <form wire:submit="resetPassword" class="space-y-4 rounded-2xl bg-white p-5 shadow-sm">
            <x-input name="password" label="كلمة المرور الجديدة" type="password" autocomplete="new-password" wire:model="password" required autofocus
                     hint="8 أحرف على الأقل وتحتوي حروفًا وأرقامًا." />
            <x-input name="password_confirmation" label="تأكيد كلمة المرور" type="password" autocomplete="new-password" wire:model="password_confirmation" required />
            <button type="submit" wire:loading.attr="disabled" class="btn-primary w-full py-3">
                <span wire:loading.remove wire:target="resetPassword">حفظ كلمة المرور</span>
                <span wire:loading wire:target="resetPassword">جارٍ الحفظ...</span>
            </button>
        </form>
    @endif

    <p class="mt-4 text-center text-sm text-gray-600">
        <a href="{{ route('login') }}" wire:navigate class="font-medium text-brand-700">العودة لتسجيل الدخول</a>
    </p>
</div>
