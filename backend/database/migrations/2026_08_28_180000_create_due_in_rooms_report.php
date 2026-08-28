<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE = 'DUE_IN_ROOMS';
    private const REPORT = 'DUE_IN_ROOMS';
    private const TEMPLATE = 'DUE_IN_ROOMS_STANDARD';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_due_in_rooms');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_due_in_rooms(
    IN p_from_date DATE, IN p_to_date DATE, IN p_area VARCHAR(100),
    IN p_company_id BIGINT, IN p_booking_id BIGINT, IN p_room_class_id BIGINT,
    IN p_registration_status_id BIGINT, IN p_show_main_guest TINYINT
)
READS SQL DATA
BEGIN
    SELECT
        DATE_FORMAT(br.arrival_date, '%d/%m/%Y') AS ArrivalDateGroup,
        b.id AS BookingId, b.id AS RegisterId, br.id AS RentalRoomId,
        CASE WHEN brg.is_primary = 1 THEN COALESCE(br.room_number, '') ELSE NULL END AS Room,
        CASE WHEN brg.is_primary = 1 THEN rc.code ELSE NULL END AS RoomType,
        TRIM(CONCAT_WS(' ', NULLIF(g.title, ''), g.full_name)) AS GuestName,
        CONCAT(DATE_FORMAT(br.arrival_date, '%d/%m/%Y'), IF(br.arrival_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(br.arrival_time, '%H:%i')))) AS ArrivalDate,
        CONCAT(DATE_FORMAT(br.departure_date, '%d/%m/%Y'), IF(br.departure_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(br.departure_time, '%H:%i')))) AS DepartureDate,
        CASE WHEN brg.is_primary = 1 THEN br.ActutalNumOfDays ELSE NULL END AS RoomNight,
        CASE WHEN brg.is_primary = 1 THEN br.extra_bed_qty ELSE NULL END AS ExtraBed,
        CASE WHEN brg.is_primary = 1 THEN CONCAT(br.adults, ' / ', br.children_qty) ELSE NULL END AS AdultChild,
        b.company_id AS CompanyId, c.name AS Company, b.booking_name AS BookingName,
        rs.name AS BookingStatusName, COALESCE(br.note, b.note) AS Note,
        brg.is_primary AS IsMainGuest, r.orders AS RoomOrder
    FROM booking_rooms br
    INNER JOIN bookings b ON b.id = br.booking_id AND b.deleted_at IS NULL
    INNER JOIN booking_room_guests brg ON brg.booking_room_id = br.id AND brg.status IN (0, 1, 2, 4, 100)
    INNER JOIN guests g ON g.id = brg.guest_id
    INNER JOIN room_classes rc ON rc.id = br.room_class_id
    LEFT JOIN rooms r ON r.room_number = br.room_number
    LEFT JOIN companies c ON c.id = b.company_id
    LEFT JOIN registration_statuses rs ON rs.id = b.registration_status_id
    WHERE br.deleted_at IS NULL
      AND br.status IN (0, 1, 2, 100) AND b.status IN (0, 1, 2)
      AND (rs.id IS NULL OR rs.is_availability = 1)
      AND br.arrival_date BETWEEN p_from_date AND p_to_date
      AND (COALESCE(p_show_main_guest, 1) = 0 OR brg.is_primary = 1)
      AND (p_area IS NULL OR p_area = '' OR r.area = p_area)
      AND (p_company_id IS NULL OR b.company_id = p_company_id)
      AND (p_booking_id IS NULL OR b.id = p_booking_id)
      AND (p_room_class_id IS NULL OR br.room_class_id = p_room_class_id)
      AND (p_registration_status_id IS NULL OR b.registration_status_id = p_registration_status_id)
      AND COALESCE(r.is_internal, 0) = 0
      AND (br.room_number IS NULL OR br.room_number NOT LIKE '0%')
    ORDER BY br.arrival_date, b.id, r.orders, br.id, brg.is_primary DESC, GuestName;
