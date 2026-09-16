<?php

namespace App\Services;

use App\Models\BookingRoom;
use App\Models\BookingRoomService;
use App\Models\RoomForm;
use App\Models\RoomRateCode;
use App\Models\StandardRate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Keeps projected room charges in step with a booking room's stay dates.
 *
 * Room charges are stored one row per night in booking_room_services.  This
 * service deliberately owns only the automatically generated RM rows:
 * posted rows are immutable and every other service code is left untouched.
 */
class BookingRoomStayChargeService
{
    /**
     * Synchronize unposted projected room charges for a room.
     *
     * The caller is expected to run this method inside the transaction that
     * updates the room dates/rate.  It is intentionally transaction agnostic
     * so it can be reused by other booking-room endpoints without creating a
     * nested transaction boundary.
     */
    public function synchronize(
        BookingRoom $room,
        ?float $fallbackRate = null,
        bool $replaceExistingRates = false
    ): void
    {
        $arrival = $this->dateOrNull($room->arrival_date);
        $departure = $this->dateOrNull($room->departure_date);
        $roomServiceCodes = $this->roomServiceCodes();

        if (!$arrival || !$departure) {
            return;
        }

        // A checked-out, cancelled, moved or noshow room is historical. Its
        // existing money remains available for audit, but date editing must
        // never generate new projected nights for it.
        if (!in_array((int) $room->status, [
            BookingRoom::STATUS_BOOKED,
            BookingRoom::STATUS_CHECKED_IN,
        ], true)) {
            return;
        }

        $stayDates = $this->stayDates($arrival, $departure);
        $this->removeStaleUnpostedRoomCharges($room, $roomServiceCodes, $stayDates);

        if ($departure->lessThanOrEqualTo($arrival)) {
            return;
        }

        $systemDate = app(RoomAvailabilityService::class)->getSystemDate()->startOfDay();
        $start = $arrival->greaterThan($systemDate) ? $arrival : $systemDate;
        if ($start->greaterThanOrEqualTo($departure)) {
            return;
        }

        $rateCode = filled($room->rate_code)
            ? RoomRateCode::with(['ratePlans', 'dailyMappings'])->find((string) $room->rate_code)
            : null;
        $standardRate = $this->standardRate($room);
        $room->loadMissing('roomClass');
        $roomForm = $room->RoomKind ? RoomForm::find($room->RoomKind)?->name : null;
        $roomRate = $this->roomRate($room, $fallbackRate, $standardRate);
        $firstRate = null;

        for ($date = $start->copy(); $date->lt($departure); $date->addDay()) {
            $resolvedRate = $rateCode
                ? $this->resolveRateCodePrice(
                    $rateCode,
                    $room->roomClass?->code,
                    $roomForm,
                    $date
                )
                : $roomRate;

            $existingRows = $this->roomChargeRows($room, $roomServiceCodes, $date);
            if ($existingRows->contains(fn (BookingRoomService $service): bool => (int) $service->is_posted === 1)) {
                // A posted RM line is financial history. Never rewrite or
                // replace it when a guest changes the stay dates.
                continue;
            }

            $existing = $existingRows->first();
            // A date-only edit should retain an agreed/manual rate on a
            // surviving unposted night. Recalculate it only when the request
            // explicitly changed rate or rate_code, or when the night is new.
            $rate = $existing && !$replaceExistingRates
                ? (float) $existing->rate
                : $resolvedRate;
            $firstRate ??= $rate;
            $values = [
                'service_name' => BookingRoomService::catalogName(
                    BookingRoomService::CODE_ROOM,
                    'Dịch vụ phòng nghỉ'
                ),
                'quantity'     => 1,
                'rate'         => $rate,
                'department'   => 'FO',
                'is_room'      => 1,
                'is_posted'    => 0,
                'deleted_at'   => null,
                'created_by'   => Auth::user()?->username ?? 'system',
            ];

            if ($existing) {
                $existing->fill($values);
                $existing->save();
                continue;
            }

            BookingRoomService::create([
                'booking_room_id' => $room->id,
                'service_code'    => BookingRoomService::catalogCode(BookingRoomService::CODE_ROOM),
                'service_date'    => $date->toDateString(),
                ...$values,
            ]);
        }

        // Keep the legacy room-level rate useful to readers that do not load
        // daily RM rows. A configured rate code owns this value; a manual
        // rate is already stored by the controller before this method runs.
        if ($replaceExistingRates && $rateCode && $firstRate !== null && (float) $room->rate !== (float) $firstRate) {
            $room->update(['rate' => $firstRate]);
        }
    }

