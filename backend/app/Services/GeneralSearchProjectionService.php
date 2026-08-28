<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingChild;
use App\Models\BookingRoom;
use App\Models\BookingRoomGuest;
use App\Models\BookingRoomService;
use App\Models\Payment;
use App\Models\ServiceBill;
use Illuminate\Support\Collection;

/** Read-only projections used by General Search (Laravel/MySQL, not a legacy SP port). */
class GeneralSearchProjectionService
{
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

    public function bookingRow(Booking $booking): array
    {
        $rooms = $booking->bookingRooms;
        $roomsById = $rooms->keyBy('id');
        $visibleRooms = $rooms->filter(fn (BookingRoom $room) => (int) $room->status !== BookingRoom::STATUS_MOVED);
        $roomRows = $visibleRooms->map(fn (BookingRoom $room) => $this->roomDetail($room, $roomsById))->values();
        $createdRoomTypes = $rooms->map(fn (BookingRoom $room) => $room->originalRoomClass?->code ?: $room->roomClass?->code)
            ->filter()->countBy()->map(fn ($count, $code) => sprintf('%s (%d)', $code, $count))->values()->join(', ');
        $actualRoomTypes = $roomRows->pluck('room_class.code')->filter()->countBy()
            ->map(fn ($count, $code) => sprintf('%s (%d)', $code, $count))->values()->join(', ');

        return [
            'id' => $booking->id, 'booking_code' => $booking->booking_code, 'reference_code' => $booking->external_booking_code,
            'booking_name' => $booking->booking_name, 'company' => $booking->company?->name, 'market' => $booking->market?->name,
            'arrival_date' => $booking->arrival_date?->toDateString(), 'departure_date' => $booking->departure_date?->toDateString(),
            'nights' => $booking->num_of_days, 'original_room_types' => $createdRoomTypes, 'actual_room_types' => $actualRoomTypes,
            'total_amount' => $this->bookingServiceTotal($booking, $rooms), 'deposit_amount' => $this->bookingDeposit($booking),
            'registration_status' => $booking->registrationStatus?->vietnamese ?: $booking->registrationStatus?->name,
            'operation_status' => $booking->status, 'note' => $booking->note, 'booking_date' => $booking->booking_date?->toDateString(),
            'sales_person' => $booking->sales_person, 'created_by' => $booking->creator?->name ?: $booking->created_by,
            'contact_phone' => $booking->contact_phone, 'rooms' => $roomRows,
        ];
    }

    public function roomGroupRow(Booking $booking, Collection $rooms): array
    {
        $roomsById = $booking->bookingRooms->keyBy('id');
        $roomRows = $rooms->map(fn (BookingRoom $room) => $this->roomDetail($room, $roomsById))->values();

        return [
            'booking_id' => $booking->id, 'booking_code' => $booking->booking_code, 'booking_name' => $booking->booking_name,
            'arrival_date' => $booking->arrival_date?->toDateString(), 'departure_date' => $booking->departure_date?->toDateString(),
            'nights' => $booking->num_of_days, 'note' => $booking->note,
            'service_total' => $this->bookingServiceTotal($booking, $booking->bookingRooms), 'paid_total' => $this->bookingPaidTotal($booking),
            'rooms' => $roomRows,
        ];
    }