END
SQL);

        $now = now();
        $db = DB::connection()->getDatabaseName();
        $params = collect([
            ['p_from_date', 'date', 'date'], ['p_to_date', 'date', 'date'], ['p_area', 'varchar', 'varchar(100)'],
            ['p_company_id', 'bigint', 'bigint'], ['p_booking_id', 'bigint', 'bigint'], ['p_room_class_id', 'bigint', 'bigint'],
            ['p_registration_status_id', 'bigint', 'bigint'], ['p_show_main_guest', 'tinyint', 'tinyint'],
        ])->values()->map(fn ($p, $i) => ['name' => $p[0], 'mode' => 'IN', 'data_type' => $p[1], 'database_type' => $p[2], 'position' => $i + 1, 'required' => true])->all();
        $fields = collect(['ArrivalDateGroup','BookingId','RegisterId','RentalRoomId','Room','RoomType','GuestName','ArrivalDate','DepartureDate','RoomNight','ExtraBed','AdultChild','CompanyId','Company','BookingName','BookingStatusName','Note','IsMainGuest','RoomOrder'])
            ->map(fn ($name) => ['name' => $name, 'type' => in_array($name, ['BookingId','RegisterId','RentalRoomId','RoomNight','ExtraBed','CompanyId','IsMainGuest','RoomOrder'], true) ? 'integer' : 'string', 'nullable' => true])->all();
        $ui = [
            ['name'=>'p_from_date','label'=>'Chọn ngày','control'=>'date-range','range_end_parameter'=>'p_to_date','default'=>'$today','required'=>true],
            ['name'=>'p_to_date','label'=>'Đến ngày','control'=>'hidden','default'=>'$today','required'=>true],
            ['name'=>'p_room_class_id','label'=>'Chọn loại phòng','control'=>'select','default'=>'','required'=>false,'options_source'=>'room-classes'],
            ['name'=>'p_registration_status_id','label'=>'Chọn tình trạng đăng ký','control'=>'select','default'=>'','required'=>false,'options_source'=>'registration-statuses'],
            ['name'=>'p_area','label'=>'Chọn khu vực','control'=>'select','default'=>'','required'=>false,'options_source'=>'areas'],
            ['name'=>'p_company_id','label'=>'Chọn công ty','control'=>'select','default'=>'','required'=>false,'options_source'=>'companies'],
            ['name'=>'p_booking_id','label'=>'Chọn đăng ký','control'=>'select','default'=>'','required'=>false,'options_source'=>'bookings'],
            ['name'=>'p_show_main_guest','label'=>'Chỉ hiển thị khách chính','control'=>'checkbox','default'=>true,'required'=>false],
        ];
        DB::table('report_data_sources')->updateOrInsert(['code'=>self::SOURCE], ['name'=>'Dữ liệu báo cáo phòng Due In','description'=>'MySQL chuyển đổi từ ProVista sp_006.','source_type'=>'procedure','schema_name'=>$db,'object_name'=>'rpt_due_in_rooms','parameter_schema'=>json_encode($params),'field_schema'=>json_encode($fields),'sample_parameters'=>json_encode(['p_from_date'=>now()->toDateString(),'p_to_date'=>now()->toDateString(),'p_show_main_guest'=>1]),'max_rows'=>5000,'is_active'=>true,'last_discovered_at'=>$now,'created_at'=>$now,'updated_at'=>$now]);
        $sid = DB::table('report_data_sources')->where('code', self::SOURCE)->value('id');
        DB::table('templates')->updateOrInsert(['report'=>self::TEMPLATE], ['group'=>'Báo cáo phòng','name'=>'Danh sách phòng Due In - Mẫu tham chiếu legacy','report_data_source_id'=>$sid,'page_size'=>'A4','page_orientation'=>'landscape','margin_top'=>6,'margin_bottom'=>6,'margin_left'=>5,'margin_right'=>5,'content_json'=>'{}','content_html'=>$this->html(),'css'=>$this->css(),'version'=>'1.0','created_at'=>$now,'updated_at'=>$now]);
        (require database_path('report_templates/due_in_rooms_reference.php'))->apply();
        $tid = DB::table('templates')->where('report', self::TEMPLATE)->value('id');
        DB::table('report_definitions')->updateOrInsert(['code'=>self::REPORT], ['name'=>'Danh sách phòng Due In','group'=>'Báo cáo phòng','description'=>'Danh sách phòng có khách đến theo legacy sp_006.','report_data_source_id'=>$sid,'parameter_ui_schema'=>json_encode($ui, JSON_UNESCAPED_UNICODE),'sort_order'=>21,'is_active'=>true,'show_in_menu'=>true,'menu_locations'=>json_encode(['reservation','frontdesk']),'menu_top_order'=>20,'menu_group_order'=>10,'menu_item_order'=>21,'created_at'=>$now,'updated_at'=>$now]);
        $rid = DB::table('report_definitions')->where('code', self::REPORT)->value('id');
        DB::table('report_definition_template')->updateOrInsert(['report_definition_id'=>$rid,'template_id'=>$tid], ['is_default'=>true,'sort_order'=>0,'created_at'=>$now,'updated_at'=>$now]);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        $rid = DB::table('report_definitions')->where('code', self::REPORT)->value('id');
        $tid = DB::table('templates')->where('report', self::TEMPLATE)->value('id');
        if ($rid) { DB::table('report_definition_template')->where('report_definition_id',$rid)->delete(); DB::table('report_definitions')->where('id',$rid)->delete(); }
        if ($tid) DB::table('templates')->where('id',$tid)->delete();
        DB::table('report_data_sources')->where('code',self::SOURCE)->delete();
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_due_in_rooms');
    }

    private function html(): string { return <<<'HTML'
<div class="report-header"><div class="hotel-header">{{hotel.logo}}<div><b>Địa chỉ:</b> {{hotel.address}}<br><b>Người dùng:</b> {{report.generated_by}}</div></div><hr><h1>DANH SÁCH PHÒNG DUE IN</h1><p>Ngày: {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p></div>
<table class="due-in"><thead><tr><th>STT</th><th>Mã ĐK</th><th>Tên Khách</th><th>Công Ty</th><th>Phòng</th><th>Loại Phòng</th><th>Ngày Đến</th><th>Ngày Đi</th><th>Đêm</th><th>Thêm Giường</th><th>N.Lớn / T.Em</th><th>Ghi Chú</th></tr></thead><tbody class="pms-grouped-rows" data-source="rows" data-group-by="ArrivalDateGroup"><tr class="pms-group-header"><td colspan="12">Ngày: {{row.ArrivalDateGroup}}</td></tr><tr class="pms-detail-row"><td></td><td>{{row.BookingId}}</td><td>{{row.GuestName}}</td><td>{{row.Company}}</td><td>{{row.Room}}</td><td>{{row.RoomType}}</td><td>{{row.ArrivalDate}}</td><td>{{row.DepartureDate}}</td><td>{{row.RoomNight}}</td><td>{{row.ExtraBed}}</td><td>{{row.AdultChild}}</td><td>{{row.Note}}</td></tr></tbody><tfoot><tr><td colspan="12">Tổng số dòng: {{summary.row_count}}</td></tr></tfoot></table>
HTML; }
    private function css(): string { return <<<'CSS'
body{font-family:Arial,sans-serif;color:#111;font-size:9px}.hotel-header{display:grid;grid-template-columns:180px 1fr;min-height:65px;align-items:center}.hotel-header img{max-width:120px;max-height:55px}h1{text-align:center;font-size:18px;margin:8px 0}p{text-align:center}.due-in{width:100%;border-collapse:collapse;table-layout:fixed}.due-in th,.due-in td{border:1px solid #b9c3d0;padding:4px;vertical-align:middle;overflow-wrap:anywhere}.due-in th{background:#d9e1ec;text-align:center}.due-in td:nth-child(1),.due-in td:nth-child(2),.due-in td:nth-child(5),.due-in td:nth-child(6),.due-in td:nth-child(7),.due-in td:nth-child(8),.due-in td:nth-child(9),.due-in td:nth-child(10),.due-in td:nth-child(11){text-align:center}.pms-group-header td{color:#dc2626;font-weight:bold;background:#fff}.due-in tfoot td{text-align:right;font-weight:bold}
CSS; }
};
