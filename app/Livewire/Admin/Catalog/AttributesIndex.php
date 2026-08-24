<?php

namespace App\Livewire\Admin\Catalog;

use App\Models\Attribute;
use App\Models\Category;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin')]
class AttributesIndex extends Component
{
    public ?int $editingId = null;
    public string $name = '';
    public string $code = '';
    public string $type = 'select';
    public string $unit = '';
    public string $helpText = '';
    public bool $isGlobal = false;
    public bool $isActive = true;
    public bool $isFilterable = true;
    public bool $isComparable = true;
    public bool $isRequired = false;
    public bool $isVariantDefining = false;
    public array $categoryIds = [];
    public string $optionsText = '';

    public function updatedName(): void
    {
        if (! $this->editingId || $this->code === '') {
            $this->code = Str::slug($this->name, '_');
        }
    }

    public function edit(int $attributeId): void
    {
        $attribute = Attribute::with(['options', 'categories'])->findOrFail($attributeId);
        $this->editingId = $attribute->id;
        $this->name = $attribute->name;
        $this->code = $attribute->code;
        $this->type = $attribute->type;
        $this->unit = $attribute->unit ?? '';
        $this->helpText = $attribute->help_text ?? '';
        $this->isGlobal = $attribute->is_global;
        $this->isActive = $attribute->is_active;
        $this->categoryIds = $attribute->categories->modelKeys();
        $pivot = $attribute->categories->first()?->pivot;
        $this->isFilterable = $pivot?->is_filterable ?? true;
        $this->isComparable = $pivot?->is_comparable ?? true;
        $this->isRequired = $pivot?->is_required ?? false;
        $this->isVariantDefining = $pivot?->is_variant_defining ?? false;
        $this->optionsText = $attribute->options->map(fn ($option) => "{$option->label}|{$option->value}")->implode("\n");
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'regex:/^[a-z0-9_]+$/', 'max:255', 'unique:attributes,code,'.($this->editingId ?? 'NULL')],
            'type' => ['required', 'in:text,number,boolean,select,multiselect,color'],
            'unit' => ['nullable', 'string', 'max:32'],
            'helpText' => ['nullable', 'string'],
            'categoryIds' => ['array'],
            'categoryIds.*' => ['integer', 'exists:categories,id'],
            'optionsText' => ['nullable', 'string'],
        ]);

        $attribute = Attribute::updateOrCreate(['id' => $this->editingId], [
            'name' => $validated['name'],
            'code' => $validated['code'],
            'type' => $validated['type'],
            'unit' => $validated['unit'] ?: null,
            'help_text' => $validated['helpText'] ?: null,
            'is_global' => $this->isGlobal,
            'is_active' => $this->isActive,
        ]);

        $pivot = [];
        foreach ($this->categoryIds as $position => $categoryId) {
            $pivot[$categoryId] = [
                'position' => $position,
                'is_required' => $this->isRequired,
                'is_filterable' => $this->isFilterable,
                'is_comparable' => $this->isComparable,
                'is_variant_defining' => $this->isVariantDefining,
            ];
        }
        $attribute->categories()->sync($pivot);
        $attribute->options()->delete();
        if (in_array($this->type, ['select', 'multiselect', 'color'], true)) {
            collect(preg_split('/\r\n|\r|\n/', trim($this->optionsText)))->filter()->values()->each(function (string $line, int $position) use ($attribute): void {
                [$label, $value] = array_pad(array_map('trim', explode('|', $line, 2)), 2, null);
                $attribute->options()->create(['label' => $label, 'value' => $value ?: Str::slug($label), 'position' => $position]);
            });
        }

        $this->resetForm();
        session()->flash('success', 'Filtrul a fost salvat.');
    }

    public function delete(int $attributeId): void
    {
        Attribute::findOrFail($attributeId)->delete();
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'code', 'unit', 'helpText', 'isGlobal', 'isRequired', 'isVariantDefining', 'categoryIds', 'optionsText']);
        $this->type = 'select';
        $this->isActive = $this->isFilterable = $this->isComparable = true;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.admin.catalog.attributes-index', [
            'attributes' => Attribute::query()->withCount(['categories', 'options'])->orderBy('name')->get(),
            'categories' => Category::query()->orderBy('full_path')->get(),
        ]);
    }
}
