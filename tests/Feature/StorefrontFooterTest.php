<?php

namespace Tests\Feature;

use App\Livewire\Admin\Settings\FooterSettings;
use App\Livewire\Storefront\NewsletterForm;
use App\Models\Category;
use App\Models\NewsletterSubscriber;
use App\Models\Page;
use App\Models\User;
use App\Settings\StoreSettings;
use App\Storefront\CollectionShowcase;
use App\Storefront\FooterMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StorefrontFooterTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_columns_are_built_from_what_the_shop_actually_has(): void
    {
        Category::create([
            'name' => 'Suspensie și direcție',
            'slug' => 'suspensie-directie',
            'full_path' => 'suspensie-directie',
            'is_active' => true,
            'is_visible_in_menu' => true,
        ]);

        Page::create([
            'title' => 'Politica de retur',
            'slug' => 'politica-de-retur',
            'content' => 'Text.',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'show_in_footer' => true,
        ]);

        FooterMenu::forget();
        CollectionShowcase::forget();

        $this->get('/')
            ->assertOk()
            ->assertSee('Suspensie și direcție')
            ->assertSee('Politica de retur')
            ->assertSee('Găsește un service');
    }

    public function test_the_payment_methods_shown_are_the_ones_that_were_ticked(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(FooterSettings::class)
            ->set('footer_payment_methods', ['visa', 'ramburs', 'inventata'])
            ->set('footer_about', 'Piese 4x4 livrate în toată țara.')
            ->call('save')
            ->assertHasNoErrors();

        // An unknown key is dropped rather than stored, because the footer would render it as a
        // blank pill.
        $this->assertSame(['visa', 'ramburs'], app(StoreSettings::class)->array('footer_payment_methods'));

        $this->get('/')
            ->assertOk()
            ->assertSee('Ramburs la curier')
            ->assertSee('Piese 4x4 livrate în toată țara.');
    }

    public function test_the_sign_up_stores_the_address_lower_cased(): void
    {
        Livewire::test(NewsletterForm::class)
            ->set('email', 'Ion@Example.COM')
            ->call('subscribe')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'ion@example.com']);
    }

    /** Signing up twice must be a no-op, not a second row and not a different answer. */
    public function test_signing_up_twice_says_the_same_thing(): void
    {
        $first = Livewire::test(NewsletterForm::class)->set('email', 'ion@example.com')->call('subscribe');
        $second = Livewire::test(NewsletterForm::class)->set('email', 'ion@example.com')->call('subscribe');

        $this->assertSame($first->get('done'), $second->get('done'));
        $this->assertSame(1, NewsletterSubscriber::query()->count());
    }

    public function test_someone_who_left_and_comes_back_is_subscribed_again(): void
    {
        NewsletterSubscriber::create([
            'email' => 'ion@example.com',
            'unsubscribed_at' => now()->subMonth(),
        ]);

        Livewire::test(NewsletterForm::class)->set('email', 'ion@example.com')->call('subscribe');

        $this->assertNull(NewsletterSubscriber::query()->firstOrFail()->unsubscribed_at);
    }

    /** The honeypot answers exactly as a real submission does; nothing is stored. */
    public function test_a_filled_honeypot_is_answered_normally_and_stored_nowhere(): void
    {
        Livewire::test(NewsletterForm::class)
            ->set('email', 'bot@example.com')
            ->set('website', 'https://spam.example')
            ->call('subscribe')
            ->assertSet('done', 'Gata. Îți scriem când apare ceva bun.');

        $this->assertSame(0, NewsletterSubscriber::query()->count());
    }
}
