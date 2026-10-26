<?php

namespace App\Services;

use App\Models\BookingRoom;
use App\Models\BookingRoomService;
use App\Models\ServiceBill;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Dòng 409: tiền phòng giống màn hình Booking (getRoomDisplayServices +
 * calculateChargeableServiceTotals của CreateRegistrationPage, chuẩn fun_052).
 *
 * - Đêm quá khứ (< ngày hệ thống): chỉ lấy theo service_bills hợp lệ.
 * - Đêm hôm nay/tương lai: bill nếu đã post trước, không thì giá dự kiến trong BK.
 * - Không lấy dịch vụ lễ tân tự post tại hóa đơn (minibar, giặt là...) không có trong setup BK.
 */
class BookingDisplayTotalService
{
    private const ROOM_CHARGE_CODES = ['RM', 'ROOM_CHARGE', 'ER'];
    private const DEFAULT_EXTRA_BED_RATE = 300000;

    private ?string $systemDate = null;

    public function __construct(private readonly ChildBreakfastService $childBreakfast) {}

    public function systemDate(): string
    {
        if ($this->systemDate === null) {
            $value = DB::table('system_date_rolls')->orderByDesc('id')->value('system_date');
            $this->systemDate = $value ? Carbon::parse($value)->toDateString() : now('Asia/Ho_Chi_Minh')->toDateString();
        }

        return $this->systemDate;
    }

    public function useSystemDate(string $date): void
    {
        $this->systemDate = $date;
    }

