<?php

namespace App\Livewire\Admin;

use App\Models\Category;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Storefront\CategoryIcons;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The vocabulary of jobs a workshop can offer.
 *
 * Kept as real records rather than free text on each listing so that two workshops offering the
 * same job are comparable, and so a job can be linked once to the parts it consumes instead of
 * being re-matched every time somebody types it.
 */
#[Layout('layouts::admin')]
class ServiceCatalogIndex extends Component
{
    public ?int $categoryId = null;

    public string $categoryName = '';

    public string $categoryIcon = '';

    public ?int $serviceId = null;

    public ?int $serviceCategoryId = null;

    public string $serviceName = '';

    public string $serviceDescription = '';

    public ?int $partsCategoryId = null;

    public string $duration = '';

    public bool $serviceActive = true;

    public function editCategory(int $id): void
    {
        $category = ServiceCategory::query()->findOrFail($id);

        $this->categoryId = $category->id;
        $this->categoryName = $category->name;
        $this->categoryIcon = (string) $category->icon;
    }

    public function saveCategory(): void
    {
        $data = $this->validate([
            'categoryName' => ['required', 'string', 'max:120'],
            'categoryIcon' => ['nullable', Rule::in(array_keys(CategoryIcons::OPTIONS))],
        ], [], ['categoryName' => 'denumirea categoriei']);

        ServiceCategory::query()->updateOrCreate(
            ['id' => $this->categoryId],
            [
                'name' => $data['categoryName'],
                'slug' => Str::slug($data['categoryName']),
                'icon' => $data['categoryIcon'] ?: null,
            ],
        );

        $this->resetCategoryForm();
        session()->flash('success', 'Categoria de servicii a fost salvată.');
    }

    public function deleteCategory(int $id): void
    {
        $category = ServiceCategory::query()->withCount('services')->findOrFail($id);

        // Deleting the category would cascade to its services and take every workshop's price
        // for them with it, so the refusal is explicit rather than a surprise.
        abort_if($category->services_count > 0, 422, 'Categoria are servicii; mută-le sau șterge-le întâi.');

        $category->delete();
        $this->resetCategoryForm();
    }

    public function editService(int $id): void
    {
        $service = Service::query()->findOrFail($id);

        $this->serviceId = $service->id;
        $this->serviceCategoryId = $service->service_category_id;
        $this->serviceName = $service->name;
        $this->serviceDescription = (string) $service->description;
        $this->partsCategoryId = $service->category_id;
        $this->duration = (string) ($service->typical_duration_minutes ?? '');
        $this->serviceActive = (bool) $service->is_active;
    }

    public function saveService(): void
    {
        $data = $this->validate([
            'serviceCategoryId' => ['required', 'integer', 'exists:service_categories,id'],
            'serviceName' => ['required', 'string', 'max:160'],
            'serviceDescription' => ['nullable', 'string'],
            'partsCategoryId' => ['nullable', 'integer', 'exists:categories,id'],
            'duration' => ['nullable', 'integer', 'min:5', 'max:2880'],
        ], [], ['serviceName' => 'denumirea serviciului']);

        Service::query()->updateOrCreate(
            ['id' => $this->serviceId],
            [
                'service_category_id' => $data['serviceCategoryId'],
                'name' => $data['serviceName'],
                'slug' => $this->serviceId ? Service::query()->find($this->serviceId)->slug : Str::slug($data['serviceName']),
                'description' => $data['serviceDescription'] ?: null,
                'category_id' => $data['partsCategoryId'],
                'typical_duration_minutes' => $data['duration'] === '' ? null : (int) $data['duration'],
                'is_active' => $this->serviceActive,
            ],
        );

        $this->resetServiceForm();
        session()->flash('success', 'Serviciul a fost salvat.');
    }

    public function deleteService(int $id): void
    {
        Service::query()->findOrFail($id)->delete();
        $this->resetServiceForm();
    }

    public function resetCategoryForm(): void
    {
        $this->reset(['categoryId', 'categoryName', 'categoryIcon']);
        $this->resetValidation();
    }

    public function resetServiceForm(): void
    {
        $this->reset(['serviceId', 'serviceCategoryId', 'serviceName', 'serviceDescription', 'partsCategoryId', 'duration']);
        $this->serviceActive = true;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.admin.service-catalog-index', [
            'categories' => ServiceCategory::query()
                ->withCount('services')
                ->with(['services' => fn ($query) => $query->withCount('shops')->with('partsCategory')])
                ->orderBy('position')
                ->orderBy('name')
                ->get(),
            'partsCategories' => Category::query()->orderBy('full_path')->get(['id', 'name', 'full_path', 'depth']),
            'iconOptions' => CategoryIcons::OPTIONS,
        ]);
    }
}
