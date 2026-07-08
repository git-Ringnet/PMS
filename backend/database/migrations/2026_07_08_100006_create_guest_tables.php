<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tạo 4 bảng thông tin khách lưu trú:
     *   1. guests                        (SP2300) — Thông tin khách người lớn
     *   2. booking_room_guests           (SP2200) — Gán khách vào phòng
     *   3. booking_children              (SP2400 + SP2500 gộp) — Trẻ em + gán vào phòng
     *   4. booking_child_breakfast_details (SP2401) — Chi tiết ăn sáng trẻ em
     */
    public function up(): void
    {
        // =============================================
        // 1. guests (SP2300 — Khach)
        // Thông tin khách người lớn — dùng chung, có thể kế thừa cho booking mới
        // =============================================
        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 200);                  // Họ tên đầy đủ (viết hoa)
            $table->string('id_number', 50)->nullable();       // Số CCCD/CMND
            $table->string('passport_number', 50)->nullable(); // Số hộ chiếu
            $table->date('dob')->nullable();                   // Ngày sinh
            // 0 = Nam, 1 = Nữ, 2 = Khác
            $table->unsignedTinyInteger('gender')->nullable();
            $table->string('nationality_code', 5)->nullable(); // Mã quốc tịch (VN, US...)
            $table->string('phone', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('address', 500)->nullable();
            // 0 = Active, 3 = Cancelled (cascade từ hủy phòng)
            $table->unsignedTinyInteger('guest_status')->default(0);
            $table->timestamps();

            // Indexes để tìm kiếm kế thừa thông tin khách cũ
            $table->index('id_number');
            $table->index('passport_number');
            $table->index('full_name');
        });

        // =============================================
        // 2. booking_room_guests (SP2200 — PhongThueKhach)
        // Bảng pivot: gán khách (người lớn) vào từng phòng thuê
        // =============================================
        Schema::create('booking_room_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_room_id')
                ->constrained('booking_rooms')
                ->cascadeOnDelete();
            $table->foreignId('guest_id')
                ->constrained('guests')
                ->restrictOnDelete();
            // 1 = Khách đại diện chính của phòng (guest phải có đầy đủ thông tin hơn)
            $table->boolean('is_primary')->default(false);
            // 0 = Active, 3 = Cancelled
            $table->unsignedTinyInteger('status')->default(0);
            $table->timestamps();

            $table->unique(['booking_room_id', 'guest_id'], 'unique_room_guest');
            $table->index('booking_room_id');
            $table->index('guest_id');
        });

        // =============================================
        // 3. booking_children (SP2400 + SP2500 gộp — TreEm + PhongThueTreEm)
        // Thông tin trẻ em + gán vào phòng trong cùng 1 bảng
        // SP2500 chỉ là pivot đơn giản (booking_child_id + booking_room_id) nên gộp vào đây
        // =============================================
        Schema::create('booking_children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')
                ->constrained('bookings')
                ->cascadeOnDelete();
            // NULL nếu chưa gán vào phòng cụ thể; có giá trị khi đã assign (gộp SP2500)
            $table->unsignedBigInteger('booking_room_id')->nullable();
            $table->foreign('booking_room_id')->references('id')->on('booking_rooms')->nullOnDelete();
            $table->string('full_name', 200)->nullable(); // Tên trẻ em (tùy chọn)
            // 'baby'  = Em bé (mặc định ăn sáng miễn phí, không tính phụ phí)
            // 'child' = Trẻ em (có thể tính phụ phí ăn sáng tùy cấu hình)
            $table->enum('age_group', ['baby', 'child'])->default('child');
            // 0 = Active, 3 = Cancelled
            $table->unsignedTinyInteger('child_status')->default(0);
            $table->timestamps();

            $table->index('booking_id');
            $table->index('booking_room_id');
        });

        // =============================================
        // 4. booking_child_breakfast_details (SP2401 — TreEmAnSangChiTiet)
        // Chi tiết ăn sáng trẻ em theo từng ngày trong giai đoạn ở
        // =============================================
        Schema::create('booking_child_breakfast_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_child_id')
                ->constrained('booking_children')
                ->cascadeOnDelete();
            $table->date('service_date');           // Ngày ăn sáng
            $table->boolean('breakfast')->default(false);       // 1 = Có ăn sáng ngày này
            $table->boolean('is_free')->default(true);          // 1 = Miễn phí (giá = 0đ)
            // 1 = Post bill BD riêng (phụ thu ăn sáng trẻ em)
            // 0 = Không phát sinh bill, trừ thẳng vào tiền phòng
            $table->boolean('is_extra_charge')->default(false);
            // 1 = FIT — bill gửi về Folio của phòng
            // 0 = GIT — bill gửi về Folio của booking (đoàn)
            $table->boolean('is_room')->default(true);
            $table->decimal('amount', 15, 2)->default(0); // Thành tiền theo GiaAnSangTreEm
            $table->timestamps();

            // Không cho trùng: 1 trẻ em chỉ 1 dòng / ngày
            $table->unique(['booking_child_id', 'service_date'], 'unique_child_breakfast_date');
            $table->index('booking_child_id');
            $table->index('service_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_child_breakfast_details');
        Schema::dropIfExists('booking_children');
        Schema::dropIfExists('booking_room_guests');
        Schema::dropIfExists('guests');
    }
};
