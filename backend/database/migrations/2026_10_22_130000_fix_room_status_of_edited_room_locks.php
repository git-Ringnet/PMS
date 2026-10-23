<?php

use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\RoomLock;
use App\Models\SystemDateRoll;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Dòng 156, 288: sửa tình trạng phòng bị ghi sai khi sửa lịch khóa (trước đây luôn ghi 'ooo').
 * Chỉ sửa các trường hợp chắc chắn sai, không đụng phòng OOO/OOS chỉnh tay không có lịch khóa.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rooms') || ! Schema::hasTable('room_locks') || ! Schema::hasTable('booking_rooms')) {
            return;
        }

        $roll = SystemDateRoll::latest('id')->first();
        $sysDate = $roll ? Carbon::parse($roll->system_date)->toDateString() : now('Asia/Ho_Chi_Minh')->toDateString();

        $inhouse = BookingRoom::query()->stayOnly()
            ->where('status', BookingRoom::STATUS_CHECKED_IN)
            ->whereNotNull('room_number')
            ->pluck('room_number')
            ->map(fn ($number) => (string) $number)
            ->unique();

        $currentLocks = RoomLock::query()
            ->where('is_active', 1)
            ->where(fn ($query) => $query->whereNull('status')->orWhere('status', '!=', 'Done'))
            ->whereDate('start_date', '<=', $sysDate)
            ->whereDate('end_date', '>=', $sysDate)
            ->get()
            ->keyBy(fn ($lock) => (string) $lock->room_number);

        // Khóa đã tới ngày bắt đầu nhưng còn ở trạng thái New (sửa ngày bắt đầu về ngày hệ thống)
        foreach ($currentLocks as $roomNumber => $lock) {
            if ($inhouse->contains($roomNumber)) {
                continue;
            }
            if ($lock->status === 'New') {
                $lock->update(['status' => 'Active']);
            }
            Room::where('room_number', $roomNumber)
                ->whereNotIn('room_status_code', ['ooo', 'oos'])
                ->update(['room_status_code' => strtoupper((string) $lock->lock_type) === 'OOS' ? 'oos' : 'ooo']);
        }

        $futureLockRooms = RoomLock::query()
            ->where('is_active', 1)
            ->whereDate('start_date', '>', $sysDate)
            ->pluck('room_number')
            ->map(fn ($number) => (string) $number)
            ->unique();

        foreach (Room::whereIn('room_status_code', ['ooo', 'oos', 'occupied_ooo'])->get() as $room) {
            $number = (string) $room->room_number;
            if ($inhouse->contains($number)) {
                // Phòng đang có khách không thể đang khóa
                $room->update(['room_status_code' => 'occupied_dirty']);
            } elseif (! $currentLocks->has($number) && $futureLockRooms->contains($number)) {
                // Chỉ có lịch khóa tương lai, chưa tới ngày khóa
                $room->update(['room_status_code' => 'vacant_ready']);
            }
        }
    }

    public function down(): void
    {
        // Sửa dữ liệu, không hoàn tác
    }
};
