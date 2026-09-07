<?php

namespace App\Livewire\Customer;

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::storefront')]
class ForgotPassword extends Component
{
    public string $email = '';

    public string $status = '';

    public function sendLink(): void
    {
        $this->validate(['email' => ['required', 'email']]);

        Password::sendResetLink(['email' => $this->email]);

        // Always the same answer, whether or not the address has an account. Reporting "no such
        // user" would turn this form into a way to discover who shops here.
        $this->status = 'Dacă adresa există în sistem, ți-am trimis un link de resetare.';
        $this->reset('email');
    }

    public function render()
    {
        return view('livewire.customer.forgot-password');
    }
}
