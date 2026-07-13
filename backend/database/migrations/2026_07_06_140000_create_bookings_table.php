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
        Schema::create('bookings', function (Blueprint $table) {
            // =========================================
            // ĐỊNH DANH BOOKING
            // =========================================
            $table->id(); // ID nội bộ tự tăng
            $table->string('booking_name'); // Tên đăng ký (tên khách/đoàn) — thường viết HOA

            // =========================================
            // NGÀY GIỜ
            // =========================================
            $table->date('arrival_date'); // Ngày đến (CheckIn)
            $table->date('departure_date'); // Ngày đi (CheckOut) — tính từ arrival_date + num_of_days
            $table->unsignedSmallInteger('num_of_days')->default(1); // Số đêm lưu trú (NumOfDays)
            $table->date('booking_date'); // Ngày tạo đăng ký — lấy từ system_date_rolls.system_date
            $table->time('booking_time')->nullable(); // Giờ tạo booking (BookingTime)
            $table->date('confirm_date')->nullable(); // Ngày nhắc xác nhận thông tin với khách (ConfirmDate)
            $table->date('expired_date')->nullable(); // Ngày hết hạn booking (ExpiredDate)

            // =========================================
            // THÔNG TIN CHUYẾN BAY
            // =========================================
            $table->string('arrival_flight')->nullable(); // Số hiệu chuyến bay đến (ArrivalFlight)
            $table->dateTime('arrival_flight_date')->nullable(); // Thời gian chuyến bay đến (ArrivalFlightDate)
            $table->string('departure_flight')->nullable(); // Số hiệu chuyến bay đi (DepartureFlight)
            $table->dateTime('departure_flight_date')->nullable(); // Thời gian chuyến bay đi (DepartureFlightDate)

            // =========================================
            // TRẠNG THÁI
            // =========================================
            // Tình trạng vận hành phòng (Status):
            // 0=Reservation, 1=Checked In, 2=Checked Out, 3=Deleted, 4=No Show, 100=Chuyển phòng
            $table->unsignedTinyInteger('status')->default(0);
            // Tình trạng đặt phòng (BookingStatus) — FK tới registration_statuses (Guaranteed, Allotment, Tentative...)
            $table->foreignId('registration_status_id')->nullable()->constrained('registration_statuses')->nullOnDelete();
            $table->string('color', 20)->nullable(); // Màu hiển thị trên sơ đồ phòng (Color)

            // =========================================
            // LOẠI BOOKING
            // =========================================
            $table->boolean('is_git')->default(false); // 0=FIT (khách lẻ), 1=GIT (đoàn) — WalkIn
            $table->boolean('is_day_use')->default(false); // Thuê theo giờ/ngày (DayUse)
            $table->boolean('breakfast_included')->default(false); // Bao gồm ăn sáng (BreakfastIncluded)
            $table->boolean('has_vat')->default(false); // Xuất hóa đơn VAT

            // =========================================
            // LIÊN KẾT ĐỐI TƯỢNG / THỊ TRƯỜNG
            // =========================================
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete(); // Công ty / Lữ hành (Company / TravelAgency)
            $table->foreignId('market_id')->nullable()->constrained('markets')->nullOnDelete(); // Thị trường (MarketSegment)
            $table->foreignId('customer_source_id')->nullable()->constrained('customer_sources')->nullOnDelete(); // Nguồn khách (SourceCode)
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete(); // Chi nhánh (Branch)
            $table->foreignId('booker_id')->nullable()->constrained('bookers')->nullOnDelete(); // Người đặt phòng (Booker)
            $table->string('contact_name')->nullable(); // Tên người liên hệ trực tiếp (Contact)
            $table->string('contact_email')->nullable(); // Email liên hệ (Email)
            $table->string('contact_phone')->nullable(); // Số điện thoại liên hệ (Phone)

            // =========================================
            // THANH TOÁN
            // =========================================
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete(); // Phương thức thanh toán (PaymentMethod)
            $table->decimal('payment_value', 15, 2)->default(0); // Giá trị đặt cọc/thanh toán trước (PaymentValue / Deposit)
            $table->string('card_no')->nullable(); // Số thẻ thanh toán (CardNo) — nên mã hóa
            $table->string('card_holder')->nullable(); // Tên chủ thẻ (CardHolder)
            $table->string('card_cvv')->nullable(); // CVV thẻ (CardCVV) — nên mã hóa
            $table->string('card_expired')->nullable(); // Ngày hết hạn thẻ MM/YY (Expired)
            $table->decimal('commission', 8, 2)->default(0); // Hoa hồng % (Commission)
            $table->string('voucher_info')->nullable(); // Thông tin voucher (VoucherInfo)
            $table->string('external_booking_code')->nullable(); // Mã booking từ hệ thống ngoài/OTA (BookingCode)
            $table->string('event_code')->nullable(); // Mã sự kiện (EventCode)

            // =========================================
            // GHI CHÚ / YÊU CẦU ĐẶC BIỆT
            // =========================================
            $table->text('note')->nullable(); // Ghi chú nội bộ (Note)
            $table->text('special_requests')->nullable(); // Yêu cầu đặc biệt của booking (Pack2)

            // =========================================
            // NGƯỜI TẠO / CẬP NHẬT
            // =========================================
            $table->string('sales_person')->nullable(); // Người bán (SalesPerson) — có thể khác người tạo booking
            $table->string('created_by'); // Username người tạo booking (Username)
            $table->string('updated_by')->nullable(); // Username người cập nhật cuối (UserUpdate)
            $table->string('module')->nullable(); // Module tạo booking (FO, RS, OTA...) — dùng cho phân quyền xóa sau này
            $table->unsignedSmallInteger('edit_count')->default(0); // Số lần chỉnh sửa (EditCount)
            $table->text('edit_message')->nullable(); // Ghi chú lần chỉnh sửa cuối (EditMessage)
            $table->dateTime('edit_date')->nullable(); // Thời điểm chỉnh sửa cuối (EditDate)

            $table->json('shuttle_info')->nullable(); // Thông tin đưa đón (dạng JSON array)
            $table->json('deposit_details')->nullable(); // Lưu chi tiết đặt cọc dạng JSON array

            $table->timestamps(); // created_at, updated_at
            $table->softDeletes(); // deleted_at — xóa mềm để lưu lịch sử
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
