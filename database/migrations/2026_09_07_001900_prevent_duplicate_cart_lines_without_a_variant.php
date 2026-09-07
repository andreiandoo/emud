<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * cart_items already carries a unique index on (cart_id, product_id, variant_id), but both
 * PostgreSQL and SQLite treat NULLs as distinct inside a unique index. A product added without
 * a variant could therefore be inserted repeatedly, giving the customer several lines for the
 * same thing and a total that counts it more than once.
 */
return new class extends Migration
{
    private const INDEX = 'cart_items_one_line_per_product_without_variant';

    public function up(): void
    {
        // Collapse existing duplicates onto the oldest line so the index can be created. The
        // quantities are summed rather than discarded: they are things a customer chose to buy.
        $duplicates = DB::table('cart_items')
            ->select('cart_id', 'product_id', DB::raw('min(id) as keep_id'), DB::raw('sum(quantity) as total'))
            ->whereNull('variant_id')
            ->groupBy('cart_id', 'product_id')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('cart_items')->where('id', $duplicate->keep_id)->update(['quantity' => $duplicate->total]);
            DB::table('cart_items')
                ->where('cart_id', $duplicate->cart_id)
                ->where('product_id', $duplicate->product_id)
                ->whereNull('variant_id')
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        DB::statement('CREATE UNIQUE INDEX '.self::INDEX.' ON cart_items (cart_id, product_id) WHERE variant_id IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS '.self::INDEX);
    }
};
