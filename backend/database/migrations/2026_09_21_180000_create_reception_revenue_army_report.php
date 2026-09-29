<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE = 'RPT_RECEPTION_REVENUE_ARMY';
    private const REPORT = 'RECEPTION_REVENUE_ARMY';
    private const TEMPLATE = 'RECEPTION_REVENUE_ARMY_REFERENCE';

    public function up(): void
    {
        $visitedDatabases = [];
        foreach ($this->targetConnections() as $connectionName) {
            $connection = DB::connection($connectionName);
            if ($connection->getDriverName() !== 'mysql') {
                continue;
            }

            $database = $connection->getDatabaseName();
            if (isset($visitedDatabases[$database])) {
                continue;
            }
            $visitedDatabases[$database] = true;

            $connection->unprepared('DROP PROCEDURE IF EXISTS rpt_reception_revenue_army');
            $connection->unprepared($this->procedureSql());
            $this->syncReportConfiguration($connectionName);
        }
    }

    public function procedureSql(): string
    {
        return <<<'SQL'
CREATE PROCEDURE rpt_reception_revenue_army(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_user VARCHAR(20),
    IN p_service VARCHAR(10)
)
READS SQL DATA
BEGIN
    WITH service_setup AS (
        SELECT 'RoomRevenue' AS DisplayName, 'RM' AS ServiceCodes,
               'Room Revenue' AS Name, 'Doanh Thu Phòng' AS NameVI
        UNION ALL
        SELECT 'LaundryRevenue', 'LA', 'Laundry Revenue', 'Doanh Thu Giặt Ủi'
        UNION ALL
        SELECT 'ServiceRevenue', 'BR,DO,EB,EI,EP,ER,KC,KE,LO,MB,MR,MS,PE,PU,TO,UP',
               'Service Revenue', 'Doanh Thu Dịch Vụ'
    ),
    detail_totals AS (
        SELECT
            d.BillServiceId,
            SUM(d.OriginalRate) AS OriginalRate,
            SUM(d.ServiceChargeAmount) AS ServiceChargeAmount,
            SUM(d.SpecialTaxAmount) AS SpecialTaxAmount,
            SUM(d.TaxAmount) AS TaxAmount
        FROM service_bill_details AS d
        GROUP BY d.BillServiceId
    ),
    payment_descriptions AS (
        SELECT
            p.payment_id AS PaymentID,
            GROUP_CONCAT(DISTINCT p.payment_method_id ORDER BY p.payment_method_id SEPARATOR ',') AS PaymentMethod,
            GROUP_CONCAT(DISTINCT p.description ORDER BY p.description SEPARATOR ',') AS Description
        FROM payments AS p
        WHERE p.payment_id IS NOT NULL
        GROUP BY p.payment_id
    ),
    source_rows AS (
        SELECT
            sb.Ma,
            sb.Date,
            sb.OpenTime,
            sb.Guest,
            sb.DepartmentId,
            sb.ServiceId,
            sb.DescriptionServive,
            sb.Amount,
            COALESCE(sb.ServiceCharge, 0) AS ServiceCharge,
            COALESCE(sb.SpecialTax, 0) AS SpecialTax,
            COALESCE(sb.Tax, 0) AS Tax,
            sb.PaymentId,
            sb.RegisterID2,
            sb.RegisterId1,
            sb.Username,
            br.room_number AS Room,
            COALESCE(br.arrival_date, b.arrival_date) AS ArrivalDate,
            COALESCE(br.departure_date, b.departure_date) AS DepartureDate,
            b.id AS BookingId,
            c.name AS Company,
            hs.prefix_booking_id AS BookingPrefix,
            hs_service.name AS FirstNameService,
            setup.DisplayName,
            setup.Name,
            setup.NameVI,
            dt.OriginalRate AS DetailOriginalRate,
            dt.ServiceChargeAmount AS DetailServiceChargeAmount,
            dt.SpecialTaxAmount AS DetailSpecialTaxAmount,
            dt.TaxAmount AS DetailTaxAmount,
            pay.PaymentMethod,
            pay.Description,
            (1 + COALESCE(sb.ServiceCharge, 0) / 100)
                * (1 + COALESCE(sb.SpecialTax, 0) / 100)
                * (1 + COALESCE(sb.Tax, 0) / 100) AS TaxFactor
        FROM service_bills AS sb
        INNER JOIN service_setup AS setup
            ON FIND_IN_SET(sb.ServiceId, setup.ServiceCodes) > 0
        LEFT JOIN booking_rooms AS br
            ON br.id = COALESCE(NULLIF(sb.RentalRoomId2, '0'), NULLIF(sb.RentalRoomId1, '0'))
        LEFT JOIN bookings AS b
            ON b.id = COALESCE(NULLIF(sb.RegisterID2, 0), br.booking_id, NULLIF(sb.RegisterId1, 0))
        LEFT JOIN companies AS c
            ON c.id = b.company_id
        LEFT JOIN hotel_settings AS hs
            ON hs.id = (SELECT MIN(setting.id) FROM hotel_settings AS setting)
        LEFT JOIN hotel_services AS hs_service
            ON hs_service.code = sb.ServiceId
        LEFT JOIN detail_totals AS dt
            ON dt.BillServiceId = sb.Ma
        LEFT JOIN payment_descriptions AS pay
            ON pay.PaymentID = sb.PaymentId
        WHERE sb.Edit = 0
          AND sb.DepartmentId IN ('FO', 'HK')
          AND sb.Date >= p_from_date
          AND sb.Date < DATE_ADD(p_to_date, INTERVAL 1 DAY)
          AND (COALESCE(p_user, '') = '' OR sb.Username = p_user)
          AND (COALESCE(p_service, '') = '' OR sb.ServiceId = p_service)
    ),
    rated_rows AS (
        SELECT
            source_rows.*,
            COALESCE(
                DetailOriginalRate,
                Amount / NULLIF(TaxFactor, 0)
            ) AS OriginalRate,
            COALESCE(
                DetailServiceChargeAmount,
                (Amount / NULLIF(TaxFactor, 0)) * (ServiceCharge / 100)
            ) AS ServiceChargeAmount,
            COALESCE(
                DetailSpecialTaxAmount,
                Amount / NULLIF(TaxFactor + (Amount / NULLIF(TaxFactor, 0)) * (ServiceCharge / 100), 0)
                    * (SpecialTax / 100)
            ) AS SpecialTaxAmount
        FROM source_rows
    ),
    taxed_rows AS (
        SELECT
            rated_rows.*,
            COALESCE(
                DetailTaxAmount,
                (OriginalRate + OriginalRate * (ServiceCharge / 100) + SpecialTaxAmount) * (Tax / 100)
            ) AS TaxAmount
        FROM rated_rows
    )
    SELECT
        CONCAT(COALESCE(BookingPrefix, ''), COALESCE(RegisterID2, BookingId, '')) AS BookingId,
        Date,
        Room,
        ArrivalDate,
        DepartureDate,
        Guest AS GuestName,
        DescriptionServive,
        OriginalRate,
        ServiceChargeAmount,
        SpecialTaxAmount,
        TaxAmount,
        Amount,
        PaymentMethod,
        Company,
        OpenTime,
        Description,
        DisplayName,
        ServiceId,
        FirstNameService,
        Name,
        NameVI
    FROM taxed_rows
    ORDER BY DisplayName ASC, ServiceId ASC, Date ASC, Ma ASC;
END
SQL;
    }

    public function down(): void
    {
        foreach ($this->targetConnections() as $connectionName) {
            $connection = DB::connection($connectionName);
            if ($connection->getDriverName() !== 'mysql') {
                continue;
            }

            $reportId = $connection->table('report_definitions')->where('code', self::REPORT)->value('id');
            if ($reportId) {
                $connection->table('report_definition_template')->where('report_definition_id', $reportId)->delete();
                $connection->table('report_definitions')->where('id', $reportId)->delete();
            }

            $connection->table('report_data_sources')->where('code', self::SOURCE)->delete();
            $connection->unprepared('DROP PROCEDURE IF EXISTS rpt_reception_revenue_army');
            // Keep the reference template so a user-edited Designer layout is not discarded by rollback.
        }
    }

    private function targetConnections(): array
    {
        return [DB::getDefaultConnection()];
    }

    private function syncReportConfiguration(string $connectionName): void
    {
        $db = DB::connection($connectionName);
        $now = now();
        $database = $db->getDatabaseName();

        $parameters = [
            ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 1, 'required' => true],
            ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 2, 'required' => true],
            ['name' => 'p_user', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(20)', 'position' => 3, 'required' => false],
            ['name' => 'p_service', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(10)', 'position' => 4, 'required' => false],
        ];

        $fieldTypes = [
            'BookingId' => 'string', 'Date' => 'datetime', 'Room' => 'string', 'ArrivalDate' => 'date',
            'DepartureDate' => 'date', 'GuestName' => 'string', 'DescriptionServive' => 'string',
            'OriginalRate' => 'number', 'ServiceChargeAmount' => 'number', 'SpecialTaxAmount' => 'number',
            'TaxAmount' => 'number', 'Amount' => 'number', 'PaymentMethod' => 'string', 'Company' => 'string',
            'OpenTime' => 'string', 'Description' => 'string', 'DisplayName' => 'string', 'ServiceId' => 'string',
            'FirstNameService' => 'string', 'Name' => 'string', 'NameVI' => 'string',
        ];
        $nonNullableFields = ['BookingId', 'Date', 'DescriptionServive', 'Amount', 'DisplayName', 'ServiceId'];
        $fields = array_map(static fn (string $name): array => [
            'name' => $name,
            'type' => $fieldTypes[$name],
            'nullable' => ! in_array($name, $nonNullableFields, true),
        ], array_keys($fieldTypes));

        $defaults = [
            'p_from_date' => now()->toDateString(),
            'p_to_date' => now()->toDateString(),
            'p_user' => '',
            'p_service' => '',
        ];

        $db->table('report_data_sources')->updateOrInsert(['code' => self::SOURCE], [
            'name' => 'Dữ liệu báo cáo doanh thu lễ tân Army',
            'description' => 'Dữ liệu theo ProVistaArmyHotel.dbo.sp_293; giữ đủ 21 alias legacy.',
            'source_type' => 'procedure',
            'schema_name' => $database,
            'object_name' => 'rpt_reception_revenue_army',
            'parameter_schema' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
            'field_schema' => json_encode($fields, JSON_UNESCAPED_UNICODE),
            'sample_parameters' => json_encode($defaults, JSON_UNESCAPED_UNICODE),
            'max_rows' => 5000,
            'is_active' => true,
            'last_discovered_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $sourceId = $db->table('report_data_sources')->where('code', self::SOURCE)->value('id');

        $definition = (require database_path('report_templates/reception_revenue_army_reference.php'))->definition();
        $template = $db->table('templates')->where('report', self::TEMPLATE)->first();
        if (! $template) {
            $templateId = $db->table('templates')->insertGetId([
                'report' => self::TEMPLATE,
                'group' => 'Báo cáo doanh thu',
                'name' => 'Báo cáo doanh thu lễ tân Army',
                'report_data_source_id' => $sourceId,
                'parameter_defaults' => json_encode($defaults, JSON_UNESCAPED_UNICODE),
                'page_size' => $definition['page_size'],
                'page_orientation' => $definition['page_orientation'],
                'margin_top' => $definition['margin_top'],
                'margin_right' => $definition['margin_right'],
                'margin_bottom' => $definition['margin_bottom'],
                'margin_left' => $definition['margin_left'],
                'content_json' => json_encode($definition['content_json'], JSON_UNESCAPED_UNICODE),
                'content_html' => $definition['content_html'],
                'css' => $definition['css'],
                'is_default' => true,
                'version' => '1.0',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $templateId = $template->id;
            $db->table('templates')->where('id', $templateId)->update([
                'report_data_source_id' => $sourceId,
                'updated_at' => $now,
            ]);
        }

        $serviceOptions = array_map(static fn (string $code): array => ['value' => $code, 'label' => $code], [
            'RM', 'LA', 'BR', 'DO', 'EB', 'EI', 'EP', 'ER', 'KC', 'KE', 'LO', 'MB', 'MR', 'MS', 'PE', 'PU', 'TO', 'UP',
        ]);
        array_unshift($serviceOptions, ['value' => '', 'label' => 'Tất cả']);

        $ui = [
            ['name' => 'p_from_date', 'label' => 'Ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            ['name' => 'p_service', 'label' => 'Dịch vụ', 'control' => 'select', 'default' => '', 'required' => false, 'options' => $serviceOptions],
            ['name' => 'p_user', 'label' => 'Người dùng', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'users'],
        ];

        $db->table('report_definitions')->updateOrInsert(['code' => self::REPORT], [
            'name' => 'Báo cáo doanh thu lễ tân Army',
            'group' => 'Báo cáo doanh thu',
            'description' => 'Doanh thu lễ tân theo ngày, nhóm dịch vụ và dịch vụ theo sp_293 Army.',
            'report_data_source_id' => $sourceId,
            'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE),
            'sort_order' => 152,
            'is_active' => true,
            'show_in_menu' => true,
            'menu_locations' => json_encode(['frontdesk'], JSON_UNESCAPED_UNICODE),
            'menu_top_order' => 20,
            'menu_group_order' => 20,
            'menu_item_order' => 152,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $reportId = $db->table('report_definitions')->where('code', self::REPORT)->value('id');

        $db->table('report_definition_template')->updateOrInsert(
            ['report_definition_id' => $reportId, 'template_id' => $templateId],
            ['is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]
        );
    }
};
