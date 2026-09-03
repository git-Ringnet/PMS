<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_room_moves');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_room_moves(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_user VARCHAR(100),
    IN p_sort_by VARCHAR(20),
    IN p_order_type VARCHAR(20)
)
READS SQL DATA
BEGIN
    SELECT
        ROW_NUMBER() OVER (ORDER BY
            CASE WHEN p_sort_by IN ('Room', 'Room1') AND p_order_type = 'ASC' THEN CAST(NULLIF(old_br.room_number, '') AS UNSIGNED) END ASC,
            CASE WHEN p_sort_by IN ('Room', 'Room1') AND p_order_type = 'DESC' THEN CAST(NULLIF(old_br.room_number, '') AS UNSIGNED) END DESC,
            CASE WHEN p_sort_by = 'ArrivalDate' AND p_order_type = 'ASC' THEN old_br.actual_arrival_date END ASC,
            CASE WHEN p_sort_by = 'ArrivalDate' AND p_order_type = 'DESC' THEN old_br.actual_arrival_date END DESC,
            CASE WHEN p_sort_by = 'ArrivalDate1' AND p_order_type = 'ASC' THEN brg.actual_arrival_date END ASC,
            CASE WHEN p_sort_by = 'ArrivalDate1' AND p_order_type = 'DESC' THEN brg.actual_arrival_date END DESC,
            old_br.id
        ) AS STT,
        CONCAT(COALESCE(hs.prefix_booking_id, 'GAL'), old_br.booking_id) AS BookingCode,
        old_br.booking_id AS BookingId,
        new_br.booking_id AS BookingCode1,
        new_br.booking_id AS BookingId1,
        old_br.id AS RentalRoomId,
        new_br.id AS RentalRoomId1,
        DATE_FORMAT(old_br.actual_arrival_date, '%d-%m-%Y') AS ArrivalDate,
        old_br.room_number AS Room,
        old_rc.code AS RoomTypeCode,
        old_rc.name AS RoomType,
        old_br.rate AS Rate,
        old_br.move_room AS MoveRoom,
        DATE_FORMAT(brg.actual_arrival_date, '%d-%m-%Y') AS ArrivalDate1,
        new_br.room_number AS Room1,
        new_rc.code AS RoomTypeCode1,
        new_rc.name AS RoomType1,
        new_br.rate AS Rate1,
        new_br.check_in_user AS Username,
        TRIM(CONCAT_WS(' ', NULLIF(g.title, ''), g.full_name)) AS Guest,
        old_br.reason AS Reason,
        old_br.departure_date AS OldDepartureDate
    FROM booking_rooms AS old_br
    INNER JOIN booking_rooms AS new_br ON new_br.id = old_br.move_room
    INNER JOIN (
        SELECT booking_room_id, MIN(guest_id) AS guest_id, actual_arrival_date
        FROM booking_room_guests
        GROUP BY booking_room_id, actual_arrival_date
    ) AS brg ON brg.booking_room_id = new_br.id
    INNER JOIN guests AS g ON g.id = brg.guest_id
    INNER JOIN room_classes AS old_rc ON old_rc.id = old_br.room_class_id
    INNER JOIN room_classes AS new_rc ON new_rc.id = new_br.room_class_id
    LEFT JOIN hotel_settings AS hs ON hs.id = (SELECT MIN(id) FROM hotel_settings)
    WHERE old_br.status = 100
      AND brg.actual_arrival_date >= p_from_date
      AND brg.actual_arrival_date < DATE_ADD(p_to_date, INTERVAL 1 DAY)
      AND (p_user IS NULL OR p_user = '' OR new_br.check_in_user LIKE CONCAT('%', p_user, '%'))
    ORDER BY
        CASE WHEN p_sort_by IN ('Room', 'Room1') AND p_order_type = 'ASC' THEN CAST(NULLIF(old_br.room_number, '') AS UNSIGNED) END ASC,
        CASE WHEN p_sort_by IN ('Room', 'Room1') AND p_order_type = 'DESC' THEN CAST(NULLIF(old_br.room_number, '') AS UNSIGNED) END DESC,
        CASE WHEN p_sort_by = 'ArrivalDate' AND p_order_type = 'ASC' THEN old_br.actual_arrival_date END ASC,
        CASE WHEN p_sort_by = 'ArrivalDate' AND p_order_type = 'DESC' THEN old_br.actual_arrival_date END DESC,
        CASE WHEN p_sort_by = 'ArrivalDate1' AND p_order_type = 'ASC' THEN brg.actual_arrival_date END ASC,
        CASE WHEN p_sort_by = 'ArrivalDate1' AND p_order_type = 'DESC' THEN brg.actual_arrival_date END DESC,
        old_br.id;
END
SQL);

        $report = DB::table('report_definitions')->where('code', 'ROOM_MOVES')->first();
        if ($report) {
            $schema = json_decode($report->parameter_ui_schema ?? '[]', true) ?: [];
            foreach ($schema as &$parameter) {
                if (($parameter['name'] ?? '') !== 'p_user') continue;

                $parameter['control'] = 'select';
                $parameter['options_source'] = 'users';
                $parameter['options'] = [];
                $parameter['default'] = '';
                $parameter['required'] = false;
            }
            unset($parameter);

            DB::table('report_definitions')->where('id', $report->id)->update([
                'parameter_ui_schema' => json_encode($schema, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        }

        (require database_path('report_templates/room_moves_reference.php'))->apply();
    }

    public function down(): void
    {
        // Không khôi phục procedure và template chưa khớp hành vi legacy.
    }
};
