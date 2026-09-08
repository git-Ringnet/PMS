<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_room_status_history');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_room_status_history(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_username VARCHAR(50),
    IN p_room VARCHAR(50)
)
READS SQL DATA
BEGIN
    SELECT
        history.id AS Id,
        DATE_FORMAT(TIMESTAMP(history.business_date, TIME(history.changed_at)), '%d-%m-%Y %H:%i:%s') AS Date,
        history.username AS Username,
        history.room AS Room,
        COALESCE(history.legacy_status_from_code, history.status_from_id) AS StatusFrom,
        COALESCE(history.legacy_status_to_code, history.status_to_id) AS StatusTo,
        REPLACE(status_from.name_en, 'Vacant ', '') AS StatusFromEnglish,
        status_from.name_vi AS StatusFromVietnamese,
        REPLACE(status_to.name_en, 'Vacant ', '') AS StatusToEnglish,
        status_to.name_vi AS StatusToVietnamese
    FROM room_status_change_logs AS history
    LEFT JOIN room_statuses AS status_from ON status_from.id = history.status_from_id
    LEFT JOIN room_statuses AS status_to ON status_to.id = history.status_to_id
    WHERE history.business_date BETWEEN p_from_date AND p_to_date
      AND (p_username IS NULL OR p_username = '' OR history.username = p_username)
      AND (p_room IS NULL OR p_room = '' OR history.room = p_room);
END
SQL);

        (require database_path('report_templates/room_status_history_reference.php'))->apply();
    }

    public function down(): void
    {
        // Keep the approved legacy layout when rolling back later migrations.
    }
};
