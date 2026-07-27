<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fb_products', function (Blueprint $table) {
            if (!Schema::hasColumn('fb_products', 'is_get_price_from_items')) {
                $table->boolean('is_get_price_from_items')->default(false)->after('is_combo');
            }
            if (!Schema::hasColumn('fb_products', 'is_check_combo')) {
                $table->boolean('is_check_combo')->default(false)->after('is_get_price_from_items');
            }
            if (!Schema::hasColumn('fb_products', 'combo_max_items')) {
                $table->integer('combo_max_items')->nullable()->after('is_check_combo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fb_products', function (Blueprint $table) {
            $columnsToDrop = array_filter([
                'is_get_price_from_items', 'is_check_combo', 'combo_max_items'
            ], fn($col) => Schema::hasColumn('fb_products', $col));

            if (!empty($columnsToDrop)) {
                $table->dropColumn(array_values($columnsToDrop));
            }
        });
    }
};
