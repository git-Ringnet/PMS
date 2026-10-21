<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tình trạng xử lý của từng sự kiện trong màn hình Alert (sinh nhật / special request).
        Schema::create('alert_event_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 120)->unique();
            $table->unsignedTinyInteger('status')->default(0); // 0: Chưa xử lý, 1: Đã xử lý
            $table->string('updated_by', 50)->nullable();
            $table->timestamps();
        });

        // Nội dung cập nhật hệ thống (admin nhập, hiển thị ở icon chuông).
        Schema::create('system_updates', function (Blueprint $table) {
            $table->id();
            $table->date('update_date');
            $table->text('content');
            $table->string('created_by', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('system_update_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_update_id')->constrained('system_updates')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->timestamp('read_at')->nullable();
            $table->unique(['system_update_id', 'user_id']);
            $table->index('user_id');
        });

        if (!DB::table('hotel_configs')->where('name', 'AllowShowAlertListView')->exists()) {
            DB::table('hotel_configs')->insert([
                'name' => 'AllowShowAlertListView',
                'value' => '1',
                'description' => 'Tự động hiện popup danh sách sinh nhật / special request khi vào module (1: Có, 0: Không - chỉ xem ở icon lịch góc phải).',
                'is_visible' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_update_reads');
        Schema::dropIfExists('system_updates');
        Schema::dropIfExists('alert_event_statuses');
    }
};
