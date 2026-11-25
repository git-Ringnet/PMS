<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TEMPLATES = [
        'LAUNDRY_INVOICES_STANDARD' => 'laundry_invoices_reference.php',
        'BREAKAGE_INVOICES_STANDARD' => 'breakage_invoices_reference.php',
        'MINIBAR_INVOICES_STANDARD' => 'minibar_invoices_reference.php',
        'LAUNDRY_FREE_INVOICES_STANDARD' => 'laundry_free_invoices_reference.php',
        'BREAKAGE_FREE_INVOICES_STANDARD' => 'breakage_free_invoices_reference.php',
        'MINIBAR_FREE_INVOICES_STANDARD' => 'minibar_free_invoices_reference.php',
    ];

    public function up(): void
    {
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

    public function down(): void
    {
        // Không hoàn nguyên template có thể đã được người dùng chỉnh sau cập nhật.
    }
};
