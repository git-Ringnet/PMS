<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $definition = (require database_path('report_templates/deposits_sale_reference.php'))->definition();
        DB::table('templates')->where('report', 'DEPOSITS_SALE_REFERENCE')->update([
            'content_html' => $definition['content_html'],
            'content_json' => json_encode($definition['content_json'], JSON_UNESCAPED_UNICODE),
            'css' => $definition['css'],
            'version' => '1.4',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Keep the report spacing compatible on rollback.
    }
};
