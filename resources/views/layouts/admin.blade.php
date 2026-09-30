<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow">
        <meta name="theme-color" content="#1e5d33">

        <title>{{ isset($title) ? $title.' | ' : '' }}إدارة {{ $storeName }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-screen bg-gray-100 font-sans text-gray-900 antialiased">
        @auth
            <div x-data="{ menuOpen: false }" @keydown.escape.window="menuOpen = false" class="lg:flex">
                {{-- Desktop sidebar --}}
                <aside class="sticky top-0 hidden h-screen w-64 shrink-0 flex-col border-e border-gray-200 bg-white lg:flex">
                    <div class="flex h-16 items-center border-b border-gray-100 px-5">
                        <a href="{{ route('admin.dashboard') }}" wire:navigate class="text-lg font-bold text-brand-700">{{ $storeName }}</a>
                    </div>
                    <div class="flex-1 overflow-y-auto p-3">
                        <x-admin.nav />
                    </div>
                    <div class="border-t border-gray-100 p-3">
                        <div class="mb-2 px-3 text-sm text-gray-500">{{ auth()->user()->name }}</div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-gray-600 hover:bg-gray-100">
                                <x-icon name="logout" /> تسجيل الخروج
                            </button>
                        </form>
                    </div>
                </aside>

                {{-- Mobile header --}}
                <header class="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-gray-200 bg-white px-3 lg:hidden">
                    <button type="button" @click="menuOpen = true" class="rounded-lg p-2 text-gray-700 hover:bg-gray-100" aria-label="فتح القائمة">
                        <x-icon name="menu" class="size-7" />
                    </button>
                    <a href="{{ route('admin.dashboard') }}" wire:navigate class="font-bold text-brand-700">{{ $storeName }}</a>
                    <span class="w-11"></span>
                </header>

                {{-- Mobile drawer --}}
                <div x-show="menuOpen" x-cloak class="fixed inset-0 z-40 lg:hidden">
                    <div x-show="menuOpen" x-transition.opacity @click="menuOpen = false" class="absolute inset-0 bg-black/40"></div>
                    <div x-show="menuOpen"
                         x-transition:enter="transition duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                         x-transition:leave="transition duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                         class="absolute inset-y-0 start-0 flex w-72 max-w-[85vw] flex-col bg-white shadow-xl">
                        <div class="flex h-14 items-center justify-between border-b border-gray-100 px-4">
                            <span class="font-bold text-brand-700">{{ $storeName }}</span>
                            <button type="button" @click="menuOpen = false" class="rounded-lg p-2 hover:bg-gray-100" aria-label="إغلاق القائمة">
                                <x-icon name="close" class="size-6" />
                            </button>
                        </div>
                        <div class="flex-1 overflow-y-auto p-3" @click="if ($event.target.closest('a')) menuOpen = false">
                            <x-admin.nav />
                        </div>
                        <div class="border-t border-gray-100 p-3">
                            <div class="mb-2 px-3 text-sm text-gray-500">{{ auth()->user()->name }}</div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-gray-600 hover:bg-gray-100">
                                    <x-icon name="logout" /> تسجيل الخروج
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <main class="min-w-0 flex-1 px-3 py-4 sm:px-6 sm:py-6">
                    <div class="mx-auto max-w-6xl">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        @else
            <main class="mx-auto max-w-6xl px-4 py-6">
                {{ $slot }}
            </main>
        @endauth

        <x-toasts />

        @livewireScripts
    </body>
</html>
