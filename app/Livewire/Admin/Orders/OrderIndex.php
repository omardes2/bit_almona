<?php

namespace App\Livewire\Admin\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\PhoneNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('الطلبات')]
class OrderIndex extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public function boot(): void
    {
        Gate::authorize('manage-orders');
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'status', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status', 'from', 'to');
        $this->resetPage();
    }

    public function render()
    {
        $term = trim($this->search);
        $phone = PhoneNumber::normalize($term);

        $base = Order::query()
            ->when($term !== '', fn ($q) => $q->where(function ($q) use ($term, $phone) {
                $q->where('order_number', 'like', '%'.strtoupper($term).'%')
                    ->orWhere('customer_name', 'like', '%'.$term.'%')
                    ->orWhere('recipient_name', 'like', '%'.$term.'%');

                if (strlen($phone) >= 4 && ctype_digit($phone)) {
                    $q->orWhere('customer_phone', 'like', '%'.$phone.'%')
                        ->orWhere('recipient_phone', 'like', '%'.$phone.'%');
                }
            }))
            ->when($this->validDate($this->from), fn ($q, $from) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($this->validDate($this->to), fn ($q, $to) => $q->where('created_at', '<=', $to->endOfDay()));

        $counts = (clone $base)->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        $orders = (clone $base)
            ->when(OrderStatus::tryFrom($this->status), fn ($q, $status) => $q->where('status', $status))
            ->with('payment')
            ->latest('id')
            ->paginate(20);

        return view('livewire.admin.orders.index', [
            'orders' => $orders,
            'counts' => $counts,
            'statuses' => OrderStatus::cases(),
        ]);
    }

    private function validDate(string $value): ?Carbon
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? rescue(fn () => Carbon::createFromFormat('Y-m-d', $value), null, false) : null;
    }
}
