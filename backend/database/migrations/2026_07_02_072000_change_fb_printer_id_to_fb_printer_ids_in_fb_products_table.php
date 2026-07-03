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
            // Drop foreign key and column
            $table->dropForeign(['fb_printer_id']);
            $table->dropColumn('fb_printer_id');

            // Add new column to store printer IDs array/JSON
            $table->string('fb_printer_ids')->nullable()->after('is_in_stock');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fb_products', function (Blueprint $table) {
            $table->dropColumn('fb_printer_ids');
            
            $table->unsignedBigInteger('fb_printer_id')->nullable()->after('is_in_stock');
            $table->foreign('fb_printer_id')->references('id')->on('fb_printers')->onDelete('set null');
        });
    }
};
