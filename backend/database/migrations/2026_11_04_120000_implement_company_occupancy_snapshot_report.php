<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createSnapshotTables();

        $connection = DB::getDefaultConnection();
        $db = DB::connection($connection);
        if ($db->getDriverName() !== 'mysql') {
            return;
        }

        $db->unprepared('DROP PROCEDURE IF EXISTS `rpt_company_occupancy`');
        $db->unprepared($this->summaryProcedure());
        $db->unprepared('DROP PROCEDURE IF EXISTS `rpt_company_occupancy_detail`');
        $db->unprepared($this->detailProcedure());
        $this->syncReportConfiguration($db);
    }

    public function down(): void
    {
        Schema::dropIfExists('night_audit_company_occupancy_snapshots');
        Schema::dropIfExists('night_audit_company_occupancy_daily_snapshots');
    }

    private function createSnapshotTables(): void
    {
        if (! Schema::hasTable('night_audit_company_occupancy_daily_snapshots')) {
            Schema::create('night_audit_company_occupancy_daily_snapshots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('night_audit_run_id')->index('idx_na_company_occ_daily_run');
                $table->date('snapshot_date')->unique('uniq_na_company_occ_daily_date');
                $table->unsignedInteger('total_rooms')->default(0);
                $table->unsignedInteger('ooo_rooms')->default(0);
                $table->unsignedInteger('room_available')->default(0);
                $table->text('revenue_service_codes')->nullable();
                $table->text('revenue_er_service_codes')->nullable();
                $table->boolean('average_room_rate_include_other_revenue')->default(true);
                $table->boolean('foc_include_compliment')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('night_audit_company_occupancy_snapshots')) {
            Schema::create('night_audit_company_occupancy_snapshots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('night_audit_run_id')->index('idx_na_company_occ_run');
                $table->date('snapshot_date')->index('idx_na_company_occ_date');
                $table->unsignedBigInteger('booking_id')->nullable()->index('idx_na_company_occ_booking');
                $table->string('booking_code', 80)->nullable();
                $table->string('booking_name', 255)->nullable();
                $table->date('booking_date')->nullable();
                $table->date('arrival_date')->nullable();
                $table->date('departure_date')->nullable();
                $table->unsignedBigInteger('company_id')->nullable()->index('idx_na_company_occ_company');
                $table->string('company_code', 80)->nullable();
                $table->string('company_name', 255)->nullable();
                $table->unsignedBigInteger('market_id')->nullable()->index('idx_na_company_occ_market');
                $table->string('market_code', 80)->nullable();
                $table->string('market_name', 255)->nullable();
                $table->unsignedBigInteger('source_id')->nullable()->index('idx_na_company_occ_source');
                $table->string('source_code', 80)->nullable();
                $table->string('source_name', 255)->nullable();
                $table->string('user_sale', 100)->nullable()->index('idx_na_company_occ_user_sale');
                $table->string('area_codes', 500)->nullable();
                $table->string('room_types', 500)->nullable();
                $table->string('nationalities', 500)->nullable();
                $table->unsignedInteger('room_qty')->default(0);
                $table->unsignedInteger('guest_qty')->default(0);
                $table->unsignedInteger('room_night')->default(0);
                $table->unsignedInteger('guest_night')->default(0);
                $table->unsignedInteger('foc_room_nights')->default(0);
                $table->unsignedInteger('hu_room_nights')->default(0);
                $table->decimal('room_revenue', 18, 4)->default(0);
                $table->decimal('breakfast_revenue', 18, 4)->default(0);
                $table->decimal('extra_bed_revenue', 18, 4)->default(0);
                $table->decimal('extra_rollaway_revenue', 18, 4)->default(0);
                $table->decimal('fb_revenue', 18, 4)->default(0);
                $table->decimal('other_revenue', 18, 4)->default(0);
                $table->decimal('total_by_date', 18, 4)->default(0);
                $table->decimal('original_total', 18, 4)->default(0);
                $table->timestamps();

                $table->unique(['snapshot_date', 'booking_id'], 'uniq_na_company_occ_date_booking');
                $table->index(['snapshot_date', 'company_id'], 'idx_na_company_occ_date_company');
            });
        }
    }

    private function syncReportConfiguration($db): void
    {
        if (! $db->getSchemaBuilder()->hasTable('report_data_sources') || ! $db->getSchemaBuilder()->hasTable('report_definitions')) {
            return;
        }

        $now = now();
        $database = $db->getDatabaseName();
        $summaryFields = [
            'CompanyCode' => 'string', 'CompanyName' => 'string', 'TotalRevenue' => 'number', 'RevenuePercentage' => 'number',
            'AverageRevenue' => 'number', 'RoomQty' => 'integer', 'RoomNight' => 'integer', 'FocRoomNights' => 'integer',
            'HuRoomNights' => 'integer', 'OccupancyRate' => 'number', 'GuestQty' => 'integer', 'GuestNight' => 'integer',
            'RoomRevenue' => 'number', 'ExtraBedRevenue' => 'number', 'ExtraRollawayRevenue' => 'number', 'FbRevenue' => 'number',
            'OtherRevenue' => 'number', 'TotalByDate' => 'number', 'OriginalTotal' => 'number', 'ActualADR' => 'number',
            'RackADR' => 'number', 'TotalRoomAvailable' => 'integer', 'RoomAvailable' => 'integer', 'BookingCount' => 'integer',
            'ActivityDate' => 'string', 'GroupHeader' => 'string', 'GroupHeaderId' => 'string', 'Foc' => 'integer', 'Hu' => 'integer',
        ];
        $summaryParameters = $this->parameterList([
            ['p_from_date', 'date'], ['p_to_date', 'date'], ['p_area', 'varchar'], ['p_company', 'varchar'],
            ['p_segment', 'varchar'], ['p_user_sale', 'varchar'], ['p_group_by', 'varchar'], ['p_include_breakfast', 'tinyint'],
        ]);
        $defaults = [
            'p_from_date' => now()->toDateString(), 'p_to_date' => now()->toDateString(), 'p_area' => '', 'p_company' => '',
            'p_segment' => '', 'p_user_sale' => '', 'p_group_by' => 'none', 'p_include_breakfast' => 1,
        ];

        $db->table('report_data_sources')->updateOrInsert(
            ['code' => 'RPT_COMPANY_OCCUPANCY'],
            [
                'name' => 'Báo cáo công suất công ty',
                'description' => 'Snapshot sau sang ngày cho lịch sử; dữ liệu live cho ngày hiện tại và tương lai.',
                'source_type' => 'procedure', 'schema_name' => $database, 'object_name' => 'rpt_company_occupancy',
                'parameter_schema' => json_encode($summaryParameters, JSON_UNESCAPED_UNICODE),
                'field_schema' => json_encode($this->fields($summaryFields), JSON_UNESCAPED_UNICODE),
                'sample_parameters' => json_encode($defaults, JSON_UNESCAPED_UNICODE), 'max_rows' => 5000,
                'is_active' => true, 'last_discovered_at' => $now, 'updated_at' => $now,
            ]
        );

        $sourceId = $db->table('report_data_sources')->where('code', 'RPT_COMPANY_OCCUPANCY')->value('id');
        $ui = $this->summaryUi($db);
        $db->table('report_definitions')->updateOrInsert(
            ['code' => 'COMPANY_OCCUPANCY'],
            [
                'name' => 'Báo cáo công suất công ty', 'group' => 'Báo cáo công suất',
                'description' => 'Dữ liệu lịch sử được khóa tại Night Audit; ngày hiện tại/tương lai lấy từ vận hành.',
                'report_data_source_id' => $sourceId, 'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE),
                'sort_order' => 167, 'is_active' => true, 'show_in_menu' => true,
                'menu_locations' => json_encode(['reservation', 'frontdesk'], JSON_UNESCAPED_UNICODE),
                'menu_top_order' => 20, 'menu_group_order' => 30, 'menu_item_order' => 167,
                'updated_at' => $now, 'created_at' => $now,
            ]
        );

        $template = require database_path('report_templates/company_occupancy_reference.php');
        $definition = $template->definition();
        $templateId = $db->table('templates')->where('report', 'COMPANY_OCCUPANCY_REFERENCE')->value('id');
        $templateValues = [
            'group' => 'Báo cáo công suất', 'name' => $definition['name'], 'report_data_source_id' => $sourceId,
            'parameter_defaults' => json_encode($defaults, JSON_UNESCAPED_UNICODE),
            'page_size' => $definition['page_size'], 'page_orientation' => $definition['page_orientation'],
            'margin_top' => $definition['margin_top'], 'margin_right' => $definition['margin_right'],
            'margin_bottom' => $definition['margin_bottom'], 'margin_left' => $definition['margin_left'],
            'content_json' => json_encode($definition['content_json'], JSON_UNESCAPED_UNICODE),
            'content_html' => $definition['content_html'], 'css' => $definition['css'],
            'is_default' => true, 'version' => $definition['version'], 'updated_at' => $now,
        ];
        if ($templateId) {
            $db->table('templates')->where('id', $templateId)->update($templateValues);
        } else {
            $templateId = $db->table('templates')->insertGetId($templateValues + ['report' => 'COMPANY_OCCUPANCY_REFERENCE', 'created_at' => $now]);
        }
        $reportId = $db->table('report_definitions')->where('code', 'COMPANY_OCCUPANCY')->value('id');
        $db->table('report_definition_template')->updateOrInsert(
            ['report_definition_id' => $reportId, 'template_id' => $templateId],
            ['is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]
        );
    }

    private function parameterList(array $items): array
    {
        return array_map(static fn (array $item, int $index): array => [
            'name' => $item[0], 'mode' => 'IN', 'data_type' => $item[1], 'database_type' => $item[1],
            'position' => $index + 1, 'required' => true,
        ], $items, array_keys($items));
    }

    private function fields(array $fields): array
    {
        return array_map(static fn (string $name, string $type): array => ['name' => $name, 'type' => $type, 'nullable' => true], array_keys($fields), $fields);
    }

    private function summaryUi($db): array
    {
        $segments = $db->table('markets')->orderBy('code')->get(['code', 'name'])
            ->map(static fn ($row): array => ['value' => $row->code, 'label' => trim($row->code.' - '.$row->name, ' -')])->all();
        return [
            ['name' => 'p_from_date', 'label' => 'Chọn ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            ['name' => 'p_company', 'label' => 'Chọn công ty', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'companies'],
            ['name' => 'p_segment', 'label' => 'Thị trường', 'control' => 'select', 'default' => '', 'required' => false, 'options' => [['value' => '', 'label' => 'Tất cả'], ...$segments]],
            ['name' => 'p_user_sale', 'label' => 'Chọn người dùng', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'users'],
            ['name' => 'p_area', 'label' => 'Chọn khu vực', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'areas'],
            ['name' => 'p_show_detail', 'label' => 'Hiển thị chi tiết', 'control' => 'checkbox', 'default' => false, 'required' => false, 'ui_only' => true, 'layout' => ['group' => 'company_occupancy_switches', 'type' => 'two-column', 'row' => 1, 'column' => 1]],
            ['name' => 'p_include_breakfast', 'label' => 'DT phòng bao gồm AS', 'control' => 'checkbox', 'default' => true, 'required' => false, 'layout' => ['group' => 'company_occupancy_switches', 'type' => 'two-column', 'row' => 1, 'column' => 2]],
            ['name' => 'p_group_by_date', 'label' => 'Nhóm theo ngày', 'control' => 'checkbox', 'default' => false, 'required' => false, 'ui_only' => true, 'layout' => ['group' => 'company_occupancy_switches', 'type' => 'two-column', 'row' => 2, 'column' => 1], 'maps_to' => ['parameter' => 'p_group_by', 'value' => 'date', 'priority' => 30, 'empty_value' => 'none', 'combinations' => [['with' => ['p_group_by_market'], 'value' => 'daysegment', 'priority' => 100], ['with' => ['p_group_by_source'], 'value' => 'daysourcecode', 'priority' => 100]]]],
            ['name' => 'p_group_by_market', 'label' => 'Nhóm theo thị trường', 'control' => 'checkbox', 'default' => false, 'required' => false, 'ui_only' => true, 'layout' => ['group' => 'company_occupancy_switches', 'type' => 'two-column', 'row' => 3, 'column' => 1], 'selection_group' => ['key' => 'company_occupancy_group_dimension', 'label' => 'Kiểu nhóm', 'mode' => 'single'], 'maps_to' => ['parameter' => 'p_group_by', 'value' => 'marketsegment', 'priority' => 20, 'empty_value' => 'none']],
            ['name' => 'p_group_by_source', 'label' => 'Nhóm theo nguồn khách', 'control' => 'checkbox', 'default' => false, 'required' => false, 'ui_only' => true, 'layout' => ['group' => 'company_occupancy_switches', 'type' => 'two-column', 'row' => 3, 'column' => 2], 'selection_group' => ['key' => 'company_occupancy_group_dimension', 'label' => 'Kiểu nhóm', 'mode' => 'single'], 'maps_to' => ['parameter' => 'p_group_by', 'value' => 'sourcecode', 'priority' => 10, 'empty_value' => 'none']],
            ['name' => 'p_group_by', 'label' => 'Nhóm theo', 'control' => 'hidden', 'default' => 'none', 'required' => false],
        ];
    }

    private function summaryProcedure(): string
    {
        return <<<'SQL'
CREATE PROCEDURE rpt_company_occupancy(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_area VARCHAR(50),
    IN p_company VARCHAR(50),
    IN p_segment VARCHAR(50),
    IN p_user_sale VARCHAR(50),
    IN p_group_by VARCHAR(30),
    IN p_include_breakfast TINYINT
)
READS SQL DATA
BEGIN
    DECLARE v_from DATE DEFAULT COALESCE(p_from_date, CURRENT_DATE());
    DECLARE v_to DATE DEFAULT COALESCE(p_to_date, COALESCE(p_from_date, CURRENT_DATE()));
    DECLARE v_system_date DATE DEFAULT CURRENT_DATE();
    DECLARE v_operation_date DATE DEFAULT NULL;
    DECLARE v_date DATE DEFAULT NULL;
    DECLARE v_total_rooms INT DEFAULT 1;
    DECLARE v_capacity INT DEFAULT 1;
    DECLARE v_group_by VARCHAR(30) DEFAULT 'none';
    DECLARE v_avg_include_other TINYINT DEFAULT 1;
    DECLARE v_er_codes TEXT DEFAULT 'ER';

    IF v_to < v_from THEN SET v_to = v_from; END IF;
    SELECT CAST(value AS DATE) INTO v_operation_date FROM hotel_configs WHERE LOWER(name) = 'operationdate' AND value <> '' LIMIT 1;
    IF v_operation_date IS NOT NULL AND v_from < v_operation_date THEN SET v_from = v_operation_date; END IF;
    SELECT CAST(value AS DATE) INTO v_system_date FROM hotel_configs WHERE LOWER(name) = 'systemdate' AND value <> '' LIMIT 1;
    IF v_system_date IS NULL THEN SELECT DATE(system_date) INTO v_system_date FROM system_date_rolls ORDER BY id DESC LIMIT 1; END IF;
    IF v_system_date IS NULL THEN SET v_system_date = CURRENT_DATE(); END IF;
    SET v_group_by = LOWER(COALESCE(NULLIF(p_group_by, ''), 'none'));
    IF v_group_by = 'company' THEN SET v_group_by = 'none'; END IF;
    IF v_group_by = 'market' THEN SET v_group_by = 'marketsegment'; END IF;
    IF v_group_by = 'source' THEN SET v_group_by = 'sourcecode'; END IF;
    IF v_group_by = 'day_market' THEN SET v_group_by = 'daysegment'; END IF;
    IF v_group_by = 'day_source' THEN SET v_group_by = 'daysourcecode'; END IF;
    SELECT COALESCE(NULLIF(value, ''), 'ER') INTO v_er_codes FROM hotel_configs WHERE LOWER(name) = 'revenueer' LIMIT 1;
    SELECT CASE WHEN LOWER(COALESCE(value, '1')) IN ('0', 'false', 'no') THEN 0 ELSE 1 END INTO v_avg_include_other FROM hotel_configs WHERE LOWER(name) = 'averageroomrateincludedothersroomrevenue' LIMIT 1;

    SELECT COUNT(*) INTO v_total_rooms FROM rooms WHERE COALESCE(is_internal, 0) = 0 AND room_number NOT LIKE '0%';
    IF v_total_rooms < 1 THEN SET v_total_rooms = 1; END IF;

    DROP TEMPORARY TABLE IF EXISTS tmp_company_occ_calendar;
    CREATE TEMPORARY TABLE tmp_company_occ_calendar (activity_date DATE PRIMARY KEY, room_available INT NOT NULL, avg_include_other TINYINT NOT NULL);
    SET v_date = v_from;
    WHILE v_date <= v_to DO
        IF v_date < v_system_date THEN
            INSERT INTO tmp_company_occ_calendar
            SELECT v_date, d.room_available, d.average_room_rate_include_other_revenue
              FROM night_audit_company_occupancy_daily_snapshots d
             WHERE d.snapshot_date = v_date;
        ELSE
            INSERT INTO tmp_company_occ_calendar
            SELECT v_date,
                   GREATEST(v_total_rooms - COUNT(DISTINCT CASE WHEN COALESCE(rl.is_active, 1) = 1 AND UPPER(COALESCE(rl.lock_type, 'OOO')) = 'OOO' AND v_date BETWEEN DATE(rl.start_date) AND DATE(rl.end_date) THEN rl.room_number END), 0),
                   v_avg_include_other
              FROM rooms r
              LEFT JOIN room_locks rl ON 1 = 1
             WHERE COALESCE(r.is_internal, 0) = 0 AND r.room_number NOT LIKE '0%';
        END IF;
        SET v_date = DATE_ADD(v_date, INTERVAL 1 DAY);
    END WHILE;
    SELECT COALESCE(SUM(room_available), 1) INTO v_capacity FROM tmp_company_occ_calendar;
    IF v_capacity < 1 THEN SET v_capacity = 1; END IF;

    DROP TEMPORARY TABLE IF EXISTS tmp_company_occ_items;
    CREATE TEMPORARY TABLE tmp_company_occ_items (
        BookingId BIGINT, CompanyCode VARCHAR(80), CompanyName VARCHAR(255), MarketCode VARCHAR(80), MarketName VARCHAR(255),
        SourceCode VARCHAR(80), SourceName VARCHAR(255), UserSale VARCHAR(100), AreaCodes VARCHAR(500), ActivityDate DATE,
        RoomQty INT, GuestQty INT, RoomNight INT, GuestNight INT, FocRoomNights INT, HuRoomNights INT,
        RoomRevenue DECIMAL(18,4), ExtraBedRevenue DECIMAL(18,4), ExtraRollawayRevenue DECIMAL(18,4), FbRevenue DECIMAL(18,4), OtherRevenue DECIMAL(18,4),
        TotalByDate DECIMAL(18,4), OriginalTotal DECIMAL(18,4), RoomAvailable INT, AvgIncludeOther TINYINT
    );

    INSERT INTO tmp_company_occ_items
    SELECT s.booking_id, COALESCE(s.company_code, 'WALKIN'), COALESCE(NULLIF(s.company_name, ''), 'KHÁCH LẺ'),
           COALESCE(s.market_code, ''), COALESCE(s.market_name, ''), COALESCE(s.source_code, ''), COALESCE(s.source_name, ''), COALESCE(s.user_sale, ''), COALESCE(s.area_codes, ''),
           s.snapshot_date, s.room_qty, s.guest_qty, s.room_night, s.guest_night, s.foc_room_nights, s.hu_room_nights,
           s.room_revenue - CASE WHEN COALESCE(p_include_breakfast, 1) = 0 THEN s.breakfast_revenue ELSE 0 END,
           s.extra_bed_revenue, s.extra_rollaway_revenue, s.fb_revenue, s.other_revenue, s.total_by_date, s.original_total,
           d.room_available, d.average_room_rate_include_other_revenue
      FROM night_audit_company_occupancy_snapshots s
      INNER JOIN night_audit_company_occupancy_daily_snapshots d ON d.snapshot_date = s.snapshot_date
     WHERE s.snapshot_date BETWEEN v_from AND LEAST(v_to, DATE_SUB(v_system_date, INTERVAL 1 DAY))
       AND (COALESCE(p_company, '') = '' OR CAST(s.company_id AS CHAR) = p_company OR s.company_code = p_company)
       AND (COALESCE(p_segment, '') = '' OR CAST(s.market_id AS CHAR) = p_segment OR s.market_code = p_segment)
       AND (COALESCE(p_user_sale, '') = '' OR s.user_sale = p_user_sale)
       AND (COALESCE(p_area, '') = '' OR FIND_IN_SET(p_area, s.area_codes));

    INSERT INTO tmp_company_occ_items
    SELECT b.id, COALESCE(c.code, 'WALKIN'), COALESCE(NULLIF(c.name, ''), 'KHÁCH LẺ'), COALESCE(m.code, ''), COALESCE(m.name, ''),
           COALESCE(cs.code, ''), COALESCE(cs.name, ''), COALESCE(b.sales_person, b.created_by, ''), COALESCE(r.area, ''), cal.activity_date,
           1, COALESCE(br.adults, 0) + COALESCE(br.children_qty, 0) + COALESCE(br.babies, 0), 1, COALESCE(br.adults, 0) + COALESCE(br.children_qty, 0),
           CASE WHEN UPPER(COALESCE(br.rate_code, '')) LIKE 'FOC%' OR COALESCE(br.rate, 0) = 0 THEN 1 ELSE 0 END,
           CASE WHEN UPPER(COALESCE(br.rate_code, '')) = 'HU' THEN 1 ELSE 0 END,
           COALESCE(br.rate, 0) - CASE WHEN COALESCE(p_include_breakfast, 1) = 0 THEN COALESCE((SELECT SUM(COALESCE(brs.total_amount, brs.quantity * brs.rate)) FROM booking_room_services brs WHERE brs.booking_room_id = br.id AND brs.deleted_at IS NULL AND brs.service_date = cal.activity_date AND UPPER(brs.service_code) = 'BF'), 0) ELSE 0 END,
           COALESCE((SELECT SUM(COALESCE(brs.total_amount, brs.quantity * brs.rate)) FROM booking_room_services brs WHERE brs.booking_room_id = br.id AND brs.deleted_at IS NULL AND brs.service_date = cal.activity_date AND UPPER(brs.service_code) = 'EB'), 0),
           COALESCE((SELECT SUM(COALESCE(brs.total_amount, brs.quantity * brs.rate)) FROM booking_room_services brs WHERE brs.booking_room_id = br.id AND brs.deleted_at IS NULL AND brs.service_date = cal.activity_date AND FIND_IN_SET(UPPER(brs.service_code), REPLACE(UPPER(v_er_codes), ' ', ''))), 0),
           COALESCE((SELECT SUM(COALESCE(brs.total_amount, brs.quantity * brs.rate)) FROM booking_room_services brs WHERE brs.booking_room_id = br.id AND brs.deleted_at IS NULL AND brs.service_date = cal.activity_date AND UPPER(COALESCE(brs.department, '')) = 'FB'), 0),
           COALESCE((SELECT SUM(COALESCE(brs.total_amount, brs.quantity * brs.rate)) FROM booking_room_services brs WHERE brs.booking_room_id = br.id AND brs.deleted_at IS NULL AND brs.service_date = cal.activity_date AND UPPER(COALESCE(brs.department, '')) <> 'FB' AND UPPER(brs.service_code) NOT IN ('RM', 'BF', 'EB') AND FIND_IN_SET(UPPER(brs.service_code), REPLACE(UPPER(v_er_codes), ' ', '')) = 0), 0),
           COALESCE(br.rate, 0), COALESCE(br.rate, 0), cal.room_available, cal.avg_include_other
      FROM booking_rooms br
      INNER JOIN bookings b ON b.id = br.booking_id
      INNER JOIN rooms r ON r.room_number = br.room_number
      INNER JOIN tmp_company_occ_calendar cal ON cal.activity_date >= GREATEST(v_system_date, v_from) AND cal.activity_date <= v_to AND cal.activity_date >= br.arrival_date AND cal.activity_date < br.departure_date
      LEFT JOIN companies c ON c.id = b.company_id
      LEFT JOIN markets m ON m.id = b.market_id
      LEFT JOIN customer_sources cs ON cs.id = b.customer_source_id
      LEFT JOIN registration_statuses rs ON rs.id = b.registration_status_id
     WHERE b.deleted_at IS NULL AND b.status NOT IN (3, 4) AND br.status NOT IN (3, 100)
       AND COALESCE(rs.is_availability, 1) = 1 AND COALESCE(r.is_internal, 0) = 0 AND r.room_number NOT LIKE '0%'
       AND (COALESCE(p_company, '') = '' OR CAST(b.company_id AS CHAR) = p_company OR c.code = p_company)
       AND (COALESCE(p_segment, '') = '' OR CAST(b.market_id AS CHAR) = p_segment OR m.code = p_segment)
       AND (COALESCE(p_user_sale, '') = '' OR b.sales_person = p_user_sale OR b.created_by = p_user_sale)
       AND (COALESCE(p_area, '') = '' OR r.area = p_area);

    DROP TEMPORARY TABLE IF EXISTS tmp_company_occ_days;
    CREATE TEMPORARY TABLE tmp_company_occ_days AS
    SELECT BookingId, CompanyCode, CompanyName, MarketCode, MarketName, SourceCode, SourceName, UserSale, AreaCodes, ActivityDate,
           SUM(RoomQty) RoomQty, SUM(GuestQty) GuestQty, SUM(RoomNight) RoomNight, SUM(GuestNight) GuestNight,
           SUM(FocRoomNights) FocRoomNights, SUM(HuRoomNights) HuRoomNights, SUM(RoomRevenue) RoomRevenue,
           SUM(ExtraBedRevenue) ExtraBedRevenue, SUM(ExtraRollawayRevenue) ExtraRollawayRevenue, SUM(FbRevenue) FbRevenue, SUM(OtherRevenue) OtherRevenue,
           SUM(TotalByDate) TotalByDate, SUM(OriginalTotal) OriginalTotal, MAX(RoomAvailable) RoomAvailable, MAX(AvgIncludeOther) AvgIncludeOther
      FROM tmp_company_occ_items
     GROUP BY BookingId, CompanyCode, CompanyName, MarketCode, MarketName, SourceCode, SourceName, UserSale, AreaCodes, ActivityDate;

    DROP TEMPORARY TABLE IF EXISTS tmp_company_occ_rows;
    CREATE TEMPORARY TABLE tmp_company_occ_rows AS
    SELECT d.*,
           CASE v_group_by
             WHEN 'date' THEN DATE_FORMAT(d.ActivityDate, '%Y-%m-%d')
             WHEN 'marketsegment' THEN CONCAT('M:', COALESCE(NULLIF(d.MarketCode, ''), 'UNKNOWN'))
             WHEN 'sourcecode' THEN CONCAT('S:', COALESCE(NULLIF(d.SourceCode, ''), 'UNKNOWN'))
             WHEN 'daysegment' THEN CONCAT(DATE_FORMAT(d.ActivityDate, '%Y-%m-%d'), '|M:', COALESCE(NULLIF(d.MarketCode, ''), 'UNKNOWN'))
             WHEN 'daysourcecode' THEN CONCAT(DATE_FORMAT(d.ActivityDate, '%Y-%m-%d'), '|S:', COALESCE(NULLIF(d.SourceCode, ''), 'UNKNOWN'))
             ELSE '__none__'
           END GroupHeaderId,
           CASE v_group_by
             WHEN 'date' THEN CONCAT('Ngày: ', DATE_FORMAT(d.ActivityDate, '%d-%m-%Y'))
             WHEN 'marketsegment' THEN CONCAT('Thị trường: ', COALESCE(NULLIF(d.MarketName, ''), d.MarketCode, 'Không xác định'))
             WHEN 'sourcecode' THEN CONCAT('Nguồn khách: ', COALESCE(NULLIF(d.SourceName, ''), d.SourceCode, 'Không xác định'))
             WHEN 'daysegment' THEN CONCAT('Ngày: ', DATE_FORMAT(d.ActivityDate, '%d-%m-%Y'), ' - Thị trường: ', COALESCE(NULLIF(d.MarketName, ''), d.MarketCode, 'Không xác định'))
             WHEN 'daysourcecode' THEN CONCAT('Ngày: ', DATE_FORMAT(d.ActivityDate, '%d-%m-%Y'), ' - Nguồn khách: ', COALESCE(NULLIF(d.SourceName, ''), d.SourceCode, 'Không xác định'))
             ELSE ''
           END GroupHeader
      FROM tmp_company_occ_days d;

    DROP TEMPORARY TABLE IF EXISTS tmp_company_occ_summary;
    CREATE TEMPORARY TABLE tmp_company_occ_summary AS
    SELECT CompanyCode, CompanyName, GroupHeaderId, GroupHeader,
           SUM(RoomQty) RoomQty, SUM(GuestQty) GuestQty, SUM(RoomNight) RoomNight, SUM(GuestNight) GuestNight,
           SUM(FocRoomNights) FocRoomNights, SUM(HuRoomNights) HuRoomNights, SUM(RoomRevenue) RoomRevenue,
           SUM(ExtraBedRevenue) ExtraBedRevenue, SUM(ExtraRollawayRevenue) ExtraRollawayRevenue, SUM(FbRevenue) FbRevenue, SUM(OtherRevenue) OtherRevenue,
           SUM(TotalByDate) TotalByDate, SUM(OriginalTotal) OriginalTotal, COUNT(DISTINCT BookingId) BookingCount,
           MIN(ActivityDate) ActivityDate, CASE WHEN v_group_by IN ('date', 'daysegment', 'daysourcecode') THEN MAX(RoomAvailable) ELSE v_capacity END RoomAvailable,
           MAX(AvgIncludeOther) AvgIncludeOther
      FROM tmp_company_occ_rows
     GROUP BY CompanyCode, CompanyName, GroupHeaderId, GroupHeader;

    SELECT COALESCE(SUM(RoomRevenue + ExtraBedRevenue + ExtraRollawayRevenue + FbRevenue + OtherRevenue), 0) INTO @total_revenue FROM tmp_company_occ_summary;
    SELECT CompanyCode, CompanyName,
           ROUND(RoomRevenue + ExtraBedRevenue + ExtraRollawayRevenue + FbRevenue + OtherRevenue, 0) TotalRevenue,
           ROUND(CASE WHEN @total_revenue = 0 THEN 0 ELSE (RoomRevenue + ExtraBedRevenue + ExtraRollawayRevenue + FbRevenue + OtherRevenue) * 100 / @total_revenue END, 2) RevenuePercentage,
           ROUND(CASE WHEN RoomNight - HuRoomNights = 0 THEN 0 ELSE (RoomRevenue + ExtraBedRevenue + ExtraRollawayRevenue + CASE WHEN AvgIncludeOther = 1 THEN OtherRevenue ELSE 0 END) / (RoomNight - HuRoomNights) END, 0) AverageRevenue,
           RoomQty, RoomNight, FocRoomNights, HuRoomNights, ROUND(CASE WHEN RoomAvailable = 0 THEN 0 ELSE RoomNight * 100 / RoomAvailable END, 2) OccupancyRate,
           GuestQty, GuestNight, ROUND(RoomRevenue, 0) RoomRevenue,
           ROUND(ExtraBedRevenue, 0) ExtraBedRevenue, ROUND(ExtraRollawayRevenue, 0) ExtraRollawayRevenue, ROUND(FbRevenue, 0) FbRevenue, ROUND(OtherRevenue, 0) OtherRevenue,
           ROUND(TotalByDate, 0) TotalByDate, ROUND(OriginalTotal, 0) OriginalTotal,
           ROUND(CASE WHEN RoomNight = 0 THEN 0 ELSE RoomRevenue / RoomNight END, 0) ActualADR,
           ROUND(CASE WHEN RoomNight - FocRoomNights - HuRoomNights = 0 THEN 0 ELSE RoomRevenue / (RoomNight - FocRoomNights - HuRoomNights) END, 0) RackADR,
           v_capacity TotalRoomAvailable, RoomAvailable, BookingCount, DATE_FORMAT(ActivityDate, '%d/%m/%Y') ActivityDate,
           GroupHeader, GroupHeaderId, FocRoomNights Foc, HuRoomNights Hu
      FROM tmp_company_occ_summary
     ORDER BY GroupHeaderId, TotalRevenue DESC, CompanyName ASC;

    DROP TEMPORARY TABLE IF EXISTS tmp_company_occ_summary;
    DROP TEMPORARY TABLE IF EXISTS tmp_company_occ_rows;
    DROP TEMPORARY TABLE IF EXISTS tmp_company_occ_days;
    DROP TEMPORARY TABLE IF EXISTS tmp_company_occ_items;
    DROP TEMPORARY TABLE IF EXISTS tmp_company_occ_calendar;
END
SQL;
    }

    private function detailProcedure(): string
    {
        return <<<'SQL'
CREATE PROCEDURE rpt_company_occupancy_detail(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_area VARCHAR(50),
    IN p_company VARCHAR(50),
    IN p_segment VARCHAR(50),
    IN p_user_sale VARCHAR(50),
    IN p_source_code VARCHAR(50)
)
READS SQL DATA
BEGIN
    DECLARE v_from DATE DEFAULT COALESCE(p_from_date, CURRENT_DATE());
    DECLARE v_to DATE DEFAULT COALESCE(p_to_date, COALESCE(p_from_date, CURRENT_DATE()));
    DECLARE v_system_date DATE DEFAULT CURRENT_DATE();
    DECLARE v_prefix VARCHAR(50) DEFAULT '';
    IF v_to < v_from THEN SET v_to = v_from; END IF;
    SELECT CAST(value AS DATE) INTO v_system_date FROM hotel_configs WHERE LOWER(name) = 'systemdate' AND value <> '' LIMIT 1;
    IF v_system_date IS NULL THEN SELECT DATE(system_date) INTO v_system_date FROM system_date_rolls ORDER BY id DESC LIMIT 1; END IF;
    IF v_system_date IS NULL THEN SET v_system_date = CURRENT_DATE(); END IF;
    SELECT COALESCE(prefix_booking_id, '') INTO v_prefix FROM hotel_settings ORDER BY id LIMIT 1;

    DROP TEMPORARY TABLE IF EXISTS tmp_company_occ_detail;
    CREATE TEMPORARY TABLE tmp_company_occ_detail (
        BookingId BIGINT, BookingCode VARCHAR(80), ReferenceCode VARCHAR(255), CompanyCode VARCHAR(80), CompanyName VARCHAR(255), GuestName VARCHAR(255),
        MarketSegment VARCHAR(255), SourceCode VARCHAR(255), BookingDate DATE, ArrivalDate DATE, DepartureDate DATE, NoOfNight INT, NoOfRoom INT,
        RoomNight INT, GuestNight INT, RoomRevenue DECIMAL(18,4), ExtraBedRevenue DECIMAL(18,4), ChildSurchargeRevenue DECIMAL(18,4),
        FbRevenue DECIMAL(18,4), OtherRevenue DECIMAL(18,4), OriginalTotal DECIMAL(18,4), RoomType VARCHAR(500), Nationality VARCHAR(500)
    );

    INSERT INTO tmp_company_occ_detail
    SELECT s.booking_id, s.booking_code, s.booking_name, COALESCE(s.company_code, 'WALKIN'), COALESCE(NULLIF(s.company_name, ''), 'KHÁCH LẺ'), s.booking_name,
           COALESCE(s.market_name, s.market_code, ''), COALESCE(s.source_name, s.source_code, ''), s.booking_date, s.arrival_date, s.departure_date,
           MAX(DATEDIFF(s.departure_date, s.arrival_date)), MAX(s.room_qty), SUM(s.room_night), SUM(s.guest_night),
           SUM(s.room_revenue), SUM(s.extra_bed_revenue), SUM(s.extra_rollaway_revenue), SUM(s.fb_revenue), SUM(s.other_revenue), SUM(s.original_total),
           MAX(s.room_types), MAX(s.nationalities)
      FROM night_audit_company_occupancy_snapshots s
     WHERE s.snapshot_date BETWEEN v_from AND LEAST(v_to, DATE_SUB(v_system_date, INTERVAL 1 DAY))
       AND (COALESCE(p_company, '') = '' OR CAST(s.company_id AS CHAR) = p_company OR s.company_code = p_company)
       AND (COALESCE(p_segment, '') = '' OR CAST(s.market_id AS CHAR) = p_segment OR s.market_code = p_segment)
       AND (COALESCE(p_user_sale, '') = '' OR s.user_sale = p_user_sale)
       AND (COALESCE(p_source_code, '') = '' OR CAST(s.source_id AS CHAR) = p_source_code OR s.source_code = p_source_code)
       AND (COALESCE(p_area, '') = '' OR FIND_IN_SET(p_area, s.area_codes))
     GROUP BY s.booking_id, s.booking_code, s.booking_name, s.company_code, s.company_name, s.market_name, s.market_code, s.source_name, s.source_code, s.booking_date, s.arrival_date, s.departure_date, s.room_qty;

    INSERT INTO tmp_company_occ_detail
    SELECT b.id, CONCAT(v_prefix, b.id), COALESCE(b.external_booking_code, b.booking_name), COALESCE(c.code, 'WALKIN'), COALESCE(NULLIF(c.name, ''), 'KHÁCH LẺ'), b.booking_name,
           COALESCE(m.name, m.code, ''), COALESCE(cs.name, cs.code, ''), b.booking_date, b.arrival_date, b.departure_date,
           DATEDIFF(b.departure_date, b.arrival_date), COUNT(DISTINCT br.id),
           SUM(DATEDIFF(LEAST(br.departure_date, DATE_ADD(v_to, INTERVAL 1 DAY)), GREATEST(br.arrival_date, GREATEST(v_from, v_system_date)))),
           SUM((COALESCE(br.adults, 0) + COALESCE(br.children_qty, 0)) * DATEDIFF(LEAST(br.departure_date, DATE_ADD(v_to, INTERVAL 1 DAY)), GREATEST(br.arrival_date, GREATEST(v_from, v_system_date)))),
           SUM(COALESCE(br.rate, 0) * DATEDIFF(LEAST(br.departure_date, DATE_ADD(v_to, INTERVAL 1 DAY)), GREATEST(br.arrival_date, GREATEST(v_from, v_system_date)))),
           0, 0, 0, 0, SUM(COALESCE(br.rate, 0) * DATEDIFF(LEAST(br.departure_date, DATE_ADD(v_to, INTERVAL 1 DAY)), GREATEST(br.arrival_date, GREATEST(v_from, v_system_date)))), GROUP_CONCAT(DISTINCT rc.code), ''
      FROM booking_rooms br
      INNER JOIN bookings b ON b.id = br.booking_id
      INNER JOIN rooms r ON r.room_number = br.room_number
      INNER JOIN room_classes rc ON rc.id = br.room_class_id
      LEFT JOIN companies c ON c.id = b.company_id
      LEFT JOIN markets m ON m.id = b.market_id
      LEFT JOIN customer_sources cs ON cs.id = b.customer_source_id
      LEFT JOIN registration_statuses rs ON rs.id = b.registration_status_id
     WHERE v_to >= v_system_date AND br.arrival_date <= v_to AND br.departure_date > GREATEST(v_from, v_system_date)
       AND b.deleted_at IS NULL AND b.status NOT IN (3, 4) AND br.status NOT IN (3, 100) AND COALESCE(rs.is_availability, 1) = 1
       AND COALESCE(r.is_internal, 0) = 0 AND r.room_number NOT LIKE '0%'
       AND (COALESCE(p_company, '') = '' OR CAST(b.company_id AS CHAR) = p_company OR c.code = p_company)
       AND (COALESCE(p_segment, '') = '' OR CAST(b.market_id AS CHAR) = p_segment OR m.code = p_segment)
       AND (COALESCE(p_user_sale, '') = '' OR b.sales_person = p_user_sale OR b.created_by = p_user_sale)
       AND (COALESCE(p_source_code, '') = '' OR CAST(b.customer_source_id AS CHAR) = p_source_code OR cs.code = p_source_code)
       AND (COALESCE(p_area, '') = '' OR r.area = p_area)
     GROUP BY b.id, b.external_booking_code, b.booking_name, c.code, c.name, m.name, m.code, cs.name, cs.code, b.booking_date, b.arrival_date, b.departure_date;

    SELECT ROW_NUMBER() OVER (ORDER BY CompanyName, ArrivalDate, BookingCode) RowNumber, BookingCode, ReferenceCode, CompanyName, GuestName, MarketSegment, SourceCode,
           DATE_FORMAT(BookingDate, '%d/%m/%Y') BookingDate, DATE_FORMAT(ArrivalDate, '%d/%m/%Y') ArrivalDate, DATE_FORMAT(DepartureDate, '%d/%m/%Y') DepartureDate,
           NoOfNight, NoOfRoom, RoomNight, GuestNight, ROUND(CASE WHEN RoomNight = 0 THEN 0 ELSE RoomRevenue / RoomNight END, 0) AverageRate,
           ROUND(CASE WHEN RoomNight = 0 THEN 0 ELSE OriginalTotal / RoomNight END, 0) AverageRateOriginal,
           ROUND(RoomRevenue, 0) RoomRevenue, ROUND(FbRevenue, 0) FbRevenue, ROUND(OtherRevenue, 0) OtherRevenue,
           ROUND(RoomRevenue + ExtraBedRevenue + ChildSurchargeRevenue + FbRevenue + OtherRevenue, 0) TotalRevenue,
           RoomType, Nationality, CompanyCode, ROUND(ExtraBedRevenue, 0) ExtraBedRevenue, ROUND(ChildSurchargeRevenue, 0) ChildSurchargeRevenue
      FROM tmp_company_occ_detail
     WHERE RoomNight <> 0 OR RoomRevenue + ExtraBedRevenue + ChildSurchargeRevenue + FbRevenue + OtherRevenue <> 0
     ORDER BY CompanyName, ArrivalDate, BookingCode;
    DROP TEMPORARY TABLE IF EXISTS tmp_company_occ_detail;
END
SQL;
    }
};
