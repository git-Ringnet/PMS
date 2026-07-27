<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fb_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('fb_orders', 'customer_email')) {
                $table->string('customer_email')->nullable()->after('customer_phone');
            }
            if (!Schema::hasColumn('fb_orders', 'customer_address')) {
                $table->string('customer_address')->nullable()->after('customer_email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fb_orders', function (Blueprint $table) {
            $columnsToDrop = array_filter([
                'customer_email', 'customer_address'
            ], fn($col) => Schema::hasColumn('fb_orders', $col));

            if (!empty($columnsToDrop)) {
                $table->dropColumn(array_values($columnsToDrop));
            }
        });
    }
};
