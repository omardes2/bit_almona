<?php

namespace App\Livewire\Admin\Auth;

use App\Actions\Auth\AttemptLogin;
use App\Enums\UserType;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('دخول الإدارة')]
class Login extends Component
{
    #[Validate('required|string|max:20')]
    public string $phone = '';

    #[Validate('required|string|max:255')]
    public string $password = '';

    public function login(AttemptLogin $attemptLogin): void
    {
        $this->validate();

        $attemptLogin->handle($this->phone, $this->password, UserType::Admin);

        $this->redirectIntended(route('admin.dashboard'));
    }

    public function render()
    {
        return view('livewire.admin.auth.login');
    }
}
