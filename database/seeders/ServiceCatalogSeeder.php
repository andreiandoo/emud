<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The starting vocabulary of workshop jobs.
 *
 * Weighted towards what this shop actually sells — lift kits, winches, off-road tyres — rather
 * than a generic garage list, because a taxonomy nobody's listings match is worse than none.
 *
 * Each job names the parts category it consumes where one exists. The link is looked up by path
 * and simply skipped when the category has not been seeded, so running this before or after the
 * catalogue both work.
 */
class ServiceCatalogSeeder extends Seeder
{
    /** @var array<string, array{icon: string, services: array<string, array{0: ?string, 1: ?int}>}> */
    private const CATALOGUE = [
        'Revizii și întreținere' => [
            'icon' => 'oil',
            'services' => [
                'Schimb ulei și filtru ulei' => ['piese-de-schimb/filtre-ulei', 60],
                'Schimb filtru aer' => ['piese-de-schimb/filtre-aer', 20],
                'Schimb filtru habitaclu' => ['piese-de-schimb/filtre-habitaclu', 20],
                'Schimb filtru combustibil' => ['piese-de-schimb/filtre-combustibil', 45],
                'Revizie completă' => [null, 180],
                'Pregătire pentru ITP' => [null, 60],
            ],
        ],
        'Frâne' => [
            'icon' => 'brake',
            'services' => [
                'Schimb plăcuțe frână față' => ['piese-de-schimb/sistem-de-franare', 60],
                'Schimb plăcuțe și discuri frână' => ['piese-de-schimb/sistem-de-franare', 120],
                'Schimb lichid de frână' => ['piese-de-schimb/sistem-de-franare', 45],
            ],
        ],
        'Suspensie și direcție' => [
            'icon' => 'suspension',
            'services' => [
                'Montaj kit de înălțare' => ['suspensie-directie/kit-uri-de-inaltare', 480],
                'Schimb amortizoare' => ['suspensie-directie/amortizoare', 180],
                'Schimb arcuri' => ['suspensie-directie/arcuri-auto', 180],
                'Schimb bucșe punte' => ['suspensie-directie/bucse', 240],
                'Geometrie roți' => [null, 90],
                'Corecție camber după înălțare' => ['suspensie-directie/corectie-camber', 120],
            ],
        ],
        'Transmisie' => [
            'icon' => 'transmission',
            'services' => [
                'Schimb ambreiaj' => ['transmisie/ambreiaje', 480],
                'Montaj diferențial blocabil' => ['transmisie/diferentiale-blocabile', 360],
                'Reparație punte' => ['transmisie/kit-uri-de-reparatii-punte', 480],
                'Schimb cruce cardanică' => ['transmisie/arbori-si-cruci-cardanice', 180],
            ],
        ],
        'Roți și anvelope' => [
            'icon' => 'tyre',
            'services' => [
                'Montaj și echilibrare anvelope' => ['anvelope', 60],
                'Montaj anvelope off-road' => ['anvelope/anvelope-off-road', 90],
                'Montaj flanșe distanțiere' => ['jante-si-flanse/flanse-distantiere', 60],
                'Vulcanizare' => [null, 45],
            ],
        ],
        'Off-road și echipare' => [
            'icon' => 'offroad',
            'services' => [
                'Montaj troliu' => ['trolii-si-recuperare/trolii-electrice', 300],
                'Montaj snorkel' => ['accesorii-interior-exterior/accesorii-exterior/snorkele', 240],
                'Montaj bare metalice' => ['accesorii-interior-exterior/accesorii-exterior/bare-metalice', 240],
                'Montaj scuturi metalice' => ['accesorii-interior-exterior/accesorii-exterior/scuturi-metalice', 180],
                'Montaj portbagaj de plafon' => ['accesorii-interior-exterior/accesorii-exterior/portbagaje', 120],
                'Montaj cort de acoperiș' => ['camping-si-outdoor/corturi-de-acoperis-auto', 120],
            ],
        ],
        'Electrice și iluminat' => [
            'icon' => 'electrical',
            'services' => [
                'Montaj bară LED' => ['iluminare/bare-led', 150],
                'Montaj proiectoare' => ['iluminare/proiectoare', 120],
                'Cablaje și întrerupătoare suplimentare' => ['iluminare/intrerupatoare-cablaje', 180],
                'Diagnoză electrică' => [null, 60],
            ],
        ],
        'Motor și răcire' => [
            'icon' => 'engine',
            'services' => [
                'Diagnoză computerizată' => [null, 60],
                'Schimb curea de distribuție' => [null, 360],
                'Schimb antigel și verificare răcire' => ['piese-de-schimb/sistem-de-racire', 90],
            ],
        ],
        'Climatizare' => [
            'icon' => 'cooling',
            'services' => [
                'Încărcare climatizare' => [null, 60],
                'Igienizare climatizare' => [null, 45],
            ],
        ],
    ];

    public function run(): void
    {
        $position = 0;

        foreach (self::CATALOGUE as $categoryName => $definition) {
            $category = ServiceCategory::query()->updateOrCreate(
                ['slug' => Str::slug($categoryName)],
                ['name' => $categoryName, 'icon' => $definition['icon'], 'position' => $position++],
            );

            $servicePosition = 0;

            foreach ($definition['services'] as $serviceName => [$categoryPath, $duration]) {
                Service::query()->updateOrCreate(
                    ['slug' => Str::slug($serviceName)],
                    [
                        'service_category_id' => $category->id,
                        'name' => $serviceName,
                        'category_id' => $this->partsCategoryId($categoryPath),
                        'typical_duration_minutes' => $duration,
                        'is_active' => true,
                        'position' => $servicePosition++,
                    ],
                );
            }
        }
    }

    private function partsCategoryId(?string $path): ?int
    {
        return $path === null ? null : Category::query()->where('full_path', $path)->value('id');
    }
}
