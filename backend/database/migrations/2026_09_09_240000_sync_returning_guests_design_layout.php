<?php

return new class extends \Illuminate\Database\Migrations\Migration
{
    public function up(): void
    {
        if (config('database.default') !== 'mysql') {
            return;
        }

        (require database_path('report_templates/returning_guests_reference.php'))->apply();
    }

    public function down(): void
    {
        // Không khôi phục layout cũ để tránh ghi đè phiên bản mẫu đã được người dùng chỉnh sửa.
    }
};
