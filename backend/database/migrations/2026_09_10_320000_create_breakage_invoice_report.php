<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const DEFINITION = [
        'outlet' => 'BR',
        'procedure' => 'rpt_breakage_invoices',
        'template' => 'BREAKAGE_INVOICES_STANDARD',
        'template_file' => 'breakage_invoices_reference.php',
        'name' => 'Báo cáo hóa đơn hàng bể vỡ',
        'sort_order' => 40,
    ];

    public function up(): void
    {
        $installer = require database_path('migrations/2026_09_10_300000_create_housekeeping_invoice_reports.php');
        $installer->installReport('BREAKAGE_INVOICES', self::DEFINITION);
    }

    public function down(): void
    {
        $installer = require database_path('migrations/2026_09_10_300000_create_housekeeping_invoice_reports.php');
        $installer->removeReport('BREAKAGE_INVOICES', self::DEFINITION);
    }
};
