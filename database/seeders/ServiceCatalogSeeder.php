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
 * The general repair work is here too, since a driver searching for a brake job is the same
 * driver who later needs a snorkel fitted.
 *
 * Each job names the parts category it consumes where one exists. The link is looked up by path
 * and simply skipped when the category has not been seeded, so running this before or after the
 * catalogue both work. Rows are matched on slug, so re-running only updates.
 *
 * @see \Database\Seeders\CategorySeeder for the parts paths referenced below
 */
class ServiceCatalogSeeder extends Seeder
{
    /**
     * Category => [icon, services], each service => [parts category path or null, typical minutes].
     *
     * Public so a test can walk the parts paths: a mistyped one links nothing and says nothing,
     * which is the failure this table is most likely to have.
     *
     * @var array<string, array{icon: string, services: array<string, array{0: ?string, 1: ?int}>}>
     */
    public const CATALOGUE = [
        'Revizii și întreținere' => [
            'icon' => 'oil',
            'services' => [
                'Schimb ulei și filtru ulei' => ['piese-de-schimb/filtre-ulei', 60],
                'Schimb filtru aer' => ['piese-de-schimb/filtre-aer', 20],
                'Schimb filtru habitaclu' => ['piese-de-schimb/filtre-habitaclu', 20],
                'Schimb filtru combustibil' => ['piese-de-schimb/filtre-combustibil', 45],
                'Revizie completă' => [null, 180],
                'Revizie la 15.000 km' => [null, 120],
                'Schimb bujii' => [null, 60],
                'Schimb bujii incandescente' => [null, 90],
                'Verificare înainte de drum lung' => [null, 60],
                'Verificare înainte de cumpărare' => [null, 90],
                'Pregătire pentru ITP' => [null, 60],
                'Schimb ștergătoare' => [null, 15],
            ],
        ],
        'Diagnoză și electronică' => [
            'icon' => 'electrical',
            'services' => [
                'Diagnoză computerizată' => [null, 60],
                'Citire și ștergere erori' => [null, 30],
                'Diagnoză electrică' => [null, 90],
                'Programare chei și telecomenzi' => [null, 60],
                'Calibrare senzori după reparație' => [null, 60],
                'Verificare sistem ABS / ESP' => [null, 90],
            ],
        ],
        'Frâne' => [
            'icon' => 'brake',
            'services' => [
                'Schimb plăcuțe frână față' => ['piese-de-schimb/sistem-de-franare', 60],
                'Schimb plăcuțe frână spate' => ['piese-de-schimb/sistem-de-franare', 60],
                'Schimb plăcuțe și discuri frână' => ['piese-de-schimb/sistem-de-franare', 120],
                'Schimb saboți și tamburi' => ['piese-de-schimb/sistem-de-franare', 120],
                'Schimb lichid de frână' => ['piese-de-schimb/sistem-de-franare', 45],
                'Reparație etrier' => ['piese-de-schimb/sistem-de-franare', 120],
                'Reglaj frână de mână' => [null, 45],
            ],
        ],
        'Suspensie și direcție' => [
            'icon' => 'suspension',
            'services' => [
                'Montaj kit de înălțare' => ['suspensie-directie/kit-uri-de-inaltare', 480],
                'Montaj suspensie completă 4x4' => ['suspensie-directie/suspensii-complete-4x4', 480],
                'Schimb amortizoare' => ['suspensie-directie/amortizoare', 180],
                'Schimb arcuri' => ['suspensie-directie/arcuri-auto', 180],
                'Schimb bucșe punte' => ['suspensie-directie/bucse', 240],
                'Schimb bare stabilizatoare' => ['suspensie-directie/bare-stabilizatoare', 120],
                'Montaj bară Panhard' => ['suspensie-directie/bare-panhard', 150],
                'Montaj bascule superioare' => ['suspensie-directie/bascule-superioare', 240],
                'Corecție camber după înălțare' => ['suspensie-directie/corectie-camber', 120],
                'Montaj body lift' => ['suspensie-directie/body-lift', 360],
                'Schimb amortizor de direcție' => ['suspensie-directie/amortizoare-directie', 90],
                'Geometrie roți' => [null, 90],
                'Schimb capete de bară și bielete' => [null, 120],
                'Reparație casetă de direcție' => [null, 300],
            ],
        ],
        'Transmisie și ambreiaj' => [
            'icon' => 'transmission',
            'services' => [
                'Schimb ambreiaj' => ['transmisie/ambreiaje', 480],
                'Schimb ulei cutie de viteze' => [null, 90],
                'Schimb ulei diferențial' => [null, 90],
                'Montaj diferențial blocabil' => ['transmisie/diferentiale-blocabile', 360],
                'Reparație punte' => ['transmisie/kit-uri-de-reparatii-punte', 480],
                'Schimb cruce cardanică' => ['transmisie/arbori-si-cruci-cardanice', 180],
                'Schimb planetară' => [null, 180],
                'Schimb rulment roată' => ['transmisie/rulmenti', 150],
                'Montaj MRL / cuplaje manuale' => ['transmisie/mrl-uri-avm', 180],
                'Montaj kit de coborâre' => ['transmisie/kit-uri-de-coborare', 300],
            ],
        ],
        'Motor' => [
            'icon' => 'engine',
            'services' => [
                'Schimb curea de distribuție' => [null, 360],
                'Schimb curea de accesorii' => [null, 90],
                'Reparație turbină' => [null, 300],
                'Curățare injectoare' => [null, 150],
                'Schimb pompă de apă' => ['piese-de-schimb/sistem-de-racire', 240],
                'Schimb garnitură de chiulasă' => [null, 600],
                'Curățare EGR și admisie' => [null, 240],
                'Test de compresie' => [null, 90],
            ],
        ],
        'Răcire și climatizare' => [
            'icon' => 'cooling',
            'services' => [
                'Schimb antigel și verificare răcire' => ['piese-de-schimb/sistem-de-racire', 90],
                'Schimb radiator' => ['piese-de-schimb/sistem-de-racire', 180],
                'Schimb termostat' => ['piese-de-schimb/sistem-de-racire', 120],
                'Încărcare climatizare' => [null, 60],
                'Igienizare climatizare' => [null, 45],
                'Detectare scurgeri climatizare' => [null, 90],
            ],
        ],
        'Evacuare și emisii' => [
            'icon' => 'exhaust',
            'services' => [
                'Schimb tobă de eșapament' => [null, 120],
                'Sudură eșapament' => [null, 90],
                'Curățare filtru de particule' => [null, 240],
                'Schimb sondă lambda' => [null, 90],
                'Test de noxe' => [null, 30],
            ],
        ],
        'Roți și anvelope' => [
            'icon' => 'tyre',
            'services' => [
                'Montaj și echilibrare anvelope' => ['anvelope', 60],
                'Montaj anvelope off-road' => ['anvelope/anvelope-off-road', 90],
                'Schimb sezonier de anvelope' => ['anvelope', 45],
                'Vulcanizare' => [null, 45],
                'Montaj flanșe distanțiere' => ['jante-si-flanse/flanse-distantiere', 60],
                'Montaj jante off-road' => ['jante-si-flanse/jante-otel-off-road', 60],
                'Reparație jantă' => [null, 90],
                'Depozitare anvelope' => [null, null],
            ],
        ],
        'Electrice și iluminat' => [
            'icon' => 'light',
            'services' => [
                'Montaj bară LED' => ['iluminare/bare-led', 150],
                'Montaj proiectoare' => ['iluminare/proiectoare', 120],
                'Cablaje și întrerupătoare suplimentare' => ['iluminare/intrerupatoare-cablaje', 180],
                'Schimb becuri și faruri' => ['iluminare/becuri-auto', 45],
                'Reglaj faruri' => [null, 30],
                'Schimb baterie' => [null, 30],
                'Test alternator și demaror' => [null, 60],
                'Montaj priză suplimentară 12V' => [null, 90],
            ],
        ],
        'Off-road și echipare' => [
            'icon' => 'offroad',
            'services' => [
                'Montaj troliu' => ['trolii-si-recuperare/trolii-electrice', 300],
                'Montaj suport troliu' => ['trolii-si-recuperare/suport-troliu', 180],
                'Montaj snorkel' => ['accesorii-interior-exterior/accesorii-exterior/snorkele', 240],
                'Montaj bare metalice' => ['accesorii-interior-exterior/accesorii-exterior/bare-metalice', 240],
                'Montaj scuturi metalice' => ['accesorii-interior-exterior/accesorii-exterior/scuturi-metalice', 180],
                'Montaj praguri laterale' => ['accesorii-interior-exterior/accesorii-exterior/praguri-laterale', 150],
                'Montaj overfendere' => ['accesorii-interior-exterior/accesorii-exterior/overfendere-dedicate', 240],
                'Montaj portbagaj de plafon' => ['accesorii-interior-exterior/accesorii-exterior/portbagaje', 120],
                'Montaj cort de acoperiș' => ['camping-si-outdoor/corturi-de-acoperis-auto', 120],
                'Montaj rollbar' => ['accesorii-interior-exterior/accesorii-exterior/rollbar-uri-pick-up', 240],
                'Montaj hardtop pick-up' => ['accesorii-interior-exterior/accesorii-exterior/hardtopuri-pick-up', 180],
                'Montaj compresor de bord' => ['trolii-si-recuperare/compresoare', 180],
                'Pregătire pentru expediție' => [null, 480],
            ],
        ],
        'Caroserie și geamuri' => [
            'icon' => 'body',
            'services' => [
                'Montaj cârlig de remorcare' => ['accesorii-interior-exterior/accesorii-exterior/carlige-remorcare', 240],
                'Reparație și vopsire element' => ['accesorii-interior-exterior/accesorii-exterior/elemente-caroserie', 480],
                'Îndreptare fără vopsire' => [null, 240],
                'Schimb parbriz' => [null, 180],
                'Reparație parbriz' => [null, 60],
                'Tratament anticoroziv' => [null, 300],
                'Polish și detailing' => ['intretinere-auto/accesorii-detailing', 300],
            ],
        ],
        'Interior și confort' => [
            'icon' => 'interior',
            'services' => [
                'Montaj scaune și huse' => ['accesorii-interior-exterior/accesorii-interior/scaune-huse', 120],
                'Montaj sisteme de depozitare' => ['accesorii-interior-exterior/accesorii-interior/sisteme-depozitare-interior', 180],
                'Montaj frigider auto' => ['camping-si-outdoor/frigidere-auto', 90],
                'Montaj stație radio și antenă' => ['camping-si-outdoor/comunicatie-navigatie', 150],
                'Igienizare interior' => ['intretinere-auto/produse-curatare-interior', 120],
            ],
        ],
        'Asistență' => [
            'icon' => 'tool',
            'services' => [
                'Tractare' => [null, null],
                'Asistență rutieră' => [null, null],
                'Pornire asistată' => [null, null],
                'Deblocare și recuperare off-road' => ['trolii-si-recuperare/accesorii-recuperare', null],
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
