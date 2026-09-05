<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE = 'CHECKED_OUT_GUESTS';
    private const REPORT = 'CHECKED_OUT_GUESTS';
    private const TEMPLATE = 'CHECKED_OUT_GUESTS_STANDARD';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_checked_out_guests');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_checked_out_guests(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_order_by VARCHAR(20),
    IN p_company_id BIGINT,
    IN p_booking_id BIGINT,
    IN p_show_note TINYINT
)
READS SQL DATA
BEGIN
    SELECT
        CONCAT(COALESCE(hs.prefix_booking_id, ''), b.id) AS BookingId,
        br.id AS RentalRoomId,
        brg.guest_id AS CustomerId,
        brg.status AS Status,
        brg.actual_checkout_date AS CheckoutDate,
        brg.actual_checkout_time AS CheckoutTime,
        NULL AS PositionId,
        brg.checkin_by AS UserCheckin,
        brg.checkout_by AS UserCheckout,
        g.title AS Title,
        NULL AS Position,
        TRIM(CONCAT_WS(' ', NULLIF(g.title, ''), g.full_name)) AS Guest,
        CASE WHEN COALESCE(p_show_note, 1) = 1 THEN CASE WHEN br.note IS NOT NULL AND br.note <> '' THEN br.note ELSE g.note END ELSE '' END AS NoteMoi,
        NULL AS Country,
        g.nationality_code AS Nationality,
        g.passport_number AS Passport,
        g.dob AS Birthday,
        g.full_name AS GuestName,
        g.address AS Address,
        g.phone AS Phone,
        NULL AS Fax,
        g.email AS Email,
        g.id_issue_date AS IssueDate,
        g.visa_no AS Visa,
        g.visa_expiry_date AS VisaDate,
        br.arrival_date AS ArrivalDate,
        br.arrival_time AS ArrivalTime,
        br.ActutalNumOfDays AS NumOfDays,
        br.room_number AS Room,
        br.adults AS Adult,
        br.children_qty AS Child,
        br.extra_bed_qty AS ExtraBed,
        br.rate AS Rate,
        br.rate_code AS RoomRateCode,
        br.breakfast AS BreakfastRoom,
        NULL AS BreakfastChild,
        br.RoomKind AS RoomKind,
        rc.code AS RoomType,
        DATE_ADD(DATE(br.arrival_date), INTERVAL COALESCE(br.ActutalNumOfDays, 0) DAY) AS DepartureDate,
        b.booking_name AS BookingName,
        b.contact_name AS Contact,
        c.name AS Company,
        b.company_id AS CompanyId,
        br.status AS GroupStatus,
        r.orders AS Orders,
        NULL AS HouseUse,
        b.note AS NoteDangKy,
        br.move_room AS MoveRoom,
        br.reason AS Description,
        br.created_at AS CreatedDate,
        TIME(br.created_at) AS CreatedHour,
        brg.is_primary AS IsMainGuest,
        NULL AS BabyCotInRoom
    FROM booking_room_guests AS brg
    INNER JOIN booking_rooms AS br ON br.id = brg.booking_room_id AND br.deleted_at IS NULL
    INNER JOIN bookings AS b ON b.id = br.booking_id AND b.deleted_at IS NULL
    INNER JOIN guests AS g ON g.id = brg.guest_id
    LEFT JOIN room_classes AS rc ON rc.id = br.room_class_id
    LEFT JOIN rooms AS r ON r.room_number = br.room_number
    LEFT JOIN companies AS c ON c.id = b.company_id
    LEFT JOIN hotel_settings AS hs ON hs.id = (SELECT MIN(id) FROM hotel_settings)
    WHERE brg.status = 2
      AND brg.actual_checkout_date BETWEEN p_from_date AND p_to_date
      AND (p_company_id IS NULL OR p_company_id = 0 OR b.company_id = p_company_id)
      AND (p_booking_id IS NULL OR p_booking_id = 0 OR b.id = p_booking_id)
      AND COALESCE(r.is_internal, 0) = 0
    ORDER BY
        CASE WHEN p_order_by = 'ArrivalDate' THEN br.arrival_date END DESC,
        CASE WHEN p_order_by = 'Room' THEN CAST(br.room_number AS UNSIGNED) END ASC,
        CASE WHEN p_order_by = 'DepartureDate' THEN DATE_ADD(DATE(br.arrival_date), INTERVAL COALESCE(br.ActutalNumOfDays, 0) DAY) END DESC,
        CASE WHEN p_order_by = 'OpenTimeDi' THEN brg.actual_checkout_time END ASC,
        r.orders, br.id, brg.is_primary DESC, Guest;
