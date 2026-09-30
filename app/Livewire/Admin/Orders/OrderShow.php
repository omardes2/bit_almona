<?php

namespace App\Livewire\Admin\Orders;

use App\Actions\Orders\ChangeOrderStatus;
use App\Actions\Orders\MarkPaymentPaid;
use App\Enums\OrderStatus;
use App\Livewire\Admin\Concerns\Toasts;
use App\Models\Order;
use App\Payments\PaymentException;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.admin')]
class OrderShow extends Component
{
    use Toasts;

    #[Locked]
    public int $orderId;

    public string $cancellationReason = '';

    public string $adminNotes = '';

    public function boot(): void
    {
        Gate::authorize('manage-orders');
    }

    public function mount(Order $order): void
    {
        $this->orderId = $order->id;
        $this->adminNotes = (string) $order->admin_notes;
    }

    public function changeStatus(string $status, ChangeOrderStatus $changeStatus): void
    {
        $target = OrderStatus::tryFrom($status);

        if ($target === null) {
            return;
        }

        if ($target === OrderStatus::Cancelled) {
            $this->validate(['cancellationReason' => ['required', 'string', 'min:3', 'max:500']], [], ['cancellationReason' => 'سبب الإلغاء']);
        }

        try {
            $changeStatus->handle($this->order(), $target, auth()->user(), $target === OrderStatus::Cancelled ? trim($this->cancellationReason) : null);
        } catch (InvalidArgumentException $e) {
            $this->toast($e->getMessage(), 'error');

            return;
        }

        $this->reset('cancellationReason');
        $this->toast('تم تغيير حالة الطلب إلى «'.$target->label().'».'.($target === OrderStatus::Cancelled ? ' أُعيدت الكميات للمخزون.' : ''));
    }

    public function markPaid(MarkPaymentPaid $markPaid): void
    {
        try {
            $markPaid->handle($this->order());
        } catch (PaymentException $e) {
            $this->toast($e->getMessage(), 'error');

            return;
        }

        $this->toast('تم تسجيل استلام المبلغ.');
    }

    public function saveNotes(): void
    {
        $this->validate(['adminNotes' => ['nullable', 'string', 'max:2000']]);
        $this->order()->update(['admin_notes' => $this->adminNotes ?: null]);
        $this->toast('تم حفظ الملاحظات.');
    }

    private function order(): Order
    {
        return Order::findOrFail($this->orderId);
    }

    public function render()
    {
        $order = Order::query()
            ->with(['items', 'payment', 'statusHistory.changedBy:id,name', 'user:id,name,phone'])
            ->findOrFail($this->orderId);

        return view('livewire.admin.orders.show', ['order' => $order])->title('طلب '.$order->order_number);
    }
}
