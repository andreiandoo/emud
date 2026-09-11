<?php

namespace Database\Seeders;

use App\Directory\ShopFacilities;
use App\Models\Service;
use App\Models\ServiceShop;
use App\Models\VehicleMake;
use Illuminate\Database\Seeder;

/**
 * One workshop with every field a listing has filled in, to design and check the public page
 * against: description, hours, priced jobs, makes, facilities, payment methods, certifications,
 * specialities, appointments.
 *
 * Kept as a draft, so it is never listed or shown to a customer: staff open it at its address
 * and see it as a preview. Running it again puts the same data back.
 *
 *   php artisan db:seed --class=DemoServiceShopSeeder --force
 */
class DemoServiceShopSeeder extends Seeder
{
    public const SLUG = 'atelier-demo-emud';

    /** Catalogue job slug => [price from, price to, minutes, note]. */
    private const PRICES = [
        'montaj-kit-de-inaltare' => [1200, 2500, 480, 'Manoperă, fără piese. Geometria inclusă.'],
        'montaj-suspensie-completa-4x4' => [1500, 3200, 480, null],
        'montaj-diferential-blocabil' => [900, 1800, 360, null],
        'montaj-troliu' => [600, 1200, 300, 'Cu cablaj și suport.'],
        'montaj-snorkel' => [450, 800, 240, null],
        'montaj-anvelope-off-road' => [160, 260, 90, 'Set de patru.'],
        'geometrie-roti' => [180, null, 90, null],
        'schimb-placute-si-discuri-frana' => [350, 600, 120, null],
        'schimb-ulei-si-filtru-ulei' => [150, null, 60, 'Fără ulei și filtru.'],
        'diagnoza-computerizata' => [120, null, 60, null],
    ];

    private const MAKES = ['Toyota', 'Land Rover', 'Suzuki', 'Jeep', 'Dacia', 'Mitsubishi', 'Nissan', 'Ford', 'Isuzu'];

    public function run(): void
    {
        $shop = ServiceShop::withTrashed()->updateOrCreate(['slug' => self::SLUG], [
            'name' => 'Atelier Demo eMUD 4x4',
            'status' => 'draft',
            'deleted_at' => null,
            'description' => '<p><strong>Fișă demonstrativă.</strong> Datele de aici sunt de probă și arată cum se vede o fișă completă.</p>'
                .'<p>Atelier specializat pe 4x4 și off-road: suspensii înălțate, blocaje de diferențial, trolii și pregătire pentru expediții. '
                .'Lucrăm pe mașini de zi cu zi și pe cele de concurs, cu diagnoză pe loc.</p>'
                .'<ul><li>Montăm piesele cumpărate de la eMUD</li><li>Geometria după înălțare este inclusă</li><li>Probă pe traseu după montaj</li></ul>',
            'county' => 'Brașov',
            'city' => 'Brașov',
            'city_slug' => 'brasov',
            'address' => 'Str. Demonstrației nr. 4',
            'postal_code' => '500001',
            'latitude' => 45.6427,
            'longitude' => 25.5887,
            'phone' => '0700 000 000',
            'email' => 'atelier.demo@example.com',
            'website' => 'https://example.com',
            'specialities' => ['4x4', 'off-road', 'suspensie', 'transmisii 4x4', 'montaj troliu'],
            'amenities' => array_keys(ShopFacilities::AMENITIES),
            'payment_methods' => array_keys(ShopFacilities::PAYMENT_METHODS),
            'certifications' => ['rar_authorised', 'rar_itp', 'ac_certified'],
            'fits_parts_bought_here' => true,
            'accepts_appointments' => true,
            'promotion_tier' => 'none',
            'promoted_until' => null,
        ]);

        foreach (range(1, 7) as $weekday) {
            $shop->hours()->updateOrCreate(['weekday' => $weekday], match (true) {
                $weekday <= 5 => ['is_closed' => false, 'opens_at' => '08:00', 'closes_at' => '18:00'],
                $weekday === 6 => ['is_closed' => false, 'opens_at' => '09:00', 'closes_at' => '14:00'],
                default => ['is_closed' => true, 'opens_at' => null, 'closes_at' => null],
            });
        }

        $services = Service::query()->whereIn('slug', array_keys(self::PRICES))->pluck('id', 'slug');
        $priceList = [];

        foreach (self::PRICES as $slug => [$from, $to, $minutes, $note]) {
            if (isset($services[$slug])) {
                $priceList[$services[$slug]] = [
                    'price_from' => $from,
                    'price_to' => $to,
                    'currency' => (string) config('emud.catalog.default_currency', 'RON'),
                    'duration_minutes' => $minutes,
                    'note' => $note,
                ];
            }
        }

        $shop->services()->sync($priceList);
        $shop->makes()->sync(VehicleMake::query()->whereIn('name', self::MAKES)->pluck('id')->all());

        $this->command?->info('Fișa demo: '.$shop->url().' (ciornă, vizibilă doar pentru administratori)');
    }
}
