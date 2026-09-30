@php
    $phone = \App\Support\Store::phone();
    $whatsapp = \App\Support\Store::whatsapp();
    $whatsappUrl = \App\Support\Store::whatsappUrl();
    $address = \App\Support\Store::address();
    $hours = \App\Support\Store::workingHours();
@endphp
<x-layouts::app title="اتصل بنا" :description="'طرق التواصل مع '.$storeName.' — الهاتف وواتساب والعنوان وساعات العمل.'" :canonical="route('contact')">
    @include('store.partials.breadcrumbs', ['items' => [['اتصل بنا', null]]])

    <div class="mx-auto max-w-3xl space-y-4">
        <h1 class="text-2xl font-extrabold">اتصل بنا</h1>

        @if (! $phone && ! $whatsappUrl && ! $address && ! $hours)
            <div class="card p-6 text-center text-gray-600">سيتم إضافة معلومات التواصل قريبًا.</div>
        @else
            <div class="grid gap-3 sm:grid-cols-2">
                @if ($phone)
                    <a href="tel:{{ $phone }}" class="card flex items-center gap-3 p-4 hover:ring-brand-500">
                        <span class="rounded-full bg-brand-50 p-3 text-brand-700"><x-icon name="phone" /></span>
                        <span><span class="block text-sm text-gray-500">الهاتف</span><bdi dir="ltr" class="font-bold">{{ $phone }}</bdi></span>
                    </a>
                @endif
                @if ($whatsappUrl)
                    <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="card flex items-center gap-3 p-4 hover:ring-brand-500">
                        <span class="rounded-full bg-brand-50 p-3 text-brand-700"><x-icon name="chat" /></span>
                        <span><span class="block text-sm text-gray-500">واتساب</span><bdi dir="ltr" class="font-bold">{{ $whatsapp }}</bdi></span>
                    </a>
                @endif
                @if ($address)
                    <div class="card flex items-center gap-3 p-4">
                        <span class="rounded-full bg-brand-50 p-3 text-brand-700"><x-icon name="zones" /></span>
                        <span><span class="block text-sm text-gray-500">العنوان</span><span class="font-bold">{{ $address }}</span></span>
                    </div>
                @endif
                @if ($hours)
                    <div class="card flex items-center gap-3 p-4">
                        <span class="rounded-full bg-brand-50 p-3 text-brand-700"><x-icon name="history" /></span>
                        <span><span class="block text-sm text-gray-500">ساعات العمل</span><span class="whitespace-pre-line font-bold">{{ $hours }}</span></span>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-layouts::app>
