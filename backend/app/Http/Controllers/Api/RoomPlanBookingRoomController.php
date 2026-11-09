<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\HotelConfig;
use App\Services\ActivityLogService;
use App\Services\BookingRoomLifecycleService;
use App\Support\ActivityScreen;
use App\Support\BookingLogText;
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

                    // Chỉ các đêm MỚI thêm vào cần thêm 1 phòng; rút ngắn / giữ nguyên không bị over.
                    $av = $this->availability->getAvailabilityAfterAddingNights(
                        (int) $bookingRoom->room_class_id,
                        $arrivalDate,
                        $departureDate,
                        $currentArrival,
                        $currentDeparture,
                        $bookingRoom->id,
                    );

                    if ($av !== null && $av < 0 && !$allowOver) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Không đủ phòng trống sau khi cập nhật. (AV = ' . $av . ')',
                            'av' => $av,
                        ], 422);
                    }

                    if ($av !== null && $av < 0 && empty($validated['confirm_overbooking'])) {
                        return response()->json([
                            'success' => false,
                            'code' => 'overbooking_confirmation_required',
                            'requires_confirmation' => true,
                            'message' => 'Loại phòng đang bị over (AV = ' . $av . '), bạn có muốn tiếp tục?',
                            'av' => $av,
                        ], 409);
                    }
                }

                $logBookingBefore = $booking->getRawOriginal();

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
                    $datesChanged,
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

                $this->logStayChange($booking, $bookingRoom, $logBookingBefore, $currentArrival, $currentDeparture, $arrivalDate, $departureDate);

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

    /**
     * Update multiple booked rooms of one booking from Room Plan in one atomic action.
     * This intentionally remains separate from BookingRoomController::bulkUpdate:
     * Room Plan may only alter stay dates of booked, physical, non-day-use rooms.
     */
    public function bulkUpdateStay(Request $request, $bookingId)
    {
        $validated = $request->validate([
            'stays' => 'required|array|min:2',
            'stays.*.room_id' => 'required|string',
            'stays.*.arrival_date' => 'required|date_format:Y-m-d',
            'stays.*.departure_date' => 'required|date_format:Y-m-d',
            'confirm_overbooking' => 'sometimes|boolean',
        ]);

        try {
            return DB::transaction(function () use ($validated, $bookingId) {
                $booking = Booking::query()
                    ->with('registrationStatus')
                    ->lockForUpdate()
                    ->findOrFail($bookingId);
                $requestedIds = collect($validated['stays'])->pluck('room_id')->map(fn ($id) => (string) $id);

                if ($requestedIds->unique()->count() !== $requestedIds->count()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Danh sách phòng cần cập nhật bị trùng.',
                    ], 422);
                }

                $rooms = BookingRoom::query()
                    ->where('booking_id', $booking->id)
                    ->whereIn('id', $requestedIds->all())
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy(fn (BookingRoom $room) => (string) $room->id);

                if ($rooms->count() !== $requestedIds->count()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Một hoặc nhiều phòng không thuộc đăng ký này.',
                    ], 422);
                }

                $allowChangeArrival = (string) HotelConfig::query()
                    ->where('name', 'RoomPlan_AllowChangeArrivalDate')
                    ->value('value') === '1';
                $allowOver = (string) HotelConfig::query()
                    ->where('name', 'AllowOverRoomTypeRoomKind')
                    ->value('value') === '1';
                $systemDate = $this->availability->getSystemDate()->toDateString();
                $plans = [];

                foreach ($validated['stays'] as $stay) {
                    /** @var BookingRoom $bookingRoom */
                    $bookingRoom = $rooms->get((string) $stay['room_id']);
                    $bookingRoom->setRelation('booking', $booking);
                    $arrivalDate = $stay['arrival_date'];
                    $departureDate = $stay['departure_date'];
                    $currentArrival = $bookingRoom->arrival_date->toDateString();
                    $currentDeparture = $bookingRoom->departure_date->toDateString();

                    if ($bookingRoom->isVirtual()) {
                        return response()->json(['success' => false, 'message' => 'Không thể chỉnh ngày lưu trú trên phòng ảo.'], 422);
                    }
                    if ((int) $bookingRoom->status !== BookingRoom::STATUS_BOOKED) {
                        return response()->json(['success' => false, 'message' => 'Chỉ cho phép thay đổi ngày của phòng đang đặt trước trên Room Plan.'], 422);
                    }
                    if ($bookingRoom->is_do_not_move) {
                        return response()->json(['success' => false, 'message' => 'Phòng này đang bị khóa chuyển phòng. Vui lòng mở khóa trước.'], 422);
                    }
                    if ($bookingRoom->is_day_use) {
                        return response()->json(['success' => false, 'message' => 'Không thể kéo thay đổi ngày của phòng ở theo giờ.'], 422);
                    }
                    if ($departureDate <= $arrivalDate) {
                        return response()->json(['success' => false, 'message' => 'Ngày đi phải sau ngày đến.'], 422);
                    }
                    if ($arrivalDate !== $currentArrival && !$allowChangeArrival) {
                        return response()->json(['success' => false, 'message' => 'Không cho phép thay đổi ngày đến trên Room Plan (RoomPlan_AllowChangeArrivalDate đang tắt).'], 422);
                    }
                    if ($arrivalDate !== $currentArrival && $arrivalDate < $systemDate) {
                        return response()->json(['success' => false, 'message' => 'Ngày đến không được trước ngày hệ thống.'], 422);
                    }
                    if (($arrivalDate !== $currentArrival || $departureDate !== $currentDeparture)
                        && !empty($bookingRoom->room_number)
                        && $this->availability->isRoomNumberOccupied(
                            $bookingRoom->room_number,
                            $arrivalDate,
                            $departureDate,
                            $bookingRoom->id,
                            $booking->id,
                            $bookingRoom->arrival_time ? Carbon::parse($bookingRoom->arrival_time)->format('H:i:s') : null,
                            $bookingRoom->departure_time ? Carbon::parse($bookingRoom->departure_time)->format('H:i:s') : null,
                        )) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Số phòng ' . $bookingRoom->room_number . ' đã được gán cho booking khác trong cùng khoảng thời gian.',
                        ], 422);
                    }

                    $plans[] = compact('bookingRoom', 'arrivalDate', 'departureDate', 'currentArrival', 'currentDeparture');
                }

                // Project AV by removing the selected rooms' old nights then adding
                // their requested nights. This prevents two rooms in one batch from
                // both passing an AV check that only has capacity for one.
                $nightsByRoomClass = [];
                foreach ($plans as $plan) {
                    foreach (array_merge(
                        $this->stayNights($plan['currentArrival'], $plan['currentDeparture']),
                        $this->stayNights($plan['arrivalDate'], $plan['departureDate']),
                    ) as $night) {
                        $nightsByRoomClass[(int) $plan['bookingRoom']->room_class_id][$night] = true;
                    }
                }

                $minimumAv = null;
                $countsForAvailability = (bool) ($booking->registrationStatus?->is_availability ?? false);
                foreach ($nightsByRoomClass as $roomClassId => $nights) {
                    foreach (array_keys($nights) as $night) {
                        $nextNight = Carbon::parse($night)->addDay()->toDateString();
                        $baselineAv = $this->availability->getAvailability($roomClassId, $night, $nextNight);
                        $oldCount = 0;
                        $newCount = 0;

                        if ($countsForAvailability) {
                            foreach ($plans as $plan) {
                                if ((int) $plan['bookingRoom']->room_class_id !== (int) $roomClassId) {
                                    continue;
                                }
                                if (in_array($night, $this->stayNights($plan['currentArrival'], $plan['currentDeparture']), true)) {
                                    $oldCount++;
                                }
                                if (in_array($night, $this->stayNights($plan['arrivalDate'], $plan['departureDate']), true)) {
                                    $newCount++;
                                }
                            }
                        }

                        $projectedAv = $baselineAv + $oldCount - $newCount;
                        $minimumAv = $minimumAv === null ? $projectedAv : min($minimumAv, $projectedAv);
                    }
                }

                if ($minimumAv !== null && $minimumAv < 0 && !$allowOver) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Không đủ phòng trống sau khi cập nhật. (AV = ' . $minimumAv . ')',
                        'av' => $minimumAv,
                    ], 422);
                }
                if ($minimumAv !== null && $minimumAv < 0 && empty($validated['confirm_overbooking'])) {
                    return response()->json([
                        'success' => false,
                        'code' => 'overbooking_confirmation_required',
                        'requires_confirmation' => true,
                        'message' => 'Loại phòng đang bị over (AV = ' . $minimumAv . '), bạn có muốn tiếp tục?',
                        'av' => $minimumAv,
                    ], 409);
                }

                $logBookingBefore = $booking->getRawOriginal();
                $updatedRooms = [];
                foreach ($plans as $plan) {
                    /** @var BookingRoom $bookingRoom */
                    $bookingRoom = $plan['bookingRoom'];
                    $bookingRoom->update([
                        'arrival_date' => $plan['arrivalDate'],
                        'departure_date' => $plan['departureDate'],
                        'updated_by' => Auth::user()?->username ?? 'system',
                    ]);
                    $updatedRoom = $bookingRoom->fresh();
                    $this->bookingRoomLifecycleService->synchronize($updatedRoom, false, true, true);
                    $updatedRooms[] = $updatedRoom;
                }

                $activeRooms = BookingRoom::query()->where('booking_id', $booking->id)->active()
                    ->get(['arrival_date', 'departure_date']);
                $bookingArrival = $activeRooms->min(fn (BookingRoom $room) => Carbon::parse($room->arrival_date)->toDateString())
                    ?? $booking->arrival_date->toDateString();
                $bookingDeparture = $activeRooms->max(fn (BookingRoom $room) => Carbon::parse($room->departure_date)->toDateString())
                    ?? $booking->departure_date->toDateString();
                $bookingNights = $booking->is_day_use ? 0 : Carbon::parse($bookingArrival)->diffInDays(Carbon::parse($bookingDeparture));
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

                foreach ($plans as $index => $plan) {
                    $this->logStayChange(
                        $booking,
                        $updatedRooms[$index],
                        $index === 0 ? $logBookingBefore : $booking->getRawOriginal(),
                        $plan['currentArrival'],
                        $plan['currentDeparture'],
                        $plan['arrivalDate'],
                        $plan['departureDate'],
                    );
                }

                $warning = $minimumAv !== null && $minimumAv < 0 && $allowOver
                    ? 'Cảnh báo: Số phòng trống của loại phòng đã bị âm (AV = ' . $minimumAv . ').'
                    : null;

                return response()->json([
                    'success' => true,
                    'data' => collect($updatedRooms)->map(fn (BookingRoom $room) => $room->load(['roomClass', 'room']))->values(),
                    'booking_dates' => ['arrival_date' => $bookingArrival, 'departure_date' => $bookingDeparture],
                    'message' => $warning ? 'Cập nhật ngày lưu trú thành công. ' . $warning : 'Cập nhật ngày lưu trú thành công!',
                    'warning' => $warning,
                ]);
            });
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['success' => false, 'message' => 'Không thể cập nhật ngày lưu trú trên Room Plan.'], 500);
        }
    }

    /** @return list<string> */
    private function stayNights(string $arrivalDate, string $departureDate): array
    {
        $night = Carbon::parse($arrivalDate)->startOfDay();
        $departure = Carbon::parse($departureDate)->startOfDay();
        $nights = [];
        while ($night->lt($departure) && count($nights) < 400) {
            $nights[] = $night->toDateString();
            $night->addDay();
        }

        return $nights;
    }

    /**
     * Dòng 7, 8 sheet GHI LOG (màn hình RoomSchedule):
     *  - "* Cập Nhật Thông Tin Đăng Ký GAL11 : -Ngày Đi: .. -> .. -Số Ngày: 4 -> 6" chỉ khi ngày của đăng ký đổi theo
     *  - "* Cập nhật ngày đi 08-09-2026 -> 11-09-2026 cho các phòng: G0000021"
     */
    private function logStayChange($booking, BookingRoom $room, array $bookingBefore, string $oldArr, string $oldDep, string $newArr, string $newDep): void
    {
        try {
            $code = ActivityLogService::bookingCode($booking->id);
            $opts = ['force_screen' => ActivityScreen::ROOM_SCHEDULE, 'booking_code' => $code, 'target_label' => $code];

            $diff = BookingLogText::bookingDiff($bookingBefore, $booking->fresh()?->getRawOriginal() ?? []);
            if ($diff) {
                ActivityLogService::record('Modify', "* Cập Nhật Thông Tin Đăng Ký {$code} : " . implode(' ', $diff), $opts + ['target_type' => 'Booking', 'target_id' => $booking->id]);
            }

            $parts = [];
            if ($oldArr !== $newArr) {
                $parts[] = 'ngày đến ' . ActivityLogService::d($oldArr) . ' -> ' . ActivityLogService::d($newArr);
            }
            if ($oldDep !== $newDep) {
                $parts[] = 'ngày đi ' . ActivityLogService::d($oldDep) . ' -> ' . ActivityLogService::d($newDep);
            }
            if ($parts) {
                ActivityLogService::record('Modify', '* Cập nhật ' . implode(', ', $parts) . " cho các phòng: {$room->id}", $opts + [
                    'room_code' => $room->id,
                    'target_type' => 'BookingRoom',
                    'target_id' => $room->id,
                ]);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
