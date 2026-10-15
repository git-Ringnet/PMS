<?php

namespace App\Services;

use App\Models\BookingChild;
use App\Models\BookingChildBreakfastDetail;
use App\Models\BookingRoom;
use App\Models\BookingRoomChild;
use App\Models\BookingRoomService;
use App\Models\HotelConfig;
use App\Models\HotelService;
use App\Models\HotelSetting;
use App\Models\ServiceBill;
use App\Models\ServiceBillDetail;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Child-breakfast surcharge rules shared by the booking, service and posting
 * flows.
 *
 * Booking_BFChildSetServiceId decides where the projected surcharge lives:
 * - detail mode: the configured code is the catalogue "Breakfast Child" service,
 *   so booking_child_breakfast_details is the only projected source;
 * - service mode: the configured code is another service, so the projected rows
 *   stay in booking_room_services and take display priority.
 */
class ChildBreakfastService
{
    private ?string $configCode = null;
    private ?string $catalogCode = null;
    private bool $catalogResolved = false;

    public function configCode(): string
    {
        if ($this->configCode === null) {
            $value = trim((string) HotelConfig::where('name', 'Booking_BFChildSetServiceId')->value('value'));
            $this->configCode = ($value === '' || $value === '0') ? BookingRoomService::CODE_BF_CHILD : $value;
        }

        return $this->configCode;
    }

    /**
     * Code of the catalogue service named "Breakfast Child / Ăn Sáng Trẻ Em".
     */
    public function catalogCode(): ?string
    {
        if (!$this->catalogResolved) {
            $service = HotelService::query()
                ->where(function ($query): void {
                    $query->where('name', 'like', '%Breakfast Child%')
                        ->orWhere('name', 'like', '%Ăn Sáng Trẻ Em%')
                        ->orWhere('name', 'like', '%ăn sáng trẻ em%');
                })
                ->orderByRaw('CASE WHEN code = ? THEN 0 ELSE 1 END', [BookingRoomService::CODE_BF_CHILD])
                ->first();
            $this->catalogCode = $service?->code;
            $this->catalogResolved = true;
        }

        return $this->catalogCode;
    }

    /**
     * True when booking_child_breakfast_details owns the projected surcharge.
     * Without a catalogue entry the configured code is treated as the child
     * breakfast service itself (legacy default BD).
     */
    public function isDetailMode(): bool
    {
        $catalogCode = $this->catalogCode();

        return $catalogCode === null || strcasecmp($catalogCode, $this->configCode()) === 0;
    }

    public function isChildBreakfastCode(?string $code): bool
    {
        return $code !== null && strcasecmp(trim($code), $this->configCode()) === 0;
    }

    public function serviceName(): string
    {
        return (string) (HotelService::where('code', $this->configCode())->value('name')
            ?: 'Phụ thu ăn sáng trẻ em');
    }

