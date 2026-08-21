<?php

namespace Tests\Feature\Booking;

use App\Http\Resources\RegistrationStatusResource;
use App\Models\Booking;
use App\Models\RegistrationStatus;
use App\Services\RegistrationStatusMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingStatusMappingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('booking_statuses')->insert([
            ['id' => 0, 'name' => 'Reservation'],
            ['id' => 1, 'name' => 'Inhouse'],
        ]);
    }

    public function test_booking_stores_registration_status_primary_key_not_legacy_code(): void
    {
        $registrationStatus = RegistrationStatus::create([
            'booking_status_id' => 20,
            'name' => 'None Guaranteed',
            'is_availability' => true,
        ]);

        $booking = Booking::create([
            'booking_name' => 'Mapping test',
            'arrival_date' => '2026-08-21',
            'departure_date' => '2026-08-22',
            'num_of_days' => 1,
            'booking_date' => '2026-08-21',
            'status' => Booking::STATUS_RESERVATION,
            'registration_status_id' => $registrationStatus->id,
            'created_by' => 'test',
        ]);

        $this->assertSame($registrationStatus->id, $booking->registration_status_id);
        $this->assertNotSame($registrationStatus->booking_status_id, $booking->registration_status_id);
        $this->assertTrue($booking->registrationStatus->is($registrationStatus));
    }

    public function test_legacy_status_code_is_resolved_to_registration_status_id(): void
    {
        $registrationStatus = RegistrationStatus::create([
            'booking_status_id' => 24,
            'name' => 'Cancelled with Penalty',
            'is_availability' => false,
        ]);

        $legacyStatusCode = 24;
        $mappedStatusId = RegistrationStatusMapper::idFromLegacyCode($legacyStatusCode);
        $this->assertSame($registrationStatus->id, $mappedStatusId);
        $mappedStatus = RegistrationStatus::findOrFail($mappedStatusId);

        $booking = Booking::create([
            'booking_name' => 'Legacy mapping test',
            'arrival_date' => '2026-08-21',
            'departure_date' => '2026-08-22',
            'num_of_days' => 1,
            'booking_date' => '2026-08-21',
            'status' => Booking::STATUS_RESERVATION,
            'registration_status_id' => $mappedStatus->id,
            'created_by' => 'test',
        ]);

        $this->assertSame($registrationStatus->id, $booking->registration_status_id);
        $this->assertSame($legacyStatusCode, $booking->registrationStatus->booking_status_id);
    }

    public function test_operational_booking_status_does_not_replace_registration_status(): void
    {
        $registrationStatus = RegistrationStatus::create([
            'booking_status_id' => 1,
            'name' => 'Guaranteed',
            'is_availability' => true,
        ]);

        $booking = Booking::create([
            'booking_name' => 'Operational status test',
            'arrival_date' => '2026-08-21',
            'departure_date' => '2026-08-22',
            'num_of_days' => 1,
            'booking_date' => '2026-08-21',
            'status' => Booking::STATUS_RESERVATION,
            'registration_status_id' => $registrationStatus->id,
            'created_by' => 'test',
        ]);

        $booking->update(['status' => Booking::STATUS_CHECKIN]);
        $booking->refresh();

        $this->assertSame(Booking::STATUS_CHECKIN, $booking->status);
        $this->assertSame($registrationStatus->id, $booking->registration_status_id);
    }

    public function test_registration_status_resource_keeps_new_id_and_legacy_code_separate(): void
    {
        $registrationStatus = RegistrationStatus::create([
            'booking_status_id' => 29,
            'name' => 'Waiting',
            'is_availability' => true,
        ]);

        $payload = (new RegistrationStatusResource($registrationStatus))->resolve();

        $this->assertSame($registrationStatus->id, $payload['id']);
        $this->assertSame(29, $payload['booking_status_id']);
        $this->assertSame(29, $payload['BookingStatusId']);
        $this->assertNotSame($payload['id'], $payload['booking_status_id']);
    }
}
