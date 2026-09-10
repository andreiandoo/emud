<?php

namespace App\Livewire\Admin\Pricing;

use App\Enums\PricingScope;
use App\Models\Brand;
use App\Models\Category;
use App\Models\PricingRule;
use App\Models\Supplier;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin')]
class PricingRulesIndex extends Component
{
    public ?int $editing = null;

    public string $scopeType = 'category';

    public ?int $scopeId = null;

    public ?string $targetMargin = null;

    public ?string $minimumContribution = null;

    public ?string $maxChange = null;

    public ?string $notes = null;

    public function edit(int $ruleId): void
    {
        $rule = PricingRule::query()->findOrFail($ruleId);

        $this->editing = $rule->id;
        $this->scopeType = $rule->scope_type->value;
        $this->scopeId = $rule->scope_id;
        $this->targetMargin = (string) $rule->target_gross_margin_percent;
        $this->minimumContribution = $rule->minimum_contribution_percent === null ? null : (string) $rule->minimum_contribution_percent;
        $this->maxChange = $rule->max_auto_change_percent === null ? null : (string) $rule->max_auto_change_percent;
        $this->notes = $rule->notes;
    }

    public function resetForm(): void
    {
        $this->reset(['editing', 'scopeType', 'scopeId', 'targetMargin', 'minimumContribution', 'maxChange', 'notes']);
        $this->resetValidation();
    }

    public function save(): void
    {
        foreach (['targetMargin', 'minimumContribution', 'maxChange', 'notes'] as $property) {
            if ($this->{$property} === '') {
                $this->{$property} = null;
            }
        }

        if ($this->scopeType === PricingScope::Default->value) {
            $this->scopeId = null;
        }

        $this->validate([
            'scopeType' => ['required', Rule::enum(PricingScope::class)],
            'scopeId' => [
                Rule::requiredIf($this->scopeType !== PricingScope::Default->value),
                'nullable',
                'integer',
                match ($this->scopeType) {
                    PricingScope::Category->value => Rule::exists('categories', 'id'),
                    PricingScope::Brand->value => Rule::exists('brands', 'id'),
                    PricingScope::Supplier->value => Rule::exists('suppliers', 'id'),
                    default => 'nullable',
                },
            ],
            // Capped at 90: a margin at or above 100% has no price, only a division by zero.
            'targetMargin' => ['required', 'numeric', 'min:0', 'max:90'],
            'minimumContribution' => ['nullable', 'numeric', 'min:0', 'max:90'],
            'maxChange' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $clash = PricingRule::query()
            ->where('scope_type', $this->scopeType)
            ->where('scope_id', $this->scopeId)
            ->when($this->editing, fn ($query) => $query->whereKeyNot($this->editing))
            ->exists();

        if ($clash) {
            $this->addError('scopeId', 'Există deja o regulă pentru acest element. Editeaz-o pe aceea.');

            return;
        }

        $payload = [
            'scope_type' => $this->scopeType,
            'scope_id' => $this->scopeId,
            'target_gross_margin_percent' => (float) $this->targetMargin,
            'minimum_contribution_percent' => $this->minimumContribution === null ? null : (float) $this->minimumContribution,
            'max_auto_change_percent' => $this->maxChange === null ? null : (float) $this->maxChange,
            'notes' => $this->notes,
        ];

        $this->editing
            ? PricingRule::query()->findOrFail($this->editing)->update($payload)
            : PricingRule::query()->create($payload);

        $this->resetForm();
        session()->flash('status', 'Regula a fost salvată. Se aplică la următoarea schimbare de cost sau la recalcularea de noapte.');
    }

    public function toggle(int $ruleId): void
    {
        $rule = PricingRule::query()->findOrFail($ruleId);

        // The default rule is what every other rule falls back to; switching it off would
        // leave products governed by an environment variable nobody can see.
        if ($rule->scope_type === PricingScope::Default) {
            return;
        }

        $rule->update(['is_active' => ! $rule->is_active]);
    }

    public function delete(int $ruleId): void
    {
        $rule = PricingRule::query()->findOrFail($ruleId);

        if ($rule->scope_type !== PricingScope::Default) {
            $rule->delete();
        }
    }

    public function render()
    {
        $categories = Category::query()->orderBy('full_path')->get(['id', 'name', 'full_path', 'depth']);
        $brands = Brand::query()->orderBy('name')->pluck('name', 'id');
        $suppliers = Supplier::query()->orderBy('name')->pluck('name', 'id');

        return view('livewire.admin.pricing.pricing-rules-index', [
            'rules' => $this->rulesWithLabels($categories, $brands, $suppliers),
            'categories' => $categories,
            'brands' => $brands,
            'suppliers' => $suppliers,
            'scopes' => PricingScope::cases(),
            'defaults' => [
                'minimum_contribution' => (float) config('emud.pricing.minimum_contribution_percent'),
                'max_change' => (float) config('emud.pricing.max_auto_change_percent'),
            ],
        ]);
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @param  Collection<int, string>  $brands
     * @param  Collection<int, string>  $suppliers
     * @return Collection<int, array{rule: PricingRule, label: string}>
     */
    private function rulesWithLabels(Collection $categories, Collection $brands, Collection $suppliers): Collection
    {
        $categoryPaths = $categories->mapWithKeys(fn (Category $category): array => [$category->id => $category->full_path]);
        $order = array_flip(array_map(static fn (PricingScope $scope): string => $scope->value, PricingScope::cases()));

        return PricingRule::query()->get()
            ->map(fn (PricingRule $rule): array => [
                'rule' => $rule,
                'label' => match ($rule->scope_type) {
                    PricingScope::Default => 'Toate produsele',
                    PricingScope::Category => $categoryPaths[$rule->scope_id] ?? "Categorie #{$rule->scope_id} (ștearsă)",
                    PricingScope::Brand => $brands[$rule->scope_id] ?? "Brand #{$rule->scope_id} (șters)",
                    PricingScope::Supplier => $suppliers[$rule->scope_id] ?? "Furnizor #{$rule->scope_id} (șters)",
                },
            ])
            ->sortBy(fn (array $row): string => $order[$row['rule']->scope_type->value].'|'.$row['label'])
            ->values();
    }
}
