<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fb_products', function (Blueprint $table) {
            if (Schema::hasColumn('fb_products', 'fb_printer_id')) {
                $table->dropForeign(['fb_printer_id']);
                $table->dropColumn('fb_printer_id');
            }

            if (!Schema::hasColumn('fb_products', 'fb_printer_ids')) {
                $table->string('fb_printer_ids')->nullable()->after('is_in_stock');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fb_products', function (Blueprint $table) {
            if (Schema::hasColumn('fb_products', 'fb_printer_ids')) {
                $table->dropColumn('fb_printer_ids');
            }
        });
    }
};
