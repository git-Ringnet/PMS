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
            $table->integer('is_in_stock')->default(1)->after('track_stock');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fb_products', function (Blueprint $table) {
            $table->dropColumn('is_in_stock');
        });
    }
};
