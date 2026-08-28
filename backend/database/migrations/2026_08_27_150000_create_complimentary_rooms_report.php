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
    SELECT COALESCE(CAST(value AS UNSIGNED), 1) INTO v_tach_foc FROM hotel_configs WHERE name = 'TachFOC' LIMIT 1;
    WITH RECURSIVE dates AS (
        SELECT p_from_date AS stay_date
        UNION ALL SELECT DATE_ADD(stay_date, INTERVAL 1 DAY) FROM dates WHERE stay_date < p_to_date
    ), room_days AS (
        SELECT br.id, d.stay_date, COALESCE(rnb.rate, br.rate, 0) AS room_rate,
               COALESCE(NULLIF(rnb.rate_code, ''), NULLIF(br.rate_code, ''), '') AS daily_rate_code,
               EXISTS (SELECT 1 FROM service_bills sb JOIN room_night_bills rb ON rb.bill_id = sb.Ma
                       WHERE sb.RentalRoomId1 = br.id AND DATE(sb.Date) = d.stay_date AND rb.is_room_night = 1) AS has_room_night
               ,EXISTS (SELECT 1 FROM service_bills sb JOIN payments p ON p.id = sb.PaymentId
                        JOIN payment_methods pm ON pm.code = p.payment_method_id
                        WHERE sb.RentalRoomId1 = br.id AND DATE(sb.Date) = d.stay_date AND sb.Edit = 0
                          AND (UPPER(p.payment_method_id) = 'CL' OR pm.is_free = 1)) AS has_free_payment
        FROM booking_rooms br CROSS JOIN dates d
        LEFT JOIN service_bills sb ON sb.RentalRoomId1 = br.id AND DATE(sb.Date) = d.stay_date AND sb.ServiceId = 'RM' AND sb.Edit = 0
        LEFT JOIN room_night_bills rnb ON rnb.bill_id = sb.Ma AND rnb.is_room_night = 1
    )
    SELECT rd.stay_date AS StayDateGroup, br.booking_id AS BookingId, br.id AS RentalRoomId,
           TRIM(CONCAT_WS(' ', NULLIF(g.title, ''), g.full_name)) AS GuestName, br.room_number AS Room,
           DATE_FORMAT(br.arrival_date, '%d/%m/%Y') AS ArrivalDate,
           DATE_FORMAT(br.departure_date, '%d/%m/%Y') AS DepartureDate,
           c.name AS Company,
           CASE WHEN UPPER(COALESCE(rd.daily_rate_code, br.rate_code, '')) LIKE 'FOC%' THEN UPPER(COALESCE(rd.daily_rate_code, br.rate_code))
                WHEN UPPER(COALESCE(rd.daily_rate_code, br.rate_code, '')) LIKE 'HU%' THEN 'HU'
                ELSE 'Compliment' END AS RoomRateCode,
           rd.room_rate AS Rate, b.note AS Note, v_tach_foc AS TachFOCMode
    FROM room_days rd
    JOIN booking_rooms br ON br.id = rd.id
    JOIN bookings b ON b.id = br.booking_id AND b.deleted_at IS NULL
    JOIN booking_room_guests brg ON brg.booking_room_id = br.id AND brg.is_primary = 1 AND brg.status IN (0,1,2,4,100)
    JOIN guests g ON g.id = brg.guest_id
    LEFT JOIN companies c ON c.id = b.company_id
    LEFT JOIN rooms r ON r.room_number = br.room_number
    WHERE br.deleted_at IS NULL
      AND (r.is_internal IS NULL OR r.is_internal = 0)
      AND (br.room_number IS NULL OR br.room_number NOT LIKE '0%')
      AND br.status IN (0,1,2,4,100) AND (br.status <> 100 OR br.ActutalNumOfDays <> 0)
      AND rd.stay_date >= br.arrival_date
      AND rd.stay_date <= DATE_ADD(br.arrival_date, INTERVAL GREATEST(br.ActutalNumOfDays - IF(br.is_day_use = 1, 0, 1), 0) DAY)
      AND (rd.room_rate = 0 OR rd.has_free_payment = 1 OR UPPER(COALESCE(rd.daily_rate_code, br.rate_code, '')) LIKE 'FOC%'
           OR UPPER(COALESCE(rd.daily_rate_code, br.rate_code, '')) LIKE 'HU%')
      AND (p_room_rate_code IS NULL OR p_room_rate_code = ''
           OR (UPPER(p_room_rate_code) = 'COMPLIMENT' AND UPPER(COALESCE(rd.daily_rate_code, br.rate_code, '')) NOT LIKE 'FOC%' AND UPPER(COALESCE(rd.daily_rate_code, br.rate_code, '')) NOT LIKE 'HU%')
           OR UPPER(COALESCE(rd.daily_rate_code, br.rate_code, '')) = UPPER(p_room_rate_code))
    ORDER BY rd.stay_date, br.booking_id, br.room_number;
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
