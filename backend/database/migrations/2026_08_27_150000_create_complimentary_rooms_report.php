<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_complimentary_rooms');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_complimentary_rooms(
    IN p_from_date DATE, IN p_to_date DATE, IN p_room_rate_code VARCHAR(50)
)
READS SQL DATA
BEGIN
    DECLARE v_system_date DATE;
    DECLARE v_tach_foc TINYINT DEFAULT 1;
    SELECT DATE(system_date) INTO v_system_date FROM system_date_rolls ORDER BY id DESC LIMIT 1;
    SET v_system_date = COALESCE(v_system_date, CURRENT_DATE());
    SELECT COALESCE(CAST(value AS UNSIGNED), 1) INTO v_tach_foc
    FROM hotel_configs WHERE name = 'TachFOC' LIMIT 1;

    WITH RECURSIVE dates AS (
        SELECT p_from_date AS stay_date
        UNION ALL
        SELECT DATE_ADD(stay_date, INTERVAL 1 DAY)
        FROM dates
        WHERE stay_date < p_to_date
    ),
    daily_rm AS (
        SELECT sb.RentalRoomId1 AS rental_room_id, DATE(sb.Date) AS stay_date,
               SUM(COALESCE(rnb.rate, sb.Amount, 0)) AS room_rate,
               MAX(NULLIF(rnb.rate_code, '')) AS rate_code,
               MAX(CASE WHEN UPPER(COALESCE(p.payment_method_id, '')) = 'CL'
                          OR COALESCE(pm.is_free, 0) = 1 THEN 1 ELSE 0 END) AS has_free_payment,
               1 AS has_room_night
        FROM service_bills sb
        INNER JOIN room_night_bills rnb ON rnb.bill_id = sb.Ma AND rnb.is_room_night = 1
        LEFT JOIN payments p ON p.id = sb.PaymentId
        LEFT JOIN payment_methods pm ON pm.code = p.payment_method_id
        WHERE sb.ServiceId = 'RM'
          AND sb.Edit = 0
          AND DATE(sb.Date) BETWEEN p_from_date AND p_to_date
        GROUP BY sb.RentalRoomId1, DATE(sb.Date)
    ),
    room_days AS (
        SELECT br.id, d.stay_date,
               CASE WHEN drm.has_room_night = 1 THEN drm.room_rate ELSE COALESCE(br.rate, 0) END AS room_rate,
               UPPER(COALESCE(NULLIF(drm.rate_code, ''), NULLIF(br.rate_code, ''), '')) AS daily_rate_code,
               COALESCE(drm.has_room_night, 0) AS has_room_night,
               COALESCE(drm.has_free_payment, 0) AS has_free_payment
        FROM booking_rooms br
        CROSS JOIN dates d
        LEFT JOIN daily_rm drm ON drm.rental_room_id = br.id AND drm.stay_date = d.stay_date
    ),
    eligible AS (
        SELECT rd.*, br.booking_id, br.room_number, br.arrival_date, br.departure_date,
               br.ActutalNumOfDays, br.is_day_use, br.status, br.rate, br.rate_code,
               b.note, b.company_id
        FROM room_days rd
        INNER JOIN booking_rooms br ON br.id = rd.id
        INNER JOIN bookings b ON b.id = br.booking_id AND b.deleted_at IS NULL
        LEFT JOIN rooms r ON r.room_number = br.room_number
        WHERE br.deleted_at IS NULL
          AND (r.is_internal IS NULL OR r.is_internal = 0)
          AND (br.room_number IS NULL OR br.room_number NOT LIKE '0%')
          AND br.status IN (0, 1, 2, 4, 100)
          AND (br.status <> 100 OR br.ActutalNumOfDays <> 0)
          AND rd.stay_date >= br.arrival_date
          AND rd.stay_date <= DATE_ADD(br.arrival_date, INTERVAL GREATEST(br.ActutalNumOfDays - IF(br.is_day_use = 1, 0, 1), 0) DAY)
          AND (
              (v_tach_foc = 1
               AND (rd.stay_date >= v_system_date OR rd.has_room_night = 1)
               AND (rd.room_rate = 0 OR rd.has_free_payment = 1))
              OR
              (v_tach_foc <> 1 AND (
                  (rd.daily_rate_code LIKE 'FOC%' AND (rd.has_room_night = 0 OR rd.room_rate = 0))
                  OR rd.daily_rate_code LIKE 'HU%'
                  OR (rd.daily_rate_code NOT LIKE 'FOC%'
                      AND rd.daily_rate_code NOT LIKE 'HU%'
                      AND (rd.room_rate = 0 OR rd.has_free_payment = 1))
              ))
          )
    )
    SELECT e.stay_date AS StayDateGroup, e.booking_id AS BookingId, e.id AS RentalRoomId,
           TRIM(CONCAT_WS(' ', NULLIF(g.title, ''), g.full_name)) AS GuestName,
           e.room_number AS Room,
           DATE_FORMAT(e.arrival_date, '%d/%m/%Y') AS ArrivalDate,
           DATE_FORMAT(e.departure_date, '%d/%m/%Y') AS DepartureDate,
           c.name AS Company,
           CASE
               WHEN e.daily_rate_code LIKE 'FOC OWN%' THEN 'FOC OWNER'
               WHEN e.daily_rate_code LIKE 'FOC%' THEN 'FOC'
               WHEN e.daily_rate_code LIKE 'HU%' THEN 'HU'
               ELSE 'Compliment'
           END AS RoomRateCode,
           e.room_rate AS Rate, e.note AS Note, v_tach_foc AS TachFOCMode
    FROM eligible e
    INNER JOIN booking_room_guests brg ON brg.booking_room_id = e.id
        AND brg.is_primary = 1 AND brg.status IN (0, 1, 2, 4, 100)
    INNER JOIN guests g ON g.id = brg.guest_id
    LEFT JOIN companies c ON c.id = e.company_id
    WHERE p_room_rate_code IS NULL OR p_room_rate_code = ''
       OR (UPPER(p_room_rate_code) = 'FOC' AND e.daily_rate_code = 'FOC')
       OR (UPPER(p_room_rate_code) IN ('FOC OWN', 'FOC OWNER') AND e.daily_rate_code LIKE 'FOC OWN%')
       OR (UPPER(p_room_rate_code) = 'HU' AND e.daily_rate_code LIKE 'HU%')
       OR (UPPER(p_room_rate_code) = 'COMPLIMENT'
           AND e.daily_rate_code NOT LIKE 'FOC%' AND e.daily_rate_code NOT LIKE 'HU%')
    ORDER BY e.stay_date, e.booking_id, e.room_number;
