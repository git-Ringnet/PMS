<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Company;
use App\Models\CustomerSource;
use App\Models\HotelConfig;
use App\Models\HotelSetting;
use App\Models\Market;
use App\Models\Permission;
use App\Models\RegistrationStatus;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\SystemDateRoll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingAllocationConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private RoomClass $roomClass;
    private RoomForm $roomForm;
    private RegistrationStatus $registrationStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['username' => 'allocation_test_user']);
        $role = Role::create([
            'code' => 'allocation_consistency_test',
            'name' => 'Allocation consistency test',
            'level' => 3,
            'department_scope' => 'FO',
            'is_active' => true,
        ]);
        foreach (['fo.booking.create', 'fo.booking.edit'] as $code) {
            $permission = Permission::firstOrCreate(
                ['code' => $code],
                ['name' => $code, 'module' => 'FO']
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $this->user->roles()->attach($role->id);
        $this->actingAs($this->user);

        DB::table('booking_statuses')->insertOrIgnore([
            ['id' => 0, 'name' => 'Reservation'],
            ['id' => 1, 'name' => 'Checked In'],
            ['id' => 2, 'name' => 'Checked Out'],
            ['id' => 3, 'name' => 'Cancelled'],
            ['id' => 4, 'name' => 'No Show'],
            ['id' => 100, 'name' => 'Moved'],
        ]);

        SystemDateRoll::create([
            'system_date' => '2026-08-07 00:00:00',
            'actual_date' => '2026-08-07 00:00:00',
            'shift' => '1',
            'username' => 'allocation_test_user',
        ]);

        $this->roomForm = RoomForm::create(['name' => 'Standard Form']);
        $this->roomClass = RoomClass::create([
            'code' => 'STD',
            'name' => 'Standard Class',
            'is_active' => true,
        ]);
        for ($number = 101; $number <= 108; $number++) {
            $this->createPhysicalRoom((string) $number, $this->roomClass);
        }

        $this->registrationStatus = RegistrationStatus::create([
            'name' => 'Guaranteed',
            'booking_status_id' => 1,
            'is_availability' => true,
        ]);

        Market::create(['id' => 1, 'name' => 'FIT', 'code' => 'FIT']);
        CustomerSource::create(['id' => 1, 'name' => 'WalkIn', 'code' => 'WI']);
        Company::create(['id' => 1, 'name' => 'WalkIn Company', 'code' => 'WIC']);
        HotelConfig::create(['name' => 'AllowOverRoomTypeRoomKind', 'value' => '0']);
        HotelConfig::create(['name' => 'FrmOOO_DefineLockByTime', 'value' => '23:59']);
        HotelSetting::create([
            'hotel_name' => 'Allocation Test Hotel',
            'breakfast_child_rate' => 90000,
            'booking_auto_extra_charge_bf_child' => 1,
        ]);
    }

    private function createPhysicalRoom(string $number, RoomClass $roomClass): Room
    {
        return Room::create([
            'room_number' => $number,
            'room_class_id' => $roomClass->id,
            'room_form_id' => $this->roomForm->id,
            'floor' => 1,
            'status' => 'available',
        ]);
    }

    private function createBooking(array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'booking_name' => 'Allocation Test Booking',
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'booking_date' => '2026-08-07',
            'created_by' => 'allocation_test_user',
            'status' => Booking::STATUS_RESERVATION,
            'registration_status_id' => $this->registrationStatus->booking_status_id,
        ], $attributes));
    }

    private function updatePayload(Booking $booking, array $allocations): array
    {
        return [
            'booking_name' => $booking->booking_name,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'num_of_days' => 1,
            'registration_status_id' => $this->registrationStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
            'room_allocations' => $allocations,
        ];
    }

    public function test_reallocation_quantity_ignores_history_and_creates_requested_current_rooms(): void
    {
        $booking = $this->createBooking();
        $cancelled = BookingRoom::create([
            'id' => 'G-ALLOC-CANCELLED',
            'booking_id' => $booking->id,
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'status' => BookingRoom::STATUS_CANCELLED,
        ]);
        $moved = BookingRoom::create([
            'id' => 'G-ALLOC-MOVED',
            'booking_id' => $booking->id,
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'status' => BookingRoom::STATUS_MOVED,
        ]);

        $details = array_fill(0, 5, [
            'roomNumber' => null,
            'guestName' => 'Reallocated Guest',
        ]);
        $response = $this->putJson(
            "/api/bookings/{$booking->id}",
            $this->updatePayload($booking, [[
                'roomClassId' => $this->roomClass->id,
                'quantity' => 5,
                'price' => 100000,
                'rooms' => $details,
            ]])
        );

        $response->assertSuccessful();
        $this->assertDatabaseHas('booking_rooms', [
            'id' => $cancelled->id,
            'status' => BookingRoom::STATUS_CANCELLED,
        ]);
        $this->assertDatabaseHas('booking_rooms', [
            'id' => $moved->id,
            'status' => BookingRoom::STATUS_MOVED,
        ]);
        $this->assertSame(
            5,
            BookingRoom::where('booking_id', $booking->id)
                ->where('status', BookingRoom::STATUS_BOOKED)
                ->count()
        );
    }

    public function test_zero_quantity_history_does_not_trigger_wrong_room_type_validation(): void
    {
        $jst = RoomClass::create([
            'code' => 'JST',
            'name' => 'Junior Suite',
            'is_active' => true,
        ]);
        $this->createPhysicalRoom('201', $jst);

        $booking = $this->createBooking();
        $cancelledJst = BookingRoom::create([
            'id' => 'G-ALLOC-JST-CANCELLED',
            'booking_id' => $booking->id,
            'room_class_id' => $jst->id,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'status' => BookingRoom::STATUS_CANCELLED,
        ]);

        $response = $this->putJson(
            "/api/bookings/{$booking->id}",
            $this->updatePayload($booking, [
                [
                    'roomClassId' => $this->roomClass->id,
                    'quantity' => 1,
                    'rooms' => [['roomNumber' => null, 'guestName' => 'FAM Guest']],
                ],
                [
                    'roomClassId' => $jst->id,
                    'quantity' => 0,
                    'rooms' => [['bookingRoomId' => $cancelledJst->id]],
                ],
            ])
        );

        $response->assertSuccessful();
        $this->assertDatabaseHas('booking_rooms', [
            'id' => $cancelledJst->id,
            'status' => BookingRoom::STATUS_CANCELLED,
        ]);
    }

    public function test_partial_inhouse_update_counts_only_new_demand_and_preserves_inhouse_room(): void
    {
        $booking = $this->createBooking(['status' => Booking::STATUS_CHECKIN]);
        $inhouse = BookingRoom::create([
            'id' => 'G-ALLOC-INHOUSE',
            'booking_id' => $booking->id,
            'room_number' => '101',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'status' => BookingRoom::STATUS_CHECKED_IN,
        ]);

        $response = $this->putJson(
            "/api/bookings/{$booking->id}",
            $this->updatePayload($booking, [[
                'roomClassId' => $this->roomClass->id,
                'quantity' => 2,
                'rooms' => [
                    ['bookingRoomId' => $inhouse->id, 'roomNumber' => '101'],
                    ['roomNumber' => null, 'guestName' => 'Additional Guest'],
                ],
            ]])
        );

        $response->assertSuccessful();
        $inhouse->refresh();
        $this->assertSame(BookingRoom::STATUS_CHECKED_IN, (int) $inhouse->status);
        $this->assertSame(
            1,
            BookingRoom::where('booking_id', $booking->id)
                ->where('status', BookingRoom::STATUS_BOOKED)
                ->count()
        );
    }

    public function test_persisted_booking_room_id_cannot_be_reused_across_allocation_rows(): void
    {
        $booking = $this->createBooking();
        $booked = BookingRoom::create([
            'id' => 'G-ALLOC-DUPLICATE',
            'booking_id' => $booking->id,
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        $response = $this->putJson(
            "/api/bookings/{$booking->id}",
            $this->updatePayload($booking, [
                [
                    'roomClassId' => $this->roomClass->id,
                    'quantity' => 1,
                    'rooms' => [['bookingRoomId' => $booked->id]],
                ],
                [
                    'roomClassId' => $this->roomClass->id,
                    'quantity' => 1,
                    'rooms' => [['bookingRoomId' => $booked->id]],
                ],
            ])
        );

        $response->assertStatus(422);
        $this->assertStringContainsString('không được lặp lại', (string) $response->json('message'));
        $this->assertDatabaseHas('booking_rooms', [
            'id' => $booked->id,
            'status' => BookingRoom::STATUS_BOOKED,
        ]);
    }

    public function test_new_multi_room_booking_reaches_availability_without_hotel_setting_method_error(): void
    {
        $classes = [];
        foreach (['SUPD', 'SUPT', 'SUPTR'] as $index => $code) {
            $roomClass = RoomClass::create([
                'code' => $code,
                'name' => "Regression {$code}",
                'is_active' => true,
            ]);
            $classes[] = $roomClass;

            foreach (range(1, 2) as $roomIndex) {
                $this->createPhysicalRoom(
                    (string) (($index + 2) * 100 + $roomIndex),
                    $roomClass
                );
            }
        }

        $allocations = array_map(function (RoomClass $roomClass): array {
            return [
                'roomClassId' => $roomClass->id,
                'quantity' => 2,
                'price' => 100000,
                'rooms' => array_fill(0, 2, [
                    'roomClassId' => $roomClass->id,
                    'roomNumber' => null,
                    'guestName' => 'eee regression guest',
                    'arrivalDate' => '2026-08-09',
                    'departureDate' => '2026-08-10',
                ]),
            ];
        }, $classes);

        $response = $this->postJson('/api/bookings', [
            'booking_name' => 'eeee',
            'arrival_date' => '2026-08-09',
            'departure_date' => '2026-08-10',
            'num_of_days' => 1,
            'registration_status_id' => $this->registrationStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
            'room_allocations' => $allocations,
        ]);

        $response->assertSuccessful();
        $booking = Booking::where('booking_name', 'eeee')->firstOrFail();
        $this->assertSame(6, BookingRoom::where('booking_id', $booking->id)->count());
        foreach ($classes as $roomClass) {
            $this->assertSame(
                2,
                BookingRoom::where('booking_id', $booking->id)
                    ->where('room_class_id', $roomClass->id)
                    ->where('status', BookingRoom::STATUS_BOOKED)
                    ->count()
            );
        }
    }
}
