<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two columns were narrower than what the sources send, and PostgreSQL refuses a value longer
 * than its varchar (SQLite, where the first national run was rehearsed, does not check). An ITP
 * station authorised for all three classes carries "ITP_CLASS_1,ITP_CLASS_2,ITP_CLASS_3", 35
 * characters: 282 stations failed on the first production import. ONRC describes some company
 * states in a sentence of almost 300 characters.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_authorizations', function (Blueprint $table): void {
            $table->string('authorization_class', 191)->nullable()->change();
        });

        Schema::table('workshop_companies', function (Blueprint $table): void {
            $table->text('status')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('workshop_companies', function (Blueprint $table): void {
            $table->string('status', 120)->nullable()->change();
        });

        Schema::table('workshop_authorizations', function (Blueprint $table): void {
            $table->string('authorization_class', 32)->nullable()->change();
        });
    }
};
