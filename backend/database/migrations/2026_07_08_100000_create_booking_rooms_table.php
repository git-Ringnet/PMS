<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tạo bảng booking_rooms (SP2100 — PhongThue)
     * Đây là bảng lõi chứa chi tiết từng phòng trong 1 booking.
     * Thay thế hoàn toàn cột room_allocations JSON trong bảng bookings.
     */
    public function up(): void
    {
        Schema::create('booking_rooms', function (Blueprint $table) {
            $table->string('id', 50)->primary();

            // =========================================
            // LIÊN KẾT BOOKING
            // =========================================
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            // Số phòng vật lý đã gán (NULL nếu chưa gán — pending assignment)
            $table->string('room_number', 20)->nullable();
            $table->foreign('room_number')->references('room_number')->on('rooms')->nullOnDelete();

            // =========================================
            // LOẠI PHÒNG
            // =========================================
            // Loại phòng hiện tại (có thể thay đổi khi nâng/hạ hạng)
            $table->foreignId('room_class_id')->constrained('room_classes')->restrictOnDelete();
            // Loại phòng khởi tạo ban đầu — giữ nguyên kể cả khi nâng hạng (LP Khởi tạo / Pack4)
            $table->unsignedBigInteger('original_room_class_id')->nullable();
            $table->foreign('original_room_class_id')->references('id')->on('room_classes')->nullOnDelete();

            // =========================================
            // NGÀY GIỜ
            // =========================================
            $table->date('arrival_date');       // Ngày đến của phòng này (có thể khác header booking)
            $table->date('departure_date');     // Ngày đi của phòng này
            $table->date('actual_arrival_date')->nullable(); // Ngày đến thực tế (giữ nguyên gốc khi chuyển phòng)
            $table->time('arrival_time')->nullable();    // Giờ đến
            $table->time('departure_time')->nullable();  // Giờ đi

            // =========================================
            // GIÁ & GIƯỜNG PHỤ
            // =========================================
            $table->decimal('rate', 15, 2)->default(0);             // Giá phòng mỗi đêm
            $table->string('rate_code', 50)->nullable();            // Mã giá phòng
            $table->boolean('breakfast')->default(false);           // Có ăn sáng hay không
            $table->boolean('is_day_use')->default(false);          // Phòng Dayuse
            $table->string('discount', 100)->nullable();            // Tên/Chuỗi giảm giá hiển thị
            $table->string('discount_type', 10)->nullable();        // up hoặc down
            $table->decimal('discount_value', 15, 2)->default(0);   // Giá trị giảm giá
            $table->string('discount_unit', 10)->nullable();        // percent hoặc amount
            $table->decimal('base_price', 15, 2)->default(0);       // Giá gốc trước khi giảm
            $table->unsignedTinyInteger('adults')->default(1);       // Số người lớn
            $table->unsignedTinyInteger('extra_bed_qty')->default(0);        // Số giường phụ
            $table->decimal('extra_bed_rate', 15, 2)->default(0);   // Giá thêm giường / đêm

            // =========================================
            // TRẠNG THÁI
            // =========================================
            // 0 = Booked (đã đặt, chưa check-in)
            // 1 = CheckedIn (đang ở)
            // 2 = CheckedOut (đã trả phòng)
            // 3 = Cancelled (đã hủy)
            $table->unsignedTinyInteger('status')->default(0);

            // Do Not Move — khóa không cho chuyển số phòng (1 = đang khóa)
            $table->unsignedTinyInteger('is_do_not_move')->default(0);
            $table->string('move_room', 20)->nullable();            // Mã phòng mới lúc chuyển phòng

            // =========================================
            // GHI CHÚ
            // =========================================
            $table->text('note')->nullable(); // Ghi chú riêng của dòng phòng này

            // =========================================
            // NGƯỜI TẠO / CẬP NHẬT
            // =========================================
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('check_in_user', 100)->nullable();       // Người thực hiện check-in
            $table->string('check_out_user', 100)->nullable();      // Người thực hiện check-out

            $table->timestamps();
            $table->softDeletes(); // Xóa mềm để giữ lịch sử

            // =========================================
            // INDEXES
            // =========================================
            $table->index('booking_id');
            $table->index('room_number');
            $table->index('status');
            $table->index(['arrival_date', 'departure_date']); // Query AV theo ngày
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_rooms');
    }
};
