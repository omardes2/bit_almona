<?php

namespace App\Livewire\Admin\Reports;

use App\Enums\OrderStatus;
use App\Services\Reports\SalesReport;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('التقارير')]
class ReportsPage extends Component
{
    #[Url(except: '7d')]
    public string $period = '7d';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: 7)]
    public int $chartDays = 7;

    public function boot(): void
    {
        Gate::authorize('view-reports');
    }

    public function render(SalesReport $report)
    {
        $period = array_key_exists($this->period, SalesReport::PERIODS) ? $this->period : '7d';
        [$start, $end] = $report->range($period, $this->from, $this->to);
        $now = CarbonImmutable::now(config('app.timezone'));

        return view('livewire.admin.reports.page', [
            'start' => $start,
            'end' => $end,
            'today' => $report->revenue($now->startOfDay(), $now->endOfDay()),
            // Palestinian week starts on Saturday.
            'week' => $report->revenue($now->startOfWeek(CarbonInterface::SATURDAY), $now->endOfDay()),
            'month' => $report->revenue($now->startOfMonth(), $now->endOfDay()),
            'revenue' => $report->revenue($start, $end),
            'orders' => $report->ordersValue($start, $end),
            'chart' => $report->dailyRevenue(in_array($this->chartDays, [7, 30], true) ? $this->chartDays : 7),
            'topProducts' => $report->topProducts($start, $end),
            'distribution' => $report->statusDistribution($start, $end),
            'statuses' => OrderStatus::cases(),
            'lowStock' => $report->lowStock(),
            'outOfStock' => $report->outOfStock(),
        ]);
    }
}
