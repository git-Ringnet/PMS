<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingRoomGuest;
use App\Models\BookingRoomService;
use App\Models\Guest;
use App\Models\LateCheckin;
use App\Models\HotelConfig;
use App\Models\HotelSetting;
use App\Models\NoshowLog;
use App\Models\Payment;
use App\Models\ServiceBill;
use App\Models\RoomClass;
use App\Models\SystemDateRoll;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShiftWorkController extends Controller
{
    /**
     * Helper lấy prefix booking ID.
     */
    private function getPrefix(): string
    {
        $setting = HotelSetting::first();
        return strtoupper($setting?->prefix_booking_id ?? 'GAL');
    }

    /**
     * Helper lấy ngày hệ thống hiện tại từ SystemDateRoll hoặc HotelConfig.
     */
    private function getSystemDate(): string
    {
        $latest = SystemDateRoll::latest('id')->first();
        if ($latest && $latest->system_date) {
            return Carbon::parse($latest->system_date)->toDateString();
        }

        $cfg = HotelConfig::where('name', 'SystemDate')->first();
        if ($cfg && $cfg->value) {
            return Carbon::parse($cfg->value)->toDateString();
        }

        return now()->timezone('Asia/Ho_Chi_Minh')->toDateString();
    }

    /**
     * Tab 1: Danh sách phòng đến (Arrivals - sp_143 & sp_147).
     */
    /** Dòng 183: phòng đã chuyển (status 100) chuyển cùng ngày có 0 đêm, không được đổi 0 thành 1. */
    private function roomNights($room): int
    {
        if ((int) $room->status === \App\Models\BookingRoom::STATUS_MOVED && $room->ActutalNumOfDays !== null) {
            return max(0, (int) $room->ActutalNumOfDays);
        }

        return (int) ($room->ActutalNumOfDays ?: ($room->actual_num_of_days ?: ($room->num_of_days ?: 1)));
    }

    public function arrivals(Request $request)
    {
        $dateStr = $request->input('date', $this->getSystemDate());
        $statusFilter = $request->input('status', 'not_checked_in'); // 'not_checked_in' (0), 'checked_in' (1), 'all' (0,1,100)
        $search = trim($request->input('search', ''));

        $prefix = $this->getPrefix();
        $targetDate = Carbon::parse($dateStr)->toDateString();

        $query = BookingRoom::with([
            'booking' => function ($q) {
                $q->with(['customerSource', 'company', 'registrationStatus']);
            },
            'roomClass',
            'room.roomClass',
            'specialRequests.specialRequest',
            'guests.guest'
        ])
        ->whereNull('deleted_at')
        ->whereDate('arrival_date', $targetDate);

        // Filter status
        if ($statusFilter === 'not_checked_in' || $statusFilter === '0') {
            $query->whereIn('status', [BookingRoom::STATUS_BOOKED, 0]);
        } elseif ($statusFilter === 'checked_in' || $statusFilter === '1') {
            $query->where('status', BookingRoom::STATUS_CHECKED_IN);
        } else {
            $query->whereIn('status', [BookingRoom::STATUS_BOOKED, BookingRoom::STATUS_CHECKED_IN, 100]);
        }

        // Exclude cancelled bookings (definite 4)
        $query->whereHas('booking', function ($q) {
            $q->whereNull('deleted_at')
              ->where(function ($sub) {
                  $sub->whereNull('registration_status_id')
                      ->orWhereHas('registrationStatus', function ($rs) {
                          $rs->where('bk_definite', '!=', 4);
                      });
              });
        });

        $rooms = $query->orderBy('booking_id', 'asc')
            ->orderBy('room_number', 'asc')
            ->get();

        // Group by booking
        $grouped = [];
        foreach ($rooms as $room) {
            $bk = $room->booking;
            if (!$bk) continue;

            $bkId = $bk->id;
            $bkCode = $prefix . $bkId;

            if (!isset($grouped[$bkId])) {
                // Tính tiền cọc của booking
                $deposit = (float) Payment::where('booking_id', $bkId)
                    ->where('pack2', Payment::PACK2_DEPOSIT)
                    ->where(function ($q) {
                        $q->where('edit_flag', 0)->orWhereNull('edit_flag');
                    })
                    ->whereNull('deleted_at')
                    ->sum('amount');

                // Tính tổng tiền booking
                $totalAmount = (float) ($bk->payment_value ?: $bk->bookingRooms()->sum('total_rate'));
                if ($totalAmount <= 0) {
                    $totalAmount = (float) $bk->bookingRooms->sum(function ($r) {
                        return ($r->rate ?: 0) * ($r->actual_num_of_days ?: ($r->num_of_days ?: 1));
                    });
                }

                // Tổng đêm phòng của booking
                $totalNights = (int) ($bk->num_of_days ?: ($room->ActutalNumOfDays ?: 1));

                // Ghi chú booking
                $noteText = $bk->note ? 'Ghi chú: ' . $bk->note : '';

                $grouped[$bkId] = [
                    'id' => $bkCode,
                    'bookingId' => $bkId,
                    'bookingName' => $bk->booking_name ?: 'Khách lẻ',
                    'status' => $bk->registrationStatus?->name ?? 'Guaranteed',
                    'roomNight' => $totalNights,
                    'roomsCount' => 0,
                    'arrivalDate' => Carbon::parse($bk->arrival_date ?: $room->arrival_date)->format('d/m/Y'),
                    'departureDate' => Carbon::parse($bk->departure_date ?: $room->departure_date)->format('d/m/Y'),
                    'notes' => $noteText,
                    'deposit' => $deposit,
                    'totalAmount' => $totalAmount,
                    'rooms' => []
                ];
            }

            // Special requests
            $spList = [];
            if ($room->specialRequests && $room->specialRequests->count() > 0) {
                foreach ($room->specialRequests as $sp) {
                    if ($sp->specialRequest?->name) {
                        $spList[] = $sp->specialRequest->name;
                    } elseif ($sp->note) {
                        $spList[] = $sp->note;
                    }
                }
            }
            $specialRequestStr = implode(', ', $spList);

            $nights = $this->roomNights($room);
            $rate = (float) ($room->rate ?: 0);
            $roomTotal = (float) ($room->total_rate ?: ($rate * $nights));

            // Room Type name
            $roomTypeName = $room->roomClass?->name 
                ?: ($room->room?->roomClass?->name 
                ?: ($room->room_type ?: ''));

            // Status label
            $statusLabel = $bk->registrationStatus?->name ?? 'Guaranteed';

            $grouped[$bkId]['roomsCount']++;
            $grouped[$bkId]['rooms'][] = [
                'id' => $room->id,
                'bookingCode' => $bkCode,
                'bookingName' => $bk->booking_name ?: 'Khách lẻ',
                'status' => $statusLabel,
                'roomType' => $roomTypeName,
                'roomNumber' => $room->room_number ?: '',
                'arrivalDate' => Carbon::parse($room->arrival_date)->format('d/m/Y'),
                'departureDate' => Carbon::parse($room->departure_date)->format('d/m/Y'),
                'nights' => $nights,
                'adults' => (int) ($room->adults ?: 1),
                'children' => (int) ($room->children_qty ?: 0),
                'price' => $rate,
                'rateCode' => $this->cleanRateCode($room->rate_code),
                'roomTotal' => $roomTotal,
                'specialRequest' => $specialRequestStr,
                'company' => $bk->company?->name ?: ($bk->customerSource?->name ?: '')
            ];
        }

        // Flatten to list of booking groups
        $result = array_values($grouped);

        // Apply search filter if present
        if ($search !== '') {
            $sLower = mb_strtolower($search);
            $result = array_values(array_filter($result, function ($bkItem) use ($sLower) {
                if (str_contains(mb_strtolower($bkItem['id']), $sLower)) return true;
                if (str_contains(mb_strtolower($bkItem['bookingName']), $sLower)) return true;
                foreach ($bkItem['rooms'] as $rm) {
                    if (str_contains(mb_strtolower($rm['roomNumber']), $sLower)) return true;
                    if (str_contains(mb_strtolower($rm['roomType']), $sLower)) return true;
                    if (str_contains(mb_strtolower($rm['company']), $sLower)) return true;
                }
                return false;
            }));
        }

        $totalRegistrations = count($result);
        $totalRooms = array_sum(array_column($result, 'roomsCount'));

        return response()->json([
            'success' => true,
            'data' => $result,
            'meta' => [
                'date' => $targetDate,
                'status' => $statusFilter,
                'totalRegistrations' => $totalRegistrations,
                'totalRooms' => $totalRooms
            ]
        ]);
    }

    /**
     * Tab 2: Danh sách phòng đi (Departures - sp_143).
     */
    public function departures(Request $request)
    {
        $dateStr = $request->input('date', $this->getSystemDate());
        $statusFilter = $request->input('status', 'not_checked_out'); // 'not_checked_out' (1), 'checked_out' (2), 'all' (1,2)
        $search = trim($request->input('search', ''));

        $prefix = $this->getPrefix();
        $targetDate = Carbon::parse($dateStr)->toDateString();

        $query = BookingRoom::with([
            'booking' => function ($q) {
                $q->with(['customerSource', 'company', 'registrationStatus']);
            },
            'roomClass',
            'room.roomClass',
            'specialRequests.specialRequest',
            'services' => function ($q) {
                $q->whereNull('deleted_at');
            }
        ])
        ->whereNull('deleted_at')
        ->whereDate('departure_date', $targetDate);

        // Filter status
        if ($statusFilter === 'not_checked_out' || $statusFilter === '1') {
            $query->where('status', BookingRoom::STATUS_CHECKED_IN);
        } elseif ($statusFilter === 'checked_out' || $statusFilter === '2') {
            $query->where('status', BookingRoom::STATUS_CHECKED_OUT);
        } else {
            $query->whereIn('status', [BookingRoom::STATUS_CHECKED_IN, BookingRoom::STATUS_CHECKED_OUT]);
        }

        $rooms = $query->orderBy('booking_id', 'asc')
            ->orderBy('room_number', 'asc')
            ->get();

        $bookingBills = $this->loadBookingServiceBills($rooms->pluck('booking_id')->unique()->all());

        $grouped = [];
        foreach ($rooms as $room) {
            $bk = $room->booking;
            if (!$bk) continue;

            $bkId = $bk->id;
            $bkCode = $prefix . $bkId;

            if (!isset($grouped[$bkId])) {
                // Dòng Booking giống dòng master trên màn Hóa đơn:
                // bill/thanh toán thuộc Phiếu Tổng, không cộng tiền của từng phòng.
                [$bkTotalServices, $bkTotalPayment] = $this->masterFolioTotals($bk, $bookingBills[$bkId] ?? collect());

                $totalNights = (int) ($bk->num_of_days ?: ($room->ActutalNumOfDays ?: 1));
                $noteText = $bk->note ? 'Ghi chú: ' . $bk->note : '';

                $grouped[$bkId] = [
                    'id' => $bkCode,
                    'bookingId' => $bkId,
                    'bookingName' => $bk->booking_name ?: 'Khách lẻ',
                    'status' => $bk->registrationStatus?->name ?? 'Guaranteed',
                    'roomNight' => $totalNights,
                    'roomsCount' => 0,
                    'arrivalDate' => Carbon::parse($bk->arrival_date ?: $room->arrival_date)->format('d/m/Y'),
                    'departureDate' => Carbon::parse($bk->departure_date ?: $room->departure_date)->format('d/m/Y'),
                    'notes' => $noteText,
                    'totalServices' => $bkTotalServices,
                    'totalPayment' => $bkTotalPayment,
                    'rooms' => []
                ];
            }

            // Tiền dịch vụ và thanh toán của riêng phòng này (cùng cách tính với màn Hóa đơn)
            $roomServices = $this->roomFolioServiceTotal($room, $bookingBills[$bk->id] ?? collect());
            $roomPayment = (float) Payment::where('booking_room_id', $room->id)
                ->where(function ($q) {
                    $q->where('edit_flag', 0)->orWhereNull('edit_flag');
                })
                ->whereNull('deleted_at')
                ->sum('amount');

            $spList = [];
            if ($room->specialRequests && $room->specialRequests->count() > 0) {
                foreach ($room->specialRequests as $sp) {
                    if ($sp->specialRequest?->name) {
                        $spList[] = $sp->specialRequest->name;
                    } elseif ($sp->note) {
                        $spList[] = $sp->note;
                    }
                }
            }
            $specialRequestStr = implode(', ', $spList);

            $nights = $this->roomNights($room);
            $rate = (float) ($room->rate ?: 0);

            // Room Type name
            $roomTypeName = $room->roomClass?->name 
                ?: ($room->room?->roomClass?->name 
                ?: ($room->room_type ?: ''));

            $statusLabel = $bk->registrationStatus?->name ?? 'Guaranteed';

            $grouped[$bkId]['roomsCount']++;
            $grouped[$bkId]['rooms'][] = [
                'id' => $room->id,
                'bookingCode' => $bkCode,
                'bookingName' => $bk->booking_name ?: 'Khách lẻ',
                'status' => $statusLabel,
                'roomType' => $roomTypeName,
                'roomNumber' => $room->room_number ?: '',
                'arrivalDate' => Carbon::parse($room->arrival_date)->format('d/m/Y'),
                'departureDate' => Carbon::parse($room->departure_date)->format('d/m/Y'),
                'nights' => $nights,
                'adults' => (int) ($room->adults ?: 1),
                'children' => (int) ($room->children_qty ?: 0),
                'price' => $rate,
                'rateCode' => $this->cleanRateCode($room->rate_code),
                'totalServices' => $roomServices,
                'totalPayment' => $roomPayment,
                'specialRequest' => $specialRequestStr,
                'company' => $bk->company?->name ?: ($bk->customerSource?->name ?: '')
            ];
        }

        $result = array_values($grouped);

        if ($search !== '') {
            $sLower = mb_strtolower($search);
            $result = array_values(array_filter($result, function ($bkItem) use ($sLower) {
                if (str_contains(mb_strtolower($bkItem['id']), $sLower)) return true;
                if (str_contains(mb_strtolower($bkItem['bookingName']), $sLower)) return true;
                foreach ($bkItem['rooms'] as $rm) {
                    if (str_contains(mb_strtolower($rm['roomNumber']), $sLower)) return true;
                    if (str_contains(mb_strtolower($rm['roomType']), $sLower)) return true;
                    if (str_contains(mb_strtolower($rm['company']), $sLower)) return true;
                }
                return false;
            }));
        }

        $totalRegistrations = count($result);
        $totalRooms = array_sum(array_column($result, 'roomsCount'));

        return response()->json([
            'success' => true,
            'data' => $result,
            'meta' => [
                'date' => $targetDate,
                'status' => $statusFilter,
                'totalRegistrations' => $totalRegistrations,
                'totalRooms' => $totalRooms
            ]
        ]);
    }

    /**
     * Xuất Excel danh sách phòng đến / phòng đi (cùng bộ lọc với màn hình).
     * GET /shift-work/export?type=arrivals|departures&date=&status=&search=
     */
    public function export(Request $request)
    {
        $type = $request->input('type') === 'departures' ? 'departures' : 'arrivals';
        $payload = $type === 'departures'
            ? $this->departures($request)->getData(true)
            : $this->arrivals($request)->getData(true);

        $isDeparture = $type === 'departures';
        $headings = [
            'Mã đăng ký', 'Giá phòng', 'Tên đăng ký', 'Tình trạng đăng ký', 'Loại phòng', 'Phòng',
            'Ngày đến', 'Ngày đi', 'Đêm phòng', 'Người lớn', 'Trẻ em', 'Mã giá phòng',
            $isDeparture ? 'Tổng DV' : 'Tổng phòng',
        ];
        if ($isDeparture) {
            $headings[] = 'Tổng TT';
        }
        array_push($headings, 'Yêu cầu ĐB', 'Công ty');

        $sheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $ws = $sheet->getActiveSheet();
        $ws->setTitle($isDeparture ? 'Phòng đi' : 'Phòng đến');
        $ws->fromArray($headings, null, 'A1');
        $ws->getStyle('A1:' . $ws->getHighestColumn() . '1')->getFont()->setBold(true);

        $rowNo = 2;
        foreach ($payload['data'] ?? [] as $booking) {
            foreach ($booking['rooms'] as $room) {
                $line = [
                    $booking['id'], $room['price'], $booking['bookingName'], $room['status'], $room['roomType'], $room['roomNumber'],
                    $room['arrivalDate'], $room['departureDate'], $room['nights'], $room['adults'], $room['children'], $room['rateCode'],
                    $isDeparture ? $room['totalServices'] : $room['roomTotal'],
                ];
                if ($isDeparture) {
                    $line[] = $room['totalPayment'];
                }
                array_push($line, $room['specialRequest'], $room['company']);
                $ws->fromArray($line, null, 'A' . $rowNo++);
            }
        }

        foreach (range('A', $ws->getHighestColumn()) as $col) {
            $ws->getColumnDimension($col)->setAutoSize(true);
        }

        $date = Carbon::parse($request->input('date', $this->getSystemDate()))->format('Ymd');
        $fileName = ($isDeparture ? 'phong_di_' : 'phong_den_') . $date . '.xlsx';

        return response()->streamDownload(function () use ($sheet) {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($sheet))->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Tab 3: Đăng ký chờ xác nhận (Pending confirmation - sp_141).
     * Mặc định từ ngày hệ thống -> ngày hệ thống + 3 ngày.
     */
    public function pending(Request $request)
    {
        $sysDate = $this->getSystemDate();
        $fromDateStr = $request->input('from_date', $sysDate);
        $toDateStr = $request->input('to_date', Carbon::parse($sysDate)->addDays(3)->toDateString());
        $search = trim($request->input('search', ''));

        $prefix = $this->getPrefix();
        $fromDate = Carbon::parse($fromDateStr)->startOfDay();
        $toDate = Carbon::parse($toDateStr)->endOfDay();

        $query = Booking::with([
            'customerSource',
            'company',
            'registrationStatus',
            'bookingRooms.roomClass',
            'bookingRooms.room.roomClass'
        ])
        ->whereNull('deleted_at')
        ->where('status', Booking::STATUS_RESERVATION)
        ->where(function ($q) use ($fromDate, $toDate) {
            $q->whereBetween('confirm_date', [$fromDate->toDateString(), $toDate->toDateString()])
              ->orWhereBetween('arrival_date', [$fromDate->toDateString(), $toDate->toDateString()])
              ->orWhere(function ($sub) use ($fromDate) {
                  $sub->where('confirm_date', '<', $fromDate->toDateString())
                      ->where('status', 0);
              });
        })
        ->where(function ($q) {
            // Lọc Non-guaranteed (mã 20 hoặc bk_definite != 1)
            $q->whereNull('registration_status_id')
              ->orWhereHas('registrationStatus', function ($rs) {
                  $rs->where('bk_definite', '!=', 1);
              });
        });

        $bookings = $query->orderBy('confirm_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $result = [];
        foreach ($bookings as $bk) {
            $bkId = $bk->id;
            $bkCode = $prefix . $bkId;

            // Tổng hợp loại phòng dạng: SUPD (5), SUPT (1)
            $roomTypeCounts = [];
            foreach ($bk->bookingRooms as $rm) {
                $code = $rm->roomClass?->code 
                    ?: ($rm->room?->roomClass?->code 
                    ?: ($rm->roomClass?->name ?: 'ROOM'));
                $roomTypeCounts[$code] = ($roomTypeCounts[$code] ?? 0) + 1;
            }
            $roomTypesFormatted = [];
            foreach ($roomTypeCounts as $code => $qty) {
                $roomTypesFormatted[] = "{$code} ({$qty})";
            }
            $roomTypesStr = implode(', ', $roomTypesFormatted);

            // Tiền cọc
            $deposit = (float) Payment::where('booking_id', $bkId)
                ->where('pack2', Payment::PACK2_DEPOSIT)
                ->where(function ($q) {
                    $q->where('edit_flag', 0)->orWhereNull('edit_flag');
                })
                ->whereNull('deleted_at')
                ->sum('amount');

            // Liên hệ
            $contactInfo = array_filter([
                $bk->contact_name,
                $bk->contact_phone,
                $bk->contact_email
            ]);
            $contactStr = implode(' - ', $contactInfo);

            $confirmDateStr = $bk->confirm_date ? Carbon::parse($bk->confirm_date)->format('d/m/Y') : '';
            $arrDateStr = $bk->arrival_date ? Carbon::parse($bk->arrival_date)->format('d/m/Y') : '';
            $depDateStr = $bk->departure_date ? Carbon::parse($bk->departure_date)->format('d/m/Y') : '';

            $result[] = [
                'id' => $bkCode,
                'bookingId' => $bkId,
                'bookingName' => $bk->booking_name ?: 'Khách lẻ',
                'status' => $bk->registrationStatus?->name ?? 'Non guaranteed',
                'company' => $bk->company?->name ?: ($bk->customerSource?->name ?: ''),
                'confirmDate' => $confirmDateStr,
                'arrivalDate' => $arrDateStr,
                'departureDate' => $depDateStr,
                'nights' => (int) ($bk->num_of_days ?: 1),
                'roomTypes' => $roomTypesStr,
                'deposit' => $deposit,
                'contact' => $contactStr,
                'notes' => $bk->note ?: '',
            ];
        }

        if ($search !== '') {
            $sLower = mb_strtolower($search);
            $result = array_values(array_filter($result, function ($item) use ($sLower) {
                return str_contains(mb_strtolower($item['id']), $sLower) ||
                       str_contains(mb_strtolower($item['bookingName']), $sLower) ||
                       str_contains(mb_strtolower($item['company']), $sLower) ||
                       str_contains(mb_strtolower($item['contact']), $sLower) ||
                       str_contains(mb_strtolower($item['roomTypes']), $sLower);
            }));
        }

        return response()->json([
            'success' => true,
            'data' => $result,
            'meta' => [
                'from_date' => $fromDateStr,
                'to_date' => $toDateStr,
                'total' => count($result)
            ]
        ]);
    }

    /**
     * Cập nhật ghi chú xác nhận của Sale cho Booking (sp2000.Pack1).
     */
    public function updatePendingNote(Request $request, $bookingId)
    {
        $request->validate([
            'note' => 'nullable|string|max:1000'
        ]);

        $booking = Booking::findOrFail($bookingId);
        $booking->note = $request->input('note', '');
        $booking->save();

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật ghi chú xác nhận thành công!',
            'data' => [
                'bookingId' => $booking->id,
                'note' => $booking->note
            ]
        ]);
    }

    /**
     * Tab 4: Đón tiễn khách (Shuttle / Airport Transfer).
     */
    public function shuttle(Request $request)
    {
        $dateStr = $request->input('date', $this->getSystemDate());
        $type = $request->input('type', 'all'); // 'all', 'arrival', 'departure'
        $search = trim($request->input('search', ''));

        $prefix = $this->getPrefix();
        $targetDate = Carbon::parse($dateStr)->toDateString();

        $query = Booking::with([
            'company',
            'bookingRooms.room'
        ])
        ->whereNull('deleted_at')
        ->where(function ($q) use ($targetDate) {
            $q->whereDate('arrival_flight_date', $targetDate)
              ->orWhereDate('departure_flight_date', $targetDate)
              ->orWhereDate('arrival_date', $targetDate)
              ->orWhereDate('departure_date', $targetDate);
        });

        $bookings = $query->orderBy('arrival_date', 'asc')->get();

        $result = [];
        foreach ($bookings as $bk) {
            $bkCode = $prefix . $bk->id;
            $roomsList = $bk->bookingRooms->pluck('room_number')->filter()->implode(', ');

            // Check Arrival Shuttle
            if ($type === 'all' || $type === 'arrival') {
                $arrFDate = $bk->arrival_flight_date ? Carbon::parse($bk->arrival_flight_date)->toDateString() : Carbon::parse($bk->arrival_date)->toDateString();
                if ($arrFDate === $targetDate && ($bk->arrival_flight || !empty($bk->shuttle_info['pickup']))) {
                    $flightTime = $bk->arrival_flight_date ? Carbon::parse($bk->arrival_flight_date)->format('H:i') : '';
                    $result[] = [
                        'id' => $bkCode,
                        'bookingName' => $bk->booking_name ?: 'Khách lẻ',
                        'roomNumber' => $roomsList,
                        'guestName' => $bk->contact_name ?: $bk->booking_name,
                        'shuttleType' => 'Đón sân bay',
                        'flightCode' => $bk->arrival_flight ?: '',
                        'flightTime' => $flightTime,
                        'date' => Carbon::parse($arrFDate)->format('d/m/Y'),
                        'pax' => (int) ($bk->bookingRooms->sum('adults') ?: 1),
                        'vehicle' => $bk->shuttle_info['vehicle'] ?? 'Xe 7 chỗ',
                        'notes' => $bk->shuttle_info['notes'] ?? ($bk->note ?: ''),
                        'company' => $bk->company?->name ?: '',
                        'status' => 'Chưa đón'
                    ];
                }
            }

            // Check Departure Shuttle
            if ($type === 'all' || $type === 'departure') {
                $depFDate = $bk->departure_flight_date ? Carbon::parse($bk->departure_flight_date)->toDateString() : Carbon::parse($bk->departure_date)->toDateString();
                if ($depFDate === $targetDate && ($bk->departure_flight || !empty($bk->shuttle_info['dropoff']))) {
                    $flightTime = $bk->departure_flight_date ? Carbon::parse($bk->departure_flight_date)->format('H:i') : '';
                    $result[] = [
                        'id' => $bkCode,
                        'bookingName' => $bk->booking_name ?: 'Khách lẻ',
                        'roomNumber' => $roomsList,
                        'guestName' => $bk->contact_name ?: $bk->booking_name,
                        'shuttleType' => 'Tiễn sân bay',
                        'flightCode' => $bk->departure_flight ?: '',
                        'flightTime' => $flightTime,
                        'date' => Carbon::parse($depFDate)->format('d/m/Y'),
                        'pax' => (int) ($bk->bookingRooms->sum('adults') ?: 1),
                        'vehicle' => $bk->shuttle_info['vehicle'] ?? 'Xe 7 chỗ',
                        'notes' => $bk->shuttle_info['notes'] ?? ($bk->note ?: ''),
                        'company' => $bk->company?->name ?: '',
                        'status' => 'Chưa tiễn'
                    ];
                }
            }
        }

        if ($search !== '') {
            $sLower = mb_strtolower($search);
            $result = array_values(array_filter($result, function ($item) use ($sLower) {
                return str_contains(mb_strtolower($item['id']), $sLower) ||
                       str_contains(mb_strtolower($item['bookingName']), $sLower) ||
                       str_contains(mb_strtolower($item['roomNumber']), $sLower) ||
                       str_contains(mb_strtolower($item['flightCode']), $sLower);
            }));
        }

        return response()->json([
            'success' => true,
            'data' => $result,
            'meta' => [
                'date' => $targetDate,
                'total' => count($result)
            ]
        ]);
    }

    private function cleanRateCode($rateCode): string
    {
        $rateCode = trim((string) $rateCode);
        return $rateCode === 'Vui lòng chọn giá phòng' ? '' : $rateCode;
    }

    private function hasBillOwner($value): bool
    {
        return $value !== null && (string) $value !== '' && (string) $value !== '0';
    }

    /**
     * Bill dịch vụ (service_bills) của các booking, gom theo booking_id
     * (bill gốc hoặc bill đã chuyển sang booking khác), bỏ bill đã sửa/hủy.
     */
    private function loadBookingServiceBills(array $bookingIds)
    {
        if (empty($bookingIds)) {
            return collect();
        }

        $bills = ServiceBill::where(function ($q) use ($bookingIds) {
                $q->whereIn('RegisterId1', $bookingIds)->orWhereIn('RegisterID2', $bookingIds);
            })
            ->where(function ($q) {
                $q->whereNull('Edit')->orWhere('Edit', '!=', 1);
            })
            ->where(function ($q) {
                $q->whereNull('Status')->orWhereNotIn('Status', [3, 4]);
            })
            ->get();

        $grouped = [];
        foreach ($bills as $bill) {
            foreach (array_unique(array_filter([(int) $bill->RegisterId1, (int) $bill->RegisterID2])) as $id) {
                $grouped[$id][] = $bill;
            }
        }

        return collect($grouped)->map(fn ($items) => collect($items));
    }

    private function isRoomChargeBill($bill): bool
    {
        $code = strtoupper((string) $bill->ServiceId);
        return in_array($code, ['RM', 'ER'], true) || str_contains((string) $bill->DescriptionServive, 'Tiền phòng');
    }

    /** Bill thuộc Phiếu Tổng: chưa chuyển về phòng/khách nào. */
    private function isMasterFolioBill($bill, $bookingId): bool
    {
        if ($this->hasBillOwner($bill->RentalRoomId2) || $this->hasBillOwner($bill->CustomerId2)) {
            return false;
        }
        if ($this->hasBillOwner($bill->RegisterID2)) {
            return (string) $bill->RegisterID2 === (string) $bookingId;
        }
        return (string) $bill->RegisterId1 === (string) $bookingId && !$this->hasBillOwner($bill->RentalRoomId1);
    }

    /** Phòng đang sở hữu bill; bill đã chuyển lên Phiếu Tổng thì không còn thuộc phòng gốc. */
    private function billCurrentRoomId($bill)
    {
        if ($this->hasBillOwner($bill->RentalRoomId2)) {
            return $bill->RentalRoomId2;
        }
        if ($this->hasBillOwner($bill->RegisterID2)) {
            return null;
        }
        return $bill->RentalRoomId1;
    }

    /** @return array{0: float, 1: float} [tổng dịch vụ, tổng thanh toán] của Phiếu Tổng */
    private function masterFolioTotals(Booking $booking, $bills): array
    {
        $services = (float) $bills
            ->filter(fn ($bill) => $this->isMasterFolioBill($bill, $booking->id))
            ->sum(fn ($bill) => (float) $bill->Amount);

        $payments = (float) Payment::where('booking_id', $booking->id)
            ->whereNull('booking_room_id')
            ->where(function ($q) {
                $q->where('edit_flag', 0)->orWhereNull('edit_flag');
            })
            ->whereNull('deleted_at')
            ->sum('amount');

        return [$services, $payments];
    }

    /**
     * Tổng dịch vụ của một phòng: dịch vụ đã post (không phải tiền phòng)
     * + bill tiền phòng của phòng, giống cách màn Hóa đơn cộng.
     */
    private function roomFolioServiceTotal(BookingRoom $room, $bills): float
    {
        $isRoomCharge = fn ($service) => in_array(strtoupper((string) $service->service_code), ['RM', 'ER'], true)
            || str_contains((string) $service->service_name, 'Tiền phòng');
        $isPosted = fn ($service) => (int) $service->is_posted === 1 || !empty($service->service_bill_id);
        $inRoomFolio = fn ($service) => $service->is_room === null || (int) $service->is_room === 1;
        $amountOf = fn ($service) => (float) ($service->total_amount ?: ((float) ($service->quantity ?: 1) * (float) $service->rate));

        $postedServices = $room->services->filter(fn ($service) => $isPosted($service) && $inRoomFolio($service));

        $extra = $postedServices->reject($isRoomCharge)->sum($amountOf);

        $roomBills = $bills->filter(fn ($bill) => $this->isRoomChargeBill($bill)
            && (string) $this->billCurrentRoomId($bill) === (string) $room->id);
        $billIds = $roomBills->pluck('Ma')->map(fn ($id) => (string) $id)->all();
        $roomCharge = (float) $roomBills->sum(fn ($bill) => (float) $bill->Amount);

        // Dòng tiền phòng cũ chỉ còn trong booking_room_services (chưa có bill)
        foreach ($postedServices->filter($isRoomCharge) as $service) {
            if (!empty($service->service_bill_id) && in_array((string) $service->service_bill_id, $billIds, true)) {
                continue;
            }
            if (empty($service->service_bill_id) && !empty($billIds)) {
                continue;
            }
            $roomCharge += $amountOf($service);
        }

        return (float) $extra + $roomCharge;
    }

    private function isNoChargeReason(?string $reason): bool
    {
        return stripos((string) $reason, 'No Charge') !== false;
    }

    private function noshowRoomTypeName(BookingRoom $room): string
    {
        return $room->roomClass?->name
            ?: ($room->room?->roomClass?->name
            ?: ($room->room_type ?: ''));
    }

    /**
     * Tab 5: Phòng không đến (Noshow - sp_054).
     */
    public function noshow(Request $request)
    {
        $sysDate = $this->getSystemDate();
        $fromDateStr = $request->input('from_date', $sysDate);
        $toDateStr = $request->input('to_date', $fromDateStr);
        $search = trim($request->input('search', ''));

        $prefix = $this->getPrefix();
        $fromDate = Carbon::parse($fromDateStr)->startOfDay();
        $toDate = Carbon::parse($toDateStr)->endOfDay();

        $query = BookingRoom::with([
            'booking.company',
            'roomClass',
            'room.roomClass'
        ])
        ->where('status', BookingRoom::STATUS_NOSHOW)
        ->whereDate('arrival_date', '>=', $fromDate->toDateString())
        ->whereDate('arrival_date', '<=', $toDate->toDateString());

        $rooms = $query->orderBy('arrival_date', 'desc')->get();

        $result = [];
        foreach ($rooms as $room) {
            $bk = $room->booking;
            $bkCode = $prefix . ($bk?->id ?? 0);

            // Tìm noshow log nếu có
            $log = NoshowLog::where('booking_room_id', $room->id)->latest()->first();

            $nights = $this->roomNights($room);
            $rate = (float) ($room->rate ?: 0);
            $totalAmount = (float) ($room->total_rate ?: ($rate * $nights));

            $noshowDate = $log && $log->noshow_date ? Carbon::parse($log->noshow_date)->format('d/m/Y') : Carbon::parse($room->arrival_date)->format('d/m/Y');
            $noshowTime = $log?->noshow_time ?: '18:00';
            $reason = $log?->reason ?: 'Khách không đến nhận phòng';

            // Không tính phí thì tổng tiền hiển thị là 0 để khớp bộ lọc Tính phí / Không tính phí
            $isCharged = !$this->isNoChargeReason($reason);

            $result[] = [
                'id' => $bkCode,
                'type' => 'noshow',
                'isCharged' => $isCharged,
                'bookingName' => $bk?->booking_name ?: 'Khách lẻ',
                'roomType' => $this->noshowRoomTypeName($room),
                'roomNumber' => $room->room_number ?: '',
                'arrivalDate' => Carbon::parse($room->arrival_date)->format('d/m/Y'),
                'nights' => $nights,
                'noshowDate' => $noshowDate,
                'noshowTime' => $noshowTime,
                'totalAmount' => $isCharged ? $totalAmount : 0,
                'reason' => $reason,
                'username' => $log?->username ?: 'Admin',
                'shift' => $log?->shift ?: '1',
                'company' => $bk?->company?->name ?: ''
            ];
        }

        // Noshow by stage (late check-in): phòng dời ngày đến sang hôm sau, chỉ tính 1 đêm
        $lateLogs = LateCheckin::with(['bookingRoom.booking.company', 'bookingRoom.roomClass', 'bookingRoom.room.roomClass'])
            ->whereBetween('late_checkin_date', [$fromDate, $toDate])
            ->orderBy('late_checkin_date', 'desc')
            ->get();

        foreach ($lateLogs as $lateLog) {
            $room = $lateLog->bookingRoom;
            if (!$room) {
                continue;
            }
            $bk = $room->booking;
            $reason = $lateLog->reason ?: 'NightAudit No Show One Day';
            $isCharged = !$this->isNoChargeReason($reason);
            $lateDate = Carbon::parse($lateLog->late_checkin_date);

            $result[] = [
                'id' => $prefix . ($bk?->id ?? 0),
                'type' => 'late_checkin',
                'isCharged' => $isCharged,
                'bookingName' => $bk?->booking_name ?: 'Khách lẻ',
                'roomType' => $this->noshowRoomTypeName($room),
                'roomNumber' => $room->room_number ?: '',
                'arrivalDate' => $lateDate->format('d/m/Y'),
                'nights' => 1,
                'noshowDate' => $lateDate->format('d/m/Y'),
                'noshowTime' => $lateLog->late_checkin_time ?: '18:00',
                'totalAmount' => $isCharged ? (float) ($room->rate ?: 0) : 0,
                'reason' => $reason,
                'username' => $lateLog->username ?: 'Admin',
                'shift' => $lateLog->shift ?: '1',
                'company' => $bk?->company?->name ?: ''
            ];
        }

        if ($search !== '') {
            $sLower = mb_strtolower($search);
            $result = array_values(array_filter($result, function ($item) use ($sLower) {
                return str_contains(mb_strtolower($item['id']), $sLower) ||
                       str_contains(mb_strtolower($item['bookingName']), $sLower) ||
                       str_contains(mb_strtolower($item['roomNumber']), $sLower) ||
                       str_contains(mb_strtolower($item['company']), $sLower);
            }));
        }

        return response()->json([
            'success' => true,
            'data' => $result,
            'meta' => [
                'from_date' => $fromDateStr,
                'to_date' => $toDateStr,
                'total' => count($result)
            ]
        ]);
    }

    /**
     * Tab 6: Sinh nhật khách (Birthdays - sp_111).
     * Mặc định từ ngày hệ thống -> ngày hệ thống + 3 ngày.
     */
    public function birthdays(Request $request)
    {
        $sysDate = $this->getSystemDate();
        $fromDateStr = $request->input('from_date', $sysDate);
        $toDateStr = $request->input('to_date', Carbon::parse($sysDate)->addDays(3)->toDateString());
        $search = trim($request->input('search', ''));

        $prefix = $this->getPrefix();
        $fromDate = Carbon::parse($fromDateStr);
        $toDate = Carbon::parse($toDateStr);

        // Lấy danh sách khách trong các phòng đang ở hoặc sắp đến (status in [0, 1])
        $roomGuests = BookingRoomGuest::with([
            'guest',
            'bookingRoom.booking.company',
            'bookingRoom.room'
        ])
        ->whereHas('bookingRoom', function ($q) {
            $q->whereNull('deleted_at')
              ->whereIn('status', [BookingRoom::STATUS_BOOKED, BookingRoom::STATUS_CHECKED_IN]);
        })
        ->whereHas('guest', function ($q) {
            $q->whereNotNull('dob');
        })
        ->get();

        $result = [];
        $seq = 1;

        foreach ($roomGuests as $rg) {
            $g = $rg->guest;
            $rm = $rg->bookingRoom;
            $bk = $rm?->booking;
            if (!$g || !$g->dob || !$rm) continue;

            $dob = Carbon::parse($g->dob);
            
            // Check if birthday falls within [fromDate, toDate] (regardless of birth year)
            $isMatch = false;
            $period = CarbonPeriod::create($fromDate->copy()->startOfDay(), $toDate->copy()->startOfDay());
            foreach ($period as $checkDate) {
                if ($checkDate->month === $dob->month && $checkDate->day === $dob->day) {
                    $isMatch = true;
                    break;
                }
            }

            if (!$isMatch) continue;

            $bkCode = $prefix . ($bk?->id ?? 0);
            $age = $dob->age;

            $result[] = [
                'no' => $seq++,
                'bookingCode' => $bkCode,
                'roomNumber' => $rm->room_number ?: '',
                'guestName' => $g->full_name ?: ($g->name ?: 'Guest'),
                'birthday' => $dob->format('d/m/Y'),
                'age' => $age,
                'idType' => $g->passport_number ? 'Hộ chiếu' : 'Căn cước công dân',
                'idNumber' => $g->passport_number ?: ($g->id_number ?: ''),
                'nationality' => $g->nationality_code ?: 'Việt Nam',
                'phone' => $g->phone ?: '',
                'email' => $g->email ?: '',
                'arrivalDate' => $rm->arrival_date ? Carbon::parse($rm->arrival_date)->format('d/m/Y') : '',
                'departureDate' => $rm->departure_date ? Carbon::parse($rm->departure_date)->format('d/m/Y') : '',
                'company' => $bk?->company?->name ?: ''
            ];
        }

        if ($search !== '') {
            $sLower = mb_strtolower($search);
            $result = array_values(array_filter($result, function ($item) use ($sLower) {
                return str_contains(mb_strtolower($item['bookingCode']), $sLower) ||
                       str_contains(mb_strtolower($item['guestName']), $sLower) ||
                       str_contains(mb_strtolower($item['roomNumber']), $sLower) ||
                       str_contains(mb_strtolower($item['idNumber']), $sLower) ||
                       str_contains(mb_strtolower($item['phone']), $sLower);
            }));
        }

        return response()->json([
            'success' => true,
            'data' => $result,
            'meta' => [
                'from_date' => $fromDateStr,
                'to_date' => $toDateStr,
                'total' => count($result)
            ]
        ]);
    }
}