    /**
     * Children currently staying in the room, including children moved in
     * through booking_room_children.
     */
    public function childrenForRoom(BookingRoom $room): Collection
    {
        $assignedIds = BookingRoomChild::where('booking_room_id', $room->id)
            ->where('status', 1)
            ->pluck('booking_child_id');

        return BookingChild::query()
            ->where(function ($query) use ($room, $assignedIds): void {
                $query->where('booking_room_id', $room->id);
                if ($assignedIds->isNotEmpty()) {
                    $query->orWhereIn('id', $assignedIds);
                }
            })
            ->where(fn ($query) => $query->whereNull('child_status')->orWhere('child_status', '!=', 3))
            ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) ASC')
            ->orderBy('id')
            ->get();
    }

    /**
     * Move the surcharge setup onto the room's current stay period.
     *
     * Prices, free/surcharge flags and FIT/GIT folio flags are preserved; only
     * the service dates follow the new [arrival, departure) period.
     *
     * @param list<string> $stayDates
     */
    public function syncToStay(BookingRoom $room, array $stayDates): void
    {
        foreach (BookingChild::where('booking_room_id', $room->id)->get() as $child) {
            $this->syncChildToStay($child, $stayDates);
        }

        if ($this->isDetailMode()) {
            $this->removeLegacyProjectedRows($room);
        } else {
            $this->syncProjectedServiceRowsToStay($room, $stayDates);
        }
    }

    /**
     * One child: keep detail rows of the stay nights, remove the others and
     * create missing nights from the child's existing setup (price, surcharge,
     * free and FIT/GIT flags). Defaults are used only for a child without any
     * detail row yet.
     *
     * @param list<string> $stayDates
     */
    public function syncChildToStay(BookingChild $child, array $stayDates): void
    {
        $details = $child->breakfastDetails()->orderBy('service_date')->get();
        $byDate = $details->keyBy(fn (BookingChildBreakfastDetail $detail) => Carbon::parse($detail->service_date)->toDateString());
        $template = $details->first(fn (BookingChildBreakfastDetail $detail) => (bool) $detail->is_extra_charge)
            ?? $details->first();
        $isBaby = $child->age_group === 'baby';
        // Nights before the PMS date are history (posted or skipped); keep them.
        $systemDate = app(RoomAvailabilityService::class)->getSystemDate()->toDateString();

        foreach ($details as $detail) {
            $detailDate = Carbon::parse($detail->service_date)->toDateString();
            if ($detailDate >= $systemDate && !in_array($detailDate, $stayDates, true)) {
                $detail->delete();
            }
        }

        foreach ($stayDates as $date) {
            if ($byDate->has($date)) {
                continue;
            }

            if ($template && !$isBaby) {
                $values = [
                    'breakfast' => (bool) $template->breakfast,
                    'is_free' => (bool) $template->is_free,
                    'is_extra_charge' => (bool) $template->is_extra_charge,
                    'is_room' => (bool) $template->is_room,
                    'amount' => (float) $template->amount,
                ];
            } else {
                $values = $this->defaultDetailValues($isBaby);
                if ($template) {
                    $values['breakfast'] = (bool) $template->breakfast;
                    $values['is_room'] = (bool) $template->is_room;
                }
            }

            BookingChildBreakfastDetail::create(['booking_child_id' => $child->id, 'service_date' => $date] + $values);
        }
    }

    /**
     * Nights [arrival, departure) of a room; a day-use room keeps its arrival day.
     *
     * @return list<string>
     */
    public function stayDatesForRoom(BookingRoom $room): array
    {
        if (!$room->arrival_date || !$room->departure_date) {
            return [];
        }

        $arrival = Carbon::parse($room->arrival_date)->startOfDay();
        $departure = Carbon::parse($room->departure_date)->startOfDay();
        if ($arrival->eq($departure)) {
            return $room->is_day_use ? [$arrival->toDateString()] : [];
        }

        $dates = [];
        for ($date = $arrival->copy(); $date->lt($departure); $date->addDay()) {
            $dates[] = $date->toDateString();
        }

        return $dates;
    }

    /**
     * Detail mode: unposted surcharge rows left in booking_room_services by the
     * old implementation are not a projected source any more.
     */
    private function removeLegacyProjectedRows(BookingRoom $room, ?string $date = null): void
    {
        BookingRoomService::where('booking_room_id', $room->id)
            ->where('service_code', $this->configCode())
            ->where('is_posted', 0)
            ->whereNull('service_bill_id')
            ->when($date, fn ($query) => $query->whereDate('service_date', $date))
            ->delete();
    }

    /**
     * Default detail values for a newly added child.
     */
    public function defaultDetailValues(bool $isBaby): array
    {
        $autoExtraCharge = (int) (HotelConfig::where('name', 'Booking_AutoExtraChargeBFChild')->value('value') ?? 0) === 1;
        $setting = HotelSetting::first();
        if ($autoExtraCharge) {
            $amount = (float) ($setting?->breakfast_child_rate ?? 0);
        } else {
            $configRate = HotelConfig::where('name', 'BreakfastRateChild')->value('value');
            $amount = (float) ($configRate ?? $setting?->breakfast_child_rate ?? 0);
        }

        return [
            'breakfast' => true,
            'is_free' => $isBaby,
            'is_extra_charge' => !$isBaby && $autoExtraCharge,
            'is_room' => !(!$isBaby && $autoExtraCharge),
            'amount' => $isBaby ? 0 : $amount,
        ];
    }

    /**
     * Apply a surcharge price entered through the automatic-service screen to
     * the daily detail rows (detail mode only).
     *
     * @return int number of child detail rows updated
     */
    public function applyServiceRate(BookingRoom $room, string $date, float $rate, float $quantity, ?bool $isRoom = null): int
    {
        $children = $this->childrenForRoom($room)->where('age_group', '!=', 'baby')->values();
        $limit = $quantity > 0 ? max(1, (int) round($quantity)) : $children->count();
        $updated = 0;

        foreach ($children as $index => $child) {
            $charge = $quantity > 0 && $index < $limit;
            $values = [
                'breakfast' => true,
                'is_free' => false,
                'is_extra_charge' => $charge,
            ];
            if ($charge) {
                $values['amount'] = max(0, $rate);
            }
            if ($isRoom !== null) {
                $values['is_room'] = $isRoom;
            }

            $detail = BookingChildBreakfastDetail::where('booking_child_id', $child->id)
                ->whereDate('service_date', $date)
                ->first();
            if ($detail) {
                $detail->update($values);
            } else {
                BookingChildBreakfastDetail::create([
                    'booking_child_id' => $child->id,
                    'service_date' => $date,
                    'is_room' => $isRoom ?? true,
                    'amount' => $charge ? max(0, $rate) : 0,
                ] + $values);
            }
            $updated++;
        }

        if ($updated > 0) {
            $this->removeLegacyProjectedRows($room, $date);
        }

        return $updated;
    }

    /**
     * Remove unposted surcharge rows when the room has no child left.
     */
    public function removeProjectedRowsWithoutChildren(BookingRoom $room): void
    {
        if ($this->childrenForRoom($room)->where('age_group', 'child')->isNotEmpty()) {
            return;
        }

        BookingRoomService::where('booking_room_id', $room->id)
            ->where('service_code', $this->configCode())
            ->where('is_posted', 0)
            ->delete();
    }

    /**
     * Post every surcharge detail of the given night that has not been billed.
     * Rows already projected in booking_room_services (service mode) are posted
     * by the regular setup-service flow instead.
     *
     * @return list<int> created bill ids
     */
    public function postForDate(BookingRoom $room, Carbon|string $date, string $user): array
    {
        $date = Carbon::parse($date)->startOfDay();
        $code = $this->configCode();
        $dateStr = $date->toDateString();

        if (!$room->arrival_date || !$room->departure_date) {
            return [];
        }
        $arrival = Carbon::parse($room->arrival_date)->startOfDay();
        $departure = Carbon::parse($room->departure_date)->startOfDay();
        $inStay = $date->gte($arrival) && ($date->lt($departure) || ($room->is_day_use && $date->eq($arrival)));
        if (!$inStay) {
            return [];
        }

        // Service mode: a projected row of this night is posted by the setup
        // service flow and has priority over the breakfast details.
        if (!$this->isDetailMode()) {
            $hasProjectedRows = BookingRoomService::where('booking_room_id', $room->id)
                ->where('service_code', $code)
                ->whereDate('service_date', $dateStr)
                ->exists();
            if ($hasProjectedRows) {
                return [];
            }
        }

        $foService = HotelService::where('code', $code)->first();
        if (!$foService) {
            return [];
        }

        $booking = $room->booking;
        $primaryGuest = $room->guests()->where('is_primary', 1)->first() ?: $room->guests()->first();
        $description = $foService->billDescription($room->room_number ?: $room->id, 'FO');
        $billIds = [];

        $charges = [];
        foreach ($this->childrenForRoom($room) as $child) {
            if ($child->age_group === 'baby') {
                continue;
            }

            $detail = BookingChildBreakfastDetail::where('booking_child_id', $child->id)
                ->whereDate('service_date', $dateStr)
                ->first();
            if ($detail && $detail->breakfast && $detail->is_extra_charge && !$detail->is_free && (float) $detail->amount > 0) {
                $charges[] = [$child, $detail];
            }
        }

        // Detail mode: the detail rows own this night, so an old projected row of
        // the same night must not be posted a second time. Nights without any
        // detail charge keep their old row (posted by the setup-service flow).
        if ($charges && $this->isDetailMode()) {
            $this->removeLegacyProjectedRows($room, $dateStr);
        }

        foreach ($charges as [$child, $detail]) {
            $amount = (float) $detail->amount;

            $alreadyPosted = ServiceBill::where('RentalRoomId1', $room->id)
                ->where('ServiceId', $code)
                ->where('CustomerId1', (string) $child->id)
                ->whereDate('Date', $dateStr)
                ->where('Edit', 0)
                ->exists();
            if ($alreadyPosted) {
                continue;
            }

            $isRoomFolio = (bool) $detail->is_room;
            $bill = ServiceBill::create([
                'Date' => $date->copy()->toDateTimeString(),
                'OpenTime' => now()->format('H:i'),
                'Guest' => $child->full_name ?: ($booking?->booking_name ?: 'Khách lẻ'),
                'DepartmentId' => 'FO',
                'ServiceId' => $foService->code,
                'DescriptionServive' => $description,
                'Quantity' => 1,
                'Amount' => $amount,
                'ServiceCharge' => (float) ($foService->service_charge ?? 0),
                'SpecialTax' => (float) ($foService->special_tax ?? 0),
                'Tax' => (float) ($foService->tax ?? 0),
                'Currency' => 'VND',
                'Exchange' => 1,
                'Edit' => 0,
                'Folio' => '1',
                'RegisterId1' => $booking?->id,
                'RentalRoomId1' => $room->id,
                'CustomerId1' => (string) $child->id,
                'CompanyId1' => $booking?->company_id,
                'RegisterID2' => $booking?->id,
                'RentalRoomId2' => $isRoomFolio ? $room->id : null,
                'CustomerId2' => $isRoomFolio ? $primaryGuest?->guest_id : null,
                'CompanyId2' => $booking?->company_id,
                'Username' => $user,
                'Status' => 1,
                'Outlet' => 'FO',
                'Year' => $date->year,
                'Month' => $date->month,
                'Day' => $date->day,
                'CreatedUser' => $user,
                'CreatedDate' => now(),
                'CreatedHour' => now()->format('H:i'),
            ]);

            $breakdown = TaxBreakdownService::breakdown(
                $amount,
                (float) ($foService->service_charge ?? 0),
                (float) ($foService->special_tax ?? 0),
                (float) ($foService->tax ?? 0),
                1
            );
            ServiceBillDetail::create([
                'BillServiceId' => $bill->Ma,
                'Ma' => 1,
                'DepartmentId' => 'FO',
                'ServiceId' => $foService->code,
                'DescriptionServive' => $description,
                'OriginalRate' => $breakdown['original_rate'],
                'Quantity' => 1,
                'ServiceCharge' => (float) ($foService->service_charge ?? 0),
                'SpecialTax' => (float) ($foService->special_tax ?? 0),
                'Tax' => (float) ($foService->tax ?? 0),
                'ServiceChargeAmount' => $breakdown['service_charge_amount'],
                'SpecialTaxAmount' => $breakdown['special_tax_amount'],
                'TaxAmount' => $breakdown['tax_amount'],
                'Amount' => $amount,
                'Currency' => 'VND',
                'Exchange' => 1,
                'DetailBillOriginalAmount' => $breakdown['net_total'],
                'OriginalAmount' => $breakdown['net_total'],
            ]);

            $billIds[] = $bill->Ma;
        }

        return $billIds;
    }

    /**
     * Service mode: projected surcharge rows follow the new stay dates using
     * the latest unposted row as the price template.
     *
     * @param list<string> $stayDates
     */
    private function syncProjectedServiceRowsToStay(BookingRoom $room, array $stayDates): void
    {
        $rows = BookingRoomService::where('booking_room_id', $room->id)
            ->where('service_code', $this->configCode())
            ->orderBy('service_date')
            ->get();
        if ($rows->isEmpty()) {
            return;
        }

        $byDate = $rows->groupBy(fn (BookingRoomService $row) => Carbon::parse($row->service_date)->toDateString());
        $template = $rows->where('is_posted', 0)->last() ?? $rows->last();

        foreach ($rows as $row) {
            if ((int) $row->is_posted === 0 && !in_array(Carbon::parse($row->service_date)->toDateString(), $stayDates, true)) {
                $row->delete();
            }
        }

        foreach ($stayDates as $date) {
            if ($byDate->has($date)) {
                continue;
            }

            BookingRoomService::create([
                'booking_room_id' => $room->id,
                'guest_id' => $template->guest_id,
                'service_code' => $template->service_code,
                'service_name' => $template->service_name,
                'service_date' => $date,
                'quantity' => $template->quantity,
                'rate' => $template->rate,
                'department' => $template->department ?: 'FO',
                'note' => $template->note,
                'folio' => $template->folio,
                'is_room' => $template->is_room,
                'is_posted' => 0,
                'created_by' => Auth::user()?->username ?? 'system',
            ]);
        }
    }
}
