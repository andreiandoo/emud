<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $tree = [
            'Echipamente service' => ['Unelte & Scule', 'Cricuri și capre'],
            'Cadouri' => ['Căni'],
            'Camping și Outdoor' => [
                'Accesorii Corturi Auto', 'Genți și rucsacuri', 'Accesorii biciclete', 'Accesorii camping',
                'Accesorii autoapărare', 'Accesorii camping pentru iarnă', 'Canistre', 'Comunicație & navigație',
                'Corturi de acoperiș auto' => ['Corturi de acoperiș Hard Top', 'Corturi de acoperiș Soft Top'],
                'Corturi camping', 'Cutii depozitare', 'Echipament pentru gătit', 'Frigidere auto', 'Hamace',
                'Iluminat camping', 'Mobilier Camping' => ['Mese Camping', 'Paturi Compacte Camping', 'Scaune Camping'],
                'Suporturi biciclete', 'Umbrare Camping',
            ],
            'Accesorii Interior & Exterior' => [
                'Accesorii Exterior' => [
                    'Overfendere universale', 'Hardtopuri Pick-Up', 'Accesorii Portbagaje Auto', 'Bare transversale',
                    'Bare Metalice' => ['Bare Față', 'Bare Spate'], 'Capitonaje Benă', 'Cârlige remorcare',
                    'Deflectoare capotă', 'Elemente caroserie', 'Extindere aripi', 'Închideri benă Pick-up',
                    'Ornamente', 'Overfendere dedicate', 'Portbagaje', 'Praguri laterale', 'Rollbar-uri Pick-Up',
                    'Scări auto', 'Scuturi Metalice', 'Sisteme depozitare exterior', 'Snorkele', 'Soft & Hard Top',
                ],
                'Accesorii Interior' => [
                    'Accesorii Electrice', 'Accesorii Generale', 'Covorașe & Tăvițe portbagaj', 'Elemente interior',
                    'Scaune & huse', 'Sisteme depozitare interior', 'Sisteme Fixare',
                ],
            ],
            'Anvelope' => ['Anvelope ATV', 'Anvelope de stradă', 'Anvelope Off-Road'],
            'Iluminare' => ['Bare LED', 'Becuri auto', 'Faruri, semnalizări & stopuri', 'Întrerupătoare & Cablaje', 'Proiectoare', 'Suporturi proiectoare'],
            'Întreținere auto' => ['Odorizante auto', 'Accesorii Detailing', 'Produse Curățare Exterior', 'Produse Curățare Interior'],
            'Jante și Flanșe' => ['Flanșe distanțiere', 'Jante Aliaj', 'Jante oțel Off-Road', 'Piulițe și prezoane'],
            'Piese de schimb' => ['Filtre ulei', 'Filtre combustibil', 'Filtre habitaclu', 'Filtre aer', 'Sistem de frânare', 'Sistem de răcire'],
            'Rulote' => [],
            'Suspensie & Direcție' => [
                'Amortizoare', 'Amortizoare direcție', 'Arcuri auto' => ['Arcuri elicoidale', 'Arcuri lamelare'],
                'Bare Panhard', 'Bare Stabilizatoare', 'Bascule Superioare', 'Body Lift', 'Bucșe', 'Corecție Camber',
                'Înălțătoare arcuri', 'Kit-uri de înălțare', 'Limitatoare cursă', 'Suspensii Complete 4x4',
            ],
            'Transmisie' => ['Ambreiaje', 'Arbori și cruci cardanice', 'Diferențiale blocabile', 'Kit-uri de coborâre', 'Kit-uri de reparații punte', 'MRL-uri AVM', 'Rulmenți'],
            'Trolii și Recuperare' => [
                'Accesorii recuperare' => ['Chingi & Șufe Copac', 'Hi Lift', 'Mănuși', 'Plăci antiderapare', 'Scripete & Ocheți', 'Șufe Cinetice'],
                'Compresoare', 'Piese trolii', 'Plasme sintetice', 'Suport troliu', 'Trolii ATV', 'Trolii electrice', 'Trolii manuale',
            ],
            'Uleiuri & aditivi' => ['Aditivi combustibil', 'Aditivi Ulei'],
            'Outlet' => [],
            'Produse noi' => [],
        ];

        $this->seedLevel($tree);
    }

    private function seedLevel(array $tree, ?Category $parent = null): void
    {
        $position = 0;

        foreach ($tree as $key => $value) {
            $name = is_int($key) ? $value : $key;
            $children = is_int($key) ? [] : $value;
            $slug = Str::slug($name);
            $fullPath = $parent ? "{$parent->full_path}/{$slug}" : $slug;

            $category = Category::query()->updateOrCreate(
                ['full_path' => $fullPath],
                [
                    'parent_id' => $parent?->id,
                    'name' => $name,
                    'slug' => $slug,
                    'depth' => $parent ? $parent->depth + 1 : 0,
                    'position' => $position++,
                    'is_active' => true,
                    'is_visible_in_menu' => true,
                ],
            );

            if ($children !== []) {
                $this->seedLevel($children, $category);
            }
        }
    }
}
