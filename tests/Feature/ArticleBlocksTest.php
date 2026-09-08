<?php

namespace Tests\Feature;

use App\Content\PartsCarousel;
use App\Content\VideoEmbed;
use App\Enums\ArticleBlockType;
use App\Livewire\Admin\Content\ArticleBlockEditor;
use App\Models\Article;
use App\Models\ArticleBlock;
use App\Models\ArticleVehicle;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductFitment;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ArticleBlocksTest extends TestCase
{
    use RefreshDatabase;

    private Article $article;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->article = Article::create([
            'title' => 'Ghid montaj',
            'slug' => 'ghid-montaj',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
    }

    // ------------------------------------------------------------ video embeds

    /**
     * The embed is always rebuilt from the extracted id. Passing the author's URL through would
     * let anything at all be framed inside a page customers trust.
     */
    public function test_youtube_links_resolve_to_a_cookieless_embed(): void
    {
        foreach ([
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtu.be/dQw4w9WgXcQ',
            'https://www.youtube.com/shorts/dQw4w9WgXcQ',
        ] as $url) {
            $embed = VideoEmbed::fromUrl($url);

            $this->assertNotNull($embed, $url);
            $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $embed->embedUrl);
        }
    }

    public function test_vimeo_links_resolve(): void
    {
        $this->assertSame(
            'https://player.vimeo.com/video/123456789',
            VideoEmbed::fromUrl('https://vimeo.com/123456789')?->embedUrl
        );
    }

    public function test_an_arbitrary_url_is_refused(): void
    {
        $this->assertNull(VideoEmbed::fromUrl('https://exemplu.ro/pagina'));
        $this->assertNull(VideoEmbed::fromUrl('javascript:alert(1)'));
        $this->assertNull(VideoEmbed::fromUrl(null));
    }

    public function test_an_unrecognised_video_renders_a_notice_not_a_frame(): void
    {
        $this->block(ArticleBlockType::Video, ['url' => 'https://exemplu.ro/video']);

        $this->get('/ghiduri/ghid-montaj')
            ->assertSee('Sunt acceptate doar linkuri YouTube și Vimeo')
            ->assertDontSee('<iframe', false);
    }

    // ---------------------------------------------------------------- rendering

    public function test_blocks_render_in_order(): void
    {
        $this->block(ArticleBlockType::RichText, ['html' => '<p>Primul paragraf</p>'], position: 1);
        $this->block(ArticleBlockType::Callout, ['title' => 'Atenție', 'html' => '<p>Ai grijă</p>'], position: 0);

        $response = $this->get('/ghiduri/ghid-montaj')->assertOk();

        $this->assertLessThan(
            strpos($response->getContent(), 'Primul paragraf'),
            strpos($response->getContent(), 'Atenție'),
        );
    }

    public function test_block_html_is_sanitised(): void
    {
        $this->block(ArticleBlockType::RichText, ['html' => '<p>Text</p><script>alert(1)</script>']);

        $this->get('/ghiduri/ghid-montaj')->assertSee('Text')->assertDontSee('alert(1)', false);
    }

    /**
     * Articles written before the block editor must not vanish from the site.
     */
    public function test_a_legacy_html_article_still_renders(): void
    {
        $this->article->update(['content' => '<p>Text vechi</p>']);

        $this->get('/ghiduri/ghid-montaj')->assertSee('Text vechi');
    }

    public function test_the_legacy_body_is_dropped_once_blocks_exist(): void
    {
        $this->article->update(['content' => '<p>Text vechi</p>']);
        $this->block(ArticleBlockType::RichText, ['html' => '<p>Text nou</p>']);

        $this->get('/ghiduri/ghid-montaj')->assertSee('Text nou')->assertDontSee('Text vechi');
    }

    public function test_steps_are_numbered(): void
    {
        $this->block(ArticleBlockType::Steps, ['steps' => [
            ['title' => 'Ridică mașina', 'text' => 'Pe cric'],
            ['title' => 'Scoate roata', 'text' => 'Cu cheia'],
        ]]);

        $this->get('/ghiduri/ghid-montaj')->assertSee('Ridică mașina')->assertSee('Scoate roata');
    }

    // ---------------------------------------------------------- parts carousel

    public function test_a_carousel_resolves_from_the_articles_vehicles(): void
    {
        [$make, $model] = $this->vehicle();
        ArticleVehicle::create(['article_id' => $this->article->id, 'make_id' => $make->id, 'model_id' => $model->id]);

        $fitting = $this->product('Bară Duster');
        ProductFitment::create(['product_id' => $fitting->id, 'make_id' => $make->id, 'model_id' => $model->id]);
        $this->product('Piesă fără legătură');

        $block = $this->block(ArticleBlockType::PartsCarousel, ['source' => 'vehicle', 'limit' => 8]);
        $products = app(PartsCarousel::class)->resolve($block, $this->article->fresh());

        $this->assertTrue($products->contains('name', 'Bară Duster'));
        $this->assertFalse($products->contains('name', 'Piesă fără legătură'));
    }

    public function test_a_carousel_resolves_a_category_subtree(): void
    {
        $parent = Category::create(['name' => 'Suspensie', 'slug' => 'suspensie', 'full_path' => 'suspensie', 'is_active' => true]);
        $child = Category::create(['name' => 'Arcuri', 'slug' => 'arcuri', 'full_path' => 'suspensie/arcuri', 'parent_id' => $parent->id, 'depth' => 1, 'is_active' => true]);

        $product = $this->product('Arc spate');
        $product->categories()->attach($child);

        $block = $this->block(ArticleBlockType::PartsCarousel, ['source' => 'category', 'category_id' => $parent->id]);

        $this->assertTrue(app(PartsCarousel::class)->resolve($block, $this->article)->contains('name', 'Arc spate'));
    }

    public function test_a_carousel_resolves_an_explicit_product_list(): void
    {
        $product = $this->product('Aleasă manual');
        $this->product('Nealeasă');

        $block = $this->block(ArticleBlockType::PartsCarousel, ['source' => 'products', 'product_ids' => [$product->id]]);
        $products = app(PartsCarousel::class)->resolve($block, $this->article);

        $this->assertSame(['Aleasă manual'], $products->pluck('name')->all());
    }

    public function test_an_unpublished_product_never_appears_in_a_carousel(): void
    {
        $draft = Product::create(['name' => 'Ciornă', 'slug' => 'ciorna-'.Str::random(5), 'status' => 'draft']);

        $block = $this->block(ArticleBlockType::PartsCarousel, ['source' => 'products', 'product_ids' => [$draft->id]]);

        $this->assertTrue(app(PartsCarousel::class)->resolve($block, $this->article)->isEmpty());
    }

    /**
     * Resolved at render time rather than frozen, so a part leaving the catalogue leaves the
     * carousel too instead of the guide advertising something that cannot be bought.
     */
    public function test_a_carousel_follows_the_catalogue(): void
    {
        $product = $this->product('Se retrage');
        $block = $this->block(ArticleBlockType::PartsCarousel, ['source' => 'products', 'product_ids' => [$product->id]]);

        $this->assertCount(1, app(PartsCarousel::class)->resolve($block, $this->article));

        $product->update(['status' => 'archived']);

        $this->assertCount(0, app(PartsCarousel::class)->resolve($block->fresh(), $this->article));
    }

    public function test_an_empty_carousel_renders_nothing_rather_than_an_empty_box(): void
    {
        $this->block(ArticleBlockType::PartsCarousel, ['source' => 'products', 'product_ids' => [], 'title' => 'Piese recomandate']);

        $this->get('/ghiduri/ghid-montaj')->assertDontSee('Piese recomandate');
    }

    // ------------------------------------------------------------------ editor

    public function test_a_block_can_be_added_and_saved(): void
    {
        Livewire::test(ArticleBlockEditor::class, ['article' => $this->article])
            ->set('newBlockType', ArticleBlockType::RichText->value)
            ->call('addBlock')
            ->set('blocks.0.data.html', '<p>Scris în editor</p>')
            ->call('saveBlocks');

        $this->assertSame('<p>Scris în editor</p>', ArticleBlock::query()->sole()->get('html'));
    }

    /**
     * Each kind keeps only the keys it understands, so a renamed field cannot leave dead keys
     * in the payload forever.
     */
    public function test_unknown_keys_are_discarded_on_save(): void
    {
        $block = $this->block(ArticleBlockType::Image, ['url' => 'https://exemplu.ro/a.jpg']);

        Livewire::test(ArticleBlockEditor::class, ['article' => $this->article])
            ->set('blocks.0.data.rubbish', 'x')
            ->call('saveBlocks');

        $this->assertArrayNotHasKey('rubbish', $block->refresh()->data);
        $this->assertSame('https://exemplu.ro/a.jpg', $block->get('url'));
    }

    public function test_blocks_can_be_reordered(): void
    {
        $first = $this->block(ArticleBlockType::RichText, ['html' => '<p>A</p>'], position: 0);
        $second = $this->block(ArticleBlockType::RichText, ['html' => '<p>B</p>'], position: 1);

        Livewire::test(ArticleBlockEditor::class, ['article' => $this->article])
            ->call('moveBlock', $second->id, -1);

        $this->assertSame([$second->id, $first->id], $this->article->blocks()->pluck('id')->all());
    }

    public function test_a_block_from_another_article_cannot_be_deleted(): void
    {
        $other = Article::create(['title' => 'Alt articol', 'slug' => 'alt-articol', 'status' => 'draft']);
        $foreign = ArticleBlock::create(['article_id' => $other->id, 'type' => 'rich_text', 'data' => []]);

        try {
            Livewire::test(ArticleBlockEditor::class, ['article' => $this->article])
                ->call('removeBlock', $foreign->id);
            $this->fail('A block belonging to another article must not be reachable.');
        } catch (ModelNotFoundException) {
            // scoped to this article
        }

        $this->assertDatabaseHas('article_blocks', ['id' => $foreign->id]);
    }

    public function test_a_vehicle_can_be_linked_and_unlinked(): void
    {
        [$make, $model] = $this->vehicle();

        $component = Livewire::test(ArticleBlockEditor::class, ['article' => $this->article])
            ->set('vehicleMakeId', $make->id)
            ->set('vehicleModelId', $model->id)
            ->call('addVehicle')
            ->assertHasNoErrors();

        $link = ArticleVehicle::query()->sole();
        $this->assertSame($model->id, $link->model_id);

        $component->call('removeVehicle', $link->id);
        $this->assertDatabaseCount('article_vehicles', 0);
    }

    public function test_a_model_from_another_make_is_refused(): void
    {
        [$make] = $this->vehicle();
        [, $otherModel] = $this->vehicle();

        Livewire::test(ArticleBlockEditor::class, ['article' => $this->article])
            ->set('vehicleMakeId', $make->id)
            ->set('vehicleModelId', $otherModel->id)
            ->call('addVehicle')
            ->assertHasErrors('vehicleModelId');
    }

    public function test_linking_the_same_vehicle_twice_keeps_one_link(): void
    {
        [$make, $model] = $this->vehicle();

        $component = Livewire::test(ArticleBlockEditor::class, ['article' => $this->article]);

        foreach ([1, 2] as $ignored) {
            $component->set('vehicleMakeId', $make->id)->set('vehicleModelId', $model->id)->call('addVehicle');
        }

        $this->assertSame(1, ArticleVehicle::query()->count());
    }

    public function test_the_editor_is_closed_to_customers(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));

        $this->get(route('admin.articles.blocks', $this->article))->assertForbidden();
    }

    /** @param array<string, mixed> $data */
    private function block(ArticleBlockType $type, array $data, int $position = 0): ArticleBlock
    {
        return ArticleBlock::create([
            'article_id' => $this->article->id,
            'type' => $type->value,
            'position' => $position,
            'data' => $data,
        ]);
    }

    private function product(string $name): Product
    {
        $product = Product::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(5),
            'status' => 'active',
            'published_at' => now(),
        ]);

        ProductVariant::create(['product_id' => $product->id, 'sku' => 'V-'.Str::random(8), 'retail_price' => '99.00', 'is_active' => true]);

        return $product->refresh();
    }

    /** @return array{0: VehicleMake, 1: VehicleModel} */
    private function vehicle(): array
    {
        $make = VehicleMake::create(['name' => 'Marca '.Str::random(4), 'slug' => 'marca-'.Str::random(6)]);
        $model = VehicleModel::create(['make_id' => $make->id, 'name' => 'Model', 'slug' => 'model-'.Str::random(6)]);

        return [$make, $model];
    }
}
