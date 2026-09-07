<?php

namespace Tests\Feature;

use App\Livewire\Admin\Content\PagesIndex;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_a_page_can_be_created_and_published(): void
    {
        Livewire::test(PagesIndex::class)
            ->set('title', 'Politica de retur')
            ->set('slug', 'retur')
            ->set('content', '<p>Textul politicii.</p>')
            ->set('status', 'published')
            ->call('save')
            ->assertHasNoErrors();

        $page = Page::query()->where('slug', 'retur')->sole();

        $this->assertSame('published', $page->status);
        $this->assertNotNull($page->published_at);
        $this->get('/retur')->assertOk()->assertSee('Textul politicii');
    }

    /**
     * published_at is what the storefront filters on, so a stale timestamp would leave an
     * unpublished page live.
     */
    public function test_unpublishing_takes_the_page_offline(): void
    {
        $page = Page::create([
            'title' => 'Retur',
            'slug' => 'retur',
            'content' => '<p>Text</p>',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        Livewire::test(PagesIndex::class)
            ->call('edit', $page->id)
            ->set('status', 'draft')
            ->call('save');

        $this->assertNull($page->refresh()->published_at);
        $this->get('/retur')->assertNotFound();
    }

    public function test_two_pages_cannot_share_a_slug(): void
    {
        Page::create(['title' => 'Contact', 'slug' => 'contact', 'status' => 'draft']);

        Livewire::test(PagesIndex::class)
            ->set('title', 'Alt contact')
            ->set('slug', 'contact')
            ->call('save')
            ->assertHasErrors('slug');
    }

    public function test_a_slug_must_be_url_safe(): void
    {
        Livewire::test(PagesIndex::class)
            ->set('title', 'Test')
            ->set('slug', 'Nu Este/Valid')
            ->call('save')
            ->assertHasErrors('slug');
    }

    public function test_the_title_suggests_a_slug_only_for_a_new_page(): void
    {
        Livewire::test(PagesIndex::class)
            ->set('title', 'Despre noi')
            ->assertSet('slug', 'despre-noi');
    }

    /**
     * Changing a live slug silently would break every link and bookmark pointing at the page.
     */
    public function test_editing_a_page_never_rewrites_its_slug_from_the_title(): void
    {
        $page = Page::create(['title' => 'Contact', 'slug' => 'contact', 'status' => 'draft']);

        Livewire::test(PagesIndex::class)
            ->call('edit', $page->id)
            ->set('title', 'Contact actualizat')
            ->assertSet('slug', 'contact');
    }

    public function test_script_in_page_content_is_not_served_to_visitors(): void
    {
        Livewire::test(PagesIndex::class)
            ->set('title', 'Despre')
            ->set('slug', 'despre')
            ->set('content', '<p>Salut</p><script>alert(1)</script>')
            ->set('status', 'published')
            ->call('save');

        $this->get('/despre')->assertOk()->assertSee('Salut')->assertDontSee('alert(1)', false);
    }

    public function test_the_page_editor_is_closed_to_customers(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));

        $this->get(route('admin.pages.index'))->assertForbidden();
    }
}
