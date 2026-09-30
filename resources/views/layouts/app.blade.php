@props([
    'title' => null,
    'description' => null,
    'canonical' => null,
    'image' => null,
    'type' => 'website',
    'noindex' => false,
])
@php
    // Works both as a Livewire layout and as the <x-layouts::app> component.
    $storeName ??= \App\Support\Store::name();
    $cartCount ??= app(\App\Services\Cart\CartService::class)->count();
    $pageTitle = $title ? $title.' | '.$storeName : $storeName;
    $metaDescription = \Illuminate\Support\Str::limit(strip_tags($description ?: 'تسوّق مونة بيتك أونلاين من '.$storeName.(\App\Support\Store::address() ? ' — '.\App\Support\Store::address() : '').'. أسعار واضحة وعروض يومية.'), 160);
    $logoUrl = \App\Support\Store::logoUrl();
    $categoriesMenu = \App\Support\StorefrontCache::categoryTree();
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#25743f">

        <title>{{ $pageTitle }}</title>
        <meta name="description" content="{{ $metaDescription }}">
        @if ($noindex)
            <meta name="robots" content="noindex, follow">
        @endif
        @if ($canonical)
            <link rel="canonical" href="{{ $canonical }}">
        @endif

        <meta property="og:site_name" content="{{ $storeName }}">
        <meta property="og:locale" content="ar_AR">
        <meta property="og:type" content="{{ $type }}">
        <meta property="og:title" content="{{ $title ?? $storeName }}">
        <meta property="og:description" content="{{ $metaDescription }}">
        <meta property="og:url" content="{{ $canonical ?? url()->current() }}">
        @if ($image ?? $logoUrl)
            <meta property="og:image" content="{{ $image ?? $logoUrl }}">
        @endif
        <meta name="twitter:card" content="summary_large_image">

        @stack('head')

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-screen bg-gray-50 font-sans text-gray-900 antialiased"
          x-data x-init="$store.cart.count = {{ (int) $cartCount }}"
          @cart-updated.window="$store.cart.count = $event.detail.count">
        <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:start-2 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow">تخطَّ إلى المحتوى</a>

        <header x-data="{ searchOpen: false, menuOpen: false }" @keydown.escape.window="searchOpen = false; menuOpen = false"
                class="sticky top-0 z-30 border-b border-gray-200 bg-white/95 backdrop-blur">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-2 px-3 py-2 md:flex-nowrap md:gap-4 md:px-4 md:py-3">
                <a href="{{ route('home') }}" wire:navigate class="flex shrink-0 items-center gap-2 rounded-lg" aria-label="{{ $storeName }} — الرئيسية">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $storeName }}" width="40" height="40" class="size-10 rounded-lg object-contain">
                    @endif
                    <span class="text-lg font-bold text-brand-700 md:text-xl">{{ $storeName }}</span>
                </a>

                {{-- Desktop: categories menu --}}
                <div class="relative hidden md:block" @click.outside="menuOpen = false">
                    <button type="button" @click="menuOpen = !menuOpen" :aria-expanded="menuOpen" class="btn-ghost gap-1 font-bold text-gray-700">
                        <x-icon name="categories" /> الأقسام
                    </button>
                    <div x-show="menuOpen" x-cloak x-transition.origin.top.right class="absolute start-0 z-40 mt-2 w-72 rounded-2xl bg-white p-2 shadow-xl ring-1 ring-gray-200">
                        @include('store.partials.categories-menu', ['categories' => $categoriesMenu])
                    </div>
                </div>

                {{-- Search (inline on desktop, toggled row on mobile) --}}
                <div :class="searchOpen ? 'block' : 'hidden'" class="order-last w-full md:order-none md:block md:flex-1">
                    <livewire:store.search-box />
                </div>

                <nav class="ms-auto flex items-center gap-1" aria-label="الحساب والسلة">
                    <button type="button" @click="searchOpen = !searchOpen; $nextTick(() => searchOpen && document.getElementById('header-search')?.focus())"
                            class="rounded-xl p-2.5 text-gray-700 hover:bg-gray-100 md:hidden" :aria-expanded="searchOpen" aria-label="بحث">
                        <x-icon name="search" class="size-6" />
                    </button>

                    @auth
                        @if (auth()->user()->isAdmin())
                            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-1 rounded-xl p-2.5 text-gray-700 hover:bg-gray-100" aria-label="لوحة الإدارة">
                                <x-icon name="settings" class="size-6" /><span class="hidden text-sm font-bold lg:inline">لوحة الإدارة</span>
                            </a>
                        @else
                            <a href="{{ route('account') }}" wire:navigate class="flex items-center gap-1 rounded-xl p-2.5 text-gray-700 hover:bg-gray-100" aria-label="حسابي">
                                <x-icon name="user" class="size-6" /><span class="hidden text-sm font-bold lg:inline">حسابي</span>
                            </a>
                        @endif
                    @else
                        <a href="{{ route('login') }}" wire:navigate class="flex items-center gap-1 rounded-xl p-2.5 text-gray-700 hover:bg-gray-100" aria-label="تسجيل الدخول">
                            <x-icon name="user" class="size-6" /><span class="hidden text-sm font-bold lg:inline">تسجيل الدخول</span>
                        </a>
                    @endauth

                    <a href="{{ route('cart') }}" wire:navigate class="relative flex items-center gap-1 rounded-xl p-2.5 text-gray-700 hover:bg-gray-100"
                       :aria-label="'السلة، ' + $store.cart.count + ' منتج'" aria-label="السلة">
                        <x-icon name="cart" class="size-6" />
                        <span class="hidden text-sm font-bold lg:inline">السلة</span>
                        <span x-show="$store.cart.count > 0" x-cloak x-text="$store.cart.count"
                              class="absolute -top-0.5 -start-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-xs font-bold text-white"></span>
                    </a>
                </nav>
            </div>
        </header>

        <main id="main" class="mx-auto max-w-6xl px-3 pb-28 pt-4 md:px-4 md:pb-12 md:pt-6">
            {{ $slot }}
        </main>

        @include('store.partials.footer')

        {{-- Mobile bottom navigation --}}
        <nav x-data="{ categoriesOpen: false }" class="fixed inset-x-0 bottom-0 z-30 border-t border-gray-200 bg-white pb-[env(safe-area-inset-bottom)] md:hidden" aria-label="التنقل الرئيسي">
            <div class="grid grid-cols-5">
                @php
                    $tab = 'flex flex-col items-center gap-0.5 py-2 text-xs font-medium';
                    $on = 'text-brand-700';
                    $off = 'text-gray-500';
                @endphp
                <a href="{{ route('home') }}" wire:navigate class="{{ $tab }} {{ request()->routeIs('home') ? $on : $off }}" @if (request()->routeIs('home')) aria-current="page" @endif>
                    <x-icon name="home" class="size-6" /> الرئيسية
                </a>
                <button type="button" @click="categoriesOpen = true" class="{{ $tab }} {{ request()->routeIs('category.*') ? $on : $off }}">
                    <x-icon name="categories" class="size-6" /> الأقسام
                </button>
                <a href="{{ route('search') }}" wire:navigate class="{{ $tab }} {{ request()->routeIs('search') ? $on : $off }}">
                    <x-icon name="search" class="size-6" /> البحث
                </a>
                <a href="{{ route('cart') }}" wire:navigate class="{{ $tab }} relative {{ request()->routeIs('cart') ? $on : $off }}">
                    <span class="relative">
                        <x-icon name="cart" class="size-6" />
                        <span x-show="$store.cart.count > 0" x-cloak x-text="$store.cart.count"
                              class="absolute -top-1.5 -start-2.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white"></span>
                    </span>
                    السلة
                </a>
                <a href="{{ auth()->check() ? (auth()->user()->isAdmin() ? route('admin.dashboard') : route('account')) : route('login') }}" wire:navigate
                   class="{{ $tab }} {{ request()->routeIs('account', 'login', 'register') ? $on : $off }}">
                    <x-icon name="user" class="size-6" /> {{ auth()->check() ? 'حسابي' : 'دخول' }}
                </a>
            </div>

            {{-- Categories sheet --}}
            <div x-show="categoriesOpen" x-cloak class="fixed inset-0 z-40" role="dialog" aria-modal="true" aria-label="الأقسام">
                <div x-show="categoriesOpen" x-transition.opacity @click="categoriesOpen = false" class="absolute inset-0 bg-black/40"></div>
                <div x-show="categoriesOpen" x-transition:enter="transition duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                     class="absolute inset-x-0 bottom-0 max-h-[80vh] overflow-y-auto rounded-t-3xl bg-white p-4 pb-[calc(1rem+env(safe-area-inset-bottom))]"
                     @click="if ($event.target.closest('a')) categoriesOpen = false">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-lg font-bold">الأقسام</h2>
                        <button type="button" @click="categoriesOpen = false" class="rounded-lg p-2 hover:bg-gray-100" aria-label="إغلاق"><x-icon name="close" /></button>
                    </div>
                    @include('store.partials.categories-menu', ['categories' => $categoriesMenu])
                </div>
            </div>
        </nav>

        <livewire:store.cart-drawer />
        <x-toasts />

        @livewireScripts
    </body>
</html>
