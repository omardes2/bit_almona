<div>
    <x-admin.page-header title="العملاء" :subtitle="$total.' عميل'">
        <x-slot:actions>
            <a href="{{ route('admin.exports', 'customers') }}" class="btn-secondary"><x-icon name="down" class="size-4" /> تصدير CSV</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mb-4 space-y-2">
        <div class="relative">
            <x-icon name="search" class="pointer-events-none absolute inset-y-0 start-3 my-auto text-gray-400" />
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="الاسم أو رقم الجوال أو الواتساب..." class="form-input ps-10" aria-label="بحث في العملاء">
        </div>
        <div class="grid grid-cols-2 gap-2 sm:flex">
            <select wire:model.live="status" class="form-input sm:w-44" aria-label="الحالة">
                <option value="">كل الحالات</option>
                <option value="active">فعّال</option>
                <option value="suspended">موقوف</option>
            </select>
            <select wire:model.live="sort" class="form-input sm:w-52" aria-label="الترتيب">
                <option value="latest">الأحدث تسجيلًا</option>
                <option value="top">الأكثر شراءً</option>
            </select>
        </div>
    </div>

    <div wire:loading.class="opacity-60" wire:target="search,status,sort,gotoPage,nextPage,previousPage">
        @if ($customers->isEmpty())
            <x-admin.empty-state icon="customers" title="لا يوجد عملاء مطابقون" />
        @else
            <div class="space-y-2 md:hidden">
                @foreach ($customers as $c)
                    <a href="{{ route('admin.customers.show', $c) }}" wire:navigate wire:key="m-c-{{ $c->id }}" class="card block p-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="truncate font-bold">{{ $c->name }}</div>
                                <div class="text-sm text-gray-600"><bdi dir="ltr">{{ $c->phone }}</bdi></div>
                            </div>
                            <x-admin.status-pill :active="$c->isActive()" />
                        </div>
                        <div class="mt-2 flex justify-between text-sm">
                            <span class="text-gray-500">{{ $c->orders_count }} طلب</span>
                            <span class="font-bold"><bdi dir="ltr">{{ \App\Support\Money::format($c->purchases_total ?? 0) }}</bdi></span>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="card hidden overflow-x-auto md:block">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium">العميل</th>
                            <th class="px-3 py-3 text-start font-medium">واتساب</th>
                            <th class="px-3 py-3 text-start font-medium">التسجيل</th>
                            <th class="px-3 py-3 text-start font-medium">الطلبات</th>
                            <th class="px-3 py-3 text-start font-medium">المشتريات (مسلّمة)</th>
                            <th class="px-3 py-3 text-start font-medium">آخر طلب</th>
                            <th class="px-3 py-3 text-start font-medium">الحالة</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($customers as $c)
                            <tr wire:key="d-c-{{ $c->id }}" class="hover:bg-gray-50/60">
                                <td class="px-4 py-3"><a href="{{ route('admin.customers.show', $c) }}" wire:navigate class="font-bold hover:text-brand-700">{{ $c->name }}</a><div class="text-xs text-gray-500"><bdi dir="ltr">{{ $c->phone }}</bdi></div></td>
                                <td class="px-3 py-3 text-gray-600" dir="ltr">{{ $c->customer?->whatsapp ?? '—' }}</td>
                                <td class="px-3 py-3 text-gray-600"><bdi dir="ltr">{{ $c->created_at->format('Y-m-d') }}</bdi></td>
                                <td class="px-3 py-3">{{ $c->orders_count }}</td>
                                <td class="px-3 py-3 font-bold"><bdi dir="ltr">{{ \App\Support\Money::format($c->purchases_total ?? 0) }}</bdi></td>
                                <td class="px-3 py-3 text-gray-600"><bdi dir="ltr">{{ $c->last_order_at ? \Illuminate\Support\Carbon::parse($c->last_order_at)->format('Y-m-d') : '—' }}</bdi></td>
                                <td class="px-3 py-3"><x-admin.status-pill :active="$c->isActive()" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $customers->links('components.admin.pagination') }}</div>
        @endif
    </div>
</div>
