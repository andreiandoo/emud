<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An invoice made out to a Romanian company names its trade register number next to its tax
 * code. The addresses table already had the company name and the tax code (vat_number); this is
 * the one field it was missing to invoice a firm.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table): void {
            $table->string('trade_register_number', 40)->nullable()->after('vat_number');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table): void {
            $table->dropColumn('trade_register_number');
        });
    }
};
