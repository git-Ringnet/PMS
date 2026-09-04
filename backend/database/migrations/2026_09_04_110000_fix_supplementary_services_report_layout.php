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

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_supplementary_services');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_supplementary_services(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_service_codes VARCHAR(2000)
)
READS SQL DATA
BEGIN
    SELECT
        ROW_NUMBER() OVER (ORDER BY brs.service_date, COALESCE(brs.service_name, hsrv.name, brs.service_code), br.room_number, b.id, brs.id) AS STT,
        CONCAT(COALESCE(hs.prefix_booking_id, 'GAL'), b.id) AS BookingCode,
        br.room_number AS Room,
        TRIM(CONCAT_WS(' ', NULLIF(g.title, ''), g.full_name)) AS Guest,
        DATE_FORMAT(brs.service_date, '%d/%m/%Y') AS ServiceDate,
        COALESCE(NULLIF(brs.service_name, ''), NULLIF(hsrv.name, ''), brs.service_code) AS ServiceName,
        brs.service_code AS ServiceCode,
        brs.quantity AS Quantity,
        brs.rate AS Rate,
        brs.total_amount AS Total,
        SUM(brs.quantity) OVER (PARTITION BY COALESCE(NULLIF(brs.service_name, ''), NULLIF(hsrv.name, ''), brs.service_code)) AS GroupQuantity,
        SUM(brs.total_amount) OVER (PARTITION BY COALESCE(NULLIF(brs.service_name, ''), NULLIF(hsrv.name, ''), brs.service_code)) AS GroupTotal,
        brs.is_room AS IsRoom,
        brs.is_posted AS IsPosted,
        brs.id AS ServiceId
    FROM booking_room_services AS brs
    INNER JOIN booking_rooms AS br ON br.id = brs.booking_room_id AND br.deleted_at IS NULL
    INNER JOIN bookings AS b ON b.id = br.booking_id AND b.deleted_at IS NULL
    LEFT JOIN booking_room_guests AS brg ON brg.booking_room_id = br.id AND brg.is_primary = 1 AND brg.status <> 3
    LEFT JOIN guests AS g ON g.id = brg.guest_id
    LEFT JOIN hotel_services AS hsrv ON hsrv.code = brs.service_code
    LEFT JOIN hotel_settings AS hs ON hs.id = (SELECT MIN(id) FROM hotel_settings)
    WHERE brs.deleted_at IS NULL
      AND brs.service_code <> 'RM'
      AND brs.service_date >= p_from_date
      AND brs.service_date < DATE_ADD(p_to_date, INTERVAL 1 DAY)
      AND (p_service_codes IS NULL OR p_service_codes = '' OR FIND_IN_SET(brs.service_code, REPLACE(p_service_codes, ' ', '')) > 0)
    ORDER BY brs.service_date, ServiceName, br.room_number, b.id, brs.id;
END
SQL);

        $source = DB::table('report_data_sources')->where('code', 'SUPPLEMENTARY_SERVICES')->first();
        if ($source) {
            $fields = json_decode($source->field_schema ?? '[]', true) ?: [];
            $names = array_column($fields, 'name');
            foreach (['GroupQuantity', 'GroupTotal'] as $name) {
                if (!in_array($name, $names, true)) {
                    $fields[] = ['name' => $name, 'type' => 'number', 'nullable' => true];
                }
            }
            DB::table('report_data_sources')->where('id', $source->id)->update([
                'field_schema' => json_encode($fields, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        }

        DB::table('templates')->where('report', 'SUPPLEMENTARY_SERVICES_STANDARD')->update([
            'page_orientation' => 'portrait',
            'updated_at' => now(),
        ]);
        (require database_path('report_templates/supplementary_services_reference.php'))->apply();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_supplementary_services');
    }
};
