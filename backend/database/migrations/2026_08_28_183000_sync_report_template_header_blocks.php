<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        foreach (['inhouse_rooms_reference.php', 'complimentary_rooms_reference.php', 'due_in_rooms_reference.php', 'ooo_lock_history_reference.php', 'oos_lock_history_reference.php'] as $file) {
            $path = database_path('report_templates/'.$file);
            if (is_file($path)) (require $path)->apply();
        }
    }

    public function down(): void
    {
        // Template content is intentionally not reverted to avoid losing user edits.
    }
};
