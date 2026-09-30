<div class="mx-auto max-w-2xl space-y-6">
    @include('account.partials.nav', ['active' => 'account'])

    <div>
        <h1 class="text-2xl font-bold">مرحبًا {{ $this->user->name }}</h1>
        <p class="text-sm text-gray-600">لديك {{ $ordersCount }} {{ $ordersCount === 1 ? 'طلب' : 'طلبات' }} في {{ \App\Support\Store::name() }}.</p>
    </div>

    @if ($latest = $recentOrders->first())
        <a href="{{ route('account.orders.show', $latest) }}" wire:navigate class="card block p-4 hover:ring-brand-500">
            <div class="text-sm text-gray-500">آخر طلب</div>
            <div class="mt-1 flex items-center justify-between gap-2">
                <span class="font-bold" dir="ltr">{{ $latest->order_number }}</span>
                <x-order-status-badge :status="$latest->status" />
            </div>
            <div class="mt-1 flex justify-between text-sm text-gray-600">
                <bdi dir="ltr">{{ $latest->created_at->format('Y-m-d') }}</bdi>
                <bdi dir="ltr" class="font-bold text-gray-900">{{ \App\Support\Money::format($latest->total) }}</bdi>
            </div>
        </a>
    @endif

    <div class="grid gap-3 sm:grid-cols-2">
        <a href="{{ route('account.orders') }}" wire:navigate class="card block p-4 hover:ring-brand-500">
            <div class="flex items-center gap-2 font-bold"><x-icon name="orders" /> طلباتي</div>
            @forelse ($recentOrders as $order)
                <div class="mt-2 flex items-center justify-between text-sm">
                    <span dir="ltr">{{ $order->order_number }}</span>
                    <x-order-status-badge :status="$order->status" />
                </div>
            @empty
                <p class="mt-1 text-sm text-gray-500">لا توجد طلبات بعد.</p>
            @endforelse
        </a>
        <a href="{{ route('account.addresses') }}" wire:navigate class="card block p-4 hover:ring-brand-500">
            <div class="flex items-center gap-2 font-bold"><x-icon name="zones" /> عناويني</div>
            <p class="mt-1 text-sm text-gray-600">{{ $defaultAddress?->toSingleLine() ?? 'أضف عنوانًا لتسريع الطلب.' }}</p>
        </a>
    </div>

    @if (session('status'))
        <div class="rounded-lg bg-brand-50 p-3 text-sm text-brand-700">{{ session('status') }}</div>
    @endif

    <p class="text-xs text-gray-500">رقم الجوال (<span class="ltr-nums">{{ $this->user->phone }}</span>) هو رقم الدخول ولا يمكن تغييره حاليًا. للتغيير تواصل مع المتجر.</p>

    <form wire:submit="updateProfile" class="space-y-4 rounded-2xl bg-white p-5 shadow-sm">
        <h2 id="profile" class="font-bold">بياناتي</h2>

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
