<?php

namespace Tests\Feature;

use App\Enums\ServicePromotionTier;
use App\Livewire\Admin\ServiceShopsIndex;
use App\Livewire\Storefront\ServiceDirectory;
use App\Models\ServiceShop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_shops_are_listed_and_drafts_are_not(): void
    {
        $this->shop('Service Alfa', published: true);
        $this->shop('Service Ciornă', published: false);

        $this->get('/service-auto')->assertOk()->assertSee('Service Alfa')->assertDontSee('Service Ciornă');
    }

    public function test_a_draft_shop_page_is_not_reachable(): void
    {
        $shop = $this->shop('Service Ciornă', published: false);

        $this->get('/service-auto/'.$shop->slug)->assertNotFound();
    }

    public function test_shops_filter_by_county_city_and_speciality(): void
    {
        $this->shop('Cluj Off-road', county: 'Cluj', city: 'Cluj-Napoca', specialities: ['off-road']);
        $this->shop('Cluj Anvelope', county: 'Cluj', city: 'Turda', specialities: ['anvelope']);
        $this->shop('Brașov Service', county: 'Brașov', city: 'Brașov', specialities: ['off-road']);

        Livewire::test(ServiceDirectory::class)
            ->set('county', 'Cluj')
            ->assertSee('Cluj Off-road')
            ->assertSee('Cluj Anvelope')
            ->assertDontSee('Brașov Service')
            ->set('city', 'Cluj-Napoca')
            ->assertSee('Cluj Off-road')
            ->assertDontSee('Cluj Anvelope');

        Livewire::test(ServiceDirectory::class)
            ->set('speciality', 'anvelope')
            ->assertSee('Cluj Anvelope')
            ->assertDontSee('Cluj Off-road');
    }

    public function test_changing_county_clears_the_city(): void
    {
        $this->shop('Cluj Off-road', county: 'Cluj', city: 'Cluj-Napoca');

        Livewire::test(ServiceDirectory::class)
            ->set('city', 'Cluj-Napoca')
            ->set('county', 'Brașov')
            ->assertSet('city', '');
    }

    public function test_shops_that_fit_our_parts_can_be_isolated(): void
    {
        $this->shop('Montează piesele noastre', fitsOurParts: true);
        $this->shop('Nu montează');

        Livewire::test(ServiceDirectory::class)
            ->set('fitsOurParts', true)
            ->assertSee('Montează piesele noastre')
            ->assertDontSee('Nu montează');
    }

    // ------------------------------------------------------------- promotion

    public function test_paid_placements_sort_above_free_ones(): void
    {
        $this->shop('Zeta gratuit');
        $this->shop('Alfa promovat', tier: ServicePromotionTier::Premium, promotedUntil: now()->addMonth());

        $content = $this->get('/service-auto')->getContent();

        $this->assertLessThan(strpos($content, 'Zeta gratuit'), strpos($content, 'Alfa promovat'));
    }

    /**
     * Ranking that money influenced has to be visible to the reader, not merely reflected in
     * the order.
     */
    public function test_a_paid_placement_is_labelled(): void
    {
        $this->shop('Promovat', tier: ServicePromotionTier::Featured, promotedUntil: now()->addMonth());

        $this->get('/service-auto')->assertSee('Promovat');
        $this->get('/service-auto/promovat')->assertSee('listare plătită');
    }

    public function test_a_free_listing_carries_no_paid_label(): void
    {
        $this->shop('Gratuit');

        $this->get('/service-auto/gratuit')->assertDontSee('listare plătită');
    }

    /**
     * Checked at read time rather than by a nightly job, so an expired placement can never keep
     * its position because a job failed to run.
     */
    public function test_an_expired_promotion_loses_its_label(): void
    {
        $shop = $this->shop('Expirat', tier: ServicePromotionTier::Premium, promotedUntil: now()->subDay());

        $this->assertFalse($shop->isPromoted());
        $this->assertSame(ServicePromotionTier::None, $shop->effectiveTier());
    }

    public function test_an_expired_promotion_loses_its_position(): void
    {
        $this->shop('Alfa gratuit');
        $this->shop('Zeta expirat', tier: ServicePromotionTier::Premium, promotedUntil: now()->subDay());

        $content = $this->get('/service-auto')->getContent();

        $this->assertLessThan(strpos($content, 'Zeta expirat'), strpos($content, 'Alfa gratuit'));
    }

    // ----------------------------------------------------------------- admin

    public function test_an_admin_can_publish_a_shop(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ServiceShopsIndex::class)
            ->set('name', 'Service Nou')
            ->set('slug', 'service-nou')
            ->set('county', 'Cluj')
            ->set('city', 'Cluj-Napoca')
            ->set('specialities', 'off-road, suspensie')
            ->set('shopStatus', 'published')
            ->call('save')
            ->assertHasNoErrors();

        $shop = ServiceShop::query()->where('slug', 'service-nou')->sole();

        $this->assertSame(['off-road', 'suspensie'], $shop->specialityList());
        $this->get('/service-auto/service-nou')->assertOk();
    }

    /**
     * A paid tier with no end date would run forever without anyone revisiting it.
     */
    public function test_a_paid_tier_requires_an_expiry_date(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ServiceShopsIndex::class)
            ->set('name', 'Service Plătit')
            ->set('slug', 'service-platit')
            ->set('county', 'Cluj')
            ->set('city', 'Cluj-Napoca')
            ->set('promotion_tier', ServicePromotionTier::Featured->value)
            ->call('save')
            ->assertHasErrors('promoted_until');
    }

    public function test_dropping_the_tier_clears_the_expiry(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $shop = $this->shop('Cu promovare', tier: ServicePromotionTier::Featured, promotedUntil: now()->addMonth());

        Livewire::test(ServiceShopsIndex::class)
            ->call('edit', $shop->id)
            ->set('promotion_tier', 'none')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($shop->refresh()->promoted_until);
    }

    public function test_the_directory_admin_is_closed_to_customers(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));

        $this->get(route('admin.service-shops.index'))->assertForbidden();
    }

    /** @param list<string> $specialities */
    private function shop(
        string $name,
        bool $published = true,
        string $county = 'Cluj',
        string $city = 'Cluj-Napoca',
        array $specialities = [],
        bool $fitsOurParts = false,
        ServicePromotionTier $tier = ServicePromotionTier::None,
        mixed $promotedUntil = null,
    ): ServiceShop {
        return ServiceShop::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'county' => $county,
            'city' => $city,
            'specialities' => $specialities,
            'fits_parts_bought_here' => $fitsOurParts,
            'status' => $published ? 'published' : 'draft',
            'promotion_tier' => $tier->value,
            'promoted_until' => $promotedUntil?->toDateString(),
        ]);
    }
}
