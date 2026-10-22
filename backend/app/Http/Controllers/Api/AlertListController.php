<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AlertEventStatus;
use App\Models\BookingRoom;
use App\Models\BookingRoomGuest;
use App\Models\HotelConfig;
use App\Models\HotelSetting;
use App\Models\SystemDateRoll;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Màn hình Alert: danh sách khách sinh nhật và phòng có special request
 * (thay cho sp_GetAlertList của hệ thống cũ).
 */
class AlertListController extends Controller
{
    private const MAX_RANGE_DAYS = 366;

    private function systemDate(): Carbon
    {
        $latest = SystemDateRoll::latest('id')->first();

        return $latest && $latest->system_date
            ? Carbon::parse($latest->system_date)->startOfDay()
            : now()->timezone('Asia/Ho_Chi_Minh')->startOfDay();
    }

    /**
     * GET /alerts?from_date=&to_date=
     * Mặc định từ ngày hệ thống đến ngày hệ thống + 3.
     */
    public function index(Request $request)
    {
        $request->validate([
            'from_date' => 'nullable|date',
            'to_date'   => 'nullable|date',
        ]);

        $from = $request->filled('from_date') ? Carbon::parse($request->from_date)->startOfDay() : $this->systemDate();
        $to = $request->filled('to_date') ? Carbon::parse($request->to_date)->startOfDay() : $from->copy()->addDays(3);

        if ($to->lt($from)) {
            return response()->json(['success' => false, 'message' => 'Đến ngày phải lớn hơn hoặc bằng từ ngày.'], 422);
        }
        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            return response()->json(['success' => false, 'message' => 'Chỉ xem tối đa 366 ngày mỗi lần.'], 422);
        }

        $prefix = strtoupper(HotelSetting::first()?->prefix_booking_id ?? 'GAL');

        $rooms = BookingRoom::with([
                'booking',
                'guests' => fn ($q) => $q->whereIn('status', [BookingRoomGuest::STATUS_ACTIVE, BookingRoomGuest::STATUS_CHECKED_IN]),
                'guests.guest',
                'specialRequests.specialRequest',
            ])
            ->whereNull('deleted_at')
            ->whereIn('status', [BookingRoom::STATUS_BOOKED, BookingRoom::STATUS_CHECKED_IN])
            ->whereDate('arrival_date', '<=', $to->toDateString())
            ->whereDate('departure_date', '>=', $from->toDateString())
            ->orderBy('arrival_date')
            ->orderBy('room_number')
            ->get();

        $events = [];
        foreach ($rooms as $room) {
            $booking = $room->booking;
            if (!$booking) {
                continue;
            }

            $arrival = Carbon::parse($room->arrival_date)->startOfDay();
            $departure = Carbon::parse($room->departure_date)->startOfDay();
            $base = [
                'bookingCode' => $prefix . $booking->id,
                'bookingId'   => $booking->id,
                'roomNumber'  => $room->room_number ?: '',
                'bookingName' => $booking->booking_name ?: '',
                'arrivalDate' => $arrival->format('d/m/Y'),
                'departureDate' => $departure->format('d/m/Y'),
            ];
            $primaryGuest = $room->guests->firstWhere('is_primary', true)?->guest ?? $room->guests->first()?->guest;

            // Sinh nhật: ngày sinh rơi vào khoảng xem và nằm trong thời gian lưu trú
            $stayStart = $arrival->greaterThan($from) ? $arrival : $from;
            $stayEnd = $departure->lessThan($to) ? $departure : $to;
            foreach ($room->guests as $roomGuest) {
                $guest = $roomGuest->guest;
                if (!$guest || !$guest->dob || $stayStart->gt($stayEnd)) {
                    continue;
                }
                $dob = Carbon::parse($guest->dob);
                $birthdayDate = $this->birthdayWithin($dob, $stayStart, $stayEnd);
                if (!$birthdayDate) {
                    continue;
                }
                $events[] = $base + [
                    'eventKey'  => "BD:{$room->id}:{$guest->id}:{$birthdayDate->year}",
                    'guestName' => $guest->full_name ?: 'Guest',
                    'birthday'  => $dob->format('d/m/Y'),
                    'eventType' => 'birthday',
                    'eventName' => 'Sinh nhật',
                    'eventDate' => $birthdayDate->toDateString(),
                ];
            }

            // Special request của phòng (phòng lưu trú giao với khoảng xem)
            foreach ($room->specialRequests as $roomRequest) {
                $name = $roomRequest->specialRequest?->name ?: $roomRequest->note;
                if (!$name) {
                    continue;
                }
                $events[] = $base + [
                    'eventKey'  => "SR:{$room->id}:{$roomRequest->special_request_id}",
                    'guestName' => $primaryGuest?->full_name ?: '',
                    'birthday'  => $primaryGuest?->dob ? Carbon::parse($primaryGuest->dob)->format('d/m/Y') : '',
                    'eventType' => 'special_request',
                    'eventName' => $roomRequest->note && $roomRequest->specialRequest?->name
                        ? $roomRequest->specialRequest->name . ' - ' . $roomRequest->note
                        : $name,
                    'eventDate' => $stayStart->toDateString(),
                ];
            }
        }

        $statuses = AlertEventStatus::whereIn('event_key', collect($events)->pluck('eventKey'))
            ->pluck('status', 'event_key');

        $events = array_map(function ($event) use ($statuses) {
            $status = (int) ($statuses[$event['eventKey']] ?? AlertEventStatus::STATUS_PENDING);
            $event['status'] = $status;
            $event['statusLabel'] = $status === AlertEventStatus::STATUS_DONE ? 'Đã xử lý' : 'Chưa xử lý';

            return $event;
        }, $events);

        usort($events, fn ($a, $b) => [$a['status'], $a['eventDate'], $a['roomNumber']] <=> [$b['status'], $b['eventDate'], $b['roomNumber']]);

        return response()->json([
            'success' => true,
            'data' => $events,
            'meta' => [
                'from_date' => $from->toDateString(),
                'to_date' => $to->toDateString(),
                'pending' => collect($events)->where('status', AlertEventStatus::STATUS_PENDING)->count(),
                'auto_popup' => (string) HotelConfig::where('name', 'AllowShowAlertListView')->value('value') !== '0',
            ],
        ]);
    }

    /**
     * PUT /alerts/status  { event_key, status }
     */
    public function updateStatus(Request $request)
    {
        $data = $request->validate([
            'event_key' => ['required', 'string', 'max:120', 'regex:/^(BD|SR):[A-Za-z0-9_-]+:[A-Za-z0-9_-]+(:\d{4})?$/'],
            'status'    => 'required|integer|in:0,1',
        ]);

        $status = AlertEventStatus::updateOrCreate(
            ['event_key' => $data['event_key']],
            ['status' => $data['status'], 'updated_by' => Auth::user()?->username]
        );

        return response()->json(['success' => true, 'data' => $status]);
    }

    /** Ngày sinh nhật (theo năm của khoảng xem) nằm trong [start, end], null nếu không có. */
    private function birthdayWithin(Carbon $dob, Carbon $start, Carbon $end): ?Carbon
    {
        for ($year = $start->year; $year <= $end->year; $year++) {
            $day = min($dob->day, Carbon::create($year, $dob->month, 1)->daysInMonth);
            $candidate = Carbon::create($year, $dob->month, $day)->startOfDay();
            if ($candidate->betweenIncluded($start, $end)) {
                return $candidate;
            }
        }

        return null;
    }
}