    public function guestRow(BookingRoomGuest $assignment): array
    {
        $room = $assignment->bookingRoom;
        $booking = $room?->booking;
        $guest = $assignment->guest;
        return [
            'id' => $assignment->id, 'booking_code' => $booking?->booking_code, 'room_number' => $room?->room_number,
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
            'id' => 'child-' . $child->id, 'booking_code' => $booking?->booking_code, 'room_number' => $room?->room_number,
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

    private function roomDetail(BookingRoom $room, Collection $roomsById): array
    {
        $finalRoom = $this->finalRoom($room, $roomsById);
        $primaryGuest = $finalRoom->guests->firstWhere('is_primary', true)?->guest ?: $finalRoom->guests->first()?->guest;
        return [
            'id' => $finalRoom->id, 'source_room_id' => $room->id, 'room_number' => $finalRoom->room_number,
            'room_class' => ['code' => $finalRoom->roomClass?->code, 'name' => $finalRoom->roomClass?->name],
            'original_room_class' => ['code' => $room->originalRoomClass?->code, 'name' => $room->originalRoomClass?->name],
            'room_status' => $finalRoom->status, 'guest_name' => $primaryGuest?->full_name,
            'arrival_date' => $room->arrival_date?->toDateString(), 'departure_date' => $finalRoom->departure_date?->toDateString(),
            'nights' => $room->ActutalNumOfDays, 'rate' => (float) $room->rate, 'rate_code' => $room->rate_code,
            'extra_bed_qty' => $room->extra_bed_qty, 'extra_bed_rate' => (float) $room->extra_bed_rate,
            'adults' => $room->adults, 'children' => $room->children_qty, 'note' => $room->note,
            'service_total' => $this->roomServiceTotal($room), 'paid_total' => $this->roomPaidTotal($room),
            'checkin_time' => $finalRoom->arrival_time, 'checkout_time' => $finalRoom->CheckoutTime ?: $finalRoom->departure_time,
            'booking_date' => $room->booking?->booking_date?->toDateString(),
        ];
    }

    private function bookingServiceTotal(Booking $booking, Collection $rooms): float
    {
        return (float) $rooms->sum(fn (BookingRoom $room) => $this->roomServiceTotal($room)) + $this->unallocatedBookingBills($booking);
    }

    private function roomServiceTotal(BookingRoom $room): float
    {
        $services = BookingRoomService::where('booking_room_id', $room->id)->get(['service_code', 'total_amount', 'service_bill_id']);
        $linkedBillIds = $services->pluck('service_bill_id')->filter()->unique();
        $bills = ServiceBill::query()->where(fn ($q) => $q->where('RentalRoomId1', $room->id)->orWhere('RentalRoomId2', $room->id))
            ->where('Edit', false)->whereNotIn('Ma', $linkedBillIds)->get(['ServiceId', 'Amount']);

        // RM/EB are generated per night.  Before night audit, there may be no
        // rows yet, so show the agreed final room rate from booking_rooms.
        // Once actual room charges exceed that value (for example surcharge),
        // use the posted amount and never count the same linked bill twice.
        $roomChargeCodes = [BookingRoomService::CODE_ROOM, BookingRoomService::CODE_EXTRA_BED];
        $actualRoomCharge = (float) $services->whereIn('service_code', $roomChargeCodes)->sum('total_amount')
            + (float) $bills->whereIn('ServiceId', $roomChargeCodes)->sum('Amount');
        $nights = max(1, (int) ($room->ActutalNumOfDays ?: 0));
        $expectedRoomCharge = ((float) $room->rate + ((int) $room->extra_bed_qty * (float) $room->extra_bed_rate)) * $nights;
        $otherServices = (float) $services->reject(fn ($service) => in_array($service->service_code, $roomChargeCodes, true))->sum('total_amount')
            + (float) $bills->reject(fn ($bill) => in_array($bill->ServiceId, $roomChargeCodes, true))->sum('Amount');

        return $otherServices + max($expectedRoomCharge, $actualRoomCharge);
    }

    private function unallocatedBookingBills(Booking $booking): float
    {
        $linkedBillIds = BookingRoomService::query()->whereIn('booking_room_id', $booking->bookingRooms->pluck('id'))
            ->whereNotNull('service_bill_id')->pluck('service_bill_id');
        return (float) ServiceBill::query()->where(fn ($q) => $q->where('RegisterId1', $booking->id)->orWhere('RegisterID2', $booking->id))
            ->whereNull('RentalRoomId1')->whereNull('RentalRoomId2')->where('Edit', false)->whereNotIn('Ma', $linkedBillIds)->sum('Amount');
    }

    private function bookingDeposit(Booking $booking): float
    {
        return (float) Payment::where('booking_id', $booking->id)->where('pack2', Payment::PACK2_DEPOSIT)
            ->where('edit_flag', 0)->where('status', '!=', Payment::STATUS_DELETED)->sum('amount');
    }

    private function bookingPaidTotal(Booking $booking): float
    {
        return (float) Payment::where('booking_id', $booking->id)->where('edit_flag', 0)->where('status', Payment::STATUS_PAID)->sum('amount');
    }

    private function roomPaidTotal(BookingRoom $room): float
    {
        return (float) Payment::where('booking_room_id', $room->id)->where('edit_flag', 0)->where('status', Payment::STATUS_PAID)->sum('amount');
    }
}
