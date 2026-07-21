<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\RoomClass;
use App\Models\Room;
use App\Models\RoomLock;
use App\Models\SystemDateRoll;
use App\Services\RoomAvailabilityService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AvailabilityController extends Controller
{
    public function __construct(protected RoomAvailabilityService $avService) {}

    /**
     * Get availability grid data and statistics.
     * Đã tích hợp booking_rooms (SP2100) — không còn TODO.
     */
    public function index(Request $request)
    {
        // 1. Xác định start_date và end_date
        $startDateInput = $request->input('start_date');
        $endDateInput   = $request->input('end_date');

        $startDate = $startDateInput
            ? Carbon::parse($startDateInput)->startOfDay()
            : now()->timezone('Asia/Ho_Chi_Minh')->startOfDay();

        $endDate = $endDateInput
            ? Carbon::parse($endDateInput)->endOfDay()
            : $startDate->copy()->addDays(29)->endOfDay();

        $startStr = $startDate->toDateString();
        $endStr   = $endDate->copy()->addDay()->toDateString(); // exclusive upper bound

        // Generate danh sách ngày Y-m-d
        $dates = [];
        $temp  = $startDate->copy();
        while ($temp->lte($endDate)) {
            $dates[] = $temp->toDateString();
            $temp = $temp->addDay();
        }

        // 2. Lấy room classes
        $roomClasses = RoomClass::where('is_active', true)->get();

        $roomCounts = Room::select('room_class_id', DB::raw('count(*) as total'), DB::raw('max(extra_beds_limit) as max_extra'))
            ->groupBy('room_class_id')
            ->get()
            ->keyBy('room_class_id');

        $roomClassesData = [];
        $grandTotalRooms = 0;
        $grandMaxExtraBeds = 0;

        foreach ($roomClasses as $rc) {
            $stats   = $roomCounts->get($rc->id);
            $total   = $stats ? $stats->total : 0;
            $maxExtra = $stats ? $stats->max_extra : 0;

            $roomClassesData[] = [
                'id'             => $rc->id,
                'code'           => $rc->code,
                'name'           => $rc->name,
                'total'          => $total,
                'max_extra_beds' => $maxExtra,
            ];

            $grandTotalRooms   += $total;
            $grandMaxExtraBeds += $maxExtra;
        }

        // Initialize statistics array before any calculation
        $statistics = [];
        foreach ($dates as $dStr) {
            $statistics[$dStr] = [
                'total_rooms'      => $grandTotalRooms,
                'ooo'              => 0,
                'oos'              => 0,
                'sellable'         => $grandTotalRooms,
                'bk_reserved'      => 0,  // Standard Booked count (non-allotment)
                'inhouse'          => 0,  // Standard CheckedIn count
                'total_occupied'   => 0,  // Standard occupied + allotment
                'av'               => $grandTotalRooms,
                'extra_beds'       => 0,
                'arrivals_rooms'   => 0,
                'departures_rooms' => 0,
                'bk_guaranteed'    => 0,
                'bk_nonguaranteed' => 0,
                'series'           => 0,
                'allotment'        => 0,
                'internal_rooms'   => 0,
                'free_rooms'       => 0,
                'total_guests'     => 0,
                'arrivals_pax'     => 0,
                'departures_pax'   => 0,
                'cancellations'    => 0,
                'noshow'           => 0,
                'occupied_pct'     => 0,
            ];
        }

        // 3. Tính OOO/OOS per class per date
        $locks = RoomLock::where('is_active', true)
            ->where('start_date', '<', $endStr)
            ->where('end_date', '>', $startStr)
            ->with('room.roomClass')
            ->get();

        $lockCounts = [];
        $lockRooms = []; // $lockRooms[$classCode][$dStr][$lockType][] = $room_number
        foreach ($locks as $lock) {
            if (!$lock->room || !$lock->room->roomClass) continue;
            $classCode = $lock->room->roomClass->code;
            $lockType  = strtoupper($lock->lock_type);
            $lockStart = Carbon::parse($lock->start_date)->startOfDay();
            $lockEnd   = Carbon::parse($lock->end_date)->endOfDay();

            foreach ($dates as $dStr) {
                $dateObj = Carbon::parse($dStr)->startOfDay();
                if ($dateObj->gte($lockStart) && $dateObj->lte($lockEnd)) {
                    $lockCounts[$classCode][$dStr][$lockType] = ($lockCounts[$classCode][$dStr][$lockType] ?? 0) + 1;
                    $lockRooms[$classCode][$dStr][$lockType][] = $lock->room_number;
                }
            }
        }

        // 4. Tính booking_rooms per class per date (★ tích hợp SP2100)
        // Lấy tất cả các booking room có status Booked (0), CheckedIn (1), CheckedOut (2), Cancelled (3)
        // Chỉ lấy các booking có SP1311.IsAvailability = 1
        $bookings = BookingRoom::whereIn('status', [
                BookingRoom::STATUS_BOOKED,
                BookingRoom::STATUS_CHECKED_IN,
                BookingRoom::STATUS_CHECKED_OUT,
                BookingRoom::STATUS_CANCELLED,
            ])
            ->where('arrival_date', '<', $endStr)
            ->where('departure_date', '>', $startStr)
            ->whereHas('booking.registrationStatus', function ($query) {
                $query->where('is_availability', 1);
            })
            ->with([
                'roomClass:id,code',
                'booking.registrationStatus',
                'booking.paymentMethod',
                'booking.company'
            ])
            ->get();

        // Tính booked/inhouse count per class per date
        $bookedCounts    = []; // $bookedCounts[classCode][dateStr] = count
        $inhouseCounts   = [];
        $allotmentCounts = []; // Allotment counts
        $arrivalCounts   = []; // Arrivals: arrival_date = dStr
        $departureCounts = [];
        $extraBedCounts  = [];

        $bookedRooms     = []; // $bookedRooms[classCode][dateStr][] = room_number
        $inhouseRooms    = []; // $inhouseRooms[classCode][dateStr][] = room_number
        $allotmentRooms  = []; // $allotmentRooms[classCode][dateStr][] = room_number

        foreach ($bookings as $br) {
            if (!$br->roomClass) continue;
            $classCode = $br->roomClass->code;
            $arrDate   = $br->arrival_date->toDateString();
            $depDate   = $br->departure_date->toDateString();

            $parentBooking      = $br->booking;
            $isRoomCancelled    = ($br->status === BookingRoom::STATUS_CANCELLED);
            $isBookingCancelled = ($parentBooking && $parentBooking->status === Booking::STATUS_DELETED);
            $isBookingNoShow    = ($parentBooking && $parentBooking->status === Booking::STATUS_NO_SHOW);
            
            $isCancelled        = $isRoomCancelled || $isBookingCancelled;
            $isNoShow           = $isBookingNoShow;
            
            // Chỉ lấy các booking có is_availability = 1
            $isAvailableStatus  = $parentBooking && $parentBooking->registrationStatus && ($parentBooking->registrationStatus->is_availability == 1);

            $isBooked          = ($br->status === BookingRoom::STATUS_BOOKED && !$isCancelled && !$isNoShow && $isAvailableStatus);
            $isCheckedIn       = ($br->status === BookingRoom::STATUS_CHECKED_IN && !$isCancelled && !$isNoShow && $isAvailableStatus);
            $isCheckedOut      = ($br->status === BookingRoom::STATUS_CHECKED_OUT && !$isCancelled && !$isNoShow && $isAvailableStatus);
            $isOccupied        = $isBooked || $isCheckedIn || $isCheckedOut;

            $regStatusName     = $parentBooking && $parentBooking->registrationStatus ? strtolower($parentBooking->registrationStatus->name) : '';
            $isAllotment       = str_contains($regStatusName, 'allotment');
            $isGIT             = $parentBooking && $parentBooking->is_git;

            $isHU = $parentBooking && (
                stripos($parentBooking->booking_name, 'House Use') !== false || 
                stripos($parentBooking->booking_name, 'HU') !== false || 
                ($parentBooking->company && strtolower($parentBooking->company->rate_code) === 'hu')
            );
            
            $isFree = $parentBooking && (
                ($parentBooking->paymentMethod && ($parentBooking->paymentMethod->is_free || strtolower($parentBooking->paymentMethod->code) === 'cl')) || 
                stripos($parentBooking->booking_name, 'FOC') !== false || 
                stripos($parentBooking->booking_name, 'Complimentary') !== false
            );

            // Dayuse booking rule
            $isDayUse = ($arrDate === $depDate && $parentBooking && $parentBooking->is_day_use);

            foreach ($dates as $dStr) {
                $isMatchDate = $isDayUse ? ($dStr === $arrDate) : ($dStr >= $arrDate && $dStr < $depDate);
                
                if ($isMatchDate) {
                    if ($isOccupied) {
                        $roomNum = $br->room_number;
                        if ($isAllotment) {
                            $allotmentCounts[$classCode][$dStr] = ($allotmentCounts[$classCode][$dStr] ?? 0) + 1;
                            if ($roomNum) {
                                $allotmentRooms[$classCode][$dStr][] = $roomNum;
                            }
                        } else {
                            if ($isBooked) {
                                $bookedCounts[$classCode][$dStr] = ($bookedCounts[$classCode][$dStr] ?? 0) + 1;
                                if ($roomNum) {
                                    $bookedRooms[$classCode][$dStr][] = $roomNum;
                                }
                            } elseif ($isCheckedIn || $isCheckedOut) {
                                $inhouseCounts[$classCode][$dStr] = ($inhouseCounts[$classCode][$dStr] ?? 0) + 1;
                                if ($roomNum) {
                                    $inhouseRooms[$classCode][$dStr][] = $roomNum;
                                }
                            }
                        }

                        if ($br->extra_bed_qty > 0) {
                            $extraBedCounts[$classCode][$dStr] = ($extraBedCounts[$classCode][$dStr] ?? 0) + $br->extra_bed_qty;
                        }

                        // Advanced statistics counts
                        if ($isHU) {
                            $statistics[$dStr]['internal_rooms']++;
                        } elseif ($isFree) {
                            $statistics[$dStr]['free_rooms']++;
                        }

                        if ($isAllotment) {
                            $statistics[$dStr]['allotment']++;
                        } else {
                            if (str_contains($regStatusName, 'none') || str_contains($regStatusName, 'non')) {
                                $statistics[$dStr]['bk_nonguaranteed']++;
                            } else {
                                $statistics[$dStr]['bk_guaranteed']++;
                            }
                        }

                        if ($isGIT) {
                            $statistics[$dStr]['series']++;
                        }

                        $statistics[$dStr]['total_guests'] += $br->adults;
                    }
                }

                // Event-based statistics
                if (!$isCancelled && !$isNoShow && $isAvailableStatus) {
                    if ($dStr === $arrDate) {
                        $statistics[$dStr]['arrivals_pax'] += $br->adults;
                    }
                    if ($dStr === $depDate) {
                        $statistics[$dStr]['departures_pax'] += $br->adults;
                    }
                }

                if ($isCancelled && $dStr === $arrDate) {
                    $statistics[$dStr]['cancellations']++;
                }

                if ($isNoShow && $dStr === $arrDate) {
                    $statistics[$dStr]['noshow']++;
                }
            }

            // Grid arrival/departure counts
            if (!$isCancelled && !$isNoShow && $isAvailableStatus) {
                if (in_array($arrDate, $dates)) {
                    $arrivalCounts[$classCode][$arrDate] = ($arrivalCounts[$classCode][$arrDate] ?? 0) + 1;
                }
                if (in_array($depDate, $dates)) {
                    $departureCounts[$classCode][$depDate] = ($departureCounts[$classCode][$depDate] ?? 0) + 1;
                }
            }
        }

        // Lấy tất cả phòng của mỗi loại phòng
        $roomsByClass = Room::select('room_number', 'room_class_id')->get()->groupBy('room_class_id');

        // 5. Build grid và statistics
        $grid = [];

        foreach ($roomClassesData as $rc) {
            $rcId  = $rc['id'];
            $code  = $rc['code'];
            $total = $rc['total'];
            $grid[$code] = [];

            // Lấy danh sách tất cả số phòng thuộc loại phòng này
            $allClassRoomNumbers = $roomsByClass->get($rcId)?->pluck('room_number')->toArray() ?? [];

            foreach ($dates as $dStr) {
                $oooCount     = $lockCounts[$code][$dStr]['OOO'] ?? 0;
                $oosCount     = $lockCounts[$code][$dStr]['OOS'] ?? 0;
                $bkCount      = $bookedCounts[$code][$dStr] ?? 0;
                $inhouseCount = $inhouseCounts[$code][$dStr] ?? 0;
                $occupied     = $bkCount + $inhouseCount; // standard non-allotment occupied
                $ebCount      = $extraBedCounts[$code][$dStr] ?? 0;
                $arrivals     = $arrivalCounts[$code][$dStr] ?? 0;
                $departures   = $departureCounts[$code][$dStr] ?? 0;
                $almCount     = $allotmentCounts[$code][$dStr] ?? 0;

                $oooRooms = array_values(array_unique(array_filter($lockRooms[$code][$dStr]['OOO'] ?? [])));
                $oosRooms = array_values(array_unique(array_filter($lockRooms[$code][$dStr]['OOS'] ?? [])));
                $occRooms = array_merge(
                    $bookedRooms[$code][$dStr] ?? [],
                    $inhouseRooms[$code][$dStr] ?? [],
                    $allotmentRooms[$code][$dStr] ?? []
                );
                $occRooms = array_values(array_unique(array_filter($occRooms)));

                $unavailableRooms = array_merge($oooRooms, $oosRooms, $occRooms);
                $avRooms          = array_values(array_diff($allClassRoomNumbers, $unavailableRooms));

                $sellable = max(0, $total - $oooCount - $oosCount);
                $av       = max(0, $sellable - $occupied - $almCount);

                $grid[$code][$dStr] = [
                    'av'         => $av,
                    'av_rooms'   => $avRooms,
                    'ooo'        => $oooCount,
                    'ooo_rooms'  => $oooRooms,
                    'oos'        => $oosCount,
                    'oos_rooms'  => $oosRooms,
                    'bk'         => $bkCount,    // Reserved (chưa check-in)
                    'occ'        => $occupied,   // total non-allotment occupied
                    'total_occ'  => $occupied,
                    'occ_rooms'  => $occRooms,
                    'eb'         => $ebCount,
                    'arr'        => $arrivals,
                    'dep'        => $departures,
                    'sellable'   => $sellable,
                    'alm'        => $almCount,
                ];

                // Tổng hợp vào statistics
                $statistics[$dStr]['ooo']              += $oooCount;
                $statistics[$dStr]['oos']              += $oosCount;
                $statistics[$dStr]['bk_reserved']      += $bkCount;
                $statistics[$dStr]['inhouse']          += $inhouseCount;
                $statistics[$dStr]['total_occupied']   += ($occupied + $almCount);
                $statistics[$dStr]['extra_beds']       += $ebCount;
                $statistics[$dStr]['arrivals_rooms']   += $arrivals;
                $statistics[$dStr]['departures_rooms'] += $departures;

                // Thu thập danh sách mã phòng cho thống kê ngày tổng
                if (!isset($statistics[$dStr]['av_rooms']))  $statistics[$dStr]['av_rooms'] = [];
                if (!isset($statistics[$dStr]['ooo_rooms'])) $statistics[$dStr]['ooo_rooms'] = [];
                if (!isset($statistics[$dStr]['oos_rooms'])) $statistics[$dStr]['oos_rooms'] = [];
                if (!isset($statistics[$dStr]['occ_rooms'])) $statistics[$dStr]['occ_rooms'] = [];

                $statistics[$dStr]['av_rooms']  = array_merge($statistics[$dStr]['av_rooms'], $avRooms);
                $statistics[$dStr]['ooo_rooms'] = array_merge($statistics[$dStr]['ooo_rooms'], $oooRooms);
                $statistics[$dStr]['oos_rooms'] = array_merge($statistics[$dStr]['oos_rooms'], $oosRooms);
                $statistics[$dStr]['occ_rooms'] = array_merge($statistics[$dStr]['occ_rooms'], $occRooms);
            }
        }

        // Tính lại sellable, av tổng và occupied_pct
        foreach ($dates as $dStr) {
            $totalOOO = $statistics[$dStr]['ooo'];
            $totalOOS = $statistics[$dStr]['oos'];
            $occ      = $statistics[$dStr]['total_occupied'];
            $sellable = max(0, $grandTotalRooms - $totalOOO - $totalOOS);
            $av       = max(0, $sellable - $occ);

            $statistics[$dStr]['sellable'] = $sellable;
            $statistics[$dStr]['av']       = $av;
            $statistics[$dStr]['occupied_pct'] = $sellable > 0 ? round(($occ / $sellable) * 100) : 0;

            // Dọn dẹp/unique mảng mã phòng của statistics
            $statistics[$dStr]['av_rooms']  = array_values(array_unique(array_filter($statistics[$dStr]['av_rooms'])));
            $statistics[$dStr]['ooo_rooms'] = array_values(array_unique(array_filter($statistics[$dStr]['ooo_rooms'])));
            $statistics[$dStr]['oos_rooms'] = array_values(array_unique(array_filter($statistics[$dStr]['oos_rooms'])));
            $statistics[$dStr]['occ_rooms'] = array_values(array_unique(array_filter($statistics[$dStr]['occ_rooms'])));
        }

        // Tính SL Phòng Tối Đa cho mỗi room class và totals
        $grandMaxRooms = 0;
        foreach ($roomClassesData as &$rc) {
            $code  = $rc['code'];
            $minAv = null;
            foreach ($dates as $dStr) {
                $av = $grid[$code][$dStr]['av'] ?? 0;
                if ($minAv === null || $av < $minAv) {
                    $minAv = $av;
                }
            }
            $rc['max_rooms'] = $minAv ?? 0;
            $grandMaxRooms += $rc['max_rooms'];
        }
        unset($rc);

        return response()->json([
            'success'      => true,
            'start_date'   => $startDate->toDateString(),
            'end_date'     => $endDate->toDateString(),
            'room_classes' => $roomClassesData,
            'dates'        => $dates,
            'grid'         => $grid,
            'statistics'   => $statistics,
            'totals'       => [
                'grand_total'          => $grandTotalRooms,
                'grand_max_rooms'      => $grandMaxRooms,
                'grand_max_extra_beds' => $grandMaxExtraBeds,
            ],
        ]);
    }

    /**
     * API kiểm tra AV nhanh cho một loại phòng & khoảng ngày.
     * GET /availability/check?room_class_id=1&arrival_date=2026-07-10&departure_date=2026-07-12
     */
    public function check(Request $request)
    {
        $request->validate([
            'room_class_id' => 'required|exists:room_classes,id',
            'arrival_date'  => 'required|date',
            'departure_date'=> 'required|date|after:arrival_date',
        ]);

        $av = $this->avService->getAvailability(
            $request->room_class_id,
            $request->arrival_date,
            $request->departure_date,
            $request->exclude_booking_room_id
        );

        return response()->json([
            'success'       => true,
            'av'            => $av,
            'is_available'  => $av > 0,
        ]);
    }
}
