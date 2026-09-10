<?php

namespace App\Livewire\Storefront;

use App\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * The footer sign-up.
 *
 * Signing up twice is deliberately indistinguishable from signing up once, and both say the same
 * thing back: the form must not become a way to ask "is this address on your list".
 */
class NewsletterForm extends Component
{
    public string $email = '';

    /** Invisible to a person, filled by a bot. Same trick as the contact form. */
    public string $website = '';

    public string $done = '';

    public function subscribe(): void
    {
        $data = $this->validate([
            'email' => ['required', 'email', 'max:255'],
        ], [], ['email' => 'adresa de e-mail']);

        if ($this->website !== '') {
            $this->reset(['email', 'website']);
            $this->done = 'Gata. Îți scriem când apare ceva bun.';

            return;
        }

        $key = 'newsletter:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Prea multe încercări. Mai încearcă peste '.RateLimiter::availableIn($key).' secunde.',
            ]);
        }

        RateLimiter::hit($key, 3600);

        // Lower-cased on the way in, because the unique index is on the raw column and
        // Ion@example.com would otherwise sit next to ion@example.com as a second subscriber.
        NewsletterSubscriber::query()->updateOrCreate(
            ['email' => mb_strtolower($data['email'])],
            [
                'source' => 'footer',
                'subscribed_at' => now(),
                // Someone re-subscribing after leaving is subscribing, not still unsubscribed.
                'unsubscribed_at' => null,
                'ip_address' => request()->ip(),
            ],
        );

        $this->reset('email');
        $this->done = 'Gata. Îți scriem când apare ceva bun.';
    }

    public function render()
    {
        return view('livewire.storefront.newsletter-form');
    }
}
