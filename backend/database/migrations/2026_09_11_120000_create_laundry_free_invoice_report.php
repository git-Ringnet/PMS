<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const DEFINITION = [
        'outlet' => 'LA',
        'procedure' => 'rpt_laundry_free_invoices',
        'template' => 'LAUNDRY_FREE_INVOICES_STANDARD',
        'template_file' => 'laundry_free_invoices_reference.php',
        'name' => 'Báo cáo hóa đơn giặt ủi miễn phí',
        'sort_order' => 42,
    ];

    public function up(): void
    {
        $installer = require database_path('migrations/2026_09_10_300000_create_housekeeping_invoice_reports.php');
        $installer->installReport('LAUNDRY_FREE_INVOICES', self::DEFINITION);
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::table('report_definitions')->where('code', 'LAUNDRY_FREE_INVOICES')->update([
                'parameter_ui_schema' => json_encode([
                    ['name' => 'p_from_date', 'label' => 'Ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
                    ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
                    ['name' => 'p_user', 'label' => 'Chọn người dùng', 'control' => 'select', 'default' => '', 'required' => false, 'placeholder' => 'Select Value', 'options_source' => 'users', 'options' => []],
                    ['name' => 'p_order_by', 'label' => 'Sắp xếp theo', 'control' => 'select', 'layout' => 'inline', 'default' => 'Ma', 'required' => true, 'options' => [['value' => 'Ma', 'label' => 'Mã']]],
                    ['name' => 'p_order_type', 'label' => 'Thứ tự', 'control' => 'select', 'layout' => 'inline', 'default' => 'ASC', 'required' => true, 'options' => [['value' => 'ASC', 'label' => 'ASC'], ['value' => 'DESC', 'label' => 'DESC']]],
                    ['name' => 'p_show_details', 'label' => 'Detail', 'control' => 'checkbox', 'default' => false, 'required' => false],
                ], JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    public function down(): void
    {
        $installer = require database_path('migrations/2026_09_10_300000_create_housekeeping_invoice_reports.php');
        $installer->removeReport('LAUNDRY_FREE_INVOICES', self::DEFINITION);
    }
};
