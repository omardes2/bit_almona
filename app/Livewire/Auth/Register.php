<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\RegisterCustomer;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('إنشاء حساب')]
class Register extends Component
{
    public string $name = '';

    public string $phone = '';

    public string $whatsapp = '';

    public bool $whatsappSameAsPhone = true;

    public string $password = '';

    public string $password_confirmation = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'phone' => ['required', 'regex:'.PhoneNumber::PATTERN, Rule::unique(User::class, 'phone')],
            'whatsapp' => [Rule::requiredIf(! $this->whatsappSameAsPhone), 'nullable', 'regex:'.PhoneNumber::PATTERN],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    protected function prepareForValidation($attributes): array
    {
        $attributes['name'] = trim($attributes['name']);
        $attributes['phone'] = PhoneNumber::normalize($attributes['phone']);
        $attributes['whatsapp'] = $this->whatsappSameAsPhone
            ? $attributes['phone']
            : PhoneNumber::normalize($attributes['whatsapp']);

        return $attributes;
    }

    public function register(RegisterCustomer $registerCustomer): void
    {
        $key = 'register|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'phone' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key), 'minutes' => ceil(RateLimiter::availableIn($key) / 60)]),
            ]);
        }

        $data = $this->validate();

        RateLimiter::hit($key, 3600);

        $user = $registerCustomer->handle($data);

        Auth::login($user);
        session()->regenerate();

        $this->redirectRoute('account', navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
