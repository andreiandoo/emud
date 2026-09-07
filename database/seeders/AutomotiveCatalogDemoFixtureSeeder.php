<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AutomotiveCatalogDemoFixtureSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AutomotiveCatalogDemoSeeder::class,
            AutomotiveCatalogDemoSupplierStateSeeder::class,
        ]);
    }
}
