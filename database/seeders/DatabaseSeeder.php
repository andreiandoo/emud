<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([CategorySeeder::class, AttributeSeeder::class, CommerceProviderSeeder::class]);

        if (filled(env('ADMIN_EMAIL')) && filled(env('ADMIN_PASSWORD'))) {
            User::query()->updateOrCreate(
                ['email' => env('ADMIN_EMAIL')],
                ['name' => env('ADMIN_NAME', 'eMUD Admin'), 'password' => env('ADMIN_PASSWORD'), 'role' => 'admin'],
            );
        }
    }
}
