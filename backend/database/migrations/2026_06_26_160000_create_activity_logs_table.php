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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // User snapshot (nullable cho login thất bại)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->default('');
            $table->string('employee_code')->nullable();

            // Action metadata
            $table->string('action', 50); // login, logout, login_failed, create, update, delete, upload, bulk_action
            $table->string('module', 80); // auth, reservation, config, system, reports
            $table->string('component', 120)->nullable(); // RoomMapPage, EmployeeTab, etc.
            $table->text('description')->nullable(); // Mô tả chi tiết bằng tiếng Việt

            // Target entity (polymorphic-style)
            $table->string('target_type', 120)->nullable(); // Model class: Room, User, Company
            $table->string('target_id', 120)->nullable(); // ID record bị tác động (string để hỗ trợ cả ID số và ID chuỗi như 'G1')
            $table->string('target_label')->nullable(); // Nhãn hiển thị: "Phòng 101"

            // Change tracking
            $table->json('old_values')->nullable(); // Giá trị cũ (update)
            $table->json('new_values')->nullable(); // Giá trị mới (create, update)

            // Request context
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_method', 10)->default('GET');
            $table->string('request_url', 500)->nullable();
            $table->smallInteger('response_status')->nullable();
            $table->integer('duration_ms')->nullable();

            // Timestamp (chỉ created_at, logs không sửa)
            $table->timestamp('created_at')->useCurrent();

            // Indexes cho tra cứu nhanh
            $table->index('user_id');
            $table->index('action');
            $table->index('module');
            $table->index('created_at');
            $table->index(['user_id', 'module', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
