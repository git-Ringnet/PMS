<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingNotification;
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
        // The PMS business date can differ from the server's calendar date.
        $today = $requestDate = request('date');
        if (!$today || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $today)) {
            $today = now('Asia/Ho_Chi_Minh')->toDateString();
        }

        return response()->json([
            'success' => true,
            'data' => $booking->notifications()
                ->whereDate('starts_on', '<=', $today)
                ->whereDate('ends_on', '>=', $today)
                ->latest('id')
                ->get(),
        ]);
    }

    public function store(Request $request, Booking $booking)
    {
        $data = $this->validated($request, $booking);
        $data['created_by_user_id'] = $request->user()?->id;
        $data['updated_by_user_id'] = $request->user()?->id;

        $notification = $booking->notifications()->create($data);
        return response()->json(['success' => true, 'data' => $notification], 201);
    }

    public function update(Request $request, Booking $booking, BookingNotification $notification)
    {
        abort_unless((int) $notification->booking_id === (int) $booking->id, 404);
        $data = $this->validated($request, $booking);
        $data['updated_by_user_id'] = $request->user()?->id;
        $notification->update($data);

        return response()->json(['success' => true, 'data' => $notification->fresh()]);
    }

    public function destroy(Booking $booking, BookingNotification $notification)
    {
        abort_unless((int) $notification->booking_id === (int) $booking->id, 404);
        $notification->delete();
        return response()->json(['success' => true, 'message' => 'Đã xóa thông báo.']);
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
