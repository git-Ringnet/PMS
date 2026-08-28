<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\HotelSetting;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;

class BreakfastController extends Controller
{
    /**
     * Lấy danh sách phòng ăn sáng theo khoảng ngày và loại tìm kiếm (sp_035).
     *
     * Params:
     * - from_date: YYYY-MM-DD
     * - to_date: YYYY-MM-DD
     * - date_type: 'breakfast' (mặc định) | 'arrival'
     * - show_type: 1 (có ăn sáng) | 0 (tất cả) | 2 (không ăn sáng)
     * - search: từ khóa tìm kiếm
     */
    public function list(Request $request)
    {
        $fromDateStr = $request->input('from_date');
        $toDateStr = $request->input('to_date', $fromDateStr);
        $dateType = $request->input('date_type', 'breakfast'); // 'breakfast' or 'arrival'
        $showType = (int)$request->input('show_type', 1); // 1: only breakfast, 0: all, 2: no breakfast
        $targetRoomNumber = trim((string) $request->input('room_number', ''));
        $includeReserved = $targetRoomNumber !== '' && $request->boolean('include_reserved');

        if (!$fromDateStr) {
            $fromDateStr = now()->toDateString();
        }
        if (!$toDateStr) {
            $toDateStr = $fromDateStr;
        }

        $fromDate = Carbon::parse($fromDateStr)->startOfDay();
        $toDate = Carbon::parse($toDateStr)->startOfDay();

        if ($fromDate->gt($toDate)) {
            $temp = $fromDate;
            $fromDate = $toDate;
            $toDate = $temp;
        }

        $hotelSetting = HotelSetting::first();
        $prefix = strtoupper($hotelSetting?->prefix_booking_id ?? 'GAL');

        $results = [];

        // Query các booking rooms hợp lệ (Status 0: Reservation, 1: CheckIn, 2: CheckOut, 100: Transfer - theo chuẩn sp_035)
        $roomsQuery = BookingRoom::with([
            'booking' => function ($q) {
                $q->with(['customerSource', 'company', 'registrationStatus']);
            },
            'guests.guest',
            'children.breakfastDetails'
        ])
        ->whereNull('deleted_at')
        ->whereIn('status', [0, 1, 2, 100])
        ->whereHas('booking', function ($q) {
            $q->whereNull('deleted_at')
              ->where(function ($sub) {
                  $sub->whereNull('registration_status_id')
                      ->orWhereHas('registrationStatus', function ($rs) {
                          $rs->where('bk_definite', '!=', 4);
                      });
              });
        });

        if ($dateType === 'arrival') {
            // Lọc theo ngày đến của phòng: Lấy các phòng sắp đến & đang đến (Đăng ký 0, Đang ở 1, Chuyển phòng 100)
            $rooms = (clone $roomsQuery)
                ->whereIn('status', $includeReserved
                    ? [BookingRoom::STATUS_BOOKED, BookingRoom::STATUS_CHECKED_IN, BookingRoom::STATUS_CHECKED_OUT, 100]
                    : [BookingRoom::STATUS_BOOKED, BookingRoom::STATUS_CHECKED_IN, 100])
                ->when($targetRoomNumber !== '', fn ($q) => $q->where('room_number', $targetRoomNumber))
                ->whereBetween('arrival_date', [$fromDate->toDateString(), $toDate->toDateString()])
                ->orderBy('arrival_date')
                ->orderBy('room_number')
                ->get();

            foreach ($rooms as $room) {
                $targetDateStr = Carbon::parse($room->arrival_date)->format('Y-m-d');
                $item = $this->formatRoomItem($room, $targetDateStr, $prefix);
                if ($this->filterShowType($item, $showType)) {
                    $results[] = $item;
                }
            }
        } else {
            // Lọc theo ngày ăn sáng (breakfast): CHỈ LẤY CÁC PHÒNG ĐÃ CHECK-IN / ĐANG Ở (1) hoặc ĐÃ CHECK-OUT TRONG NGÀY (2)
            // Khách chưa nhận phòng (Đăng ký 0) chưa có mặt thực tế tại khách sạn nên không nằm trong danh sách ăn sáng
            $candidateRooms = (clone $roomsQuery)
                ->whereIn('status', $includeReserved
                    ? [BookingRoom::STATUS_BOOKED, BookingRoom::STATUS_CHECKED_IN, BookingRoom::STATUS_CHECKED_OUT, 100]
                    : [BookingRoom::STATUS_CHECKED_IN, BookingRoom::STATUS_CHECKED_OUT])
                ->when($targetRoomNumber !== '', fn ($q) => $q->where('room_number', $targetRoomNumber))
                ->where('arrival_date', '<=', $toDate->toDateString())
                ->where('departure_date', '>=', $fromDate->toDateString())
                ->orderBy('room_number')
                ->get();

            $period = CarbonPeriod::create($fromDate, $toDate);

            foreach ($period as $date) {
                $dateStr = $date->toDateString();

                foreach ($candidateRooms as $room) {
                    $arrDate = Carbon::parse($room->arrival_date)->startOfDay();
                    $depDate = Carbon::parse($room->departure_date)->startOfDay();

                    // Điều kiện ăn sáng ngày D:
                    // Thường lệ: arrDate < D <= depDate (tức từ sáng hôm sau ngày đến -> sáng ngày đi)
                    // Ngoại lệ: D == arrDate và giờ đến rạng sáng <= 00:01
                    $isBreakfastDay = false;
                    if ($date->gt($arrDate) && $date->lte($depDate)) {
                        $isBreakfastDay = true;
                    } elseif ($date->eq($arrDate) && !empty($room->arrival_time) && $room->arrival_time <= '00:01') {
                        $isBreakfastDay = true;
                    }

                    if (!$isBreakfastDay) {
                        continue;
                    }

                    $item = $this->formatRoomItem($room, $dateStr, $prefix);
                    if ($this->filterShowType($item, $showType)) {
                        $results[] = $item;
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $results,
            'meta' => [
                'from_date' => $fromDate->toDateString(),
                'to_date' => $toDate->toDateString(),
                'date_type' => $dateType,
                'show_type' => $showType,
                'total_rows' => count($results),
                'total_adults' => array_sum(array_column($results, 'adults')),
                'total_children' => array_sum(array_column($results, 'children_breakfast')),
            ]
        ]);
    }

    /**
     * Format một bản ghi phòng cho bảng ăn sáng.
     */
    private function formatRoomItem($room, $targetDateStr, $prefix)
    {
        $booking = $room->booking;
        $bookingId = $booking?->id ?? 0;
        $bookingCode = $prefix . $bookingId;
        $bookingName = $booking?->booking_name ?? 'Khách lẻ';

        // Tên khách đại diện
        $mainGuestName = '';
        if ($room->guests && $room->guests->count() > 0) {
            $primary = $room->guests->firstWhere('is_primary', true) ?: $room->guests->first();
            $mainGuestName = $primary?->guest?->full_name ?? '';
        }
        if (!$mainGuestName) {
            $mainGuestName = $booking?->contact_name ?: $bookingName;
        }

        // Số người lớn
        $adultsCount = (int)($room->adults ?: 1);

        // Trẻ em ăn sáng trong ngày này
        $childrenBfCount = 0;
        if ($room->children && $room->children->count() > 0) {
            foreach ($room->children as $child) {
                if ($child->child_status === 3) continue; // bỏ trẻ đã hủy
                $hasBf = false;
                if ($child->breakfastDetails && $child->breakfastDetails->count() > 0) {
                    $detail = $child->breakfastDetails->first(function ($d) use ($targetDateStr) {
                        return Carbon::parse($d->service_date)->format('Y-m-d') === $targetDateStr;
                    });
                    if ($detail) {
                        $hasBf = (bool)$detail->breakfast;
                    } else {
                        // Nếu không có dòng đúng ngày thì kiểm tra nếu phòng có ăn sáng hoặc có bất kỳ suất ăn sáng nào
                        $hasBf = (bool)$room->breakfast || (bool)$booking?->breakfast_included || $child->breakfastDetails->contains('breakfast', 1);
                    }
                } else {
                    // Nếu không có chi tiết từng ngày, kế thừa từ phòng
                    if ((bool)$room->breakfast || (bool)$booking?->breakfast_included) {
                        $hasBf = true;
                    }
                }
                if ($hasBf) {
                    $childrenBfCount++;
                }
            }
        }

        // Có ăn sáng hay không
        $isBreakfast = (bool)$room->breakfast || (bool)$booking?->breakfast_included || ($childrenBfCount > 0);

        // Tính danh sách tất cả các ngày ăn sáng hợp lệ của phòng này
        $arr = Carbon::parse($room->arrival_date)->startOfDay();
        $dep = Carbon::parse($room->departure_date)->startOfDay();
        $allBfDates = [];
        $curr = $arr->copy()->addDay();
        while ($curr->lte($dep)) {
            $allBfDates[] = $curr->toDateString();
            $curr->addDay();
        }
        // Trường hợp check-in sớm rạng sáng
        if (!empty($room->arrival_time) && $room->arrival_time <= '00:01' && !in_array($arr->toDateString(), $allBfDates)) {
            array_unshift($allBfDates, $arr->toDateString());
        }

        $formattedArr = Carbon::parse($room->arrival_date)->format('Y-m-d');
        $formattedDep = Carbon::parse($room->departure_date)->format('Y-m-d');
        $formattedTarget = Carbon::parse($targetDateStr)->format('Y-m-d');

        return [
            'id' => $room->id . '_' . $formattedTarget,
            'rental_room_id' => $room->id,
            'booking_id' => $bookingId,
            'booking_code' => $bookingCode,
            'booking_name' => $bookingName,
            'information_bk' => $bookingCode . '-' . $bookingName,
            'room_number' => $room->room_number ?: 'Chưa gán',
            'arrival_date' => $formattedArr,
            'departure_date' => $formattedDep,
            'target_date' => $formattedTarget,
            'guest_name' => $mainGuestName,
            'adults' => $adultsCount,
            'children_breakfast' => $childrenBfCount,
            'children_qty' => (int)($room->children_qty ?: 0),
            'is_breakfast' => $isBreakfast,
            'company' => $booking?->company?->name ?? $booking?->customerSource?->name ?? '',
            'all_breakfast_dates' => $allBfDates,
        ];
    }

    /**
     * Lọc theo showType.
     */
    private function filterShowType($item, $showType)
    {
        if ($showType === 1) {
            return $item['is_breakfast'] === true;
        } elseif ($showType === 2) {
            return $item['is_breakfast'] === false;
        }
        return true;
    }
}