    public function roomTotal(BookingRoom $room): float
    {
        $services = $room->services ?? collect();
        $bills = $this->roomBills($room);
        $arrival = $this->dateOnly($room->arrival_date ?? $room->booking?->arrival_date);
        $departure = $this->dateOnly($room->departure_date ?? $room->booking?->departure_date);
        $isSameDay = $arrival !== '' && $arrival === $departure;
        $isDayUse = $isSameDay && (bool) $room->is_day_use;
        $nights = $isSameDay ? ($isDayUse ? 1 : 0) : $this->nightCount($arrival, $departure);
        $stayDates = $arrival === '' ? [] : array_map(fn (int $i) => Carbon::parse($arrival)->addDays($i)->toDateString(), $nights > 0 ? range(0, $nights - 1) : []);
        $sysDate = $this->systemDate();
        $handled = [];
        $total = 0.0;

        // 1. Tiền phòng theo từng đêm.
        $dailyRoomPrices = $services->filter(fn ($svc) => in_array($this->code($svc->service_code), ['RM', 'ROOM_CHARGE'], true))
            ->mapWithKeys(fn ($svc) => [$this->dateOnly($svc->service_date) => (float) $svc->rate])->all();
        foreach ($stayDates as $date) {
            $isPast = $date < $sysDate;
            $matching = $bills->filter(fn (ServiceBill $bill) => $this->isRoomChargeBill($bill) && $this->billDate($bill) === $date);
            if ($matching->isNotEmpty()) {
                $pendingPlan = $isPast ? null : $services->first(fn ($svc) => $this->isRoomChargeService($svc)
                    && $this->dateOnly($svc->service_date) === $date && (int) $svc->is_posted !== 1 && !$svc->service_bill_id);
                foreach ($matching as $bill) {
                    $handled[(string) $bill->Ma] = true;
                    $qty = (float) $bill->Quantity ?: 1;
                    $billedRate = $qty > 0 ? (float) $bill->Amount / $qty : (float) $bill->Amount;
                    $rate = $pendingPlan && $matching->count() === 1 ? (float) $pendingPlan->rate : $billedRate;
                    $total += $rate * $qty;
                }
            } elseif (!$isPast) {
                $dbCharge = $services->first(fn ($svc) => $this->isRoomChargeService($svc) && $this->dateOnly($svc->service_date) === $date);
                $rate = $dailyRoomPrices[$date] ?? ($dbCharge ? (float) $dbCharge->rate : (float) $room->rate);
                $total += $rate * ($dbCharge ? ((float) $dbCharge->quantity ?: 1) : 1);
            }
            // Đêm quá khứ không có bill hợp lệ (bill đã xóa): không tự bù tiền phòng.
        }
        foreach ($bills as $bill) {
            if ($this->isRoomChargeBill($bill) && !isset($handled[(string) $bill->Ma])) {
                $handled[(string) $bill->Ma] = true;
                $total += (float) $bill->Amount;
            }
        }

        // 2. Giường phụ (EB).
        $ebServices = $services->filter(fn ($svc) => $this->code($svc->service_code) === 'EB');
        $hasDailyEb = $ebServices->isNotEmpty();
        $roomEbRate = $this->hasValue($room->extra_bed_rate) ? (float) $room->extra_bed_rate : null;
        $hasEb = $hasDailyEb || ((int) $room->extra_bed_qty > 0 && (float) $roomEbRate > 0);
        foreach ($hasEb ? $stayDates : [] as $date) {
            if ($date < $sysDate) {
                $ebBill = $bills->first(fn (ServiceBill $bill) => $this->code($bill->ServiceId) === 'EB' && $this->billDate($bill) === $date
                    && $this->isValidBill($bill) && !isset($handled[(string) $bill->Ma]));
                if ($ebBill) {
                    $handled[(string) $ebBill->Ma] = true;
                    $total += (float) $ebBill->Amount;
                }
                continue;
            }
            if ($hasDailyEb) {
                $ebSvc = $ebServices->filter(fn ($svc) => $this->dateOnly($svc->service_date) === $date)->last();
                $qty = $ebSvc ? (float) $ebSvc->quantity : 0;
                $rate = $ebSvc && $this->hasValue($ebSvc->rate) ? (float) $ebSvc->rate : ($roomEbRate ?? self::DEFAULT_EXTRA_BED_RATE);
                if ($qty > 0) $total += $qty * $rate;
            } else {
                $total += (int) $room->extra_bed_qty * ($roomEbRate ?? self::DEFAULT_EXTRA_BED_RATE);
            }
        }

        // 3. Phụ thu ăn sáng trẻ em theo chi tiết ăn sáng.
        $bfCode = $this->code($this->childBreakfast->configCode());
        $detailMode = $this->childBreakfast->isDetailMode();
        $servicePriorityDates = $detailMode ? [] : $services->filter(fn ($svc) => $this->code($svc->service_code) === $bfCode)
            ->mapWithKeys(fn ($svc) => [$this->dateOnly($svc->service_date) => true])->all();
        $detailChargeDates = [];
        foreach ($room->children ?? [] as $child) {
            foreach ($child->breakfastDetails ?? [] as $detail) {
                $amount = (float) $detail->amount;
                $date = $this->dateOnly($detail->service_date);
                if (!$detail->breakfast || !$detail->is_extra_charge || $detail->is_free || $amount <= 0 || $date === '') continue;
                if (isset($servicePriorityDates[$date])) continue;
                $detailChargeDates[$date] = true;

                $isPast = $date < $sysDate;
                $bfBill = null;
                if ($isPast) {
                    $candidates = $bills->filter(fn (ServiceBill $bill) => $this->isChildBreakfastBill($bill, $bfCode) && $this->billDate($bill) === $date
                        && $this->isValidBill($bill) && !isset($handled[(string) $bill->Ma]));
                    $bfBill = $candidates->first(fn (ServiceBill $bill) => (string) $bill->CustomerId1 === (string) $child->id) ?? $candidates->first();
                } elseif (!$this->isInStay($date, $arrival, $departure, $isDayUse)) {
                    continue;
                }

                if ($bfBill) {
                    $handled[(string) $bfBill->Ma] = true;
                    $total += (float) $bfBill->Amount;
                } elseif (!$isPast) {
                    $total += $amount;
                }
            }
        }

        // 4. Dịch vụ đã setup trong BK (khác RM, EB).
        foreach ($services as $svc) {
            $code = $this->code($svc->service_code);
            if (in_array($code, [...self::ROOM_CHARGE_CODES, 'EB'], true)) continue;
            $date = $this->dateOnly($svc->service_date);
            if (($code === $bfCode || $code === 'BD') && $detailMode && isset($detailChargeDates[$date])) continue;
            if ($date === '') continue;

            if ($date < $sysDate) {
                $bill = $bills->first(fn (ServiceBill $bill) => ($this->code($bill->ServiceId) === $code
                        || ($svc->service_bill_id && (string) $bill->Ma === (string) $svc->service_bill_id))
                    && $this->billDate($bill) === $date && $this->isValidBill($bill) && !isset($handled[(string) $bill->Ma]));
                if ($bill) {
                    $handled[(string) $bill->Ma] = true;
                    $total += (float) $bill->Amount;
                }
                continue;
            }
            $isPosted = (int) $svc->is_posted === 1 || $svc->service_bill_id;
            if (!$isPosted && !$this->isInStay($date, $arrival, $departure, $isDayUse)) continue;
            $total += (float) $svc->rate * ($svc->quantity !== null ? (float) $svc->quantity : 1);
        }

        return $total;
    }

