<?php

namespace App\Livewire\Admin\Settings;

use App\Enums\SettingType;
use App\Livewire\Admin\Concerns\Toasts;
use App\Models\StoreSetting;
use App\Services\Media\ImageStorage;
use App\Support\Decimal;
use App\Support\ImageRules;
use App\Support\PhoneNumber;
use App\Support\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Edits the existing key/value store_settings rows (typed via SettingType).
 */
#[Layout('layouts.admin')]
#[Title('إعدادات المتجر')]
class StoreSettingsForm extends Component
{
    use Toasts, WithFileUploads;

    public const CURRENCIES = [
        'ILS' => ['label' => 'شيكل (ILS)', 'symbol' => '₪'],
        'JOD' => ['label' => 'دينار أردني (JOD)', 'symbol' => 'د.أ'],
        'USD' => ['label' => 'دولار (USD)', 'symbol' => '$'],
    ];

    public string $store_name = '';

    public string $store_phone = '';

    public string $store_whatsapp = '';

    public string $store_address = '';

    public string $currency_code = 'ILS';

    public string $min_order_amount = '';

    public string $working_hours = '';

    /** @var TemporaryUploadedFile|null */
    public $logo = null;

    public function boot(): void
    {
        Gate::authorize('manage-settings');
    }

    public function mount(): void
    {
        foreach (['store_name', 'store_phone', 'store_whatsapp', 'store_address', 'working_hours'] as $key) {
            $this->{$key} = (string) StoreSetting::get($key, '');
        }

        $this->currency_code = (string) StoreSetting::get('currency_code', 'ILS');
        $min = StoreSetting::get('min_order_amount');
        $this->min_order_amount = $min ? Decimal::trim((string) $min) : '';
    }

    protected function rules(): array
    {
        return [
            'store_name' => ['required', 'string', 'min:2', 'max:100'],
            'store_phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{6,20}$/'],
            'store_whatsapp' => ['nullable', 'regex:'.PhoneNumber::PATTERN],
            'store_address' => ['nullable', 'string', 'max:255'],
            'currency_code' => ['required', Rule::in(array_keys(self::CURRENCIES))],
            'min_order_amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999'],
            'working_hours' => ['nullable', 'string', 'max:500'],
            'logo' => ImageRules::rules(),
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'store_name' => 'اسم المتجر',
            'store_phone' => 'رقم الهاتف',
            'store_whatsapp' => 'رقم واتساب',
            'store_address' => 'العنوان',
            'currency_code' => 'العملة',
            'min_order_amount' => 'الحد الأدنى للطلب',
            'working_hours' => 'ساعات العمل',
            'logo' => 'الشعار',
        ];
    }

    public function updatedLogo(): void
    {
        $this->validateOnly('logo');
    }

    public function removeLogo(ImageStorage $images): void
    {
        $old = StoreSetting::get('store_logo');
        StoreSetting::set('store_logo', null, SettingType::Image);
        $images->delete($old);
        $this->toast('تم حذف الشعار.');
    }

    public function save(ImageStorage $images): void
    {
        $this->store_whatsapp = $this->store_whatsapp !== '' ? PhoneNumber::normalize($this->store_whatsapp) : '';
        $data = $this->validate();

        $oldLogo = StoreSetting::get('store_logo');
        $newLogo = $this->logo ? $images->store($this->logo, 'branding') : null;

        DB::transaction(function () use ($data, $newLogo) {
            StoreSetting::set('store_name', trim($data['store_name']), SettingType::String);
            StoreSetting::set('store_phone', $data['store_phone'] ?: null, SettingType::String);
            StoreSetting::set('store_whatsapp', $data['store_whatsapp'] ?: null, SettingType::String);
            StoreSetting::set('store_address', $data['store_address'] ?: null, SettingType::Text);
            StoreSetting::set('currency_code', $data['currency_code'], SettingType::String);
            StoreSetting::set('currency_symbol', self::CURRENCIES[$data['currency_code']]['symbol'], SettingType::String);
            StoreSetting::set('min_order_amount', $data['min_order_amount'] !== '' ? $data['min_order_amount'] : null, SettingType::Decimal);
            StoreSetting::set('working_hours', $data['working_hours'] ?: null, SettingType::Text);

            if ($newLogo) {
                StoreSetting::set('store_logo', $newLogo, SettingType::Image);
            }
        });

        if ($newLogo && $oldLogo) {
            $images->delete($oldLogo);
        }

        $this->reset('logo');
        $this->toast('تم حفظ إعدادات المتجر.');
    }

    public function render()
    {
        return view('livewire.admin.settings.form', [
            'logoUrl' => Store::logoUrl(),
        ]);
    }
}
