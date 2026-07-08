<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('room_locks', function (Blueprint $table) {
            $table->id();
            $table->string('room_number'); // Số phòng cần khóa
            $table->foreign('room_number')->references('room_number')->on('rooms')->onDelete('cascade'); // Liên kết trực tiếp tới số phòng trong bảng rooms
            $table->dateTime('start_date')->nullable(); // Thời điểm bắt đầu khóa phòng
            $table->dateTime('end_date')->nullable(); // Thời điểm kết thúc khóa phòng (mặc định 23:59:59)
            $table->string('reason')->nullable(); // Lý do khóa phòng (mô tả chi tiết)
            $table->integer('maintenance_percent')->default(0); // Tỷ lệ hao mòn/bảo trì (%)
            $table->string('status')->default('New'); // Trạng thái xử lý (New, Active, Done...)
            $table->string('username')->default('NB0016'); // Tài khoản người thực hiện khóa phòng
            $table->string('lock_type')->nullable(); // Loại khóa phòng: OOO (Out of Order) hoặc OOS (Out of Service)
            $table->unsignedTinyInteger('is_active')->default(1); // 1 = Đang có hiệu lực/Lên kế hoạch khóa, 2 = Đã mở khóa trước thời hạn
            $table->string('unlock_username')->nullable(); // Tài khoản người thực hiện mở khóa phòng
            $table->dateTime('unlocked_at')->nullable(); // Thời điểm thực hiện mở khóa phòng
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_locks');
    }
};
