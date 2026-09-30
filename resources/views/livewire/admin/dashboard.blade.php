<div>
    <h1 class="mb-6 text-2xl font-bold">لوحة التحكم</h1>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ($stats as $label => $value)
            <div class="rounded-2xl bg-white p-4 shadow-sm">
                <div class="text-sm text-gray-500">{{ $label }}</div>
                <div class="mt-1 text-2xl font-bold">{{ number_format($value) }}</div>
            </div>
        @endforeach
    </div>

    <p class="mt-6 text-sm text-gray-500">إدارة المنتجات والطلبات ستُضاف في المراحل القادمة.</p>
</div>
