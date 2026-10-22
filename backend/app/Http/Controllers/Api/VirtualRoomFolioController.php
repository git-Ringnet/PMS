<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingRoom;
use App\Models\Payment;
use App\Models\ServiceBill;
use App\Services\VirtualRoomFolioService;

/**
 * Folio phòng ảo cho màn hình Hóa đơn. Phòng ảo không có booking nên trả về theo cấu trúc
 * giống booking (booking_rooms, service_bills, payments) để giao diện dùng lại cách hiển thị folio.
 */
class VirtualRoomFolioController extends Controller
{
    public function index(VirtualRoomFolioService $folios)
    {
        $billRelations = [
            'employeeOperator:id,employee_code,name',
            'usernameOperator:id,username,name',
            'user:id,name,username',
            'hotelService:id,code,name',
        ];

        $rooms = BookingRoom::query()
            ->whereNull('booking_id')
            ->whereIn('status', [BookingRoom::STATUS_BOOKED, BookingRoom::STATUS_CHECKED_IN])
            ->with(['room', 'roomClass', 'guests.guest', 'services'])
            ->orderBy('room_number')
            ->get();

        $data = $rooms->map(function (BookingRoom $room) use ($folios, $billRelations) {
            $roomId = (string) $room->id;
            $bills = ServiceBill::query()
                ->where(fn ($query) => $query->where('RentalRoomId1', $roomId)->orWhere('RentalRoomId2', $roomId))
                ->with($billRelations)
                ->orderBy('Ma')
                ->get();
            $payments = Payment::query()
                ->where('booking_room_id', $roomId)
                ->withSum([
                    'debtSettlements as settled_amount' => fn ($query) => $query->where('edit_flag', 0)->whereNull('deleted_at'),
                ], 'amount')
                ->with(['paymentMethod', 'user', 'bankAccount'])
                ->orderBy('id')
                ->get();

            $roomData = $room->toArray();
            $roomData['service_bills'] = $bills->filter(fn ($bill) => (string) $bill->RentalRoomId1 === $roomId)->values()->toArray();
            $roomData['current_service_bills'] = $bills->filter(fn ($bill) => (string) $bill->RentalRoomId2 === $roomId)->values()->toArray();

            return [
                'id' => null,
                'virtual_folio' => true,
                'virtual_room_id' => $roomId,
                'booking_code' => (string) $room->room_number,
                'booking_name' => $folios->folioName((string) $room->room_number),
                'status' => 1,
                'is_service_only' => true,
                'no_post' => false,
                'arrival_date' => $room->arrival_date?->toDateString(),
                'departure_date' => $room->departure_date?->toDateString(),
                'booking_rooms' => [$roomData],
                'service_bills' => $bills->toArray(),
                'master_service_bills' => [],
                'payments' => $payments->toArray(),
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $data]);
    }
}
