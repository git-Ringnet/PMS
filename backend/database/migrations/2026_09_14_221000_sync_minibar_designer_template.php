<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $definition = (require database_path('report_templates/minibar_invoices_by_product_reference.php'))->definition();
        DB::table('templates')->where('report', 'MINIBAR_INVOICES_BY_PRODUCT_STANDARD')->update([
            'content_json' => json_encode($definition['content_json'], JSON_UNESCAPED_UNICODE),
            'content_html' => $definition['content_html'],
            'css' => $definition['css'],
            'version' => '1.1',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Template synchronization is intentionally non-destructive.
    }
};