    /**
     * Return every night in [arrival, departure).
     *
     * @return list<string>
     */
    public function stayDateStrings(Carbon|string $arrival, Carbon|string $departure): array
    {
        $arrivalDate = $arrival instanceof Carbon ? $arrival->copy()->startOfDay() : Carbon::parse($arrival)->startOfDay();
        $departureDate = $departure instanceof Carbon ? $departure->copy()->startOfDay() : Carbon::parse($departure)->startOfDay();

        return $this->stayDates($arrivalDate, $departureDate);
    }

    /**
     * @param list<string> $roomServiceCodes
     * @param list<string> $stayDates
     */
    private function removeStaleUnpostedRoomCharges(
        BookingRoom $room,
        array $roomServiceCodes,
        array $stayDates
    ): void {
        $query = BookingRoomService::query()
            ->where('booking_room_id', $room->id)
            ->whereIn('service_code', $roomServiceCodes)
            ->where('is_posted', 0);

        if ($stayDates) {
            $query->whereNotIn('service_date', $stayDates);
        }

        // Soft delete keeps the row recoverable while ensuring it does not
        // appear as a current projected charge. Posted rows are excluded.
        $query->delete();
    }

    /**
     * @param list<string> $roomServiceCodes
     */
    private function roomChargeRows(BookingRoom $room, array $roomServiceCodes, Carbon $date): Collection
    {
        return BookingRoomService::withTrashed()
            ->where('booking_room_id', $room->id)
            ->whereIn('service_code', $roomServiceCodes)
            ->whereDate('service_date', $date->toDateString())
            ->orderByRaw('CASE WHEN deleted_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<string>
     */
    private function roomServiceCodes(): array
    {
        return collect([
            BookingRoomService::CODE_ROOM,
            BookingRoomService::catalogCode(BookingRoomService::CODE_ROOM),
        ])->filter(fn ($code): bool => filled($code))->map(fn ($code): string => (string) $code)->unique()->values()->all();
    }

    private function standardRate(BookingRoom $room): float
    {
        if (!$room->room_class_id) {
            return 0.0;
        }

        return (float) (StandardRate::query()
            ->where('room_class_id', $room->room_class_id)
            ->when($room->RoomKind, fn ($query) => $query->where('room_form_id', $room->RoomKind))
            ->value('room_price') ?? 0);
    }

    private function roomRate(BookingRoom $room, ?float $fallbackRate, float $standardRate): float
    {
        if ($fallbackRate !== null) {
            return max(0.0, $fallbackRate);
        }

        $storedRate = (float) ($room->rate ?? 0);
        return $storedRate > 0 ? $storedRate : $standardRate;
    }

    private function resolveRateCodePrice(
        RoomRateCode $rateCode,
        ?string $roomClassCode,
        ?string $roomForm,
        Carbon $date
    ): float {
        $mapping = $rateCode->IsDaily
            ? $rateCode->dailyMappings->first(
                fn ($item): bool => Carbon::parse($item->Date)->toDateString() === $date->toDateString()
            )
            : null;

        if ($rateCode->IsDaily && !$mapping) {
            return 0.0;
        }

        $plan = $rateCode->IsDaily
            ? $rateCode->ratePlans->firstWhere('Code', $mapping->Code)
            : ($rateCode->ratePlans->firstWhere('Code', 'DEFAULT') ?? $rateCode->ratePlans->first());
        if (!$plan || !$roomClassCode || !$roomForm) {
            return 0.0;
        }

        $period = is_string($plan->Period) ? json_decode($plan->Period, true) : $plan->Period;
        if (!is_array($period)) {
            return 0.0;
        }

        $planCode = (string) ($plan->Code ?: 'DEFAULT');
        foreach ([
            $planCode . '_' . $roomClassCode . '_' . $roomForm,
            $rateCode->Ma . '_' . $roomClassCode . '_' . $roomForm,
        ] as $key) {
            if (array_key_exists($key, $period) && is_numeric($period[$key])) {
                return max(0.0, (float) $period[$key]);
            }
        }

        return 0.0;
    }

    /**
     * @return list<string>
     */
    private function stayDates(Carbon $arrival, Carbon $departure): array
    {
        $dates = [];
        for ($date = $arrival->copy(); $date->lt($departure); $date->addDay()) {
            $dates[] = $date->toDateString();
        }

        return $dates;
    }

    private function dateOrNull(mixed $date): ?Carbon
    {
        if (!$date) {
            return null;
        }

        try {
            return Carbon::parse($date)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
