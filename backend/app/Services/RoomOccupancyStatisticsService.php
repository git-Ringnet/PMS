<?php

namespace App\Services;

use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\RoomLock;
use Carbon\Carbon;

class RoomOccupancyStatisticsService
{
    /**
     * Tính công suất và các chỉ số dịch chuyển phòng theo ngày nghiệp vụ.
     * Chỉ booking có tình trạng đăng ký được tính availability mới tham gia thống kê.
     */
    public function calculate(Carbon $date): array
    {
        $date = $date->copy()->startOfDay();
        $dateString = $date->toDateString();
        $physicalRooms = Room::physical()->get(['room_number', 'room_status_code']);
        $physicalRoomNumbers = $physicalRooms->pluck('room_number')->values();

        // room_status_code chỉ phản ánh hiện tại; khi xem ngày khác phải dựa vào lịch sử khóa phòng.
        $isSystemDate = $date->isSameDay(app(RoomAvailabilityService::class)->getSystemDate());
        $currentOooRooms = $isSystemDate
            ? $physicalRooms->whereIn('room_status_code', ['ooo', 'occupied_ooo'])->pluck('room_number')
            : collect();
        $currentOosRooms = $isSystemDate
            ? $physicalRooms->where('room_status_code', 'oos')->pluck('room_number')
            : collect();

        $oooRoomNumbers = $this->lockedRoomNumbers($physicalRoomNumbers, $date, 'OOO')
            ->merge($currentOooRooms)
            ->unique()
            ->values();
        $oosRoomNumbers = $this->lockedRoomNumbers($physicalRoomNumbers, $date, 'OOS')
            ->merge($currentOosRooms)
            ->unique()
            ->values();
        $unavailableRoomNumbers = $oooRoomNumbers->merge($oosRoomNumbers)->unique()->values();

        $totalRooms = $physicalRoomNumbers->count();
        $ooo = $oooRoomNumbers->count();
        $oos = $oosRoomNumbers->count();

        // Phòng có thể bán loại cả OOO/OOS; riêng mẫu số công suất theo yêu cầu chỉ loại OOO.
        $saleableRooms = max(0, $totalRooms - $ooo - $oos);
        $occupancyBaseRooms = max(0, $totalRooms - $ooo);

        $availabilityQuery = fn () => BookingRoom::query()
            ->whereHas('booking.registrationStatus', fn ($query) => $query->where('is_availability', 1));

        $assignedPhysicalQuery = fn () => $availabilityQuery()
            ->whereIn('room_number', $physicalRoomNumbers)
            ->whereNotIn('room_number', $unavailableRoomNumbers);

        // Reservation chưa gán số phòng vẫn giữ một phòng trong dự báo cuối ngày.
        // Dòng đã gán chỉ được tính nếu là phòng thật và không bị khóa OOO/OOS.
        $forecastQuery = fn () => $availabilityQuery()
            ->where(function ($query) use ($physicalRoomNumbers, $unavailableRoomNumbers) {
                $query->whereNull('room_number')
                    ->orWhere('room_number', '')
                    ->orWhere(function ($assigned) use ($physicalRoomNumbers, $unavailableRoomNumbers) {
                        $assigned->whereIn('room_number', $physicalRoomNumbers)
                            ->whereNotIn('room_number', $unavailableRoomNumbers);
                    });
            });

        $projected = $this->summarize(
            $forecastQuery()
                ->whereDate('arrival_date', '<=', $dateString)
                ->where(function ($query) use ($dateString) {
                    $query->where(function ($active) use ($dateString) {
                        $active->whereIn('status', [BookingRoom::STATUS_BOOKED, BookingRoom::STATUS_CHECKED_IN])
                            ->whereDate('departure_date', '>', $dateString);
                    })->orWhere(function ($historical) use ($dateString) {
                        $historical->whereIn('status', [BookingRoom::STATUS_CHECKED_OUT, BookingRoom::STATUS_MOVED])
                            ->whereDate('CheckoutDate', '>', $dateString);
                    });
                }),
            true
        );

        $occupiedCurrent = $this->summarize(
            $assignedPhysicalQuery()->where('status', BookingRoom::STATUS_CHECKED_IN)
        );

        // Phòng đến gồm cả reservation chưa gán; phòng đến đã gán chỉ gồm số phòng vật lý.
        $expectedArrivals = $this->summarize(
            $forecastQuery()
                ->where('status', BookingRoom::STATUS_BOOKED)
                ->whereDate('arrival_date', $dateString),
            true
        );
        $assignedArrivals = $this->summarize(
            $assignedPhysicalQuery()
                ->where('status', BookingRoom::STATUS_BOOKED)
                ->whereDate('arrival_date', $dateString)
        );
        $arrivalsCheckedIn = $this->summarize(
            $assignedPhysicalQuery()
                ->whereIn('status', [BookingRoom::STATUS_CHECKED_IN, BookingRoom::STATUS_CHECKED_OUT])
                ->whereDate('actual_arrival_date', $dateString)
        );

        $pendingDepartures = $this->summarize(
            $assignedPhysicalQuery()
                ->where('status', BookingRoom::STATUS_CHECKED_IN)
                ->whereDate('departure_date', '<=', $dateString)
        );
        $departuresCheckedOut = $this->summarize(
            $assignedPhysicalQuery()
                ->where('status', BookingRoom::STATUS_CHECKED_OUT)
                ->whereDate('CheckoutDate', $dateString)
        );

        // Link Hotel nhận diện checkout sớm bằng số đêm thực tế nhỏ hơn số đêm đặt ban đầu.
        $earlyDepartures = $this->summarize(
            $assignedPhysicalQuery()
                ->where('status', BookingRoom::STATUS_CHECKED_OUT)
                ->whereDate('CheckoutDate', $dateString)
                ->whereColumn('ActutalNumOfDays', '<', 'NumOfDays')
        );
        $extendedStays = $this->summarize(
            $assignedPhysicalQuery()
                ->where('status', BookingRoom::STATUS_CHECKED_IN)
                ->whereDate('planned_departure_date', '<=', $dateString)
                ->whereColumn('departure_date', '>', 'planned_departure_date')
        );

        $dayRooms = $this->summarize(
            $forecastQuery()
                ->whereIn('status', [
                    BookingRoom::STATUS_BOOKED,
                    BookingRoom::STATUS_CHECKED_IN,
                    BookingRoom::STATUS_CHECKED_OUT,
                ])
                ->where('is_day_use', true)
                ->whereDate('arrival_date', $dateString),
            true
        );
        $sameDayReservations = $this->summarize(
            $forecastQuery()
                ->whereIn('status', [
                    BookingRoom::STATUS_BOOKED,
                    BookingRoom::STATUS_CHECKED_IN,
                    BookingRoom::STATUS_CHECKED_OUT,
                ])
                ->whereDate('arrival_date', $dateString)
                ->whereHas('booking', fn ($query) => $query->whereDate('booking_date', $dateString)),
            true
        );
        $walkIns = $this->summarize(
            $forecastQuery()
                ->whereIn('status', [
                    BookingRoom::STATUS_BOOKED,
                    BookingRoom::STATUS_CHECKED_IN,
                    BookingRoom::STATUS_CHECKED_OUT,
                ])
                ->whereDate('arrival_date', $dateString)
                ->whereHas('booking.customerSource', fn ($query) => $query->where('code', 'WALKIN')),
            true
        );

        $occupiedProjected = min($saleableRooms, $projected['rooms']);

        return [
            'total_rooms' => $totalRooms,
            'ooo' => $ooo,
            'oos' => $oos,
            'saleable_rooms' => $saleableRooms,
            'occupancy_base_rooms' => $occupancyBaseRooms,
            'occupied_current' => $occupiedCurrent['rooms'],
            'occupied_current_pax' => $occupiedCurrent['pax'],
            'occupied_projected' => $occupiedProjected,
            'occupied_projected_pax' => $projected['pax'],
            'vacant_projected' => max(0, $saleableRooms - $occupiedProjected),
            'occupancy_rate' => $occupancyBaseRooms > 0
                ? (int) round(($occupiedProjected / $occupancyBaseRooms) * 100)
                : 0,
            'arrivals_checked_in' => $arrivalsCheckedIn['rooms'],
            'arrivals_checked_in_pax' => $arrivalsCheckedIn['pax'],
            'arrivals_pending' => $expectedArrivals['rooms'],
            'arrivals_pending_pax' => $expectedArrivals['pax'],
            'arrivals_assigned' => $arrivalsCheckedIn['rooms'] + $assignedArrivals['rooms'],
            'arrivals_assigned_pax' => $arrivalsCheckedIn['pax'] + $assignedArrivals['pax'],
            'arrivals_total' => $arrivalsCheckedIn['rooms'] + $expectedArrivals['rooms'],
            'arrivals_total_pax' => $arrivalsCheckedIn['pax'] + $expectedArrivals['pax'],
            'departures_checked_out' => $departuresCheckedOut['rooms'],
            'departures_checked_out_pax' => $departuresCheckedOut['pax'],
            'departures_pending' => $pendingDepartures['rooms'],
            'departures_pending_pax' => $pendingDepartures['pax'],
            'departures_total' => $departuresCheckedOut['rooms'] + $pendingDepartures['rooms'],
            'departures_total_pax' => $departuresCheckedOut['pax'] + $pendingDepartures['pax'],
            'early_departures' => $earlyDepartures['rooms'],
            'extended_stays' => $extendedStays['rooms'],
            'day_rooms' => $dayRooms['rooms'],
            'same_day_reservations' => $sameDayReservations['rooms'],
            'same_day_reservations_pax' => $sameDayReservations['pax'],
            'walk_in_rooms' => $walkIns['rooms'],
            'walk_in_pax' => $walkIns['pax'],
        ];
    }

