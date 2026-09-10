<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Livewire\Admin\Content\ReviewEditor;
use App\Livewire\Admin\Content\ReviewsIndex;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReviewsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_an_operator_can_write_a_review_with_everything_it_carries(): void
    {
        $product = $this->product();

        Livewire::test(ReviewEditor::class)
            ->set('reviewerName', 'Andrei M.')
            ->set('reviewerLocation', 'Brașov')
            ->set('reviewerInstagram', '@andrei.4x4')
            ->set('reviewerFacebook', 'https://www.facebook.com/andrei.4x4')
            ->set('title', 'Merge impecabil')
            ->set('body', 'Kitul s-a montat fără surprize și mașina stă perfect pe drum.')
            ->set('rating', '5')
            ->set('videoUrl', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->set('vehicleLabel', 'Suzuki Jimny III')
            ->call('selectProduct', $product->id)
            ->set('reviewStatus', 'published')
            ->call('save')
            ->assertHasNoErrors();

        $review = Review::query()->firstOrFail();

        $this->assertSame('Andrei M.', $review->reviewer_name);
        $this->assertSame($product->id, $review->product_id);
        $this->assertSame(5, $review->rating);
        $this->assertSame('Suzuki Jimny III', $review->vehicleLabel());
        $this->assertSame('https://www.instagram.com/andrei.4x4', $review->instagramUrl());
        // A pasted profile link is left exactly as it was pasted.
        $this->assertSame('https://www.facebook.com/andrei.4x4', $review->facebookUrl());
    }

    /**
     * The storefront filters on the flag *and* the date. A review published with no date would
     * look live in the back office and never appear on the shop.
     */
    public function test_publishing_without_a_date_stamps_one(): void
    {
        Livewire::test(ReviewEditor::class)
            ->set('reviewerName', 'Ana')
            ->set('body', 'Comandă livrată rapid și piesa se potrivește.')
            ->set('reviewStatus', 'published')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNotNull(Review::query()->firstOrFail()->published_at);
    }

    public function test_the_list_can_publish_and_retract(): void
    {
        $review = Review::create([
            'reviewer_name' => 'Ana',
            'body' => 'Text.',
            'status' => 'draft',
        ]);

        Livewire::test(ReviewsIndex::class)->call('togglePublished', $review->id);
        $this->assertSame('published', $review->fresh()->status);
        $this->assertNotNull($review->fresh()->published_at);

        Livewire::test(ReviewsIndex::class)->call('togglePublished', $review->id);
        $this->assertSame('draft', $review->fresh()->status);
    }

    public function test_a_scheduled_review_stays_off_the_shop_until_its_time(): void
    {
        $product = $this->product();

        Review::create([
            'product_id' => $product->id,
            'reviewer_name' => 'Din viitor',
            'body' => 'Text programat pentru mai târziu.',
            'status' => 'published',
            'published_at' => now()->addWeek(),
        ]);

        $this->assertCount(0, Review::query()->published()->get());
    }

    public function test_a_published_review_appears_on_the_product_page(): void
    {
        $product = $this->product();

        Review::create([
            'product_id' => $product->id,
            'reviewer_name' => 'Andrei M.',
            'body' => 'Se potrivește perfect, recomand.',
            'rating' => 5,
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $this->get(route('storefront.product', $product))
            ->assertOk()
            ->assertSee('Andrei M.')
            ->assertSee('Se potrivește perfect, recomand.')
            // The rules on review transparency ask a trader to say where its reviews come from.
            ->assertSee('publicate de noi')
            ->assertSee('AggregateRating');
    }

    /** Marking up a rating the page cannot show would be exactly the misleading case. */
    public function test_no_aggregate_rating_is_emitted_when_no_review_carries_a_score(): void
    {
        $product = $this->product();

        Review::create([
            'product_id' => $product->id,
            'reviewer_name' => 'Andrei M.',
            'body' => 'Fără notă, doar text.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $this->get(route('storefront.product', $product))
            ->assertOk()
            ->assertDontSee('AggregateRating');
    }

    private function product(): Product
    {
        return Product::create([
            'name' => 'Kit de înălțare',
            'slug' => 'kit-de-inaltare',
            'status' => ProductStatus::Active,
            'published_at' => now(),
        ]);
    }
}
