<?php

namespace App\Services;

use App\Models\BookingChild;
use App\Models\BookingChildBreakfastDetail;
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
        bool $synchronizeRoomCharges = true,
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
        $query = BookingRoomService::withTrashed()
            ->where('booking_room_id', $room->id)
            ->where('service_code', BookingRoomService::CODE_EXTRA_BED);
        $existing = $query->get()->keyBy(fn (BookingRoomService $service) => Carbon::parse($service->service_date)->toDateString());

        $query->where('is_posted', 0)
            ->when($stayDates, fn ($builder) => $builder->whereNotIn('service_date', $stayDates))
            ->delete();

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

            $rate = ($row && $row->rate !== null && (float) $row->rate > 0)
                ? (float) $row->rate
                : (float) ($room->extra_bed_rate ?? 0);

            BookingRoomService::withTrashed()->updateOrCreate(
                [
                    'booking_room_id' => $room->id,
                    'service_code' => BookingRoomService::CODE_EXTRA_BED,
                    'service_date' => $date,
                ],
                [
                    'service_name' => BookingRoomService::catalogName(BookingRoomService::CODE_EXTRA_BED, 'Extra Bed'),
                    'quantity' => (int) $room->extra_bed_qty,
                    'rate' => $rate,
                    'department' => 'FO',
                    'is_room' => $row?->is_room ?? 1,
                    'is_posted' => 0,
                    'deleted_at' => null,
                    'created_by' => Auth::user()?->username ?? 'system',
                ],
            );
        }
    }

    /**
     * Rebuild child-breakfast details for the new [arrival, departure) period.
     * Values already entered by staff are retained as the template for newly
     * created nights; a missing/zero non-free amount falls back to the current
     * configured child breakfast rate.
     *
     * @param list<string> $stayDates
     */
    private function synchronizeChildBreakfast(BookingRoom $room, array $stayDates): void
    {
        $children = BookingChild::where('booking_room_id', $room->id)->get();
        foreach ($children as $child) {
            $details = $child->breakfastDetails()->get();
            $byDate = $details->keyBy(fn (BookingChildBreakfastDetail $detail) => Carbon::parse($detail->service_date)->toDateString());
            $template = $details->first();

            // Remove the detail rows before deleting legacy projected BD
            // rows. BookingRoomService's delete hook can otherwise rewrite a
            // matching detail's amount to the default rate before it becomes
            // the template for the new stay period.
            $child->breakfastDetails()->delete();
            $this->removeProjectedBreakfastServices($room, $child);

            foreach ($stayDates as $date) {
                $source = $byDate->get($date) ?: $template;
                $isBaby = $child->age_group === 'baby';
                $isFree = $source ? (bool) $source->is_free : $isBaby;
                $isExtra = $source ? (bool) $source->is_extra_charge : (!$isBaby && $this->autoExtraCharge());
                $amount = $source ? (float) $source->amount : $this->defaultChildBreakfastRate();

                if ($isBaby || $isFree) {
                    $amount = 0.0;
                } elseif ($amount <= 0) {
                    $amount = $this->defaultChildBreakfastRate();
                }

                BookingChildBreakfastDetail::create([
                    'booking_child_id' => $child->id,
                    'service_date' => $date,
                    'breakfast' => $source ? (bool) $source->breakfast : true,
                    'is_free' => $isFree,
                    'is_extra_charge' => $isExtra,
                    'is_room' => $source ? (bool) $source->is_room : !$isExtra,
                    'amount' => $amount,
                ]);
            }
        }
    }

    private function removeProjectedBreakfastServices(BookingRoom $room, BookingChild $child): void
    {
        $serviceCode = \App\Models\HotelConfig::where('name', 'Booking_BFChildSetServiceId')->value('value') ?: BookingRoomService::CODE_BF_CHILD;
        $prefix = 'Phụ thu ăn sáng trẻ em: ' . $child->full_name;

        BookingRoomService::where('booking_room_id', $room->id)
            ->where('service_code', $serviceCode)
            ->where('note', $prefix)
            ->where('is_posted', 0)
            ->delete();
    }

    private function autoExtraCharge(): bool
    {
        return (int) (\App\Models\HotelConfig::where('name', 'Booking_AutoExtraChargeBFChild')->value('value') ?? 0) === 1;
    }

    private function defaultChildBreakfastRate(): float
    {
        $configRate = \App\Models\HotelConfig::where('name', 'BreakfastRateChild')->value('value');
        $settingRate = \App\Models\HotelSetting::first()?->breakfast_child_rate;

        return (float) ($configRate ?? $settingRate ?? 0);
    }
}
