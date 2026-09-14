<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $definition = (require database_path('report_templates/deposits_sale_reference.php'))->definition();

        DB::table('templates')->where('report', 'DEPOSITS_SALE_REFERENCE')->update([
            'content_json' => json_encode($definition['content_json'], JSON_UNESCAPED_UNICODE),
            'content_html' => $definition['content_html'],
            'css' => $definition['css'],
            'version' => '1.9',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Preserve user-configured static table styles on rollback.
    }
};
