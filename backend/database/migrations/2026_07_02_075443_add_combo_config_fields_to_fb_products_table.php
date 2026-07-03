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
        Schema::table('fb_products', function (Blueprint $table) {
            $table->boolean('is_get_price_from_items')->default(false)->after('is_combo');
            $table->boolean('is_check_combo')->default(false)->after('is_get_price_from_items');
            $table->integer('combo_max_items')->nullable()->after('is_check_combo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fb_products', function (Blueprint $table) {
            $table->dropColumn([
                'is_get_price_from_items',
                'is_check_combo',
                'combo_max_items'
            ]);
        });
    }
};
