<?php

namespace App\Commerce;

use App\Enums\PricingScope;
use App\Models\Category;
use App\Models\PricingRule;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Support\Collection;

/**
 * Which pricing rule governs a product, and with which guardrails.
 *
 * Most specific wins: a brand rule, then a supplier rule, then the nearest category on the
 * product's primary branch, then the shop-wide default. Categories are walked upwards, so a
 * rule on "Iluminare" covers every LED bar and switch panel below it until a subcategory
 * says otherwise.
 *
 * The guardrails fall back separately from the margin: a category rule that states only a
 * margin still inherits the default rule's contribution floor and change limit.
 */
final class PricingPolicy
{
    /** @var Collection<string, Collection<int, PricingRule>>|null */
    private ?Collection $rules = null;

    /**
     * @return array{rule_id: ?int, scope: string, scope_id: ?int, target_margin_percent: float, minimum_contribution_percent: float, max_auto_change_percent: float}
     */
    public function for(Product $product, ?Supplier $supplier = null): array
    {
        $default = $this->rules(PricingScope::Default)->first();
        $matched = $this->match(PricingScope::Brand, $product->brand_id)
            ?? $this->match(PricingScope::Supplier, $supplier?->id)
            ?? $this->nearestCategoryRule($product)
            ?? $default;

        return [
            'rule_id' => $matched?->id,
            'scope' => $matched?->scope_type->value ?? 'config',
            'scope_id' => $matched?->scope_id,
            'target_margin_percent' => (float) ($matched?->target_gross_margin_percent ?? config('emud.pricing.default_target_margin_percent')),
            'minimum_contribution_percent' => (float) ($matched?->minimum_contribution_percent
                ?? $default?->minimum_contribution_percent
                ?? config('emud.pricing.minimum_contribution_percent')),
            'max_auto_change_percent' => (float) ($matched?->max_auto_change_percent
                ?? $default?->max_auto_change_percent
                ?? config('emud.pricing.max_auto_change_percent')),
        ];
    }

    private function nearestCategoryRule(Product $product): ?PricingRule
    {
        $category = $product->categories()->orderByPivot('is_primary', 'desc')->first();

        // A handful of levels at most; the guard only protects against a cycle in bad data.
        for ($depth = 0; $category !== null && $depth < 12; $depth++) {
            if ($rule = $this->match(PricingScope::Category, $category->id)) {
                return $rule;
            }

            $category = $category->parent_id ? Category::query()->find($category->parent_id) : null;
        }

        return null;
    }

    private function match(PricingScope $scope, ?int $scopeId): ?PricingRule
    {
        return $scopeId === null ? null : $this->rules($scope)->firstWhere('scope_id', $scopeId);
    }

    /** @return Collection<int, PricingRule> */
    private function rules(PricingScope $scope): Collection
    {
        $this->rules ??= PricingRule::query()
            ->where('is_active', true)
            ->get()
            ->groupBy(fn (PricingRule $rule): string => $rule->scope_type->value);

        return $this->rules->get($scope->value, collect());
    }
}
