<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const REPORTS = [
        'LAUNDRY_INVOICES' => [
            'outlet' => 'LA',
            'procedure' => 'rpt_laundry_invoices',
            'template' => 'LAUNDRY_INVOICES_STANDARD',
            'template_file' => 'laundry_invoices_reference.php',
            'name' => 'Báo cáo hóa đơn giặt ủi',
            'sort_order' => 39,
        ],
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::REPORTS as $code => $definition) {
            $this->installReport($code, $definition);
        }
    }

    public function installReport(string $code, array $definition): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS `'.$definition['procedure'].'`');
        DB::unprepared($this->procedureSql($definition['procedure'], $definition['outlet']));
        $this->registerReport($code, $definition);
    }

    public function procedureSql(string $procedure, string $outlet): string
    {
        $sql = <<<'SQL'
CREATE PROCEDURE __PROCEDURE__(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_shift VARCHAR(20),
    IN p_department VARCHAR(50),
    IN p_user VARCHAR(50),
    IN p_view_type VARCHAR(20),
    IN p_order_by VARCHAR(30),
    IN p_order_type VARCHAR(4),
    IN p_show_details TINYINT
)
READS SQL DATA
BEGIN
    WITH product_lines AS (
        SELECT
            d.BillId,
            TRIM(d.Product) AS Product,
            SUM(d.Quantity) AS Quantity,
            SUM(d.TotalAmount) AS TotalAmount
        FROM housekeeping_service_bill_details AS d
        GROUP BY d.BillId, TRIM(d.Product)
    ),
    product_rollup AS (
        SELECT
            pl.BillId,
            GROUP_CONCAT(
                CONCAT('* ', pl.Product, ' - ', CAST(pl.Quantity AS CHAR))
                ORDER BY pl.Product SEPARATOR ''
            ) AS Product,
            CONCAT('[', GROUP_CONCAT(JSON_OBJECT(
                    'Product', pl.Product,
                    'Quantity', pl.Quantity,
                    'TotalAmount', pl.TotalAmount
                ) ORDER BY pl.Product SEPARATOR ','), ']') AS ProductItems
        FROM product_lines AS pl
        WHERE NULLIF(pl.Product, '') IS NOT NULL
        GROUP BY pl.BillId
    ),
    payment_ranked AS (
        SELECT
            p.payment_id,
            p.payment_method_id AS PaymentMethod,
            ROW_NUMBER() OVER (
                PARTITION BY p.payment_id
                ORDER BY COALESCE(p.legacy_id, p.id), p.id
            ) AS PaymentRank
        FROM payments AS p
        WHERE p.payment_id IS NOT NULL
    ),
    payment_rollup AS (
        SELECT payment_id, PaymentMethod
        FROM payment_ranked
        WHERE PaymentRank = 1
    ),
    report_rows AS (
        SELECT
            ROW_NUMBER() OVER (
                ORDER BY
                    CASE
                        WHEN UPPER(COALESCE(NULLIF(p_view_type, ''), 'ALL')) = 'FREE'
                         AND UPPER(COALESCE(p_order_type, 'ASC')) = 'ASC'
                        THEN h.Ma
                    END DESC,
                    CASE
                        WHEN UPPER(COALESCE(NULLIF(p_view_type, ''), 'ALL')) = 'FREE'
                         AND UPPER(COALESCE(p_order_type, 'ASC')) = 'DESC'
                        THEN h.Ma
                    END ASC,
                    CASE
                        WHEN UPPER(COALESCE(NULLIF(p_view_type, ''), 'ALL')) <> 'FREE'
                         AND UPPER(COALESCE(p_order_by, 'MA')) = 'ROOM'
                         AND UPPER(COALESCE(p_order_type, 'ASC')) = 'ASC'
                        THEN CASE
                            WHEN h.RoomNo REGEXP '^[0-9]+$' THEN CAST(h.RoomNo AS UNSIGNED)
                            ELSE NULL
                        END
                    END DESC,
                    CASE
                        WHEN UPPER(COALESCE(NULLIF(p_view_type, ''), 'ALL')) <> 'FREE'
                         AND UPPER(COALESCE(p_order_by, 'MA')) = 'ROOM'
                         AND UPPER(COALESCE(p_order_type, 'ASC')) = 'DESC'
                        THEN CASE
                            WHEN h.RoomNo REGEXP '^[0-9]+$' THEN CAST(h.RoomNo AS UNSIGNED)
                            ELSE NULL
                        END
                    END ASC,
                    CASE
                        WHEN UPPER(COALESCE(NULLIF(p_view_type, ''), 'ALL')) <> 'FREE'
                         AND UPPER(COALESCE(p_order_by, 'MA')) = 'DATE'
                         AND UPPER(COALESCE(p_order_type, 'ASC')) = 'ASC'
                        THEN sb.Date
                    END DESC,
                    CASE
                        WHEN UPPER(COALESCE(NULLIF(p_view_type, ''), 'ALL')) <> 'FREE'
                         AND UPPER(COALESCE(p_order_by, 'MA')) = 'DATE'
                         AND UPPER(COALESCE(p_order_type, 'ASC')) = 'DESC'
                        THEN sb.Date
                    END ASC,
                    CASE
                        WHEN UPPER(COALESCE(NULLIF(p_view_type, ''), 'ALL')) <> 'FREE'
                         AND UPPER(COALESCE(p_order_by, 'MA')) = 'SERVICEID'
                         AND UPPER(COALESCE(p_order_type, 'ASC')) = 'ASC'
                        THEN CASE
                            WHEN sb.ServiceId REGEXP '^[0-9]+$' THEN CAST(sb.ServiceId AS UNSIGNED)
                            ELSE NULL
                        END
                    END DESC,
                    CASE
                        WHEN UPPER(COALESCE(NULLIF(p_view_type, ''), 'ALL')) <> 'FREE'
                         AND UPPER(COALESCE(p_order_by, 'MA')) = 'SERVICEID'
                         AND UPPER(COALESCE(p_order_type, 'ASC')) = 'DESC'
                        THEN CASE
                            WHEN sb.ServiceId REGEXP '^[0-9]+$' THEN CAST(sb.ServiceId AS UNSIGNED)
                            ELSE NULL
                        END
                    END ASC,
                    CASE
                        WHEN UPPER(COALESCE(NULLIF(p_view_type, ''), 'ALL')) <> 'FREE'
                         AND UPPER(COALESCE(p_order_by, 'MA')) = 'REFID'
                         AND UPPER(COALESCE(p_order_type, 'ASC')) = 'ASC'
                        THEN sb.RefId
                    END DESC,
                    CASE
                        WHEN UPPER(COALESCE(NULLIF(p_view_type, ''), 'ALL')) <> 'FREE'
                         AND UPPER(COALESCE(p_order_by, 'MA')) = 'REFID'
                         AND UPPER(COALESCE(p_order_type, 'ASC')) = 'DESC'
                        THEN sb.RefId
                    END ASC,
                    CASE
                        WHEN UPPER(COALESCE(NULLIF(p_view_type, ''), 'ALL')) <> 'FREE'
                         AND UPPER(COALESCE(p_order_by, 'MA')) = 'MA'
                         AND UPPER(COALESCE(p_order_type, 'ASC')) = 'ASC'
                        THEN sb.Ma
                    END DESC,
                    CASE
                        WHEN UPPER(COALESCE(NULLIF(p_view_type, ''), 'ALL')) <> 'FREE'
                         AND UPPER(COALESCE(p_order_by, 'MA')) = 'MA'
                         AND UPPER(COALESCE(p_order_type, 'ASC')) = 'DESC'
                        THEN sb.Ma
                    END ASC
            ) AS STT,
            COALESCE(h.BookingId, sb.RegisterID2, sb.RegisterId1) AS BookingId,
            CASE
                WHEN UPPER(COALESCE(NULLIF(p_view_type, ''), 'ALL')) = 'FREE' THEN COALESCE(h.NumOfRoom, h.RoomNo)
                WHEN sb.RegisterID2 IS NULL AND sb.RentalRoomId2 IS NOT NULL THEN h.RoomNo
                ELSE ''
            END AS Room,
            CASE
                WHEN UPPER(COALESCE(NULLIF(p_view_type, ''), 'ALL')) = 'FREE' THEN COALESCE(
                    NULLIF(sb.Guest, ''),
                    NULLIF(TRIM(CONCAT_WS(' ', NULLIF(g.title, ''), g.full_name)), ''),
                    NULLIF(h.GuestId, '')
                )
                ELSE sb.Guest
            END AS Guest,
            sb.DescriptionServive,
            COALESCE(pr.Product, '') AS Product,
            h.BillOriginalAmount AS TotalAmount,
            h.BillDiscountAmount AS DiscountAmount,
            COALESCE(h.BillTotalAmount, h.BillAmount) AS NetAmount,
            sb.PaymentId AS PaymentID,
            h.BillNote,
            CASE
                WHEN UPPER(COALESCE(NULLIF(p_view_type, ''), 'ALL')) = 'FREE' THEN h.BillUsername
                ELSE sb.Username
            END AS Username,
            CASE
                WHEN UPPER(COALESCE(NULLIF(p_view_type, ''), 'ALL')) = 'FREE' THEN CAST(h.BillShift AS CHAR)
                ELSE sb.Ca
            END AS Ca,
            DATE_FORMAT(
                CASE
                    WHEN UPPER(COALESCE(NULLIF(p_view_type, ''), 'ALL')) = 'FREE' THEN h.Date
                    ELSE sb.Date
                END,
                '%d/%m/%Y'
            ) AS DateGroup,
            COALESCE(pr.ProductItems, JSON_ARRAY()) AS ProductItems,
            SUM(h.BillOriginalAmount) OVER () AS ReportTotalAmount,
            SUM(h.BillDiscountAmount) OVER () AS ReportDiscountAmount,
            SUM(COALESCE(h.BillTotalAmount, h.BillAmount)) OVER () AS ReportNetAmount
        FROM housekeeping_service_bills AS h
        LEFT JOIN service_bills AS sb ON sb.Ma = h.BillServiceId
        LEFT JOIN product_rollup AS pr ON pr.BillId = h.Ma
        LEFT JOIN payment_rollup AS pay ON pay.payment_id = sb.PaymentId
        LEFT JOIN guests AS g ON g.id = h.GuestId
        WHERE
            (
                UPPER(COALESCE(NULLIF(p_view_type, ''), 'ALL')) = 'FREE'
                AND h.Date >= p_from_date
                AND h.Date < DATE_ADD(p_to_date, INTERVAL 1 DAY)
                AND h.Outlet = '__OUTLET__'
                AND pay.PaymentMethod = 'CL'
                AND (
                    p_user IS NULL OR p_user = ''
                    OR h.BillUsername LIKE CONCAT('%', p_user, '%')
                )
            )
            OR
            (
                UPPER(COALESCE(NULLIF(p_view_type, ''), 'ALL')) <> 'FREE'
                AND sb.Ma IS NOT NULL
                AND sb.Date >= p_from_date
                AND sb.Date < DATE_ADD(p_to_date, INTERVAL 1 DAY)
                AND sb.Outlet LIKE CONCAT('%', '__OUTLET__', '%')
                AND sb.Amount <> 0
                AND sb.Status <> 4
                AND (
                    p_shift IS NULL OR p_shift = ''
                    OR sb.Ca LIKE CONCAT('%', p_shift, '%')
                )
                AND (
                    p_department IS NULL OR p_department = ''
                    OR sb.DepartmentId LIKE CONCAT('%', p_department, '%')
                )
                AND (
                    p_user IS NULL OR p_user = ''
                    OR sb.Username LIKE CONCAT('%', p_user, '%')
                )
                AND (
                    UPPER(COALESCE(NULLIF(p_view_type, ''), 'ALL')) = 'ALL'
                    OR (
                        UPPER(p_view_type) = 'POST'
                        AND sb.Edit = 0
                        AND COALESCE(pay.PaymentMethod, '') <> 'CL'
                    )
                    OR (
                        UPPER(p_view_type) = 'CORRECT'
                        AND sb.Edit = 1
                        AND h.BillEdit = 1
                    )
                )
            )
    )
    SELECT
        STT, BookingId, Room, Guest, DescriptionServive, Product,
        TotalAmount, DiscountAmount, NetAmount, PaymentID, BillNote,
        Username, Ca, DateGroup, ProductItems,
        ReportTotalAmount, ReportDiscountAmount, ReportNetAmount
    FROM report_rows
    ORDER BY STT;
END
SQL;

        return str_replace(
            ['__PROCEDURE__', '__OUTLET__'],
            ['`'.$procedure.'`', $outlet],
            $sql
        );
    }

    private function registerReport(string $code, array $definition): void
    {
        $now = now();
        $database = DB::connection()->getDatabaseName();
        $parameters = $this->parameters();
        $fields = $this->fields();
        $defaults = [
            'p_from_date' => now()->toDateString(),
            'p_to_date' => now()->toDateString(),
            'p_shift' => '',
            'p_department' => '',
            'p_user' => '',
            'p_view_type' => 'post',
            'p_order_by' => 'Ma',
            'p_order_type' => 'ASC',
            'p_show_details' => false,
        ];

        DB::table('report_data_sources')->updateOrInsert(['code' => $code], [
            'name' => $definition['name'],
            'description' => 'Dữ liệu báo cáo hóa đơn dịch vụ buồng phòng theo logic sp_125/sp_202 legacy; mapping tiền runtime cần nghiệm thu với legacy.',
            'source_type' => 'procedure',
            'schema_name' => $database,
            'object_name' => $definition['procedure'],
            'parameter_schema' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
            'field_schema' => json_encode($fields, JSON_UNESCAPED_UNICODE),
            'sample_parameters' => json_encode($defaults, JSON_UNESCAPED_UNICODE),
            'max_rows' => 5000,
            'is_active' => true,
            'last_discovered_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $sourceId = DB::table('report_data_sources')->where('code', $code)->value('id');

        $provider = require database_path('report_templates/'.$definition['template_file']);
        $templateDefinition = $provider->definition();
        DB::table('templates')->updateOrInsert(['report' => $definition['template']], [
            'group' => 'Báo cáo dịch vụ',
            'name' => $definition['name'].' - Mẫu tham chiếu',
            'report_data_source_id' => $sourceId,
            'parameter_defaults' => json_encode($defaults, JSON_UNESCAPED_UNICODE),
            'page_size' => $templateDefinition['page_size'] ?? 'A4',
            'page_orientation' => $templateDefinition['page_orientation'] ?? 'portrait',
            'margin_top' => $templateDefinition['margin_top'] ?? 6,
            'margin_bottom' => $templateDefinition['margin_bottom'] ?? 6,
            'margin_left' => $templateDefinition['margin_left'] ?? 5,
            'margin_right' => $templateDefinition['margin_right'] ?? 5,
            'content_json' => json_encode($templateDefinition['content_json'] ?? [], JSON_UNESCAPED_UNICODE),
            'content_html' => $templateDefinition['content_html'] ?? '',
            'css' => $templateDefinition['css'] ?? '',
            'is_default' => false,
            'version' => $templateDefinition['version'] ?? '1.0',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $templateId = DB::table('templates')->where('report', $definition['template'])->value('id');

        DB::table('report_definitions')->updateOrInsert(['code' => $code], [
            'name' => $definition['name'],
            'group' => 'Báo cáo dịch vụ',
            'description' => 'Danh sách hóa đơn dịch vụ '.$definition['outlet'].' theo logic legacy sp_125/sp_202.',
            'report_data_source_id' => $sourceId,
            'parameter_ui_schema' => json_encode($this->parameterUiSchema(), JSON_UNESCAPED_UNICODE),
            'sort_order' => $definition['sort_order'],
            'is_active' => true,
            'show_in_menu' => true,
            'menu_locations' => json_encode(['reservation', 'frontdesk']),
            'menu_top_order' => 40,
            'menu_group_order' => 20,
            'menu_item_order' => $definition['sort_order'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $reportId = DB::table('report_definitions')->where('code', $code)->value('id');
        DB::table('report_definition_template')->updateOrInsert(
            ['report_definition_id' => $reportId, 'template_id' => $templateId],
            ['is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]
        );
    }

    private function parameters(): array
    {
        return [
            ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 1, 'required' => true],
            ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 2, 'required' => true],
            ['name' => 'p_shift', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(20)', 'position' => 3, 'required' => false],
            ['name' => 'p_department', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(50)', 'position' => 4, 'required' => false],
            ['name' => 'p_user', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(50)', 'position' => 5, 'required' => false],
            ['name' => 'p_view_type', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(20)', 'position' => 6, 'required' => true],
            ['name' => 'p_order_by', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(30)', 'position' => 7, 'required' => true],
            ['name' => 'p_order_type', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'char(4)', 'position' => 8, 'required' => true],
            ['name' => 'p_show_details', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 9, 'required' => false],
        ];
    }

    private function fields(): array
    {
        $names = [
            'STT', 'BookingId', 'Room', 'Guest', 'DescriptionServive', 'Product',
            'TotalAmount', 'DiscountAmount', 'NetAmount', 'PaymentID', 'BillNote',
            'Username', 'Ca', 'DateGroup', 'ProductItems', 'ReportTotalAmount',
            'ReportDiscountAmount', 'ReportNetAmount',
        ];
        $numeric = ['STT', 'TotalAmount', 'DiscountAmount', 'NetAmount', 'ReportTotalAmount', 'ReportDiscountAmount', 'ReportNetAmount'];

        return array_map(static fn (string $name): array => [
            'name' => $name,
            'type' => in_array($name, $numeric, true) ? (in_array($name, ['STT'], true) ? 'integer' : 'number') : 'string',
            'nullable' => $name !== 'STT',
        ], $names);
    }

    private function parameterUiSchema(): array
    {
        return [
            ['name' => 'p_from_date', 'label' => 'Ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            ['name' => 'p_shift', 'label' => 'Ca làm việc', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'report-shifts', 'options' => []],
            ['name' => 'p_department', 'label' => 'Chọn bộ phận', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'service-departments', 'options' => []],
            ['name' => 'p_user', 'label' => 'Chọn người dùng', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'users', 'options' => []],
            ['name' => 'p_view_type', 'label' => 'Loại dữ liệu', 'control' => 'radio', 'default' => 'post', 'required' => true, 'options' => [
                ['value' => 'all', 'label' => 'All'], ['value' => 'post', 'label' => 'Post'],
                ['value' => 'correct', 'label' => 'Correct'], ['value' => 'free', 'label' => 'Free'],
            ]],
            ['name' => 'p_order_by', 'label' => 'Sắp xếp theo', 'control' => 'select', 'layout' => 'inline', 'default' => 'Ma', 'required' => true, 'options' => [
                ['value' => 'Ma', 'label' => 'Mã'], ['value' => 'Room', 'label' => 'Phòng'],
                ['value' => 'Date', 'label' => 'Ngày'], ['value' => 'ServiceId', 'label' => 'Dịch vụ'],
                ['value' => 'RefId', 'label' => 'Mã tham chiếu'],
            ]],
            ['name' => 'p_order_type', 'label' => 'Thứ tự', 'control' => 'select', 'layout' => 'inline', 'default' => 'ASC', 'required' => true, 'options' => [
                ['value' => 'ASC', 'label' => 'ASC'], ['value' => 'DESC', 'label' => 'DESC'],
            ]],
            ['name' => 'p_show_details', 'label' => 'Xem chi tiết', 'control' => 'checkbox', 'default' => false, 'required' => false],
        ];
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (array_reverse(self::REPORTS, true) as $code => $definition) {
            $this->removeReport($code, $definition);
        }
    }

    public function removeReport(string $code, array $definition): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $reportId = DB::table('report_definitions')->where('code', $code)->value('id');
        $templateId = DB::table('templates')->where('report', $definition['template'])->value('id');
        if ($reportId) {
            DB::table('report_definition_template')->where('report_definition_id', $reportId)->delete();
            DB::table('report_definitions')->where('id', $reportId)->delete();
        }
        if ($templateId) {
            DB::table('templates')->where('id', $templateId)->delete();
        }
        DB::table('report_data_sources')->where('code', $code)->delete();
        DB::unprepared('DROP PROCEDURE IF EXISTS `'.$definition['procedure'].'`');
    }
};
