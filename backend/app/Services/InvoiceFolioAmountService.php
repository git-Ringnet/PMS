<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Payment;
use App\Models\ServiceBill;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Dòng 410: "Tổng DV" và "Đã TT" giống màn hình Hóa đơn (CheckoutPage.vue).
 * Chỉ tính tiền đã post (bill / dịch vụ đã post), không tính giá dự kiến của BK.
 */
class InvoiceFolioAmountService
{
    /** Tổng DV của folio phòng: dịch vụ đã post + tiền phòng đã post thuộc phòng. */
    public function roomServiceTotal(BookingRoom $room): float
    {
        $services = $room->services ?? collect();
        $extraServices = $services
            ->filter(fn ($svc) => $this->roomFlag($svc) === 1 && !$this->isRoomCharge($svc->service_code, $svc->service_name) && $this->isPosted($svc))
            ->sum(fn ($svc) => $this->serviceAmount($svc));

        $processedBillIds = [];
        $postedRoomCharge = 0.0;
        foreach ($this->roomBills($room) as $bill) {
            if ((int) $bill->Edit === 1 || !$this->isRoomCharge($bill->ServiceId, $bill->DescriptionServive)) continue;
            if ((string) $this->currentRoomId($bill) !== (string) $room->id) continue;
            $processedBillIds[(string) $bill->Ma] = true;
            $postedRoomCharge += (float) $bill->Amount;
        }

        // Tiền phòng chỉ còn liên kết ở booking_room_services (bill cũ).
        foreach ($services as $svc) {
            if ($this->roomFlag($svc) !== 1 || !$this->isRoomCharge($svc->service_code, $svc->service_name) || !$this->isPosted($svc)) continue;
            if ($svc->service_bill_id && isset($processedBillIds[(string) $svc->service_bill_id])) continue;
            if (!$svc->service_bill_id && $processedBillIds !== []) continue;
            $postedRoomCharge += $this->serviceAmount($svc);
        }

        return (float) $extraServices + $postedRoomCharge;
    }

    /** Đã TT của folio phòng: mọi khoản thanh toán/cọc còn hiệu lực gắn với phòng. */
    public function roomPaidTotal(BookingRoom $room, ?Collection $payments = null): float
    {
        return (float) ($payments ?? Payment::where('booking_room_id', $room->id)->get())
            ->filter(fn (Payment $payment) => (string) $payment->booking_room_id === (string) $room->id && !$payment->edit_flag)
            ->sum('amount');
    }

    /** Tổng DV của Phiếu Tổng (Master) của booking. */
    public function masterServiceTotal(Booking $booking): float
    {
        $allBills = ServiceBill::query()
            ->where(fn ($q) => $q->where('RegisterID2', $booking->id)->orWhere('RegisterId1', $booking->id))
            ->get()->unique('Ma')->values();
        $masterBills = $allBills->filter(fn (ServiceBill $bill) => (int) $bill->Edit !== 1
            && !in_array((int) $bill->Status, [3, 4], true) && $this->isMasterBill($bill, $booking));

        $counted = $masterBills->mapWithKeys(fn (ServiceBill $bill) => [(string) $bill->Ma => true])->all();
        $total = (float) $masterBills->sum('Amount');
        $billsById = $allBills->keyBy(fn (ServiceBill $bill) => (string) $bill->Ma);
        foreach ($booking->bookingRooms as $room) {
            foreach ($room->services ?? [] as $svc) {
                if (!$this->isRoomCharge($svc->service_code, $svc->service_name) || $this->roomFlag($svc) !== 0) continue;
                $linkedId = $svc->service_bill_id ? (string) $svc->service_bill_id : null;
                if ($linkedId && isset($counted[$linkedId])) continue;
                if ($linkedId && $billsById->has($linkedId)) {
                    $linked = $billsById->get($linkedId);
                    if ((int) $linked->Edit !== 1 && !in_array((int) $linked->Status, [3, 4], true) && $this->isMasterBill($linked, $booking)) {
                        $total += (float) $linked->Amount;
                        $counted[$linkedId] = true;
                    }
                    continue;
                }
                $total += $this->serviceAmount($svc);
            }
        }

        return $total;
    }

    /** Đã TT của Phiếu Tổng: thanh toán/cọc chung không gắn phòng. */
    public function masterPaidTotal(Booking $booking, ?Collection $payments = null): float
    {
        return (float) ($payments ?? Payment::where('booking_id', $booking->id)->get())
            ->filter(fn (Payment $payment) => (string) $payment->booking_id === (string) $booking->id && !$payment->booking_room_id && !$payment->edit_flag)
            ->sum('amount');
    }

    private function roomBills(BookingRoom $room): Collection
    {
        $bills = $room->relationLoaded('serviceBills') && $room->relationLoaded('currentServiceBills')
            ? $room->serviceBills->concat($room->currentServiceBills)
            : ServiceBill::query()->where(fn ($q) => $q->where('RentalRoomId1', $room->id)->orWhere('RentalRoomId2', $room->id))->get();

        return $bills->unique('Ma')->values();
    }

    /** Bill đã chuyển giữ ID gốc để truy vết; chỉ chủ sở hữu hiện tại quyết định bill thuộc phòng nào. */
    private function currentRoomId(ServiceBill $bill)
    {
        if ($this->hasValue($bill->RentalRoomId2)) return $bill->RentalRoomId2;
        if ($this->hasValue($bill->RegisterID2)) return null;
        return $bill->RentalRoomId1;
    }

    private function isMasterBill(ServiceBill $bill, Booking $booking): bool
    {
        if ($this->hasValue($bill->RentalRoomId2) || $this->hasValue($bill->CustomerId2)) return false;
        if ((string) $bill->RegisterID2 === (string) $booking->id) return true;
        return !$this->hasValue($bill->RegisterID2) && (string) $bill->RegisterId1 === (string) $booking->id && !$this->hasValue($bill->RentalRoomId1);
    }

    private function isRoomCharge($code, $name): bool
    {
        return in_array(Str::upper(trim((string) $code)), ['RM', 'ER'], true) || Str::contains((string) $name, 'Tiền phòng');
    }

    private function isPosted($svc): bool
    {
        return (int) $svc->is_posted === 1 || (bool) $svc->service_bill_id;
    }

    private function roomFlag($svc): int
    {
        return $svc->is_room === null || $svc->is_room === '' ? 1 : ((int) $svc->is_room === 1 ? 1 : 0);
    }

    private function serviceAmount($svc): float
    {
        return (float) $svc->total_amount ?: ((float) $svc->quantity ?: 1) * (float) $svc->rate;
    }

    private function hasValue($value): bool
    {
        return $value !== null && (string) $value !== '' && (string) $value !== '0';
    }
}
