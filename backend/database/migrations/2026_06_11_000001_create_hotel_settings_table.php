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
        Schema::create('hotel_settings', function (Blueprint $table) {
            // 33 Cột Legacy theo đúng thứ tự
            $table->id(); // 1: Ma
            $table->string('first_name')->nullable(); // 2: FirstName - Tên người đại diện
            $table->string('hotel_name'); // 3: HotelName - Tên khách sạn
            $table->string('hotel_name1')->nullable(); // 4: HotelName1 - Tên khách sạn phụ
            $table->text('address')->nullable(); // 5: Address - Địa chỉ
            $table->text('address1')->nullable(); // 6: Address1 - Địa chỉ dòng 2
            $table->string('phone')->nullable(); // 7: Phone - Số điện thoại
            $table->string('fax')->nullable(); // 8: Fax - Số fax
            $table->string('email')->nullable(); // 9: Email
            $table->string('website')->nullable(); // 10: Website
            $table->string('account')->nullable(); // 11: Account - Số tài khoản ngân hàng
            $table->string('bank_code')->nullable(); // 12: BankCode - Mã ngân hàng
            $table->string('bank')->nullable(); // 13: Bank - Tên ngân hàng
            $table->string('tax_code')->nullable(); // 14: TaxCode - Mã số thuế
            $table->string('account_name')->nullable(); // 15: AccountName - Tên chủ tài khoản
            $table->string('serial')->nullable(); // 16: Serial - Ký hiệu hóa đơn thường
            $table->string('invoice_number')->nullable(); // 17: InvoiceNumber - Số hóa đơn thường
            $table->integer('invoice_number_length')->nullable(); // 18: InvoiceNumberLength - Độ dài số hóa đơn thường
            $table->string('form_no')->nullable(); // 19: FormNo - Mẫu số hóa đơn thường
            $table->string('logo')->nullable(); // 20: Logo - Logo cũ
            $table->decimal('breakfast_adult_rate', 15, 2)->default(0); // 21: BreakfastAdultRate - Giá ăn sáng người lớn
            $table->decimal('breakfast_child_rate', 15, 2)->default(0); // 22: BreakfastChildRate - Giá ăn sáng trẻ em
            $table->decimal('extra_bed_rate', 15, 2)->default(0); // 23: ExtraBedRate - Giá thêm giường
            $table->integer('room_number')->default(0); // 24: RoomNumber - Tổng số phòng
            $table->string('division')->nullable(); // 25: Division - Bộ phận / Chi nhánh
            $table->string('currency')->default('VND'); // 26: Currency - Tiền tệ
            $table->string('prefix_booking_id')->nullable(); // 27: PrefixBookingId - Tiền tố mã đặt phòng
            $table->string('channel_manager')->nullable(); // 28: ChannelManager - Kênh quản lý
            $table->string('facebook')->nullable(); // 29: Facebook
            $table->string('pos_serial')->nullable(); // 30: POS_Serial - Số serial POS
            $table->string('pos_invoice_number')->nullable(); // 31: POS_InvoiceNumber - Số hóa đơn POS
            $table->integer('pos_invoice_number_length')->nullable(); // 32: POS_InvoiceNumberLength - Độ dài số hóa đơn POS
            $table->string('pos_invoice_form_no')->nullable(); // 33: POS_InvoiceFormNo - Mẫu số hóa đơn POS

            // Các cột mở rộng của hệ thống mới (từ cột 34 trở đi, đều nullable)
            $table->text('invoice_address')->nullable(); // Địa chỉ xuất hóa đơn
            $table->string('hotel_link')->nullable(); // Link đặt phòng
            $table->string('pos_invoice_symbol')->nullable(); // Ký hiệu hóa đơn POS
            $table->string('logo_url')->nullable(); // Hình ảnh Logo (mở rộng)
            $table->string('qr_code_url')->nullable(); // Hình ảnh QR Code (mở rộng)

            // Config parameters
            $table->boolean('allow_over_room_type')->default(false); // Cho phép lấy âm phòng
            $table->text('booking_hidden_bk_info')->nullable(); // Các trường cần ẩn
            $table->string('booking_bf_child_set_service_id')->nullable(); // Mã dịch vụ ăn sáng TE
            $table->boolean('booking_auto_extra_charge_bf_child')->default(false); // Tự động tính phụ phí
            $table->boolean('check_module_before_delete')->default(false); // Kiểm tra module khi xóa
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotel_settings');
    }
};
