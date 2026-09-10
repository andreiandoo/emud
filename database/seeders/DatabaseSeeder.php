<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            AttributeSeeder::class,
            // After the categories: each job links to the parts category it consumes, and the
            // link is looked up by path.
            ServiceCatalogSeeder::class,
            CommerceProviderSeeder::class,
            CatalogSourceSeeder::class,
            SupplierProfileSeeder::class,
            SupplierProspectSeeder::class,
            PageSeeder::class,
            // Last, and a no-op until the vehicle graph is imported: it reads makes and models
            // rather than writing them.
            VehicleCollectionSeeder::class,
        ]);

        if (filled(env('ADMIN_EMAIL')) && filled(env('ADMIN_PASSWORD'))) {
            User::query()->updateOrCreate(
                ['email' => env('ADMIN_EMAIL')],
                ['name' => env('ADMIN_NAME', 'eMUD Admin'), 'password' => env('ADMIN_PASSWORD'), 'role' => 'admin'],
            );
        }
    }
}
