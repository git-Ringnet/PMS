<?php

namespace App\Services;

use App\Models\BookingRoom;
use App\Models\RoomLock;
use App\Models\SystemDateRoll;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * RoomAvailabilityService
 * Dịch vụ dùng chung kiểm tra số phòng trống (AV) theo loại phòng và khoảng ngày.
 * Được dùng ở: Epic 1 (tạo booking), Epic 2 (bulk update), Epic 6 (nâng hạng)
 */
class RoomAvailabilityService
{
    /**
     * Lấy số phòng đang được đặt (booked / inhouse) của một loại phòng
     * trong khoảng [arrival_date, departure_date), không tính booking đã hủy/checkout.
     *
     * @param int        $roomClassId  ID loại phòng cần kiểm tra
     * @param string     $arrivalDate  Ngày đến (Y-m-d)
     * @param string     $departureDate Ngày đi (Y-m-d)
     * @param int|null   $excludeBookingRoomId  ID booking_room cần loại trừ (khi update)
     * @return int
     */
    public function getBookedCount(
        int $roomClassId,
        string $arrivalDate,
        string $departureDate,
        ?int $excludeBookingRoomId = null
    ): int {
        $query = BookingRoom::where('room_class_id', $roomClassId)
            ->whereIn('status', [
                BookingRoom::STATUS_BOOKED,
                BookingRoom::STATUS_CHECKED_IN,
            ])
            // Overlap condition: arrival < departure_other AND departure > arrival_other
            ->where('arrival_date', '<', $departureDate)
            ->where('departure_date', '>', $arrivalDate);

        if ($excludeBookingRoomId) {
            $query->where('id', '!=', $excludeBookingRoomId);
        }

        return $query->count();
    }

    /**
     * Lấy số phòng OOO/OOS của loại phòng trong khoảng ngày.
     *
     * @param int    $roomClassId
     * @param string $arrivalDate
     * @param string $departureDate
     * @return int
     */
    public function getLockedCount(int $roomClassId, string $arrivalDate, string $departureDate): int
    {
        $roomNumbers = \App\Models\Room::where('room_class_id', $roomClassId)
            ->pluck('room_number');

        if ($roomNumbers->isEmpty()) return 0;

        return RoomLock::whereIn('room_number', $roomNumbers)
            ->where('is_active', 1)
            ->where('start_date', '<', $departureDate)
            ->where('end_date', '>', $arrivalDate)
            ->count();
    }

    /**
     * Lấy tổng số phòng thuộc loại phòng.
     *
     * @param int $roomClassId
     * @return int
     */
    public function getTotalRooms(int $roomClassId): int
    {
        return \App\Models\Room::where('room_class_id', $roomClassId)->count();
    }

    /**
     * Tính số phòng trống (AV) của một loại phòng trong khoảng ngày.
     * AV = Total - OOO/OOS count - Booked count
     *
     * @param int      $roomClassId
     * @param string   $arrivalDate
     * @param string   $departureDate
     * @param int|null $excludeBookingRoomId
     * @return int  Có thể âm nếu AllowOverRoomTypeRoomKind = 1
     */
    public function getAvailability(
        int $roomClassId,
        string $arrivalDate,
        string $departureDate,
        ?int $excludeBookingRoomId = null
    ): int {
        $total  = $this->getTotalRooms($roomClassId);
        $locked = $this->getLockedCount($roomClassId, $arrivalDate, $departureDate);
        $booked = $this->getBookedCount($roomClassId, $arrivalDate, $departureDate, $excludeBookingRoomId);

        return $total - $locked - $booked;
    }

    /**
     * Kiểm tra số phòng vật lý có bị trùng giai đoạn ở với booking khác không.
     * Dùng cho Epic 3 (Auto Room Assignment) và validate khi gán số phòng.
     *
     * @param string   $roomNumber
     * @param string   $arrivalDate
     * @param string   $departureDate
     * @param int|null $excludeBookingRoomId
     * @return bool  true = phòng đang bận (trùng), false = phòng trống
     */
    public function isRoomNumberOccupied(
        string $roomNumber,
        string $arrivalDate,
        string $departureDate,
        ?int $excludeBookingRoomId = null
    ): bool {
        $query = BookingRoom::where('room_number', $roomNumber)
            ->whereIn('status', [
                BookingRoom::STATUS_BOOKED,
                BookingRoom::STATUS_CHECKED_IN,
            ])
            ->where('arrival_date', '<', $departureDate)
            ->where('departure_date', '>', $arrivalDate);

        if ($excludeBookingRoomId) {
            $query->where('id', '!=', $excludeBookingRoomId);
        }

        return $query->exists();
    }

    /**
     * Lấy system_date hiện tại.
     */
    public function getSystemDate(): Carbon
    {
        $roll = SystemDateRoll::latest('id')->first();
        return $roll
            ? Carbon::parse($roll->system_date)->startOfDay()
            : now()->startOfDay();
    }

    /**
     * Lấy AV theo từng ngày trong khoảng (dùng cho grid availability).
     * Trả về mảng ['Y-m-d' => av_count]
     *
     * @param int    $roomClassId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getAvailabilityGrid(int $roomClassId, string $startDate, string $endDate): array
    {
        $total  = $this->getTotalRooms($roomClassId);
        $result = [];

        // Lấy tất cả booking_rooms overlap với khoảng ngày
        $bookings = BookingRoom::where('room_class_id', $roomClassId)
            ->whereIn('status', [BookingRoom::STATUS_BOOKED, BookingRoom::STATUS_CHECKED_IN])
            ->where('arrival_date', '<', $endDate)
            ->where('departure_date', '>', $startDate)
            ->get(['arrival_date', 'departure_date']);

        // Lấy locks
        $locks = RoomLock::whereHas('room', fn($q) => $q->where('room_class_id', $roomClassId))
            ->where('is_active', true)
            ->where('start_date', '<', $endDate)
            ->where('end_date', '>', $startDate)
            ->get(['start_date', 'end_date']);

        $current = Carbon::parse($startDate);
        $end     = Carbon::parse($endDate);

        while ($current->lt($end)) {
            $dateStr = $current->toDateString();
            $nextDay = $current->copy()->addDay()->toDateString();

            // Count booked rooms occupying this date
            $booked = $bookings->filter(fn($br) =>
                $br->arrival_date->toDateString() < $nextDay &&
                $br->departure_date->toDateString() > $dateStr
            )->count();

            // Count locked rooms occupying this date
            $locked = $locks->filter(fn($lk) =>
                Carbon::parse($lk->start_date)->toDateString() <= $dateStr &&
                Carbon::parse($lk->end_date)->toDateString() >= $dateStr
            )->count();

            $result[$dateStr] = max(0, $total - $locked - $booked);
            $current->addDay();
        }

        return $result;
    }
}
