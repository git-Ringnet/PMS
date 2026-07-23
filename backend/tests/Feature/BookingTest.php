<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingRoomGuest;
use App\Models\Guest;
use App\Models\User;
use App\Models\RegistrationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed standard statuses/definitions if required
        $this->artisan('db:seed', ['--class' => 'SystemConfigurationSeeder']);
        $this->artisan('db:seed', ['--class' => 'HotelDefinitionSeeder']);
        $this->artisan('db:seed', ['--class' => 'SpecialRequestSeeder']);
        $this->artisan('db:seed', ['--class' => 'SystemDateRollSeeder']);
        $this->artisan('db:seed', ['--class' => 'SystemDefinitionSeeder']);
        $this->artisan('db:seed', ['--class' => 'BookingStatusSeeder']);
    }

    public function test_booking_creation_automatically_creates_guest_and_generates_g_prefixed_ids()
    {
        $user = User::factory()->create();
        $this->withoutExceptionHandling();
        
        $status = RegistrationStatus::first();
        $company = \App\Models\Company::create(['name' => 'Test Company']);
        $market = \App\Models\Market::first() ?? \App\Models\Market::create(['name' => 'Test Market', 'code' => 'TM']);
        $customerSource = \App\Models\CustomerSource::first() ?? \App\Models\CustomerSource::create(['name' => 'Test Source', 'code' => 'TS']);

        $payload = [
            'booking_name' => 'NGUYEN VAN A',
            'company_id' => $company->id,
            'market_id' => $market->id,
            'customer_source_id' => $customerSource->id,
            'arrival_date' => now()->toDateString(),
            'departure_date' => now()->addDay()->toDateString(),
            'num_of_days' => 1,
            'registration_status_id' => $status->id,
            'room_allocations' => [
                [
                    'roomClassId' => 1,
                    'quantity' => 1,
                    'price' => 1000000,
                    'rooms' => [
                        [
                            'roomNumber' => '105',
                            'guestName' => 'NGUYEN VAN A',
                            'adults' => 2,
                            'children' => 1,
                            'babies' => 0,
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/bookings', $payload);

        $response->assertSuccessful();

        // Assert booking was created
        $booking = Booking::first();
        $this->assertNotNull($booking);
        $this->assertEquals('NGUYEN VAN A', $booking->booking_name);

        // Assert booking_room was created with string ID starting with "G"
        $bookingRoom = BookingRoom::first();
        $this->assertNotNull($bookingRoom);
        $this->assertStringStartsWith('G', $bookingRoom->id);

        // Assert guest was created (defaulting to NGUYEN VAN A)
        $guest = Guest::first();
        $this->assertNotNull($guest);
        $this->assertEquals('NGUYEN VAN A', $guest->full_name);

        // Assert pivot record was created linking them
        $pivot = BookingRoomGuest::first();
        $this->assertNotNull($pivot);
        $this->assertEquals($bookingRoom->id, $pivot->booking_room_id);
        $this->assertEquals($guest->id, $pivot->guest_id);
    }
}
