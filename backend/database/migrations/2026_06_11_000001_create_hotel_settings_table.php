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
            $table->id();
            $table->string('code')->nullable(); // Mã KS
            $table->string('name'); // Tên KS/KNM
            $table->string('address')->nullable(); // Địa chỉ
            $table->string('tax_code')->nullable(); // Thuế
            $table->string('phone')->nullable(); // Số điện thoại
            $table->string('fax')->nullable(); // Số fax
            $table->string('email')->nullable(); // Email
            $table->string('facebook')->nullable(); // Facebook
            $table->string('channel_manager')->nullable(); // Kênh quản lý
            $table->string('currency')->default('VND'); // Tiền tệ
            $table->string('bank_name')->nullable(); // Tên ngân hàng
            $table->string('bank_account_name')->nullable(); // Tên tài khoản
            $table->string('bank_account_number')->nullable(); // Số tài khoản
            $table->decimal('adult_breakfast_price', 15, 2)->default(0); // Giá ăn sáng người lớn
            $table->decimal('child_breakfast_price', 15, 2)->default(0); // Giá ăn sáng trẻ em
            $table->decimal('extra_bed_price', 15, 2)->default(0); // Giá Thêm Giường
            $table->integer('total_rooms')->default(0); // Số phòng
            $table->string('website')->nullable(); // Web Hotel
            $table->string('booking_prefix')->nullable(); // Tiền tố mã đăng ký
            $table->string('logo_url')->nullable(); // Hình ảnh Logo
            $table->string('qr_code_url')->nullable(); // Hình ảnh QR Code
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
