<div class="mx-auto max-w-md space-y-4">
    @include('account.partials.nav', ['active' => 'account'])

    <h1 class="text-2xl font-bold">توثيق رقم الجوال</h1>

    @if ($user->hasVerifiedPhone())
        <div class="rounded-2xl bg-brand-50 p-4 text-brand-700 ring-1 ring-brand-100" role="status">
            <p class="flex items-center gap-2 font-bold"><x-icon name="check" class="size-5" /> رقم جوالك موثّق.</p>
        </div>
    @elseif (! $available)
        @include('partials.otp-unavailable', ['message' => 'خدمة توثيق رقم الجوال غير مفعلة حاليًا'])
    @else
        <p class="text-sm text-gray-600">سنرسل رمز تحقق إلى رقمك <bdi dir="ltr" class="font-bold">{{ \App\Support\PhoneNumber::mask($user->phone) }}</bdi>.</p>

        @if (! $codeSent)
            <button type="button" wire:click="send" wire:loading.attr="disabled" class="btn-primary w-full py-3">إرسال الرمز</button>
            @error('code') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        @else
            <form wire:submit="verify" class="space-y-4 rounded-2xl bg-white p-5 shadow-sm">
                <x-input name="code" label="رمز التحقق" inputmode="numeric" autocomplete="one-time-code" maxlength="10"
                         class="ltr-nums text-center text-2xl tracking-[0.5em]" wire:model="code" required autofocus />
                <button type="submit" wire:loading.attr="disabled" class="btn-primary w-full py-3">تحقق</button>
                <button type="button" wire:click="send" class="w-full text-sm font-medium text-brand-700">إعادة إرسال الرمز</button>
            </form>
        @endif
    @endif
</div>
