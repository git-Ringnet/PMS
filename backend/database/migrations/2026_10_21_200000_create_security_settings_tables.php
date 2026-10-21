<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cấu hình bảo mật nâng cao dùng chung toàn hệ thống (1 dòng duy nhất).
        Schema::create('security_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('auto_logout_enabled')->default(false);
            $table->unsignedSmallInteger('auto_logout_hours')->default(5);
            $table->boolean('ip_restriction_enabled')->default(false);
            $table->boolean('time_restriction_enabled')->default(false);
            $table->timestamps();
        });

        // Địa chỉ IP (hoặc dải CIDR) được phép truy cập.
        Schema::create('security_allowed_ips', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('ip', 64);
            $table->timestamps();
        });

        // Khoảng thời gian được phép truy cập: ngày trong tuần (1 = Thứ hai ... 7 = Chủ nhật) + giờ.
        Schema::create('security_time_windows', function (Blueprint $table) {
            $table->id();
            $table->string('days', 20);
            $table->string('start_time', 5);
            $table->string('end_time', 5);
            $table->timestamps();
        });

        // Người dùng không bị giới hạn IP / thời gian.
        Schema::create('security_exempt_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_exempt_users');
        Schema::dropIfExists('security_time_windows');
        Schema::dropIfExists('security_allowed_ips');
        Schema::dropIfExists('security_settings');
    }
};
