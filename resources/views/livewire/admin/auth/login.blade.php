<div class="mx-auto mt-10 max-w-sm">
    <h1 class="mb-6 text-center text-2xl font-bold">دخول لوحة الإدارة</h1>

    <form wire:submit="login" class="space-y-4 rounded-2xl bg-white p-5 shadow-sm">
        <x-input name="phone" label="رقم الجوال" type="tel" inputmode="tel" autocomplete="username"
                 placeholder="05XXXXXXXX" class="ltr-nums text-left" wire:model="phone" required autofocus />

        <x-input name="password" label="كلمة المرور" type="password" autocomplete="current-password"
                 wire:model="password" required />

        <button type="submit" wire:loading.attr="disabled"
                class="w-full rounded-lg bg-gray-900 py-3 font-bold text-white hover:bg-gray-800 disabled:opacity-60">
            دخول
        </button>
    </form>
</div>
