<?php

namespace Tests\Feature;

use App\Livewire\Admin\Catalog\AttributesIndex;
use App\Livewire\Admin\Catalog\CategoriesIndex;
use App\Livewire\Admin\Catalog\ProductEditor;
use App\Livewire\Admin\Content\ArticleEditor;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminCatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_nested_category_with_seo_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $parent = Category::create(['name' => 'Suspensii', 'slug' => 'suspensii', 'full_path' => 'suspensii']);

        Livewire::actingAs($admin)->test(CategoriesIndex::class)
            ->set('name', 'Amortizoare')
            ->set('slug', 'amortizoare')
            ->set('parentId', $parent->id)
            ->set('seoTitle', 'Amortizoare 4x4')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categories', ['full_path' => 'suspensii/amortizoare', 'depth' => 1, 'seo_title' => 'Amortizoare 4x4']);
    }

    public function test_admin_can_define_filter_options_and_attach_categories(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Anvelope', 'slug' => 'anvelope', 'full_path' => 'anvelope']);

        Livewire::actingAs($admin)->test(AttributesIndex::class)
            ->set('name', 'Diametru')
            ->set('code', 'diametru')
            ->set('type', 'select')
            ->set('categoryIds', [$category->id])
            ->set('optionsText', "33 inch|33\n35 inch|35")
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('attributes', ['code' => 'diametru']);
        $this->assertDatabaseHas('attribute_options', ['value' => '35']);
        $this->assertDatabaseHas('attribute_category', ['category_id' => $category->id, 'is_filterable' => true]);
    }

    public function test_admin_can_publish_article_with_seo(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)->test(ArticleEditor::class)
            ->set('title', 'Ghid troliu 4x4')
            ->set('slug', 'ghid-troliu-4x4')
            ->set('content', '<p>Conținut editorial complet.</p>')
            ->set('status', 'published')
            ->set('seoTitle', 'Cum alegi un troliu 4x4')
            ->call('save')
            ->assertHasNoErrors();

        $article = Article::firstOrFail();
        $this->assertNotNull($article->published_at);
        $this->assertSame('Cum alegi un troliu 4x4', $article->seo_title);
    }

    public function test_admin_can_create_a_complete_product_variant(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Trolii', 'slug' => 'trolii', 'full_path' => 'trolii']);

        Livewire::actingAs($admin)->test(ProductEditor::class)
            ->set('name', 'Troliu 12.000 lbs')
            ->set('slug', 'troliu-12000-lbs')
            ->set('categoryIds', [$category->id])
            ->set('variants.0.sku', 'TRL-12000')
            ->set('variants.0.price', 2499.90)
            ->set('seoTitle', 'Troliu 12.000 lbs pentru 4x4')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('products', ['slug' => 'troliu-12000-lbs', 'seo_title' => 'Troliu 12.000 lbs pentru 4x4']);
        $this->assertDatabaseHas('product_variants', ['sku' => 'TRL-12000', 'retail_price' => 2499.90]);
    }
}
