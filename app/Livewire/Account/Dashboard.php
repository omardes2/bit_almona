<?php

namespace App\Livewire\Account;

use App\Models\User;
use App\Otp\OtpService;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app', ['noindex' => true])]
#[Title('حسابي')]
class Dashboard extends Component
{
    public string $name = '';

    public string $whatsapp = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->name = $this->user->name;
        $this->whatsapp = (string) $this->user->customer?->whatsapp;
    }

    #[Computed]
    public function user(): User
    {
        return Auth::user()->loadMissing('customer');
    }

    public function updateProfile(): void
    {
        $this->whatsapp = PhoneNumber::normalize($this->whatsapp);

        $data = $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'whatsapp' => ['nullable', 'regex:'.PhoneNumber::PATTERN],
        ]);

        $this->user->update(['name' => trim($data['name'])]);
        $this->user->customer()->updateOrCreate([], ['whatsapp' => $data['whatsapp'] ?: null]);

        session()->flash('status', 'تم حفظ بياناتك بنجاح.');
    }

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $this->user->update(['password' => $this->password]);

        $this->reset('current_password', 'password', 'password_confirmation');

        session()->flash('status', 'تم تغيير كلمة المرور.');
    }

    public function render(OtpService $otp)
    {
        return view('livewire.account.dashboard', [
            'otpAvailable' => $otp->isAvailable(),
            'recentOrders' => $this->user->orders()->latest('id')->limit(3)->get(),
            'ordersCount' => $this->user->orders()->count(),
            'defaultAddress' => $this->user->addresses()->where('is_default', true)->first(),
        ]);
    }
}
