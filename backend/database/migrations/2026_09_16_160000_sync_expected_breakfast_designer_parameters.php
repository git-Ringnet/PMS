<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TEMPLATES = [
        'EXPECTED_BREAKFAST_ARMY_SUMMARY' => false,
        'EXPECTED_BREAKFAST_DTX_SUMMARY' => false,
        'EXPECTED_BREAKFAST_DETAIL' => true,
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $now = now();

        foreach ([DB::getDefaultConnection()] as $connectionName) {
            $connection = DB::connection($connectionName);
            if ($connection->getDriverName() !== 'mysql') {
                continue;
            }

            foreach (self::TEMPLATES as $code => $showRoomDetails) {
                $connection->table('templates')
                    ->where('report', $code)
                    ->update([
                        'parameter_defaults' => json_encode([
                            'p_from_date' => '$today',
                            'p_to_date' => '$today',
                            'p_show_type' => 1,
                            'p_user' => '',
                            'p_sort_by' => 'Room',
                            'p_sort_type' => 'ASC',
                            'p_late_checkin' => true,
                            'p_show_room_details' => $showRoomDetails,
                            'p_group_by_booking' => false,
                        ], JSON_UNESCAPED_UNICODE),
                        'updated_at' => $now,
                    ]);
            }
        }
    }

    public function down(): void
    {
        // Keep manually adjusted preview defaults when rolling back.
    }
};
