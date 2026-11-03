<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Services\ActivityLogService;
use App\Support\ActivityScreen;
use Illuminate\Http\Request;

class BookingNoPostController extends Controller
{
    public function updateBooking(Request $request, int $bookingId)
    {
        $data = $request->validate(['no_post' => ['required', 'boolean']]);
        $booking = Booking::findOrFail($bookingId);

        $booking->update(['no_post' => $data['no_post']]);

        // Dòng 63: thao tác ở đăng ký thì nội dung không có phòng và cột Mã phòng để trống
        $code = ActivityLogService::bookingCode($booking->id);
        $this->log(($data['no_post'] ? 'Bật' : 'Tắt') . " trạng thái No Post cho Mã đăng ký {$code}", $code, null, $request);

        return response()->json([
            'success' => true,
            'data' => $booking->fresh(['bookingRooms']),
        ]);
    }

    public function updateRoom(Request $request, string $roomId)
    {
        $data = $request->validate(['no_post' => ['required', 'boolean']]);
        $room = BookingRoom::findOrFail($roomId);
        $room->update(['no_post' => $data['no_post']]);

        // Dòng 63: "* Bật trạng thái No Post cho Mã đăng ký GAL27, phòng: 405"
        $code = ActivityLogService::bookingCode($room->booking_id);
        $this->log(($data['no_post'] ? 'Bật' : 'Tắt') . " trạng thái No Post cho Mã đăng ký {$code}, phòng: " . ($room->room_number ?: $room->id), $code, $room->id, $request);

        return response()->json([
            'success' => true,
            'data' => $room->fresh(),
        ]);
    }

    private function log(string $text, ?string $code, ?string $roomId, Request $request): void
    {
        try {
            ActivityLogService::record('Modify', "* {$text}", [
                'screen' => ActivityScreen::CHECKOUT,
                'booking_code' => $code,
                'room_code' => $roomId,
                'target_type' => $roomId ? 'BookingRoom' : 'Booking',
                'target_id' => $roomId,
                'target_label' => $code,
            ], $request);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Log no-post error: ' . $e->getMessage());
        }
    }
}
