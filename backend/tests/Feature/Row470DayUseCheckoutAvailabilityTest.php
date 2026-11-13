<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Company;
use App\Models\CustomerSource;
use App\Models\Market;
use App\Models\RegistrationStatus;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\User;
use App\Services\RoomAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Row470DayUseCheckoutAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private RoomClass $roomClass;
    private int $registrationStatusId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\BookingStatusSeeder::class);
        $this->seed(\Database\Seeders\SystemDefinitionSeeder::class);
        $this->actingAs(User::factory()->withRole('super_admin')->create(['username' => 'row470_user']));
        \App\Models\SystemDateRoll::create(['system_date' => '2026-10-30 00:00:00', 'actual_date' => '2026-10-30 00:00:00', 'shift' => '1', 'username' => 'row470_user']);

        $this->registrationStatusId = (int) RegistrationStatus::where('is_availability', true)
            ->value('booking_status_id');
        $this->roomClass = RoomClass::create([
            'code' => 'DU470',
            'name' => 'Day Use 470',
            'is_active' => true,
        ]);
        $form = RoomForm::first() ?? RoomForm::create(['name' => 'Double']);

        foreach (['807', '808'] as $roomNumber) {
            Room::create([
                'room_number' => $roomNumber,
                'room_class_id' => $this->roomClass->id,
                'room_form_id' => $form->id,
                'floor' => 8,
                'status' => 'vacant_ready',
            ]);
        }
    }

    public function test_checked_out_day_use_releases_occ_av_and_physical_room_assignment(): void
    {
        $checkedOut = $this->stay('807', BookingRoom::STATUS_CHECKED_OUT, true);
        $checkedIn = $this->stay('808', BookingRoom::STATUS_CHECKED_IN, true);
        $service = app(RoomAvailabilityService::class);

        $this->assertSame(1, $service->getBookedCount($this->roomClass->id, '2026-10-30', '2026-10-30'));
        $this->assertNull($service->findRoomNumberConflict('807', '2026-10-30', '2026-10-30'));
        $this->assertNotNull($service->findRoomNumberConflict('808', '2026-10-30', '2026-10-30'));

        $vacantRoomNumbers = collect($this->getJson('/api/rooms/vacant?' . http_build_query([
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-10-30',
            'departure_date' => '2026-10-30',
        ]))->assertSuccessful()->json('data'))->pluck('room_number')->all();
        $this->assertContains('807', $vacantRoomNumbers);
        $this->assertNotContains('808', $vacantRoomNumbers);

        $availability = $this->getJson('/api/availability?start_date=2026-10-30&end_date=2026-10-30')
            ->assertSuccessful();
        $availability->assertJsonPath('grid.DU470.2026-10-30.occ', 1);
        $availability->assertJsonPath('grid.DU470.2026-10-30.av', 1);
        $availability->assertJsonCount(1, 'occ_bookings.DU470');
        $this->assertSame($checkedIn->id, $availability->json('occ_bookings.DU470.0.booking_room_id'));

        $details = $this->getJson('/api/availability/details?' . http_build_query([
            'date' => '2026-10-30',
            'metric' => 'OCC',
            'room_class_id' => $this->roomClass->id,
        ]))->assertSuccessful();
        $details->assertJsonCount(1, 'booking_rooms');
        $this->assertSame('808', $details->json('booking_rooms.0.room_number'));

        $company = Company::create(['name' => 'Row 470 Company']);
        $market = Market::first() ?? Market::create(['name' => 'Row 470 Market', 'code' => 'R470']);
        $source = CustomerSource::first() ?? CustomerSource::create(['name' => 'Row 470 Source', 'code' => 'R470']);
        $this->postJson('/api/bookings', [
            'booking_name' => 'Reuse room 807 after Day Use checkout',
            'company_id' => $company->id,
            'market_id' => $market->id,
            'customer_source_id' => $source->id,
            'arrival_date' => '2026-10-30',
            'departure_date' => '2026-10-30',
            'num_of_days' => 0,
            'is_day_use' => true,
            'registration_status_id' => $this->registrationStatusId,
            'room_allocations' => [[
                'roomClassId' => $this->roomClass->id,
                'quantity' => 1,
                'price' => 500000,
                'rooms' => [[
                    'roomNumber' => '807',
                    'arrivalDate' => '2026-10-30',
                    'departureDate' => '2026-10-30',
                    'is_day_use' => true,
                ]],
            ]],
        ])->assertSuccessful();
        $this->assertDatabaseHas('booking_rooms', [
            'room_number' => '807',
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        $this->assertSame(BookingRoom::STATUS_CHECKED_OUT, $checkedOut->fresh()->status);
    }

    public function test_checked_out_overnight_stay_keeps_its_existing_conflict_behavior(): void
    {
        $this->stay('807', BookingRoom::STATUS_CHECKED_OUT, false, '2026-10-31', '2026-11-01');

        $this->assertNotNull(app(RoomAvailabilityService::class)->findRoomNumberConflict(
            '807',
            '2026-10-31',
            '2026-11-01',
        ));
    }

    private function stay(
        string $roomNumber,
        int $status,
        bool $isDayUse,
        string $arrivalDate = '2026-10-30',
        string $departureDate = '2026-10-30',
    ): BookingRoom {
        $booking = Booking::create([
            'booking_name' => "Row 470 {$roomNumber}",
            'booking_date' => $arrivalDate,
            'arrival_date' => $arrivalDate,
            'departure_date' => $departureDate,
            'is_day_use' => $isDayUse,
            'status' => Booking::STATUS_RESERVATION,
            'registration_status_id' => $this->registrationStatusId,
            'created_by' => 'row470_user',
        ]);

        return BookingRoom::create([
            'booking_id' => $booking->id,
            'room_number' => $roomNumber,
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => $arrivalDate,
            'departure_date' => $departureDate,
            'is_day_use' => $isDayUse,
            'rate' => 500000,
            'status' => $status,
        ]);
    }
}
