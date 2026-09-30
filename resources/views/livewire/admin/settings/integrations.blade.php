@php
    $styles = [
        'configured' => ['bg-green-100 text-green-800', 'مربوط'],
        'development' => ['bg-amber-100 text-amber-800', 'وضع التطوير'],
        'not_configured' => ['bg-gray-200 text-gray-700', 'غير مربوط'],
    ];
@endphp
<div class="space-y-4">
    <x-admin.page-header title="التكاملات" subtitle="حالة الربط مع المزودات الخارجية (عرض فقط)" :back="route('admin.system')" />

    <div class="flex items-start gap-2 rounded-2xl bg-blue-50 p-4 text-sm text-blue-900 ring-1 ring-blue-100" role="note">
        <x-icon name="lock" class="size-5" />
        <p>لأسباب أمنية لا يمكن إدخال مفاتيح أو كلمات مرور المزودات من لوحة التحكم. تُضبط بيانات الربط في ملف <code dir="ltr">.env</code> على الخادم فقط، ولا تُعرض هنا.</p>
    </div>

    <ul class="grid gap-3 sm:grid-cols-2">
        @foreach ($integrations as $integration)
            <li wire:key="int-{{ $integration['key'] }}" class="card p-4">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="font-bold">{{ $integration['label'] }}</h2>
                    <span @class(['shrink-0 rounded-full px-2.5 py-0.5 text-xs font-bold', $styles[$integration['status']][0]])>{{ $styles[$integration['status']][1] }}</span>
                </div>
                <p class="mt-2 text-sm text-gray-600">{{ $integration['detail'] }}</p>
            </li>
        @endforeach
    </ul>
</div>
