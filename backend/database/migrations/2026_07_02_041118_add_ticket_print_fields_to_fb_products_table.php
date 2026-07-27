<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fb_products', function (Blueprint $table) {
            if (!Schema::hasColumn('fb_products', 'is_in_stock')) {
                $table->integer('is_in_stock')->default(1)->after('track_stock');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fb_products', function (Blueprint $table) {
            if (Schema::hasColumn('fb_products', 'is_in_stock')) {
                $table->dropColumn('is_in_stock');
            }
        });
    }
};
