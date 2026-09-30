<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#25743f">

        <title>{{ isset($title) ? $title.' | ' : '' }}{{ $storeName }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-screen bg-gray-50 font-sans text-gray-900 antialiased">
        <header class="sticky top-0 z-30 border-b border-gray-200 bg-white">
            <div class="mx-auto flex h-14 max-w-5xl items-center justify-between px-4">
                <a href="{{ route('home') }}" wire:navigate class="text-lg font-bold text-brand-700">{{ $storeName }}</a>

                <nav class="flex items-center gap-3 text-sm">
                    @auth
                        @if (auth()->user()->isAdmin())
                            <a href="{{ route('admin.dashboard') }}" class="font-medium text-brand-700">لوحة الإدارة</a>
                        @else
                            <a href="{{ route('account') }}" wire:navigate class="font-medium text-brand-700">حسابي</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-gray-500 hover:text-gray-800">خروج</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" wire:navigate class="font-medium text-brand-700">دخول</a>
                        <a href="{{ route('register') }}" wire:navigate class="rounded-lg bg-brand-600 px-3 py-1.5 font-medium text-white">حساب جديد</a>
                    @endauth
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-6">
            {{ $slot }}
        </main>

        @livewireScripts
    </body>
</html>
