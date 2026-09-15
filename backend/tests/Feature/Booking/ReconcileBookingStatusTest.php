<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingRoom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReconcileBookingStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_preserves_data_and_apply_only_repairs_inhouse_headers(): void
    {
        DB::table('booking_statuses')->insert([['id' => 0, 'name' => 'Reservation'], ['id' => 1, 'name' => 'Inhouse']]);
        $fields = ['booking_name' => 'Reconcile', 'arrival_date' => '2026-09-09', 'departure_date' => '2026-09-12', 'booking_date' => '2026-09-09', 'status' => 0, 'created_by' => 'test'];
        $booking = Booking::create($fields);
        $untouched = Booking::create($fields);
        $roomClass = \App\Models\RoomClass::create(['code' => 'RECON', 'name' => 'Reconcile test']);
        BookingRoom::create(['booking_id' => $booking->id, 'room_class_id' => $roomClass->id, 'arrival_date' => '2026-09-09', 'departure_date' => '2026-09-12', 'status' => 1]);
        $this->artisan('bookings:reconcile-inhouse-status')->assertSuccessful();
        $this->assertSame(0, $booking->fresh()->status);
        $this->artisan('bookings:reconcile-inhouse-status', ['--apply' => true])->assertSuccessful();
        $this->assertSame(1, $booking->fresh()->status);
        $this->assertSame(0, $untouched->fresh()->status);
        $this->artisan('bookings:reconcile-inhouse-status', ['--apply' => true])->expectsOutput('Reconciled candidates: 0')->assertSuccessful();
    }
}
