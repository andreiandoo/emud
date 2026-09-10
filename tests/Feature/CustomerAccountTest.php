<?php

namespace Tests\Feature;

use App\Livewire\Customer\ForgotPassword;
use App\Livewire\Customer\Orders as OrdersComponent;
use App\Livewire\Customer\Profile;
use App\Livewire\Customer\ResetPassword;
use App\Models\Order;
use App\Models\User;
use App\Storefront\AddressBook;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_only_sees_their_own_orders(): void
    {
        $mine = User::factory()->create();
        $this->order($mine, 'EM-MINE');
        $this->order(User::factory()->create(), 'EM-THEIRS');

        $this->actingAs($mine);

        Livewire::test(OrdersComponent::class)
            ->assertSee('EM-MINE')
            ->assertDontSee('EM-THEIRS');
    }

    /**
     * The order number is predictable enough to be useful operationally, which is exactly why
     * it must not be the thing that grants access to someone's delivery address.
     */
    public function test_an_order_is_reachable_only_through_its_checkout_token(): void
    {
        $order = $this->order(User::factory()->create(), 'EM-SECRET');

        $this->get(route('storefront.order', $order->checkout_token))->assertOk();
        $this->get('/comanda/'.$order->number)->assertNotFound();
    }

    public function test_a_reset_link_is_sent_for_a_known_address(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        Livewire::test(ForgotPassword::class)->set('email', $user->email)->call('sendLink');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    /**
     * Reporting that an address is unknown would turn the form into a way to discover who has
     * an account here, so the answer never varies.
     */
    public function test_an_unknown_address_gets_the_same_answer(): void
    {
        Notification::fake();

        Livewire::test(ForgotPassword::class)
            ->set('email', 'nobody@example.com')
            ->call('sendLink')
            ->assertSet('status', 'Dacă adresa există în sistem, ți-am trimis un link de resetare.');

        Notification::assertNothingSent();
    }

    public function test_a_valid_token_changes_the_password(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $token = app('auth.password.broker')->createToken($user);

        Livewire::test(ResetPassword::class, ['token' => $token])
            ->set('email', $user->email)
            ->set('password', 'o-parola-noua-sigura-1')
            ->set('password_confirmation', 'o-parola-noua-sigura-1')
            ->call('resetPassword');

        $this->assertTrue(Hash::check('o-parola-noua-sigura-1', $user->refresh()->password));
    }

    public function test_an_invalid_token_is_refused(): void
    {
        $user = User::factory()->create();

        Livewire::test(ResetPassword::class, ['token' => 'not-a-real-token'])
            ->set('email', $user->email)
            ->set('password', 'o-parola-noua-sigura-1')
            ->set('password_confirmation', 'o-parola-noua-sigura-1')
            ->call('resetPassword')
            ->assertHasErrors('email');
    }

    /**
     * Without this, an unattended session could be used to lock the real owner out.
     */
    public function test_changing_the_password_requires_the_current_one(): void
    {
        $user = User::factory()->create(['password' => 'parola-curenta-1']);
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->set('current_password', 'gresita')
            ->set('password', 'alta-parola-sigura-1')
            ->set('password_confirmation', 'alta-parola-sigura-1')
            ->call('changePassword')
            ->assertHasErrors('current_password');

        $this->assertTrue(Hash::check('parola-curenta-1', $user->refresh()->password));
    }

    public function test_the_correct_current_password_allows_the_change(): void
    {
        $user = User::factory()->create(['password' => 'parola-curenta-1']);
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->set('current_password', 'parola-curenta-1')
            ->set('password', 'alta-parola-sigura-1')
            ->set('password_confirmation', 'alta-parola-sigura-1')
            ->call('changePassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('alta-parola-sigura-1', $user->refresh()->password));
    }

    /**
     * A changed address has not been proven to belong to the customer, so any earlier
     * verification of the old one cannot carry over.
     */
    public function test_changing_the_email_clears_its_verification(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        Livewire::test(Profile::class)->set('email', 'nou@example.com')->call('saveProfile');

        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_withdrawing_marketing_consent_clears_the_timestamp(): void
    {
        $user = User::factory()->create(['marketing_consent_at' => now()]);
        $this->actingAs($user);

        Livewire::test(Profile::class)->set('marketing_consent', false)->call('saveProfile');

        $this->assertNull($user->refresh()->marketing_consent_at);
    }

    public function test_consent_keeps_its_original_timestamp_when_left_on(): void
    {
        $granted = now()->subMonth();
        $user = User::factory()->create(['marketing_consent_at' => $granted]);
        $this->actingAs($user);

        Livewire::test(Profile::class)->set('name', 'Andrei Nou')->call('saveProfile');

        $this->assertSame($granted->toDateString(), $user->refresh()->marketing_consent_at?->toDateString());
    }

    public function test_the_delivery_address_is_kept_on_the_account(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->set('shipping.first_name', 'Andrei')
            ->set('shipping.last_name', 'Popescu')
            ->set('shipping.line_1', 'Str. Exemplu 1')
            ->set('shipping.city', 'Cluj-Napoca')
            ->call('saveShipping')
            ->assertHasNoErrors();

        $this->assertSame('Cluj-Napoca', app(AddressBook::class)->shipping($user)?->city);
    }

    public function test_company_billing_details_are_kept_on_the_account(): void
    {
        $user = User::factory()->create(['name' => 'Andrei Popescu']);
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->set('billingType', 'company')
            ->set('billing.company', 'Off Road Garage SRL')
            ->set('billing.vat_number', 'ro 12345678')
            ->set('billing.trade_register_number', 'j40/1234/2020')
            ->set('billing.line_1', 'Str. Depozitului 4')
            ->set('billing.city', 'Brașov')
            ->call('saveBilling')
            ->assertHasNoErrors();

        $billing = app(AddressBook::class)->billing($user);

        $this->assertSame('Off Road Garage SRL', $billing?->company);
        $this->assertSame('RO12345678', $billing?->vat_number);
        $this->assertSame('J40/1234/2020', $billing?->trade_register_number);
        // With no delivery name saved yet, the invoice's contact comes from the account name.
        $this->assertSame('Andrei', $billing?->first_name);
    }

    public function test_a_company_is_not_saved_without_its_tax_code(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Profile::class)
            ->set('billingType', 'company')
            ->set('billing.company', 'Off Road Garage SRL')
            ->set('billing.line_1', 'Str. Depozitului 4')
            ->set('billing.city', 'Brașov')
            ->call('saveBilling')
            ->assertHasErrors('billing.vat_number');
    }

    /**
     * Most customers are invoiced where the parcel goes. That is one address, not a second copy
     * of it waiting to drift out of date.
     */
    public function test_invoicing_the_delivery_person_keeps_no_second_address(): void
    {
        $user = User::factory()->create();
        $book = app(AddressBook::class);
        $book->saveBilling($user, AddressBook::billingDetails('company', [
            'company' => 'Off Road Garage SRL',
            'vat_number' => '12345678',
            'line_1' => 'Str. Depozitului 4',
            'city' => 'Brașov',
        ], ['first_name' => 'Andrei', 'last_name' => 'Popescu']));
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->assertSet('billingType', 'company')
            ->set('billingType', 'person')
            ->set('billingSame', true)
            ->call('saveBilling')
            ->assertHasNoErrors();

        $this->assertNull($book->billing($user));
    }

    private function order(User $user, string $number): Order
    {
        return Order::create([
            'number' => $number,
            'user_id' => $user->id,
            'checkout_token' => (string) Str::uuid(),
            'customer_email' => $user->email,
            'currency' => 'RON',
            'subtotal' => 100,
            'shipping_total' => 20,
            'grand_total' => 120,
            'placed_at' => now(),
        ]);
    }
}
