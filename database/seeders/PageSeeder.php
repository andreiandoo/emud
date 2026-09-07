<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Creates the informational and legal pages a Romanian shop is expected to publish.
 *
 * Only the structure is seeded. The text is deliberately left empty and every page starts as a
 * draft: legal wording is a commitment the business makes to its customers and to the ANPC, and
 * inventing placeholder terms that could go live by accident would be worse than having none.
 */
class PageSeeder extends Seeder
{
    /** @var list<array{slug: string, title: string, excerpt: string, position: int}> */
    private const PAGES = [
        ['slug' => 'despre-noi', 'title' => 'Despre noi', 'excerpt' => 'Cine suntem și ce vindem.', 'position' => 10],
        ['slug' => 'contact', 'title' => 'Contact', 'excerpt' => 'Cum ne poți contacta.', 'position' => 20],
        ['slug' => 'livrare', 'title' => 'Livrare', 'excerpt' => 'Termene, costuri și curieri.', 'position' => 30],
        ['slug' => 'plata', 'title' => 'Modalități de plată', 'excerpt' => 'Cum poți plăti comanda.', 'position' => 40],
        ['slug' => 'retur', 'title' => 'Politica de retur', 'excerpt' => 'Dreptul de retragere și procedura de retur.', 'position' => 50],
        ['slug' => 'garantii', 'title' => 'Garanții', 'excerpt' => 'Garanția produselor și procedura de service.', 'position' => 60],
        ['slug' => 'termeni-si-conditii', 'title' => 'Termeni și condiții', 'excerpt' => 'Condițiile de utilizare a magazinului.', 'position' => 70],
        ['slug' => 'confidentialitate', 'title' => 'Politica de confidențialitate', 'excerpt' => 'Cum prelucrăm datele personale.', 'position' => 80],
        ['slug' => 'cookies', 'title' => 'Politica de cookies', 'excerpt' => 'Ce cookie-uri folosim și de ce.', 'position' => 90],
        ['slug' => 'anpc', 'title' => 'ANPC și SOL', 'excerpt' => 'Soluționarea alternativă a litigiilor.', 'position' => 100],
    ];

    public function run(): void
    {
        foreach (self::PAGES as $page) {
            // Only the slug is matched, and existing rows keep their content and status: a
            // deployment must never revert wording the owner has published.
            Page::query()->firstOrCreate(
                ['slug' => $page['slug']],
                [
                    'title' => $page['title'],
                    'excerpt' => $page['excerpt'],
                    'content' => null,
                    'status' => 'draft',
                    'show_in_footer' => true,
                    'position' => $page['position'],
                ],
            );
        }
    }
}
