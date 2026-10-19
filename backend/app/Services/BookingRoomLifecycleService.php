<?php

namespace App\Services;

use App\Models\BookingRoom;
use App\Models\BookingRoomService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Keeps the date-dependent rows of a booking room in step with its stay.
 *
 * Booking-room dates are edited from several endpoints (the room editor,
 * bulk update and the booking header editor).  Keeping this synchronization in
 * one service prevents one entry point from leaving stale projected charges or
 * child-breakfast dates behind another entry point.
 */
class BookingRoomLifecycleService
{
    public function __construct(
        private BookingRoomStayChargeService $stayChargeService,
    ) {}

    /**
     * Synchronize unposted date-dependent rows for an active booking room.
     * Posted rows remain immutable financial history.
     */
    public function synchronize(
        BookingRoom $room,
        bool $replaceRoomRates = false,
        bool $synchronizeChildBreakfast = false,
        bool $synchronizeRoomCharges = false,
    ): void
    {
        if (!in_array((int) $room->status, [BookingRoom::STATUS_BOOKED, BookingRoom::STATUS_CHECKED_IN], true)) {
            return;
        }

        $room = $room->fresh(['children.breakfastDetails']) ?: $room;
        $stayDates = $this->stayDates($room);

        if ($synchronizeRoomCharges) {
            $this->stayChargeService->synchronize(
                $room,
                (float) ($room->rate ?? 0),
                $replaceRoomRates,
            );
        }
        $this->synchronizeExtraBed($room, $stayDates);
        if ($synchronizeChildBreakfast) {
            $this->synchronizeChildBreakfast($room, $stayDates);
        }
    }

    /**
     * @return list<string>
     */
    private function stayDates(BookingRoom $room): array
    {
        if (!$room->arrival_date || !$room->departure_date) {
            return [];
        }

        $arrival = Carbon::parse($room->arrival_date)->startOfDay();
        $departure = Carbon::parse($room->departure_date)->startOfDay();
        // Day-use rooms keep their date-bound rows on the arrival date.
        if ($room->is_day_use && $departure->equalTo($arrival)) {
            $departure = $arrival->copy()->addDay();
        }
        $dates = [];

        for ($date = $arrival->copy(); $date->lt($departure); $date->addDay()) {
            $dates[] = $date->toDateString();
        }

        return $dates;
    }

    /**
     * Resize the projected EB rows without rewriting posted rows or existing
     * manual values on nights that still belong to the stay.
     *
     * @param list<string> $stayDates
     */
    private function synchronizeExtraBed(BookingRoom $room, array $stayDates): void
    {
        // So khớp theo ngày đã chuẩn hóa (không phụ thuộc kiểu cột date/datetime)
        // và ưu tiên dòng chưa xóa, để không sinh dòng EB trùng ngày.
        $rows = BookingRoomService::withTrashed()
            ->where('booking_room_id', $room->id)
            ->where('service_code', BookingRoomService::CODE_EXTRA_BED)
            ->orderBy('id')
            ->get();
        $dateOf = fn (BookingRoomService $service) => Carbon::parse($service->service_date)->toDateString();
        $existing = $rows->groupBy($dateOf)
            ->map(fn ($sameDate) => $sameDate->first(fn (BookingRoomService $service) => !$service->trashed()) ?? $sameDate->first());

        $rows->filter(fn (BookingRoomService $service) => (int) $service->is_posted === 0
                && !$service->trashed()
                && (!$stayDates || !in_array($dateOf($service), $stayDates, true)))
            ->each(fn (BookingRoomService $service) => $service->delete());

        if ((int) ($room->extra_bed_qty ?? 0) <= 0 || !$stayDates) {
            if (!$stayDates) {
                BookingRoomService::withTrashed()
                    ->where('booking_room_id', $room->id)
                    ->where('service_code', BookingRoomService::CODE_EXTRA_BED)
                    ->where('is_posted', 0)
                    ->delete();
            }
            return;
        }

        foreach ($stayDates as $date) {
            $row = $existing->get($date);
            if ($row && (int) $row->is_posted === 1) {
                continue;
            }

            $postedOnPreviousRoom = app(BookingRoomMoveService::class)->hasPostedService(
                $room,
                $date,
                [BookingRoomService::CODE_EXTRA_BED, BookingRoomService::catalogCode(BookingRoomService::CODE_EXTRA_BED)],
            );
            if ($postedOnPreviousRoom) {
                if ($row) {
                    $row->delete();
                }
                continue;
            }

            // Nếu ngày này đã có cấu hình số lượng (kể cả quantity = 0), bảo toàn số lượng đã cấu hình
            $qty = ($row && $row->quantity !== null)
                ? (float) $row->quantity
                : (int) ($room->extra_bed_qty ?? 0);

            $rate = ($row && $row->rate !== null)
                ? (float) $row->rate
                : (float) ($room->extra_bed_rate ?? 0);

            $values = [
                'service_name' => BookingRoomService::catalogName(BookingRoomService::CODE_EXTRA_BED, 'Extra Bed'),
                'quantity' => $qty,
                'rate' => $rate,
                'department' => 'FO',
                'is_room' => $row?->is_room ?? 1,
                'is_posted' => 0,
                'deleted_at' => null,
                'created_by' => Auth::user()?->username ?? 'system',
            ];
            if ($row) {
                $row->fill($values)->save();
                continue;
            }

            BookingRoomService::create([
                'booking_room_id' => $room->id,
                'service_code' => BookingRoomService::CODE_EXTRA_BED,
                'service_date' => $date,
            ] + $values);
        }
    }

    /**
     * Move child-breakfast setup to the new [arrival, departure) period while
     * keeping the entered price and surcharge/free/FIT-GIT flags.
     *
     * @param list<string> $stayDates
     */
    private function synchronizeChildBreakfast(BookingRoom $room, array $stayDates): void
    {
        app(ChildBreakfastService::class)->syncToStay($room, $stayDates);
    }
}
