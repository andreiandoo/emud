<?php

namespace App\Livewire\Customer;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::storefront')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function authenticate()
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Throttling per email and address rather than address alone: a shared office IP should
        // not lock everyone out, and an attacker rotating addresses should not get unlimited
        // attempts against one account.
        $key = 'customer-login:'.mb_strtolower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Prea multe încercări. Reîncearcă în '.RateLimiter::availableIn($key).' secunde.',
            ]);
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($key, 300);

            throw ValidationException::withMessages([
                'email' => 'Datele de autentificare nu sunt corecte.',
            ]);
        }

        RateLimiter::clear($key);
        session()->regenerate();

        return redirect()->intended(route('customer.dashboard'));
    }

    public function render()
    {
        return view('livewire.customer.login');
    }
}
