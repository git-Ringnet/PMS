<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Danh sách nhân viên Housekeeping
        Schema::create('hk_staff', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_hidden')->default(false); // ẩn khỏi danh sách phân công
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // 2. Header phân công theo ngày + ca
        Schema::create('hk_assignments', function (Blueprint $table) {
            $table->id();
            $table->date('work_date');
            $table->unsignedBigInteger('shift_id'); // FK -> shifts.id
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['work_date', 'shift_id']); // mỗi ngày/ca chỉ 1 record
            $table->foreign('shift_id')->references('id')->on('shifts')->onDelete('cascade');
        });

        // 3. Nhóm nhân viên trong 1 lần phân công
        Schema::create('hk_assignment_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assignment_id');
            $table->string('color', 30)->default('#0ea5e9'); // màu nhận diện nhóm
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('assignment_id')->references('id')->on('hk_assignments')->onDelete('cascade');
        });

        // 4. Nhân viên trong nhóm
        Schema::create('hk_assignment_group_staff', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('staff_id');
            $table->timestamps();

            $table->unique(['group_id', 'staff_id']);
            $table->foreign('group_id')->references('id')->on('hk_assignment_groups')->onDelete('cascade');
            $table->foreign('staff_id')->references('id')->on('hk_staff')->onDelete('cascade');
        });

        // 5. Phòng được phân công cho nhóm (có snapshot trạng thái tại thời điểm giao việc)
        Schema::create('hk_assignment_group_rooms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('room_id'); // FK -> rooms.id
            // Snapshot tình trạng phòng tại thời điểm giao việc
            // (lưu để in worksheet, KHÔNG tự cập nhật theo real-time)
            $table->string('room_status_snapshot', 50)->nullable(); // vd: occupied_dirty, vacant_clean
            $table->string('booking_status_snapshot', 50)->nullable(); // vd: checkout, stayover
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            // 1 phòng chỉ thuộc 1 nhóm trong 1 ca (enforce qua assignment_id)
            // unique trên (group's assignment_id + room_id) — enforce ở tầng app
            $table->unique(['group_id', 'room_id']);
            $table->foreign('group_id')->references('id')->on('hk_assignment_groups')->onDelete('cascade');
            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hk_assignment_group_rooms');
        Schema::dropIfExists('hk_assignment_group_staff');
        Schema::dropIfExists('hk_assignment_groups');
        Schema::dropIfExists('hk_assignments');
        Schema::dropIfExists('hk_staff');
    }
};
