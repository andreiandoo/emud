<?php

namespace App\Livewire\Storefront;

use App\Models\ContactMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::storefront')]
class Contact extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $subject = '';

    public string $message = '';

    /**
     * A field no person can see and no person will fill. Bots complete every input they find, so
     * anything arriving here identifies itself as automated without asking a customer to solve
     * a puzzle.
     */
    public string $website = '';

    public string $sent = '';

    public function mount(): void
    {
        if ($user = auth()->user()) {
            $this->name = (string) $user->name;
            $this->email = (string) $user->email;
            $this->phone = (string) $user->phone;
        }
    }

    public function send(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'subject' => ['nullable', 'string', 'max:180'],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
        ]);

        // Answered exactly as a real submission would be. Telling a bot it was detected only
        // teaches whoever wrote it what to change.
        if ($this->website !== '') {
            $this->reset(['subject', 'message', 'website']);
            $this->sent = 'Mesajul a fost trimis. Îți răspundem cât putem de repede.';

            return;
        }

        $key = 'contact:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'message' => 'Ai trimis prea multe mesaje. Încearcă din nou peste '.RateLimiter::availableIn($key).' secunde.',
            ]);
        }

        RateLimiter::hit($key, 3600);

        ContactMessage::create([
            ...$data,
            'phone' => $data['phone'] ?: null,
            'subject' => $data['subject'] ?: null,
            'ip_address' => request()->ip(),
        ]);

        $this->reset(['subject', 'message']);
        $this->sent = 'Mesajul a fost trimis. Îți răspundem cât putem de repede.';
    }

    public function render()
    {
        return view('livewire.storefront.contact');
    }
}