    /** Bill của phòng: phòng gốc (RentalRoomId1) và phòng hiện sở hữu (RentalRoomId2). */
    private function roomBills(BookingRoom $room): Collection
    {
        $bills = $room->relationLoaded('serviceBills') && $room->relationLoaded('currentServiceBills')
            ? $room->serviceBills->concat($room->currentServiceBills)
            : ServiceBill::query()->where(fn ($q) => $q->where('RentalRoomId1', $room->id)->orWhere('RentalRoomId2', $room->id))->get();

        return $bills->unique('Ma')->values();
    }

    private function isRoomChargeService($svc): bool
    {
        return in_array($this->code($svc->service_code), self::ROOM_CHARGE_CODES, true);
    }

    private function isRoomChargeBill(ServiceBill $bill): bool
    {
        if (!$this->isValidBill($bill)) return false;
        $name = (string) $bill->DescriptionServive;
        return in_array($this->code($bill->ServiceId), ['RM', 'ER'], true) || Str::contains($name, ['Tiền phòng', 'Room Charge']);
    }

    private function isChildBreakfastBill(ServiceBill $bill, string $bfCode): bool
    {
        $code = $this->code($bill->ServiceId);
        return $code === $bfCode || $code === 'BD' || Str::contains(Str::lower((string) $bill->DescriptionServive), 'ăn sáng trẻ em');
    }

    private function isValidBill(ServiceBill $bill): bool
    {
        return (int) $bill->Edit !== 1 && !in_array((int) $bill->Status, [3, 4], true);
    }

    private function billDate(ServiceBill $bill): string
    {
        $date = $this->dateOnly($bill->Date);
        if ($date !== '' || !$bill->Year || !$bill->Month || !$bill->Day) return $date;
        return sprintf('%04d-%02d-%02d', $bill->Year, $bill->Month, $bill->Day);
    }

    private function isInStay(string $date, string $arrival, string $departure, bool $isDayUse): bool
    {
        if ($date === '' || $arrival === '' || $departure === '') return true;
        if ($arrival === $departure) return $isDayUse && $date === $arrival;
        return $date >= $arrival && $date < $departure;
    }

    private function nightCount(string $arrival, string $departure): int
    {
        if ($arrival === '' || $departure === '') return 1;
        return max(0, (int) Carbon::parse($arrival)->diffInDays(Carbon::parse($departure), false));
    }

    private function dateOnly($value): string
    {
        if ($value instanceof \DateTimeInterface) return $value->format('Y-m-d');
        $raw = trim((string) $value);
        if ($raw === '') return '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) return substr($raw, 0, 10);
        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return '';
        }
    }

    private function code($value): string
    {
        return Str::upper(trim((string) $value));
    }

    private function hasValue($value): bool
    {
        return $value !== null && $value !== '';
    }
}
