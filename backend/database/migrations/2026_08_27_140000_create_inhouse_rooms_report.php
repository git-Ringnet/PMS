<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE = 'INHOUSE_ROOMS';
    private const REPORT = 'INHOUSE_ROOMS';
    private const TEMPLATE = 'INHOUSE_ROOMS_STANDARD';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_inhouse_rooms');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_inhouse_rooms(
    IN p_from_date DATE, IN p_to_date DATE, IN p_actual TINYINT,
    IN p_room_class_id BIGINT, IN p_registration_status_id BIGINT,
    IN p_area VARCHAR(100), IN p_company_id BIGINT, IN p_booking_id BIGINT,
    IN p_show_main_guest TINYINT, IN p_show_detail TINYINT,
    IN p_show_room_rate TINYINT, IN p_vat TINYINT, IN p_no_vat TINYINT
)
READS SQL DATA
BEGIN
    DECLARE v_system_date DATE;
    SELECT DATE(system_date) INTO v_system_date FROM system_date_rolls ORDER BY id DESC LIMIT 1;
    SET v_system_date = COALESCE(v_system_date, CURRENT_DATE());

    WITH RECURSIVE report_dates AS (
        SELECT p_from_date AS stay_date
        UNION ALL
        SELECT DATE_ADD(stay_date, INTERVAL 1 DAY)
        FROM report_dates
        WHERE stay_date < p_to_date
    ),
    room_days AS (
        SELECT br.id, d.stay_date,
               COALESCE((
                   SELECT rnb.rate
                   FROM service_bills sb
                   INNER JOIN room_night_bills rnb ON rnb.bill_id = sb.Ma AND rnb.is_room_night = 1
                   WHERE sb.RentalRoomId1 = br.id
                     AND DATE(sb.Date) = d.stay_date
                     AND sb.ServiceId = 'RM'
                     AND sb.Edit = 0
                   ORDER BY sb.Ma DESC
                   LIMIT 1
               ), br.rate) AS night_rate,
               EXISTS (
                   SELECT 1
                   FROM service_bills sb
                   INNER JOIN room_night_bills rnb ON rnb.bill_id = sb.Ma AND rnb.is_room_night = 1
                   WHERE sb.RentalRoomId1 = br.id
                     AND DATE(sb.Date) = d.stay_date
                     AND sb.ServiceId = 'RM'
                     AND sb.Edit = 0
               ) AS has_room_night
        FROM booking_rooms br
        CROSS JOIN report_dates d
    ),
    eligible AS (
        SELECT br.id, rd.stay_date, rd.night_rate, br.booking_id, br.room_number, br.room_class_id,
               br.original_room_class_id, br.arrival_date, br.departure_date, br.CheckoutDate,
               br.arrival_time, br.departure_time, br.ActutalNumOfDays, br.status AS room_status,
               br.rate, br.adults, br.babies, br.children_qty, br.extra_bed_qty, br.breakfast,
               br.note AS room_note, b.booking_name, b.note AS booking_note, b.special_requests,
               b.company_id, b.registration_status_id, b.has_vat, b.is_day_use,
               c.name AS company_name, rs.name AS registration_status_name,
               rc.code AS room_type_code, rc.name AS room_type_name, orc.name AS original_room_type_name,
               r.area, r.orders AS room_order, r.is_internal,
               CASE
                   WHEN br.status = 4 AND rd.has_room_night = 1 THEN 'X'
                   WHEN br.status = 1 AND rd.stay_date > br.arrival_date + INTERVAL GREATEST(br.ActutalNumOfDays - 1, 0) DAY THEN 'X'
                   ELSE NULL
               END AS no_show_late
        FROM booking_rooms br
        INNER JOIN room_days rd ON rd.id = br.id
        INNER JOIN bookings b ON b.id = br.booking_id AND b.deleted_at IS NULL
        INNER JOIN room_classes rc ON rc.id = br.room_class_id
        LEFT JOIN room_classes orc ON orc.id = CAST(SUBSTRING_INDEX(br.original_room_class_id, '-', 1) AS UNSIGNED)
        LEFT JOIN rooms r ON r.room_number = br.room_number
        LEFT JOIN companies c ON c.id = b.company_id
        LEFT JOIN registration_statuses rs ON rs.id = b.registration_status_id
        WHERE br.deleted_at IS NULL
          AND (r.is_internal IS NULL OR r.is_internal = 0)
          AND (br.room_number IS NULL OR br.room_number NOT LIKE '0%')
          AND (rs.id IS NULL OR rs.is_availability = 1)
          AND (p_area IS NULL OR p_area = '' OR r.area = p_area)
          AND (p_company_id IS NULL OR b.company_id = p_company_id)
          AND (p_booking_id IS NULL OR b.id = p_booking_id)
          AND (p_room_class_id IS NULL OR br.room_class_id = p_room_class_id)
          AND (p_registration_status_id IS NULL OR b.registration_status_id = p_registration_status_id)
          AND ((p_vat = 1 AND p_no_vat = 0 AND b.has_vat = 1)
            OR (p_vat = 0 AND p_no_vat = 1 AND b.has_vat = 0)
            OR (p_vat = 0 AND p_no_vat = 0))
          AND (
             (p_actual = 1 AND rd.stay_date = v_system_date AND br.status = 1
                AND (br.departure_date >= v_system_date OR br.is_day_use = 1 OR b.is_day_use = 1))
             OR (p_actual = 0 AND rd.stay_date = v_system_date
                AND ((br.status = 1 AND br.departure_date > v_system_date)
                  OR (br.status = 0 AND br.arrival_date = v_system_date)))
             OR (rd.stay_date <> v_system_date AND rd.stay_date >= br.arrival_date
                AND rd.stay_date < br.departure_date AND rd.has_room_night = 1)
             OR (br.status = 4 AND rd.has_room_night = 1)
          )
    ),
    result AS (
        SELECT e.stay_date AS StayDateGroup,
               CONCAT(e.booking_id, ' - ', e.booking_name) AS Booking,
               e.booking_id AS BookingId, e.booking_id AS RegisterId, e.id AS RentalRoomId,
               CASE WHEN g.is_primary = 1 THEN COALESCE(e.room_number, '') ELSE NULL END AS Room,
               CASE WHEN g.is_primary = 1 THEN e.room_type_code ELSE NULL END AS RoomTypeCode,
               CASE WHEN g.is_primary = 1 THEN e.room_type_name ELSE NULL END AS RoomType,
               CASE WHEN g.is_primary = 1 THEN e.original_room_type_name ELSE NULL END AS RoomTypeOriginal,
               TRIM(CONCAT_WS(' ', NULLIF(gu.title, ''), gu.full_name)) AS GuestName,
               CONCAT(DATE_FORMAT(e.arrival_date, '%d/%m/%Y'), IF(e.arrival_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(e.arrival_time, '%H:%i')))) AS ArrivalDate,
               CONCAT(DATE_FORMAT(e.departure_date, '%d/%m/%Y'), IF(e.departure_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(e.departure_time, '%H:%i')))) AS DepartureDate,
               DATE_FORMAT(e.CheckoutDate, '%d/%m/%Y') AS ActualDepartureDate,
               CASE WHEN g.is_primary = 1 THEN e.extra_bed_qty ELSE NULL END AS ExtraBed,
               CASE WHEN g.is_primary = 1 THEN e.ActutalNumOfDays ELSE NULL END AS RoomNight,
               CASE WHEN g.is_primary = 1 THEN e.adults ELSE NULL END AS Adult,
               CASE WHEN g.is_primary = 1 THEN e.babies ELSE NULL END AS Infant,
               CASE WHEN g.is_primary = 1 THEN e.children_qty ELSE NULL END AS Child,
               CASE WHEN g.is_primary = 1 THEN CONCAT(e.adults, ' / ', e.babies, ' / ', e.children_qty) ELSE NULL END AS AdultChild,
               CASE WHEN g.is_primary = 1 AND p_show_room_rate = 1 THEN e.night_rate ELSE NULL END AS Rate,
               CASE WHEN p_show_detail = 1 THEN e.no_show_late ELSE NULL END AS NoShowLate,
               CASE WHEN g.is_primary = 1 THEN e.special_requests ELSE NULL END AS Special,
               COALESCE(e.room_note, e.booking_note) AS Note, e.company_id AS CompanyId,
               e.company_name AS Company, e.registration_status_name AS BookingStatusName,
               g.is_primary AS IsMainGuest, e.room_type_code AS SummaryRoomTypeCode,
               e.room_order AS RoomOrder
        FROM eligible e
        INNER JOIN booking_room_guests g ON g.booking_room_id = e.id AND g.status IN (0, 1, 2, 4, 100)
        INNER JOIN guests gu ON gu.id = g.guest_id
        WHERE (COALESCE(p_show_main_guest, 1) = 0 OR g.is_primary = 1)

        UNION ALL

        SELECT e.stay_date, CONCAT(e.booking_id, ' - ', e.booking_name), e.booking_id, e.booking_id, e.id,
               NULL, NULL, NULL, NULL, TRIM(CONCAT_WS(' ', NULLIF(ch.title, ''), ch.full_name)),
               CONCAT(DATE_FORMAT(e.arrival_date, '%d/%m/%Y'), IF(e.arrival_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(e.arrival_time, '%H:%i')))),
               CONCAT(DATE_FORMAT(e.departure_date, '%d/%m/%Y'), IF(e.departure_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(e.departure_time, '%H:%i')))),
               NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
               e.company_id, e.company_name, NULL, -1, e.room_type_code, e.room_order
        FROM eligible e
        INNER JOIN booking_room_children brc ON brc.booking_room_id = e.id AND brc.status IN (0, 1, 2, 4, 100)
        INNER JOIN booking_children ch ON ch.id = brc.booking_child_id
        WHERE COALESCE(p_show_main_guest, 1) = 0
    ),
    room_type_totals AS (
        SELECT SummaryRoomTypeCode, COUNT(DISTINCT RentalRoomId) AS room_count
        FROM result
        WHERE IsMainGuest = 1
        GROUP BY SummaryRoomTypeCode
    ),
    room_total AS (
        SELECT COUNT(DISTINCT RentalRoomId) AS room_count
        FROM result
        WHERE IsMainGuest = 1
    )
    SELECT result.*,
           ROUND(COALESCE(room_type_totals.room_count * 100 / NULLIF(room_total.room_count, 0), 0), 2) AS RoomTypePercent
    FROM result
    LEFT JOIN room_type_totals ON room_type_totals.SummaryRoomTypeCode = result.SummaryRoomTypeCode
    CROSS JOIN room_total
    ORDER BY result.RoomOrder, result.RentalRoomId, result.IsMainGuest DESC, result.GuestName;
