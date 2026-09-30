<div class="mx-auto max-w-lg space-y-6">
    <div>
        <h1 class="text-2xl font-bold">أهلًا {{ $this->user->name }}</h1>
        <p class="text-sm text-gray-600">رقم الجوال: <span class="ltr-nums">{{ $this->user->phone }}</span></p>
    </div>

    @if (session('status'))
        <div class="rounded-lg bg-brand-50 p-3 text-sm text-brand-700">{{ session('status') }}</div>
    @endif

    <form wire:submit="updateProfile" class="space-y-4 rounded-2xl bg-white p-5 shadow-sm">
        <h2 class="font-bold">بياناتي</h2>

        <x-input name="name" label="الاسم الكامل" wire:model="name" required />

        <x-input name="whatsapp" label="رقم الواتساب" type="tel" inputmode="tel"
                 placeholder="05XXXXXXXX" class="ltr-nums text-left" wire:model="whatsapp" />

        <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 font-bold text-white hover:bg-brand-700">حفظ</button>
    </form>

    <form wire:submit="updatePassword" class="space-y-4 rounded-2xl bg-white p-5 shadow-sm">
        <h2 class="font-bold">تغيير كلمة المرور</h2>

        <x-input name="current_password" label="كلمة المرور الحالية" type="password" autocomplete="current-password" wire:model="current_password" />
        <x-input name="password" label="كلمة المرور الجديدة" type="password" autocomplete="new-password" wire:model="password" />
        <x-input name="password_confirmation" label="تأكيد كلمة المرور" type="password" autocomplete="new-password" wire:model="password_confirmation" />

        <button type="submit" class="rounded-lg bg-gray-900 px-5 py-2.5 font-bold text-white hover:bg-gray-800">تغيير</button>
    </form>
</div>
