<?php

namespace App\Livewire\Admin\DeliveryZones;

use App\Livewire\Admin\Concerns\Toasts;
use App\Models\DeliveryZone;
use App\Support\Decimal;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class ZoneForm extends Component
{
    use Toasts;

    public ?DeliveryZone $zone = null;

    public string $name = '';

    public string $delivery_fee = '';

    public string $min_order_amount = '';

    public bool $is_active = true;

    public int|string $sort_order = 0;

    public function boot(): void
    {
        Gate::authorize('manage-delivery');
    }

    public function mount(?DeliveryZone $zone = null): void
    {
        if ($zone?->exists) {
            $this->zone = $zone;
            $this->name = $zone->name;
            $this->delivery_fee = Decimal::trim($zone->getRawOriginal('delivery_fee'));
            $this->min_order_amount = $zone->min_order_amount !== null ? Decimal::trim($zone->getRawOriginal('min_order_amount')) : '';
            $this->is_active = $zone->is_active;
            $this->sort_order = $zone->sort_order;

            return;
        }

        $this->sort_order = (int) DeliveryZone::max('sort_order') + 1;
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100', Rule::unique(DeliveryZone::class, 'name')->ignore($this->zone?->id)],
            'delivery_fee' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999.99'],
            'min_order_amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999'],
            'is_active' => ['boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
        ];
    }

    protected function validationAttributes(): array
    {
        return ['name' => 'اسم المنطقة', 'delivery_fee' => 'سعر التوصيل', 'min_order_amount' => 'الحد الأدنى للطلب'];
    }

    public function save()
    {
        $data = $this->validate();

        $zone = $this->zone ?? new DeliveryZone;
        $zone->fill([
            'name' => trim($data['name']),
            'delivery_fee' => $data['delivery_fee'],
            'min_order_amount' => $data['min_order_amount'] !== '' ? $data['min_order_amount'] : null,
            'is_active' => $data['is_active'],
            'sort_order' => (int) $data['sort_order'],
        ])->save();

        $this->flashToast($this->zone ? 'تم حفظ المنطقة.' : 'تم إضافة المنطقة «'.$zone->name.'».');

        return $this->redirectRoute('admin.delivery-zones.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.delivery-zones.form')->title($this->zone ? 'تعديل منطقة' : 'إضافة منطقة');
    }
}
