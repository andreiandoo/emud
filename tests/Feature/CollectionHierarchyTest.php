<?php

namespace Tests\Feature;

use App\Livewire\Admin\Catalog\CollectionEditor;
use App\Livewire\Storefront\CollectionsIndex;
use App\Models\User;
use App\Models\VehicleCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Main collections and the derivatives under them.
 *
 * The tree is deliberately two levels deep and no more: a parent must itself be a root. That one
 * rule rules out both failure modes at once — a collection under its own descendant, and a third
 * level nobody has designed a page for.
 */
class CollectionHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_grid_shows_only_main_collections(): void
    {
        $iveco = $this->collection('IVECO', 'iveco');
        $this->collection('IVECO 35C16', 'iveco-35c16', $iveco);

        Livewire::test(CollectionsIndex::class)
            ->assertSee('IVECO')
            ->assertDontSee('IVECO 35C16');
    }

    /** What the grid hides, the box still finds — otherwise a derivative is unreachable. */
    public function test_the_search_reaches_the_derivatives(): void
    {
        $iveco = $this->collection('IVECO', 'iveco');
        $this->collection('IVECO 35C16', 'iveco-35c16', $iveco);

        Livewire::test(CollectionsIndex::class)
            ->set('search', '35c16')
            ->assertSee('IVECO 35C16');
    }

    public function test_a_main_collection_leads_the_results_over_its_own_derivatives(): void
    {
        $iveco = $this->collection('IVECO', 'iveco');
        $this->collection('IVECO 35C16', 'iveco-35c16', $iveco);
        $this->collection('IVECO 35S18', 'iveco-35s18', $iveco);

        $names = Livewire::test(CollectionsIndex::class)
            ->set('search', 'iveco')
            ->viewData('tiles')
            ->pluck('name')
            ->all();

        $this->assertSame('IVECO', $names[0]);
    }

    public function test_the_parent_page_lists_its_derivatives_and_the_child_names_its_parent(): void
    {
        $iveco = $this->collection('IVECO', 'iveco');
        $child = $this->collection('IVECO 35C16', 'iveco-35c16', $iveco);

        $this->get($iveco->url())
            ->assertOk()
            ->assertSee('Variante de IVECO')
            ->assertSee('IVECO 35C16');

        // The trail on a derivative goes through its make, on screen and in the structured data.
        $this->get($child->url())
            ->assertOk()
            ->assertSee('IVECO 35C16')
            ->assertSee($iveco->url());
    }

    /** A derivative has none by construction, so the page must not offer an empty variants band. */
    public function test_a_derivative_page_does_not_advertise_variants(): void
    {
        $iveco = $this->collection('IVECO', 'iveco');
        $child = $this->collection('IVECO 35C16', 'iveco-35c16', $iveco);

        $this->get($child->url())->assertOk()->assertDontSee('Variante de');
    }

    public function test_an_operator_can_subordinate_a_collection(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $iveco = $this->collection('IVECO', 'iveco');
        $child = $this->collection('IVECO 35C16', 'iveco-35c16');

        Livewire::test(CollectionEditor::class, ['collection' => $child])
            ->set('parentId', (string) $iveco->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($iveco->id, $child->fresh()->parent_id);
    }

    public function test_a_derivative_cannot_be_chosen_as_a_parent(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $iveco = $this->collection('IVECO', 'iveco');
        $child = $this->collection('IVECO 35C16', 'iveco-35c16', $iveco);
        $other = $this->collection('IVECO 35S18', 'iveco-35s18');

        Livewire::test(CollectionEditor::class, ['collection' => $other])
            ->set('parentId', (string) $child->id)
            ->call('save')
            ->assertHasErrors('parentId');

        $this->assertNull($other->fresh()->parent_id);
    }

    public function test_a_collection_cannot_be_its_own_parent(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $iveco = $this->collection('IVECO', 'iveco');

        Livewire::test(CollectionEditor::class, ['collection' => $iveco])
            ->set('parentId', (string) $iveco->id)
            ->call('save')
            ->assertHasErrors('parentId');
    }

    /** Demoting a parent would leave its children two levels down from the new root. */
    public function test_a_collection_that_already_has_derivatives_cannot_become_one(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $iveco = $this->collection('IVECO', 'iveco');
        $this->collection('IVECO 35C16', 'iveco-35c16', $iveco);
        $other = $this->collection('Dacia', 'dacia');

        Livewire::test(CollectionEditor::class, ['collection' => $iveco])
            ->set('parentId', (string) $other->id)
            ->call('save')
            ->assertHasErrors('parentId');

        $this->assertNull($iveco->fresh()->parent_id);
    }

    private function collection(string $name, string $slug, ?VehicleCollection $parent = null): VehicleCollection
    {
        return VehicleCollection::create([
            'name' => $name,
            'slug' => $slug,
            'parent_id' => $parent?->id,
            'is_active' => true,
        ]);
    }
}
