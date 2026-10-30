<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Khóa phòng đã mở nhưng end_date không được cắt về lúc mở khóa (lỗi so giờ thật
     * với giờ bắt đầu khóa cùng ngày nghiệp vụ) vẫn bị thống kê coi là đang khóa,
     * làm lệch số phòng đang ở / OOO / OOS. Cắt end_date về ngày nghiệp vụ lúc mở khóa.
     */
    public function up(): void
    {
        if (!Schema::hasTable('room_locks') || !Schema::hasTable('system_date_rolls')) {
            return;
        }

        $rolls = DB::table('system_date_rolls')->orderBy('id')->get(['system_date', 'actual_date']);

        DB::table('room_locks')
            ->where('is_active', 2)
            ->whereNotNull('unlocked_at')
            ->orderBy('id')
            ->get(['id', 'start_date', 'end_date', 'unlocked_at'])
            ->each(function ($lock) use ($rolls) {
                $unlockedAt = Carbon::parse($lock->unlocked_at);
                $roll = $rolls->filter(fn ($roll) => Carbon::parse($roll->actual_date)->lte($unlockedAt))->last();
                if (!$roll || !$lock->start_date || !$lock->end_date) {
                    return;
                }

                $businessDate = Carbon::parse($roll->system_date)->toDateString();
                $start = Carbon::parse($lock->start_date);
                $end = Carbon::parse($lock->end_date);
                // Chỉ sửa khóa đã bắt đầu mà ngày kết thúc còn kéo qua ngày mở khóa.
                if ($start->toDateString() > $businessDate || $end->toDateString() <= $businessDate) {
                    return;
                }

                $unlockEnd = Carbon::parse($businessDate.' '.$unlockedAt->format('H:i:s'));
                $newEnd = $start->gt($unlockEnd) ? $start : $unlockEnd;

                DB::table('room_locks')->where('id', $lock->id)->update([
                    'end_date' => $newEnd->format('Y-m-d H:i:s'),
                ]);
            });
    }

    public function down(): void
    {
        // Không khôi phục end_date sai.
    }
};
