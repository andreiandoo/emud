<?php

namespace Database\Seeders;

use App\Enums\PricingScope;
use App\Models\Category;
use App\Models\PricingRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The starting pricing policy, approved on 2026-09-10.
 *
 * Target gross margins are the lower bounds of the planning ranges in the high-margin
 * research: lighting and wiring sell well above their cost, tyres sell close to theirs.
 * They are a starting point for the back office, not a verdict, and should be replaced by
 * measured margins once real sales exist.
 *
 * Additive: an existing rule is never overwritten, so an operator's edit survives re-seeding.
 */
class PricingRuleSeeder extends Seeder
{
    /** @var list<array{0: list<string>, 1: float}> category path by name, target margin */
    private const CATEGORY_MARGINS = [
        [['Iluminare'], 45],
        [['Trolii și Recuperare', 'Accesorii recuperare'], 40],
        [['Accesorii Interior & Exterior', 'Accesorii Exterior'], 30],
        [['Suspensie & Direcție'], 25],
        [['Trolii și Recuperare'], 20],
        [['Jante și Flanșe'], 20],
        [['Anvelope'], 15],
    ];

    public function run(): void
    {
        PricingRule::query()->firstOrCreate(
            ['scope_type' => PricingScope::Default, 'scope_id' => null],
            [
                'target_gross_margin_percent' => 25,
                'minimum_contribution_percent' => 8,
                'max_auto_change_percent' => 15,
                'notes' => 'Regula implicită, pentru orice produs fără o regulă mai specifică.',
            ],
        );

        foreach (self::CATEGORY_MARGINS as [$names, $margin]) {
            // Built the way CategorySeeder builds it, so a renamed category is simply skipped
            // rather than matched to the wrong branch.
            $path = implode('/', array_map(static fn (string $name): string => Str::slug($name), $names));
            $category = Category::query()->where('full_path', $path)->first();

            if (! $category) {
                continue;
            }

            PricingRule::query()->firstOrCreate(
                ['scope_type' => PricingScope::Category, 'scope_id' => $category->id],
                ['target_gross_margin_percent' => $margin, 'notes' => 'Din tabelul aprobat pe 2026-09-10.'],
            );
        }
    }
}
