<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Đã gộp vào 2026_09_03_260000_create_no_show_report.php.
    }

    public function down(): void
    {
        // Giữ template mới để không khôi phục dòng tổng ra ngoài bảng.
    }
};
