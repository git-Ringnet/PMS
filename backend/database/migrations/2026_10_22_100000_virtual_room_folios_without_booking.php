<?php

use App\Models\BookingRoom;
use App\Services\VirtualRoomFolioService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phòng ảo không còn booking ẩn (is_service_only) để số BK liền mạch:
 * booking_rooms.booking_id cho phép NULL; folio phòng ảo chỉ gồm booking_rooms + guest + booking_room_guests.
 * Booking ẩn đã tạo trước đây được gỡ: dòng phòng/bill/thanh toán bỏ liên kết booking rồi xóa mềm booking ẩn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_rooms', function (Blueprint $table) {
            $table->unsignedBigInteger('booking_id')->nullable()->change();
        });

        $service = app(VirtualRoomFolioService::class);
        $anchorBookingIds = DB::table('bookings')
            ->where('is_service_only', true)
            ->whereNull('deleted_at')
            ->pluck('id');

        foreach ($anchorBookingIds as $bookingId) {
            DB::transaction(function () use ($bookingId, $service) {
                $roomIds = DB::table('booking_rooms')->where('booking_id', $bookingId)->pluck('id');

                DB::table('booking_rooms')->where('booking_id', $bookingId)->update(['booking_id' => null]);
                DB::table('service_bills')->where('RegisterId1', $bookingId)->whereIn('RentalRoomId1', $roomIds)->update(['RegisterId1' => null]);
                DB::table('service_bills')->where('RegisterID2', $bookingId)->whereIn('RentalRoomId2', $roomIds)->update(['RegisterID2' => null]);
                DB::table('payments')->where('booking_id', $bookingId)->update(['booking_id' => null]);
                if (Schema::hasColumn('sales_invoices', 'booking_id')) {
                    DB::table('sales_invoices')->where('booking_id', $bookingId)->update(['booking_id' => null]);
                }
                DB::table('bookings')->where('id', $bookingId)->update(['deleted_at' => now()]);

                BookingRoom::whereIn('id', $roomIds)->get()
                    ->each(fn (BookingRoom $room) => $service->attachNamedGuest($room));
            });
        }
    }

    public function down(): void
    {
        // Không tự dựng lại booking ẩn: folio phòng ảo có thể đã nhận bill/thanh toán sau khi chuyển đổi.
    }
};
