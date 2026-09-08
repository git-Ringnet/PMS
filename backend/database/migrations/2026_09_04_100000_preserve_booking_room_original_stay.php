<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_rooms', function (Blueprint $table) {
            $table->date('planned_departure_date')->nullable()->after('departure_date')
                ->comment('Ngày đi dự kiến ban đầu, giữ nguyên khi checkout sớm');
            $table->unsignedInteger('planned_num_of_days')->nullable()->after('planned_departure_date')
                ->comment('Số đêm dự kiến ban đầu, tương đương NumOfDay của hệ thống cũ');
        });

        // Dữ liệu cũ không còn lịch sử để suy ngược; khởi tạo kế hoạch từ giá trị hiện đang lưu.
        DB::table('booking_rooms')->orderBy('id')->chunk(500, function ($rooms) {
            foreach ($rooms as $room) {
                DB::table('booking_rooms')->where('id', $room->id)->update([
                    'planned_departure_date' => $room->departure_date,
                    'planned_num_of_days' => $room->ActutalNumOfDays,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('booking_rooms', function (Blueprint $table) {
            $table->dropColumn(['planned_departure_date', 'planned_num_of_days']);
        });
    }
};