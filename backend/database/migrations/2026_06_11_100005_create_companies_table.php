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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // Mã công ty tự sinh (CTY0001, CTY0002...)
            $table->string('name'); // Tên công ty
            $table->string('trading_name')->nullable(); // Tên giao dịch
            $table->text('address')->nullable(); // Địa chỉ
            $table->string('tax_code')->nullable(); // Mã số thuế
            $table->string('phone')->nullable(); // Số điện thoại
            $table->string('email')->nullable(); // Email
            $table->foreignId('customer_source_id')->nullable()->constrained('customer_sources')->nullOnDelete(); // Nguồn khách
            $table->foreignId('market_id')->nullable()->constrained('markets')->nullOnDelete(); // Thị trường
            $table->boolean('sync_acc')->default(false); // Đồng bộ ACC
            $table->decimal('max_debt', 15, 2)->default(0); // Công nợ tối đa
            $table->string('bank_account')->nullable(); // Tài khoản ngân hàng
            $table->foreignId('booker_id')->nullable()->constrained('bookers')->nullOnDelete(); // Người đặt phòng
            $table->string('rate_code')->nullable(); // Mã giá phòng
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete(); // Chi nhánh
            $table->boolean('is_active')->default(true); // Trạng thái
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
