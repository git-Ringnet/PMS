<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('hotel_services', 'is_active')) {
            Schema::table('hotel_services', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('hotel_services', 'is_active')) {
            Schema::table('hotel_services', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }
};
