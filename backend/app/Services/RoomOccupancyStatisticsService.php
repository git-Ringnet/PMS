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
        $operationDate = app(RoomAvailabilityService::class)->getSystemDate()->copy()->startOfDay();
        $isFutureView = $date->gt($operationDate);
        // Checklist Công suất: loại phòng ngưng sử dụng (legacy SP1100.IsDayUse = 0 ~ is_active = 0)
        // không thuộc tổng phòng / phòng có thể bán, giống các ô phòng trên Room map.
        $physicalRooms = Room::physical()
            ->whereHas('roomClass', fn ($query) => $query->where('is_active', true))
            ->get(['room_number', 'room_status_code']);
        $physicalRoomNumbers = $physicalRooms->pluck('room_number')->values();

        // room_status_code chỉ phản ánh hiện tại; khi xem ngày khác phải dựa vào lịch sử khóa phòng.
        $isSystemDate = $date->isSameDay($operationDate);
        // Dòng 288: phòng đang có khách check-in thì không thể là phòng khóa, dù room_status_code còn lưu OOO/OOS
        // (dữ liệu cũ bị ghi sai khi sửa lịch khóa) -> không loại khách của phòng đó khỏi số đang ở / dự kiến cuối ngày.
        $inhouseRoomNumbers = $isSystemDate
            ? BookingRoom::query()->stayOnly()->where('status', BookingRoom::STATUS_CHECKED_IN)
                ->whereIn('room_number', $physicalRoomNumbers)->pluck('room_number')->unique()
            : collect();
        $currentOooRooms = $isSystemDate
            ? $physicalRooms->whereIn('room_status_code', ['ooo', 'occupied_ooo'])->pluck('room_number')->diff($inhouseRoomNumbers)
            : collect();
        $currentOosRooms = $isSystemDate
            ? $physicalRooms->where('room_status_code', 'oos')->pluck('room_number')->diff($inhouseRoomNumbers)
            : collect();

        // Lịch khóa cũng vậy: khóa đã mở nhưng end_date còn kéo dài (dữ liệu mở khóa cũ
        // không cắt ngày kết thúc) không được loại phòng đang có khách.
        $oooRoomNumbers = $this->lockedRoomNumbers($physicalRoomNumbers, $date, 'OOO')
            ->merge($currentOooRooms)
            ->diff($inhouseRoomNumbers)
            ->unique()
            ->values();
        $oosRoomNumbers = $this->lockedRoomNumbers($physicalRoomNumbers, $date, 'OOS')
            ->merge($currentOosRooms)
            ->diff($inhouseRoomNumbers)
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
            ->stayOnly()
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

        $projectedQuery = fn () => $forecastQuery()
                ->whereDate('arrival_date', '<=', $dateString)
                ->where(function ($query) use ($dateString) {
                    $query->where(function ($active) use ($dateString) {
                        $active->whereIn('status', [BookingRoom::STATUS_BOOKED, BookingRoom::STATUS_CHECKED_IN])
                            ->whereDate('departure_date', '>', $dateString);
                    })->orWhere(function ($activeDayUse) use ($dateString) {
                        // Day-use đang check-in vẫn chiếm một OCC trong ngày.
                        // Khi checkout, status đổi sang 2 nên tự bị loại khỏi cả hai vế Đang ở.
                        $activeDayUse->where('status', BookingRoom::STATUS_CHECKED_IN)
                            ->where('is_day_use', true)
                            ->whereDate('arrival_date', $dateString)
                            ->whereDate('departure_date', $dateString);
                    })->orWhere(function ($historical) use ($dateString) {
                        $historical->whereIn('status', [BookingRoom::STATUS_CHECKED_OUT, BookingRoom::STATUS_MOVED])
                            ->whereDate('CheckoutDate', '>', $dateString);
                    });
                });
        $projected = $this->summarize($projectedQuery(), true);
        $forecastBreakdown = $this->forecastBreakdown($projectedQuery(), $occupancyBaseRooms);

        // Xem tương lai (note 30/10): chưa có check-in thực tế ở ngày đó, nên "đang ở" là phòng dự kiến
        // ở trong ngày xem (đặt trước hoặc đang ở hiện tại còn ở qua ngày xem), không lấy phòng đang ở hôm nay.
        $occupiedCurrent = $isFutureView
            ? $this->summarize(
                $assignedPhysicalQuery()
                    ->whereIn('status', [BookingRoom::STATUS_BOOKED, BookingRoom::STATUS_CHECKED_IN])
                    ->whereDate('arrival_date', '<=', $dateString)
                    ->whereDate('departure_date', '>', $dateString)
            )
            : $this->summarize(
                $assignedPhysicalQuery()->where('status', BookingRoom::STATUS_CHECKED_IN)
            );

        // Phòng đến gồm cả reservation chưa gán; phòng đến đã gán chỉ gồm số phòng vật lý.
        $expectedArrivals = $this->summarize(
            $forecastQuery()
                ->where('status', BookingRoom::STATUS_BOOKED)
                ->whereDate('arrival_date', $dateString),
            true,
            true
        );
        $assignedArrivals = $this->summarize(
            $assignedPhysicalQuery()
                ->where('status', BookingRoom::STATUS_BOOKED)
                ->whereDate('arrival_date', $dateString),
            false,
            true
        );
        $arrivalsCheckedIn = $this->summarize(
            $assignedPhysicalQuery()
                ->whereIn('status', [BookingRoom::STATUS_CHECKED_IN, BookingRoom::STATUS_CHECKED_OUT])
                ->whereDate('actual_arrival_date', $dateString),
            false,
            true
        );

        $pendingDepartures = $this->summarize(
            $assignedPhysicalQuery()
                ->where(function ($query) use ($dateString, $isFutureView) {
                    $query->where(function ($checkedIn) use ($dateString, $isFutureView) {
                        // Ngày hệ thống: phòng đang ở đến/quá hạn ngày đi là phòng chưa trả.
                        // Xem tương lai: chỉ phòng đang ở có ngày đi đúng ngày xem (phòng đi ngày trước đã tính ở ngày đó).
                        $checkedIn->where('status', BookingRoom::STATUS_CHECKED_IN)
                            ->whereDate('departure_date', $isFutureView ? '=' : '<=', $dateString);
                    })->orWhere(function ($sameDayReservation) use ($dateString) {
                        // Dòng 392: phòng chưa check-in có ngày đến = ngày đi là phòng đi trong ngày
                        // (không phải phòng đang ở), dù có tick Day Use hay không.
                        $sameDayReservation->where('status', BookingRoom::STATUS_BOOKED)
                            ->whereDate('arrival_date', $dateString)
                            ->whereDate('departure_date', $dateString);
                    });
                    if ($isFutureView) {
                        // Chưa có check-in ở tương lai; ngày đi đã lên lịch vẫn là phòng dự kiến đi.
                        $query->orWhere(function ($scheduledDeparture) use ($dateString) {
                            $scheduledDeparture->where('status', BookingRoom::STATUS_BOOKED)
                                ->whereDate('departure_date', $dateString);
                        });
                    }
                }),
            false,
            true
        );
        $departuresCheckedOut = $this->summarize(
            $assignedPhysicalQuery()
                ->where('status', BookingRoom::STATUS_CHECKED_OUT)
                ->whereDate('CheckoutDate', $dateString),
            false,
            true
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
                ? round(($occupiedProjected / $occupancyBaseRooms) * 100, 2)
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
            'day_rooms_pax' => $dayRooms['pax'],
            'same_day_reservations' => $sameDayReservations['rooms'],
            'same_day_reservations_pax' => $sameDayReservations['pax'],
            'walk_in_rooms' => $walkIns['rooms'],
            'walk_in_pax' => $walkIns['pax'],
        ] + $forecastBreakdown;
    }

    /**
     * Đếm một số phòng đúng một lần. Với reservation chưa gán phòng, mỗi booking_room là một phòng dự kiến.
     * $perStay: đếm theo từng lượt ở (booking_room) như legacy sp_178 (count(*) SP2100) — dùng cho Đến/Đi,
     * vì một phòng có thể trả khách rồi bán tiếp lượt trong ngày (ngày đến = ngày đi) trong cùng một ngày.
     */
    private function summarize($query, bool $includeUnassigned = false, bool $perStay = false): array
    {
        $rows = $query
            ->get(['id', 'room_number', 'adults', 'children_qty', 'babies'])
            ->when(
                $perStay,
                fn ($rows) => $rows->unique('id'),
                fn ($rows) => $includeUnassigned
                    ? $rows->unique(fn ($room) => $room->room_number
                        ? 'room:'.$room->room_number
                        : 'booking-room:'.$room->id)
                    : $rows->unique('room_number')
            );

        return [
            'rooms' => $rows->count(),
            'pax' => (int) $rows->sum(fn ($room) =>
                (int) $room->adults + (int) $room->children_qty + (int) $room->babies
            ),
        ];
    }

    /**
     * Dự báo cuối ngày tách theo mã giá, tính trên cùng tập phòng dự kiến cuối ngày với "Phòng ở"
     * (trước đây giao diện tự tính từ ô phòng nên lệch với tổng).
     * - COMP: giá 0đ và gán mã giá FOC.  - HU: mã giá HU / House Use.  - Nội bộ: mã giá nội bộ / internal.
     * - Khách đoàn: phòng dùng mã giá có cờ Khách đoàn (room_rate_codes.IsGIT) như legacy vw_009/SP1323;
     *   còn lại (kể cả không có mã giá) là khách lẻ. Không dùng bookings.is_git (= tiền phòng gửi Master, dòng 19).
     */
    private function forecastBreakdown($query, int $occupancyBaseRooms): array
    {
        $rows = $query
            ->get(['id', 'room_number', 'adults', 'children_qty', 'babies', 'rate', 'rate_code'])
            ->unique(fn ($room) => $room->room_number ? 'room:'.$room->room_number : 'booking-room:'.$room->id)
            ->values();
        $code = fn ($room) => strtoupper(trim((string) $room->rate_code));
        $isComp = fn ($room) => (float) $room->rate == 0.0 && str_contains($code($room), 'FOC');
        $isHu = fn ($room) => (bool) preg_match('/(^|[^A-Z])HU([^A-Z]|$)|HOUSE\s*USE/', $code($room));
        $isInternal = fn ($room) => (bool) preg_match('/NOI\s*BO|INTERNAL/', $code($room));
        $pax = fn ($list) => (int) $list->sum(fn ($room) => (int) $room->adults + (int) $room->children_qty + (int) $room->babies);
        $rate = fn (int $rooms) => $occupancyBaseRooms > 0 ? round($rooms / $occupancyBaseRooms * 100, 2) : 0;

        $comp = $rows->filter($isComp);
        $hu = $rows->filter(fn ($room) => !$isComp($room) && $isHu($room));
        $internal = $rows->filter(fn ($room) => !$isComp($room) && !$isHu($room) && $isInternal($room));
        $chargeable = $rows->reject(fn ($room) => $isComp($room) || $isHu($room) || $isInternal($room));
        $revenue = (float) $chargeable->sum(fn ($room) => (float) $room->rate);
        $gitCodes = \Illuminate\Support\Facades\Schema::hasColumn('room_rate_codes', 'IsGIT')
            ? \App\Models\RoomRateCode::where('IsGIT', true)->pluck('Ma')->map(fn ($ma) => strtoupper(trim((string) $ma)))->all()
            : [];
        $group = $rows->filter(fn ($room) => in_array($code($room), $gitCodes, true));
        $retail = $rows->reject(fn ($room) => in_array($code($room), $gitCodes, true));

        return [
            'forecast_group_rooms' => $group->count(),
            'forecast_group_pax' => $pax($group),
            'forecast_retail_rooms' => $retail->count(),
            'forecast_retail_pax' => $pax($retail),
            'forecast_chargeable_rooms' => $chargeable->count(),
            'forecast_chargeable_pax' => $pax($chargeable),
            'forecast_chargeable_rate' => $rate($chargeable->count()),
            'forecast_comp_rooms' => $comp->count(),
            'forecast_comp_pax' => $pax($comp),
            'forecast_without_hu_rooms' => $chargeable->count() + $comp->count(),
            'forecast_without_hu_pax' => $pax($chargeable) + $pax($comp),
            'forecast_without_hu_rate' => $rate($chargeable->count() + $comp->count()),
            'forecast_hu_rooms' => $hu->count(),
            'forecast_hu_pax' => $pax($hu),
            'forecast_internal_rooms' => $internal->count(),
            'forecast_internal_pax' => $pax($internal),
            'forecast_revenue' => round($revenue),
            'forecast_average_rate' => $chargeable->count() > 0 ? round($revenue / $chargeable->count()) : 0,
        ];
    }

    private function lockedRoomNumbers($physicalRoomNumbers, Carbon $date, string $lockType)
    {
        $dateStr = $date->toDateString();
        $defineLockTime = \App\Models\HotelConfig::where('name', 'FrmOOO_DefineLockByTime')->value('value') ?? '12:00';

        $locks = RoomLock::query()
            ->whereIn('room_number', $physicalRoomNumbers)
            ->where('lock_type', $lockType)
            ->where('start_date', '<=', $date->copy()->endOfDay())
            ->where('end_date', '>=', $date->copy()->startOfDay())
            ->whereIn('is_active', [RoomLock::STATUS_ACTIVE, RoomLock::STATUS_UNLOCKED])
            ->get(['room_number', 'start_date', 'end_date', 'is_active']);

        return $locks->filter(function ($lock) use ($dateStr, $defineLockTime) {
            $lockStart = Carbon::parse($lock->start_date)->toDateString();
            $lockEnd = Carbon::parse($lock->end_date)->toDateString();

            // Nếu phòng đã mở khóa (is_active = 2) và ngày xét là ngày kết thúc của khóa phòng
            if ($lock->is_active == RoomLock::STATUS_UNLOCKED && $dateStr === $lockEnd && $lockStart !== $lockEnd) {
                $endTime = Carbon::parse($lock->end_date)->format('H:i');
                if ($endTime < $defineLockTime) {
                    return false;
                }
            }
            return true;
        })->pluck('room_number')->unique();
    }
}
