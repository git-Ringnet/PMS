<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingChild;
use App\Models\BookingRoom;
use App\Models\BookingRoomGuest;
use App\Models\Payment;
use Illuminate\Support\Collection;

/** Read-only projections used by General Search (Laravel/MySQL, not a legacy SP port). */
class GeneralSearchProjectionService
{
    public function __construct(
        private readonly BookingDisplayTotalService $bookingTotals,
        private readonly InvoiceFolioAmountService $invoice,
    ) {}

    /** Equivalent in purpose to legacy func_081: resolve the final room after a room move. */
    public function finalRoom(BookingRoom $room, ?Collection $roomsById = null): BookingRoom
    {
        $seen = [];
        $current = $room;

        while ($current->move_room && !isset($seen[$current->id])) {
            $seen[$current->id] = true;
            $next = $roomsById?->get($current->move_room) ?? BookingRoom::withTrashed()->find($current->move_room);
            if (!$next) break;
            $current = $next;
        }

        return $current;
    }

    /**
     * Dòng 409/410: booking đang hoạt động (0,1,2,4) không hiển thị phòng đã hủy, giống
     * BookingController::hideCancelledRoomsForActiveBooking. Booking đã hủy giữ phòng để xem lại.
     */
    public function activeRooms(Booking $booking, Collection $rooms): Collection
    {
        if ((int) $booking->status === Booking::STATUS_DELETED) return $rooms->values();
        return $rooms->reject(fn (BookingRoom $room) => (int) $room->status === BookingRoom::STATUS_CANCELLED)->values();
    }

    public function bookingRow(Booking $booking): array
    {
        $rooms = $this->activeRooms($booking, $booking->bookingRooms);
        $roomsById = $booking->bookingRooms->keyBy('id');
        $visibleRooms = $rooms->filter(fn (BookingRoom $room) => (int) $room->status !== BookingRoom::STATUS_MOVED);
        $roomRows = $visibleRooms->map(fn (BookingRoom $room) => $this->roomDetail($room, $roomsById, $booking, false))->values();
        $createdRoomTypes = $rooms->map(fn (BookingRoom $room) => $room->originalRoomClass?->code ?: $room->roomClass?->code)
            ->filter()->countBy()->map(fn ($count, $code) => sprintf('%s (%d)', $code, $count))->values()->join(', ');
        $actualRoomTypes = $roomRows->pluck('room_class.code')->filter()->countBy()
            ->map(fn ($count, $code) => sprintf('%s (%d)', $code, $count))->values()->join(', ');

        return [
            'id' => $booking->id, 'booking_code' => $booking->booking_code, 'reference_code' => $booking->external_booking_code,
            'booking_name' => $booking->booking_name, 'company' => $booking->company?->name, 'market' => $booking->market?->name,
            'arrival_date' => $booking->arrival_date?->toDateString(), 'departure_date' => $booking->departure_date?->toDateString(),
            'nights' => $booking->num_of_days, 'original_room_types' => $createdRoomTypes, 'actual_room_types' => $actualRoomTypes,
            'total_amount' => (float) $roomRows->sum('room_total'), 'deposit_amount' => $this->bookingDeposit($booking),
            'registration_status' => $booking->registrationStatus?->vietnamese ?: $booking->registrationStatus?->name,
            'registration_status_color' => $booking->registrationStatus?->color,
            'registration_status_id' => $booking->registration_status_id,
            'operation_status' => $booking->status, 'note' => $booking->note, 'booking_date' => $booking->booking_date?->toDateString(),
            'sales_person' => $booking->sales_person, 'created_by' => $booking->creator?->name ?: $booking->created_by,
            'contact_phone' => $booking->contact_phone, 'rooms' => $roomRows,
        ];
    }

    public function roomGroupRow(Booking $booking, Collection $rooms): array
    {
        $roomsById = $booking->bookingRooms->keyBy('id');
        $roomRows = $rooms->map(fn (BookingRoom $room) => $this->roomDetail($room, $roomsById, $booking, true))->values();
        $payments = $booking->relationLoaded('payments') ? $booking->payments : null;
        $folioRooms = $this->activeRooms($booking, $booking->bookingRooms)
            ->filter(fn (BookingRoom $room) => (int) $room->status !== BookingRoom::STATUS_MOVED);

        return [
            'booking_id' => $booking->id, 'booking_code' => $booking->booking_code, 'booking_name' => $booking->booking_name,
            'arrival_date' => $booking->arrival_date?->toDateString(), 'departure_date' => $booking->departure_date?->toDateString(),
            'nights' => $booking->num_of_days, 'note' => $booking->note,
            // Tổng của booking = Phiếu Tổng + các folio phòng, cùng cách tính với màn hình Hóa đơn.
            'service_total' => $this->invoice->masterServiceTotal($booking)
                + (float) $folioRooms->sum(fn (BookingRoom $room) => $this->invoice->roomServiceTotal($room)),
            'paid_total' => $this->invoice->masterPaidTotal($booking, $payments)
                + (float) $folioRooms->sum(fn (BookingRoom $room) => $this->invoice->roomPaidTotal($room, $payments)),
            'rooms' => $roomRows,
        ];
    }

