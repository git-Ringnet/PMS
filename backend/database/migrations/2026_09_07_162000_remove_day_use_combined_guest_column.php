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

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_day_use_rooms');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_day_use_rooms(IN p_from_date DATE, IN p_to_date DATE, IN p_user VARCHAR(100), IN p_sort_by VARCHAR(30), IN p_sort_order VARCHAR(4))
READS SQL DATA
BEGIN
    SELECT ROW_NUMBER() OVER (ORDER BY
        CASE WHEN UPPER(COALESCE(p_sort_order,'ASC'))='DESC' AND COALESCE(p_sort_by,'Room')='ArrivalDate' THEN br.arrival_date END DESC,
        CASE WHEN UPPER(COALESCE(p_sort_order,'ASC'))<>'DESC' AND COALESCE(p_sort_by,'Room')='ArrivalDate' THEN br.arrival_date END ASC,
        CASE WHEN UPPER(COALESCE(p_sort_order,'ASC'))='DESC' AND COALESCE(p_sort_by,'Room')='Room' THEN br.room_number END DESC,
        CASE WHEN UPPER(COALESCE(p_sort_order,'ASC'))<>'DESC' AND COALESCE(p_sort_by,'Room')='Room' THEN br.room_number END ASC, br.id) AS STT,
        CONCAT(COALESCE(hs.prefix_booking_id,''),b.id) AS BookingId, c.name AS Company, br.room_number AS Room, rc.code AS RoomType,
        CONCAT(DATE_FORMAT(br.arrival_date,'%d/%m/%Y'),IF(br.arrival_time IS NULL,'',CONCAT(' - ',TIME_FORMAT(br.arrival_time,'%H:%i')))) AS ArrivalDate,
        CONCAT(DATE_FORMAT(br.departure_date,'%d/%m/%Y'),IF(br.departure_time IS NULL,'',CONCAT(' - ',TIME_FORMAT(br.departure_time,'%H:%i')))) AS DepartureDate,
        br.adults AS Adult, br.babies AS Baby, br.children_qty AS Child, br.rate AS Rate, COALESCE(br.note,b.note) AS Note,
        br.id AS RentalRoomId, b.id AS BookingNumericId, br.arrival_date AS ArrivalDateSort, r.orders AS RoomOrder
    FROM booking_rooms br JOIN bookings b ON b.id=br.booking_id AND b.deleted_at IS NULL JOIN room_classes rc ON rc.id=br.room_class_id
    LEFT JOIN rooms r ON r.room_number=br.room_number LEFT JOIN companies c ON c.id=b.company_id LEFT JOIN hotel_settings hs ON 1=1
    WHERE br.deleted_at IS NULL AND br.is_day_use=1 AND br.arrival_date=br.departure_date AND br.status IN (0,1,2) AND b.status IN (0,1,2)
      AND br.arrival_date BETWEEN p_from_date AND p_to_date AND (COALESCE(p_user,'')='' OR br.created_by=p_user)
      AND (br.room_number IS NULL OR br.room_number NOT LIKE '0%') AND COALESCE(r.is_internal,0)=0
    ORDER BY br.arrival_date, br.room_number, br.id;
END
SQL);

        $sourceId = DB::table('report_data_sources')->where('code', 'DAY_USE_ROOMS')->value('id');
        if ($sourceId) {
            $source = DB::table('report_data_sources')->where('id', $sourceId)->first();
            $fields = json_decode($source->field_schema ?? '[]', true) ?: [];
            $fields = array_values(array_filter($fields, static fn (array $field): bool => ($field['name'] ?? '') !== 'AdultBabyChild'));
            DB::table('report_data_sources')->where('id', $sourceId)->update(['field_schema' => json_encode($fields, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
        }

        $template = require database_path('report_templates/day_use_rooms_reference.php');
        DB::table('templates')->where('report', 'DAY_USE_ROOMS_STANDARD')->update([
            'content_json' => json_encode($template->contentJson(), JSON_UNESCAPED_UNICODE),
            'content_html' => $template->html(),
            'css' => $template->css(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Intentionally no-op: the removed report field is not restored.
    }
};
