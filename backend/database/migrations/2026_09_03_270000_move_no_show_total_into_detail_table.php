<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        (require database_path('report_templates/no_show_reference.php'))->apply();
    }

    public function down(): void
    {
        // Giữ template mới để không khôi phục dòng tổng ra ngoài bảng.
    }
};
