<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fb_products', function (Blueprint $table) {
            $table->string('product_code')->nullable()->after('name');
            $table->boolean('flexible_price')->default(false)->after('price');
            $table->boolean('change_table')->default(false)->after('flexible_price');
            $table->boolean('open_key')->default(false)->after('change_table');
            $table->boolean('is_alcohol')->default(false)->after('open_key');
            $table->boolean('track_stock')->default(false)->after('is_alcohol');
            $table->decimal('original_amount', 15, 2)->nullable()->after('price');
            $table->decimal('service_charge_percent', 5, 2)->default(0)->after('original_amount');
            $table->decimal('service_charge_amount', 15, 2)->nullable()->after('service_charge_percent');
            $table->decimal('tax_percent', 5, 2)->default(0)->after('service_charge_amount');
            $table->decimal('tax_amount', 15, 2)->nullable()->after('tax_percent');
            $table->decimal('special_tax_percent', 5, 2)->default(0)->after('tax_amount');
            $table->decimal('special_tax_amount', 15, 2)->nullable()->after('special_tax_percent');
            $table->text('note')->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('fb_products', function (Blueprint $table) {
            $table->dropColumn([
                'product_code',
                'flexible_price',
                'change_table',
                'open_key',
                'is_alcohol',
                'track_stock',
                'original_amount',
                'service_charge_percent',
                'service_charge_amount',
                'tax_percent',
                'tax_amount',
                'special_tax_percent',
                'special_tax_amount',
                'note',
            ]);
        });
    }
};
