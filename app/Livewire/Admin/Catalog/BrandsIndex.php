<?php

namespace App\Livewire\Admin\Catalog;

use App\Models\Brand;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts::admin')]
class BrandsIndex extends Component
{
    use WithFileUploads;

    public ?int $editingId = null;
    public string $name = '';
    public string $website = '';
    public string $description = '';
    public bool $isActive = true;
    public $logo;

    public function edit(int $id): void
    {
        $brand = Brand::findOrFail($id);
        $this->editingId = $id; $this->name = $brand->name; $this->website = $brand->website ?? '';
        $this->description = $brand->description ?? ''; $this->isActive = $brand->is_active;
    }

    public function save(): void
    {
        $data = $this->validate(['name' => ['required', 'string', 'max:255'], 'website' => ['nullable', 'url'], 'description' => ['nullable', 'string'], 'logo' => ['nullable', 'image', 'max:4096']]);
        $brand = $this->editingId ? Brand::findOrFail($this->editingId) : new Brand;
        $brand->fill(['name' => $data['name'], 'slug' => Str::slug($data['name']), 'website' => $data['website'] ?: null, 'description' => $data['description'] ?: null, 'is_active' => $this->isActive, 'logo_path' => $this->logo?->store('brands', 'public') ?? $brand->logo_path])->save();
        $this->reset(['editingId', 'name', 'website', 'description', 'logo']); $this->isActive = true;
    }

    public function delete(int $id): void { Brand::findOrFail($id)->delete(); }

    public function render() { return view('livewire.admin.catalog.brands-index', ['brands' => Brand::withCount('products')->orderBy('name')->get()]); }
}
