<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sheet GHI LOG: cột Mã đăng ký / Mã phòng (mã G của phòng thuê) lưu tường minh lúc ghi log,
 * không suy đoán lại khi hiển thị. log_version = 2 đánh dấu log ghi theo chuẩn mới.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('activity_logs')) {
            return;
        }

        Schema::table('activity_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('activity_logs', 'booking_code')) {
                $table->string('booking_code', 50)->nullable()->after('target_label');
                $table->index('booking_code');
            }
            if (!Schema::hasColumn('activity_logs', 'room_code')) {
                $table->string('room_code', 1000)->nullable()->after('booking_code');
            }
            if (!Schema::hasColumn('activity_logs', 'log_version')) {
                $table->unsignedTinyInteger('log_version')->default(1)->after('room_code');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('activity_logs')) {
            return;
        }

        Schema::table('activity_logs', function (Blueprint $table) {
            if (Schema::hasColumn('activity_logs', 'booking_code')) {
                $table->dropIndex(['booking_code']);
                $table->dropColumn('booking_code');
            }
            foreach (['room_code', 'log_version'] as $column) {
                if (Schema::hasColumn('activity_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
