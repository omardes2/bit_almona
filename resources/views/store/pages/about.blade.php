@php($about = \App\Support\Store::about())
<x-layouts::app title="من نحن" :canonical="route('about')">
    @include('store.partials.breadcrumbs', ['items' => [['من نحن', null]]])

    <article class="card mx-auto max-w-3xl space-y-4 p-5 sm:p-8">
        <h1 class="text-2xl font-extrabold">من نحن</h1>

        @if ($about)
            <div class="whitespace-pre-line leading-8 text-gray-800">{{ $about }}</div>
        @else
            <p class="leading-8 text-gray-800">
                {{ $storeName }}@if (\App\Support\Store::address()) — {{ \App\Support\Store::address() }}@endif.
                تصفّح المنتجات والعروض واطلب أونلاين، وتابع حالة طلبك من حسابك.
            </p>
        @endif

        <div class="flex flex-wrap gap-2 pt-2">
            <a href="{{ route('home') }}" wire:navigate class="btn-primary">تسوّق الآن</a>
            <a href="{{ route('contact') }}" wire:navigate class="btn-secondary">اتصل بنا</a>
        </div>
    </article>
</x-layouts::app>
