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

        (require database_path('report_templates/early_checkout_rooms_reference.php'))->apply();
    }

    public function down(): void
    {
        // Giữ lại template đã tạo; migration này chỉ bổ sung cấu hình Design.
    }
};
