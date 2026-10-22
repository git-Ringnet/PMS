<?php

namespace App\Services;

use App\Models\BookingRoom;
use App\Models\BookingRoomGuest;
use App\Models\Guest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Folio phòng ảo: phòng không kinh doanh, chỉ dùng để post bill/thanh toán dịch vụ phát sinh
 * cho khách không lưu trú. Không tạo booking (tránh nhảy số BK): chỉ có dòng booking_rooms
 * (booking_id NULL), một guest mang tên phòng và liên kết booking_room_guests.
 */
class VirtualRoomFolioService
{
    /** Ngày đi để xa để folio luôn mở cho việc post bill. */
    public const OPEN_DEPARTURE_DATE = '2099-12-31';

    public function folioName(string $roomNumber): string
    {
        return 'PHÒNG ẢO ' . $roomNumber;
    }

    /** Folio đang mở của phòng ảo (booking_id NULL, chưa hủy/checkout). */
    public function activeFolio(string $roomNumber): ?BookingRoom
    {
        return BookingRoom::query()
            ->whereNull('booking_id')
            ->where('room_number', $roomNumber)
            ->whereIn('status', [BookingRoom::STATUS_BOOKED, BookingRoom::STATUS_CHECKED_IN])
            ->orderBy('created_at')
            ->first();
    }

    public function createFolio(string $roomNumber, $roomClassId, string $username, ?string $arrivalDate = null): BookingRoom
    {
        $arrivalDate ??= $this->systemDate();

        return DB::transaction(function () use ($roomNumber, $roomClassId, $username, $arrivalDate) {
            $bookingRoom = BookingRoom::create([
                'booking_id' => null,
                'room_number' => $roomNumber,
                'room_class_id' => $roomClassId,
                'arrival_date' => $arrivalDate,
                'departure_date' => self::OPEN_DEPARTURE_DATE,
                'actual_arrival_date' => $arrivalDate,
                'planned_departure_date' => self::OPEN_DEPARTURE_DATE,
                'status' => BookingRoom::STATUS_BOOKED,
                'rate' => 0,
                'base_price' => 0,
                'adults' => 0,
                'babies' => 0,
                'children_qty' => 0,
                'created_by' => $username,
                'updated_by' => $username,
            ]);
            $this->attachNamedGuest($bookingRoom);

            return $bookingRoom;
        });
    }

    /** Guest mang tên phòng để bill/thanh toán có người nhận (CustomerId) như phòng thường. */
    public function attachNamedGuest(BookingRoom $bookingRoom): BookingRoomGuest
    {
        $existing = $bookingRoom->guests()->where('is_primary', true)->first();
        if ($existing) {
            return $existing;
        }

        $guest = Guest::create(['full_name' => $this->folioName((string) $bookingRoom->room_number)]);

        return BookingRoomGuest::create([
            'booking_room_id' => $bookingRoom->id,
            'guest_id' => $guest->id,
            'is_primary' => true,
            'status' => BookingRoomGuest::STATUS_CHECKED_IN,
            'actual_arrival_date' => $bookingRoom->arrival_date,
        ]);
    }

    private function systemDate(): string
    {
        $systemDate = DB::table('system_date_rolls')->orderByDesc('id')->value('system_date');

        return $systemDate
            ? Carbon::parse($systemDate)->toDateString()
            : now('Asia/Ho_Chi_Minh')->toDateString();
    }
}
