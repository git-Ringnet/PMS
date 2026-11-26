<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mã giá phòng: cờ Khách đoàn (GIT) như legacy SP1323.IsGIT.
 * Popup Thống kê (Room map) tách Khách đoàn / Khách lẻ theo mã giá của phòng.
 * Không liên quan bookings.is_git (= tiền phòng gửi Master, dòng 19).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('room_rate_codes', 'IsGIT')) {
            Schema::table('room_rate_codes', function (Blueprint $table) {
                $table->boolean('IsGIT')->default(false)->after('IncludeBF');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('room_rate_codes', 'IsGIT')) {
            Schema::table('room_rate_codes', function (Blueprint $table) {
                $table->dropColumn('IsGIT');
            });
        }
    }
};
