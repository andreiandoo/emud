<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The garage picks the customer's primary vehicle to personalise the storefront, and nothing
 * stopped two rows for the same customer carrying is_primary. Which one won then depended on
 * row order, so the shop could silently switch the vehicle it filtered for.
 */
return new class extends Migration
{
    private const INDEX = 'customer_vehicles_one_primary_per_user';

    public function up(): void
    {
        // Any existing duplicates have to go before a unique index can be created. The oldest
        // row keeps the flag, which matches how the context resolved them until now.
        $duplicates = DB::table('customer_vehicles')
            ->select('user_id', DB::raw('min(id) as keep_id'))
            ->where('is_primary', true)
            ->groupBy('user_id')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('customer_vehicles')
                ->where('user_id', $duplicate->user_id)
                ->where('is_primary', true)
                ->where('id', '!=', $duplicate->keep_id)
                ->update(['is_primary' => false]);
        }

        // A partial unique index rather than a plain one: only the primary rows must be unique
        // per customer, while any number of non-primary vehicles stays allowed.
        DB::statement('CREATE UNIQUE INDEX '.self::INDEX.' ON customer_vehicles (user_id) WHERE is_primary');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS '.self::INDEX);
    }
};
