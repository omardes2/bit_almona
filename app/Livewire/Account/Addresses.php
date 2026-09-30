<?php

namespace App\Livewire\Account;

use App\Livewire\Forms\AddressForm;
use App\Models\Address;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app', ['noindex' => true])]
#[Title('عناويني')]
class Addresses extends Component
{
    public AddressForm $form;

    public bool $editing = false;

    #[Locked]
    public ?int $editingId = null;

    public function create(): void
    {
        $this->form->forUser(Auth::user());
        $this->editingId = null;
        $this->editing = true;
    }

    public function edit(int $id): void
    {
        $address = $this->find($id);
        $this->authorize('update', $address);

        $this->form->fillFrom($address);
        $this->editingId = $address->id;
        $this->editing = true;
    }

    public function save(): void
    {
        $address = $this->editingId ? $this->find($this->editingId) : null;

        if ($address) {
            $this->authorize('update', $address);
        }

        $this->form->save(Auth::user(), $address);

        $this->editing = false;
        $this->editingId = null;
        $this->dispatch('toast', message: 'تم حفظ العنوان.');
    }

    public function cancel(): void
    {
        $this->editing = false;
        $this->editingId = null;
        $this->form->resetValidation();
    }

    public function makeDefault(int $id): void
    {
        $address = $this->find($id);
        $this->authorize('update', $address);
        $address->makeDefault();

        $this->dispatch('toast', message: 'تم تعيين العنوان الافتراضي.');
    }

    public function delete(int $id): void
    {
        $address = $this->find($id);
        $this->authorize('delete', $address);

        $wasDefault = $address->is_default;
        $address->delete(); // orders keep their own copy of the address

        if ($wasDefault) {
            Auth::user()->addresses()->latest('id')->first()?->makeDefault();
        }

        $this->dispatch('toast', message: 'تم حذف العنوان.');
    }

    private function find(int $id): Address
    {
        return Address::findOrFail($id);
    }

    public function render()
    {
        return view('livewire.account.addresses', [
            'addresses' => Auth::user()->addresses()->orderByDesc('is_default')->latest('id')->get(),
        ]);
    }
}
