<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Support\HtmlSanitizer;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontPagesAndSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_published_page_is_reachable_at_its_slug(): void
    {
        $this->page('livrare', 'Livrare', published: true);

        $this->get('/livrare')->assertOk()->assertSee('Livrare');
    }

    /**
     * Drafts must not be reachable by guessing the slug, or unfinished legal wording becomes
     * public before anyone approved it.
     */
    public function test_a_draft_page_is_not_reachable(): void
    {
        $this->page('retur', 'Politica de retur', published: false);

        $this->get('/retur')->assertNotFound();
    }

    public function test_a_page_scheduled_for_later_is_not_reachable_yet(): void
    {
        Page::create([
            'title' => 'Termeni noi',
            'slug' => 'termeni-noi',
            'status' => 'published',
            'published_at' => now()->addWeek(),
        ]);

        $this->get('/termeni-noi')->assertNotFound();
    }

    public function test_the_page_route_never_shadows_a_real_route(): void
    {
        // A page could be created with any slug; the specific routes must still win. Kept out
        // of the footer so the assertion reads the served page rather than a navigation link.
        $this->page('cos', 'Coș fals', published: true, inFooter: false);

        $this->get('/cos')->assertOk()->assertSee('Coșul este gol')->assertDontSee('Coș fals');
    }

    public function test_published_pages_appear_in_the_footer(): void
    {
        $this->page('contact', 'Contact', published: true);
        $this->page('ascunsa', 'Ascunsă', published: true, inFooter: false);

        $this->get('/')->assertSee('Contact')->assertDontSee('Ascunsă');
    }

    public function test_the_seeder_creates_the_legal_pages_as_unpublished_drafts(): void
    {
        $this->seed(PageSeeder::class);

        $this->assertDatabaseHas('pages', ['slug' => 'termeni-si-conditii', 'status' => 'draft']);
        $this->assertSame(0, Page::query()->published()->count());
    }

    public function test_reseeding_never_reverts_published_wording(): void
    {
        $this->seed(PageSeeder::class);
        Page::query()->where('slug', 'retur')->update(['content' => 'Text aprobat', 'status' => 'published']);

        $this->seed(PageSeeder::class);

        $page = Page::query()->where('slug', 'retur')->sole();
        $this->assertSame('Text aprobat', $page->content);
        $this->assertSame('published', $page->status);
    }

    // ------------------------------------------------------------------ SEO

    public function test_the_home_page_declares_a_canonical_and_a_description(): void
    {
        $this->get('/')
            ->assertSee('<link rel="canonical"', false)
            ->assertSee('<meta name="description"', false);
    }

    public function test_the_basket_is_kept_out_of_the_index(): void
    {
        $this->get('/cos')->assertSee('content="noindex,nofollow"', false);
    }

    public function test_the_sitemap_lists_only_reachable_pages(): void
    {
        Category::create(['name' => 'Suspensie', 'slug' => 'suspensie', 'full_path' => 'suspensie', 'is_active' => true]);
        $live = Product::create(['name' => 'Arc', 'slug' => 'arc', 'status' => 'active', 'published_at' => now()]);
        Product::create(['name' => 'Ciornă', 'slug' => 'ciorna-produs', 'status' => 'draft']);
        $this->page('contact', 'Contact', published: true);
        $this->page('nepublicata', 'Nepublicată', published: false);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertSee(route('storefront.product', $live), false);
        $response->assertDontSee('ciorna-produs', false);
        $response->assertSee('/contact', false);
        $response->assertDontSee('/nepublicata', false);
    }

    public function test_robots_keeps_crawlers_out_of_private_areas(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /admin')
            ->assertSee('Disallow: /cont')
            ->assertSee('Sitemap: ');
    }

    // ------------------------------------------------------------ sanitizer

    public function test_script_is_removed_from_editorial_html(): void
    {
        $clean = app(HtmlSanitizer::class)->clean('<p>Text</p><script>alert(1)</script>');

        $this->assertStringContainsString('<p>Text</p>', $clean);
        $this->assertStringNotContainsString('alert', $clean);
    }

    public function test_event_handlers_are_stripped_but_the_text_survives(): void
    {
        $clean = app(HtmlSanitizer::class)->clean('<p onclick="steal()">Important</p>');

        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringContainsString('Important', $clean);
    }

    public function test_javascript_urls_are_refused_while_normal_links_stay(): void
    {
        $sanitizer = app(HtmlSanitizer::class);

        $this->assertStringNotContainsString('javascript', $sanitizer->clean('<a href="javascript:alert(1)">clic</a>'));
        $this->assertStringContainsString('https://exemplu.ro', $sanitizer->clean('<a href="https://exemplu.ro">clic</a>'));
        $this->assertStringContainsString('/categorie/suspensie', $sanitizer->clean('<a href="/categorie/suspensie">clic</a>'));
    }

    /**
     * Removing a disallowed tag must not take the sentence inside it with it.
     */
    public function test_a_disallowed_tag_is_unwrapped_rather_than_deleted(): void
    {
        $clean = app(HtmlSanitizer::class)->clean('<marquee>Text important</marquee>');

        $this->assertStringNotContainsString('marquee', $clean);
        $this->assertStringContainsString('Text important', $clean);
    }

    public function test_a_link_opening_a_new_tab_gets_noopener(): void
    {
        $clean = app(HtmlSanitizer::class)->clean('<a href="https://exemplu.ro" target="_blank">clic</a>');

        $this->assertStringContainsString('noopener', $clean);
    }

    public function test_diacritics_survive_sanitising(): void
    {
        $this->assertStringContainsString('Șerpuit înălțime', app(HtmlSanitizer::class)->clean('<p>Șerpuit înălțime</p>'));
    }

    private function page(string $slug, string $title, bool $published, bool $inFooter = true): Page
    {
        return Page::create([
            'title' => $title,
            'slug' => $slug,
            'content' => '<p>'.$title.'</p>',
            'status' => $published ? 'published' : 'draft',
            'published_at' => $published ? now()->subDay() : null,
            'show_in_footer' => $inFooter,
            'position' => 10,
        ]);
    }
}
