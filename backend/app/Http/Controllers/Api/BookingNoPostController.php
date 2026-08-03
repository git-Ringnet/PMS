<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingNoPostController extends Controller
{
    public function updateBooking(Request $request, int $bookingId)
    {
        $data = $request->validate(['no_post' => ['required', 'boolean']]);
        $booking = Booking::findOrFail($bookingId);

        DB::transaction(function () use ($booking, $data) {
            $booking->update(['no_post' => $data['no_post']]);
            $booking->bookingRooms()->update(['no_post' => $data['no_post']]);
        });

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

        return response()->json([
            'success' => true,
            'data' => $room->fresh(),
        ]);
    }
}
