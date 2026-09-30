<?php

use App\Models\Booking;
use App\Models\BookingRoom;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('bookings', 'is_service_only')
            || !Schema::hasColumn('booking_rooms', 'room_class_id')
            || !Schema::hasColumn('rooms', 'is_internal')) {
            throw new \RuntimeException('Virtual-room folios require service-only booking support and internal room flags.');
        }

        $systemDate = DB::table('system_date_rolls')->orderByDesc('id')->value('system_date');
        $arrivalDate = $systemDate
            ? Carbon::parse($systemDate)->toDateString()
            : now('Asia/Ho_Chi_Minh')->toDateString();
        $departureDate = Carbon::parse($arrivalDate)->addDay()->toDateString();

        DB::table('rooms')
            ->where('is_internal', true)
            ->where('room_number', 'like', '0%')
            ->orderBy('room_number')
            ->get(['room_number', 'room_class_id'])
            ->each(function (object $room) use ($arrivalDate, $departureDate): void {
                DB::transaction(function () use ($room, $arrivalDate, $departureDate): void {
                    $lockedRoom = DB::table('rooms')
                        ->where('room_number', $room->room_number)
                        ->where('is_internal', true)
                        ->where('room_number', 'like', '0%')
                        ->lockForUpdate()
                        ->first(['room_number', 'room_class_id']);

                    if (!$lockedRoom || $this->hasActiveFolio($lockedRoom->room_number)) {
                        return;
                    }

                    $booking = Booking::create([
                        'booking_name' => 'PHÒNG ẢO ' . $lockedRoom->room_number,
                        'arrival_date' => $arrivalDate,
                        'departure_date' => $departureDate,
                        'num_of_days' => 1,
                        'booking_date' => $arrivalDate,
                        'status' => Booking::STATUS_RESERVATION,
                        'is_service_only' => true,
                        'is_master_room_rate' => false,
                        'created_by' => 'system',
                        'updated_by' => 'system',
                        'module' => 'FO',
                    ]);

                    BookingRoom::create([
                        'booking_id' => $booking->id,
                        'room_number' => $lockedRoom->room_number,
                        'room_class_id' => $lockedRoom->room_class_id,
                        'arrival_date' => $arrivalDate,
                        'departure_date' => $departureDate,
                        'actual_arrival_date' => $arrivalDate,
                        'planned_departure_date' => $departureDate,
                        'NumOfDays' => 1,
                        'ActutalNumOfDays' => 1,
                        'status' => BookingRoom::STATUS_BOOKED,
                        'rate' => 0,
                        'base_price' => 0,
                        'adults' => 0,
                        'babies' => 0,
                        'children_qty' => 0,
                        'created_by' => 'system',
                        'updated_by' => 'system',
                    ]);
                });
            });
    }

    public function down(): void
    {
        // A service-only folio can receive bills/payments after creation.
        // Keep operational data on rollback rather than deleting it automatically.
    }

    private function hasActiveFolio(string $roomNumber): bool
    {
        return DB::table('booking_rooms as booking_room')
            ->join('bookings as booking', 'booking.id', '=', 'booking_room.booking_id')
            ->where('booking_room.room_number', $roomNumber)
            ->whereNull('booking_room.deleted_at')
            ->whereNull('booking.deleted_at')
            ->whereIn('booking_room.status', [BookingRoom::STATUS_BOOKED, BookingRoom::STATUS_CHECKED_IN])
            ->exists();
    }
};
