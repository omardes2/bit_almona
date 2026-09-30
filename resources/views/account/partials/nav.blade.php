{{-- $active: account | orders | addresses | notifications (explicit, so it survives Livewire re-renders) --}}
<nav class="mb-4 flex gap-2 overflow-x-auto pb-1" aria-label="حسابي"
     x-data x-init="$el.querySelector('[aria-current]')?.scrollIntoView({ inline: 'nearest', block: 'nearest' })">
    @foreach ([['account', 'account', 'حسابي', 'user'], ['orders', 'account.orders', 'طلباتي', 'orders'], ['addresses', 'account.addresses', 'عناويني', 'zones'], ['notifications', 'account.notifications', 'التنبيهات', 'bell']] as [$key, $route, $label, $icon])
        <a href="{{ route($route) }}" wire:navigate @if ($active === $key) aria-current="page" @endif
           @class(['btn min-h-10 shrink-0 py-2', 'bg-brand-600 text-white' => $active === $key, 'bg-white text-gray-700 ring-1 ring-gray-300' => $active !== $key])>
            <x-icon :name="$icon" class="size-4" /> {{ $label }}
        </a>
    @endforeach
</nav>
