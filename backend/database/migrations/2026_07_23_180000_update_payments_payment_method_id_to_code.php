<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Drop foreign key if exists
            try {
                $table->dropForeign(['payment_method_id']);
            } catch (\Throwable $e) {
                // Ignore if foreign key doesn't exist
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            // Change column to string(50) to store code string (CA, AC, BT...)
            $table->string('payment_method_id', 50)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_method_id')->nullable()->change();
        });
    }
};
