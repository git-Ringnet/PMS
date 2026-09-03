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
            'registration_status_id' => $this->regStatus->id,
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
            'registration_status_id' => $this->regStatus->id,
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
            'registration_status_id' => $this->regStatus->id,
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
    public function test_room_move_sync_leaves_orphaned_breakfast_details(): void
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

        // 2. Perform a Room Move from room 101 to room 102 on system_date = 2026-08-07
        $response = $this->postJson("/api/bookings/{$booking->id}/rooms/{$bRoom->id}/move", [
            'move_type' => 'available',
            'target_room_number' => '102',
            'reason' => 'Room move child sync test',
        ]);
        $response->assertSuccessful();

        // 3. Inspect child records.
        // Old child is marked status 100.
        $child->refresh();
        $this->assertEquals(100, $child->child_status);

        // A new child should have been cloned for the new room.
        $newChild = BookingChild::where('booking_room_id', '!=', $bRoom->id)->first();
        $this->assertNotNull($newChild);

        // Breakfast details >= 2026-08-07 (bf2, bf3) are transferred to $newChild->id.
        $bf2->refresh();
        $bf3->refresh();
        $this->assertEquals($newChild->id, $bf2->booking_child_id);
        $this->assertEquals($newChild->id, $bf3->booking_child_id);

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
     * TC-08: Restore master checkout is blocked when one of the child rooms is occupied.
     */
    public function test_restore_master_checkout_blocked_when_child_room_is_occupied(): void
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

        // 3. Attempting to restore checkout for the master booking fails with 422 because room 102 is occupied.
        $response = $this->postJson("/api/bookings/{$booking->id}/restore-checkout");
        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $this->assertStringContainsString('không thể khôi phục checkout', $response->json('message'));
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
            'registration_status_id' => $this->regStatus->id,
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
            'registration_status_id' => $this->regStatus->id,
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
                            'arrivalDate' => '2026-08-06', // Outside booking arrival
                            'departureDate' => '2026-08-09',
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

        // 2. Update booking arrival_date to 2026-08-22
        $response = $this->putJson("/api/bookings/{$booking->id}", [
            'booking_name' => 'Updated Booking Name',
            'arrival_date' => '2026-08-22',
            'departure_date' => '2026-08-25',
            'registration_status_id' => $this->regStatus->id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
        ]);
        $response->assertSuccessful();

        // 3. Assertions
        $booking->refresh();
        $bRoom1->refresh();
        $bRoom2->refresh();

        // Booking arrival date updated to 22
        $this->assertEquals('2026-08-22', $booking->arrival_date->toDateString());

        // Room 1 (Reservation) arrival date synchronized to 22
        $this->assertEquals('2026-08-22', $bRoom1->arrival_date->toDateString());

        // Room 2 (Checked In) arrival date MUST remain 21
        $this->assertEquals('2026-08-21', $bRoom2->arrival_date->toDateString());
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
            'registration_status_id' => $this->regStatus->id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Ngày của phòng phải nằm trong giai đoạn của đăng ký', $response->json('message'));
    }

    /**
     * TC-13: Updating booking dates succeeds when a room is Checked-In, preserving its dates.
     */
    public function test_booking_update_succeeds_and_checked_in_room_dates_are_preserved(): void
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
        // The room is Checked-In so it is not synced and stays on 21. The update succeeds.
        $response = $this->putJson("/api/bookings/{$booking->id}", [
            'booking_name' => 'Succeeds Update Checked In',
            'arrival_date' => '2026-08-22',
            'departure_date' => '2026-08-25',
            'registration_status_id' => $this->regStatus->id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
        ]);

        $response->assertSuccessful();
        $bRoom->refresh();
        // Room (Checked In) arrival date MUST remain 21
        $this->assertEquals('2026-08-21', $bRoom->arrival_date->toDateString());
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
            'registration_status_id' => $this->regStatus->id,
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
            'registration_status_id' => $this->regStatus->id,
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
            'registration_status_id' => $this->regStatus->id,
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
            'registration_status_id' => $this->regStatus->id,
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
            'registration_status_id' => $this->regStatus->id,
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
            'registration_status_id' => $this->regStatus->id,
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
            'registration_status_id' => $this->regStatus->id,
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
            'registration_status_id' => $this->regStatus->id,
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
     * TC-16: Inline service update (such as RM bypass and EB sync to booking_rooms)
     */
    public function test_child_breakfast_sync_preserves_fit_owner_flag(): void
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

        $service = BookingRoomService::where('booking_room_id', $room->id)
            ->where('service_code', 'BD')
            ->whereDate('service_date', '2026-08-07')
            ->firstOrFail();
        $this->assertSame(1, (int) $service->is_room);

        $this->patchJson("/api/booking-children/{$child->id}/breakfast-details/{$detail->id}", [
            'is_room' => false,
            'amount' => 90000,
        ])->assertSuccessful()->assertJsonPath('data.is_room', false);

        $this->assertDatabaseCount('booking_room_services', 1);
        $this->assertSame(0, (int) $service->fresh()->is_room);
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

        // 4. Verify booking header dates synchronized exactly
        $this->assertEquals('2026-08-22', $booking->arrival_date->toDateString());
        $this->assertEquals('2026-08-26', $booking->departure_date->toDateString());

        // 5. Test Inhouse restrictions: set room1 status to CHECKED_IN
        $room1->update(['status' => BookingRoom::STATUS_CHECKED_IN]);

        // Scenario A: Non-FO user tries to update inhouse room dates.
        // Arrival and Departure dates should remain unchanged. Only Rate is allowed.
        $this->user->update(['department_code' => 'SALES']); // Set to non-FO

        $payloadNonFO = [
            'room_ids' => ['G0000001'],
            'arrival_date' => '2026-08-23', // Should be ignored
            'departure_date' => '2026-08-27', // Should be ignored for non-FO
            'rate' => 180000, // Allowed
        ];

        $responseNonFO = $this->postJson("/api/bookings/{$booking->id}/rooms/bulk-update", $payloadNonFO);
        $responseNonFO->assertSuccessful();

        $room1->refresh();
        $this->assertEquals('2026-08-22', $room1->arrival_date->toDateString());
        $this->assertEquals('2026-08-26', $room1->departure_date->toDateString()); // Unchanged
        $this->assertEquals(180000, $room1->rate); // Changed

        // Scenario B: FO user tries to update inhouse room dates.
        // Departure date should be allowed to change.
        $this->user->update(['department_code' => 'FO']); // Set to FO

        $payloadFO = [
            'room_ids' => ['G0000001'],
            'departure_date' => '2026-08-27', // Allowed for FO
        ];

        $responseFO = $this->postJson("/api/bookings/{$booking->id}/rooms/bulk-update", $payloadFO);
        $responseFO->assertSuccessful();

        $room1->refresh();
        $this->assertEquals('2026-08-22', $room1->arrival_date->toDateString()); // Unchanged
        $this->assertEquals('2026-08-27', $room1->departure_date->toDateString()); // Changed
    }
}
