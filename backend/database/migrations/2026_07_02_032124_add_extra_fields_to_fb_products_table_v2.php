<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fb_products', function (Blueprint $table) {
            if (!Schema::hasColumn('fb_products', 'entrance_ip')) {
                $table->string('entrance_ip')->nullable()->after('is_pre_printed');
            }
            if (!Schema::hasColumn('fb_products', 'entrance_gate_ticket_type')) {
                $table->integer('entrance_gate_ticket_type')->default(0)->after('entrance_ip');
            }
            if (!Schema::hasColumn('fb_products', 'exchange_limit_hours')) {
                $table->integer('exchange_limit_hours')->default(0)->after('entrance_gate_ticket_type');
            }
            if (!Schema::hasColumn('fb_products', 'is_fixed_price')) {
                $table->boolean('is_fixed_price')->default(false)->after('exchange_limit_hours');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fb_products', function (Blueprint $table) {
            $columnsToDrop = array_filter([
                'entrance_ip', 'entrance_gate_ticket_type', 'exchange_limit_hours', 'is_fixed_price'
            ], fn($col) => Schema::hasColumn('fb_products', $col));

            if (!empty($columnsToDrop)) {
                $table->dropColumn(array_values($columnsToDrop));
            }
        });
    }
};
