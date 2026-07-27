<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fb_products', function (Blueprint $table) {
            if (!Schema::hasColumn('fb_products', 'is_print_one_ticket')) {
                $table->boolean('is_print_one_ticket')->default(false)->after('is_pre_printed');
            }
            if (!Schema::hasColumn('fb_products', 'ticket_type')) {
                $table->string('ticket_type')->nullable()->after('is_print_one_ticket');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fb_products', function (Blueprint $table) {
            $columnsToDrop = array_filter([
                'is_print_one_ticket', 'ticket_type'
            ], fn($col) => Schema::hasColumn('fb_products', $col));

            if (!empty($columnsToDrop)) {
                $table->dropColumn(array_values($columnsToDrop));
            }
        });
    }
};