END
SQL);
        $now = now(); $db = DB::connection()->getDatabaseName();
        $fields = collect(['StayDateGroup','BookingId','RentalRoomId','GuestName','Room','ArrivalDate','DepartureDate','Company','RoomRateCode','Rate','Note','TachFOCMode'])->map(fn($name) => ['name'=>$name,'type'=>in_array($name,['BookingId','RentalRoomId','TachFOCMode'])?'integer':($name === 'Rate'?'number':'string'),'nullable'=>true])->all();
        DB::table('report_data_sources')->updateOrInsert(['code'=>'COMPLIMENTARY_ROOMS'], ['name'=>'Dữ liệu báo cáo phòng miễn phí','description'=>'MySQL chuyển đổi từ ProVista sp_048.','source_type'=>'procedure','schema_name'=>$db,'object_name'=>'rpt_complimentary_rooms','parameter_schema'=>json_encode([['name'=>'p_from_date','mode'=>'IN','data_type'=>'date','database_type'=>'date','position'=>1,'required'=>true],['name'=>'p_to_date','mode'=>'IN','data_type'=>'date','database_type'=>'date','position'=>2,'required'=>true],['name'=>'p_room_rate_code','mode'=>'IN','data_type'=>'varchar','database_type'=>'varchar(50)','position'=>3,'required'=>false]],JSON_UNESCAPED_UNICODE),'field_schema'=>json_encode($fields,JSON_UNESCAPED_UNICODE),'sample_parameters'=>json_encode(['p_from_date'=>now()->toDateString(),'p_to_date'=>now()->toDateString(),'p_room_rate_code'=>''],JSON_UNESCAPED_UNICODE),'max_rows'=>5000,'is_active'=>true,'last_discovered_at'=>$now,'created_at'=>$now,'updated_at'=>$now]);
        $sid = DB::table('report_data_sources')->where('code','COMPLIMENTARY_ROOMS')->value('id');
        DB::table('templates')->updateOrInsert(['report'=>'COMPLIMENTARY_ROOMS_STANDARD'], ['group'=>'Báo cáo phòng','name'=>'Báo cáo phòng miễn phí - Mẫu tham chiếu legacy','report_data_source_id'=>$sid,'page_size'=>'A4','page_orientation'=>'landscape','margin_top'=>6,'margin_bottom'=>6,'margin_left'=>5,'margin_right'=>5,'content_json'=>'{}','content_html'=>'','css'=>'','version'=>'1.0','created_at'=>$now,'updated_at'=>$now]);
        (require database_path('report_templates/complimentary_rooms_reference.php'))->apply();
        $tid = DB::table('templates')->where('report','COMPLIMENTARY_ROOMS_STANDARD')->value('id');
        DB::table('report_definitions')->updateOrInsert(['code'=>'COMPLIMENTARY_ROOMS'], ['name'=>'Báo cáo phòng miễn phí','group'=>'Báo cáo phòng','description'=>'Phòng có giá phòng miễn phí theo legacy sp_048.','report_data_source_id'=>$sid,'parameter_ui_schema'=>json_encode([['name'=>'p_from_date','label'=>'Ngày','control'=>'date-range','range_end_parameter'=>'p_to_date','default'=>'$today','required'=>true],['name'=>'p_to_date','label'=>'Đến ngày','control'=>'hidden','default'=>'$today','required'=>true],['name'=>'p_room_rate_code','label'=>'Mã giá phòng','control'=>'select','default'=>'','required'=>false,'options'=>[['label'=>'Tất cả','value'=>''],['label'=>'FOC','value'=>'FOC'],['label'=>'HU','value'=>'HU'],['label'=>'FOC OWN','value'=>'FOC OWN'],['label'=>'Compliment','value'=>'Compliment']]]],JSON_UNESCAPED_UNICODE),'sort_order'=>12,'is_active'=>true,'show_in_menu'=>true,'menu_locations'=>json_encode(['reservation','frontdesk']),'menu_top_order'=>20,'menu_group_order'=>10,'menu_item_order'=>30,'created_at'=>$now,'updated_at'=>$now]);
        $rid = DB::table('report_definitions')->where('code','COMPLIMENTARY_ROOMS')->value('id');
        DB::table('report_definition_template')->updateOrInsert(['report_definition_id'=>$rid,'template_id'=>$tid],['is_default'=>true,'sort_order'=>0,'created_at'=>$now,'updated_at'=>$now]);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        $rid=DB::table('report_definitions')->where('code','COMPLIMENTARY_ROOMS')->value('id'); $tid=DB::table('templates')->where('report','COMPLIMENTARY_ROOMS_STANDARD')->value('id'); $sid=DB::table('report_data_sources')->where('code','COMPLIMENTARY_ROOMS')->value('id');
        if($rid){DB::table('report_definition_template')->where('report_definition_id',$rid)->delete();DB::table('report_definitions')->where('id',$rid)->delete();} if($tid)DB::table('templates')->where('id',$tid)->delete(); if($sid)DB::table('report_data_sources')->where('id',$sid)->delete(); DB::unprepared('DROP PROCEDURE IF EXISTS rpt_complimentary_rooms');
    }
};
