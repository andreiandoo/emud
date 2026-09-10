<?php

namespace App\Livewire\Customer;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::storefront', ['fullWidth' => true])]
class Profile extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public bool $marketing_consent = false;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $status = '';

    public function mount(): void
    {
        $user = auth()->user();

        $this->name = (string) $user->name;
        $this->email = (string) $user->email;
        $this->phone = (string) $user->phone;
        $this->marketing_consent = $user->marketing_consent_at !== null;
    }

    public function saveProfile(): void
    {
        $user = auth()->user();

        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?: null,
        ]);

        // Changing the address invalidates any prior verification: the new one has not been
        // proven to belong to this customer.
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        // Withdrawal has to be recorded as clearly as consent, so the timestamp is cleared
        // rather than left standing as evidence of an agreement no longer given.
        $user->marketing_consent_at = $this->marketing_consent
            ? ($user->marketing_consent_at ?? now())
            : null;

        $user->save();

        $this->status = 'Datele au fost salvate.';
    }

    public function changePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // Requiring the current password stops someone using an unattended session to lock the
        // real owner out of their account.
        if (! Hash::check($this->current_password, auth()->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Parola actuală nu este corectă.',
            ]);
        }

        auth()->user()->forceFill(['password' => $this->password])->save();

        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->status = 'Parola a fost schimbată.';
    }

    public function render()
    {
        return view('livewire.customer.profile');
    }
}
