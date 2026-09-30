<?php

namespace App\Livewire\Admin\DeliveryZones;

use App\Livewire\Admin\Concerns\ReordersRecords;
use App\Livewire\Admin\Concerns\Toasts;
use App\Models\DeliveryZone;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('مناطق التوصيل')]
class ZoneIndex extends Component
{
    use ReordersRecords, Toasts;

    public function boot(): void
    {
        Gate::authorize('manage-delivery');
    }

    public function toggleActive(int $id): void
    {
        $zone = DeliveryZone::findOrFail($id);
        $zone->update(['is_active' => ! $zone->is_active]);

        $this->toast($zone->is_active ? 'تم تفعيل المنطقة.' : 'تم تعطيل المنطقة. لن تظهر للزبائن عند إتمام الطلب.');
    }

    public function move(int $id, int $direction): void
    {
        $this->moveInOrder(DeliveryZone::query(), DeliveryZone::findOrFail($id), $direction > 0 ? 1 : -1);
    }

    /**
     * Safe delete: a zone used by past orders is kept (orders also keep a
     * snapshot of its name and fee) — the admin should deactivate it instead.
     */
    public function delete(int $id): void
    {
        $zone = DeliveryZone::findOrFail($id);

        if ($zone->orders()->exists()) {
            $this->toast('لا يمكن حذف منطقة استُخدمت في طلبات سابقة. يمكنك تعطيلها بدلًا من ذلك.', 'error');

            return;
        }

        $zone->delete();
        $this->toast('تم حذف المنطقة «'.$zone->name.'».');
    }

    public function render()
    {
        return view('livewire.admin.delivery-zones.index', [
            'zones' => DeliveryZone::query()->withCount('orders')->ordered()->get(),
        ]);
    }
}
