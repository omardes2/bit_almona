<?php

namespace App\Livewire\Forms;

use App\Models\Address;
use App\Models\User;
use App\Support\PhoneNumber;
use Livewire\Form;

/**
 * Address fields + validation, shared by the account address book and checkout.
 */
class AddressForm extends Form
{
    public string $label = '';

    public string $recipient_name = '';

    public string $recipient_phone = '';

    public string $address_line = '';

    public string $city = 'الخليل';

    public string $area = '';

    public string $notes = '';

    public bool $is_default = false;

    protected function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:50'],
            'recipient_name' => ['required', 'string', 'min:3', 'max:100'],
            'recipient_phone' => ['required', 'regex:'.PhoneNumber::PATTERN],
            'address_line' => ['required', 'string', 'min:5', 'max:255'],
            'city' => ['required', 'string', 'min:2', 'max:100'],
            'area' => ['required', 'string', 'min:2', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_default' => ['boolean'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'label' => 'اسم العنوان',
            'recipient_name' => 'اسم المستلم',
            'recipient_phone' => 'رقم جوال المستلم',
            'address_line' => 'العنوان التفصيلي',
            'city' => 'المدينة',
            'area' => 'المنطقة / الحي',
            'notes' => 'ملاحظات العنوان',
        ];
    }

    protected function messages(): array
    {
        return ['recipient_phone.regex' => 'رقم الجوال يجب أن يكون بالصيغة 05XXXXXXXX.'];
    }

    public function forUser(User $user): void
    {
        $this->reset();
        $this->recipient_name = $user->name;
        $this->recipient_phone = $user->phone;
    }

    public function fillFrom(Address $address): void
    {
        $this->label = (string) $address->label;
        $this->recipient_name = (string) $address->recipient_name;
        $this->recipient_phone = (string) $address->recipient_phone;
        $this->address_line = (string) $address->address_line;
        $this->city = (string) $address->city;
        $this->area = (string) $address->area;
        $this->notes = (string) $address->notes;
        $this->is_default = (bool) $address->is_default;
    }

    /**
     * Validate and save into $address (new or existing) for $user.
     */
    public function save(User $user, ?Address $address = null): Address
    {
        $this->recipient_phone = PhoneNumber::normalize($this->recipient_phone);
        $data = $this->validate();

        $address ??= new Address(['user_id' => $user->id]);
        $address->fill([
            'label' => trim($data['label']) ?: null,
            'recipient_name' => trim($data['recipient_name']),
            'recipient_phone' => $data['recipient_phone'],
            'address_line' => trim($data['address_line']),
            'city' => trim($data['city']),
            'area' => trim($data['area']),
            'notes' => trim($data['notes']) ?: null,
        ]);
        $address->user_id = $user->id;
        $address->save();

        // The first address, or one explicitly marked, becomes the default.
        if ($data['is_default'] || ! $user->addresses()->whereKeyNot($address->id)->exists()) {
            $address->makeDefault();
        }

        return $address;
    }
}
