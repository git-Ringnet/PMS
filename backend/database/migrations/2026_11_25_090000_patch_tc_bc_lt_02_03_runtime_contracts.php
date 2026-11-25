<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const COMPANY_PROCEDURES = [
        'rpt_company_occupancy',
        'rpt_company_occupancy_detail',
    ];

    private const PRODUCT_PROCEDURES = [
        'rpt_laundry_invoices_by_product',
        'rpt_breakage_invoices_by_product',
        'rpt_minibar_invoices_by_product',
    ];

    private const PRODUCT_REPORTS = [
        'LAUNDRY_INVOICES_BY_PRODUCT',
        'BREAKAGE_INVOICES_BY_PRODUCT',
        'MINIBAR_INVOICES_BY_PRODUCT',
    ];

    private const FREE_REPORTS = [
        'LAUNDRY_FREE_INVOICES',
        'BREAKAGE_FREE_INVOICES',
        'MINIBAR_FREE_INVOICES',
    ];

    private const TEMPLATES = [
        'LAUNDRY_INVOICES_STANDARD' => 'laundry_invoices_reference.php',
        'BREAKAGE_INVOICES_STANDARD' => 'breakage_invoices_reference.php',
        'MINIBAR_INVOICES_STANDARD' => 'minibar_invoices_reference.php',
        'LAUNDRY_INVOICES_BY_PRODUCT_STANDARD' => 'laundry_invoices_by_product_reference.php',
        'BREAKAGE_INVOICES_BY_PRODUCT_STANDARD' => 'breakage_invoices_by_product_reference.php',
        'MINIBAR_INVOICES_BY_PRODUCT_STANDARD' => 'minibar_invoices_by_product_reference.php',
        'LAUNDRY_FREE_INVOICES_STANDARD' => 'laundry_free_invoices_reference.php',
        'BREAKAGE_FREE_INVOICES_STANDARD' => 'breakage_free_invoices_reference.php',
        'MINIBAR_FREE_INVOICES_STANDARD' => 'minibar_free_invoices_reference.php',
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::COMPANY_PROCEDURES as $procedure) {
            $this->rewriteProcedure($procedure, [
                '/(s\.(?:company_code|market_code|user_sale|source_code))\s*=\s*(p_(?:company|segment|user_sale|source_code))/' => '$1 COLLATE utf8mb4_unicode_ci = $2',
                '/(c\.code|m\.code|cs\.code|b\.sales_person|b\.created_by|r\.area)\s*=\s*(p_(?:company|segment|user_sale|source_code|area))/' => '$1 COLLATE utf8mb4_unicode_ci = $2',
                '/FIND_IN_SET\(p_area,\s*(s\.area_codes)\)/' => 'FIND_IN_SET(p_area COLLATE utf8mb4_unicode_ci, $1 COLLATE utf8mb4_unicode_ci)',
            ]);
        }

        foreach (self::PRODUCT_PROCEDURES as $procedure) {
            $this->rewriteProcedure($procedure, [
                '/(h\.BillUsername|h\.Department)\s+LIKE\s+CONCAT\(/' => '$1 COLLATE utf8mb4_unicode_ci LIKE CONCAT(',
                '/CAST\(h\.BillShift AS CHAR\)\s+LIKE\s+CONCAT\(/' => 'CAST(h.BillShift AS CHAR) COLLATE utf8mb4_unicode_ci LIKE CONCAT(',
            ]);
        }

        foreach (self::PRODUCT_REPORTS as $code) {
            $this->updateUiSchema($code, static function (array $parameter): array {
                if (($parameter['name'] ?? '') === 'p_freeitem') {
                    $parameter['control'] = 'pill-toggle';
                    $parameter['inactive_label'] = 'Hàng bán';
                    $parameter['active_label'] = 'Hàng miễn phí';
                    $parameter['default'] = 0;
                }

                return $parameter;
            });
        }

        $allowedFreeParameters = ['p_from_date', 'p_to_date', 'p_user', 'p_order_by', 'p_order_type', 'p_show_details'];
        foreach (self::FREE_REPORTS as $code) {
            $this->updateUiSchema($code, static function (array $parameter): array {
                if (($parameter['name'] ?? '') === 'p_order_by') {
                    $parameter['options'] = [['value' => 'Ma', 'label' => 'Mã']];
                    $parameter['default'] = 'Ma';
                }

                return $parameter;
            }, $allowedFreeParameters);
        }

        foreach (self::TEMPLATES as $report => $providerFile) {
            $definition = (require database_path('report_templates/'.$providerFile))->definition();
            DB::table('templates')->where('report', $report)->update([
                'content_html' => $definition['content_html'],
                'content_json' => json_encode($definition['content_json'], JSON_UNESCAPED_UNICODE),
                'css' => $definition['css'],
                'updated_at' => now(),
            ]);
        }
    }

    private function rewriteProcedure(string $name, array $replacements): void
    {
        $row = DB::selectOne('SHOW CREATE PROCEDURE `'.$name.'`');
        $values = (array) $row;
        $sql = $values['Create Procedure'] ?? null;
        if (! is_string($sql) || $sql === '') {
            throw new \RuntimeException("Không đọc được stored procedure {$name} để vá TC_BC_LT_02/03.");
        }

        $sql = preg_replace('/CREATE\\s+DEFINER=`[^`]+`@`[^`]+`\\s+PROCEDURE/i', 'CREATE PROCEDURE', $sql) ?? $sql;
        foreach ($replacements as $pattern => $replacement) {
            $sql = preg_replace($pattern, $replacement, $sql) ?? $sql;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS `'.$name.'`');
        DB::unprepared($sql);
    }

    private function updateUiSchema(string $code, callable $transform, ?array $allowed = null): void
    {
        $definition = DB::table('report_definitions')->where('code', $code)->first(['id', 'parameter_ui_schema']);
        if (! $definition) {
            return;
        }

        $schema = json_decode($definition->parameter_ui_schema ?: '[]', true);
        if (! is_array($schema)) {
            return;
        }

        $schema = array_values(array_map($transform, array_filter($schema, static fn (array $parameter): bool => $allowed === null || in_array($parameter['name'] ?? '', $allowed, true))));
        DB::table('report_definitions')->where('id', $definition->id)->update([
            'parameter_ui_schema' => json_encode($schema, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Không khôi phục contract lỗi hoặc template đã thay thế.
    }
};
