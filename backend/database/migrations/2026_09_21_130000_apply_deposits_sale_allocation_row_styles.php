<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONNECTIONS = [
        'mysql',
        'mysql_hkt1',
        'mysql_hkt2',
        'mysql_hkt3',
        'mysql_hkt4',
    ];

    public function up(): void
    {
        $definition = (require database_path('report_templates/deposits_sale_reference.php'))->definition();

        $candidates = array_merge([$this->getConnection()], self::CONNECTIONS);
        $connections = array_values(array_unique(array_filter(
            $candidates,
            static fn (?string $conn): bool => !empty($conn) && config("database.connections.{$conn}") !== null
        )));

        foreach ($connections as $connection) {
            $db = DB::connection($connection);
            if ($db->getDriverName() !== 'mysql') {
                continue;
            }

            $db->table('templates')->where('report', 'DEPOSITS_SALE_REFERENCE')->update([
                'content_json' => json_encode($definition['content_json'], JSON_UNESCAPED_UNICODE),
                'content_html' => $definition['content_html'],
                'css' => $definition['css'],
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Keep consistent allocation row colors when rolling back unrelated changes.
    }
};
