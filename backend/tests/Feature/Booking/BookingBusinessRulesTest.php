<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingChild;
use App\Models\BookingChildBreakfastDetail;
use App\Models\BookingCancelLog;
use App\Models\BookingRoom;
use App\Models\BookingRoomGuest;
use App\Models\BookingRoomService;
use App\Models\Guest;
use App\Models\HotelConfig;
use App\Models\HotelSetting;
use App\Models\Permission;
use App\Models\RegistrationStatus;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\RoomLock;
use App\Models\RoomRateCode;
use App\Models\RoomRateDailyMapping;
use App\Models\RoomRatePlan;
use App\Models\ServiceBill;
use App\Models\StandardRate;
use App\Models\SystemDateRoll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingBusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected RoomClass $roomClass;
    protected RoomForm $roomForm;
    protected Room $room101;
    protected Room $room102;
    protected RegistrationStatus $regStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['username' => 'test_user']);
        $role = Role::create(['code' => 'booking_rules_test', 'name' => 'Booking rules test', 'level' => 3, 'department_scope' => 'FO', 'is_active' => true]);
        foreach (['fo.booking.create', 'fo.booking.edit', 'fo.frontdesk.view', 'fo.checkin', 'fo.checkout', 'fo.room.move', 'fo.service.view', 'fo.service.add', 'fo.service.edit', 'fo.service.delete'] as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'FO']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $this->user->roles()->attach($role->id);
        $this->actingAs($this->user);

        // Seed booking statuses
        DB::table('booking_statuses')->insertOrIgnore([
            ['id' => 0, 'name' => 'Reservation'],
            ['id' => 1, 'name' => 'Checked In'],
            ['id' => 2, 'name' => 'Checked Out'],
            ['id' => 3, 'name' => 'Cancelled'],
            ['id' => 4, 'name' => 'No Show'],
            ['id' => 100, 'name' => 'Chuyển phòng'],
        ]);

        // Seed system date
        SystemDateRoll::create([
            'system_date' => '2026-08-07 00:00:00',
            'actual_date' => '2026-08-07 00:00:00',
            'shift'       => '1',
            'username'    => 'test_user',
        ]);

        $this->roomForm = RoomForm::create(['name' => 'Standard Form']);
        $this->roomClass = RoomClass::create([
            'code'      => 'STD',
            'name'      => 'Standard Class',
            'is_active' => true,
        ]);

        // Create standard physical rooms
        $this->room101 = Room::create([
            'room_number'   => '101',
            'room_class_id' => $this->roomClass->id,
            'room_form_id'  => $this->roomForm->id,
            'floor'         => 1,
            'status'        => 'available',
        ]);

        $this->room102 = Room::create([
            'room_number'   => '102',
            'room_class_id' => $this->roomClass->id,
            'room_form_id'  => $this->roomForm->id,
            'floor'         => 1,
            'status'        => 'available',
        ]);

        $this->regStatus = RegistrationStatus::create([
            'name' => 'Guaranteed',
            'booking_status_id' => 1,
            'is_availability' => true,
        ]);

        // Create a standard market and customer source
        DB::table('markets')->insertOrIgnore([
            ['id' => 1, 'name' => 'FIT', 'code' => 'FIT', 'created_at' => now(), 'updated_at' => now()]
        ]);
        DB::table('customer_sources')->insertOrIgnore([
            ['id' => 1, 'name' => 'WalkIn', 'code' => 'WI', 'created_at' => now(), 'updated_at' => now()]
        ]);
        DB::table('companies')->insertOrIgnore([
            ['id' => 1, 'name' => 'WalkIn Company', 'code' => 'WIC', 'created_at' => now(), 'updated_at' => now()]
        ]);

        // Seed configs
        HotelConfig::create(['name' => 'AllowOverRoomTypeRoomKind', 'value' => '0']);
        HotelConfig::create(['name' => 'AllowCheckinVacantClean', 'value' => '0']);
        HotelConfig::create(['name' => 'IsCheckBookingStatusWhenCheckin', 'value' => '0']);
        
        HotelSetting::create([
            'hotel_name' => 'Test Hotel',
            'breakfast_child_rate' => 90000,
            'booking_auto_extra_charge_bf_child' => 1
        ]);
    }

    private function grantBookingCancelPermission(): void
    {
        $role = Role::firstOrCreate(
            ['code' => 'booking_cancel_test'],
            ['name' => 'Booking cancel test', 'level' => 3, 'department_scope' => 'FO', 'is_active' => true]
        );
        $permission = Permission::firstOrCreate(
            ['code' => 'fo.booking.cancel'],
            ['name' => 'Hủy đặt phòng', 'module' => 'FO']
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $this->user->roles()->syncWithoutDetaching([$role->id]);
    }

    /**
     * Helper to create a booking with default required fields
     */
    protected function createBooking(array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'booking_name' => 'Test Booking',
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'booking_date' => '2026-08-07',
            'created_by'   => 'test_user',
            'status'       => Booking::STATUS_RESERVATION,
        ], $attributes));
    }

    /**
     * TC-01: Double Booking is blocked when AV is insufficient (under AllowOverRoomTypeRoomKind = 0).
     */
    public function test_double_booking_is_blocked_when_av_insufficient(): void
    {
        // 1. Create a booking that allocates STD room class for 1 room
        $payload = [
            'booking_name' => 'Booking A',
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'num_of_days' => 1,
            'registration_status_id' => $this->regStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
            'room_allocations' => [
                [
                    'roomClassId' => $this->roomClass->id,
                    'quantity' => 2, // STD class has only 2 rooms in database (101, 102)
                    'price' => 1000000,
                    'rooms' => [
                        ['roomNumber' => '101', 'guestName' => 'Guest A1'],
                        ['roomNumber' => '102', 'guestName' => 'Guest A2']
                    ]
                ]
            ]
        ];

        $response = $this->postJson('/api/bookings', $payload);
        $response->assertSuccessful();

        // 2. Try to create another booking that tries to allocate the same class STD. Since AV is now 0, it should be blocked.
        $payloadB = [
            'booking_name' => 'Booking B',
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'num_of_days' => 1,
            'registration_status_id' => $this->regStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
            'room_allocations' => [
                [
                    'roomClassId' => $this->roomClass->id,
                    'quantity' => 1,
                    'price' => 1000000,
                    'rooms' => [
                        ['roomNumber' => null, 'guestName' => 'Guest B']
                    ]
                ]
            ]
        ];

        $responseB = $this->postJson('/api/bookings', $payloadB);
        $responseB->assertStatus(422);
        $responseB->assertJsonFragment([
            'success' => false
        ]);
        $this->assertStringContainsString('Không đủ phòng trống', $responseB->json('message'));
    }

    /**
     * Dòng 364: Booking trạng thái Waiting (is_availability = 0) được phép tạo ngay cả khi hết phòng (AV = 0)
     */
    public function test_waiting_booking_can_be_created_when_av_is_zero(): void
    {
        // 1. Tạo booking chiếm hết 2 phòng STD
        $payloadA = [
            'booking_name' => 'Booking Full AV',
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'num_of_days' => 1,
            'registration_status_id' => $this->regStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
            'room_allocations' => [
                [
                    'roomClassId' => $this->roomClass->id,
                    'quantity' => 2,
                    'price' => 1000000,
                    'rooms' => [
                        ['roomNumber' => '101', 'guestName' => 'Guest 1'],
                        ['roomNumber' => '102', 'guestName' => 'Guest 2']
                    ]
                ]
            ]
        ];
        $this->postJson('/api/bookings', $payloadA)->assertSuccessful();

        // 2. Tạo registration status Waiting (is_availability = 0)
        $waitingStatus = RegistrationStatus::create([
            'name' => 'Waiting / Chờ chốt',
            'booking_status_id' => 99,
            'is_availability' => false,
        ]);

        // 3. Tạo booking trạng thái Waiting -> Cho phép thành công ngay cả khi AV = 0 và AllowOverRoomTypeRoomKind = 0
        $payloadWaiting = [
            'booking_name' => 'Booking Waiting Sales',
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'num_of_days' => 1,
            'registration_status_id' => $waitingStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
            'room_allocations' => [
                [
                    'roomClassId' => $this->roomClass->id,
                    'quantity' => 1,
                    'price' => 1000000,
                    'rooms' => [
                        ['roomNumber' => null, 'guestName' => 'Guest Waiting']
                    ]
                ]
            ]
        ];

        $responseWaiting = $this->postJson('/api/bookings', $payloadWaiting);
        $responseWaiting->assertSuccessful();
        $this->assertDatabaseHas('bookings', [
            'booking_name' => 'Booking Waiting Sales',
            'registration_status_id' => $waitingStatus->booking_status_id,
        ]);
    }

    /**
     * TC-02: Dayuse overlap check loophole.
     * Overlapping dayuse bookings (on the same day) can double-book the same room.
     */
    public function test_dayuse_overlap_check_loophole(): void
    {
        // 1. Create a Dayuse Booking A for room 101 on 2026-08-07.
        // Booking header departure_date must be after arrival_date for validation,
        // but child room stays on the same date (2026-08-07 to 2026-08-07).
        $bookingA = $this->createBooking([
            'booking_name' => 'Dayuse A',
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
        ]);
        $bRoomA = BookingRoom::create([
            'id' => 'G0000001',
            'booking_id' => $bookingA->id,
            'room_number' => '101',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-07',
            'arrival_time' => '08:00:00',
            'departure_time' => '12:00:00',
            'is_day_use' => true,
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        // 2. Create Dayuse Booking B for room 101 on the same day 2026-08-07, with overlapping times (09:00:00 to 13:00:00).
        // Since isRoomNumberOccupied check is based on date arrival_date < departure_date, it evaluates 2026-08-07 < 2026-08-07 which is FALSE.
        // Thus, Booking B will successfully book room 101, showing the loophole.
        $payloadB = [
            'booking_name' => 'Dayuse B',
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'num_of_days' => 1,
            'registration_status_id' => $this->regStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
            'room_allocations' => [
                [
                    'roomClassId' => $this->roomClass->id,
                    'quantity' => 1,
                    'price' => 500000,
                    'rooms' => [
                        [
                            'roomNumber' => '101',
                            'guestName' => 'Dayuse Guest B',
                            'arrivalDate' => '2026-08-07',
                            'departureDate' => '2026-08-07',
                            'arrivalTime' => '09:00:00',
                            'hoursOut' => '13:00:00',
                        ]
                    ]
                ]
            ]
        ];

        // This call will be BLOCKED because we fixed the loophole.
        $response = $this->postJson('/api/bookings', $payloadB);
        $response->assertStatus(422);

        $this->assertEquals(1, BookingRoom::where('room_number', '101')->count());
    }

    /**
     * TC-03: Double Booking / Race Condition khi Auto Assign.
     * Demonstrates that without pessimistic locking, concurrent auto-assign requests can assign
     * the same physical room to two different bookings on overlapping dates.
     */
    public function test_auto_assign_race_condition_allows_double_booking(): void
    {
        // 1. Create two bookings Room A and Room B
        $bookingA = $this->createBooking(['booking_name' => 'Auto A']);
        $bRoomA = BookingRoom::create([
            'id' => 'G0000001',
            'booking_id' => $bookingA->id,
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        $bookingB = $this->createBooking(['booking_name' => 'Auto B']);
        $bRoomB = BookingRoom::create([
            'id' => 'G0000002',
            'booking_id' => $bookingB->id,
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        // Remove room 102 so that ONLY room 101 is available in the class
        $this->room102->delete();

        // 2. We simulate the concurrent execution of autoAssign logic:
        // Thread 1 and Thread 2 both query candidate rooms.
        // Since there is no database locking, both threads find room 101 is unoccupied.
        DB::transaction(function () use ($bRoomA, $bRoomB) {
            // Thread A checks candidates and picks 101
            $assignedRoomA = Room::where('room_class_id', $bRoomA->room_class_id)
                ->where('is_internal', false)
                ->first();

            // Thread B checks candidates simultaneously and picks 101 as well (since A hasn't committed)
            $assignedRoomB = Room::where('room_class_id', $bRoomB->room_class_id)
                ->where('is_internal', false)
                ->first();

            // Both update their booking rooms to room 101
            $bRoomA->update(['room_number' => $assignedRoomA->room_number]);
            $bRoomB->update(['room_number' => $assignedRoomB->room_number]);
        });

        // Verify that the race condition successfully caused a double booking on room 101.
        $this->assertEquals('101', $bRoomA->fresh()->room_number);
        $this->assertEquals('101', $bRoomB->fresh()->room_number);
    }

    /**
     * TC-04: Undo Checkout sai ngày hệ thống.
     * Restoring a room checkout is blocked if the checkout date does not equal the system date.
     */
    public function test_undo_checkout_fails_when_date_mismatch_with_system_date(): void
    {
        // 1. Create a Checked Out booking room whose checkout date is 2026-08-05.
        // Active system date is 2026-08-07.
        $booking = $this->createBooking([
            'booking_name' => 'Old Checkout',
            'arrival_date' => '2026-08-01',
            'departure_date' => '2026-08-05',
            'status' => Booking::STATUS_CHECKOUT,
        ]);
        $bRoom = BookingRoom::create([
            'id' => 'G0000001',
            'booking_id' => $booking->id,
            'room_number' => '101',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-01',
            'departure_date' => '2026-08-05',
            'status' => BookingRoom::STATUS_CHECKED_OUT,
            'CheckoutDate' => '2026-08-05', // Mismatch with system date 2026-08-07
            'CheckoutTime' => '12:00:00',
        ]);
        $guest = Guest::create(['full_name' => 'Guest Old']);
        BookingRoomGuest::create([
            'booking_room_id' => $bRoom->id,
            'guest_id' => $guest->id,
            'status' => BookingRoomGuest::STATUS_CHECKED_OUT,
        ]);

        // 2. Request undo checkout. It should be blocked due to date mismatch.
        $response = $this->postJson("/api/booking-rooms/{$bRoom->id}/restore-checkout");
        
        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $this->assertStringContainsString('trong ngày hệ thống', $response->json('message'));
    }

    /**
     * TC-05: Undo checkout is blocked when room is occupied by another booking.
     */
    public function test_undo_checkout_is_blocked_when_room_occupied(): void
    {
        // 1. Create booking A checked out room 101 on the system date (2026-08-07)
        $bookingA = $this->createBooking([
            'booking_name' => 'Booking A Checkout',
            'arrival_date' => '2026-08-01',
            'departure_date' => '2026-08-07',
            'status' => Booking::STATUS_CHECKOUT,
        ]);
        $bRoomA = BookingRoom::create([
            'id' => 'G0000001',
            'booking_id' => $bookingA->id,
            'room_number' => '101',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-01',
            'departure_date' => '2026-08-07',
            'status' => BookingRoom::STATUS_CHECKED_OUT,
            'CheckoutDate' => '2026-08-07',
            'CheckoutTime' => '12:00:00',
        ]);
        $guestA = Guest::create(['full_name' => 'Guest A']);
        BookingRoomGuest::create([
            'booking_room_id' => $bRoomA->id,
            'guest_id' => $guestA->id,
            'status' => BookingRoomGuest::STATUS_CHECKED_OUT,
        ]);

        // 2. Room 101 is now occupied by Booking B (Checked In).
        $bookingB = $this->createBooking([
            'booking_name' => 'Booking B Inhouse',
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-09',
            'status' => Booking::STATUS_CHECKIN,
        ]);
        $bRoomB = BookingRoom::create([
            'id' => 'G0000002',
            'booking_id' => $bookingB->id,
            'room_number' => '101',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-09',
            'status' => BookingRoom::STATUS_CHECKED_IN,
        ]);

        // 3. Trying to restore checkout of Booking Room A should be BLOCKED because room 101 is currently occupied.
        $response = $this->postJson("/api/booking-rooms/{$bRoomA->id}/restore-checkout");
        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $this->assertStringContainsString('không thể khôi phục checkout', $response->json('message'));
    }

    /**
     * TC-06: Cancel Booking cascade dữ liệu con.
     * Cancelling a room booking cascade-updates `booking_room_guests` and `booking_children` to Cancelled (3),
     * but leaves `booking_room_services` unmodified/orphaned.
     */
    public function test_cancel_booking_cascade_updates_guests_and_children_but_orphans_services(): void
    {
        $this->grantBookingCancelPermission();

        // 1. Create booking and room in BOOKED state (allow cancel)
        $booking = $this->createBooking([
            'booking_name' => 'Booking to Cancel',
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'status' => Booking::STATUS_RESERVATION,
        ]);
        $bRoom = BookingRoom::create([
            'id' => 'G0000001',
            'booking_id' => $booking->id,
            'room_number' => '101',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        // Create secondary guest
        $guest = Guest::create(['full_name' => 'Secondary Guest']);
        $guestPivot = BookingRoomGuest::create([
            'booking_room_id' => $bRoom->id,
            'guest_id' => $guest->id,
            'status' => BookingRoomGuest::STATUS_ACTIVE,
        ]);

        // Create child
        $child = BookingChild::create([
            'id' => 'BC0001',
            'booking_id' => $booking->id,
            'booking_room_id' => $bRoom->id,
            'full_name' => 'Child A',
            'age_group' => 'child',
            'child_status' => 0,
        ]);

        // Create room services (extra bed / daily automated posts)
        $service = BookingRoomService::create([
            'booking_room_id' => $bRoom->id,
            'service_code' => 'RM',
            'service_name' => 'Room Charge',
            'service_date' => '2026-08-07',
            'rate' => 1000000,
            'quantity' => 1,
        ]);

        // 2. Perform cancellation
        $response = $this->deleteJson("/api/bookings/{$booking->id}/rooms/{$bRoom->id}/cancel", [
            'reason' => 'Khách hủy phòng'
        ]);
        $response->assertSuccessful();

        $cancelLog = BookingCancelLog::where('booking_room_id', $bRoom->id)->sole();
        $this->assertSame('2026-08-07', $cancelLog->cancelled_at->toDateString());

        // 3. Assert room status and cascade updates
        $bRoom->refresh();
        $this->assertEquals(BookingRoom::STATUS_CANCELLED, $bRoom->status);
        $booking->refresh();
        $this->assertNotEquals(Booking::STATUS_DELETED, $booking->status);

        // Guests cascaded to status 3
        $guestPivot->refresh();
        $this->assertEquals(3, $guestPivot->status);

        // Children cascaded to child_status 3
        $child->refresh();
        $this->assertEquals(3, $child->child_status);

        // Loophole: Booking Room Services remain in the database unmodified and orphaned
        $this->assertDatabaseHas('booking_room_services', [
            'id' => $service->id,
            'booking_room_id' => $bRoom->id,
        ]);
    }

    public function test_cancel_whole_booking_logs_use_pms_system_date(): void
    {
        $this->grantBookingCancelPermission();

        $booking = $this->createBooking([
            'booking_name' => 'Whole Booking to Cancel',
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'status' => Booking::STATUS_RESERVATION,
        ]);
        $bRoom = BookingRoom::create([
            'id' => 'G0000099',
            'booking_id' => $booking->id,
            'room_number' => '102',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        $response = $this->deleteJson("/api/bookings/{$booking->id}", [
            'note' => 'Khách hủy đăng ký',
        ]);

        $response->assertSuccessful();
        $logs = BookingCancelLog::where('booking_id', $booking->id)->get();
        $this->assertCount(2, $logs);
        $this->assertSame(['booking', 'room'], $logs->pluck('cancel_type')->sort()->values()->all());
        $this->assertTrue($logs->every(
            fn (BookingCancelLog $log) => $log->cancelled_at->toDateString() === '2026-08-07'
        ));
    }

    /**
     * TC-07: Room Move đồng bộ dữ liệu (booking_child_breakfast_details).
     * Past breakfast details are left under the old child/room ID, while current/future ones are synchronized.
     */
    public function test_room_move_preserves_child_identity_and_breakfast_history(): void
    {
        // 1. Create a Checked-In booking room with a child and breakfast details on 2026-08-06 and 2026-08-07
        $booking = $this->createBooking([
            'booking_name' => 'Room Move Child Test',
            'arrival_date' => '2026-08-06',
            'departure_date' => '2026-08-09',
            'status' => Booking::STATUS_CHECKIN,
        ]);
        $bRoom = BookingRoom::create([
            'id' => 'G0000001',
            'booking_id' => $booking->id,
            'room_number' => '101',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-06',
            'departure_date' => '2026-08-09',
            'status' => BookingRoom::STATUS_CHECKED_IN,
            'actual_arrival_date' => '2026-08-06',
            'adults' => 1,
            'children_qty' => 1,
        ]);

        $guest = Guest::create(['full_name' => 'Adult Guest']);
        BookingRoomGuest::create([
            'booking_room_id' => $bRoom->id,
            'guest_id' => $guest->id,
            'status' => BookingRoomGuest::STATUS_CHECKED_IN,
            'is_primary' => true,
        ]);

        $child = BookingChild::create([
            'id' => 'BC0001',
            'booking_id' => $booking->id,
            'booking_room_id' => $bRoom->id,
            'full_name' => 'Child 1',
            'age_group' => 'child',
            'child_status' => 0,
        ]);

        // Daily breakfast details
        $bf1 = BookingChildBreakfastDetail::create([
            'booking_child_id' => $child->id,
            'service_date' => '2026-08-06',
            'breakfast' => true,
            'is_free' => false,
            'is_extra_charge' => true,
            'amount' => 90000,
        ]);
        $bf2 = BookingChildBreakfastDetail::create([
            'booking_child_id' => $child->id,
            'service_date' => '2026-08-07',
            'breakfast' => true,
            'is_free' => false,
            'is_extra_charge' => true,
            'amount' => 90000,
        ]);
        $bf3 = BookingChildBreakfastDetail::create([
            'booking_child_id' => $child->id,
            'service_date' => '2026-08-08',
            'breakfast' => true,
            'is_free' => false,
            'is_extra_charge' => true,
            'amount' => 90000,
        ]);

        // Reject leaving children behind without leaking the transaction opened by moveRoom.
        $transactionLevel = DB::transactionLevel();
        $this->postJson("/api/bookings/{$booking->id}/rooms/{$bRoom->id}/move", [
            'move_type' => 'available', 'target_room_number' => '102', 'reason' => 'Missing child selection',
        ])->assertStatus(422);
        $this->assertSame($transactionLevel, DB::transactionLevel());

        // 2. Perform a Room Move from room 101 to room 102 on system_date = 2026-08-07
        $response = $this->postJson("/api/bookings/{$booking->id}/rooms/{$bRoom->id}/move", [
            'move_type' => 'available',
            'target_room_number' => '102',
            'reason' => 'Room move child sync test',
            'selected_child_ids' => [$child->id],
        ]);
        $response->assertSuccessful();

        // Child identity is retained; the room assignment pivot holds transfer history.
        $child->refresh();
        $this->assertEquals(1, $child->child_status);
        $this->assertNotEquals($bRoom->id, $child->booking_room_id);
        $this->assertDatabaseHas('booking_room_children', ['booking_child_id' => $child->id, 'booking_room_id' => $bRoom->id, 'status' => 100]);
        $this->assertDatabaseHas('booking_room_children', ['booking_child_id' => $child->id, 'booking_room_id' => $child->booking_room_id, 'status' => 1]);

        // Breakfast dates remain attached to that same child across the room move.
        $bf2->refresh();
        $bf3->refresh();
        $this->assertEquals($child->id, $bf2->booking_child_id);
        $this->assertEquals($child->id, $bf3->booking_child_id);

        // Breakfast details < 2026-08-07 (bf1) remain under the old child ($child->id)
        $bf1->refresh();
        $this->assertEquals($child->id, $bf1->booking_child_id);
        // This is normal for past stay, but verify it exists.
        $this->assertDatabaseHas('booking_child_breakfast_details', [
            'id' => $bf1->id,
            'booking_child_id' => $child->id,
        ]);
    }

    /**
     * TC-08: Restore master checkout only reopens the booking header, so an occupied child room does not block it
     * and the child rooms stay checked out.
     */
    public function test_restore_master_checkout_only_reopens_booking_when_child_room_is_occupied(): void
    {
        // 1. Create a Master Booking with 2 rooms (101, 102) that is Checked Out.
        $booking = $this->createBooking([
            'booking_name' => 'Master Checkout',
            'arrival_date' => '2026-08-01',
            'departure_date' => '2026-08-05',
            'status' => Booking::STATUS_CHECKOUT,
        ]);
        $bRoom1 = BookingRoom::create([
            'id' => 'G0000001',
            'booking_id' => $booking->id,
            'room_number' => '101',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-01',
            'departure_date' => '2026-08-05',
            'status' => BookingRoom::STATUS_CHECKED_OUT,
        ]);
        $bRoom2 = BookingRoom::create([
            'id' => 'G0000002',
            'booking_id' => $booking->id,
            'room_number' => '102',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-01',
            'departure_date' => '2026-08-05',
            'status' => BookingRoom::STATUS_CHECKED_OUT,
        ]);

        // 2. Room 102 is now occupied by another booking (Checked In).
        $otherBooking = $this->createBooking([
            'booking_name' => 'Other Inhouse',
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-09',
            'status' => Booking::STATUS_CHECKIN,
        ]);
        $otherRoom = BookingRoom::create([
            'id' => 'G0000003',
            'booking_id' => $otherBooking->id,
            'room_number' => '102',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-09',
            'status' => BookingRoom::STATUS_CHECKED_IN,
        ]);

        // 3. Restoring the master only reopens the booking; child rooms are not re-checked-in.
        $response = $this->postJson("/api/bookings/{$booking->id}/restore-checkout");
        $response->assertSuccessful();
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => Booking::STATUS_CHECKIN]);
        $this->assertDatabaseHas('booking_rooms', ['id' => $bRoom1->id, 'status' => BookingRoom::STATUS_CHECKED_OUT]);
        $this->assertDatabaseHas('booking_rooms', ['id' => $bRoom2->id, 'status' => BookingRoom::STATUS_CHECKED_OUT]);
    }

    /**
     * TC-09: Booking departure date can be equal to arrival date (for Dayuse).
     */
    public function test_booking_departure_date_can_be_equal_to_arrival_date(): void
    {
        $payload = [
            'booking_name' => 'Dayuse Same Date',
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-07', // Same date
            'num_of_days' => 1,
            'registration_status_id' => $this->regStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
            'room_allocations' => [
                [
                    'roomClassId' => $this->roomClass->id,
                    'quantity' => 1,
                    'price' => 500000,
                    'rooms' => [
                        [
                            'roomNumber' => '101',
                            'guestName' => 'Dayuse Guest',
                            'arrivalDate' => '2026-08-07',
                            'departureDate' => '2026-08-07',
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->postJson('/api/bookings', $payload);
        $response->assertSuccessful();
        
        $this->assertDatabaseHas('bookings', [
            'arrival_date' => '2026-08-07 00:00:00',
            'departure_date' => '2026-08-07 00:00:00',
        ]);
    }

    /**
     * TC-10: Room stay date must be within booking stay dates.
     */
    public function test_room_stay_date_must_be_within_booking_stay_dates(): void
    {
        // 1. Booking has dates 2026-08-07 to 2026-08-09
        $payload = [
            'booking_name' => 'Out of bounds test',
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-09',
            'num_of_days' => 2,
            'registration_status_id' => $this->regStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
            'room_allocations' => [
                [
                    'roomClassId' => $this->roomClass->id,
                    'quantity' => 1,
                    'price' => 500000,
                    'rooms' => [
                        [
                            'roomNumber' => '101',
                            'guestName' => 'Out of bounds Guest',
                            'arrivalDate' => '2026-08-07', // Valid PMS date; isolate containment validation
                            'departureDate' => '2026-08-10', // Outside booking departure
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->postJson('/api/bookings', $payload);
        $response->assertStatus(422);
        $this->assertStringContainsString('Thời gian ở của phòng phải nằm trong khoảng thời gian của booking', $response->json('message'));
    }

    /**
     * TC-11: SyncRoomDateByBookingDate = 1 updates reservation rooms but does not touch Checked-In rooms.
     */
    public function test_sync_room_date_by_booking_date_logic(): void
    {
        // Set SyncRoomDateByBookingDate = 1
        HotelConfig::updateOrCreate(['name' => 'SyncRoomDateByBookingDate'], ['value' => '1']);

        // 1. Create a Booking with 2 rooms: Room 1 (STATUS_BOOKED), Room 2 (STATUS_CHECKED_IN)
        // Booking date: 2026-08-21 to 2026-08-25
        $booking = $this->createBooking([
            'arrival_date' => '2026-08-21',
            'departure_date' => '2026-08-25',
        ]);

        $bRoom1 = BookingRoom::create([
            'id' => 'G0000001',
            'booking_id' => $booking->id,
            'room_number' => '101',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-21',
            'departure_date' => '2026-08-25',
            'status' => BookingRoom::STATUS_BOOKED, // Reservation
        ]);

        $bRoom2 = BookingRoom::create([
            'id' => 'G0000002',
            'booking_id' => $booking->id,
            'room_number' => '102',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-21',
            'departure_date' => '2026-08-25',
            'status' => BookingRoom::STATUS_CHECKED_IN, // Checked In
        ]);

        // 2. Widen booking departure to 2026-08-26. Row 394: the in-house room
        // (21-25) must stay inside the booking period, so only widen here.
        $response = $this->putJson("/api/bookings/{$booking->id}", [
            'booking_name' => 'Updated Booking Name',
            'arrival_date' => '2026-08-21',
            'departure_date' => '2026-08-26',
            'registration_status_id' => $this->regStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
        ]);
        $response->assertSuccessful();

        // 3. Assertions
        $booking->refresh();
        $bRoom1->refresh();
        $bRoom2->refresh();

        // Booking departure date updated to 26
        $this->assertEquals('2026-08-26', $booking->departure_date->toDateString());

        // Room 1 (Reservation) departure date synchronized to 26
        $this->assertEquals('2026-08-26', $bRoom1->departure_date->toDateString());

        // Room 2 (Checked In) dates MUST remain 21-25
        $this->assertEquals('2026-08-21', $bRoom2->arrival_date->toDateString());
        $this->assertEquals('2026-08-25', $bRoom2->departure_date->toDateString());
    }

    /**
     * TC-12: Updating booking dates is blocked when SyncRoomDateByBookingDate = 0 and room stay falls outside.
     */
    public function test_booking_update_blocked_when_sync_disabled_and_room_outside_boundaries(): void
    {
        // Set SyncRoomDateByBookingDate = 0
        HotelConfig::updateOrCreate(['name' => 'SyncRoomDateByBookingDate'], ['value' => '0']);

        $booking = $this->createBooking([
            'arrival_date' => '2026-08-21',
            'departure_date' => '2026-08-25',
        ]);

        $bRoom = BookingRoom::create([
            'id' => 'G0000001',
            'booking_id' => $booking->id,
            'room_number' => '101',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-21',
            'departure_date' => '2026-08-25',
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        // Attempt updating booking arrival_date to 22.
        // Since sync is disabled, the room's arrival_date remains 21, which falls outside the new booking dates (22 to 25).
        // This should be blocked.
        $response = $this->putJson("/api/bookings/{$booking->id}", [
            'booking_name' => 'Blocked Update',
            'arrival_date' => '2026-08-22',
            'departure_date' => '2026-08-25',
            'registration_status_id' => $this->regStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Ngày đến hoặc ngày đi của đăng ký không khớp với giai đoạn chi tiết của phòng', $response->json('message'));
    }

    /**
     * TC-13 (row 394): a Checked-In room is never synced, so moving the booking
     * arrival past it leaves the room outside the period and is rejected.
     */
    public function test_booking_update_blocked_when_checked_in_room_falls_outside(): void
    {
        // Set SyncRoomDateByBookingDate = 1
        HotelConfig::updateOrCreate(['name' => 'SyncRoomDateByBookingDate'], ['value' => '1']);

        $booking = $this->createBooking([
            'arrival_date' => '2026-08-21',
            'departure_date' => '2026-08-25',
        ]);

        $bRoom = BookingRoom::create([
            'id' => 'G0000001',
            'booking_id' => $booking->id,
            'room_number' => '101',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-21',
            'departure_date' => '2026-08-25',
            'status' => BookingRoom::STATUS_CHECKED_IN, // Checked In
        ]);

        // Attempt updating booking arrival_date to 22.
        // The room is Checked-In so it is not synced and would stay on 21,
        // outside the new period. Row 394: the update is rejected.
        $response = $this->putJson("/api/bookings/{$booking->id}", [
            'booking_name' => 'Blocked Update Checked In',
            'arrival_date' => '2026-08-22',
            'departure_date' => '2026-08-25',
            'registration_status_id' => $this->regStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
        ]);

        $response->assertStatus(422);
        $bRoom->refresh();
        // Room (Checked In) arrival date MUST remain 21, booking unchanged
        $this->assertEquals('2026-08-21', $bRoom->arrival_date->toDateString());
        $this->assertEquals('2026-08-21', $booking->fresh()->arrival_date->toDateString());
    }

    public function test_booking_update_can_clear_one_room_rate_code_without_affecting_sibling_room(): void
    {
        HotelConfig::updateOrCreate(['name' => 'SyncRoomDateByBookingDate'], ['value' => '0']);

        $booking = $this->createBooking([
            'arrival_date' => '2026-08-21',
            'departure_date' => '2026-08-25',
        ]);
        $room1 = BookingRoom::create([
            'id' => 'G-RATE-01',
            'booking_id' => $booking->id,
            'room_number' => '101',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-21',
            'departure_date' => '2026-08-25',
            'rate' => 900000,
            'base_price' => 900000,
            'rate_code' => 'BAR',
            'status' => BookingRoom::STATUS_BOOKED,
        ]);
        $room2 = BookingRoom::create([
            'id' => 'G-RATE-02',
            'booking_id' => $booking->id,
            'room_number' => '102',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-21',
            'departure_date' => '2026-08-25',
            'rate' => 700000,
            'base_price' => 700000,
            'rate_code' => 'CORP',
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        $response = $this->putJson("/api/bookings/{$booking->id}", [
            'booking_name' => 'Clear one room rate code',
            'arrival_date' => '2026-08-21',
            'departure_date' => '2026-08-25',
            'registration_status_id' => $this->regStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
            'room_allocations' => [[
                'roomClassId' => $this->roomClass->id,
                'quantity' => 2,
                'price' => 900000,
                'basePrice' => 900000,
                'rateCode' => 'BAR',
                'rooms' => [
                    [
                        'bookingRoomId' => $room1->id,
                        'roomNumber' => '101',
                        'arrivalDate' => '2026-08-21',
                        'departureDate' => '2026-08-25',
                        'price' => 500000,
                        'basePrice' => 500000,
                        'rateCode' => null,
                    ],
                    [
                        'bookingRoomId' => $room2->id,
                        'roomNumber' => '102',
                        'arrivalDate' => '2026-08-21',
                        'departureDate' => '2026-08-25',
                        'price' => 700000,
                        'basePrice' => 700000,
                        'rateCode' => 'CORP',
                    ],
                ],
            ]],
        ]);

        $response->assertSuccessful();
        $room1->refresh();
        $room2->refresh();

        $this->assertNull($room1->rate_code);
        $this->assertEquals(500000, $room1->rate);
        $this->assertEquals(500000, $room1->base_price);
        $this->assertEquals('CORP', $room2->rate_code);
        $this->assertEquals(700000, $room2->rate);
    }

    public function test_booking_create_persists_rate_code_prices_for_each_stay_night(): void
    {
        StandardRate::create([
            'room_class_id' => $this->roomClass->id,
            'room_form_id' => $this->roomForm->id,
            'room_price' => 900000,
        ]);
        RoomRateCode::create(['Ma' => 'TEST1', 'IsDaily' => true]);
        RoomRatePlan::create([
            'RateCode' => 'TEST1',
            'Code' => 'TEST1',
            'Period' => ['TEST1_STD_Standard Form' => 1],
        ]);
        RoomRatePlan::create([
            'RateCode' => 'TEST1',
            'Code' => 'TEST2',
            'Period' => ['TEST2_STD_Standard Form' => 11],
        ]);
        foreach ([
            '2026-08-09' => 'TEST1',
            '2026-08-10' => 'TEST2',
            '2026-08-11' => 'TEST2',
            '2026-08-12' => 'TEST1',
            '2026-08-13' => 'TEST1',
        ] as $date => $code) {
            RoomRateDailyMapping::create(['RateCode' => 'TEST1', 'Date' => $date, 'Code' => $code]);
        }

        $response = $this->postJson('/api/bookings', [
            'booking_name' => 'Daily rate code prices',
            'arrival_date' => '2026-08-09',
            'departure_date' => '2026-08-14',
            'num_of_days' => 5,
            'registration_status_id' => $this->regStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
            'room_allocations' => [[
                'roomClassId' => $this->roomClass->id,
                'quantity' => 1,
                'price' => 999,
                'basePrice' => 999,
                'rateCode' => 'TEST1',
                'rooms' => [[
                    'roomNumber' => null,
                    'guestName' => 'Daily Rate Guest',
                    'arrivalDate' => '2026-08-09',
                    'departureDate' => '2026-08-14',
                    'price' => 999,
                    'basePrice' => 999,
                    'rateCode' => 'TEST1',
                    'dailyRoomPrices' => [
                        '2026-08-09' => 999,
                        '2026-08-10' => 999,
                        '2026-08-11' => 999,
                        '2026-08-12' => 999,
                        '2026-08-13' => 999,
                    ],
                ]],
            ]],
        ]);

        $response->assertSuccessful();
        $room = BookingRoom::latest('created_at')->firstOrFail();

        $this->assertEquals('TEST1', $room->rate_code);
        $this->assertEquals(1, $room->rate);
        $this->assertDatabaseCount('booking_room_services', 5);

        foreach ([
            '2026-08-09' => 1,
            '2026-08-10' => 11,
            '2026-08-11' => 11,
            '2026-08-12' => 1,
            '2026-08-13' => 1,
        ] as $date => $rate) {
            $this->assertDatabaseHas('booking_room_services', [
                'booking_room_id' => $room->id,
                'service_code' => BookingRoomService::CODE_ROOM,
                'service_date' => $date . ' 00:00:00',
                'rate' => $rate,
                'is_posted' => 0,
            ]);
        }

        $this->assertDatabaseMissing('booking_room_services', [
            'booking_room_id' => $room->id,
            'service_code' => BookingRoomService::CODE_ROOM,
            'service_date' => '2026-08-14 00:00:00',
        ]);

        $updateResponse = $this->putJson("/api/bookings/{$room->booking_id}", [
            'booking_name' => 'Daily rate code prices updated',
            'arrival_date' => '2026-08-09',
            'departure_date' => '2026-08-14',
            'registration_status_id' => $this->regStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
            'room_allocations' => [[
                'roomClassId' => $this->roomClass->id,
                'quantity' => 1,
                'rateCode' => 'TEST1',
                'rooms' => [[
                    'bookingRoomId' => $room->id,
                    'arrivalDate' => '2026-08-09',
                    'departureDate' => '2026-08-14',
                    'price' => 777,
                    'basePrice' => 777,
                    'rateCode' => 'TEST1',
                    'dailyRoomPrices' => array_fill_keys([
                        '2026-08-09',
                        '2026-08-10',
                        '2026-08-11',
                        '2026-08-12',
                        '2026-08-13',
                    ], 777),
                ]],
            ]],
        ]);

        $updateResponse->assertSuccessful();
        $room->refresh();
        $this->assertEquals(1, $room->rate);
        $this->assertEquals(1, $room->base_price);
        $this->assertDatabaseHas('booking_room_services', [
            'booking_room_id' => $room->id,
            'service_code' => BookingRoomService::CODE_ROOM,
            'service_date' => '2026-08-10 00:00:00',
            'rate' => 11,
        ]);
    }

    public function test_booking_create_uses_default_plan_for_non_daily_rate_code(): void
    {
        StandardRate::create([
            'room_class_id' => $this->roomClass->id,
            'room_form_id' => $this->roomForm->id,
            'room_price' => 900000,
        ]);
        RoomRateCode::create(['Ma' => 'BAR', 'IsDaily' => false]);
        RoomRatePlan::create([
            'RateCode' => 'BAR',
            'Code' => 'DEFAULT',
            'Period' => ['DEFAULT_STD_Standard Form' => 321000],
        ]);

        $response = $this->postJson('/api/bookings', [
            'booking_name' => 'Default rate code price',
            'arrival_date' => '2026-08-09',
            'departure_date' => '2026-08-11',
            'num_of_days' => 2,
            'registration_status_id' => $this->regStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
            'room_allocations' => [[
                'roomClassId' => $this->roomClass->id,
                'quantity' => 1,
                'price' => 999,
                'basePrice' => 999,
                'rateCode' => 'BAR',
                'rooms' => [[
                    'arrivalDate' => '2026-08-09',
                    'departureDate' => '2026-08-11',
                    'price' => 999,
                    'basePrice' => 999,
                    'rateCode' => 'BAR',
                    'dailyRoomPrices' => [
                        '2026-08-09' => 999,
                        '2026-08-10' => 999,
                    ],
                ]],
            ]],
        ]);

        $response->assertSuccessful();
        $room = BookingRoom::latest('created_at')->firstOrFail();
        $this->assertEquals('BAR', $room->rate_code);
        $this->assertEquals(321000, $room->rate);
        $this->assertEquals(321000, $room->base_price);
        $this->assertDatabaseCount('booking_room_services', 2);
        foreach (['2026-08-09', '2026-08-10'] as $date) {
            $this->assertDatabaseHas('booking_room_services', [
                'booking_room_id' => $room->id,
                'service_code' => BookingRoomService::CODE_ROOM,
                'service_date' => $date . ' 00:00:00',
                'rate' => 321000,
            ]);
        }
    }

    public function test_master_room_rate_flag_does_not_move_existing_room_charge_bills(): void
    {
        $booking = $this->createBooking([
            'arrival_date' => '2026-08-21',
            'departure_date' => '2026-08-25',
            'is_master_room_rate' => false,
        ]);
        $room = BookingRoom::create([
            'id' => 'G0000099',
            'booking_id' => $booking->id,
            'room_number' => '101',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-21',
            'departure_date' => '2026-08-25',
            'status' => BookingRoom::STATUS_BOOKED,
        ]);
        $roomBill = ServiceBill::create([
            'Date' => '2026-08-21', 'OpenTime' => '12:00', 'Guest' => 'Room guest',
            'DepartmentId' => 'FO', 'ServiceId' => 'RM', 'Amount' => 500000,
            'RegisterId1' => $booking->id, 'RentalRoomId1' => $room->id,
            'RegisterID2' => $booking->id, 'RentalRoomId2' => $room->id,
            'Edit' => 0, 'Status' => 1, 'Username' => $this->user->username,
        ]);
        $masterBill = ServiceBill::create([
            'Date' => '2026-08-22', 'OpenTime' => '12:00', 'Guest' => $booking->booking_name,
            'DepartmentId' => 'FO', 'ServiceId' => 'RM', 'Amount' => 500000,
            'RegisterId1' => $booking->id, 'RentalRoomId1' => $room->id,
            'RegisterID2' => $booking->id, 'RentalRoomId2' => null,
            'Edit' => 0, 'Status' => 1, 'Username' => $this->user->username,
        ]);
        $roomService = BookingRoomService::create([
            'booking_room_id' => $room->id, 'service_bill_id' => $roomBill->Ma,
            'service_code' => 'RM', 'service_date' => '2026-08-21',
            'quantity' => 1, 'rate' => 500000, 'folio' => 1, 'is_room' => 1, 'is_posted' => 1,
        ]);
        $payload = [
            'booking_name' => $booking->booking_name,
            'arrival_date' => '2026-08-21',
            'departure_date' => '2026-08-25',
            'registration_status_id' => $this->regStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
        ];

        $this->putJson("/api/bookings/{$booking->id}", [...$payload, 'is_master_room_rate' => true])
            ->assertSuccessful();
        $this->putJson("/api/bookings/{$booking->id}", [...$payload, 'is_master_room_rate' => false])
            ->assertSuccessful();

        $this->assertDatabaseHas('service_bills', ['Ma' => $roomBill->Ma, 'RentalRoomId2' => $room->id]);
        $this->assertDatabaseHas('service_bills', ['Ma' => $masterBill->Ma, 'RentalRoomId2' => null]);
        $this->assertDatabaseHas('booking_room_services', ['id' => $roomService->id, 'service_bill_id' => $roomBill->Ma]);
    }

    /**
     * TC-14: Dayuse is deducted from Room AV only when is_day_use = 1.
     */
    public function test_dayuse_is_availability_deducted_only_when_dayuse_enabled(): void
    {
        $avService = app(\App\Services\RoomAvailabilityService::class);

        // 1. Create a same-day stay (07 -> 07) with is_day_use = 1
        $bookingA = $this->createBooking([
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-07',
            'registration_status_id' => $this->regStatus->booking_status_id,
        ]);
        $bRoomA = BookingRoom::create([
            'id' => 'G0000001',
            'booking_id' => $bookingA->id,
            'room_number' => '101',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-07',
            'is_day_use' => true,
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        // It should deduct from booked count
        $bookedCount = $avService->getBookedCount($this->roomClass->id, '2026-08-07', '2026-08-07');
        $this->assertEquals(1, $bookedCount);

        // 2. Create another same-day stay (07 -> 07) with is_day_use = 0
        $bookingB = $this->createBooking([
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-07',
            'registration_status_id' => $this->regStatus->booking_status_id,
        ]);
        $bRoomB = BookingRoom::create([
            'id' => 'G0000002',
            'booking_id' => $bookingB->id,
            'room_number' => '102',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-07',
            'is_day_use' => false,
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        // It should NOT deduct from booked count (so count remains 1, from A only)
        $bookedCountTotal = $avService->getBookedCount($this->roomClass->id, '2026-08-07', '2026-08-07');
        $this->assertEquals(1, $bookedCountTotal);
    }

    /**
     * TC-15: Adjacent stays in the same booking (in Day 1 out Day 2, and in Day 2 out Day 3) can be assigned the same physical room.
     */
    public function test_adjacent_non_overlapping_room_stays_in_same_booking_can_be_assigned_same_room(): void
    {
        // Set SyncRoomDateByBookingDate = 0 so child rooms are not forced to parent booking dates
        HotelConfig::updateOrCreate(['name' => 'SyncRoomDateByBookingDate'], ['value' => '0']);

        $booking = $this->createBooking([
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-09',
        ]);

        // Payload with 2 room stays for room 101:
        // Stay 1: 07 to 08, room 101.
        // Stay 2: 08 to 09, room 101.
        // Since dates are formatted as dd/mm/yyyy from UI, we test the normalization here too.
        $payload = [
            'booking_name' => 'Adjacent stays',
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-09',
            'num_of_days' => 2,
            'registration_status_id' => $this->regStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
            'room_allocations' => [
                [
                    'roomClassId' => $this->roomClass->id,
                    'quantity' => 2,
                    'price' => 500000,
                    'rooms' => [
                        [
                            'roomNumber' => '101',
                            'guestName' => 'Stay A',
                            'arrivalDate' => '07/08/2026', // UI format
                            'departureDate' => '08/08/2026',
                        ],
                        [
                            'roomNumber' => '101',
                            'guestName' => 'Stay B',
                            'arrivalDate' => '08/08/2026', // UI format
                            'departureDate' => '09/08/2026',
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->postJson('/api/bookings', $payload);
        $response->assertSuccessful();

        $this->assertDatabaseHas('booking_rooms', [
            'room_number' => '101',
            'arrival_date' => '2026-08-07 00:00:00',
            'departure_date' => '2026-08-08 00:00:00',
        ]);
        $this->assertDatabaseHas('booking_rooms', [
            'room_number' => '101',
            'arrival_date' => '2026-08-08 00:00:00',
            'departure_date' => '2026-08-09 00:00:00',
        ]);
    }

    /**
     * Section 8: child breakfast surcharge is persisted only in the daily
     * detail table; editing it must not create a new booking-room service.
     */
    public function test_child_breakfast_edit_does_not_create_booking_room_service(): void
    {
        HotelConfig::create(['name' => 'Booking_BFChildSetServiceId', 'value' => 'BD']);
        $booking = $this->createBooking();
        $room = BookingRoom::create([
            'id' => 'G-BF-FOLIO',
            'booking_id' => $booking->id,
            'room_number' => '101',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'status' => BookingRoom::STATUS_CHECKED_IN,
        ]);
        $child = BookingChild::create([
            'id' => 'BC-BF-FOLIO',
            'booking_id' => $booking->id,
            'booking_room_id' => $room->id,
            'full_name' => 'Child FIT',
            'age_group' => 'child',
        ]);
        $detail = BookingChildBreakfastDetail::create([
            'booking_child_id' => $child->id,
            'service_date' => '2026-08-07',
            'breakfast' => true,
            'is_free' => false,
            'is_extra_charge' => true,
            'is_room' => true,
            'amount' => 90000,
        ]);

        $this->patchJson("/api/booking-children/{$child->id}/breakfast-details/{$detail->id}", [
            'is_room' => true,
            'amount' => 90000,
        ])->assertSuccessful();

        $this->assertDatabaseMissing('booking_room_services', [
            'booking_room_id' => $room->id,
            'service_code' => 'BD',
            'service_date' => '2026-08-07 00:00:00',
        ]);

        $this->patchJson("/api/booking-children/{$child->id}/breakfast-details/{$detail->id}", [
            'is_room' => false,
            'amount' => 90000,
        ])->assertSuccessful()->assertJsonPath('data.is_room', false);

        $this->assertDatabaseCount('booking_room_services', 0);
        $this->assertDatabaseHas('booking_child_breakfast_details', [
            'id' => $detail->id,
            'is_room' => 0,
            'amount' => 90000,
        ]);
    }

    public function test_room_date_update_moves_guest_actual_arrival_and_child_breakfast_period(): void
    {
        HotelConfig::updateOrCreate(['name' => 'Booking_AutoExtraChargeBFChild'], ['value' => '1']);
        $booking = $this->createBooking([
            'arrival_date' => '2026-08-17',
            'departure_date' => '2026-08-28',
        ]);
        $room = BookingRoom::create([
            'id' => 'G-BF-DATE',
            'booking_id' => $booking->id,
            'room_number' => '101',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-17',
            'departure_date' => '2026-08-20',
            'status' => BookingRoom::STATUS_BOOKED,
        ]);
        $guest = Guest::create(['full_name' => 'Date Guest', 'guest_status' => Guest::STATUS_ACTIVE]);
        $pivot = BookingRoomGuest::create([
            'booking_room_id' => $room->id,
            'guest_id' => $guest->id,
            'is_primary' => true,
            'status' => BookingRoomGuest::STATUS_ACTIVE,
            'actual_arrival_date' => '2026-08-17',
        ]);
        $child = BookingChild::create([
            'id' => 'BC-BF-DATE',
            'booking_id' => $booking->id,
            'booking_room_id' => $room->id,
            'full_name' => 'Date Child',
            'age_group' => 'child',
        ]);
        foreach (['2026-08-17', '2026-08-18', '2026-08-19'] as $date) {
            BookingChildBreakfastDetail::create([
                'booking_child_id' => $child->id,
                'service_date' => $date,
                'breakfast' => true,
                'is_free' => false,
                'is_extra_charge' => true,
                'is_room' => false,
                'amount' => 90000,
            ]);
        }

        $this->putJson("/api/bookings/{$booking->id}/rooms/{$room->id}", [
            'arrival_date' => '2026-08-24',
            'departure_date' => '2026-08-28',
        ])->assertSuccessful();

        $this->assertSame('2026-08-24', $pivot->fresh()->actual_arrival_date->toDateString());
        $this->assertSame(['2026-08-24', '2026-08-25', '2026-08-26', '2026-08-27'],
            $child->fresh()->breakfastDetails()->orderBy('service_date')->pluck('service_date')->map(fn ($date) => $date->toDateString())->all());
        $this->assertSame(90000.0, (float) $child->fresh()->breakfastDetails()->first()->amount);
        // Phụ thu ăn sáng trẻ chỉ nằm ở chi tiết ăn sáng; dòng RM là tiền phòng
        // theo đêm của giai đoạn ở mới (dòng 366).
        $this->assertSame(0, BookingRoomService::where('booking_room_id', $room->id)
            ->where('service_code', '!=', BookingRoomService::CODE_ROOM)
            ->count());
    }

    public function test_inline_service_update_bypasses_service_check_and_syncs_extra_bed(): void
    {
        // 1. Create a booking and booking_room
        $booking = $this->createBooking([
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
        ]);

        $bookingRoom = BookingRoom::create([
            'booking_id' => $booking->id,
            'room_class_id' => $this->roomClass->id,
            'room_number' => '101',
            'price' => 500000,
            'arrival_date' => '2026-08-07 00:00:00',
            'departure_date' => '2026-08-08 00:00:00',
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        // 2. Call POST /api/booking-rooms/{roomId}/services with service_code = RM
        // It should bypass the existence check and save successfully.
        $payloadRM = [
            'service_code' => 'RM',
            'service_name' => 'Dịch vụ phòng nghỉ',
            'service_date' => '2026-08-07',
            'quantity' => 1,
            'rate' => 600000,
            'is_room' => true,
            'folio' => 1,
        ];

        $response = $this->postJson("/api/booking-rooms/{$bookingRoom->id}/services", $payloadRM);
        $response->assertSuccessful();

        $this->assertDatabaseHas('booking_room_services', [
            'booking_room_id' => $bookingRoom->id,
            'service_code' => 'RM',
            'rate' => 600000,
            'department' => 'FO',
        ]);

        // 3. Call POST /api/booking-rooms/{roomId}/services with service_code = EB
        // Seed the EB hotel_service first so it passes the check
        $department = \App\Models\Department::firstOrCreate(['code' => 'FO'], ['name' => 'Reception/ Lê Tân']);
        \App\Models\HotelService::create([
            'code' => 'EB',
            'name' => 'Extra Bed',
            'is_active' => true,
            'price' => 250000,
            'department_id' => $department->id,
        ]);

        $payloadEB = [
            'service_code' => 'EB',
            'service_name' => 'Extra Bed',
            'service_date' => '2026-08-07',
            'quantity' => 1,
            'rate' => 300000,
            'is_room' => false,
            'folio' => 1,
        ];

        $response = $this->postJson("/api/booking-rooms/{$bookingRoom->id}/services", $payloadEB);
        $response->assertSuccessful();

        $this->assertDatabaseHas('booking_room_services', [
            'booking_room_id' => $bookingRoom->id,
            'service_code' => 'EB',
            'department' => 'FO',
        ]);

        // 4. Assert that booking_rooms has extra_bed_qty and extra_bed_rate synced
        $bookingRoom->refresh();
        $this->assertEquals(1, $bookingRoom->extra_bed_qty);
        $this->assertEquals(300000, $bookingRoom->extra_bed_rate);
    }

    /**
     * TC-15: RoomKind is saved as RoomForm ID instead of name.
     */
    public function test_booking_room_stores_room_kind_as_integer(): void
    {
        // 1. Create StandardRate for the room class
        \App\Models\StandardRate::create([
            'room_class_id' => $this->roomClass->id,
            'room_form_id' => $this->roomForm->id,
            'rate' => 100000,
        ]);

        $booking = $this->createBooking([
            'arrival_date' => '2026-08-21',
            'departure_date' => '2026-08-25',
        ]);

        $bRoom = BookingRoom::create([
            'id' => 'G0000001',
            'booking_id' => $booking->id,
            'room_number' => '101',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-21',
            'departure_date' => '2026-08-25',
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        // Assert that RoomKind is stored as an integer/ID (equal to $this->roomForm->id)
        $bRoom->refresh();
        $this->assertEquals($this->roomForm->id, (int)$bRoom->RoomKind);
    }

    /**
     * TC-16: Test bulkUpdate rooms business rules (Reservation and Inhouse restrictions + date sync).
     */
    public function test_bulk_update_rooms_business_rules(): void
    {
        // 1. Create a Booking with 2 rooms
        $booking = $this->createBooking([
            'arrival_date' => '2026-08-21',
            'departure_date' => '2026-08-25',
        ]);

        $room1 = BookingRoom::create([
            'id' => 'G0000001',
            'booking_id' => $booking->id,
            'room_number' => '101',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-21',
            'departure_date' => '2026-08-25',
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        $room2 = BookingRoom::create([
            'id' => 'G0000002',
            'booking_id' => $booking->id,
            'room_number' => '102',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-21',
            'departure_date' => '2026-08-25',
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        // 2. Perform bulkUpdate to shift dates forward: 2026-08-22 to 2026-08-26
        $payload = [
            'room_ids' => ['G0000001', 'G0000002'],
            'arrival_date' => '2026-08-22',
            'departure_date' => '2026-08-26',
            'rate' => 150000,
            'extra_bed_qty' => 1,
            'extra_bed_rate' => 200000,
        ];

        $response = $this->postJson("/api/bookings/{$booking->id}/rooms/bulk-update", $payload);
        $response->assertSuccessful();

        // 3. Verify Room dates, rates, and extra beds updated
        $room1->refresh();
        $room2->refresh();
        $booking->refresh();

        $this->assertEquals('2026-08-22', $room1->arrival_date->toDateString());
        $this->assertEquals('2026-08-26', $room1->departure_date->toDateString());
        $this->assertEquals(150000, $room1->rate);
        $this->assertEquals(1, $room1->extra_bed_qty);
        $this->assertEquals(200000, $room1->extra_bed_rate);

        // 4. Row 393: the booking period only widens. Arrival 21/08 stays
        // (rooms are still inside it); departure widens to the rooms' 26/08.
        $this->assertEquals('2026-08-21', $booking->arrival_date->toDateString());
        $this->assertEquals('2026-08-26', $booking->departure_date->toDateString());
        $this->assertEquals(5, (int) $booking->num_of_days);

        // 5. Test Inhouse restrictions: set room1 status to CHECKED_IN
        $room1->update(['status' => BookingRoom::STATUS_CHECKED_IN]);

        // Sale must reject unsupported fields atomically, even with FO grants.
        $this->postJson("/api/bookings/{$booking->id}/rooms/bulk-update", [
            'current_module' => 'SALE', 'room_ids' => [$room1->id],
            'arrival_date' => '2026-08-23', 'departure_date' => '2026-08-27', 'rate' => 180000,
        ])->assertStatus(422);
        $this->assertEquals('2026-08-22', $room1->fresh()->arrival_date->toDateString());
        $this->assertEquals('2026-08-26', $room1->fresh()->departure_date->toDateString());
        $this->assertEquals(150000, $room1->fresh()->rate);

        HotelConfig::updateOrCreate(['name' => 'AllowReserUpdateRate_DeptDateRoomInhouse'], ['value' => '0']);
        $this->postJson("/api/bookings/{$booking->id}/rooms/bulk-update", [
            'current_module' => 'SALE', 'room_ids' => [$room1->id], 'rate' => 180000,
        ])->assertStatus(403);
        $this->assertEquals(150000, $room1->fresh()->rate);

        HotelConfig::where('name', 'AllowReserUpdateRate_DeptDateRoomInhouse')->update(['value' => '1']);
        $this->postJson("/api/bookings/{$booking->id}/rooms/bulk-update", [
            'current_module' => 'SALE', 'room_ids' => [$room1->id],
            'departure_date' => '2026-08-27', 'rate' => 180000,
        ])->assertSuccessful();
        $this->assertEquals('2026-08-22', $room1->fresh()->arrival_date->toDateString());
        $this->assertEquals('2026-08-27', $room1->fresh()->departure_date->toDateString());
        $this->assertEquals(180000, $room1->fresh()->rate);

        // FO entry requires an actual FO grant, regardless of department labels.
        HotelConfig::where('name', 'AllowReserUpdateRate_DeptDateRoomInhouse')->update(['value' => '0']);
        $this->postJson("/api/bookings/{$booking->id}/rooms/bulk-update", [
            'current_module' => 'FO', 'room_ids' => [$room1->id], 'departure_date' => '2026-08-28',
        ])->assertSuccessful();
        $this->assertEquals('2026-08-22', $room1->fresh()->arrival_date->toDateString());
        $this->assertEquals('2026-08-28', $room1->fresh()->departure_date->toDateString());
    }

    /**
     * Day-use keeps a same-day stay at zero nights and can be edited through
     * the bulk room endpoint without being rejected as an invalid overnight
     * period.
     */
    public function test_day_use_room_persists_zero_nights_and_accepts_same_day_bulk_update(): void
    {
        $booking = $this->createBooking([
            'arrival_date' => '2026-08-21',
            'departure_date' => '2026-08-21',
            'num_of_days' => 0,
            'is_day_use' => true,
        ]);

        $room = BookingRoom::create([
            'id' => 'G-DAY-USE',
            'booking_id' => $booking->id,
            'room_number' => '101',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-21',
            'departure_date' => '2026-08-21',
            'is_day_use' => true,
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        $this->assertSame(0, (int) $room->fresh()->ActutalNumOfDays);
        $this->assertSame(0, (int) $room->fresh()->NumOfDays);

        $this->postJson("/api/bookings/{$booking->id}/rooms/bulk-update", [
            'room_ids' => [$room->id],
            'arrival_date' => '2026-08-21',
            'departure_date' => '2026-08-21',
        ])->assertSuccessful();

        $room->refresh();
        $this->assertSame(0, (int) $room->ActutalNumOfDays);
        $this->assertSame(0, (int) $room->NumOfDays);
    }

    private function headerPayload(Booking $booking, string $arrival, string $departure): array
    {
        return [
            'booking_name' => $booking->booking_name ?: 'Header edit',
            'arrival_date' => $arrival,
            'departure_date' => $departure,
            'num_of_days' => (int) \Carbon\Carbon::parse($arrival)->diffInDays(\Carbon\Carbon::parse($departure)),
            'registration_status_id' => $this->regStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
        ];
    }

    /**
     * Row 394 (screenshot GAL9): an in-house room 02/09-06/09 must block
     * moving the booking period to 09/09-10/09 from "Thông tin đăng ký".
     */
    public function test_booking_period_cannot_leave_inhouse_room_outside(): void
    {
        HotelConfig::updateOrCreate(['name' => 'SyncRoomDateByBookingDate'], ['value' => '1']);
        $booking = $this->createBooking(['arrival_date' => '2026-08-07', 'departure_date' => '2026-08-11', 'num_of_days' => 4]);
        BookingRoom::create([
            'id' => 'G-394-IN', 'booking_id' => $booking->id, 'room_number' => '101',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07', 'departure_date' => '2026-08-11',
            'status' => BookingRoom::STATUS_CHECKED_IN,
        ]);

        $this->putJson("/api/bookings/{$booking->id}", $this->headerPayload($booking, '2026-08-14', '2026-08-15'))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ngày đến hoặc ngày đi của đăng ký không khớp với giai đoạn chi tiết của phòng. Vui lòng kiểm tra thông tin.');

        $booking->refresh();
        $this->assertSame('2026-08-07', $booking->arrival_date->toDateString());
        $this->assertSame('2026-08-11', $booking->departure_date->toDateString());
    }

    /**
     * Row 394: shrinking the period so a checked-out room falls outside is
     * rejected as well; widening it is allowed.
     */
    public function test_booking_period_checks_checked_out_rooms_and_allows_widening(): void
    {
        HotelConfig::updateOrCreate(['name' => 'SyncRoomDateByBookingDate'], ['value' => '0']);
        $booking = $this->createBooking(['arrival_date' => '2026-08-07', 'departure_date' => '2026-08-10', 'num_of_days' => 3]);
        BookingRoom::create([
            'id' => 'G-394-OUT', 'booking_id' => $booking->id, 'room_number' => '101',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07', 'departure_date' => '2026-08-08',
            'status' => BookingRoom::STATUS_CHECKED_OUT,
        ]);

        $this->putJson("/api/bookings/{$booking->id}", $this->headerPayload($booking, '2026-08-08', '2026-08-10'))
            ->assertStatus(422);

        $this->putJson("/api/bookings/{$booking->id}", $this->headerPayload($booking, '2026-08-07', '2026-08-12'))
            ->assertSuccessful();
        $this->assertSame('2026-08-12', $booking->fresh()->departure_date->toDateString());
    }

    /**
     * Row 394 + SyncRoomDateByBookingDate = 1: reservation rooms follow the
     * new period, so the edit is valid.
     */
    public function test_booking_period_with_sync_moves_reservation_rooms(): void
    {
        HotelConfig::updateOrCreate(['name' => 'SyncRoomDateByBookingDate'], ['value' => '1']);
        $booking = $this->createBooking(['arrival_date' => '2026-08-08', 'departure_date' => '2026-08-10', 'num_of_days' => 2]);
        BookingRoom::create([
            'id' => 'G-394-SYNC', 'booking_id' => $booking->id, 'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-08', 'departure_date' => '2026-08-10',
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        $this->putJson("/api/bookings/{$booking->id}", $this->headerPayload($booking, '2026-08-12', '2026-08-14'))
            ->assertSuccessful();
        $room = BookingRoom::where('booking_id', $booking->id)->firstOrFail();
        $this->assertSame('2026-08-12', $room->arrival_date->toDateString());
        $this->assertSame('2026-08-14', $room->departure_date->toDateString());
    }

    /**
     * Row 394 + SyncRoomDateByBookingDate = 0: a reservation room left
     * outside the new period blocks the save.
     */
    public function test_booking_period_without_sync_rejects_reservation_room_outside(): void
    {
        HotelConfig::updateOrCreate(['name' => 'SyncRoomDateByBookingDate'], ['value' => '0']);
        $booking = $this->createBooking(['arrival_date' => '2026-08-08', 'departure_date' => '2026-08-10', 'num_of_days' => 2]);
        BookingRoom::create([
            'id' => 'G-394-NOSYNC', 'booking_id' => $booking->id, 'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-08', 'departure_date' => '2026-08-10',
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        $this->putJson("/api/bookings/{$booking->id}", $this->headerPayload($booking, '2026-08-12', '2026-08-14'))
            ->assertStatus(422);
        $this->assertSame('2026-08-08', $booking->fresh()->arrival_date->toDateString());
    }

    private function roomOnBooking(string $id, Booking $booking, string $arrival, string $departure, string $roomNumber = '101'): BookingRoom
    {
        return BookingRoom::create([
            'id' => $id, 'booking_id' => $booking->id, 'room_number' => $roomNumber,
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => $arrival, 'departure_date' => $departure,
            'rate' => 500000, 'status' => BookingRoom::STATUS_BOOKED,
        ]);
    }

    /**
     * Row 395 (screenshot GAL33/GAL32): extending the departure over another
     * booking holding the same room number is rejected.
     */
    public function test_quick_update_departure_cannot_overlap_same_room_number(): void
    {
        $bk1 = $this->createBooking(['arrival_date' => '2026-08-07', 'departure_date' => '2026-08-12']);
        $bk2 = $this->createBooking(['arrival_date' => '2026-08-08', 'departure_date' => '2026-08-10']);
        $room1 = $this->roomOnBooking('G-395-A', $bk1, '2026-08-07', '2026-08-08');
        $this->roomOnBooking('G-395-B', $bk2, '2026-08-08', '2026-08-10');

        $this->postJson("/api/bookings/{$bk1->id}/rooms/bulk-update", [
            'room_ids' => [$room1->id], 'departure_date' => '2026-08-10',
        ])->assertStatus(422)->assertJsonPath('code', 'ROOM_NUMBER_CONFLICT');

        $this->assertSame('2026-08-08', $room1->fresh()->departure_date->toDateString());
    }

    /**
     * Row 395: same check for the arrival date.
     */
    public function test_quick_update_arrival_cannot_overlap_same_room_number(): void
    {
        $bk1 = $this->createBooking(['arrival_date' => '2026-08-07', 'departure_date' => '2026-08-12']);
        $bk2 = $this->createBooking(['arrival_date' => '2026-08-07', 'departure_date' => '2026-08-09']);
        $this->roomOnBooking('G-395-C', $bk2, '2026-08-07', '2026-08-09');
        $room1 = $this->roomOnBooking('G-395-D', $bk1, '2026-08-09', '2026-08-12');

        $this->postJson("/api/bookings/{$bk1->id}/rooms/bulk-update", [
            'room_ids' => [$room1->id], 'arrival_date' => '2026-08-08',
        ])->assertStatus(422)->assertJsonPath('code', 'ROOM_NUMBER_CONFLICT');

        $this->assertSame('2026-08-09', $room1->fresh()->arrival_date->toDateString());
    }

    /**
     * Row 395: back-to-back stays stay valid, and a period inside an OOO
     * lock is rejected.
     */
    public function test_quick_update_allows_back_to_back_and_rejects_lock(): void
    {
        $bk1 = $this->createBooking(['arrival_date' => '2026-08-07', 'departure_date' => '2026-08-15']);
        $bk2 = $this->createBooking(['arrival_date' => '2026-08-10', 'departure_date' => '2026-08-11']);
        $room1 = $this->roomOnBooking('G-395-E', $bk1, '2026-08-07', '2026-08-08');
        $this->roomOnBooking('G-395-F', $bk2, '2026-08-10', '2026-08-11');

        $this->postJson("/api/bookings/{$bk1->id}/rooms/bulk-update", [
            'room_ids' => [$room1->id], 'departure_date' => '2026-08-10',
        ])->assertSuccessful();

        \App\Models\RoomLock::create([
            'room_number' => '101', 'start_date' => '2026-08-12 00:00:00', 'end_date' => '2026-08-13 18:00:00',
            'lock_type' => 'OOO', 'is_active' => 1, 'reason' => 'Bảo trì',
        ]);
        $this->postJson("/api/bookings/{$bk1->id}/rooms/bulk-update", [
            'room_ids' => [$room1->id], 'arrival_date' => '2026-08-11', 'departure_date' => '2026-08-14',
        ])->assertStatus(422)->assertJsonPath('code', 'ROOM_NUMBER_CONFLICT');
    }

    /**
     * Row 395: two selected rooms with the same room number cannot be moved
     * onto the same nights together.
     */
    public function test_quick_update_rejects_overlap_between_selected_rooms(): void
    {
        $booking = $this->createBooking(['arrival_date' => '2026-08-07', 'departure_date' => '2026-08-12']);
        $a = $this->roomOnBooking('G-395-G', $booking, '2026-08-07', '2026-08-08');
        $b = $this->roomOnBooking('G-395-H', $booking, '2026-08-08', '2026-08-09');

        $this->postJson("/api/bookings/{$booking->id}/rooms/bulk-update", [
            'room_ids' => [$a->id, $b->id], 'arrival_date' => '2026-08-10', 'departure_date' => '2026-08-11',
        ])->assertStatus(422)->assertJsonPath('code', 'ROOM_NUMBER_CONFLICT');
    }

    /**
     * Row 391: a same-day room may be created without day-use. Only the room
     * with "Ở theo giờ" on is day-use; the other one has zero nights.
     */
    public function test_same_day_room_without_day_use_can_be_created(): void
    {
        $this->postJson('/api/bookings', [
            'booking_name' => 'Day use mix',
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-07',
            'num_of_days' => 0,
            'is_day_use' => 1,
            'registration_status_id' => $this->regStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
            'room_allocations' => [[
                'roomClassId' => $this->roomClass->id,
                'quantity' => 2,
                'price' => 500000,
                'rooms' => [
                    ['roomNumber' => '101', 'arrivalDate' => '2026-08-07', 'departureDate' => '2026-08-07', 'hourly' => true, 'is_day_use' => true],
                    ['roomNumber' => '102', 'arrivalDate' => '2026-08-07', 'departureDate' => '2026-08-07', 'hourly' => false, 'is_day_use' => false],
                ],
            ]],
        ])->assertSuccessful();

        $dayUse = BookingRoom::where('room_number', '101')->firstOrFail();
        $sameDay = BookingRoom::where('room_number', '102')->firstOrFail();
        $this->assertTrue((bool) $dayUse->is_day_use);
        $this->assertFalse((bool) $sameDay->is_day_use);
        $this->assertSame(0, (int) $sameDay->ActutalNumOfDays);

        $booked = app(\App\Services\RoomAvailabilityService::class)
            ->getBookedCount($this->roomClass->id, '2026-08-07', '2026-08-07');
        $this->assertSame(1, (int) $booked);
    }

    /**
     * Row 391: turning "Ở theo giờ" off on one room must not be overridden by
     * the booking-level day-use flag of a same-day header.
     */
    public function test_update_keeps_per_room_day_use_switch(): void
    {
        $booking = $this->createBooking([
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-07',
            'num_of_days' => 0,
            'is_day_use' => true,
        ]);
        $ids = [];
        foreach (['G-DU-A' => '101', 'G-DU-B' => '102'] as $id => $roomNumber) {
            $ids[$roomNumber] = BookingRoom::create([
                'id' => $id,
                'booking_id' => $booking->id,
                'room_number' => $roomNumber,
                'room_class_id' => $this->roomClass->id,
                'arrival_date' => '2026-08-07',
                'departure_date' => '2026-08-07',
                'is_day_use' => true,
                'rate' => 500000,
                'status' => BookingRoom::STATUS_BOOKED,
            ])->id;
        }

        $this->putJson("/api/bookings/{$booking->id}", [
            'booking_name' => $booking->booking_name,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-07',
            'num_of_days' => 0,
            'is_day_use' => 1,
            'registration_status_id' => $this->regStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
            'room_allocations' => [[
                'roomClassId' => $this->roomClass->id,
                'quantity' => 2,
                'price' => 500000,
                'rooms' => [
                    ['bookingRoomId' => $ids['101'], 'roomNumber' => '101', 'arrivalDate' => '2026-08-07', 'departureDate' => '2026-08-07', 'hourly' => true, 'is_day_use' => true, 'price' => 500000],
                    ['bookingRoomId' => $ids['102'], 'roomNumber' => '102', 'arrivalDate' => '2026-08-07', 'departureDate' => '2026-08-07', 'hourly' => false, 'is_day_use' => false, 'price' => 500000],
                ],
            ]],
        ])->assertSuccessful();

        $this->assertTrue((bool) BookingRoom::find($ids['101'])->is_day_use);
        $this->assertFalse((bool) BookingRoom::find($ids['102'])->is_day_use);
    }

    /**
     * Row 391: a day-use room is charged once, on its arrival date.
     */
    public function test_day_use_room_gets_projected_room_charge_on_arrival(): void
    {
        $booking = $this->createBooking([
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-07',
            'is_day_use' => true,
        ]);
        $room = BookingRoom::create([
            'id' => 'G-DU-RM',
            'booking_id' => $booking->id,
            'room_number' => '101',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-07',
            'is_day_use' => true,
            'rate' => 500000,
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        app(\App\Services\BookingRoomStayChargeService::class)->synchronize($room->fresh(), 500000.0);

        $charges = \App\Models\BookingRoomService::where('booking_room_id', $room->id)->get();
        $this->assertCount(1, $charges);
        $this->assertSame('2026-08-07', \Carbon\Carbon::parse($charges->first()->service_date)->toDateString());
        $this->assertEquals(500000, (float) $charges->first()->rate);

        // Same-day room without day-use has no room charge.
        $room->update(['is_day_use' => false]);
        app(\App\Services\BookingRoomStayChargeService::class)->synchronize($room->fresh(), 500000.0);
        $this->assertSame(0, \App\Models\BookingRoomService::where('booking_room_id', $room->id)->count());
    }

    /**
     * Row 393: quick update allows departure = arrival (same-day room without
     * day-use, zero nights) and only rejects departure < arrival.
     */
    public function test_quick_update_allows_same_day_and_rejects_departure_before_arrival(): void
    {
        $booking = $this->createBooking(['arrival_date' => '2026-08-07', 'departure_date' => '2026-08-09', 'num_of_days' => 2]);
        $room = BookingRoom::create([
            'id' => 'G-QU-1', 'booking_id' => $booking->id, 'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07', 'departure_date' => '2026-08-09', 'rate' => 500000,
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        $this->postJson("/api/bookings/{$booking->id}/rooms/bulk-update", [
            'room_ids' => [$room->id], 'arrival_date' => '2026-08-08', 'departure_date' => '2026-08-08',
        ])->assertSuccessful()->assertJson(['success' => true]);

        $room->refresh();
        $this->assertSame('2026-08-08', $room->departure_date->toDateString());
        $this->assertFalse((bool) $room->is_day_use);
        $this->assertSame(0, (int) $room->ActutalNumOfDays);

        $this->postJson("/api/bookings/{$booking->id}/rooms/bulk-update", [
            'room_ids' => [$room->id], 'departure_date' => '2026-08-07',
        ])->assertStatus(422);
    }

    /**
     * Row 393: the booking arrival stays as is while rooms remain inside it and
     * is widened (with nights recalculated) when a room goes outside.
     * Row 432: an edited departure makes the booking departure follow the rooms.
     */
    public function test_quick_update_only_widens_booking_period(): void
    {
        $booking = $this->createBooking(['arrival_date' => '2026-08-08', 'departure_date' => '2026-08-17', 'num_of_days' => 9]);
        $room = BookingRoom::create([
            'id' => 'G-QU-2', 'booking_id' => $booking->id, 'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-08', 'departure_date' => '2026-08-17', 'rate' => 500000,
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        $this->postJson("/api/bookings/{$booking->id}/rooms/bulk-update", [
            'room_ids' => [$room->id], 'arrival_date' => '2026-08-09', 'departure_date' => '2026-08-16',
        ])->assertSuccessful();
        $booking->refresh();
        $this->assertSame('2026-08-08', $booking->arrival_date->toDateString());
        $this->assertSame('2026-08-16', $booking->departure_date->toDateString());
        $this->assertSame(8, (int) $booking->num_of_days);

        $this->postJson("/api/bookings/{$booking->id}/rooms/bulk-update", [
            'room_ids' => [$room->id], 'departure_date' => '2026-08-17',
        ])->assertSuccessful();

        $this->postJson("/api/bookings/{$booking->id}/rooms/bulk-update", [
            'room_ids' => [$room->id], 'arrival_date' => '2026-08-07',
        ])->assertSuccessful();
        $booking->refresh();
        $this->assertSame('2026-08-07', $booking->arrival_date->toDateString());
        $this->assertSame('2026-08-17', $booking->departure_date->toDateString());
        $this->assertSame(10, (int) $booking->num_of_days);
    }

    /**
     * Row 432: shortening a room's departure pulls the booking departure back
     * to the latest departure of the rooms shown on it; cancelled rows are
     * ignored and other rooms keep the booking open.
     */
    public function test_quick_update_departure_shrinks_booking_to_latest_shown_room(): void
    {
        $booking = $this->createBooking(['arrival_date' => '2026-08-08', 'departure_date' => '2026-08-17', 'num_of_days' => 9]);
        $make = fn (string $id, string $out, int $status) => BookingRoom::create([
            'id' => $id, 'booking_id' => $booking->id, 'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-08', 'departure_date' => $out, 'rate' => 500000,
            'status' => $status,
        ]);
        $a = $make('G-432-A', '2026-08-17', BookingRoom::STATUS_BOOKED);
        $b = $make('G-432-B', '2026-08-14', BookingRoom::STATUS_BOOKED);
        $make('G-432-C', '2026-08-20', BookingRoom::STATUS_CANCELLED);

        $this->postJson("/api/bookings/{$booking->id}/rooms/bulk-update", [
            'room_ids' => [$a->id], 'departure_date' => '2026-08-12',
        ])->assertSuccessful();
        $booking->refresh();
        $this->assertSame('2026-08-14', $booking->departure_date->toDateString());
        $this->assertSame(6, (int) $booking->num_of_days);

        $this->postJson("/api/bookings/{$booking->id}/rooms/bulk-update", [
            'room_ids' => [$b->id], 'departure_date' => '2026-08-10',
        ])->assertSuccessful();
        $booking->refresh();
        $this->assertSame('2026-08-12', $booking->departure_date->toDateString());
        $this->assertSame(4, (int) $booking->num_of_days);
    }

    /**
     * Row 431: quick update of extra bed qty/rate overwrites the nightly EB
     * rows in booking_room_services, not only booking_rooms.
     */
    public function test_quick_update_extra_bed_overwrites_nightly_services(): void
    {
        $booking = $this->createBooking(['arrival_date' => '2026-08-08', 'departure_date' => '2026-08-10', 'num_of_days' => 2]);
        $room = BookingRoom::create([
            'id' => 'G-431', 'booking_id' => $booking->id, 'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-08', 'departure_date' => '2026-08-10', 'rate' => 500000,
            'extra_bed_qty' => 1, 'extra_bed_rate' => 100000,
            'status' => BookingRoom::STATUS_BOOKED,
        ]);
        foreach (['2026-08-08', '2026-08-09'] as $date) {
            BookingRoomService::create([
                'booking_room_id' => $room->id, 'service_code' => BookingRoomService::CODE_EXTRA_BED,
                'service_name' => 'Extra Bed', 'service_date' => $date,
                'quantity' => 1, 'rate' => 100000, 'is_room' => 1, 'is_posted' => 0,
            ]);
        }

        $this->postJson("/api/bookings/{$booking->id}/rooms/bulk-update", [
            'room_ids' => [$room->id], 'extra_bed_qty' => 2, 'extra_bed_rate' => 250000,
        ])->assertSuccessful();

        $services = BookingRoomService::where('booking_room_id', $room->id)
            ->where('service_code', BookingRoomService::CODE_EXTRA_BED)
            ->orderBy('service_date')->get();
        $this->assertCount(2, $services);
        foreach ($services as $service) {
            $this->assertEquals(2, (float) $service->quantity);
            $this->assertEquals(250000, (float) $service->rate);
        }

        // Only the submitted field is overwritten.
        $this->postJson("/api/bookings/{$booking->id}/rooms/bulk-update", [
            'room_ids' => [$room->id], 'extra_bed_rate' => 300000,
        ])->assertSuccessful();
        $service = BookingRoomService::where('booking_room_id', $room->id)
            ->where('service_code', BookingRoomService::CODE_EXTRA_BED)->first();
        $this->assertEquals(2, (float) $service->quantity);
        $this->assertEquals(300000, (float) $service->rate);
    }

    /**
     * Row 432 (task example): 1 room 01 -> 10, quick update departure to 08,
     * the booking departure becomes 08 too.
     */
    public function test_row432_single_room_departure_pulls_booking_departure(): void
    {
        $booking = $this->createBooking(['arrival_date' => '2026-08-08', 'departure_date' => '2026-08-17', 'num_of_days' => 9]);
        $room = BookingRoom::create([
            'id' => 'G-432-S', 'booking_id' => $booking->id, 'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-08', 'departure_date' => '2026-08-17', 'rate' => 500000,
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        $this->postJson("/api/bookings/{$booking->id}/rooms/bulk-update", [
            'room_ids' => [$room->id], 'departure_date' => '2026-08-15',
        ])->assertSuccessful();

        $booking->refresh();
        $this->assertSame('2026-08-08', $booking->arrival_date->toDateString());
        $this->assertSame('2026-08-15', $booking->departure_date->toDateString());
        $this->assertSame(7, (int) $booking->num_of_days);
    }

    /**
     * Row 432: only a departure edit shrinks the booking; an arrival-only edit
     * keeps the row 393 behaviour (period stays as is).
     */
    public function test_row432_arrival_only_update_keeps_booking_departure(): void
    {
        $booking = $this->createBooking(['arrival_date' => '2026-08-08', 'departure_date' => '2026-08-17', 'num_of_days' => 9]);
        $room = BookingRoom::create([
            'id' => 'G-432-X', 'booking_id' => $booking->id, 'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-08', 'departure_date' => '2026-08-12', 'rate' => 500000,
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        $this->postJson("/api/bookings/{$booking->id}/rooms/bulk-update", [
            'room_ids' => [$room->id], 'arrival_date' => '2026-08-09',
        ])->assertSuccessful();

        $booking->refresh();
        $this->assertSame('2026-08-08', $booking->arrival_date->toDateString());
        $this->assertSame('2026-08-17', $booking->departure_date->toDateString());
        $this->assertSame(9, (int) $booking->num_of_days);
    }

    /**
     * Row 432: checked-out / no-show rows are still shown on the booking, so
     * they keep the booking departure; only status 3 rows are ignored.
     */
    public function test_row432_non_cancelled_history_rows_still_count(): void
    {
        $booking = $this->createBooking(['arrival_date' => '2026-08-08', 'departure_date' => '2026-08-17', 'num_of_days' => 9]);
        $make = fn (string $id, string $out, int $status) => BookingRoom::create([
            'id' => $id, 'booking_id' => $booking->id, 'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-08', 'departure_date' => $out, 'rate' => 500000,
            'status' => $status,
        ]);
        $a = $make('G-432-H1', '2026-08-17', BookingRoom::STATUS_BOOKED);
        $make('G-432-H2', '2026-08-13', BookingRoom::STATUS_NOSHOW);

        $this->postJson("/api/bookings/{$booking->id}/rooms/bulk-update", [
            'room_ids' => [$a->id], 'departure_date' => '2026-08-11',
        ])->assertSuccessful();

        $this->assertSame('2026-08-13', $booking->fresh()->departure_date->toDateString());
    }

    /**
     * Row 431: several selected rooms all get their nightly EB rows updated.
     */
    public function test_row431_extra_bed_update_applies_to_every_selected_room(): void
    {
        $booking = $this->createBooking(['arrival_date' => '2026-08-08', 'departure_date' => '2026-08-10', 'num_of_days' => 2]);
        $ids = [];
        foreach (['G-431-A', 'G-431-B'] as $id) {
            $id = $ids[] = BookingRoom::create([
                'id' => $id, 'booking_id' => $booking->id, 'room_class_id' => $this->roomClass->id,
                'arrival_date' => '2026-08-08', 'departure_date' => '2026-08-10', 'rate' => 500000,
                'extra_bed_qty' => 1, 'extra_bed_rate' => 100000,
                'status' => BookingRoom::STATUS_BOOKED,
            ])->id;
            foreach (['2026-08-08', '2026-08-09'] as $date) {
                BookingRoomService::create([
                    'booking_room_id' => $id, 'service_code' => BookingRoomService::CODE_EXTRA_BED,
                    'service_name' => 'Extra Bed', 'service_date' => $date,
                    'quantity' => 1, 'rate' => 100000, 'is_room' => 1, 'is_posted' => 0,
                ]);
            }
        }

        $this->postJson("/api/bookings/{$booking->id}/rooms/bulk-update", [
            'room_ids' => $ids, 'extra_bed_qty' => 3, 'extra_bed_rate' => 150000,
        ])->assertSuccessful();

        $services = BookingRoomService::whereIn('booking_room_id', $ids)
            ->where('service_code', BookingRoomService::CODE_EXTRA_BED)->get();
        $this->assertCount(4, $services);
        foreach ($services as $service) {
            $this->assertEquals(3, (float) $service->quantity);
            $this->assertEquals(150000, (float) $service->rate);
        }
        $this->assertEquals(3, BookingRoom::find($ids[0])->extra_bed_qty);
        $this->assertEquals(150000, (float) BookingRoom::find($ids[1])->extra_bed_rate);
    }

    /**
     * Row 431: posted nights and nights before the system date keep their
     * values; only open nights from the system date on are overwritten.
     */
    public function test_row431_extra_bed_update_keeps_posted_and_past_nights(): void
    {
        $booking = $this->createBooking(['arrival_date' => '2026-08-06', 'departure_date' => '2026-08-10', 'num_of_days' => 4]);
        $room = BookingRoom::create([
            'id' => 'G-431-P', 'booking_id' => $booking->id, 'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-06', 'departure_date' => '2026-08-10', 'rate' => 500000,
            'extra_bed_qty' => 1, 'extra_bed_rate' => 100000,
            'status' => BookingRoom::STATUS_BOOKED,
        ]);
        $nights = [
            '2026-08-06' => 0, // before system date 2026-08-07
            '2026-08-07' => 1, // posted
            '2026-08-08' => 0,
            '2026-08-09' => 0,
        ];
        foreach ($nights as $date => $posted) {
            BookingRoomService::create([
                'booking_room_id' => $room->id, 'service_code' => BookingRoomService::CODE_EXTRA_BED,
                'service_name' => 'Extra Bed', 'service_date' => $date,
                'quantity' => 1, 'rate' => 100000, 'is_room' => 1, 'is_posted' => $posted,
            ]);
        }

        $this->postJson("/api/bookings/{$booking->id}/rooms/bulk-update", [
            'room_ids' => [$room->id], 'extra_bed_qty' => 2, 'extra_bed_rate' => 250000,
        ])->assertSuccessful();

        $byDate = BookingRoomService::where('booking_room_id', $room->id)
            ->where('service_code', BookingRoomService::CODE_EXTRA_BED)->get()
            ->keyBy(fn ($s) => $s->service_date->toDateString());
        $this->assertEquals([1, 100000], [(float) $byDate['2026-08-06']->quantity, (float) $byDate['2026-08-06']->rate]);
        $this->assertEquals([1, 100000], [(float) $byDate['2026-08-07']->quantity, (float) $byDate['2026-08-07']->rate]);
        $this->assertEquals([2, 250000], [(float) $byDate['2026-08-08']->quantity, (float) $byDate['2026-08-08']->rate]);
        $this->assertEquals([2, 250000], [(float) $byDate['2026-08-09']->quantity, (float) $byDate['2026-08-09']->rate]);
    }

    /**
     * Row 431: a room without EB rows gets one row per night when extra beds
     * are added via quick update.
     */
    public function test_row431_extra_bed_update_creates_missing_nights(): void
    {
        $booking = $this->createBooking(['arrival_date' => '2026-08-08', 'departure_date' => '2026-08-11', 'num_of_days' => 3]);
        $room = BookingRoom::create([
            'id' => 'G-431-N', 'booking_id' => $booking->id, 'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-08', 'departure_date' => '2026-08-11', 'rate' => 500000,
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        $this->postJson("/api/bookings/{$booking->id}/rooms/bulk-update", [
            'room_ids' => [$room->id], 'extra_bed_qty' => 1, 'extra_bed_rate' => 200000,
        ])->assertSuccessful();

        $services = BookingRoomService::where('booking_room_id', $room->id)
            ->where('service_code', BookingRoomService::CODE_EXTRA_BED)->orderBy('service_date')->get();
        $this->assertSame(['2026-08-08', '2026-08-09', '2026-08-10'],
            $services->map(fn ($s) => $s->service_date->toDateString())->all());
        foreach ($services as $service) {
            $this->assertEquals(1, (float) $service->quantity);
            $this->assertEquals(200000, (float) $service->rate);
        }
    }

    /**
     * Row 433: a cancelled (status 3) booking can still be opened to view its
     * registration info, but editing it is rejected.
     */
    public function test_row433_cancelled_booking_is_viewable_but_not_editable(): void
    {
        $booking = $this->createBooking([
            'booking_name' => 'Cancelled BK',
            'arrival_date' => '2026-08-08', 'departure_date' => '2026-08-10',
            'status' => Booking::STATUS_DELETED,
        ]);
        BookingRoom::create([
            'id' => 'G-433', 'booking_id' => $booking->id, 'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-08', 'departure_date' => '2026-08-10', 'rate' => 500000,
            'status' => BookingRoom::STATUS_CANCELLED,
        ]);

        $this->getJson("/api/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.booking_name', 'Cancelled BK');

        $this->putJson("/api/bookings/{$booking->id}", ['booking_name' => 'Changed'])
            ->assertStatus(422);
        $this->assertSame('Cancelled BK', $booking->fresh()->booking_name);
    }

    /**
     * Row 397: price edited on the registration grid row only updates rate and
     * base_price; no nightly booking_room_services rows are created.
     */
    public function test_grid_row_price_edit_only_updates_room_rate(): void
    {
        HotelConfig::updateOrCreate(['name' => 'SyncRoomDateByBookingDate'], ['value' => '0']);
        $booking = $this->createBooking(['arrival_date' => '2026-08-07', 'departure_date' => '2026-08-10', 'num_of_days' => 3]);
        $room = BookingRoom::create([
            'id' => 'G-397', 'booking_id' => $booking->id, 'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07', 'departure_date' => '2026-08-10',
            'rate' => 500000, 'base_price' => 500000, 'status' => BookingRoom::STATUS_BOOKED,
        ]);

        $this->putJson("/api/bookings/{$booking->id}", [
            'booking_name' => $booking->booking_name,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-10',
            'num_of_days' => 3,
            'registration_status_id' => $this->regStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
            'room_allocations' => [[
                'roomClassId' => $this->roomClass->id,
                'quantity' => 1,
                'price' => 650000,
                'rooms' => [[
                    'bookingRoomId' => $room->id,
                    'arrivalDate' => '2026-08-07',
                    'departureDate' => '2026-08-10',
                    'price' => 650000,
                    'basePrice' => 650000,
                    'dailyRoomPrices' => null,
                    'manualRateOverride' => true,
                ]],
            ]],
        ])->assertSuccessful();

        $fresh = $room->fresh();
        $this->assertEquals(650000.0, (float) $fresh->rate);
        $this->assertEquals(650000.0, (float) $fresh->base_price);
        $this->assertSame(0, \App\Models\BookingRoomService::where('booking_room_id', $room->id)
            ->whereIn('service_code', ['RM', 'ROOM_CHARGE'])->count());
    }

    /**
     * EB (khách báo): phòng không đặt thêm giường thì lưu đăng ký không được
     * sinh dòng EB số lượng 0; dòng EB đã có thì vẫn được đưa về 0 (task 368).
     */
    public function test_saving_grid_does_not_create_zero_extra_bed_rows(): void
    {
        HotelConfig::updateOrCreate(['name' => 'SyncRoomDateByBookingDate'], ['value' => '0']);
        $booking = $this->createBooking(['arrival_date' => '2026-08-07', 'departure_date' => '2026-08-09', 'num_of_days' => 2]);
        $plain = BookingRoom::create([
            'id' => 'G-EB-NONE', 'booking_id' => $booking->id, 'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07', 'departure_date' => '2026-08-09',
            'rate' => 500000, 'extra_bed_qty' => 0, 'status' => BookingRoom::STATUS_BOOKED,
        ]);
        $withEb = BookingRoom::create([
            'id' => 'G-EB-SET', 'booking_id' => $booking->id, 'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07', 'departure_date' => '2026-08-09',
            'rate' => 500000, 'extra_bed_qty' => 0, 'status' => BookingRoom::STATUS_BOOKED,
        ]);

        $room = fn (BookingRoom $r, int $ebQty) => [
            'bookingRoomId' => $r->id, 'arrivalDate' => '2026-08-07', 'departureDate' => '2026-08-09',
            'price' => 500000, 'extraBedQty' => $ebQty, 'extraBedPrice' => $ebQty ? 300000 : 0,
            'dailyExtraBeds' => [
                ['dateStr' => '2026-08-07', 'quantity' => $ebQty, 'rate' => $ebQty ? 300000 : 0],
                ['dateStr' => '2026-08-08', 'quantity' => 0, 'rate' => 0],
            ],
        ];
        $save = fn (int $ebQtyForSecondRoom) => $this->putJson("/api/bookings/{$booking->id}", [
            'booking_name' => $booking->booking_name, 'arrival_date' => '2026-08-07', 'departure_date' => '2026-08-09',
            'num_of_days' => 2, 'registration_status_id' => $this->regStatus->booking_status_id,
            'company_id' => 1, 'market_id' => 1, 'customer_source_id' => 1,
            'room_allocations' => [[
                'roomClassId' => $this->roomClass->id, 'quantity' => 2, 'price' => 500000,
                'rooms' => [$room($plain, 0), $room($withEb, $ebQtyForSecondRoom)],
            ]],
        ])->assertSuccessful();
        $ebRows = fn (BookingRoom $r) => \App\Models\BookingRoomService::where('booking_room_id', $r->id)->where('service_code', 'EB');

        // 1. Phòng có EB đêm 07 = 1: chỉ có dòng EB của đêm đó; phòng không có EB thì không có dòng nào.
        $save(1);
        $this->assertSame(0, $ebRows($plain)->count());
        $this->assertSame(1, $ebRows($withEb)->count());

        // 2. Chỉnh EB về 0: dòng EB của phòng đó được giữ với số lượng 0 (task 368);
        // phòng không đặt EB vẫn không có dòng nào.
        $save(0);
        $this->assertSame(0, $ebRows($plain)->count());
        $this->assertGreaterThan(0, $ebRows($withEb)->count());
        $this->assertSame(0.0, (float) $ebRows($withEb)->sum('quantity'));
    }

    /**
     * Sửa giá tiền phòng của MỘT đêm (chi tiết dịch vụ) chỉ lưu dòng RM của
     * đêm đó; booking_rooms.rate/base_price giữ nguyên để các đêm khác không
     * nhảy theo.
     */
    public function test_single_night_rate_edit_keeps_room_rate(): void
    {
        $booking = $this->createBooking(['arrival_date' => '2026-08-07', 'departure_date' => '2026-08-09', 'num_of_days' => 2]);
        $room = BookingRoom::create([
            'id' => 'G-NIGHT-RATE', 'booking_id' => $booking->id, 'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07', 'departure_date' => '2026-08-09',
            'rate' => 900000, 'base_price' => 900000, 'status' => BookingRoom::STATUS_BOOKED,
        ]);

        $this->postJson("/api/booking-rooms/{$room->id}/services", [
            'booking_room_id' => $room->id,
            'service_code' => 'RM',
            'service_name' => 'Dịch vụ phòng nghỉ',
            'service_date' => '2026-08-08',
            'quantity' => 1,
            'rate' => 700000,
            'is_room' => 1,
        ])->assertSuccessful();

        $fresh = $room->fresh();
        $this->assertEquals(900000.0, (float) $fresh->rate);
        $this->assertEquals(900000.0, (float) $fresh->base_price);
        $rows = \App\Models\BookingRoomService::where('booking_room_id', $room->id)->where('service_code', 'RM')->get();
        $this->assertCount(1, $rows);
        $this->assertEquals(700000.0, (float) $rows->first()->rate);
    }

    /**
     * Dòng 366: phòng 08/08-12/08 có giá theo đêm đã chỉnh tay (đêm cuối 0đ,
     * EB đêm đầu và đêm cuối 0đ). Giá EB mặc định của phòng là 300.000.
     */
    private function roomWithManualNightlyRates(Booking $booking, string $id): BookingRoom
    {
        $room = BookingRoom::create([
            'id' => $id, 'booking_id' => $booking->id, 'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-08', 'departure_date' => '2026-08-12',
            'rate' => 600000, 'base_price' => 600000,
            'extra_bed_qty' => 1, 'extra_bed_rate' => 300000,
            'status' => BookingRoom::STATUS_BOOKED,
        ]);
        $nights = [
            '2026-08-08' => [600000, 0],
            '2026-08-09' => [600000, 300000],
            '2026-08-10' => [600000, 300000],
            '2026-08-11' => [0, 0],
        ];
        foreach ($nights as $date => [$roomRate, $ebRate]) {
            BookingRoomService::create(['booking_room_id' => $room->id, 'service_code' => 'RM', 'service_name' => 'Dịch vụ phòng nghỉ', 'service_date' => $date, 'quantity' => 1, 'rate' => $roomRate, 'is_room' => 1, 'is_posted' => 0]);
            BookingRoomService::create(['booking_room_id' => $room->id, 'service_code' => 'EB', 'service_name' => 'Extra Bed', 'service_date' => $date, 'quantity' => 1, 'rate' => $ebRate, 'is_room' => 1, 'is_posted' => 0]);
        }

        return $room;
    }

    /** @return array<string, float> ngày => đơn giá của mã dịch vụ */
    private function nightlyRates(BookingRoom $room, string $code): array
    {
        return BookingRoomService::where('booking_room_id', $room->id)
            ->where('service_code', $code)
            ->orderBy('service_date')
            ->get()
            ->mapWithKeys(fn (BookingRoomService $service) => [$service->service_date->toDateString() => (float) $service->rate])
            ->all();
    }

    private function bookingEditPayload(Booking $booking, BookingRoom $room, string $departure): array
    {
        $services = BookingRoomService::where('booking_room_id', $room->id)->get();

        return $this->headerPayload($booking, '2026-08-08', $departure) + [
            'room_allocations' => [[
                'roomClassId' => $this->roomClass->id,
                'quantity' => 1,
                'price' => 600000,
                'rooms' => [[
                    'bookingRoomId' => $room->id,
                    'roomClassId' => $this->roomClass->id,
                    'guestName' => 'Guest 366',
                    'price' => 600000,
                    'extraBedQty' => 1,
                    'extraBedPrice' => 300000,
                    'arrivalDate' => '2026-08-08',
                    'departureDate' => $departure,
                    'dailyRoomPrices' => $services->where('service_code', 'RM')
                        ->mapWithKeys(fn ($s) => [$s->service_date->toDateString() => (float) $s->rate])->all(),
                    'dailyExtraBeds' => $services->where('service_code', 'EB')->map(fn ($s) => [
                        'dateStr' => $s->service_date->toDateString(),
                        'quantity' => (float) $s->quantity,
                        'rate' => (float) $s->rate,
                        'isRoom' => true,
                    ])->values()->all(),
                ]],
            ]],
        ];
    }

    public function test_room_info_extension_keeps_manual_nightly_rates_and_updates_booking_nights(): void
    {
        $booking = $this->createBooking(['arrival_date' => '2026-08-08', 'departure_date' => '2026-08-12', 'num_of_days' => 4]);
        $room = $this->roomWithManualNightlyRates($booking, 'G-366-INFO');
        $guest = Guest::create(['full_name' => 'Guest 366', 'guest_status' => Guest::STATUS_ACTIVE]);
        BookingRoomGuest::create(['booking_room_id' => $room->id, 'guest_id' => $guest->id, 'is_primary' => true]);

        // Màn hình Thông tin phòng luôn gửi kèm giá phòng và SL/giá EB của phòng.
        $this->putJson("/api/booking-rooms/{$room->id}/guests/{$guest->id}", [
            'full_name' => 'Guest 366',
            'arrival_date' => '2026-08-08',
            'departure_date' => '2026-08-14',
            'rate' => 600000,
            'rate_code' => null,
            'extra_bed_qty' => 1,
            'extra_bed_rate' => 300000,
            'breakfast' => true,
            'is_day_use' => false,
        ])->assertSuccessful();

        $this->assertSame([
            '2026-08-08' => 600000.0, '2026-08-09' => 600000.0, '2026-08-10' => 600000.0,
            '2026-08-11' => 0.0, '2026-08-12' => 600000.0, '2026-08-13' => 600000.0,
        ], $this->nightlyRates($room, 'RM'));
        $this->assertSame([
            '2026-08-08' => 0.0, '2026-08-09' => 300000.0, '2026-08-10' => 300000.0,
            '2026-08-11' => 0.0, '2026-08-12' => 300000.0, '2026-08-13' => 300000.0,
        ], $this->nightlyRates($room, 'EB'));

        $booking->refresh();
        $this->assertSame('2026-08-14', $booking->departure_date->toDateString());
        $this->assertSame(6, (int) $booking->num_of_days);
    }

    public function test_booking_edit_extension_charges_new_nights_and_keeps_default_extra_bed_rate(): void
    {
        HotelConfig::updateOrCreate(['name' => 'SyncRoomDateByBookingDate'], ['value' => '1']);
        $booking = $this->createBooking(['arrival_date' => '2026-08-08', 'departure_date' => '2026-08-12', 'num_of_days' => 4]);
        $room = $this->roomWithManualNightlyRates($booking, 'G-366-EDIT');

        $payload = $this->bookingEditPayload($booking, $room, '2026-08-14');
        // Màn hình có thể gửi số đêm cũ; backend phải tự tính lại.
        $payload['num_of_days'] = 4;
        $this->putJson("/api/bookings/{$booking->id}", $payload)->assertSuccessful();

        $this->assertSame([
            '2026-08-08' => 600000.0, '2026-08-09' => 600000.0, '2026-08-10' => 600000.0,
            '2026-08-11' => 0.0, '2026-08-12' => 600000.0, '2026-08-13' => 600000.0,
        ], $this->nightlyRates($room, 'RM'));
        $this->assertSame([
            '2026-08-08' => 0.0, '2026-08-09' => 300000.0, '2026-08-10' => 300000.0,
            '2026-08-11' => 0.0, '2026-08-12' => 300000.0, '2026-08-13' => 300000.0,
        ], $this->nightlyRates($room, 'EB'));
        $this->assertSame(300000.0, (float) $room->fresh()->extra_bed_rate);
        $this->assertSame(6, (int) $booking->fresh()->num_of_days);
    }

    public function test_booking_edit_saves_room_dates_when_header_sync_is_disabled(): void
    {
        HotelConfig::updateOrCreate(['name' => 'SyncRoomDateByBookingDate'], ['value' => '0']);
        $booking = $this->createBooking(['arrival_date' => '2026-08-08', 'departure_date' => '2026-08-12', 'num_of_days' => 4]);
        $room = $this->roomWithManualNightlyRates($booking, 'G-366-NOSYNC');

        $this->putJson("/api/bookings/{$booking->id}", $this->bookingEditPayload($booking, $room, '2026-08-14'))
            ->assertSuccessful();
        $this->assertSame('2026-08-14', $room->fresh()->departure_date->toDateString());

        $this->putJson("/api/bookings/{$booking->id}", array_merge(
            $this->bookingEditPayload($booking, $room->fresh(), '2026-08-13'),
            ['departure_date' => '2026-08-14'],
        ))->assertSuccessful();
        $this->assertSame('2026-08-13', $room->fresh()->departure_date->toDateString());
        $this->assertSame('2026-08-14', $booking->fresh()->departure_date->toDateString());
    }

    public function test_extension_does_not_copy_zeroed_child_surcharge_to_new_nights(): void
    {
        $booking = $this->createBooking(['arrival_date' => '2026-08-08', 'departure_date' => '2026-08-14', 'num_of_days' => 6]);
        $room = BookingRoom::create([
            'id' => 'G-366-KID', 'booking_id' => $booking->id, 'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-08', 'departure_date' => '2026-08-11',
            'rate' => 600000, 'status' => BookingRoom::STATUS_BOOKED,
        ]);
        $child = BookingChild::create([
            'id' => 'BC-366-KID', 'booking_id' => $booking->id, 'booking_room_id' => $room->id,
            'full_name' => 'Kid 366', 'age_group' => 'child',
        ]);
        // Đêm đầu đã chỉnh tay phụ thu về 0đ, các đêm sau 90.000.
        foreach (['2026-08-08' => 0, '2026-08-09' => 90000, '2026-08-10' => 90000] as $date => $amount) {
            BookingChildBreakfastDetail::create([
                'booking_child_id' => $child->id, 'service_date' => $date,
                'breakfast' => true, 'is_free' => false, 'is_extra_charge' => true, 'is_room' => false,
                'amount' => $amount,
            ]);
        }

        $this->putJson("/api/bookings/{$booking->id}/rooms/{$room->id}", [
            'departure_date' => '2026-08-13',
        ])->assertSuccessful();

        $amounts = $child->fresh()->breakfastDetails()->orderBy('service_date')->get()
            ->mapWithKeys(fn ($detail) => [$detail->service_date->toDateString() => (float) $detail->amount])
            ->all();
        $this->assertSame([
            '2026-08-08' => 0.0, '2026-08-09' => 90000.0, '2026-08-10' => 90000.0,
            '2026-08-11' => 90000.0, '2026-08-12' => 90000.0,
        ], $amounts);
    }

    public function test_single_night_extra_bed_rate_edit_keeps_room_default_rate(): void
    {
        $booking = $this->createBooking(['arrival_date' => '2026-08-08', 'departure_date' => '2026-08-12', 'num_of_days' => 4]);
        $room = $this->roomWithManualNightlyRates($booking, 'G-366-EB');
        $department = \App\Models\Department::firstOrCreate(['code' => 'FO'], ['name' => 'Reception/ Lê Tân']);
        \App\Models\HotelService::create([
            'code' => 'EB', 'name' => 'Extra Bed', 'is_active' => true,
            'price' => 300000, 'department_id' => $department->id,
        ]);

        $this->postJson("/api/booking-rooms/{$room->id}/services", [
            'service_code' => 'EB',
            'service_name' => 'Extra Bed',
            'service_date' => '2026-08-10',
            'quantity' => 1,
            'rate' => 0,
            'is_room' => 1,
        ])->assertSuccessful();

        $this->assertSame(0.0, $this->nightlyRates($room, 'EB')['2026-08-10']);
        $this->assertSame(300000.0, (float) $room->fresh()->extra_bed_rate);
    }
}
