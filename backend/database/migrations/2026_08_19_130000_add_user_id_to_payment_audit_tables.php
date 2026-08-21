<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'payments',
        'payment_debt_settlements',
        'booking_room_services',
        'service_bills',
        'housekeeping_service_bills',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasColumn($tableName, 'user_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    // Shared User lives in mysql_system; keep a scalar reference in the branch DB.
                    $table->unsignedBigInteger('user_id')->nullable()->index();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $tableName) {
            if (Schema::hasColumn($tableName, 'user_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('user_id');
                });
            }
        }
    }
};
