<?php

namespace App\Livewire\Admin\Customers;

use App\Actions\Customers\SetCustomerStatus;
use App\Enums\AccountStatus;
use App\Enums\OrderStatus;
use App\Livewire\Admin\Concerns\Toasts;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.admin')]
class CustomerShow extends Component
{
    use Toasts;

    #[Locked]
    public int $customerId;

    public function boot(): void
    {
        Gate::authorize('manage-customers');
    }

    public function mount(User $customer): void
    {
        abort_unless($customer->isCustomer(), 404);
        $this->customerId = $customer->id;
    }

    public function toggleStatus(SetCustomerStatus $setStatus): void
    {
        $customer = $this->customer();
        $target = $customer->isActive() ? AccountStatus::Suspended : AccountStatus::Active;

        $setStatus->handle($customer, $target);

        $this->toast($target === AccountStatus::Suspended ? 'تم تعطيل حساب العميل وتسجيل خروجه.' : 'تم تفعيل حساب العميل.');
    }

    private function customer(): User
    {
        return User::query()->customers()->findOrFail($this->customerId);
    }

    public function render()
    {
        $customer = User::query()->customers()->with(['customer', 'addresses' => fn ($q) => $q->orderByDesc('is_default')->latest('id')])->findOrFail($this->customerId);

        $stats = $customer->orders()
            ->selectRaw('count(*) as orders_count')
            ->selectRaw('sum(case when status = ? then total else 0 end) as delivered_total', [OrderStatus::Delivered->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as delivered_count', [OrderStatus::Delivered->value])
            ->selectRaw('max(created_at) as last_order_at')
            ->toBase()
            ->first();

        return view('livewire.admin.customers.show', [
            'customer' => $customer,
            'stats' => $stats,
            'average' => (int) $stats->delivered_count > 0 ? (float) $stats->delivered_total / (int) $stats->delivered_count : 0,
            'orders' => $customer->orders()->with('payment')->latest('id')->limit(15)->get(),
        ])->title($customer->name);
    }
}
