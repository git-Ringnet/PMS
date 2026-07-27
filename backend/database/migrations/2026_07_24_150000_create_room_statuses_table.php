<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tạo bảng room_statuses
        Schema::create('room_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();        // Mã định danh (vacant_ready, dirty, ooo, ...)
            $table->string('name_vi');               // Tên tiếng Việt
            $table->string('name_en');               // Tên tiếng Anh
            $table->string('icon')->nullable();      // Tên icon trong RoomIcon.vue
            $table->boolean('is_occupied')->default(false); // Phòng đang có khách?
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 2. Seed dữ liệu tình trạng phòng chuẩn
        DB::table('room_statuses')->insert([
            // --- Phòng trống (Vacant) ---
            ['id' => 1,  'code' => 'vacant_ready',    'name_vi' => 'Phòng sẵn sàng',       'name_en' => 'Vacant Ready',      'icon' => 'available',           'is_occupied' => false, 'sort_order' => 1,  'created_at' => now(), 'updated_at' => now()],
            ['id' => 2,  'code' => 'vacant_dirty',    'name_vi' => 'Phòng chưa dọn',       'name_en' => 'Vacant Dirty',      'icon' => 'dirty',               'is_occupied' => false, 'sort_order' => 2,  'created_at' => now(), 'updated_at' => now()],
            ['id' => 3,  'code' => 'vacant_clean',    'name_vi' => 'Phòng sạch',            'name_en' => 'Vacant Clean',      'icon' => 'clean',               'is_occupied' => false, 'sort_order' => 3,  'created_at' => now(), 'updated_at' => now()],
            ['id' => 4,  'code' => 'ooo',             'name_vi' => 'Phòng sửa chữa',        'name_en' => 'Out Of Order',      'icon' => 'ooo',                 'is_occupied' => false, 'sort_order' => 4,  'created_at' => now(), 'updated_at' => now()],
            ['id' => 5,  'code' => 'oos',             'name_vi' => 'Phòng Dịch Vụ',         'name_en' => 'Out Of Service',    'icon' => 'oos',                 'is_occupied' => false, 'sort_order' => 5,  'created_at' => now(), 'updated_at' => now()],
            ['id' => 6,  'code' => 'turndown',        'name_vi' => 'Lau dọn (Turndown)',    'name_en' => 'Turndown',          'icon' => 'checkout',            'is_occupied' => false, 'sort_order' => 6,  'created_at' => now(), 'updated_at' => now()],
            ['id' => 7,  'code' => 'housekeeping',    'name_vi' => 'Dịch vụ dọn phòng',    'name_en' => 'Housekeeping',      'icon' => 'housekeeping-service','is_occupied' => false, 'sort_order' => 7,  'created_at' => now(), 'updated_at' => now()],
            ['id' => 8,  'code' => 'dnd',             'name_vi' => 'Không làm phiền',       'name_en' => 'Do Not Disturb',    'icon' => 'dnd',                 'is_occupied' => false, 'sort_order' => 8,  'created_at' => now(), 'updated_at' => now()],
            ['id' => 16, 'code' => 'vacant_priority', 'name_vi' => 'Phòng ưu tiên dọn',    'name_en' => 'Vacant Priority',   'icon' => 'priority',            'is_occupied' => false, 'sort_order' => 16, 'created_at' => now(), 'updated_at' => now()],
            // --- Phòng có khách (Occupied) ---
            ['id' => 11, 'code' => 'occupied_ready',  'name_vi' => 'Chiếm dụng sẵn sàng',  'name_en' => 'Occupied Ready',    'icon' => null,                  'is_occupied' => true,  'sort_order' => 11, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 12, 'code' => 'occupied_dirty',  'name_vi' => 'Chiếm dụng chưa dọn',  'name_en' => 'Occupied Dirty',    'icon' => 'dirty',               'is_occupied' => true,  'sort_order' => 12, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 13, 'code' => 'occupied_clean',  'name_vi' => 'Chiếm dụng sạch',      'name_en' => 'Occupied Clean',    'icon' => null,                  'is_occupied' => true,  'sort_order' => 13, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 14, 'code' => 'occupied_ooo',    'name_vi' => 'Chiếm dụng OOO',        'name_en' => 'Occupied OOO',      'icon' => 'ooo',                 'is_occupied' => true,  'sort_order' => 14, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('room_statuses');
    }
};
