<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_settings')) {
            return;
        }

        // JSON_CONTAINS_PATH / JSON_REMOVE chỉ có trên MySQL; DB test (SQLite) không có dữ liệu cũ cần dọn.
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::table('user_settings')
            ->whereRaw("JSON_CONTAINS_PATH(settings, 'one', '$.topbar_color')")
            ->update([
                'settings' => DB::raw("JSON_REMOVE(settings, '$.topbar_color')"),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_settings')) {
            return;
        }

        // Không thể khôi phục giá trị màu cá nhân đã được xóa một cách chính xác.
    }
};
