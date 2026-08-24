<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Category;
use Illuminate\Database\Seeder;

class AttributeSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            ['code' => 'color', 'name' => 'Culoare', 'type' => 'select', 'global' => true],
            ['code' => 'material', 'name' => 'Material', 'type' => 'select', 'global' => true],
            ['code' => 'position', 'name' => 'Poziție montaj', 'type' => 'select', 'global' => true],
            ['code' => 'weight_kg', 'name' => 'Greutate', 'type' => 'number', 'unit' => 'kg', 'global' => true],
            ['code' => 'tire_width', 'name' => 'Lățime anvelopă', 'type' => 'number', 'unit' => 'mm', 'paths' => ['anvelope']],
            ['code' => 'tire_aspect_ratio', 'name' => 'Raport înălțime', 'type' => 'number', 'unit' => '%', 'paths' => ['anvelope']],
            ['code' => 'rim_diameter', 'name' => 'Diametru jantă', 'type' => 'number', 'unit' => 'inch', 'paths' => ['anvelope', 'jante-si-flanse']],
            ['code' => 'terrain', 'name' => 'Tip teren', 'type' => 'multiselect', 'paths' => ['anvelope']],
            ['code' => 'bolt_pattern', 'name' => 'Prindere (PCD)', 'type' => 'select', 'paths' => ['jante-si-flanse']],
            ['code' => 'wheel_width', 'name' => 'Lățime jantă', 'type' => 'number', 'unit' => 'inch', 'paths' => ['jante-si-flanse']],
            ['code' => 'wheel_offset', 'name' => 'Offset ET', 'type' => 'number', 'unit' => 'mm', 'paths' => ['jante-si-flanse']],
            ['code' => 'center_bore', 'name' => 'Alezaj central', 'type' => 'number', 'unit' => 'mm', 'paths' => ['jante-si-flanse']],
            ['code' => 'lift_height', 'name' => 'Înălțare', 'type' => 'number', 'unit' => 'mm', 'paths' => ['suspensie-directie']],
            ['code' => 'axle_load', 'name' => 'Sarcină punte', 'type' => 'number', 'unit' => 'kg', 'paths' => ['suspensie-directie']],
            ['code' => 'winch_capacity', 'name' => 'Capacitate tragere', 'type' => 'number', 'unit' => 'kg', 'paths' => ['trolii-si-recuperare']],
            ['code' => 'rope_type', 'name' => 'Tip cablu', 'type' => 'select', 'paths' => ['trolii-si-recuperare']],
            ['code' => 'voltage', 'name' => 'Tensiune', 'type' => 'number', 'unit' => 'V', 'paths' => ['trolii-si-recuperare', 'iluminare']],
            ['code' => 'lumens', 'name' => 'Flux luminos', 'type' => 'number', 'unit' => 'lm', 'paths' => ['iluminare']],
            ['code' => 'beam_pattern', 'name' => 'Tip fascicul', 'type' => 'select', 'paths' => ['iluminare']],
            ['code' => 'ip_rating', 'name' => 'Protecție IP', 'type' => 'select', 'paths' => ['iluminare']],
        ];

        foreach ($definitions as $position => $definition) {
            $attribute = Attribute::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'type' => $definition['type'],
                    'unit' => $definition['unit'] ?? null,
                    'is_global' => $definition['global'] ?? false,
                    'is_active' => true,
                ],
            );

            $paths = $definition['paths'] ?? [];
            if ($definition['global'] ?? false) {
                $paths = Category::query()->whereNull('parent_id')->pluck('full_path')->all();
            }

            Category::query()->whereIn('full_path', $paths)->each(function (Category $category) use ($attribute, $position): void {
                $category->attributes()->syncWithoutDetaching([
                    $attribute->id => [
                        'position' => $position,
                        'is_filterable' => true,
                        'is_comparable' => true,
                        'is_required' => false,
                        'is_variant_defining' => false,
                    ],
                ]);
            });
        }
    }
}
