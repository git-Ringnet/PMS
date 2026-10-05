<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\HotelConfig;
use App\Services\BookingRoomLifecycleService;
use App\Services\RoomAvailabilityService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class RoomPlanBookingRoomController extends Controller
{
    public function __construct(
        private RoomAvailabilityService $availability,
        private BookingRoomLifecycleService $bookingRoomLifecycleService,
    ) {}

    /**
     * Update one reservation's stay dates from Room Plan and expand its
     * booking header when necessary. The generic booking-room update endpoint
     * keeps its existing date-range guard for every other screen.
     */
    public function updateStay(Request $request, $bookingId, $roomId)
    {
        $validated = $request->validate([
            'arrival_date' => 'required|date_format:Y-m-d',
            'departure_date' => 'required|date_format:Y-m-d',
            'confirm_overbooking' => 'sometimes|boolean',
        ]);

        try {
            return DB::transaction(function () use ($validated, $bookingId, $roomId) {
                $booking = Booking::query()->lockForUpdate()->findOrFail($bookingId);
                $bookingRoom = BookingRoom::query()
                    ->where('booking_id', $booking->id)
                    ->lockForUpdate()
                    ->findOrFail($roomId);
                $bookingRoom->setRelation('booking', $booking);

                if ($bookingRoom->isVirtual()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Không thể chỉnh ngày lưu trú trên phòng ảo.',
                    ], 422);
                }

                if ((int) $bookingRoom->status !== BookingRoom::STATUS_BOOKED) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Chỉ cho phép thay đổi ngày của phòng đang đặt trước trên Room Plan.',
                    ], 422);
                }

                if ($bookingRoom->is_do_not_move) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Phòng này đang bị khóa chuyển phòng. Vui lòng mở khóa trước.',
                    ], 422);
                }

                if ($bookingRoom->is_day_use) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Không thể kéo thay đổi ngày của phòng ở theo giờ.',
                    ], 422);
                }

                $arrivalDate = $validated['arrival_date'];
                $departureDate = $validated['departure_date'];
                if ($departureDate <= $arrivalDate) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ngày đi phải sau ngày đến.',
                    ], 422);
                }

                $currentArrival = $bookingRoom->arrival_date->toDateString();
                $currentDeparture = $bookingRoom->departure_date->toDateString();
                $arrivalChanged = $arrivalDate !== $currentArrival;
                $datesChanged = $arrivalChanged || $departureDate !== $currentDeparture;

                if ($arrivalChanged) {
                    $allowChangeArrival = (string) HotelConfig::query()
                        ->where('name', 'RoomPlan_AllowChangeArrivalDate')
                        ->value('value') === '1';

                    if (!$allowChangeArrival) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Không cho phép thay đổi ngày đến trên Room Plan (RoomPlan_AllowChangeArrivalDate đang tắt).',
                        ], 422);
                    }

                    if ($arrivalDate < $this->availability->getSystemDate()->toDateString()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Ngày đến không được trước ngày hệ thống.',
                        ], 422);
                    }
                }

                $av = null;
                $allowOver = (string) HotelConfig::query()
                    ->where('name', 'AllowOverRoomTypeRoomKind')
                    ->value('value') === '1';

                if ($datesChanged) {
                    if (!empty($bookingRoom->room_number)
                        && $this->availability->isRoomNumberOccupied(
                            $bookingRoom->room_number,
                            $arrivalDate,
                            $departureDate,
                            $bookingRoom->id,
                            $booking->id,
                            $bookingRoom->arrival_time
                                ? Carbon::parse($bookingRoom->arrival_time)->format('H:i:s')
                                : null,
                            $bookingRoom->departure_time
                                ? Carbon::parse($bookingRoom->departure_time)->format('H:i:s')
                                : null,
                        )) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Số phòng ' . $bookingRoom->room_number . ' đã được gán cho booking khác trong cùng khoảng thời gian.',
                        ], 422);
                    }

                    $av = $this->availability->getAvailability(
                        (int) $bookingRoom->room_class_id,
                        $arrivalDate,
                        $departureDate,
                        $bookingRoom->id,
                    );

                    if ($av < 0 && !$allowOver) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Không đủ phòng trống sau khi cập nhật. (AV = ' . $av . ')',
                            'av' => $av,
                        ], 422);
                    }

                    if ($av < 0 && empty($validated['confirm_overbooking'])) {
                        return response()->json([
                            'success' => false,
                            'code' => 'overbooking_confirmation_required',
                            'requires_confirmation' => true,
                            'message' => 'Loại phòng đang bị over (AV = ' . $av . '), bạn có muốn tiếp tục?',
                            'av' => $av,
                        ], 409);
                    }
                }

                $bookingRoom->update([
                    'arrival_date' => $arrivalDate,
                    'departure_date' => $departureDate,
                    'updated_by' => Auth::user()?->username ?? 'system',
                ]);

                $updatedRoom = $bookingRoom->fresh();
                $this->bookingRoomLifecycleService->synchronize(
                    $updatedRoom,
                    false,
                    $datesChanged,
                    false,
                );

                // Recalculate the booking header from every active room so a
                // Room Plan resize can both expand and narrow the parent dates.
                $activeRooms = BookingRoom::query()
                    ->where('booking_id', $booking->id)
                    ->active()
                    ->get(['arrival_date', 'departure_date']);

                $arrivals = $activeRooms
                    ->map(fn (BookingRoom $room) => Carbon::parse($room->arrival_date)->toDateString());
                $departures = $activeRooms
                    ->map(fn (BookingRoom $room) => Carbon::parse($room->departure_date)->toDateString());

                $bookingArrival = $arrivals->min() ?? $booking->arrival_date->toDateString();
                $bookingDeparture = $departures->max() ?? $booking->departure_date->toDateString();
                $bookingNights = $booking->is_day_use
                    ? 0
                    : Carbon::parse($bookingArrival)->diffInDays(Carbon::parse($bookingDeparture));
                if ($bookingArrival !== $booking->arrival_date->toDateString()
                    || $bookingDeparture !== $booking->departure_date->toDateString()
                    || (int) $booking->num_of_days !== $bookingNights) {
                    $booking->update([
                        'arrival_date' => $bookingArrival,
                        'departure_date' => $bookingDeparture,
                        'num_of_days' => $bookingNights,
                        'updated_by' => Auth::user()?->username ?? 'system',
                    ]);
                }

                $warning = ($av !== null && $av < 0 && $allowOver)
                    ? 'Cảnh báo: Số phòng trống của loại phòng đã bị âm (AV = ' . $av . ').'
                    : null;

                return response()->json([
                    'success' => true,
                    'data' => $updatedRoom->load(['roomClass', 'room']),
                    'booking_dates' => [
                        'arrival_date' => $bookingArrival,
                        'departure_date' => $bookingDeparture,
                    ],
                    'message' => $warning
                        ? 'Cập nhật ngày lưu trú thành công. ' . $warning
                        : 'Cập nhật ngày lưu trú thành công!',
                    'warning' => $warning,
                ]);
            });
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Không thể cập nhật ngày lưu trú trên Room Plan.',
            ], 500);
        }
    }
}
