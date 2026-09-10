<?php

namespace App\Livewire\Customer;

use App\Models\User;
use App\Storefront\CartManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::storefront')]
class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $marketing_consent = false;

    public function register()
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // The role is set here rather than taken from input: a registration form must never be
        // able to mint an administrator, and the column's default is not a safeguard once
        // attributes are mass assigned.
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?: null,
            'password' => $data['password'],
            'role' => 'customer',
        ]);

        // Marketing consent is recorded separately from the account, and only when given, so
        // the timestamp is evidence of when the customer opted in.
        if ($this->marketing_consent) {
            $user->forceFill(['marketing_consent_at' => now()])->save();
        }

        Auth::login($user);
        session()->regenerate();

        // Whatever the visitor put in the basket before making the account comes with them.
        app(CartManager::class)->mergeInto($user);

        return redirect()->route('customer.garage');
    }

    public function render()
    {
        return view('livewire.customer.register');
    }
}