END
SQL);
        $this->seedConfiguration();
        (require database_path('report_templates/inhouse_rooms_reference.php'))->apply();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        $rid = DB::table('report_definitions')->where('code', self::REPORT)->value('id');
        $tid = DB::table('templates')->where('report', self::TEMPLATE)->value('id');
        if ($rid) { DB::table('report_definition_template')->where('report_definition_id', $rid)->delete(); DB::table('report_definitions')->where('id', $rid)->delete(); }
        if ($tid) DB::table('templates')->where('id', $tid)->delete();
        DB::table('report_data_sources')->where('code', self::SOURCE)->delete();
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_inhouse_rooms');
    }

    private function seedConfiguration(): void
    {
        $now = now(); $db = DB::connection()->getDatabaseName();
        $params = collect(['p_from_date','p_to_date','p_actual','p_room_class_id','p_registration_status_id','p_area','p_company_id','p_booking_id','p_show_main_guest','p_show_detail','p_show_room_rate','p_vat','p_no_vat'])->values()->map(fn($n,$i)=>['name'=>$n,'mode'=>'IN','data_type'=>in_array($n,['p_from_date','p_to_date'])?'date':(str_contains($n,'date')?'date':(in_array($n,['p_area'])?'varchar':'tinyint')),'database_type'=>in_array($n,['p_from_date','p_to_date'])?'date':(in_array($n,['p_area'])?'varchar(100)':(str_ends_with($n,'id')?'bigint':'tinyint')),'position'=>$i+1,'required'=>true])->all();
        foreach ([['p_room_class_id','room-classes'],['p_registration_status_id','registration-statuses'],['p_area','areas'],['p_company_id','companies'],['p_booking_id','bookings']] as [$n,$src]) foreach ($params as &$p) if($p['name']===$n){$p['data_type']=str_contains($n,'id')?'bigint':'varchar';$p['database_type']=str_contains($n,'id')?'bigint':'varchar(100)';$p['options_source']=$src;} unset($p);
        $fields = collect(['StayDateGroup','Booking','BookingId','RegisterId','RentalRoomId','Room','RoomTypeCode','RoomType','RoomTypeOriginal','GuestName','ArrivalDate','DepartureDate','ActualDepartureDate','ExtraBed','RoomNight','Adult','Infant','Child','AdultChild','Rate','NoShowLate','Special','Note','CompanyId','Company','BookingStatusName','IsMainGuest','SummaryRoomTypeCode','RoomOrder','RoomTypePercent'])->map(fn($n)=>['name'=>$n,'type'=>in_array($n,['BookingId','RegisterId','ExtraBed','RoomNight','Adult','Infant','Child','CompanyId','IsMainGuest','RoomOrder'])?'integer':(in_array($n,['Rate','RoomTypePercent'])?'number':'string'),'nullable'=>true])->all();
        DB::table('report_data_sources')->updateOrInsert(['code'=>self::SOURCE],['name'=>'Dữ liệu báo cáo phòng ở','description'=>'MySQL chuyển đổi từ ProVista sp_167.','source_type'=>'procedure','schema_name'=>$db,'object_name'=>'rpt_inhouse_rooms','parameter_schema'=>json_encode($params,JSON_UNESCAPED_UNICODE),'field_schema'=>json_encode($fields,JSON_UNESCAPED_UNICODE),'sample_parameters'=>json_encode(['p_from_date'=>now()->toDateString(),'p_to_date'=>now()->toDateString(),'p_actual'=>1,'p_show_main_guest'=>1,'p_show_detail'=>1,'p_show_room_rate'=>1,'p_vat'=>0,'p_no_vat'=>0],JSON_UNESCAPED_UNICODE),'max_rows'=>5000,'is_active'=>true,'last_discovered_at'=>$now,'created_at'=>$now,'updated_at'=>$now]);
        $sid=DB::table('report_data_sources')->where('code',self::SOURCE)->value('id');
        DB::table('templates')->updateOrInsert(['report'=>self::TEMPLATE],['group'=>'Báo cáo phòng','name'=>'Báo cáo phòng ở - Mẫu tham chiếu legacy','report_data_source_id'=>$sid,'parameter_defaults'=>json_encode(['p_from_date'=>now()->toDateString(),'p_to_date'=>now()->toDateString(),'p_actual'=>1,'p_show_main_guest'=>1,'p_show_detail'=>1,'p_show_room_rate'=>1,'p_vat'=>0,'p_no_vat'=>0]),'page_size'=>'A4','page_orientation'=>'landscape','margin_top'=>6,'margin_bottom'=>6,'margin_left'=>5,'margin_right'=>5,'content_json'=>'{}','content_html'=>'','css'=>'','is_default'=>false,'version'=>'1.0','created_at'=>$now,'updated_at'=>$now]);
        $tid=DB::table('templates')->where('report',self::TEMPLATE)->value('id');
        DB::table('report_definitions')->updateOrInsert(['code'=>self::REPORT],['name'=>'Báo cáo phòng ở','group'=>'Báo cáo phòng','description'=>'Danh sách phòng/khách đang ở theo ngày, chuyển đổi từ legacy sp_167.','report_data_source_id'=>$sid,'parameter_ui_schema'=>json_encode($this->ui(),JSON_UNESCAPED_UNICODE),'sort_order'=>11,'is_active'=>true,'show_in_menu'=>true,'menu_locations'=>json_encode(['reservation','frontdesk']),'menu_top_order'=>20,'menu_group_order'=>10,'menu_item_order'=>20,'created_at'=>$now,'updated_at'=>$now]);
        $rid=DB::table('report_definitions')->where('code',self::REPORT)->value('id'); DB::table('report_definition_template')->updateOrInsert(['report_definition_id'=>$rid,'template_id'=>$tid],['is_default'=>true,'sort_order'=>0,'created_at'=>$now,'updated_at'=>$now]);
    }

    private function ui(): array { return [
        ['name'=>'p_from_date','label'=>'Chọn ngày','control'=>'date-range','range_end_parameter'=>'p_to_date','default'=>'$today','required'=>true,'options'=>[]],['name'=>'p_to_date','label'=>'Đến ngày','control'=>'hidden','default'=>'$today','required'=>true,'options'=>[]],
        ['name'=>'p_actual','label'=>'Thực tế','control'=>'checkbox','default'=>true,'required'=>false,'options'=>[]],['name'=>'p_room_class_id','label'=>'Chọn loại phòng','control'=>'select','default'=>'','required'=>false,'options'=>[],'options_source'=>'room-classes'],['name'=>'p_registration_status_id','label'=>'Chọn tình trạng đăng ký','control'=>'select','default'=>'','required'=>false,'options'=>[],'options_source'=>'registration-statuses'],['name'=>'p_area','label'=>'Chọn khu vực','control'=>'select','default'=>'','required'=>false,'options'=>[],'options_source'=>'areas'],['name'=>'p_company_id','label'=>'Chọn công ty','control'=>'select','default'=>'','required'=>false,'options'=>[],'options_source'=>'companies'],['name'=>'p_booking_id','label'=>'Chọn đăng ký','control'=>'select','default'=>'','required'=>false,'options'=>[],'options_source'=>'bookings'],['name'=>'p_show_main_guest','label'=>'Hiển thị khách chính','control'=>'checkbox','default'=>true,'required'=>false,'options'=>[]],['name'=>'p_show_detail','label'=>'Hiển thị chi tiết','control'=>'checkbox','default'=>true,'required'=>false,'options'=>[]],['name'=>'p_show_room_rate','label'=>'Giá phòng','control'=>'checkbox','default'=>true,'required'=>false,'options'=>[]],['name'=>'p_vat','label'=>'VAT','control'=>'checkbox','default'=>false,'required'=>false,'options'=>[]],['name'=>'p_no_vat','label'=>'Không VAT','control'=>'checkbox','default'=>false,'required'=>false,'options'=>[]]
    ]; }
};
