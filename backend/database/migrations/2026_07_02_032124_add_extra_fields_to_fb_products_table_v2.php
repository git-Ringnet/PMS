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
            $table->string('entrance_ip')->nullable()->after('is_pre_printed');
            $table->integer('entrance_gate_ticket_type')->default(0)->after('entrance_ip');
            $table->integer('exchange_limit_hours')->default(0)->after('entrance_gate_ticket_type');
            $table->boolean('is_fixed_price')->default(false)->after('exchange_limit_hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fb_products', function (Blueprint $table) {
            $table->dropColumn([
                'entrance_ip',
                'entrance_gate_ticket_type',
                'exchange_limit_hours',
                'is_fixed_price',
            ]);
        });
    }
};
