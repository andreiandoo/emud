<?php

namespace Tests\Feature;

use App\Livewire\Admin\Settings\CompanySettings;
use App\Livewire\Admin\Settings\ContactSettings;
use App\Livewire\Admin\Settings\DocumentSettings;
use App\Livewire\Admin\Settings\GeneralSettings;
use App\Livewire\Admin\Settings\SettingsPage;
use App\Livewire\Admin\Settings\SocialSettings;
use App\Models\User;
use App\Settings\StoreSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class StoreSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_the_settings_page_opens_on_the_general_tab(): void
    {
        $this->get(route('admin.settings'))->assertOk()->assertSee('Titlul site-ului');
    }

    /**
     * The tab lives in the URL so a specific screen can be linked to, and reloading does not
     * throw the operator back to the first tab.
     */
    public function test_a_tab_can_be_linked_to(): void
    {
        $this->get(route('admin.settings', ['tab' => 'company']))->assertOk()->assertSee('CUI');
    }

    public function test_an_unknown_tab_falls_back_instead_of_erroring(): void
    {
        Livewire::withUrlParams(['tab' => 'inexistent'])
            ->test(SettingsPage::class)
            ->assertSet('tab', 'general');
    }

    public function test_the_old_commerce_settings_url_still_lands_somewhere(): void
    {
        $this->get('/admin/commerce-settings')->assertRedirect('/admin/settings?tab=commerce');
    }

    // --------------------------------------------------------------- general

    public function test_general_settings_are_stored_and_read_back(): void
    {
        Livewire::test(GeneralSettings::class)
            ->set('site_title', 'eMUD Off-road')
            ->set('site_tagline', 'Piese pentru teren')
            ->set('site_description', 'Magazin de piese 4x4.')
            ->set('default_currency', 'ron')
            ->set('timezone', 'Europe/Bucharest')
            ->call('save')
            ->assertHasNoErrors();

        $settings = app(StoreSettings::class);

        $this->assertSame('eMUD Off-road', $settings->string('site_title'));
        $this->assertSame('RON', $settings->string('default_currency'));
    }

    public function test_a_description_longer_than_a_search_snippet_is_refused(): void
    {
        Livewire::test(GeneralSettings::class)
            ->set('site_title', 'eMUD')
            ->set('site_description', str_repeat('a', 161))
            ->call('save')
            ->assertHasErrors('site_description');
    }

    public function test_a_logo_can_be_uploaded(): void
    {
        Storage::fake('public');

        Livewire::test(GeneralSettings::class)
            ->set('site_title', 'eMUD')
            ->set('logoUpload', UploadedFile::fake()->image('logo.png'))
            ->call('save')
            ->assertHasNoErrors();

        $path = app(StoreSettings::class)->string('logo_path');

        $this->assertNotSame('', $path);
        Storage::disk('public')->assertExists($path);
    }

    /**
     * The favicon is served on every page, and an SVG can carry script.
     */
    public function test_an_svg_favicon_is_refused(): void
    {
        Storage::fake('public');

        Livewire::test(GeneralSettings::class)
            ->set('site_title', 'eMUD')
            ->set('faviconUpload', UploadedFile::fake()->create('icon.svg', 4, 'image/svg+xml'))
            ->call('save')
            ->assertHasErrors('faviconUpload');
    }

    // --------------------------------------------------------------- company

    public function test_company_details_are_stored(): void
    {
        Livewire::test(CompanySettings::class)
            ->set('company_name', 'eMUD SRL')
            ->set('company_vat_id', 'ro12345678')
            ->set('company_registration_number', 'J12/345/2020')
            ->set('company_iban', 'ro49aaaa1b31007593840000')
            ->set('company_vat_payer', true)
            ->call('save')
            ->assertHasNoErrors();

        $settings = app(StoreSettings::class);

        // Normalised on the way in, so documents do not print two spellings of the same code.
        $this->assertSame('RO12345678', $settings->string('company_vat_id'));
        $this->assertSame('RO49AAAA1B31007593840000', $settings->string('company_iban'));
        $this->assertTrue($settings->bool('company_vat_payer'));
    }

    public function test_a_malformed_vat_id_is_refused(): void
    {
        Livewire::test(CompanySettings::class)
            ->set('company_name', 'eMUD SRL')
            ->set('company_vat_id', 'nu-e-cui')
            ->call('save')
            ->assertHasErrors('company_vat_id');
    }

    public function test_a_malformed_iban_is_refused(): void
    {
        Livewire::test(CompanySettings::class)
            ->set('company_name', 'eMUD SRL')
            ->set('company_iban', '1234')
            ->call('save')
            ->assertHasErrors('company_iban');
    }

    // --------------------------------------------------------------- contact

    public function test_contact_details_and_hours_are_stored(): void
    {
        Livewire::test(ContactSettings::class)
            ->set('contact_email', 'contact@emud.ro')
            ->set('contact_phone', '+40700000000')
            ->call('save')
            ->assertHasNoErrors();

        $hours = app(StoreSettings::class)->array('opening_hours');

        $this->assertCount(7, $hours);
        $this->assertSame('Luni', $hours[0]['day']);
    }

    /**
     * Storing hours alongside "closed" would leave two answers to the same question.
     */
    public function test_a_closed_day_keeps_no_hours(): void
    {
        Livewire::test(ContactSettings::class)
            ->set('contact_email', 'contact@emud.ro')
            ->set('contact_phone', '+40700000000')
            ->set('opening_hours.0.closed', true)
            ->call('save');

        $hours = app(StoreSettings::class)->array('opening_hours');

        $this->assertTrue($hours[0]['closed']);
        $this->assertNull($hours[0]['from']);
        $this->assertNull($hours[0]['to']);
    }

    // ------------------------------------------------------------- documents

    public function test_document_series_are_stored(): void
    {
        Livewire::test(DocumentSettings::class)
            ->set('series.invoice.prefix', 'EMUD')
            ->set('series.invoice.next', 250)
            ->call('save')
            ->assertHasNoErrors();

        $series = app(StoreSettings::class)->array('document_series');

        $this->assertSame('EMUD', $series['invoice']['prefix']);
        $this->assertSame(250, $series['invoice']['next']);
    }

    public function test_a_lowercase_prefix_is_refused(): void
    {
        Livewire::test(DocumentSettings::class)
            ->set('series.order.prefix', 'cmd nou')
            ->call('save')
            ->assertHasErrors('series.order.prefix');
    }

    // ---------------------------------------------------------------- social

    public function test_social_links_are_stored_and_blanks_dropped(): void
    {
        Livewire::test(SocialSettings::class)
            ->set('links.facebook', 'https://facebook.com/emud')
            ->set('links.tiktok', '')
            ->call('save')
            ->assertHasNoErrors();

        $links = app(StoreSettings::class)->array('social_links');

        $this->assertSame('https://facebook.com/emud', $links['facebook']);
        $this->assertArrayNotHasKey('tiktok', $links);
    }

    public function test_a_link_that_is_not_a_url_is_refused(): void
    {
        Livewire::test(SocialSettings::class)
            ->set('links.instagram', 'nu e link')
            ->call('save')
            ->assertHasErrors('links.instagram');
    }

    /**
     * A value the owner just changed still showing the old one would look broken.
     */
    public function test_saving_clears_the_cache_immediately(): void
    {
        $settings = app(StoreSettings::class);
        $settings->all();

        $settings->put('general', ['site_title' => 'Nou']);

        $this->assertSame('Nou', app(StoreSettings::class)->string('site_title'));
    }

    public function test_settings_are_closed_to_customers(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));

        $this->get(route('admin.settings'))->assertForbidden();
    }
}
