<?php

namespace App\Services;

use App\Models\Booking;
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
        string|int|null $excludeBookingRoomId = null
    ): int {
        $query = BookingRoom::where('room_class_id', $roomClassId)
            ->whereIn('status', [
                BookingRoom::STATUS_BOOKED,
                BookingRoom::STATUS_CHECKED_IN,
                BookingRoom::STATUS_CHECKED_OUT,
            ])
            // Rule: Ignore same-day stays that are not dayuse
            ->where(function ($q) {
                $q->whereColumn('arrival_date', '!=', 'departure_date')
                  ->orWhere('is_day_use', 1);
            })
            ->whereHas('booking', function ($q) {
                $q->whereNotIn('status', [Booking::STATUS_DELETED, Booking::STATUS_NO_SHOW])
                  ->whereHas('registrationStatus', function ($subQ) {
                      $subQ->where('is_availability', 1);
                  });
            });

        // Comprehensive overlap checking
        $query->where(function ($q) use ($arrivalDate, $departureDate) {
            // Case 1: Standard date overlap
            $q->where(function ($sub) use ($arrivalDate, $departureDate) {
                $sub->where('arrival_date', '<', $departureDate)
                    ->where('departure_date', '>', $arrivalDate);
            });

            // Case 2: Dayuse database room overlaps with query dates
            $q->orWhere(function ($sub) use ($arrivalDate, $departureDate) {
                $sub->whereColumn('arrival_date', '=', 'departure_date')
                    ->where('is_day_use', 1)
                    ->where(function ($sub2) use ($arrivalDate, $departureDate) {
                        if ($arrivalDate === $departureDate) {
                            $sub2->whereBetween('arrival_date', [$arrivalDate . ' 00:00:00', $arrivalDate . ' 23:59:59']);
                        } else {
                            $sub2->where('arrival_date', '>=', $arrivalDate . ' 00:00:00')
                                 ->where('arrival_date', '<', $departureDate . ' 00:00:00');
                        }
                    });
            });

            // Case 3: If query itself is Dayuse ($arrivalDate === $departureDate)
            if ($arrivalDate === $departureDate) {
                $q->orWhere(function ($sub) use ($arrivalDate) {
                    $sub->where('arrival_date', '<=', $arrivalDate . ' 23:59:59')
                        ->where('departure_date', '>', $arrivalDate . ' 00:00:00');
                });
            }
        });

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
            ->where('is_internal', false)
            ->pluck('room_number');

        if ($roomNumbers->isEmpty()) return 0;

        return RoomLock::whereIn('room_number', $roomNumbers)
            ->where('is_active', 1)
            ->where('start_date', '<', $departureDate)
            ->where('end_date', '>', $arrivalDate)
            ->count();
    }

    /**
     * Lấy tổng số phòng thuộc loại phòng (loại trừ phòng nội bộ/phòng ảo).
     *
     * @param int $roomClassId
     * @return int
     */
    public function getTotalRooms(int $roomClassId): int
    {
        return \App\Models\Room::where('room_class_id', $roomClassId)
            ->where('is_internal', false)
            ->count();
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
        string|int|null $excludeBookingRoomId = null
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
        string|int|null $excludeBookingRoomId = null,
        string|int|null $excludeBookingId = null,
        string|null $arrivalTime = null,
        string|null $departureTime = null
    ): bool {
        $query = BookingRoom::where('room_number', $roomNumber)
            ->whereIn('status', [
                BookingRoom::STATUS_BOOKED,
                BookingRoom::STATUS_CHECKED_IN,
                BookingRoom::STATUS_CHECKED_OUT,
            ])
            ->where(function ($q) {
                $q->whereColumn('arrival_date', '!=', 'departure_date')
                  ->orWhere('is_day_use', 1);
            })
            ->whereHas('booking', function ($q) use ($excludeBookingId) {
                $q->whereNotIn('status', [Booking::STATUS_DELETED, Booking::STATUS_NO_SHOW]);
                if ($excludeBookingId) {
                    $q->where('id', '!=', $excludeBookingId);
                }
            });

        if ($excludeBookingRoomId) {
            $query->where('id', '!=', $excludeBookingRoomId);
        }

        $query->where(function ($q) use ($arrivalDate, $departureDate, $arrivalTime, $departureTime) {
            // Case 1: Standard date overlap check
            $q->where(function ($sub) use ($arrivalDate, $departureDate) {
                $sub->where('arrival_date', '<', $departureDate)
                    ->where('departure_date', '>', $arrivalDate);
            });

            // Case 2: Same-day Dayuse hourly overlap check
            $q->orWhere(function ($sub) use ($arrivalDate, $departureDate, $arrivalTime, $departureTime) {
                $sub->whereBetween('arrival_date', [$arrivalDate . ' 00:00:00', $arrivalDate . ' 23:59:59'])
                    ->whereBetween('departure_date', [$departureDate . ' 00:00:00', $departureDate . ' 23:59:59']);

                if ($arrivalTime && $departureTime) {
                    $sub->where('arrival_time', '<', $departureTime)
                        ->where('departure_time', '>', $arrivalTime);
                }
            });
        });

        if ($query->exists()) {
            return true;
        }

        // Check if room has an active room lock (OOO / OOS) overlapping arrivalDate ~ departureDate
        return \App\Models\RoomLock::where('room_number', $roomNumber)
            ->where('is_active', 1)
            ->where('start_date', '<=', $departureDate . ' 23:59:59')
            ->where('end_date', '>=', $arrivalDate . ' 00:00:00')
            ->exists();
    }

    /**
     * Lấy system_date hiện tại.
     */
    public function getSystemDate(): Carbon
    {
        $roll = SystemDateRoll::latest('id')->first();
        if ($roll) {
            return Carbon::parse($roll->system_date)->startOfDay();
        }
        return now()->startOfDay();
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
            ->whereIn('status', [
                BookingRoom::STATUS_BOOKED,
                BookingRoom::STATUS_CHECKED_IN,
                BookingRoom::STATUS_CHECKED_OUT,
            ])
            ->whereHas('booking', function ($q) {
                $q->whereNotIn('status', [Booking::STATUS_DELETED, Booking::STATUS_NO_SHOW])
                  ->whereHas('registrationStatus', function ($subQ) {
                      $subQ->where('is_availability', 1);
                  });
            })
            ->where('arrival_date', '<', $endDate)
            ->where('departure_date', '>', $startDate)
            ->get(['arrival_date', 'departure_date']);

        // Lấy locks
        $locks = RoomLock::whereHas('room', fn($q) => $q->where('room_class_id', $roomClassId)->where('is_internal', false))
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
            $current = $current->addDay();
        }

        return $result;
    }
}
