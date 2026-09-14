<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const DEFINITION = [
        'outlet' => 'MB',
        'procedure' => 'rpt_minibar_invoices',
        'template' => 'MINIBAR_INVOICES_STANDARD',
        'template_file' => 'minibar_invoices_reference.php',
        'name' => 'Báo cáo hóa đơn minibar',
        'sort_order' => 41,
    ];

    public function up(): void
    {
        $installer = require database_path('migrations/2026_09_10_300000_create_housekeeping_invoice_reports.php');
        $installer->installReport('MINIBAR_INVOICES', self::DEFINITION);
    }

    public function down(): void
    {
        $installer = require database_path('migrations/2026_09_10_300000_create_housekeeping_invoice_reports.php');
        $installer->removeReport('MINIBAR_INVOICES', self::DEFINITION);
    }
};
