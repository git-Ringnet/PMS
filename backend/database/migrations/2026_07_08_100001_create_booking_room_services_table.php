<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tạo bảng booking_room_services (SP2102 — PhongThueDichVuTuDong)
     * Dùng chung cho:
     *   - Dịch vụ set-up trước (Epic 4) — tự động post khi sang ngày
     *   - Extra Bed theo từng ngày (Epic 14) — service_code = 'EB'
     *   - Phụ thu ăn sáng trẻ em (Epic 13) — service_code = 'BD'
     *   - Tiền phòng mỗi đêm (service_code = 'RM') khi cần lưu chi tiết theo ngày
     */
    public function up(): void
    {
        Schema::create('booking_room_services', function (Blueprint $table) {
            $table->id();

            // =========================================
            // LIÊN KẾT
            // =========================================
            $table->string('booking_room_id', 50);
            $table->foreign('booking_room_id')->references('id')->on('booking_rooms')->cascadeOnDelete();

            // Mã dịch vụ: 'RM' (tiền phòng), 'EB' (extra bed), 'BD' (phụ thu ăn sáng trẻ em)
            // hoặc mã dịch vụ bất kỳ từ bảng hotel_services
            $table->string('service_code', 30);

            // Tên dịch vụ (lưu lại để không phụ thuộc FK khi dịch vụ bị xóa)
            $table->string('service_name', 100)->nullable();

            // =========================================
            // CHI TIẾT SỬ DỤNG
            // =========================================
            $table->date('service_date');               // Ngày áp dụng dịch vụ
            $table->decimal('quantity', 10, 2)->default(1);  // Số lượng
            $table->decimal('rate', 15, 2)->default(0);      // Đơn giá
            $table->decimal('total_amount', 15, 2)->default(0)->comment('Tổng tiền = quantity * rate');

            // =========================================
            // FIT / GIT
            // =========================================
            // 1 = FIT — bill gửi về Folio của phòng
            // 0 = GIT — bill gửi về Folio của booking (đoàn)
            $table->unsignedTinyInteger('is_room')->default(1);

            // =========================================
            // TRẠNG THÁI POST BILL
            // =========================================
            // 0 = Chưa post (sẽ auto-post khi sang ngày)
            // 1 = Đã post sang Folio
            $table->unsignedTinyInteger('is_posted')->default(0);
            $table->dateTime('posted_at')->nullable(); // Thời điểm đã post

            // =========================================
            // NGƯỜI TẠO
            // =========================================
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // =========================================
            // INDEXES
            // =========================================
            $table->index('booking_room_id');
            $table->index('service_date');
            $table->index('service_code');
            $table->index('is_posted'); // Query batch job auto-post
            // Chống trùng: 1 dịch vụ chỉ 1 dòng / ngày / phòng (trừ RM)
            $table->unique(['booking_room_id', 'service_code', 'service_date'], 'unique_room_service_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_room_services');
    }
};