    public function guestRow(BookingRoomGuest $assignment): array
    {
        $room = $assignment->bookingRoom;
        $booking = $room?->booking;
        $guest = $assignment->guest;
        return [
            'id' => $assignment->id, 'booking_id' => $booking?->id, 'guest_id' => $guest?->id, 'booking_code' => $booking?->booking_code, 'room_number' => $room?->room_number,
            'guest_name' => $guest?->full_name, 'arrival_date' => $room?->arrival_date?->toDateString(),
            'departure_date' => $room?->departure_date?->toDateString(), 'nights' => $room?->ActutalNumOfDays,
            'rate' => (float) ($room?->rate ?? 0), 'rate_code' => $room?->rate_code, 'company' => $booking?->company?->name,
            'id_type' => $guest?->id_type, 'id_number' => $guest?->id_number ?: $guest?->passport_number, 'email' => $guest?->email,
            'phone' => $guest?->phone, 'dob' => $guest?->dob?->toDateString(), 'nationality' => $guest?->nationality_code,
            'province' => $guest?->province, 'address' => $guest?->address, 'visa_no' => $guest?->visa_no,
            'visa_expiry_date' => $guest?->visa_expiry_date?->toDateString(), 'entry_date' => $guest?->entry_date?->toDateString(),
            'border_gate' => $guest?->border_gate, 'guest_type' => 'Khách',
        ];
    }

    /** Same output shape as an adult guest, so the Guest tab includes children too. */
    public function childRow(BookingChild $child): array
    {
        $room = $child->bookingRoom;
        $booking = $child->booking;
        return [
            'id' => 'child-' . $child->id, 'booking_id' => $booking?->id, 'booking_code' => $booking?->booking_code, 'room_number' => $room?->room_number,
            'guest_name' => $child->full_name, 'arrival_date' => $room?->arrival_date?->toDateString(),
            'departure_date' => $room?->departure_date?->toDateString(), 'nights' => $room?->ActutalNumOfDays,
            'rate' => (float) ($room?->rate ?? 0), 'rate_code' => $room?->rate_code, 'company' => $booking?->company?->name,
            'id_type' => $child->id_type, 'id_number' => $child->id_number ?: $child->passport_number, 'email' => $child->email,
            'phone' => $child->phone, 'dob' => $child->dob?->toDateString(), 'nationality' => $child->nationality_code,
            'province' => $child->province, 'address' => $child->address, 'visa_no' => $child->visa_no,
            'visa_expiry_date' => $child->visa_expiry_date?->toDateString(), 'entry_date' => $child->entry_date?->toDateString(),
            'border_gate' => $child->border_gate, 'guest_type' => $child->age_group === 'baby' ? 'Em bé' : 'Trẻ em',
        ];
    }

    private function roomDetail(BookingRoom $room, Collection $roomsById, Booking $booking, bool $invoiceAmounts): array
    {
        $finalRoom = $this->finalRoom($room, $roomsById);
        $payments = $booking->relationLoaded('payments') ? $booking->payments : null;
        // Trẻ em/em bé đếm theo booking_children giống màn hình Booking (không dùng children_qty).
        $children = $room->relationLoaded('children') ? $room->children : null;
        $primaryGuest = $finalRoom->guests->firstWhere('is_primary', true)?->guest ?: $finalRoom->guests->first()?->guest;
        return [
            'id' => $finalRoom->id, 'source_room_id' => $room->id,
            'booking_id' => $booking->id, 'booking_code' => $booking->booking_code,
            'room_number' => $finalRoom->room_number,
            'room_class' => ['code' => $finalRoom->roomClass?->code, 'name' => $finalRoom->roomClass?->name],
            'original_room_class' => ['code' => $room->originalRoomClass?->code, 'name' => $room->originalRoomClass?->name],
            'room_status' => $finalRoom->status, 'guest_name' => $primaryGuest?->full_name,
            'arrival_date' => $room->arrival_date?->toDateString(), 'departure_date' => $finalRoom->departure_date?->toDateString(),
            'nights' => $room->ActutalNumOfDays, 'rate' => (float) $room->rate, 'rate_code' => $room->rate_code,
            'extra_bed_qty' => $room->extra_bed_qty, 'extra_bed_rate' => (float) $room->extra_bed_rate,
            'adults' => $room->adults, 'note' => $room->note,
            'children' => $children ? $children->where('age_group', 'child')->count() : (int) $room->children_qty,
            'babies' => $children ? $children->where('age_group', 'baby')->count() : (int) $room->babies,
            // Tab Đăng ký: tiền như màn hình Booking. Tab Phòng: Tổng DV/Đã TT như màn hình Hóa đơn.
            'room_total' => $invoiceAmounts ? null : $this->bookingTotals->roomTotal($room),
            'service_total' => $invoiceAmounts ? $this->invoice->roomServiceTotal($room) : null,
            'paid_total' => $invoiceAmounts ? $this->invoice->roomPaidTotal($room, $payments) : null,
            'checkin_time' => $finalRoom->arrival_time, 'checkout_time' => $finalRoom->CheckoutTime ?: $finalRoom->departure_time,
            'booking_date' => $room->booking?->booking_date?->toDateString(),
        ];
    }

    private function bookingDeposit(Booking $booking): float
    {
        // Giống "Đặt cọc" trên màn hình Booking (active_deposit_total).
        $payments = $booking->relationLoaded('payments') ? $booking->payments : Payment::where('booking_id', $booking->id)->get();
        return (float) $payments->where('pack2', Payment::PACK2_DEPOSIT)->filter(fn (Payment $payment) => (int) $payment->edit_flag === 0)->sum('amount');
    }
}
