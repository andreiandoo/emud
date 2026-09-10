<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * A readable address for each car in the garage: /cont/garaj/dacia-duster-2018 instead of
 * /cont/garaj/7.
 *
 * Unique per customer rather than across the shop. Two customers can both own a 2018 Duster,
 * and the page is only ever looked up inside the signed-in customer's own garage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_vehicles', function (Blueprint $table): void {
            $table->string('slug', 120)->nullable()->after('user_id');
            $table->unique(['user_id', 'slug']);
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('customer_vehicles', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'slug']);
            $table->dropColumn('slug');
        });
    }

    /**
     * Written against the tables rather than the model, so this keeps working whatever the model
     * turns into. The rule matches CustomerVehicle::uniqueSlug(): make, model and year, a
     * counter for a second identical car, and never digits alone, which the old numbered
     * address still answers to.
     */
    private function backfill(): void
    {
        $taken = [];

        DB::table('customer_vehicles')
            ->leftJoin('vehicle_makes', 'vehicle_makes.id', '=', 'customer_vehicles.make_id')
            ->leftJoin('vehicle_models', 'vehicle_models.id', '=', 'customer_vehicles.model_id')
            ->select([
                'customer_vehicles.id',
                'customer_vehicles.user_id',
                'customer_vehicles.year',
                'vehicle_makes.name as make_name',
                'vehicle_models.name as model_name',
            ])
            ->orderBy('customer_vehicles.id')
            ->chunk(500, function ($rows) use (&$taken): void {
                foreach ($rows as $row) {
                    $base = Str::slug(trim(implode(' ', array_filter([$row->make_name, $row->model_name, $row->year])))) ?: 'masina';

                    if (ctype_digit($base)) {
                        $base = 'masina-'.$base;
                    }

                    $slug = $base;

                    for ($suffix = 2; isset($taken[$row->user_id.'|'.$slug]); $suffix++) {
                        $slug = $base.'-'.$suffix;
                    }

                    $taken[$row->user_id.'|'.$slug] = true;

                    DB::table('customer_vehicles')->where('id', $row->id)->update(['slug' => $slug]);
                }
            });
    }
};
