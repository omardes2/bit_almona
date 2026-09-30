{{-- $message: what is not available. Shown when no OTP sender is configured. --}}
@php
    $storePhone = \App\Support\Store::phone();
    $storeWhatsapp = \App\Support\Store::whatsappUrl();
@endphp
<div class="rounded-2xl bg-amber-50 p-5 text-amber-900 ring-1 ring-amber-200" role="status">
    <p class="flex items-center gap-2 font-bold"><x-icon name="alert" class="size-5" /> {{ $message }}</p>
    @if ($storePhone || $storeWhatsapp)
        <p class="mt-2 text-sm">للمساعدة تواصل مع المتجر:</p>
        <div class="mt-2 flex flex-wrap gap-2">
            @if ($storePhone)
                <a href="tel:{{ $storePhone }}" class="btn-secondary min-h-10 py-2"><x-icon name="phone" class="size-4" /> <bdi dir="ltr">{{ $storePhone }}</bdi></a>
            @endif
            @if ($storeWhatsapp)
                <a href="{{ $storeWhatsapp }}" target="_blank" rel="noopener" class="btn-secondary min-h-10 py-2"><x-icon name="chat" class="size-4" /> واتساب</a>
            @endif
        </div>
    @endif
</div>