    /**
     * Đếm một số phòng đúng một lần. Với reservation chưa gán phòng, mỗi booking_room là một phòng dự kiến.
     */
    private function summarize($query, bool $includeUnassigned = false): array
    {
        $rows = $query
            ->get(['id', 'room_number', 'adults', 'children_qty', 'babies'])
            ->when(
                $includeUnassigned,
                fn ($rows) => $rows->unique(fn ($room) => $room->room_number
                    ? 'room:'.$room->room_number
                    : 'booking-room:'.$room->id),
                fn ($rows) => $rows->unique('room_number')
            );

        return [
            'rooms' => $rows->count(),
            'pax' => (int) $rows->sum(fn ($room) =>
                (int) $room->adults + (int) $room->children_qty + (int) $room->babies
            ),
        ];
    }

    private function lockedRoomNumbers($physicalRoomNumbers, Carbon $date, string $lockType)
    {
        return RoomLock::query()
            ->whereIn('room_number', $physicalRoomNumbers)
            ->where('lock_type', $lockType)
            ->where('start_date', '<=', $date->copy()->endOfDay())
            ->where('end_date', '>=', $date->copy()->startOfDay())
            ->where(function ($query) use ($date) {
                $query->where('is_active', RoomLock::STATUS_ACTIVE)
                    ->orWhere('unlocked_at', '>', $date->copy()->endOfDay());
            })
            ->distinct()
            ->pluck('room_number');
    }
}
