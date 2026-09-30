@php
    $phone = \App\Support\Store::phone();
    $whatsappUrl = \App\Support\Store::whatsappUrl();
    $address = \App\Support\Store::address();
@endphp
<footer class="border-t border-gray-200 bg-white pb-24 md:pb-0">
    <div class="mx-auto grid max-w-6xl gap-6 px-4 py-8 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <div class="text-lg font-bold text-brand-700">{{ $storeName }}</div>
            @if ($address)
                <p class="mt-1 text-sm text-gray-600">{{ $address }}</p>
            @endif
        </div>
        <div>
            <h2 class="mb-2 text-sm font-bold text-gray-900">تسوّق</h2>
            <ul class="space-y-1.5 text-sm text-gray-600">
                <li><a href="{{ route('offers') }}" wire:navigate class="hover:text-brand-700">العروض</a></li>
                <li><a href="{{ route('search') }}" wire:navigate class="hover:text-brand-700">البحث</a></li>
                <li><a href="{{ route('cart') }}" wire:navigate class="hover:text-brand-700">السلة</a></li>
            </ul>
        </div>
        <div>
            <h2 class="mb-2 text-sm font-bold text-gray-900">المتجر</h2>
            <ul class="space-y-1.5 text-sm text-gray-600">
                <li><a href="{{ route('about') }}" wire:navigate class="hover:text-brand-700">من نحن</a></li>
                <li><a href="{{ route('contact') }}" wire:navigate class="hover:text-brand-700">اتصل بنا</a></li>
                <li><a href="{{ route('privacy') }}" wire:navigate class="hover:text-brand-700">سياسة الخصوصية</a></li>
                <li><a href="{{ route('terms') }}" wire:navigate class="hover:text-brand-700">الشروط والأحكام</a></li>
            </ul>
        </div>
        @if ($phone || $whatsappUrl)
            <div>
                <h2 class="mb-2 text-sm font-bold text-gray-900">تواصل معنا</h2>
                <ul class="space-y-2 text-sm text-gray-600">
                    @if ($phone)
                        <li><a href="tel:{{ $phone }}" class="inline-flex items-center gap-2 hover:text-brand-700"><x-icon name="phone" class="size-4" /> <bdi dir="ltr">{{ $phone }}</bdi></a></li>
                    @endif
                    @if ($whatsappUrl)
                        <li><a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 hover:text-brand-700"><x-icon name="chat" class="size-4" /> واتساب</a></li>
                    @endif
                </ul>
            </div>
        @endif
    </div>
    <div class="border-t border-gray-100 py-4 text-center text-xs text-gray-500">© {{ now()->year }} {{ $storeName }}</div>
</footer>
