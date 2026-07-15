<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng payments — dùng cho cả Đặt cọc (Deposit) & Thanh toán (Payment).
     * Theo nghiệp vụ mục 4.9 — PLAN_NGHIEP_VU_DAT_PHONG.md
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // =========================================
            // THAM CHIẾU ĐỐI TƯỢNG
            // =========================================
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('booking_room_id')->nullable()->constrained('booking_rooms')->nullOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained('guests')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();

            // =========================================
            // THÔNG TIN CỌC / THANH TOÁN
            // =========================================
            $table->date('date');                               // Ngày cọc (cho phép chọn ngày cũ)
            $table->time('open_time')->nullable();             // Giờ tạo
            $table->string('guest_display')->nullable();       // Hiển thị: mã booking + công ty + tên booker
            $table->string('description')->nullable();         // Mô tả: "Deposit + (hình thức TT)"
            $table->decimal('amount', 15, 2)->default(0);     // Số tiền
            $table->decimal('total_amount_before_split', 15, 2)->nullable(); // Số tiền gốc trước khi tách

            // =========================================
            // PHÂN LOẠI
            // =========================================
            // pack2 = "DPR" nếu là dòng đặt cọc; null/other = thanh toán thường
            $table->string('pack2', 10)->nullable()->comment('"DPR" = deposit row');
            // pack4 = "AP" nếu là advance payment
            $table->string('pack4', 10)->nullable()->comment('"AP" = advance payment');

            // =========================================
            // LIÊN KẾT THANH TOÁN
            // =========================================
            $table->unsignedBigInteger('folio_id')->nullable();       // Folio gắn vào (mặc định folio 1)
            $table->unsignedBigInteger('payment_id')->nullable();     // Nếu dòng cọc đã thanh toán → ID payment
            // Đối trừ: reversal_ref trỏ sang dòng âm hoặc dương tương ứng
            $table->unsignedBigInteger('reversal_ref')->nullable()->comment('ID dòng đối trừ khi xóa/chuyển cọc');

            // =========================================
            // PHƯƠNG THỨC THANH TOÁN
            // =========================================
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->string('debit_account')->nullable();       // Tài khoản ngân hàng
            $table->string('image_path', 255)->nullable();     // Ảnh chứng từ cọc

            // =========================================
            // HÓA ĐƠN VAT
            // =========================================
            $table->string('vat_number')->nullable();
            $table->string('serial')->nullable();
            $table->string('invoice_number')->nullable();

            // =========================================
            // TRẠNG THÁI & CỜ HIỆU
            // =========================================
            // status: 1=chưa TT, 2=đã TT, 3=đã xóa
            $table->unsignedTinyInteger('status')->default(1)->comment('1=chưa TT, 2=đã TT, 3=đã xóa');
            // edit_flag: 0=bình thường, 1=dòng đã hủy/đối trừ
            $table->unsignedTinyInteger('edit_flag')->default(0)->comment('0=normal, 1=cancelled/reversed');

            // =========================================
            // MODULE / CA / NGƯỜI TẠO
            // =========================================
            $table->string('department_id')->nullable();       // Bộ phận tạo cọc
            $table->string('outlet')->nullable();              // Nếu tạo ở module nhà hàng
            $table->string('username')->nullable();            // User tạo
            $table->string('shift')->nullable();               // Ca làm việc khi tạo
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('booking_id');
            $table->index('booking_room_id');
            $table->index(['status', 'edit_flag']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
