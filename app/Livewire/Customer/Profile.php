<?php

namespace App\Livewire\Customer;

use App\Storefront\AddressBook;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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

    /**
     * Where parcels go unless the customer says otherwise at checkout.
     *
     * @var array<string, string>
     */
    public array $shipping = [];

    /** Who the invoice is made out to: 'person' or 'company'. */
    public string $billingType = 'person';

    /** A person invoiced at the delivery address, which is most customers. */
    public bool $billingSame = true;

    /** @var array<string, string> */
    public array $billing = [];

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $status = '';

    public function mount(AddressBook $book): void
    {
        $user = auth()->user();

        $this->name = (string) $user->name;
        $this->email = (string) $user->email;
        $this->phone = (string) $user->phone;
        $this->marketing_consent = $user->marketing_consent_at !== null;

        $this->shipping = AddressBook::form($book->shipping($user));

        $billing = $book->billing($user);
        $this->billing = AddressBook::form($billing);
        $this->billingType = filled($billing?->company) ? 'company' : 'person';
        $this->billingSame = $billing === null;
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

    public function saveShipping(AddressBook $book): void
    {
        $this->validate(AddressBook::rules('shipping'), AddressBook::messages('shipping'));

        $book->saveShipping(auth()->user(), $this->shipping);

        $this->status = 'Adresa de livrare a fost salvată. O completăm automat la următoarea comandă.';
    }

    public function saveBilling(AddressBook $book): void
    {
        $kind = $this->invoiceKind();

        if ($kind === null) {
            $book->saveBilling(auth()->user(), null);
            $this->status = 'Facturăm pe numele și la adresa de livrare.';

            return;
        }

        $this->validate(AddressBook::rules('billing', $kind), AddressBook::messages('billing'));

        $book->saveBilling(auth()->user(), AddressBook::billingDetails($kind, $this->billing, $this->contact()));

        $this->status = $kind === 'company' ? 'Datele firmei au fost salvate.' : 'Datele de facturare au fost salvate.';
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

    /** Null when the invoice goes to the person at the delivery address. */
    private function invoiceKind(): ?string
    {
        if ($this->billingType === 'company') {
            return 'company';
        }

        return $this->billingSame ? null : 'person';
    }

    /**
     * The person an invoice made out to a firm names as its contact: the delivery name when
     * there is one, the account name otherwise.
     *
     * @return array{first_name: string, last_name: string}
     */
    private function contact(): array
    {
        if (filled($this->shipping['first_name'] ?? null)) {
            return [
                'first_name' => (string) $this->shipping['first_name'],
                'last_name' => (string) ($this->shipping['last_name'] ?? ''),
            ];
        }

        $name = trim((string) auth()->user()->name);

        return [
            'first_name' => Str::before($name, ' '),
            'last_name' => str_contains($name, ' ') ? Str::after($name, ' ') : '',
        ];
    }
}
