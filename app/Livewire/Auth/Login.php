<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\AttemptLogin;
use App\Enums\UserType;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('تسجيل الدخول')]
class Login extends Component
{
    #[Validate('required|string|max:20')]
    public string $phone = '';

    #[Validate('required|string|max:255')]
    public string $password = '';

    public bool $remember = false;

    public function login(AttemptLogin $attemptLogin): void
    {
        $this->validate();

        $attemptLogin->handle($this->phone, $this->password, UserType::Customer, $this->remember);

        $this->redirectIntended(route('account'), navigate: true);
    }

    /** True when the customer was sent here from checkout (their cart is kept). */
    public function fromCheckout(): bool
    {
        return str_contains((string) session('url.intended'), '/checkout');
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
