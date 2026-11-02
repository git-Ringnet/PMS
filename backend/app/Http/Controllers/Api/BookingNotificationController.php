<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingNotification;
use App\Services\ActivityLogService;
use App\Support\ActivityScreen;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BookingNotificationController extends Controller
{
    public function index(Booking $booking)
    {
        return response()->json([
            'success' => true,
            'data' => $booking->notifications()->latest('starts_on')->latest('id')->get(),
        ]);
    }

    public function active(Booking $booking)
    {
        $requestDate = request('date');
        $query = $booking->notifications();

        if ($requestDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestDate)) {
            $query->where(function ($q) use ($requestDate, $booking) {
                // 1. Hiệu lực tại ngày chỉ định
                $q->where(function ($sub) use ($requestDate) {
                    $sub->whereDate('starts_on', '<=', $requestDate)
                        ->whereDate('ends_on', '>=', $requestDate);
                });
                // 2. Hoặc nằm trong giai đoạn lưu trú của booking
                if ($booking->arrival_date && $booking->departure_date) {
                    $q->orWhere(function ($sub) use ($booking) {
                        $sub->whereDate('starts_on', '<=', $booking->departure_date)
                            ->whereDate('ends_on', '>=', $booking->arrival_date);
                    });
                }
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest('id')->get(),
        ]);
    }

    public function store(Request $request, Booking $booking)
    {
        $data = $this->validated($request, $booking);
        $data['created_by_user_id'] = $request->user()?->id;
        $data['updated_by_user_id'] = $request->user()?->id;

        $notification = $booking->notifications()->create($data);
        $this->logAlert('Add', '* Tạo Alert cho mã đăng ký', $booking, $notification, $request);

        return response()->json(['success' => true, 'data' => $notification], 201);
    }

    public function update(Request $request, Booking $booking, BookingNotification $notification)
    {
        abort_unless((int) $notification->booking_id === (int) $booking->id, 404);
        $data = $this->validated($request, $booking);
        $data['updated_by_user_id'] = $request->user()?->id;
        $oldDescription = (string) $notification->description;
        $oldFrom = $notification->starts_on;
        $oldTo = $notification->ends_on;
        $notification->update($data);
        $this->logAlertUpdate($booking, $notification->fresh(), $oldDescription, $oldFrom, $oldTo, $request);

        return response()->json(['success' => true, 'data' => $notification->fresh()]);
    }

    public function destroy(Booking $booking, BookingNotification $notification)
    {
        abort_unless((int) $notification->booking_id === (int) $booking->id, 404);
        $notification->delete();
        $this->logAlert('Cancel', '* Xóa Alert mã đăng ký', $booking, $notification, request());
        return response()->json(['success' => true, 'message' => 'Đã xóa thông báo.']);
    }

    /**
     * Dòng 45, 46: "* Tạo Alert cho mã đăng ký GAL27" / "* Xóa Alert mã đăng ký GAL27"
     * - Nội dung cảnh báo - Department - Từ ngày - Đến ngày.
     */
    private function logAlert(string $action, string $title, Booking $booking, BookingNotification $notification, ?Request $request): void
    {
        $code = ActivityLogService::bookingCode($booking->id);
        $desc = implode("\n", [
            "{$title} {$code}",
            "- Nội dung cảnh báo: {$notification->description}",
            '- Department: ' . ActivityScreen::department($request),
            '- Từ ngày: ' . ActivityLogService::d($notification->starts_on) . ' - Đến ngày: ' . ActivityLogService::d($notification->ends_on),
        ]);
        $this->writeAlertLog($action, $desc, $booking, $notification, $request);
    }

    /** Dòng 47: "* Sửa Alert mã đăng ký GAL27:" chỉ các thông tin thay đổi. */
    private function logAlertUpdate(Booking $booking, BookingNotification $notification, string $oldDescription, $oldFrom, $oldTo, ?Request $request): void
    {
        $code = ActivityLogService::bookingCode($booking->id);
        $lines = ["* Sửa Alert mã đăng ký {$code}:"];
        if ($oldDescription !== (string) $notification->description) {
            $lines[] = "- Nội dung cảnh báo: {$oldDescription} ➜ {$notification->description}";
        }
        $from = ActivityLogService::d($notification->starts_on);
        $to = ActivityLogService::d($notification->ends_on);
        if (ActivityLogService::d($oldFrom) !== $from || ActivityLogService::d($oldTo) !== $to) {
            $lines[] = '- Từ ngày: ' . ActivityLogService::d($oldFrom) . " ➜ {$from} - Đến ngày: " . ActivityLogService::d($oldTo) . " ➜ {$to}";
        } else {
            $lines[] = "- Từ ngày: {$from} - Đến ngày: {$to}";
        }
        $this->writeAlertLog('Modify', implode("\n", $lines), $booking, $notification, $request);
    }

    private function writeAlertLog(string $action, string $desc, Booking $booking, BookingNotification $notification, ?Request $request): void
    {
        try {
            $code = ActivityLogService::bookingCode($booking->id);
            ActivityLogService::record($action, $desc, [
                'screen' => ActivityScreen::REGISTRATION,
                'booking_code' => $code,
                'room_code' => $notification->scope_type === 'room' ? ($notification->booking_room_ids ?? []) : null,
                'target_type' => 'BookingNotification',
                'target_id' => $notification->id,
                'target_label' => $code,
            ], $request);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Log booking alert error: ' . $e->getMessage());
        }
    }

    private function validated(Request $request, Booking $booking): array
    {
        $data = $request->validate([
            'scope_type' => 'required|in:booking,room',
            'booking_room_ids' => 'nullable|array',
            'booking_room_ids.*' => 'string',
            'starts_on' => 'required|date',
            'ends_on' => 'required|date|after_or_equal:starts_on',
            'description' => 'required|string|max:5000',
        ]);

        $roomIds = array_values(array_unique($data['booking_room_ids'] ?? []));
        if ($data['scope_type'] === 'room') {
            if (!$roomIds) {
                throw ValidationException::withMessages([
                    'booking_room_ids' => 'Vui lòng chọn ít nhất một phòng.',
                ]);
            }
            $validCount = $booking->bookingRooms()->whereIn('id', $roomIds)->count();
            if ($validCount !== count($roomIds)) {
                throw ValidationException::withMessages([
                    'booking_room_ids' => 'Có phòng không thuộc đăng ký này.',
                ]);
            }
        }

        $data['booking_room_ids'] = $data['scope_type'] === 'room' ? $roomIds : null;
        return $data;
    }
}
