<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const INSTALLATIONS = [
        ['source' => 'RPT_SUMMARY_SERVICE_INVOICES', 'report' => 'SUMMARY_SERVICE_INVOICES', 'template' => 'SUMMARY_SERVICE_INVOICES_REFERENCE', 'procedure' => 'rpt_summary_service_invoices', 'name' => 'Báo cáo hóa đơn dịch vụ tổng hợp', 'group' => 'Báo cáo doanh thu', 'sort' => 154, 'menu_group' => 10],
        ['source' => 'RPT_DEPOSITS_SUMMARY', 'report' => 'DEPOSITS_SUMMARY', 'template' => 'DEPOSITS_SUMMARY_REFERENCE', 'procedure' => 'rpt_deposits_summary', 'name' => 'Báo cáo tiền đặt cọc', 'group' => 'Báo cáo thu ngân', 'sort' => 159, 'menu_group' => 20],
        ['source' => 'RPT_DAILY_SUMMARY', 'report' => 'DAILY_SUMMARY', 'template' => 'DAILY_SUMMARY_REFERENCE', 'procedure' => 'rpt_daily_summary', 'name' => 'Báo cáo tổng hợp ngày', 'group' => 'Báo cáo doanh thu', 'sort' => 160, 'menu_group' => 10],
        ['source' => 'RPT_COMPANY_OCCUPANCY_DETAIL', 'report' => 'COMPANY_OCCUPANCY_DETAIL', 'template' => 'COMPANY_OCCUPANCY_DETAIL_REFERENCE', 'procedure' => 'rpt_company_occupancy_detail', 'name' => 'Báo cáo chi tiết công suất công ty', 'group' => 'Báo cáo công suất', 'sort' => 166, 'menu_group' => 30],
        ['source' => 'RPT_COMPANY_OCCUPANCY', 'report' => 'COMPANY_OCCUPANCY', 'template' => 'COMPANY_OCCUPANCY_REFERENCE', 'procedure' => 'rpt_company_occupancy', 'name' => 'Báo cáo công suất công ty', 'group' => 'Báo cáo công suất', 'sort' => 167, 'menu_group' => 30],
        ['source' => 'RPT_SALESPERSON_REVENUE_SUMMARY', 'report' => 'SALESPERSON_REVENUE_SUMMARY', 'template' => 'SALESPERSON_REVENUE_SUMMARY_REFERENCE', 'procedure' => 'rpt_salesperson_revenue_summary', 'name' => 'Báo cáo tổng hợp doanh thu theo người bán', 'group' => 'Báo cáo doanh thu', 'sort' => 168, 'menu_group' => 10],
        ['source' => 'RPT_SALESPERSON_REVENUE_DETAIL', 'report' => 'SALESPERSON_REVENUE_DETAIL', 'template' => 'SALESPERSON_REVENUE_DETAIL_REFERENCE', 'procedure' => 'rpt_salesperson_revenue_detail', 'name' => 'Báo cáo chi tiết doanh thu theo người bán', 'group' => 'Báo cáo doanh thu', 'sort' => 169, 'menu_group' => 10],
    ];

    public function up(): void
    {
        $visitedDatabases = [];
        foreach ($this->branchConnections() as $connectionName) {
            $db = DB::connection($connectionName);
            if ($db->getDriverName() !== 'mysql') {
                continue;
            }

            $database = $db->getDatabaseName();
            if (isset($visitedDatabases[$database])) {
                continue;
            }
            $visitedDatabases[$database] = true;

            foreach ([
                'rpt_summary_service_invoices' => $this->summaryServiceInvoicesProcedure(),
                'rpt_deposits_summary' => $this->depositsSummaryProcedure(),
                'rpt_daily_summary' => $this->dailySummaryProcedure(),
                'rpt_company_occupancy_detail' => $this->companyOccupancyDetailProcedure(),
                'rpt_company_occupancy' => $this->companyOccupancyProcedure(),
                'rpt_salesperson_revenue_summary' => $this->salespersonRevenueSummaryProcedure(),
                'rpt_salesperson_revenue_detail' => $this->salespersonRevenueDetailProcedure(),
            ] as $procedure => $sql) {
                $db->unprepared('DROP PROCEDURE IF EXISTS `'.$procedure.'`');
                $db->unprepared($sql);
            }

            $this->syncConfiguration($db, $database);
        }
    }

    public function down(): void
    {
        foreach ($this->branchConnections() as $connectionName) {
            $db = DB::connection($connectionName);
            if ($db->getDriverName() !== 'mysql') {
                continue;
            }
            foreach (self::INSTALLATIONS as $installation) {
                $reportId = $db->table('report_definitions')->where('code', $installation['report'])->value('id');
                if ($reportId) {
                    $db->table('report_definition_template')->where('report_definition_id', $reportId)->delete();
                    $db->table('report_definitions')->where('id', $reportId)->delete();
                }
                $db->table('report_data_sources')->where('code', $installation['source'])->delete();
                $db->unprepared('DROP PROCEDURE IF EXISTS `'.$installation['procedure'].'`');
            }
        }
    }

    private function branchConnections(): array
    {
        return array_values(array_unique(array_merge(
            [config('database.default', 'mysql')],
            array_values(config('database_domains.branch_connections', []))
        )));
    }

    private function syncConfiguration($db, string $database): void
    {
        $now = now();
        $configurations = $this->configurations();
        foreach ($configurations as $configuration) {
            $installation = $configuration['installation'];
            $ui = $this->hydrateInlineLookupOptions($db, $configuration['ui']);
            $db->table('report_data_sources')->updateOrInsert(['code' => $installation['source']], [
                'name' => $installation['name'],
                'description' => $configuration['description'],
                'source_type' => 'procedure',
                'schema_name' => $database,
                'object_name' => $installation['procedure'],
                'parameter_schema' => json_encode($configuration['parameters'], JSON_UNESCAPED_UNICODE),
                'field_schema' => json_encode(array_map(static fn (string $name, string $type): array => ['name' => $name, 'type' => $type, 'nullable' => true], array_keys($configuration['fields']), $configuration['fields']), JSON_UNESCAPED_UNICODE),
                'sample_parameters' => json_encode($configuration['defaults'], JSON_UNESCAPED_UNICODE),
                'max_rows' => 5000,
                'is_active' => true,
                'last_discovered_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $sourceId = $db->table('report_data_sources')->where('code', $installation['source'])->value('id');
            $definition = (require database_path('report_templates/'.strtolower($installation['template']).'.php'))->definition();
            $template = $db->table('templates')->where('report', $installation['template'])->first();
            $templateValues = [
                'group' => $installation['group'], 'name' => $definition['name'], 'report_data_source_id' => $sourceId,
                'parameter_defaults' => json_encode($configuration['defaults'], JSON_UNESCAPED_UNICODE),
                'page_size' => $definition['page_size'], 'page_orientation' => $definition['page_orientation'],
                'margin_top' => $definition['margin_top'], 'margin_right' => $definition['margin_right'],
                'margin_bottom' => $definition['margin_bottom'], 'margin_left' => $definition['margin_left'],
                'content_json' => json_encode($definition['content_json'], JSON_UNESCAPED_UNICODE),
                'content_html' => $definition['content_html'], 'css' => $definition['css'],
                'is_default' => true, 'version' => '1.0', 'updated_at' => $now,
            ];
            if (! $template) {
                $templateValues['report'] = $installation['template'];
                $templateValues['created_at'] = $now;
                $templateId = $db->table('templates')->insertGetId($templateValues);
            } else {
                $templateId = $template->id;
                // Preserve a template already customized in Designer; only repair its source link.
                $db->table('templates')->where('id', $templateId)->update(['report_data_source_id' => $sourceId, 'updated_at' => $now]);
            }

            $db->table('report_definitions')->updateOrInsert(['code' => $installation['report']], [
                'name' => $installation['name'], 'group' => $installation['group'], 'description' => $configuration['description'],
                'report_data_source_id' => $sourceId, 'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE),
                'sort_order' => $installation['sort'], 'is_active' => true, 'show_in_menu' => true,
                'menu_locations' => json_encode(['reservation', 'frontdesk'], JSON_UNESCAPED_UNICODE),
                'menu_top_order' => 20, 'menu_group_order' => $installation['menu_group'], 'menu_item_order' => $installation['sort'],
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $reportId = $db->table('report_definitions')->where('code', $installation['report'])->value('id');
            $db->table('report_definition_template')->updateOrInsert(
                ['report_definition_id' => $reportId, 'template_id' => $templateId],
                ['is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    private function hydrateInlineLookupOptions($db, array $ui): array
    {
        foreach ($ui as &$field) {
            if (($field['name'] ?? '') === 'p_segment') {
                $field['options'] = array_merge([['value' => '', 'label' => 'Tất cả']], $db->table('markets')->orderBy('code')->get(['code', 'name'])->map(fn ($row) => ['value' => $row->code, 'label' => $row->code.' - '.$row->name])->all());
            }
            if (($field['name'] ?? '') === 'p_source_code' || ($field['name'] ?? '') === 'p_market_segment') {
                $table = ($field['name'] ?? '') === 'p_market_segment' ? 'markets' : 'customer_sources';
                $field['options'] = array_merge([['value' => '', 'label' => 'Tất cả']], $db->table($table)->orderBy('code')->get(['code', 'name'])->map(fn ($row) => ['value' => $row->code, 'label' => $row->code.' - '.$row->name])->all());
            }
        }
        unset($field);

        return $ui;
    }

    private function configurations(): array
    {
        return [
            ['installation' => self::INSTALLATIONS[0], 'description' => 'Dịch vụ phát sinh theo ngày, nhóm theo mã dịch vụ và nhóm doanh thu Navy.', 'parameters' => $this->params154(), 'fields' => $this->fields154(), 'defaults' => ['p_from_date' => now()->toDateString(), 'p_to_date' => now()->toDateString(), 'p_department' => '', 'p_services' => '', 'p_user' => '', 'p_order_by' => 'Date', 'p_show_deleted' => 0, 'p_group_by_service' => 1, 'p_group_by_date' => 0], 'ui' => $this->ui154()],
            ['installation' => self::INSTALLATIONS[1], 'description' => 'Tiền đặt cọc và từng lần cấn trừ thực tế.', 'parameters' => $this->params159(), 'fields' => $this->fields159(), 'defaults' => ['p_from_date' => now()->toDateString(), 'p_to_date' => now()->toDateString(), 'p_option' => 1, 'p_shift' => '', 'p_department' => '', 'p_outlet' => '', 'p_company' => '', 'p_user' => '', 'p_sort_by' => 'Date', 'p_order_by' => 'ASC'], 'ui' => $this->ui159()],
            ['installation' => self::INSTALLATIONS[2], 'description' => 'Tổng hợp ngày theo doanh thu, hoạt động phòng, OCC và ADR.', 'parameters' => $this->params160(), 'fields' => $this->fields160(), 'defaults' => ['p_date' => now()->toDateString()], 'ui' => $this->ui160()],
            ['installation' => self::INSTALLATIONS[3], 'description' => 'Chi tiết công suất và doanh thu theo từng booking.', 'parameters' => $this->params166(), 'fields' => $this->fields166(), 'defaults' => ['p_from_date' => now()->toDateString(), 'p_to_date' => now()->toDateString(), 'p_area' => '', 'p_company' => '', 'p_segment' => '', 'p_user_sale' => '', 'p_source_code' => ''], 'ui' => $this->ui166()],
            ['installation' => self::INSTALLATIONS[4], 'description' => 'Tổng hợp 11 cột công suất công ty; chi tiết nằm ở báo cáo Dòng 166.', 'parameters' => $this->params167(), 'fields' => $this->fields167(), 'defaults' => ['p_from_date' => now()->toDateString(), 'p_to_date' => now()->toDateString(), 'p_area' => '', 'p_company' => '', 'p_segment' => '', 'p_user_sale' => '', 'p_source_code' => '', 'p_group_by' => 'COMPANY', 'p_include_breakfast' => 1], 'ui' => $this->ui167()],
            ['installation' => self::INSTALLATIONS[5], 'description' => 'Tổng hợp doanh thu theo người bán, hỗ trợ Army arrival và Navy room-night.', 'parameters' => $this->params168(), 'fields' => $this->fields168Summary(), 'defaults' => ['p_from_date' => now()->toDateString(), 'p_to_date' => now()->toDateString(), 'p_filter_mode' => 1, 'p_sales_person' => '', 'p_market_segment' => '', 'p_company_id' => '', 'p_group_by' => 'SALESPERSON'], 'ui' => $this->ui168(false)],
            ['installation' => self::INSTALLATIONS[6], 'description' => 'Chi tiết doanh thu từng booking theo người bán, hỗ trợ Army arrival và Navy room-night.', 'parameters' => $this->params168(), 'fields' => $this->fields168Detail(), 'defaults' => ['p_from_date' => now()->toDateString(), 'p_to_date' => now()->toDateString(), 'p_filter_mode' => 1, 'p_sales_person' => '', 'p_market_segment' => '', 'p_company_id' => '', 'p_group_by' => 'SALESPERSON'], 'ui' => $this->ui168(true)],
        ];
    }

    private function params154(): array { return $this->parameterList([['p_from_date','date'],['p_to_date','date'],['p_department','varchar'],['p_services','text'],['p_user','varchar'],['p_order_by','varchar'],['p_show_deleted','tinyint'],['p_group_by_service','tinyint'],['p_group_by_date','tinyint']]); }
    private function params159(): array { return $this->parameterList([['p_from_date','date'],['p_to_date','date'],['p_option','int'],['p_shift','varchar'],['p_department','varchar'],['p_outlet','varchar'],['p_company','varchar'],['p_user','varchar'],['p_sort_by','varchar'],['p_order_by','varchar']]); }
    private function params160(): array { return $this->parameterList([['p_date','date']]); }
    private function params166(): array { return $this->parameterList([['p_from_date','date'],['p_to_date','date'],['p_area','varchar'],['p_company','varchar'],['p_segment','varchar'],['p_user_sale','varchar'],['p_source_code','varchar']]); }
    private function params167(): array { return $this->parameterList([['p_from_date','date'],['p_to_date','date'],['p_area','varchar'],['p_company','varchar'],['p_segment','varchar'],['p_user_sale','varchar'],['p_source_code','varchar'],['p_group_by','varchar'],['p_include_breakfast','tinyint']]); }
    private function params168(): array { return $this->parameterList([['p_from_date','date'],['p_to_date','date'],['p_filter_mode','int'],['p_sales_person','varchar'],['p_market_segment','varchar'],['p_company_id','varchar'],['p_group_by','varchar']]); }
    private function parameterList(array $items): array { return array_map(static fn (array $item, int $index): array => ['name' => $item[0], 'mode' => 'IN', 'data_type' => $item[1], 'database_type' => $item[1], 'position' => $index + 1, 'required' => true], $items, array_keys($items)); }

    private function fields154(): array { return ['Stt'=>'integer','BookingCode'=>'string','RoomNumber'=>'string','ArrivalDate'=>'string','DepartureDate'=>'string','GuestName'=>'string','Description'=>'string','Amount'=>'number','PaymentMethod'=>'string','CompanyName'=>'string','OpenTime'=>'string','Note'=>'string','ServiceCode'=>'string','RevenueGroupName'=>'string','ServiceGroupHeader'=>'string','DateGroupHeader'=>'string']; }
    private function fields159(): array { return ['MaDatCoc'=>'string','MTT'=>'string','PaymentDate'=>'string','TimePayment'=>'string','BookingRoomCode'=>'string','BookingName'=>'string','BusinessName'=>'string','ArrivalDate'=>'string','DepartureDate'=>'string','Amount'=>'number','PaymentMethodName'=>'string','Description'=>'string','Username'=>'string','PaymentMethod'=>'string','DepositGroup'=>'string']; }
    private function fields160(): array { return ['GroupIndex'=>'integer','SortOrder'=>'string','Content'=>'string','DateAmount'=>'number','MonthAmount'=>'number','PlanAmount'=>'number','Rate'=>'number','IsBold'=>'integer','CustomText'=>'string']; }
    private function fields166(): array { return ['BookingCode'=>'string','ReferenceCode'=>'string','CompanyName'=>'string','GuestName'=>'string','MarketSegment'=>'string','SourceCode'=>'string','BookingDate'=>'string','ArrivalDate'=>'string','DepartureDate'=>'string','NoOfNight'=>'integer','NoOfRoom'=>'integer','RoomNight'=>'integer','GuestNight'=>'integer','AverageRate'=>'number','AverageRateOriginal'=>'number','RoomRevenue'=>'number','FbRevenue'=>'number','OtherRevenue'=>'number','TotalRevenue'=>'number','RoomType'=>'string','Nationality'=>'string']; }
    private function fields167(): array { return ['CompanyCode'=>'string','CompanyName'=>'string','OccupancyRate'=>'number','RoomNight'=>'number','GuestQty'=>'number','ActualADR'=>'number','RackADR'=>'number','RoomRevenue'=>'number','FbRevenue'=>'number','OtherRevenue'=>'number','TotalRevenue'=>'number']; }
    private function fields168Summary(): array { return ['SalesPersonCode'=>'string','SalesPersonName'=>'string','OccupancyRate'=>'number','RoomNights'=>'number','GuestQty'=>'number','ActualADR'=>'number','RackADR'=>'number','RoomRevenue'=>'number','FbRevenue'=>'number','OtherRevenue'=>'number','TotalRevenue'=>'number']; }
    private function fields168Detail(): array { return ['BookingCode'=>'string','BookingName'=>'string','ArrivalDate'=>'string','DepartureDate'=>'string','RoomNights'=>'number','FocRoomNights'=>'number','GuestQty'=>'number','CompanyName'=>'string','MarketSegment'=>'string','RoomRevenue'=>'number','FbRevenue'=>'number','OtherRevenue'=>'number','TotalRevenue'=>'number','SalesPersonName'=>'string']; }

    private function ui154(): array { return [['name'=>'p_from_date','label'=>'Từ ngày','control'=>'date-range','range_end_parameter'=>'p_to_date','default'=>'$today','required'=>true],['name'=>'p_to_date','label'=>'Đến ngày','control'=>'hidden','default'=>'$today','required'=>true],['name'=>'p_department','label'=>'Bộ phận','control'=>'select','default'=>'','required'=>false,'options_source'=>'service-departments'],['name'=>'p_services','label'=>'Dịch vụ','control'=>'multi-select','default'=>'','required'=>false,'options'=>array_map(fn($v,$l)=>['value'=>$v,'label'=>$l],['RM','EB','FB','MB','LA','PU','DO'],['RM - Tiền phòng','EB - Phụ thu phòng','FB - F&B','MB - Minibar','LA - Giặt là','PU - Đón khách','DO - Đưa khách'])],['name'=>'p_user','label'=>'Người dùng','control'=>'select','default'=>'','required'=>false,'options_source'=>'users'],['name'=>'p_order_by','label'=>'Sắp xếp theo','control'=>'select','default'=>'Date','required'=>true,'options'=>[['value'=>'Date','label'=>'Ngày'],['value'=>'Room','label'=>'Phòng'],['value'=>'Ma','label'=>'Mã']]],['name'=>'p_show_deleted','label'=>'Hiển thị đã xóa','control'=>'checkbox','default'=>false,'required'=>false],['name'=>'p_group_by_service','label'=>'Nhóm theo dịch vụ','control'=>'checkbox','default'=>true,'required'=>false],['name'=>'p_group_by_date','label'=>'Nhóm theo ngày','control'=>'checkbox','default'=>false,'required'=>false]]; }
    private function ui159(): array { return [['name'=>'p_from_date','label'=>'Ngày','control'=>'date-range','range_end_parameter'=>'p_to_date','default'=>'$today','required'=>true],['name'=>'p_to_date','label'=>'Đến ngày','control'=>'hidden','default'=>'$today','required'=>true],['name'=>'p_shift','label'=>'Ca làm việc','control'=>'select','default'=>'','required'=>false,'options_source'=>'report-shifts'],['name'=>'p_department','label'=>'Bộ phận','control'=>'select','default'=>'','required'=>false,'options_source'=>'service-departments'],['name'=>'p_outlet','label'=>'Outlet','control'=>'select','default'=>'','required'=>false,'options_source'=>'outlets'],['name'=>'p_company','label'=>'Công ty','control'=>'select','default'=>'','required'=>false,'options_source'=>'companies'],['name'=>'p_user','label'=>'Người dùng','control'=>'select','default'=>'','required'=>false,'options_source'=>'users'],['name'=>'p_option','label'=>'Điều kiện lọc cọc','control'=>'radio','default'=>1,'required'=>true,'options'=>[['value'=>1,'label'=>'Ngày đặt cọc'],['value'=>2,'label'=>'Ngày đến'],['value'=>3,'label'=>'Ngày đi'],['value'=>4,'label'=>'Cọc chờ đến'],['value'=>5,'label'=>'Cọc đã sử dụng trong kỳ']]],['name'=>'p_sort_by','label'=>'Sắp xếp theo','control'=>'select','default'=>'Date','required'=>true,'options'=>[['value'=>'Date','label'=>'Ngày'],['value'=>'Amount','label'=>'Số tiền'],['value'=>'Room','label'=>'Phòng']]],['name'=>'p_order_by','label'=>'Thứ tự','control'=>'select','default'=>'ASC','required'=>true,'options'=>[['value'=>'ASC','label'=>'Tăng dần'],['value'=>'DESC','label'=>'Giảm dần']]]]; }
    private function ui160(): array { return [['name'=>'p_date','label'=>'Ngày báo cáo','control'=>'date','default'=>'$today','required'=>true]]; }
    private function ui166(): array { return [['name'=>'p_from_date','label'=>'Từ ngày','control'=>'date-range','range_end_parameter'=>'p_to_date','default'=>'$today','required'=>true],['name'=>'p_to_date','label'=>'Đến ngày','control'=>'hidden','default'=>'$today','required'=>true],['name'=>'p_company','label'=>'Công ty','control'=>'select','default'=>'','required'=>false,'options_source'=>'companies'],['name'=>'p_segment','label'=>'Thị trường','control'=>'select','default'=>'','required'=>false,'options'=>[['value'=>'','label'=>'Tất cả']]],['name'=>'p_source_code','label'=>'Nguồn khách','control'=>'select','default'=>'','required'=>false,'options'=>[['value'=>'','label'=>'Tất cả']]],['name'=>'p_user_sale','label'=>'Người bán','control'=>'select','default'=>'','required'=>false,'options_source'=>'users'],['name'=>'p_area','label'=>'Khu vực','control'=>'select','default'=>'','required'=>false,'options_source'=>'areas']]; }
    private function ui167(): array { return array_merge($this->ui166(), [['name'=>'p_group_by','label'=>'Nhóm theo','control'=>'select','default'=>'COMPANY','required'=>true,'options'=>[['value'=>'COMPANY','label'=>'Công ty'],['value'=>'DATE','label'=>'Ngày'],['value'=>'MARKET','label'=>'Thị trường'],['value'=>'SOURCE','label'=>'Nguồn khách']]],['name'=>'p_include_breakfast','label'=>'DT phòng bao gồm ăn sáng','control'=>'checkbox','default'=>true,'required'=>false]]); }
    private function ui168(bool $detail): array { return [['name'=>'p_from_date','label'=>'Từ ngày','control'=>'date-range','range_end_parameter'=>'p_to_date','default'=>'$today','required'=>true],['name'=>'p_to_date','label'=>'Đến ngày','control'=>'hidden','default'=>'$today','required'=>true],['name'=>'p_filter_mode','label'=>'Chế độ lọc','control'=>'select','default'=>1,'required'=>true,'options'=>[['value'=>1,'label'=>'Theo ngày đến'],['value'=>2,'label'=>'Theo đêm phòng ở']]],['name'=>'p_sales_person','label'=>'Người bán','control'=>'select','default'=>'','required'=>false,'options_source'=>'users'],['name'=>'p_market_segment','label'=>'Thị trường','control'=>'select','default'=>'','required'=>false,'options'=>[['value'=>'','label'=>'Tất cả']]],['name'=>'p_company_id','label'=>'Công ty','control'=>'select','default'=>'','required'=>false,'options_source'=>'companies'],['name'=>'p_group_by','label'=>'Nhóm theo','control'=>'hidden','default'=>'SALESPERSON','required'=>false]]; }

    private function summaryServiceInvoicesProcedure(): string
    {
        return <<<'SQL'
CREATE PROCEDURE rpt_summary_service_invoices(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_department VARCHAR(20),
    IN p_services TEXT,
    IN p_user VARCHAR(50),
    IN p_order_by VARCHAR(20),
    IN p_show_deleted TINYINT,
    IN p_group_by_service TINYINT,
    IN p_group_by_date TINYINT
)
READS SQL DATA
BEGIN
    SELECT
        ROW_NUMBER() OVER (ORDER BY DATE(sb.Date), sb.Ma) AS Stt,
        COALESCE(NULLIF(b.external_booking_code, ''), CAST(b.id AS CHAR), '') AS BookingCode,
        COALESCE(br.room_number, sb.RentalRoomId2, '') AS RoomNumber,
        DATE_FORMAT(COALESCE(br.arrival_date, b.arrival_date), '%d/%m/%Y') AS ArrivalDate,
        DATE_FORMAT(COALESCE(br.departure_date, b.departure_date), '%d/%m/%Y') AS DepartureDate,
        COALESCE(NULLIF(b.booking_name, ''), NULLIF(sb.Guest, ''), '') AS GuestName,
        COALESCE(NULLIF(sb.DescriptionServive, ''), NULLIF(hs.name, ''), sb.ServiceId) AS Description,
        CAST(COALESCE(sb.Amount, 0) AS DECIMAL(15,2)) AS Amount,
        COALESCE(NULLIF(pm.code, ''), NULLIF(pay.payment_method_id, ''), '') AS PaymentMethod,
        COALESCE(NULLIF(c.name, ''), 'KHÁCH LẺ') AS CompanyName,
        COALESCE(NULLIF(sb.OpenTime, ''), DATE_FORMAT(sb.Date, '%H:%i')) AS OpenTime,
        COALESCE(NULLIF(sb.Pack3, ''), '') AS Note,
        sb.ServiceId AS ServiceCode,
        CASE
            WHEN sb.ServiceId IN ('BC','BD','BF','EB','EI','EP','ER','LO','RM','TB','DN','GN','HN','HT','LH','MR','MS','NB','TO','WS') THEN 'Doanh Thu Phòng'
            WHEN COALESCE(sb.DepartmentId, '') = 'FB' OR sb.ServiceId IN ('FB','OT','RB','RF') THEN 'Doanh Thu Nhà Hàng'
            WHEN sb.ServiceId = 'MB' THEN 'Doanh Thu Minibar'
            WHEN sb.ServiceId = 'LA' THEN 'Doanh Thu Giặt Là'
            WHEN sb.ServiceId IN ('PU','DO') THEN 'Doanh Thu Vận Chuyển'
            ELSE 'Doanh Thu Dịch Vụ'
        END AS RevenueGroupName,
        CONCAT('Dịch vụ: ', sb.ServiceId, ' - ', COALESCE(NULLIF(hs.name, ''), sb.ServiceId)) AS ServiceGroupHeader,
        DATE_FORMAT(sb.Date, '%d/%m/%Y') AS DateGroupHeader
    FROM service_bills AS sb
    LEFT JOIN booking_rooms AS br ON br.id = sb.RentalRoomId2
    LEFT JOIN bookings AS b ON b.id = COALESCE(br.booking_id, NULLIF(sb.RegisterID2, 0))
    LEFT JOIN companies AS c ON c.id = COALESCE(b.company_id, sb.CompanyId2)
    LEFT JOIN payments AS pay ON pay.id = sb.PaymentId
    LEFT JOIN payment_methods AS pm ON pm.code = pay.payment_method_id
    LEFT JOIN hotel_services AS hs ON hs.code = sb.ServiceId
    WHERE DATE(sb.Date) BETWEEN COALESCE(p_from_date, CURRENT_DATE()) AND COALESCE(p_to_date, CURRENT_DATE())
      AND (COALESCE(p_show_deleted, 0) = 1 OR COALESCE(sb.Edit, 0) = 0)
      AND (COALESCE(p_department, '') = '' OR sb.DepartmentId = p_department)
      AND (COALESCE(p_services, '') = '' OR FIND_IN_SET(sb.ServiceId, REPLACE(p_services, ' ', '')) > 0)
      AND (COALESCE(p_user, '') = '' OR COALESCE(NULLIF(sb.Username, ''), NULLIF(sb.CreatedUser, '')) = p_user)
    ORDER BY
        CASE WHEN p_order_by = 'Room' THEN COALESCE(br.room_number, sb.RentalRoomId2, '') END,
        CASE WHEN p_order_by = 'Ma' THEN sb.Ma END,
        CASE WHEN p_order_by = 'Date' OR p_order_by IS NULL OR p_order_by = '' THEN sb.Date END,
        sb.Ma;
END
SQL;
    }
    private function depositsSummaryProcedure(): string
    {
        return <<<'SQL'
CREATE PROCEDURE rpt_deposits_summary(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_option INT,
    IN p_shift VARCHAR(20),
    IN p_department VARCHAR(20),
    IN p_outlet VARCHAR(20),
    IN p_company VARCHAR(50),
    IN p_user VARCHAR(50),
    IN p_sort_by VARCHAR(20),
    IN p_order_by VARCHAR(10)
)
READS SQL DATA
BEGIN
    DECLARE v_prefix VARCHAR(50) DEFAULT '';
    SELECT COALESCE(MAX(CASE WHEN LOWER(name) IN ('prefix_booking_id', 'prefixbookingid') THEN value END), '')
      INTO v_prefix FROM hotel_configs;

    SELECT
        CAST(p.id AS CHAR) AS MaDatCoc,
        COALESCE(NULLIF(p.payment_id, ''), NULLIF(si.payment_code, ''), CAST(si.id AS CHAR), '') AS MTT,
        DATE_FORMAT(COALESCE(s.payment_date, si.payment_date, si.invoice_date, p.date), '%d/%m/%Y') AS PaymentDate,
        COALESCE(s.payment_time, p.open_time, DATE_FORMAT(p.created_at, '%H:%i')) AS TimePayment,
        CASE WHEN b.id IS NULL THEN '' ELSE CONCAT(v_prefix, b.id, CASE WHEN br.room_number IS NULL OR br.room_number = '' THEN '' ELSE CONCAT('/', br.room_number) END) END AS BookingRoomCode,
        COALESCE(NULLIF(b.booking_name, ''), '') AS BookingName,
        COALESCE(NULLIF(c.name, ''), 'Khách lẻ') AS BusinessName,
        DATE_FORMAT(COALESCE(br.arrival_date, b.arrival_date), '%d/%m/%Y') AS ArrivalDate,
        DATE_FORMAT(COALESCE(br.departure_date, b.departure_date), '%d/%m/%Y') AS DepartureDate,
        CAST(CASE WHEN COALESCE(p_option, 1) = 5 THEN COALESCE(s.amount, 0) ELSE COALESCE(p.amount, 0) END AS DECIMAL(15,2)) AS Amount,
        COALESCE(NULLIF(pm.name, ''), NULLIF(pm.code, ''), COALESCE(s.payment_method_id, p.payment_method_id, '')) AS PaymentMethodName,
        COALESCE(NULLIF(s.description, ''), NULLIF(p.description, ''), '') AS Description,
        COALESCE(NULLIF(s.created_by, ''), NULLIF(p.created_by, ''), NULLIF(p.username, ''), '') AS Username,
        COALESCE(s.payment_method_id, p.payment_method_id, '') AS PaymentMethod,
        CASE WHEN COALESCE(p_option, 1) = 5 THEN 'Đã sử dụng' WHEN p.payment_id IS NULL OR p.payment_id = '' THEN 'Chưa sử dụng' ELSE 'Đã sử dụng' END AS DepositGroup
    FROM payments AS p
    LEFT JOIN payment_debt_settlements AS s
      ON s.payment_id = p.id
     AND COALESCE(s.edit_flag, 0) = 0
     AND s.deleted_at IS NULL
     AND COALESCE(p_option, 1) = 5
    LEFT JOIN sales_invoices AS si ON si.id = p.invoice_id
    LEFT JOIN bookings AS b ON b.id = p.booking_id
    LEFT JOIN booking_rooms AS br ON br.id = p.booking_room_id
    LEFT JOIN companies AS c ON c.id = COALESCE(p.company_id, b.company_id)
    LEFT JOIN payment_methods AS pm ON pm.code = COALESCE(s.payment_method_id, p.payment_method_id)
    WHERE COALESCE(p.status, 1) <> 3
      AND COALESCE(p.edit_flag, 0) = 0
      AND p.deleted_at IS NULL
      AND (p.pack2 = 'DPR' OR p.pack4 = 'AP')
      AND (COALESCE(p_shift, '') = '' OR p.shift = p_shift)
      AND (COALESCE(p_department, '') = '' OR p.department_id = p_department)
      AND (COALESCE(p_outlet, '') = '' OR p.outlet = p_outlet)
      AND (COALESCE(p_company, '') = '' OR CAST(COALESCE(p.company_id, b.company_id) AS CHAR) = p_company OR c.code = p_company)
      AND (COALESCE(p_user, '') = '' OR COALESCE(NULLIF(p.created_by, ''), NULLIF(p.username, '')) = p_user)
      AND (
          (COALESCE(p_option, 1) = 1 AND p.date BETWEEN COALESCE(p_from_date, CURRENT_DATE()) AND COALESCE(p_to_date, CURRENT_DATE()))
       OR (COALESCE(p_option, 1) = 2 AND COALESCE(br.arrival_date, b.arrival_date) BETWEEN COALESCE(p_from_date, CURRENT_DATE()) AND COALESCE(p_to_date, CURRENT_DATE()))
       OR (COALESCE(p_option, 1) = 3 AND COALESCE(br.departure_date, b.departure_date) BETWEEN COALESCE(p_from_date, CURRENT_DATE()) AND COALESCE(p_to_date, CURRENT_DATE()))
       OR (COALESCE(p_option, 1) = 4 AND p.date BETWEEN COALESCE(p_from_date, CURRENT_DATE()) AND COALESCE(p_to_date, CURRENT_DATE()) AND (p.payment_id IS NULL OR p.payment_id = ''))
       OR (COALESCE(p_option, 1) = 5 AND COALESCE(s.payment_date, si.payment_date, si.invoice_date, p.date) BETWEEN COALESCE(p_from_date, CURRENT_DATE()) AND COALESCE(p_to_date, CURRENT_DATE()))
      )
    ORDER BY
        CASE WHEN p_order_by = 'DESC' AND p_sort_by = 'Amount' THEN (CASE WHEN COALESCE(p_option, 1) = 5 THEN COALESCE(s.amount, 0) ELSE COALESCE(p.amount, 0) END) END DESC,
        CASE WHEN p_order_by <> 'DESC' AND p_sort_by = 'Amount' THEN (CASE WHEN COALESCE(p_option, 1) = 5 THEN COALESCE(s.amount, 0) ELSE COALESCE(p.amount, 0) END) END ASC,
        CASE WHEN p_order_by = 'DESC' AND p_sort_by = 'Room' THEN BookingRoomCode END DESC,
        CASE WHEN p_order_by <> 'DESC' AND p_sort_by = 'Room' THEN BookingRoomCode END ASC,
        CASE WHEN p_order_by = 'DESC' OR p_order_by IS NULL OR p_order_by = '' THEN PaymentDate END DESC,
        PaymentDate ASC, MaDatCoc ASC;
END
SQL;
    }
    private function dailySummaryProcedure(): string
    {
        return <<<'SQL'
CREATE PROCEDURE rpt_daily_summary(IN p_date DATE)
READS SQL DATA
BEGIN
    DECLARE v_date DATE DEFAULT COALESCE(p_date, CURRENT_DATE());
    DECLARE v_month_start DATE;
    DECLARE v_total_day DECIMAL(15,2) DEFAULT 0;
    DECLARE v_total_month DECIMAL(15,2) DEFAULT 0;
    DECLARE v_room_day DECIMAL(15,2) DEFAULT 0;
    DECLARE v_room_month DECIMAL(15,2) DEFAULT 0;
    DECLARE v_fb_day DECIMAL(15,2) DEFAULT 0;
    DECLARE v_fb_month DECIMAL(15,2) DEFAULT 0;
    DECLARE v_mb_day DECIMAL(15,2) DEFAULT 0;
    DECLARE v_mb_month DECIMAL(15,2) DEFAULT 0;
    DECLARE v_la_day DECIMAL(15,2) DEFAULT 0;
    DECLARE v_la_month DECIMAL(15,2) DEFAULT 0;
    DECLARE v_transport_day DECIMAL(15,2) DEFAULT 0;
    DECLARE v_transport_month DECIMAL(15,2) DEFAULT 0;
    DECLARE v_conference_day DECIMAL(15,2) DEFAULT 0;
    DECLARE v_conference_month DECIMAL(15,2) DEFAULT 0;
    DECLARE v_vpth_day DECIMAL(15,2) DEFAULT 0;
    DECLARE v_vpth_month DECIMAL(15,2) DEFAULT 0;
    DECLARE v_shop_day DECIMAL(15,2) DEFAULT 0;
    DECLARE v_shop_month DECIMAL(15,2) DEFAULT 0;
    DECLARE v_other_day DECIMAL(15,2) DEFAULT 0;
    DECLARE v_other_month DECIMAL(15,2) DEFAULT 0;
    DECLARE v_inhouse INT DEFAULT 0;
    DECLARE v_checkin INT DEFAULT 0;
    DECLARE v_checkout INT DEFAULT 0;
    DECLARE v_foc_day INT DEFAULT 0;
    DECLARE v_foc_month INT DEFAULT 0;
    DECLARE v_room_available INT DEFAULT 0;
    DECLARE v_occ DECIMAL(15,2) DEFAULT 0;
    DECLARE v_adr DECIMAL(15,2) DEFAULT 0;
    DECLARE v_revenue_list TEXT DEFAULT 'BC,BD,BF,EB,EI,EP,ER,LO,RM,TB,DN,GN,HN,HT,LH,MR,MS,NB,TO,WS';
    DECLARE v_fb_list TEXT DEFAULT 'FB,OT,RB,RF';
    DECLARE v_conference_list TEXT DEFAULT '';
    DECLARE v_minibar_list TEXT DEFAULT 'MB';
    DECLARE v_laundry_list TEXT DEFAULT 'LA';
    DECLARE v_transport_list TEXT DEFAULT 'PU,DO';
    DECLARE v_vpth_list TEXT DEFAULT '';
    DECLARE v_shop_list TEXT DEFAULT '';
    DECLARE v_include_others TINYINT DEFAULT 0;

    SET v_month_start = DATE_FORMAT(v_date, '%Y-%m-01');
    SELECT COALESCE(NULLIF(MAX(CASE WHEN LOWER(name) = 'revenue' THEN value END), ''), v_revenue_list),
           COALESCE(NULLIF(MAX(CASE WHEN LOWER(name) = 'fbrevenue' THEN value END), ''), v_fb_list),
           COALESCE(NULLIF(MAX(CASE WHEN LOWER(name) = 'conferencerevenue' THEN value END), ''), v_conference_list),
           COALESCE(NULLIF(MAX(CASE WHEN LOWER(name) = 'minibarrevenue' THEN value END), ''), v_minibar_list),
           COALESCE(NULLIF(MAX(CASE WHEN LOWER(name) = 'laundryrevenue' THEN value END), ''), v_laundry_list),
           COALESCE(NULLIF(MAX(CASE WHEN LOWER(name) = 'transportationrevenue' THEN value END), ''), v_transport_list),
           COALESCE(NULLIF(MAX(CASE WHEN LOWER(name) IN ('vpthrevenue', 'officerevenue') THEN value END), ''), v_vpth_list),
           COALESCE(NULLIF(MAX(CASE WHEN LOWER(name) = 'shoprevenue' THEN value END), ''), v_shop_list),
           COALESCE(MAX(CASE WHEN LOWER(name) = 'averageroomrateincludedothersroomrevenue' THEN CAST(value AS UNSIGNED) END), 0)
      INTO v_revenue_list, v_fb_list, v_conference_list, v_minibar_list, v_laundry_list,
           v_transport_list, v_vpth_list, v_shop_list, v_include_others
      FROM hotel_configs;

    SELECT COALESCE(SUM(CASE WHEN DATE(Date) = v_date THEN Amount ELSE 0 END), 0),
           COALESCE(SUM(Amount), 0),
           COALESCE(SUM(CASE WHEN DATE(Date) = v_date AND (CASE WHEN v_include_others = 1 THEN FIND_IN_SET(ServiceId, REPLACE(v_revenue_list, ' ', '')) > 0 ELSE ServiceId = 'RM' END) THEN Amount ELSE 0 END), 0),
           COALESCE(SUM(CASE WHEN DATE(Date) BETWEEN v_month_start AND v_date AND (CASE WHEN v_include_others = 1 THEN FIND_IN_SET(ServiceId, REPLACE(v_revenue_list, ' ', '')) > 0 ELSE ServiceId = 'RM' END) THEN Amount ELSE 0 END), 0),
           COALESCE(SUM(CASE WHEN DATE(Date) = v_date AND (DepartmentId = 'FB' OR FIND_IN_SET(ServiceId, REPLACE(v_fb_list, ' ', '')) > 0) THEN Amount ELSE 0 END), 0),
           COALESCE(SUM(CASE WHEN DATE(Date) BETWEEN v_month_start AND v_date AND (DepartmentId = 'FB' OR FIND_IN_SET(ServiceId, REPLACE(v_fb_list, ' ', '')) > 0) THEN Amount ELSE 0 END), 0),
           COALESCE(SUM(CASE WHEN DATE(Date) = v_date AND FIND_IN_SET(ServiceId, REPLACE(v_minibar_list, ' ', '')) > 0 THEN Amount ELSE 0 END), 0),
           COALESCE(SUM(CASE WHEN DATE(Date) BETWEEN v_month_start AND v_date AND FIND_IN_SET(ServiceId, REPLACE(v_minibar_list, ' ', '')) > 0 THEN Amount ELSE 0 END), 0),
           COALESCE(SUM(CASE WHEN DATE(Date) = v_date AND FIND_IN_SET(ServiceId, REPLACE(v_laundry_list, ' ', '')) > 0 THEN Amount ELSE 0 END), 0),
           COALESCE(SUM(CASE WHEN DATE(Date) BETWEEN v_month_start AND v_date AND FIND_IN_SET(ServiceId, REPLACE(v_laundry_list, ' ', '')) > 0 THEN Amount ELSE 0 END), 0),
           COALESCE(SUM(CASE WHEN DATE(Date) = v_date AND FIND_IN_SET(ServiceId, REPLACE(v_transport_list, ' ', '')) > 0 THEN Amount ELSE 0 END), 0),
           COALESCE(SUM(CASE WHEN DATE(Date) BETWEEN v_month_start AND v_date AND FIND_IN_SET(ServiceId, REPLACE(v_transport_list, ' ', '')) > 0 THEN Amount ELSE 0 END), 0),
           COALESCE(SUM(CASE WHEN DATE(Date) = v_date AND v_include_others = 1 AND FIND_IN_SET(ServiceId, REPLACE(v_conference_list, ' ', '')) > 0 THEN Amount ELSE 0 END), 0),
           COALESCE(SUM(CASE WHEN DATE(Date) BETWEEN v_month_start AND v_date AND v_include_others = 1 AND FIND_IN_SET(ServiceId, REPLACE(v_conference_list, ' ', '')) > 0 THEN Amount ELSE 0 END), 0),
           COALESCE(SUM(CASE WHEN DATE(Date) = v_date AND v_include_others = 1 AND FIND_IN_SET(ServiceId, REPLACE(v_vpth_list, ' ', '')) > 0 THEN Amount ELSE 0 END), 0),
           COALESCE(SUM(CASE WHEN DATE(Date) BETWEEN v_month_start AND v_date AND v_include_others = 1 AND FIND_IN_SET(ServiceId, REPLACE(v_vpth_list, ' ', '')) > 0 THEN Amount ELSE 0 END), 0),
           COALESCE(SUM(CASE WHEN DATE(Date) = v_date AND v_include_others = 1 AND FIND_IN_SET(ServiceId, REPLACE(v_shop_list, ' ', '')) > 0 THEN Amount ELSE 0 END), 0),
           COALESCE(SUM(CASE WHEN DATE(Date) BETWEEN v_month_start AND v_date AND v_include_others = 1 AND FIND_IN_SET(ServiceId, REPLACE(v_shop_list, ' ', '')) > 0 THEN Amount ELSE 0 END), 0),
           COALESCE(SUM(CASE WHEN DATE(Date) = v_date AND v_include_others = 1 AND FIND_IN_SET(ServiceId, REPLACE(v_revenue_list, ' ', '')) = 0 AND FIND_IN_SET(ServiceId, REPLACE(v_fb_list, ' ', '')) = 0 AND FIND_IN_SET(ServiceId, REPLACE(v_conference_list, ' ', '')) = 0 AND FIND_IN_SET(ServiceId, REPLACE(v_minibar_list, ' ', '')) = 0 AND FIND_IN_SET(ServiceId, REPLACE(v_laundry_list, ' ', '')) = 0 AND FIND_IN_SET(ServiceId, REPLACE(v_transport_list, ' ', '')) = 0 AND FIND_IN_SET(ServiceId, REPLACE(v_vpth_list, ' ', '')) = 0 AND FIND_IN_SET(ServiceId, REPLACE(v_shop_list, ' ', '')) = 0 AND COALESCE(DepartmentId, '') <> 'FB' THEN Amount ELSE 0 END), 0),
           COALESCE(SUM(CASE WHEN DATE(Date) BETWEEN v_month_start AND v_date AND v_include_others = 1 AND FIND_IN_SET(ServiceId, REPLACE(v_revenue_list, ' ', '')) = 0 AND FIND_IN_SET(ServiceId, REPLACE(v_fb_list, ' ', '')) = 0 AND FIND_IN_SET(ServiceId, REPLACE(v_conference_list, ' ', '')) = 0 AND FIND_IN_SET(ServiceId, REPLACE(v_minibar_list, ' ', '')) = 0 AND FIND_IN_SET(ServiceId, REPLACE(v_laundry_list, ' ', '')) = 0 AND FIND_IN_SET(ServiceId, REPLACE(v_transport_list, ' ', '')) = 0 AND FIND_IN_SET(ServiceId, REPLACE(v_vpth_list, ' ', '')) = 0 AND FIND_IN_SET(ServiceId, REPLACE(v_shop_list, ' ', '')) = 0 AND COALESCE(DepartmentId, '') <> 'FB' THEN Amount ELSE 0 END), 0)
      INTO v_total_day, v_total_month, v_room_day, v_room_month, v_fb_day, v_fb_month,
           v_mb_day, v_mb_month, v_la_day, v_la_month, v_transport_day, v_transport_month,
           v_conference_day, v_conference_month, v_vpth_day, v_vpth_month,
           v_shop_day, v_shop_month, v_other_day, v_other_month
      FROM service_bills
     WHERE COALESCE(Edit, 0) = 0 AND COALESCE(Status, 1) <> 3
       AND DATE(Date) BETWEEN v_month_start AND v_date;

    SELECT COUNT(*) INTO v_checkin FROM booking_rooms
     WHERE arrival_date = v_date AND status IN (0,1,2) AND (room_number IS NULL OR room_number NOT LIKE '0%');
    SELECT COUNT(*) INTO v_checkout FROM booking_rooms
     WHERE departure_date = v_date AND status IN (0,1,2) AND (room_number IS NULL OR room_number NOT LIKE '0%');

    SELECT COUNT(*) INTO v_inhouse FROM (
        SELECT DISTINCT br.id
        FROM service_bills sb
        INNER JOIN room_night_bills rnb ON rnb.bill_id = sb.Ma AND rnb.is_room_night = 1 AND rnb.date = v_date
        INNER JOIN booking_rooms br ON br.id = sb.RentalRoomId1
        WHERE sb.ServiceId = 'RM' AND COALESCE(sb.Edit, 0) = 0 AND br.status IN (1,2,100)
        UNION
        SELECT DISTINCT br.id
        FROM booking_rooms br
        WHERE br.status = 1 AND v_date >= br.arrival_date AND v_date < br.departure_date
          AND br.room_number IS NOT NULL AND br.room_number NOT LIKE '0%'
    ) AS inhouse_rows;

    SELECT COUNT(*) INTO v_foc_day
      FROM service_bills sb INNER JOIN room_night_bills rnb ON rnb.bill_id = sb.Ma AND rnb.is_room_night = 1
     WHERE sb.ServiceId = 'RM' AND COALESCE(sb.Edit, 0) = 0 AND rnb.date = v_date
       AND (rnb.rate_code = 'FOC' OR (COALESCE(rnb.rate, 0) = 0 AND COALESCE(rnb.rate_code, '') <> 'HU'));
    SELECT COUNT(*) INTO v_foc_month
      FROM service_bills sb INNER JOIN room_night_bills rnb ON rnb.bill_id = sb.Ma AND rnb.is_room_night = 1
     WHERE sb.ServiceId = 'RM' AND COALESCE(sb.Edit, 0) = 0 AND rnb.date BETWEEN v_month_start AND v_date
       AND (rnb.rate_code = 'FOC' OR (COALESCE(rnb.rate, 0) = 0 AND COALESCE(rnb.rate_code, '') <> 'HU'));

    SELECT COUNT(*) INTO v_room_available FROM rooms WHERE COALESCE(is_internal, 0) = 0;
    SET v_room_available = GREATEST(v_room_available - (
        SELECT COUNT(DISTINCT room_number) FROM room_locks
         WHERE lock_type IN ('OOO','OOS') AND is_active IN (1,2)
           AND start_date <= CONCAT(v_date, ' 23:59:59') AND end_date >= CONCAT(v_date, ' 00:00:00')
    ), 0);
    SET v_occ = CASE WHEN v_room_available > 0 THEN ROUND(v_inhouse * 100 / v_room_available, 2) ELSE 0 END;
    SET v_adr = CASE WHEN v_inhouse > 0 THEN ROUND(v_room_day / v_inhouse, 0) ELSE 0 END;

    SELECT * FROM (
        SELECT '1-1' AS SortOrder, 1 AS GroupIndex, 'Tổng doanh thu' AS Content, v_total_day AS DateAmount, v_total_month AS MonthAmount, NULL AS PlanAmount, NULL AS Rate, 1 AS IsBold, '' AS CustomText
        UNION ALL SELECT '1-2',1,'Doanh thu phòng',v_room_day,v_room_month,NULL,NULL,0,''
        UNION ALL SELECT '1-3',1,'Doanh thu nhà hàng',v_fb_day,v_fb_month,NULL,NULL,0,''
        UNION ALL SELECT '1-4',1,'Doanh thu hội nghị',v_conference_day,v_conference_month,NULL,NULL,0,''
        UNION ALL SELECT '1-5',1,'Doanh thu Minibar',v_mb_day,v_mb_month,NULL,NULL,0,''
        UNION ALL SELECT '1-6',1,'Doanh thu VPTH',v_vpth_day,v_vpth_month,NULL,NULL,0,''
        UNION ALL SELECT '1-7',1,'Doanh thu giặt ủi',v_la_day,v_la_month,NULL,NULL,0,''
        UNION ALL SELECT '1-8',1,'Doanh thu shop',v_shop_day,v_shop_month,NULL,NULL,0,''
        UNION ALL SELECT '1-9',1,'Doanh thu vận chuyển',v_transport_day,v_transport_month,NULL,NULL,0,''
        UNION ALL SELECT '1-10',1,'Doanh thu dịch vụ khác',v_other_day,v_other_month,NULL,NULL,0,''
        UNION ALL SELECT '1-11',1,'FOC',v_foc_day,v_foc_month,NULL,NULL,0,''
        UNION ALL SELECT '2-1',2,'Hoạt động khách sạn',NULL,NULL,NULL,NULL,1,''
        UNION ALL SELECT '2-2',2,'Số phòng In house',v_inhouse,NULL,NULL,NULL,0,''
        UNION ALL SELECT '2-3',2,'Check in',v_checkin,NULL,NULL,NULL,0,''
        UNION ALL SELECT '2-4',2,'Check out',v_checkout,NULL,NULL,NULL,0,''
        UNION ALL SELECT '2-5',2,'Số phòng ở cuối ngày',v_inhouse,NULL,NULL,NULL,0,''
        UNION ALL SELECT '3-1',3,'Công suất phòng (OCC)/%',v_occ,NULL,NULL,NULL,1,CONCAT(v_occ,'%')
        UNION ALL SELECT '4-1',4,'Giá phòng bình quân (ADR)',v_adr,NULL,NULL,NULL,1,''
        UNION ALL SELECT '5-1',5,'Ý kiến khách hàng',NULL,NULL,NULL,NULL,1,''
        UNION ALL SELECT '6-1',6,'Tình trạng cơ sở vật chất',NULL,NULL,NULL,NULL,1,'Đã hoàn tất'
        UNION ALL SELECT '7-1',7,'Đề xuất',NULL,NULL,NULL,NULL,1,''
    ) AS result_rows ORDER BY GroupIndex, CAST(SUBSTRING_INDEX(SortOrder, '-', -1) AS UNSIGNED);
END
SQL;
    }
    private function companyOccupancyDetailProcedure(): string
    {
        return <<<'SQL'
CREATE PROCEDURE rpt_company_occupancy_detail(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_area VARCHAR(20),
    IN p_company VARCHAR(50),
    IN p_segment VARCHAR(20),
    IN p_user_sale VARCHAR(50),
    IN p_source_code VARCHAR(20)
)
READS SQL DATA
BEGIN
    DECLARE v_prefix VARCHAR(50) DEFAULT '';
    SELECT COALESCE(prefix_booking_id, '') INTO v_prefix FROM hotel_settings ORDER BY id LIMIT 1;

    DROP TEMPORARY TABLE IF EXISTS tmp_company_occupancy_detail;
    CREATE TEMPORARY TABLE tmp_company_occupancy_detail AS
    SELECT
        CONCAT(v_prefix, b.id) AS BookingCode,
        COALESCE(NULLIF(b.external_booking_code, ''), '') AS ReferenceCode,
        COALESCE(NULLIF(c.name, ''), 'KHÁCH LẺ') AS CompanyName,
        COALESCE(NULLIF(b.booking_name, ''), '') AS GuestName,
        COALESCE(NULLIF(m.code, ''), '') AS MarketSegment,
        COALESCE(NULLIF(cs.code, ''), '') AS SourceCode,
        DATE_FORMAT(b.booking_date, '%d/%m/%Y') AS BookingDate,
        DATE_FORMAT(b.arrival_date, '%d/%m/%Y') AS ArrivalDate,
        DATE_FORMAT(b.departure_date, '%d/%m/%Y') AS DepartureDate,
        GREATEST(DATEDIFF(b.departure_date, b.arrival_date), 0) AS NoOfNight,
        (SELECT COUNT(*) FROM booking_rooms br0 WHERE br0.booking_id = b.id AND br0.status NOT IN (3,100)) AS NoOfRoom,
        COALESCE(rev.RoomNight, 0) AS RoomNight,
        COALESCE(rev.RoomNight, 0) * COALESCE((SELECT SUM(br1.adults + br1.children_qty + br1.babies) FROM booking_rooms br1 WHERE br1.booking_id = b.id AND br1.status NOT IN (3,100)), 0) AS GuestNight,
        CASE WHEN COALESCE(rev.RoomNight, 0) > 0 THEN ROUND(rev.RoomRevenue / rev.RoomNight, 0) ELSE 0 END AS AverageRate,
        CASE WHEN COALESCE(rev.RoomNight, 0) > 0 THEN ROUND(rev.OriginalAmount / rev.RoomNight, 0) ELSE 0 END AS AverageRateOriginal,
        COALESCE(rev.RoomRevenue, 0) AS RoomRevenue,
        COALESCE(rev.FbRevenue, 0) AS FbRevenue,
        COALESCE(rev.OtherRevenue, 0) AS OtherRevenue,
        COALESCE(rev.RoomRevenue, 0) + COALESCE(rev.FbRevenue, 0) + COALESCE(rev.OtherRevenue, 0) AS TotalRevenue,
        COALESCE((SELECT rc.code FROM booking_rooms br2 LEFT JOIN room_classes rc ON rc.id = br2.room_class_id WHERE br2.booking_id = b.id AND br2.status NOT IN (3,100) ORDER BY br2.id LIMIT 1), '') AS RoomType,
        COALESCE((SELECT g.nationality_code FROM booking_room_guests brg INNER JOIN guests g ON g.id = brg.guest_id INNER JOIN booking_rooms br3 ON br3.id = brg.booking_room_id WHERE br3.booking_id = b.id ORDER BY brg.is_primary DESC, brg.id LIMIT 1), '') AS Nationality
    FROM bookings AS b
    LEFT JOIN companies AS c ON c.id = b.company_id
    LEFT JOIN markets AS m ON m.id = b.market_id
    LEFT JOIN customer_sources AS cs ON cs.id = b.customer_source_id
    LEFT JOIN (
        SELECT
            x.booking_id,
            SUM(CASE WHEN x.ServiceId IN ('RM','EB','ER','LO','TB','DN','GN','HN','HT','LH','MR','MS','NB','TO','WS') THEN x.Amount ELSE 0 END) AS RoomRevenue,
            SUM(CASE WHEN x.DepartmentId = 'FB' OR x.ServiceId = 'FB' THEN x.Amount ELSE 0 END) AS FbRevenue,
            SUM(CASE WHEN x.ServiceId NOT IN ('RM','EB','ER','LO','TB','DN','GN','HN','HT','LH','MR','MS','NB','TO','WS','FB') AND COALESCE(x.DepartmentId, '') <> 'FB' THEN x.Amount ELSE 0 END) AS OtherRevenue,
            SUM(CASE WHEN x.ServiceId = 'RM' AND x.is_room_night = 1 THEN 1 ELSE 0 END) AS RoomNight,
            SUM(CASE WHEN x.ServiceId IN ('RM','EB','ER','LO','TB','DN','GN','HN','HT','LH','MR','MS','NB','TO','WS') THEN x.OriginalAmount ELSE 0 END) AS OriginalAmount
        FROM (
            SELECT COALESCE(br.booking_id, NULLIF(sb.RegisterID2, 0)) AS booking_id,
                   sb.ServiceId, sb.DepartmentId, COALESCE(sb.Amount, 0) AS Amount,
                   CASE WHEN rnb.is_room_night = 1 AND sb.ServiceId = 'RM' THEN 1 ELSE 0 END AS is_room_night,
                   CASE WHEN sb.ServiceId IN ('RM','EB','ER','LO','TB','DN','GN','HN','HT','LH','MR','MS','NB','TO','WS') THEN COALESCE(NULLIF(rnb.rate, 0), sb.Amount) ELSE 0 END AS OriginalAmount
            FROM service_bills sb
            LEFT JOIN booking_rooms br ON br.id = COALESCE(sb.RentalRoomId2, sb.RentalRoomId1)
            LEFT JOIN room_night_bills rnb ON rnb.bill_id = sb.Ma
            WHERE COALESCE(sb.Edit, 0) = 0 AND COALESCE(sb.Status, 1) <> 3
              AND DATE(COALESCE(rnb.date, sb.Date)) BETWEEN COALESCE(p_from_date, CURRENT_DATE()) AND COALESCE(p_to_date, CURRENT_DATE())
        ) AS x
        WHERE x.booking_id IS NOT NULL
        GROUP BY x.booking_id
    ) AS rev ON rev.booking_id = b.id
    WHERE b.deleted_at IS NULL AND b.status NOT IN (3,4)
      AND (rev.booking_id IS NOT NULL OR (b.arrival_date <= COALESCE(p_to_date, CURRENT_DATE()) AND b.departure_date >= COALESCE(p_from_date, CURRENT_DATE())))
      AND (COALESCE(p_company, '') = '' OR CAST(b.company_id AS CHAR) = p_company OR c.code = p_company)
      AND (COALESCE(p_segment, '') = '' OR CAST(b.market_id AS CHAR) = p_segment OR m.code = p_segment)
      AND (COALESCE(p_source_code, '') = '' OR CAST(b.customer_source_id AS CHAR) = p_source_code OR cs.code = p_source_code)
      AND (COALESCE(p_user_sale, '') = '' OR b.sales_person = p_user_sale OR b.created_by = p_user_sale)
      AND (COALESCE(p_area, '') = '' OR EXISTS (
          SELECT 1 FROM booking_rooms bra INNER JOIN rooms rra ON rra.room_number = bra.room_number
           WHERE bra.booking_id = b.id AND rra.area = p_area
      ));

    SELECT * FROM tmp_company_occupancy_detail ORDER BY ArrivalDate, BookingCode;
    DROP TEMPORARY TABLE IF EXISTS tmp_company_occupancy_detail;
END
SQL;
    }
    private function companyOccupancyProcedure(): string
    {
        return <<<'SQL'
CREATE PROCEDURE rpt_company_occupancy(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_area VARCHAR(20),
    IN p_company VARCHAR(50),
    IN p_segment VARCHAR(20),
    IN p_user_sale VARCHAR(50),
    IN p_source_code VARCHAR(20),
    IN p_group_by VARCHAR(20),
    IN p_include_breakfast TINYINT
)
READS SQL DATA
BEGIN
    DECLARE v_capacity DECIMAL(15,2) DEFAULT 0;
    SELECT COUNT(*) * (DATEDIFF(COALESCE(p_to_date, CURRENT_DATE()), COALESCE(p_from_date, CURRENT_DATE())) + 1)
      INTO v_capacity FROM rooms WHERE COALESCE(is_internal, 0) = 0;
    SET v_capacity = GREATEST(v_capacity, 1);

    DROP TEMPORARY TABLE IF EXISTS tmp_company_occupancy_base;
    CREATE TEMPORARY TABLE tmp_company_occupancy_base AS
    SELECT
        COALESCE(CAST(b.company_id AS CHAR), 'WALKIN') AS CompanyCode,
        COALESCE(NULLIF(c.name, ''), 'KHÁCH LẺ') AS CompanyName,
        COALESCE(NULLIF(m.code, ''), '') AS MarketCode,
        COALESCE(NULLIF(m.name, ''), NULLIF(m.code, ''), '') AS MarketName,
        COALESCE(NULLIF(cs.code, ''), '') AS SourceCode,
        COALESCE(NULLIF(cs.name, ''), NULLIF(cs.code, ''), '') AS SourceName,
        b.arrival_date AS ActivityDate,
        COALESCE(rev.RoomNight, 0) AS RoomNight,
        COALESCE((SELECT SUM(br1.adults + br1.children_qty + br1.babies) FROM booking_rooms br1 WHERE br1.booking_id = b.id AND br1.status NOT IN (3,100)), 0) AS GuestQty,
        COALESCE(rev.FocRoomNights, 0) AS FocRoomNights,
        COALESCE(rev.HuRoomNights, 0) AS HuRoomNights,
        COALESCE(rev.RoomRevenue, 0) AS RoomRevenue,
        COALESCE(rev.FbRevenue, 0) AS FbRevenue,
        COALESCE(rev.OtherRevenue, 0) AS OtherRevenue,
        COALESCE(rev.OriginalAmount, 0) AS OriginalAmount
    FROM bookings b
    LEFT JOIN companies c ON c.id = b.company_id
    LEFT JOIN markets m ON m.id = b.market_id
    LEFT JOIN customer_sources cs ON cs.id = b.customer_source_id
    LEFT JOIN (
        SELECT x.booking_id,
               SUM(CASE WHEN x.ServiceId IN ('RM','EB','ER','LO','TB','DN','GN','HN','HT','LH','MR','MS','NB','TO','WS') THEN x.Amount - CASE WHEN COALESCE(p_include_breakfast, 1) = 0 THEN x.BreakfastAmount ELSE 0 END ELSE 0 END) AS RoomRevenue,
               SUM(CASE WHEN x.DepartmentId = 'FB' OR x.ServiceId = 'FB' THEN x.Amount ELSE 0 END)
                 + CASE WHEN COALESCE(p_include_breakfast, 1) = 0 THEN SUM(x.BreakfastAmount) ELSE 0 END AS FbRevenue,
               SUM(CASE WHEN x.ServiceId NOT IN ('RM','EB','ER','LO','TB','DN','GN','HN','HT','LH','MR','MS','NB','TO','WS','FB') AND COALESCE(x.DepartmentId, '') <> 'FB' THEN x.Amount ELSE 0 END) AS OtherRevenue,
               SUM(CASE WHEN x.ServiceId = 'RM' AND x.is_room_night = 1 THEN 1 ELSE 0 END) AS RoomNight,
               SUM(CASE WHEN x.ServiceId = 'RM' AND x.is_room_night = 1 AND x.RateCode = 'FOC' THEN 1 ELSE 0 END) AS FocRoomNights,
               SUM(CASE WHEN x.ServiceId = 'RM' AND x.is_room_night = 1 AND x.RateCode = 'HU' THEN 1 ELSE 0 END) AS HuRoomNights,
               SUM(CASE WHEN x.ServiceId IN ('RM','EB','ER','LO','TB','DN','GN','HN','HT','LH','MR','MS','NB','TO','WS') THEN x.OriginalAmount ELSE 0 END) AS OriginalAmount
        FROM (
            SELECT COALESCE(br.booking_id, NULLIF(sb.RegisterID2, 0)) AS booking_id, sb.ServiceId, sb.DepartmentId,
                   COALESCE(sb.Amount, 0) AS Amount, COALESCE(rnb.breakfast_amount, 0) AS BreakfastAmount,
                   CASE WHEN rnb.is_room_night = 1 AND sb.ServiceId = 'RM' THEN 1 ELSE 0 END AS is_room_night,
                   COALESCE(rnb.rate_code, '') AS RateCode,
                   CASE WHEN sb.ServiceId IN ('RM','EB','ER','LO','TB','DN','GN','HN','HT','LH','MR','MS','NB','TO','WS') THEN COALESCE(NULLIF(rnb.rate, 0), sb.Amount) ELSE 0 END AS OriginalAmount
            FROM service_bills sb
            LEFT JOIN booking_rooms br ON br.id = COALESCE(sb.RentalRoomId2, sb.RentalRoomId1)
            LEFT JOIN room_night_bills rnb ON rnb.bill_id = sb.Ma
            WHERE COALESCE(sb.Edit, 0) = 0 AND COALESCE(sb.Status, 1) <> 3
              AND DATE(COALESCE(rnb.date, sb.Date)) BETWEEN COALESCE(p_from_date, CURRENT_DATE()) AND COALESCE(p_to_date, CURRENT_DATE())
        ) x
        WHERE x.booking_id IS NOT NULL
        GROUP BY x.booking_id
    ) rev ON rev.booking_id = b.id
    WHERE b.deleted_at IS NULL AND b.status NOT IN (3,4)
      AND rev.booking_id IS NOT NULL
      AND (COALESCE(p_company, '') = '' OR CAST(b.company_id AS CHAR) = p_company OR c.code = p_company)
      AND (COALESCE(p_segment, '') = '' OR CAST(b.market_id AS CHAR) = p_segment OR m.code = p_segment)
      AND (COALESCE(p_source_code, '') = '' OR CAST(b.customer_source_id AS CHAR) = p_source_code OR cs.code = p_source_code)
      AND (COALESCE(p_user_sale, '') = '' OR b.sales_person = p_user_sale OR b.created_by = p_user_sale)
      AND (COALESCE(p_area, '') = '' OR EXISTS (SELECT 1 FROM booking_rooms bra INNER JOIN rooms rra ON rra.room_number = bra.room_number WHERE bra.booking_id = b.id AND rra.area = p_area));

    SELECT
        grouped_rows.CompanyCode,
        grouped_rows.CompanyName,
        ROUND(SUM(grouped_rows.RoomNight) * 100 / v_capacity, 2) AS OccupancyRate,
        SUM(grouped_rows.RoomNight) AS RoomNight,
        SUM(grouped_rows.GuestQty) AS GuestQty,
        CASE WHEN SUM(grouped_rows.RoomNight) > 0 THEN ROUND(SUM(grouped_rows.RoomRevenue) / SUM(grouped_rows.RoomNight), 0) ELSE 0 END AS ActualADR,
        CASE WHEN SUM(grouped_rows.RoomNight - grouped_rows.FocRoomNights - grouped_rows.HuRoomNights) > 0 THEN ROUND(SUM(grouped_rows.OriginalAmount) / SUM(grouped_rows.RoomNight - grouped_rows.FocRoomNights - grouped_rows.HuRoomNights), 0) ELSE 0 END AS RackADR,
        SUM(grouped_rows.RoomRevenue) AS RoomRevenue,
        SUM(grouped_rows.FbRevenue) AS FbRevenue,
        SUM(grouped_rows.OtherRevenue) AS OtherRevenue,
        SUM(grouped_rows.RoomRevenue + grouped_rows.FbRevenue + grouped_rows.OtherRevenue) AS TotalRevenue
    FROM (
        SELECT
            CASE COALESCE(p_group_by, 'COMPANY') WHEN 'DATE' THEN DATE_FORMAT(ActivityDate, '%d/%m/%Y') WHEN 'MARKET' THEN MarketCode WHEN 'SOURCE' THEN SourceCode ELSE CompanyCode END AS CompanyCode,
            CASE COALESCE(p_group_by, 'COMPANY') WHEN 'DATE' THEN DATE_FORMAT(ActivityDate, '%d/%m/%Y') WHEN 'MARKET' THEN MarketName WHEN 'SOURCE' THEN SourceName ELSE CompanyName END AS CompanyName,
            RoomNight, GuestQty, FocRoomNights, HuRoomNights, RoomRevenue, FbRevenue, OtherRevenue, OriginalAmount
        FROM tmp_company_occupancy_base
    ) AS grouped_rows
    GROUP BY grouped_rows.CompanyCode, grouped_rows.CompanyName
    ORDER BY TotalRevenue DESC, grouped_rows.CompanyName;

    DROP TEMPORARY TABLE IF EXISTS tmp_company_occupancy_base;
END
SQL;
    }
    private function salespersonRevenueSummaryProcedure(): string { return $this->salespersonRevenueProcedure(false); }
    private function salespersonRevenueDetailProcedure(): string { return $this->salespersonRevenueProcedure(true); }

    private function salespersonRevenueProcedure(bool $detail): string
    {
        $select = $detail
            ? "SELECT BookingCode, BookingName, ArrivalDate, DepartureDate, RoomNights, FocRoomNights, GuestQty, CompanyName, MarketSegment, RoomRevenue, FbRevenue, OtherRevenue, TotalRevenue, SalesPersonName FROM tmp_salesperson_revenue_base ORDER BY SalesPersonName, ArrivalDate, BookingCode;"
            : "SELECT SalesPersonCode, SalesPersonName, ROUND(SUM(RoomNights) * 100 / v_capacity, 2) AS OccupancyRate, SUM(RoomNights) AS RoomNights, SUM(GuestQty) AS GuestQty, CASE WHEN SUM(RoomNights) > 0 THEN ROUND(SUM(RoomRevenue) / SUM(RoomNights), 0) ELSE 0 END AS ActualADR, CASE WHEN SUM(RoomNights - FocRoomNights - HuRoomNights) > 0 THEN ROUND(SUM(OriginalAmount) / SUM(RoomNights - FocRoomNights - HuRoomNights), 0) ELSE 0 END AS RackADR, SUM(RoomRevenue) AS RoomRevenue, SUM(FbRevenue) AS FbRevenue, SUM(OtherRevenue) AS OtherRevenue, SUM(RoomRevenue + FbRevenue + OtherRevenue) AS TotalRevenue FROM tmp_salesperson_revenue_base GROUP BY SalesPersonCode, SalesPersonName ORDER BY TotalRevenue DESC, SalesPersonName;";

        $procedure = $detail ? 'rpt_salesperson_revenue_detail' : 'rpt_salesperson_revenue_summary';
        return str_replace(['__PROCEDURE_NAME__', '__FINAL_SELECT__'], [$procedure, $select], <<<'SQL'
CREATE PROCEDURE __PROCEDURE_NAME__(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_filter_mode INT,
    IN p_sales_person VARCHAR(50),
    IN p_market_segment VARCHAR(50),
    IN p_company_id VARCHAR(50),
    IN p_group_by VARCHAR(20)
)
READS SQL DATA
BEGIN
    DECLARE v_prefix VARCHAR(50) DEFAULT '';
    DECLARE v_capacity DECIMAL(15,2) DEFAULT 0;
    SELECT COALESCE(prefix_booking_id, '') INTO v_prefix FROM hotel_settings ORDER BY id LIMIT 1;
    SELECT COUNT(*) * (DATEDIFF(COALESCE(p_to_date, CURRENT_DATE()), COALESCE(p_from_date, CURRENT_DATE())) + 1)
      INTO v_capacity FROM rooms WHERE COALESCE(is_internal, 0) = 0;
    SET v_capacity = GREATEST(v_capacity, 1);

    DROP TEMPORARY TABLE IF EXISTS tmp_salesperson_revenue_base;
    CREATE TEMPORARY TABLE tmp_salesperson_revenue_base AS
    SELECT
        CONCAT(v_prefix, b.id) AS BookingCode,
        COALESCE(NULLIF(b.booking_name, ''), '') AS BookingName,
        DATE_FORMAT(b.arrival_date, '%d/%m/%Y') AS ArrivalDate,
        DATE_FORMAT(b.departure_date, '%d/%m/%Y') AS DepartureDate,
        CASE WHEN COALESCE(p_filter_mode, 1) = 1 THEN GREATEST(DATEDIFF(b.departure_date, b.arrival_date), 0) * (SELECT COUNT(*) FROM booking_rooms br0 WHERE br0.booking_id = b.id AND br0.status NOT IN (3,100)) ELSE COALESCE(rev.RoomNight, 0) END AS RoomNights,
        COALESCE(rev.FocRoomNights, 0) AS FocRoomNights,
        COALESCE((SELECT SUM(br1.adults + br1.children_qty + br1.babies) FROM booking_rooms br1 WHERE br1.booking_id = b.id AND br1.status NOT IN (3,100)), 0) AS GuestQty,
        COALESCE(NULLIF(c.name, ''), 'KHÁCH LẺ') AS CompanyName,
        COALESCE(NULLIF(m.name, ''), NULLIF(m.code, ''), '') AS MarketSegment,
        COALESCE(rev.RoomRevenue, 0) AS RoomRevenue,
        COALESCE(rev.FbRevenue, 0) AS FbRevenue,
        COALESCE(rev.OtherRevenue, 0) AS OtherRevenue,
        COALESCE(rev.RoomRevenue, 0) + COALESCE(rev.FbRevenue, 0) + COALESCE(rev.OtherRevenue, 0) AS TotalRevenue,
        CASE WHEN COALESCE(NULLIF(b.sales_person, ''), NULLIF(b.created_by, '')) IS NULL THEN 'UNASSIGNED' ELSE COALESCE(NULLIF(b.sales_person, ''), NULLIF(b.created_by, '')) END AS SalesPersonCode,
        COALESCE(NULLIF(b.sales_person, ''), NULLIF(b.created_by, ''), 'Chưa phân công') AS SalesPersonName,
        COALESCE(rev.HuRoomNights, 0) AS HuRoomNights,
        COALESCE(rev.OriginalAmount, 0) AS OriginalAmount
    FROM bookings b
    LEFT JOIN companies c ON c.id = b.company_id
    LEFT JOIN markets m ON m.id = b.market_id
    LEFT JOIN (
        SELECT x.booking_id,
               SUM(CASE WHEN x.ServiceId IN ('RM','EB','ER','LO','TB','DN','GN','HN','HT','LH','MR','MS','NB','TO','WS') THEN x.Amount ELSE 0 END) AS RoomRevenue,
               SUM(CASE WHEN x.DepartmentId = 'FB' OR x.ServiceId = 'FB' THEN x.Amount ELSE 0 END) AS FbRevenue,
               SUM(CASE WHEN x.ServiceId NOT IN ('RM','EB','ER','LO','TB','DN','GN','HN','HT','LH','MR','MS','NB','TO','WS','FB') AND COALESCE(x.DepartmentId, '') <> 'FB' THEN x.Amount ELSE 0 END) AS OtherRevenue,
               SUM(CASE WHEN x.ServiceId = 'RM' AND x.is_room_night = 1 THEN 1 ELSE 0 END) AS RoomNight,
               SUM(CASE WHEN x.ServiceId = 'RM' AND x.is_room_night = 1 AND x.RateCode = 'FOC' THEN 1 ELSE 0 END) AS FocRoomNights,
               SUM(CASE WHEN x.ServiceId = 'RM' AND x.is_room_night = 1 AND x.RateCode = 'HU' THEN 1 ELSE 0 END) AS HuRoomNights,
               SUM(CASE WHEN x.ServiceId IN ('RM','EB','ER','LO','TB','DN','GN','HN','HT','LH','MR','MS','NB','TO','WS') THEN x.OriginalAmount ELSE 0 END) AS OriginalAmount
        FROM (
            SELECT COALESCE(br.booking_id, NULLIF(sb.RegisterID2, 0)) AS booking_id, sb.ServiceId, sb.DepartmentId, COALESCE(sb.Amount, 0) AS Amount,
                   CASE WHEN rnb.is_room_night = 1 AND sb.ServiceId = 'RM' THEN 1 ELSE 0 END AS is_room_night,
                   COALESCE(rnb.rate_code, '') AS RateCode,
                   CASE WHEN sb.ServiceId IN ('RM','EB','ER','LO','TB','DN','GN','HN','HT','LH','MR','MS','NB','TO','WS') THEN COALESCE(NULLIF(rnb.rate, 0), sb.Amount) ELSE 0 END AS OriginalAmount
            FROM service_bills sb
            LEFT JOIN booking_rooms br ON br.id = COALESCE(sb.RentalRoomId2, sb.RentalRoomId1)
            LEFT JOIN bookings bx ON bx.id = COALESCE(br.booking_id, NULLIF(sb.RegisterID2, 0))
            LEFT JOIN room_night_bills rnb ON rnb.bill_id = sb.Ma
            WHERE COALESCE(sb.Edit, 0) = 0 AND COALESCE(sb.Status, 1) <> 3
              AND (
                  (COALESCE(p_filter_mode, 1) = 1 AND DATE(COALESCE(rnb.date, sb.Date)) BETWEEN COALESCE(bx.arrival_date, DATE(COALESCE(rnb.date, sb.Date))) AND COALESCE(bx.departure_date, DATE(COALESCE(rnb.date, sb.Date))))
                  OR (COALESCE(p_filter_mode, 1) = 2 AND DATE(COALESCE(rnb.date, sb.Date)) BETWEEN COALESCE(p_from_date, CURRENT_DATE()) AND COALESCE(p_to_date, CURRENT_DATE()))
              )
        ) x
        WHERE x.booking_id IS NOT NULL
        GROUP BY x.booking_id
    ) rev ON rev.booking_id = b.id
    WHERE b.deleted_at IS NULL AND b.status NOT IN (3,4)
      AND ((COALESCE(p_filter_mode, 1) = 1 AND b.arrival_date BETWEEN COALESCE(p_from_date, CURRENT_DATE()) AND COALESCE(p_to_date, CURRENT_DATE())) OR (COALESCE(p_filter_mode, 1) = 2 AND rev.booking_id IS NOT NULL))
      AND (COALESCE(p_sales_person, '') = '' OR b.sales_person = p_sales_person OR b.created_by = p_sales_person)
      AND (COALESCE(p_market_segment, '') = '' OR CAST(b.market_id AS CHAR) = p_market_segment OR m.code = p_market_segment)
      AND (COALESCE(p_company_id, '') = '' OR p_company_id = '0' OR CAST(b.company_id AS CHAR) = p_company_id);

    __FINAL_SELECT__
    DROP TEMPORARY TABLE IF EXISTS tmp_salesperson_revenue_base;
END
SQL);
    }
};
