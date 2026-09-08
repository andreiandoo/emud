<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contact messages are stored as well as emailed. A mail server that is down or a message
 * filtered as spam would otherwise mean a customer's question is simply lost with no record
 * that it was ever asked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 32)->nullable();
            $table->string('subject')->nullable();
            $table->text('message');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 24)->default('new')->index();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
