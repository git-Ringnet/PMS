<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('room_rate_codes', function (Blueprint $table) {
            $table->boolean('IsDaily')->default(false);
        });

        // Update existing rate codes that already have daily mappings to IsDaily = true
        DB::table('room_rate_codes')
            ->whereIn('Ma', function ($query) {
                $query->select('RateCode')
                    ->from('room_rate_daily_mappings')
                    ->distinct();
            })
            ->update(['IsDaily' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('room_rate_codes', function (Blueprint $table) {
            $table->dropColumn('IsDaily');
        });
    }
};
