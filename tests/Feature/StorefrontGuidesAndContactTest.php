<?php

namespace Tests\Feature;

use App\Livewire\Storefront\Contact;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class StorefrontGuidesAndContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_guides_are_listed(): void
    {
        $this->article('Cum alegi anvelopele', 'anvelope', published: true);

        $this->get('/ghiduri')->assertOk()->assertSee('Cum alegi anvelopele');
    }

    public function test_a_draft_guide_is_neither_listed_nor_readable(): void
    {
        $this->article('Ciornă internă', 'ciorna', published: false);

        $this->get('/ghiduri')->assertOk()->assertDontSee('Ciornă internă');
        $this->get('/ghiduri/ciorna')->assertNotFound();
    }

    public function test_a_guide_scheduled_for_later_is_not_readable_yet(): void
    {
        Article::create([
            'title' => 'Ghid viitor',
            'slug' => 'ghid-viitor',
            'status' => 'published',
            'published_at' => now()->addWeek(),
        ]);

        $this->get('/ghiduri/ghid-viitor')->assertNotFound();
    }

    public function test_guides_can_be_filtered_by_category(): void
    {
        $category = ArticleCategory::create(['name' => 'Suspensie', 'slug' => 'suspensie', 'is_active' => true]);
        $this->article('Despre suspensie', 'despre-suspensie', published: true, category: $category);
        $this->article('Despre anvelope', 'despre-anvelope', published: true);

        $this->get('/ghiduri?category=suspensie')
            ->assertSee('Despre suspensie')
            ->assertDontSee('Despre anvelope');
    }

    public function test_script_in_a_guide_is_not_served(): void
    {
        $article = $this->article('Ghid montaj', 'ghid-montaj', published: true);
        $article->update(['content' => '<p>Pas 1</p><script>alert(1)</script>']);

        $this->get('/ghiduri/ghid-montaj')->assertSee('Pas 1')->assertDontSee('alert(1)', false);
    }

    // ---------------------------------------------------------------- contact

    public function test_a_message_is_stored(): void
    {
        Livewire::test(Contact::class)
            ->set('name', 'Andrei')
            ->set('email', 'andrei@example.com')
            ->set('message', 'Se potrivește bara asta pe Duster 2020?')
            ->call('send')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('contact_messages', ['email' => 'andrei@example.com', 'status' => 'new']);
    }

    /**
     * A bot fills every input it finds. Answering it exactly as a real submission would be
     * avoids teaching whoever wrote it what to change.
     */
    public function test_a_filled_honeypot_is_silently_discarded(): void
    {
        Livewire::test(Contact::class)
            ->set('name', 'Bot')
            ->set('email', 'bot@example.com')
            ->set('message', 'Cumpără ceasuri ieftine acum!')
            ->set('website', 'http://spam.example')
            ->call('send')
            ->assertHasNoErrors()
            ->assertSet('sent', 'Mesajul a fost trimis. Îți răspundem cât putem de repede.');

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_a_short_message_is_refused(): void
    {
        Livewire::test(Contact::class)
            ->set('name', 'Andrei')
            ->set('email', 'andrei@example.com')
            ->set('message', 'salut')
            ->call('send')
            ->assertHasErrors('message');
    }

    public function test_sending_is_rate_limited(): void
    {
        RateLimiter::clear('contact:127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            Livewire::test(Contact::class)
                ->set('name', 'Andrei')
                ->set('email', 'andrei@example.com')
                ->set('message', 'Am o întrebare despre comanda mea.')
                ->call('send');
        }

        Livewire::test(Contact::class)
            ->set('name', 'Andrei')
            ->set('email', 'andrei@example.com')
            ->set('message', 'Încă o întrebare despre comanda mea.')
            ->call('send')
            ->assertHasErrors('message');

        $this->assertDatabaseCount('contact_messages', 5);
    }

    public function test_a_signed_in_customer_gets_their_details_prefilled(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Andrei Popescu', 'email' => 'andrei@example.com']));

        Livewire::test(Contact::class)
            ->assertSet('name', 'Andrei Popescu')
            ->assertSet('email', 'andrei@example.com');
    }

    private function article(string $title, string $slug, bool $published, ?ArticleCategory $category = null): Article
    {
        return Article::create([
            'title' => $title,
            'slug' => $slug,
            'excerpt' => 'Rezumat pentru '.$title,
            'content' => '<p>Conținut</p>',
            'article_category_id' => $category?->id,
            'status' => $published ? 'published' : 'draft',
            'published_at' => $published ? now()->subDay() : null,
        ]);
    }
}
