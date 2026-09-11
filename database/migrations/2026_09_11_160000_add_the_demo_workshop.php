<?php

use App\Models\ServiceShop;
use Database\Seeders\DemoServiceShopSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * The demo workshop, put in by the deploy rather than by a command someone has to remember: a
 * draft listing with every field filled in, to design the workshop page against. Staff open it
 * at /service-auto/brasov/atelier-demo-emud; nobody else can see it.
 *
 * Only a directory that already has workshops gets one. A fresh database — a new install, the
 * test suite — starts empty, and a demo there would be a row nobody asked for.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! ServiceShop::withTrashed()->exists()) {
            return;
        }

        (new DemoServiceShopSeeder)->run();
    }

    public function down(): void
    {
        // Left in place: it may have been edited since, and it is only ever a draft. Delete it in
        // the back office if it is no longer wanted.
    }
};
