<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TEMPLATES = [
        'COMPANY_OCCUPANCY_REFERENCE' => 'company_occupancy_reference.php',
        'COMPANY_OCCUPANCY_DETAIL_REFERENCE' => 'company_occupancy_detail_reference.php',
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
        // Không hoàn nguyên typography hoặc template đã đồng bộ.
    }
};
