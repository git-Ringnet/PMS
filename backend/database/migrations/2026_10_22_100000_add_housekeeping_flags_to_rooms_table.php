<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->boolean('is_housekeeping_service')->default(false)->after('room_status_code');
            $table->boolean('is_priority_cleaning')->default(false)->after('is_housekeeping_service');
            $table->boolean('is_do_not_disturb')->default(false)->after('is_priority_cleaning');
        });

        // Preserve existing visual indicators without inventing the lifecycle status
        // that historical data may already have overwritten.
        DB::table('rooms')->where('room_status_code', 'housekeeping')->update(['is_housekeeping_service' => true]);
        DB::table('rooms')->where('room_status_code', 'vacant_priority')->update(['is_priority_cleaning' => true]);
        DB::table('rooms')->where('room_status_code', 'dnd')->update(['is_do_not_disturb' => true]);
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn([
                'is_housekeeping_service',
                'is_priority_cleaning',
                'is_do_not_disturb',
            ]);
        });
    }
};