END
SQL);

        $now = now();
        $database = DB::connection()->getDatabaseName();
        $parameters = [
            ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 1, 'required' => true],
            ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 2, 'required' => true],
            ['name' => 'p_order_by', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(20)', 'position' => 3, 'required' => true],
            ['name' => 'p_company_id', 'mode' => 'IN', 'data_type' => 'bigint', 'database_type' => 'bigint', 'position' => 4, 'required' => false],
            ['name' => 'p_booking_id', 'mode' => 'IN', 'data_type' => 'bigint', 'database_type' => 'bigint', 'position' => 5, 'required' => false],
            ['name' => 'p_show_note', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 6, 'required' => false],
        ];
        $fields = ['BookingId','RentalRoomId','CustomerId','Status','CheckoutDate','CheckoutTime','PositionId','UserCheckin','UserCheckout','Title','Position','Guest','NoteMoi','Country','Nationality','Passport','Birthday','GuestName','Address','Phone','Fax','Email','IssueDate','Visa','VisaDate','ArrivalDate','ArrivalTime','NumOfDays','Room','Adult','Child','ExtraBed','Rate','RoomRateCode','BreakfastRoom','BreakfastChild','RoomKind','RoomType','DepartureDate','BookingName','Contact','Company','CompanyId','GroupStatus','Orders','HouseUse','NoteDangKy','MoveRoom','Description','CreatedDate','CreatedHour','IsMainGuest','BabyCotInRoom'];
        $numeric = ['RentalRoomId','CustomerId','Status','PositionId','NumOfDays','Adult','Child','ExtraBed','Rate','BreakfastRoom','BreakfastChild','CompanyId','GroupStatus','Orders','HouseUse','IsMainGuest'];
        $fieldSchema = array_map(fn ($name) => ['name' => $name, 'type' => in_array($name, $numeric, true) ? 'number' : 'string', 'nullable' => true], $fields);
        $defaults = ['p_from_date' => now()->toDateString(), 'p_to_date' => now()->toDateString(), 'p_order_by' => 'ArrivalDate', 'p_company_id' => null, 'p_booking_id' => null, 'p_show_note' => 1];
        $ui = [
            ['name'=>'p_from_date','label'=>'Từ ngày','control'=>'date-range','range_end_parameter'=>'p_to_date','default'=>'$today','required'=>true,'options'=>[]],
            ['name'=>'p_to_date','label'=>'Đến ngày','control'=>'hidden','default'=>'$today','required'=>true,'options'=>[]],
            ['name'=>'p_order_by','label'=>'Sắp xếp theo','control'=>'select','default'=>'ArrivalDate','required'=>true,'options'=>[['value'=>'ArrivalDate','label'=>'Ngày đến'],['value'=>'Room','label'=>'Phòng'],['value'=>'DepartureDate','label'=>'Ngày đi'],['value'=>'OpenTimeDi','label'=>'Giờ trả phòng']]],
            ['name'=>'p_company_id','label'=>'Lọc theo công ty','control'=>'select','default'=>'','required'=>false,'options'=>[],'options_source'=>'companies'],
            ['name'=>'p_booking_id','label'=>'Lọc theo đăng ký','control'=>'select','default'=>'','required'=>false,'options'=>[],'options_source'=>'bookings'],
            ['name'=>'p_show_note','label'=>'Hiển thị ghi chú','control'=>'checkbox','default'=>true,'required'=>false,'options'=>[]],
        ];

        DB::table('report_data_sources')->updateOrInsert(['code'=>self::SOURCE], [
            'name'=>'Dữ liệu khách đã trả phòng','description'=>'Danh sách khách status = 2 theo legacy sp_149.','source_type'=>'procedure','schema_name'=>$database,'object_name'=>'rpt_checked_out_guests',
            'parameter_schema'=>json_encode($parameters, JSON_UNESCAPED_UNICODE),'field_schema'=>json_encode($fieldSchema, JSON_UNESCAPED_UNICODE),'sample_parameters'=>json_encode($defaults),
            'max_rows'=>5000,'is_active'=>true,'last_discovered_at'=>$now,'created_at'=>$now,'updated_at'=>$now,
        ]);
        $sourceId = DB::table('report_data_sources')->where('code', self::SOURCE)->value('id');
        DB::table('templates')->updateOrInsert(['report'=>self::TEMPLATE], [
            'group'=>'Báo cáo phòng','name'=>'Danh sách khách đã trả phòng - Mẫu chuẩn','report_data_source_id'=>$sourceId,'parameter_defaults'=>json_encode($defaults),
            'page_size'=>'A4','page_orientation'=>'portrait','margin_top'=>6,'margin_bottom'=>6,'margin_left'=>5,'margin_right'=>5,
            'content_json'=>json_encode(['header'=>[],'detail'=>[],'footer'=>[]]),'content_html'=>'<h1>BÁO CÁO DANH SÁCH KHÁCH ĐÃ TRẢ PHÒNG</h1>','css'=>'','is_default'=>false,'version'=>'1.8','created_at'=>$now,'updated_at'=>$now,
        ]);
        (require database_path('report_templates/checked_out_guests_reference.php'))->apply();
        $templateId = DB::table('templates')->where('report', self::TEMPLATE)->value('id');
        DB::table('report_definitions')->updateOrInsert(['code'=>self::REPORT], [
            'name'=>'Báo cáo danh sách khách đã trả phòng','group'=>'Báo cáo phòng','description'=>'Danh sách khách đã trả phòng theo legacy sp_149.','report_data_source_id'=>$sourceId,
            'parameter_ui_schema'=>json_encode($ui, JSON_UNESCAPED_UNICODE),'sort_order'=>32,'is_active'=>true,'show_in_menu'=>true,'menu_locations'=>json_encode(['reservation','frontdesk']),
            'menu_top_order'=>20,'menu_group_order'=>10,'menu_item_order'=>32,'created_at'=>$now,'updated_at'=>$now,
        ]);
        $reportId = DB::table('report_definitions')->where('code', self::REPORT)->value('id');
        DB::table('report_definition_template')->updateOrInsert(['report_definition_id'=>$reportId,'template_id'=>$templateId], ['is_default'=>true,'sort_order'=>0,'created_at'=>$now,'updated_at'=>$now]);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        $reportId = DB::table('report_definitions')->where('code', self::REPORT)->value('id');
        $templateId = DB::table('templates')->where('report', self::TEMPLATE)->value('id');
        if ($reportId) { DB::table('report_definition_template')->where('report_definition_id',$reportId)->delete(); DB::table('report_definitions')->where('id',$reportId)->delete(); }
        if ($templateId) DB::table('templates')->where('id',$templateId)->delete();
        DB::table('report_data_sources')->where('code',self::SOURCE)->delete();
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_checked_out_guests');
    }
};
