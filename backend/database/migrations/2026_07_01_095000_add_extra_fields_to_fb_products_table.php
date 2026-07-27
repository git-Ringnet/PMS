<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fb_products', function (Blueprint $table) {
            if (!Schema::hasColumn('fb_products', 'product_code')) {
                $table->string('product_code')->nullable()->after('name');
            }
            if (!Schema::hasColumn('fb_products', 'flexible_price')) {
                $table->boolean('flexible_price')->default(false)->after('price');
            }
            if (!Schema::hasColumn('fb_products', 'change_table')) {
                $table->boolean('change_table')->default(false)->after('flexible_price');
            }
            if (!Schema::hasColumn('fb_products', 'open_key')) {
                $table->boolean('open_key')->default(false)->after('change_table');
            }
            if (!Schema::hasColumn('fb_products', 'is_alcohol')) {
                $table->boolean('is_alcohol')->default(false)->after('open_key');
            }
            if (!Schema::hasColumn('fb_products', 'track_stock')) {
                $table->boolean('track_stock')->default(false)->after('is_alcohol');
            }
            if (!Schema::hasColumn('fb_products', 'original_amount')) {
                $table->decimal('original_amount', 15, 2)->nullable()->after('price');
            }
            if (!Schema::hasColumn('fb_products', 'service_charge_percent')) {
                $table->decimal('service_charge_percent', 5, 2)->default(0)->after('original_amount');
            }
            if (!Schema::hasColumn('fb_products', 'service_charge_amount')) {
                $table->decimal('service_charge_amount', 15, 2)->nullable()->after('service_charge_percent');
            }
            if (!Schema::hasColumn('fb_products', 'tax_percent')) {
                $table->decimal('tax_percent', 5, 2)->default(0)->after('service_charge_amount');
            }
            if (!Schema::hasColumn('fb_products', 'tax_amount')) {
                $table->decimal('tax_amount', 15, 2)->nullable()->after('tax_percent');
            }
            if (!Schema::hasColumn('fb_products', 'special_tax_percent')) {
                $table->decimal('special_tax_percent', 5, 2)->default(0)->after('tax_amount');
            }
            if (!Schema::hasColumn('fb_products', 'special_tax_amount')) {
                $table->decimal('special_tax_amount', 15, 2)->nullable()->after('special_tax_percent');
            }
            if (!Schema::hasColumn('fb_products', 'note')) {
                $table->text('note')->nullable()->after('image');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fb_products', function (Blueprint $table) {
            $columnsToDrop = array_filter([
                'product_code', 'flexible_price', 'change_table', 'open_key',
                'is_alcohol', 'track_stock', 'original_amount', 'service_charge_percent',
                'service_charge_amount', 'tax_percent', 'tax_amount', 'special_tax_percent',
                'special_tax_amount', 'note'
            ], fn($col) => Schema::hasColumn('fb_products', $col));

            if (!empty($columnsToDrop)) {
                $table->dropColumn(array_values($columnsToDrop));
            }
        });
    }
};
