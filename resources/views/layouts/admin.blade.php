<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow">

        <title>{{ isset($title) ? $title.' | ' : '' }}إدارة {{ $storeName }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-screen bg-gray-100 font-sans text-gray-900 antialiased">
        @auth
            <header class="border-b border-gray-200 bg-white">
                <div class="mx-auto flex h-14 max-w-6xl items-center justify-between px-4">
                    <a href="{{ route('admin.dashboard') }}" class="font-bold text-brand-700">إدارة {{ $storeName }}</a>

                    <div class="flex items-center gap-3 text-sm">
                        <span class="hidden text-gray-600 sm:inline">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-gray-500 hover:text-gray-800">خروج</button>
                        </form>
                    </div>
                </div>
            </header>
        @endauth

        <main class="mx-auto max-w-6xl px-4 py-6">
            {{ $slot }}
        </main>

        @livewireScripts
    </body>
</html>
