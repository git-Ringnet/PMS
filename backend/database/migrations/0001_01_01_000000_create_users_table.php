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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique()->nullable(); // Mã nhân viên
            $table->string('name'); // Tên nhân viên
            $table->string('username')->unique(); // Tên tài khoản đăng nhập
            $table->string('email')->unique(); // Thư điện tử (Email)
            $table->timestamp('email_verified_at')->nullable(); // Thời điểm xác thực email
            $table->string('password'); // Mật khẩu (đã mã hóa bcrypt)
            $table->string('department_code')->nullable(); // Mã bộ phận (e.g. MGMT, HK, FO...)
            $table->string('department')->nullable(); // Tên bộ phận tiếng Việt
            $table->string('job_title_code')->nullable(); // Mã chức vụ (e.g. RL001, RL016...)
            $table->string('job_title')->nullable(); // Tên chức vụ tiếng Việt
            $table->date('birth_date')->nullable(); // Ngày sinh nhật
            $table->date('start_date')->nullable(); // Ngày bắt đầu làm việc
            $table->string('phone')->nullable(); // Số điện thoại liên hệ
            $table->text('address')->nullable(); // Địa chỉ thường trú
            $table->boolean('is_active_user')->default(true); // Trạng thái hoạt động (true = kích hoạt, false = khóa tài khoản)
            $table->string('signature_url')->nullable(); // Đường dẫn hình ảnh chữ ký nhân viên
            $table->rememberToken(); // Token ghi nhớ đăng nhập
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
