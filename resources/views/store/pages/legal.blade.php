<x-layouts::app :title="$title" :canonical="url()->current()" :noindex="true">
    @include('store.partials.breadcrumbs', ['items' => [[$title, null]]])

    <article class="card mx-auto max-w-3xl space-y-4 p-5 sm:p-8">
        <h1 class="text-2xl font-extrabold">{{ $title }}</h1>

        {{-- Placeholder on purpose: no invented legal text. Replace with the approved text before launch. --}}
        <div class="rounded-xl bg-amber-50 p-4 text-amber-900 ring-1 ring-amber-200" role="note">
            <p class="font-bold">يجب استبدال هذا المحتوى بالنص القانوني المعتمد قبل الإطلاق.</p>
        </div>

        <p class="text-sm text-gray-600">لأي استفسار يمكنك <a href="{{ route('contact') }}" wire:navigate class="font-medium text-brand-700">التواصل مع {{ $storeName }}</a>.</p>
    </article>
</x-layouts::app>
