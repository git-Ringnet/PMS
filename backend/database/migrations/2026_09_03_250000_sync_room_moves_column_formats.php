<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;

        (require database_path('report_templates/room_moves_reference.php'))->apply();
    }

    public function down(): void
    {
        // Không khôi phục binding có modifier nằm chung trong tên trường.
    }
};
