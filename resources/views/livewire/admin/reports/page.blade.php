@php($money = fn ($v) => \App\Support\Money::format($v))
<div>
    <x-admin.page-header title="التقارير" :subtitle="'من '.$start->format('Y-m-d').' إلى '.$end->format('Y-m-d')">
        <x-slot:actions>
            <a href="{{ route('admin.exports', ['type' => 'orders', 'from' => $start->format('Y-m-d'), 'to' => $end->format('Y-m-d')]) }}" class="btn-secondary"><x-icon name="down" class="size-4" /> تصدير الطلبات</a>
            <a href="{{ route('admin.exports', 'products') }}" class="btn-secondary"><x-icon name="down" class="size-4" /> تصدير المنتجات</a>
        </x-slot:actions>
    </x-admin.page-header>

    {{-- Fixed cards: realised revenue --}}
    <div class="mb-4 grid grid-cols-3 gap-2 sm:gap-3">
        @foreach ([['مبيعات اليوم', $today], ['هذا الأسبوع', $week], ['هذا الشهر', $month]] as [$label, $data])
            <div class="card p-3 sm:p-4">
                <div class="text-xs text-gray-500 sm:text-sm">{{ $label }}</div>
                <div class="mt-1 text-base font-extrabold text-brand-700 sm:text-2xl"><bdi dir="ltr">{{ $money($data['revenue']) }}</bdi></div>
                <div class="text-xs text-gray-500">{{ $data['count'] }} طلب مسلّم</div>
            </div>
        @endforeach
    </div>

    {{-- Period filter --}}
    <div class="card mb-4 space-y-2 p-3">
        <div class="flex gap-2 overflow-x-auto pb-1">
            @foreach (\App\Services\Reports\SalesReport::PERIODS as $key => $label)
                <button type="button" wire:click="$set('period', '{{ $key }}')" @class(['btn min-h-9 shrink-0 py-1.5', 'bg-gray-900 text-white' => $period === $key, 'bg-white text-gray-700 ring-1 ring-gray-300' => $period !== $key])>{{ $label }}</button>
            @endforeach
        </div>
        @if ($period === 'custom')
            <div class="grid grid-cols-2 gap-2 sm:flex">
                <label class="text-sm text-gray-600">من <input type="date" wire:model.live="from" class="form-input mt-1"></label>
                <label class="text-sm text-gray-600">إلى <input type="date" wire:model.live="to" class="form-input mt-1"></label>
            </div>
        @endif
    </div>

    <div wire:loading.class="opacity-60">
        <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="card p-4"><div class="text-sm text-gray-500">المبيعات المحققة</div><div class="mt-1 text-xl font-extrabold text-brand-700"><bdi dir="ltr">{{ $money($revenue['revenue']) }}</bdi></div><div class="text-xs text-gray-500">{{ $revenue['count'] }} طلب مسلّم</div></div>
            <div class="card p-4"><div class="text-sm text-gray-500">متوسط قيمة الطلب</div><div class="mt-1 text-xl font-extrabold"><bdi dir="ltr">{{ $money($revenue['average']) }}</bdi></div><div class="text-xs text-gray-500">من الطلبات المسلّمة</div></div>
            <div class="card p-4"><div class="text-sm text-gray-500">قيمة الطلبات</div><div class="mt-1 text-xl font-extrabold"><bdi dir="ltr">{{ $money($orders['value']) }}</bdi></div><div class="text-xs text-gray-500">{{ $orders['count'] }} طلب غير ملغي</div></div>
            <div class="card p-4"><div class="text-sm text-gray-500">طلبات ملغاة</div><div class="mt-1 text-xl font-extrabold text-red-600">{{ $orders['cancelled'] }}</div><div class="text-xs text-gray-500">في الفترة المختارة</div></div>
        </div>
        <p class="mb-4 rounded-xl bg-blue-50 p-3 text-xs text-blue-900">
            <b>المبيعات المحققة</b> = الطلبات <b>المسلّمة</b> فقط (حسب تاريخ التسليم). <b>قيمة الطلبات</b> = كل الطلبات غير الملغاة المنشأة في الفترة، وتشمل طلبات لم تُسلّم بعد.
        </p>

        {{-- Chart --}}
        @php($max = max(1, max($chart)))
        <section class="card mb-4 p-4">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-bold">المبيعات اليومية (مسلّمة)</h2>
                <div class="flex gap-1">
                    @foreach ([7, 30] as $d)
                        <button type="button" wire:click="$set('chartDays', {{ $d }})" @class(['btn min-h-8 px-3 py-1 text-xs', 'bg-gray-900 text-white' => $chartDays === $d, 'bg-white text-gray-700 ring-1 ring-gray-300' => $chartDays !== $d])>{{ $d }} يوم</button>
                    @endforeach
                </div>
            </div>
            {{-- Time runs left to right, matching the date labels below. --}}
            <div class="flex h-48 items-end gap-0.5 sm:gap-1" dir="ltr" role="img" aria-label="رسم بياني للمبيعات اليومية">
                @foreach ($chart as $day => $value)
                    <div class="group relative flex h-full min-w-0 flex-1 flex-col justify-end" title="{{ $day }}: {{ $money($value) }}">
                        <div class="w-full rounded-t {{ $value > 0 ? 'bg-brand-500 group-hover:bg-brand-700' : 'bg-gray-200' }}" style="height: {{ $value > 0 ? max(3, round($value / $max * 100)) : 1 }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-1 flex justify-between text-[10px] text-gray-500" dir="ltr">
                <span>{{ \Illuminate\Support\Carbon::parse(array_key_first($chart))->format('m-d') }}</span>
                <span>{{ \Illuminate\Support\Carbon::parse(array_key_last($chart))->format('m-d') }}</span>
            </div>
            <table class="sr-only"><caption>المبيعات اليومية</caption>@foreach ($chart as $day => $value)<tr><th>{{ $day }}</th><td>{{ $money($value) }}</td></tr>@endforeach</table>
        </section>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="card p-4">
                <h2 class="mb-3 font-bold">الأكثر مبيعًا (مسلّمة)</h2>
                @forelse ($topProducts as $row)
                    <div class="flex items-center justify-between gap-2 border-t border-gray-100 py-2 text-sm first:border-0">
                        <span class="min-w-0 flex-1 truncate"><span class="me-1 text-gray-400">{{ $loop->iteration }}.</span>{{ $row->name }}</span>
                        <span class="shrink-0 text-gray-600"><bdi dir="ltr">{{ \App\Support\Decimal::trim($row->quantity) }}</bdi></span>
                        <span class="w-24 shrink-0 text-end font-bold"><bdi dir="ltr">{{ $money($row->sales) }}</bdi></span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">لا توجد مبيعات مسلّمة في هذه الفترة.</p>
                @endforelse
            </section>

            <section class="card p-4">
                <h2 class="mb-3 font-bold">توزيع الطلبات حسب الحالة</h2>
                @php($totalOrders = max(1, array_sum($distribution)))
                @foreach ($statuses as $status)
                    <div class="mb-2">
                        <div class="mb-1 flex justify-between text-sm"><span>{{ $status->label() }}</span><span class="font-bold">{{ $distribution[$status->value] }}</span></div>
                        <div class="h-2 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full {{ $status === \App\Enums\OrderStatus::Cancelled ? 'bg-red-500' : 'bg-brand-500' }}" style="width: {{ round($distribution[$status->value] / $totalOrders * 100) }}%"></div></div>
                    </div>
                @endforeach
            </section>

            <section class="card p-4">
                <h2 class="mb-3 flex items-center gap-2 font-bold"><x-icon name="alert" class="text-amber-600" /> مخزون منخفض ({{ $lowStock->count() }})</h2>
                @include('livewire.admin.reports.stock-list', ['products' => $lowStock, 'empty' => 'لا توجد منتجات منخفضة المخزون.'])
            </section>

            <section class="card p-4">
                <h2 class="mb-3 flex items-center gap-2 font-bold"><x-icon name="alert" class="text-red-600" /> نفد من المخزون ({{ $outOfStock->count() }})</h2>
                @include('livewire.admin.reports.stock-list', ['products' => $outOfStock, 'empty' => 'لا توجد منتجات نافدة.'])
            </section>
        </div>
    </div>
</div>
