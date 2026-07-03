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
            $table->unsignedBigInteger('fb_printer_id')->nullable()->after('is_in_stock');
            
            $table->foreign('fb_printer_id')->references('id')->on('fb_printers')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fb_products', function (Blueprint $table) {
            $table->dropForeign(['fb_printer_id']);
            $table->dropColumn('fb_printer_id');
        });
    }
};
