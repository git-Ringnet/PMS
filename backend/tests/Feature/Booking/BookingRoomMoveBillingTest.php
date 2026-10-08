<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingRoomGuest;
use App\Models\BookingRoomService;
use App\Models\Guest;
use App\Models\HotelConfig;
use App\Models\RoomNightBill;
use App\Models\RoomDoNotMoveLock;
use App\Models\RoomClass;
use App\Models\Room;
use App\Models\RoomForm;
use App\Models\ServiceBill;
use App\Models\SystemDateRoll;
use App\Models\User;
use App\Services\BookingRoomLifecycleService;
use App\Services\BookingRoomMoveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingRoomMoveBillingTest extends TestCase
{
    use RefreshDatabase;

    private Booking $booking;

    private RoomClass $roomClass;

    private RoomForm $roomForm;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create(['username' => 'move_test']);
        $this->actingAs($user);
        DB::table('booking_statuses')->insertOrIgnore([
            ['id' => Booking::STATUS_RESERVATION, 'name' => 'Reservation'],
            ['id' => Booking::STATUS_CHECKIN, 'name' => 'Checked In'],
            ['id' => Booking::STATUS_CHECKOUT, 'name' => 'Checked Out'],
            ['id' => Booking::STATUS_DELETED, 'name' => 'Deleted'],
            ['id' => Booking::STATUS_NO_SHOW, 'name' => 'No Show'],
            ['id' => Booking::STATUS_TRANSFER, 'name' => 'Moved'],
        ]);

        SystemDateRoll::create([
            'system_date' => '2026-08-16 00:00:00',
            'actual_date' => '2026-08-16 00:00:00',
            'shift' => '1',
            'username' => 'move_test',
        ]);

        $this->booking = Booking::create([
            'booking_name' => 'Move billing test',
            'booking_date' => '2026-08-11',
            'arrival_date' => '2026-08-11',
            'departure_date' => '2026-08-18',
            'created_by' => 'move_test',
            'updated_by' => 'move_test',
            'status' => Booking::STATUS_CHECKIN,
        ]);
        $this->roomClass = RoomClass::create([
            'code' => 'MOVE',
            'name' => 'Move Test',
            'is_active' => true,
        ]);
        $this->roomForm = RoomForm::create(['name' => 'Move Test Form']);
        foreach (['410', '907', '902'] as $roomNumber) {
            Room::create([
                'room_number' => $roomNumber,
                'room_class_id' => $this->roomClass->id,
                'room_form_id' => $this->roomForm->id,
                'floor' => 4,
                'status' => 'available',
            ]);
        }
    }

    public function test_partial_move_endpoint_copies_only_selected_guest_setup_without_stealing_source_rows(): void
    {
        $user = User::where('username', 'move_test')->firstOrFail();
        $this->grantMovePermission($user);
        $source = $this->makeRoom('907', '2026-08-11', '2026-08-19', ['adults' => 2]);
        $movedGuest = Guest::create(['full_name' => 'Guest who moves']);
        $stayingGuest = Guest::create(['full_name' => 'Guest who stays']);
        BookingRoomGuest::create([
            'booking_room_id' => $source->id, 'guest_id' => $movedGuest->id,
            'is_primary' => true, 'status' => BookingRoomGuest::STATUS_CHECKED_IN,
        ]);
        BookingRoomGuest::create([
            'booking_room_id' => $source->id, 'guest_id' => $stayingGuest->id,
            'is_primary' => false, 'status' => BookingRoomGuest::STATUS_CHECKED_IN,
        ]);
        $this->makeUnpostedService($source, 'EB', '2026-08-16', 300000, $movedGuest->id);
        $this->makeUnpostedService($source, 'AUTO1', '2026-08-16', 50000, $stayingGuest->id);
        $this->makeUnpostedService($source, 'AUTO2', '2026-08-16', 70000);

        $this->actingAs($user)
            ->postJson("/api/bookings/{$this->booking->id}/rooms/{$source->id}/move", [
                'move_type' => 'available',
                'target_room_number' => '410',
                'reason' => 'Guest requested separate room',
                'selected_guest_ids' => [$movedGuest->id],
                'is_change_rate' => false,
            ])
            ->assertSuccessful()
            ->assertJsonPath('success', true);

        $target = BookingRoom::where('booking_id', $this->booking->id)
            ->where('room_number', '410')->firstOrFail();
        $this->assertSame(BookingRoom::STATUS_CHECKED_IN, (int) $source->fresh()->status);
        $this->assertSame(BookingRoom::STATUS_CHECKED_IN, (int) $target->status);
        $this->assertSame(1, (int) $source->fresh()->adults);
        $this->assertSame(1, (int) $target->adults);
        $this->assertDatabaseHas('booking_room_guests', [
            'booking_room_id' => $source->id, 'guest_id' => $stayingGuest->id,
            'status' => BookingRoomGuest::STATUS_CHECKED_IN,
        ]);
        $this->assertDatabaseHas('booking_room_guests', [
            'booking_room_id' => $target->id, 'guest_id' => $movedGuest->id,
            'status' => BookingRoomGuest::STATUS_CHECKED_IN,
        ]);
        $this->assertSame(1, BookingRoomService::where('booking_room_id', $source->id)
            ->where('service_code', 'EB')->where('guest_id', $movedGuest->id)->whereDate('service_date', '2026-08-16')->count());
        $this->assertSame(1, BookingRoomService::where('booking_room_id', $target->id)
            ->where('service_code', 'EB')->where('guest_id', $movedGuest->id)->whereDate('service_date', '2026-08-16')->count());
        $this->assertSame(1, BookingRoomService::where('booking_room_id', $source->id)
            ->where('service_code', 'AUTO1')->where('guest_id', $stayingGuest->id)->count());
        $this->assertDatabaseMissing('booking_room_services', [
            'booking_room_id' => $target->id, 'guest_id' => $stayingGuest->id,
        ]);
        $this->assertDatabaseHas('booking_room_services', [
            'booking_room_id' => $target->id, 'service_code' => 'AUTO2', 'guest_id' => null,
        ]);
    }

    public function test_second_full_move_traverses_chain_and_dedupes_master_room_bill(): void
    {
        $user = User::where('username', 'move_test')->firstOrFail();
        $this->grantMovePermission($user, 'fo.service.add');
        $source = $this->makeRoom('907', '2026-08-11', '2026-08-19');
        $bill = $this->makeBill($source, 'RM', '2026-08-16', 1000000, true);
        $firstTarget = $source->moveToRoom('902', '2026-08-16', 'move_test');
        $secondTarget = $firstTarget->moveToRoom('410', '2026-08-16', 'move_test');

        $moves = app(BookingRoomMoveService::class);
        $this->assertSame([$secondTarget->id, $firstTarget->id, $source->id], $moves->previousRoomIds($secondTarget));
        $this->assertTrue($moves->hasPostedRoomNight($secondTarget, '2026-08-16', true));
        $this->assertSame([(string) $bill->Ma], array_map(
            fn (array $historyBill): string => (string) $historyBill['bill_id'],
            $moves->postedHistoryBills($secondTarget),
        ));

        $this->actingAs($user)
            ->postJson('/api/booking-room-services/post-room-charge', [
                'booking_id' => $this->booking->id,
                'date_from' => '2026-08-16', 'date_to' => '2026-08-16',
                'mode' => 'auto', 'folio' => 1, 'currency' => 'VND',
            ])
            ->assertSuccessful();

        $this->assertSame(1, ServiceBill::where('RegisterId1', $this->booking->id)
            ->where('ServiceId', 'RM')->whereDate('Date', '2026-08-16')
            ->where('Status', 1)->where('Edit', 0)->count());
        $this->assertSame($source->id, (string) $bill->fresh()->RentalRoomId1);
        $this->assertNull($bill->fresh()->RentalRoomId2);
    }

    public function test_paid_master_room_and_eb_bills_remain_history_and_are_not_reposted_after_move(): void
    {
        $user = User::where('username', 'move_test')->firstOrFail();
        $this->grantMovePermission($user, 'fo.service.add');
        $source = $this->makeRoom('907', '2026-08-11', '2026-08-19');
        $roomBill = $this->makeBill($source, 'RM', '2026-08-16', 1000000, true);
        $ebBill = $this->makeBill($source, 'EB', '2026-08-16', 300000, true);
        $roomBill->update(['Status' => 2, 'PaymentId' => 51]);
        $ebBill->update(['Status' => 2, 'PaymentId' => 51]);
        $this->makePostedService($source, 'RM', '2026-08-16', 1000000, $roomBill);
        $this->makePostedService($source, 'EB', '2026-08-16', 300000, $ebBill);

        $target = $source->moveToRoom('902', '2026-08-16', 'move_test');
        $moves = app(BookingRoomMoveService::class);

        $this->assertTrue($moves->hasPostedRoomNight($target, '2026-08-16', true));
        $this->assertTrue($moves->hasPostedService($target, '2026-08-16', ['EB']));
        $this->assertEqualsCanonicalizing(
            [(string) $roomBill->Ma, (string) $ebBill->Ma],
            array_map(fn (array $bill): string => (string) $bill['bill_id'], $moves->postedHistoryBills($target)),
        );

        $this->actingAs($user)
            ->postJson('/api/booking-room-services/post-room-charge', [
                'booking_id' => $this->booking->id,
                'date_from' => '2026-08-16', 'date_to' => '2026-08-16',
                'mode' => 'auto', 'folio' => 1, 'currency' => 'VND',
            ])
            ->assertSuccessful();

        $this->assertSame(1, ServiceBill::where('RegisterId1', $this->booking->id)
            ->where('ServiceId', 'RM')->whereDate('Date', '2026-08-16')
            ->whereIn('Status', [1, 2])->where('Edit', 0)->count());
        $this->assertSame(1, ServiceBill::where('RegisterId1', $this->booking->id)
            ->where('ServiceId', 'EB')->whereDate('Date', '2026-08-16')
            ->whereIn('Status', [1, 2])->where('Edit', 0)->count());
        $this->assertSame($source->id, (string) $roomBill->fresh()->RentalRoomId1);
        $this->assertNull($roomBill->fresh()->RentalRoomId2);
        $this->assertSame(2, (int) $roomBill->fresh()->Status);
        $this->assertSame(2, (int) $ebBill->fresh()->Status);
    }
    public function test_reversed_room_night_does_not_block_reposting_after_full_move(): void
    {
        $user = User::where('username', 'move_test')->firstOrFail();
        $this->grantMovePermission($user, 'fo.service.add');
        $source = $this->makeRoom('907', '2026-08-11', '2026-08-19');
        $reversedBill = $this->makeBill($source, 'RM', '2026-08-16', 1000000, true);
        // Legacy reversal data may retain Edit=0; Status is the decisive active-bill flag.
        $reversedBill->update(['Status' => 3, 'Edit' => 0]);
        $target = $source->moveToRoom('902', '2026-08-16', 'move_test');

        $moves = app(BookingRoomMoveService::class);
        $this->assertFalse($moves->hasPostedRoomNight($target, '2026-08-16', true));
        $this->assertSame([], $moves->postedHistoryBills($target));

        $this->actingAs($user)
            ->postJson('/api/booking-room-services/post-room-charge', [
                'booking_id' => $this->booking->id,
                'date_from' => '2026-08-16', 'date_to' => '2026-08-16',
                'mode' => 'auto', 'folio' => 1, 'currency' => 'VND',
            ])
            ->assertSuccessful();

        $this->assertDatabaseHas('service_bills', [
            'RegisterId1' => $this->booking->id,
            'RentalRoomId1' => $target->id,
            'ServiceId' => 'RM', 'Date' => '2026-08-16 00:00:00',
            'Amount' => 1000000, 'Status' => 1, 'Edit' => 0,
        ]);
        $this->assertSame(1, ServiceBill::where('RegisterId1', $this->booking->id)
            ->where('RentalRoomId1', $target->id)->where('ServiceId', 'RM')
            ->where('Status', 1)->where('Edit', 0)->count());
    }
    public function test_sale_user_cannot_spoof_frontdesk_context_for_inhouse_edits(): void
    {
        $role = \App\Models\Role::create([
            'code' => 'move_test_booking_edit_only',
            'name' => 'Move test booking edit only',
            'level' => 3,
            'department_scope' => 'FO',
            'is_active' => true,
        ]);
        $permission = \App\Models\Permission::firstOrCreate(
            ['code' => 'fo.booking.edit'],
            ['name' => 'Edit booking', 'module' => 'FO'],
        );
        $role->permissions()->sync([$permission->id]);
        $user = User::where('username', 'move_test')->firstOrFail();
        $user->roles()->syncWithoutDetaching([$role->id]);

        $room = $this->makeRoom('410', '2026-08-11', '2026-08-18', ['rate' => 1000000]);
        $this->actingAs($user)
            ->postJson("/api/bookings/{$this->booking->id}/rooms/bulk-update", [
                'room_ids' => [(string) $room->id],
                'current_module' => 'frontdesk',
                'rate' => 2500000,
                'departure_date' => '2026-08-20',
            ])
            ->assertForbidden();

        $this->assertSame(1000000.0, (float) $room->fresh()->rate);
        $this->assertSame('2026-08-18', $room->fresh()->departure_date->toDateString());
    }
    public function test_paid_room_charge_cannot_be_recreated_or_changed_by_service_endpoint(): void
    {
        $this->grantMovePermission(User::where('username', 'move_test')->firstOrFail(), 'fo.service.add');
        $source = $this->makeRoom('907', '2026-08-11', '2026-08-19');
        $bill = $this->makeBill($source, 'RM', '2026-08-16', 1000000, false);
        $bill->update(['Status' => 2, 'PaymentId' => 51]);

        $this->actingAs(User::where('username', 'move_test')->firstOrFail())
            ->postJson("/api/booking-rooms/{$source->id}/services", [
                'service_code' => 'RM', 'service_name' => 'Room charge',
                'service_date' => '2026-08-16', 'quantity' => 1, 'rate' => 2500000, 'is_room' => 1,
            ])
            ->assertStatus(422);

        $this->assertSame(1, ServiceBill::where('RegisterId1', $this->booking->id)
            ->where('ServiceId', 'RM')->whereDate('Date', '2026-08-16')
            ->whereIn('Status', [1, 2])->where('Edit', 0)->count());
        $this->assertSame(1000000.0, (float) $bill->fresh()->Amount);
        $this->assertSame(2, (int) $bill->fresh()->Status);
    }

    public function test_do_not_move_lock_entry_is_retry_safe_and_creates_one_active_audit(): void
    {
        $user = User::where('username', 'move_test')->firstOrFail();
        $this->grantMovePermission($user, 'fo.booking.edit');
        $room = $this->makeRoom('410', '2026-08-11', '2026-08-18');
        $url = "/api/bookings/{$this->booking->id}/rooms/{$room->id}/lock-move";

        $this->actingAs($user)->postJson($url, ['note' => 'guest request'])->assertSuccessful();
        $activeLock = RoomDoNotMoveLock::where('booking_room_id', $room->id)->whereNull('unlocked_at')->firstOrFail();
        $this->assertSame((int) $user->getKey(), (int) $activeLock->locked_by_user_id);
        $this->assertSame(1, (int) $room->fresh()->is_do_not_move);

        $this->actingAs($user)->postJson($url, ['note' => 'retry'])->assertStatus(422);
        $this->assertSame(1, RoomDoNotMoveLock::where('booking_room_id', $room->id)->whereNull('unlocked_at')->count());
        $this->assertSame('guest request', $activeLock->fresh()->note);
    }

    public function test_do_not_move_unlock_allows_an_exact_configured_role_over_http(): void
    {
        $owner = User::factory()->create(['username' => 'move_role_lock_owner']);
        $allowedUser = User::where('username', 'move_test')->firstOrFail();
        $this->grantMovePermission($owner, 'fo.booking.edit');
        $this->grantMovePermission($allowedUser, 'fo.booking.edit');
        HotelConfig::updateOrCreate(['name' => 'RoleUserOpenDoNotMove'], ['value' => 'move_billing_feature_role']);
        $room = $this->makeRoom('410', '2026-08-11', '2026-08-18', ['is_do_not_move' => true]);
        $lock = RoomDoNotMoveLock::create([
            'booking_room_id' => $room->id,
            'locked_by_user_id' => $owner->getKey(),
            'locked_by_username' => $owner->username,
            'locked_at' => now()->subMinute(),
        ]);

        $this->actingAs($allowedUser)
            ->deleteJson("/api/bookings/{$this->booking->id}/rooms/{$room->id}/lock-move")
            ->assertSuccessful();

        $this->assertSame((int) $allowedUser->getKey(), (int) $lock->fresh()->unlocked_by_user_id);
        $this->assertNotNull($lock->fresh()->unlocked_at);
        $this->assertSame(0, (int) $room->fresh()->is_do_not_move);
    }
    public function test_do_not_move_unlock_denies_other_user_and_repeated_unlock_preserves_audit(): void
    {
        $actor = User::where('username', 'move_test')->firstOrFail();
        $this->grantMovePermission($actor, 'fo.booking.edit');
        $owner = User::factory()->create(['username' => 'move_lock_owner']);
        $this->grantMovePermission($owner, 'fo.booking.edit');
        $room = $this->makeRoom('410', '2026-08-11', '2026-08-18', ['is_do_not_move' => true]);
        $lock = RoomDoNotMoveLock::create([
            'booking_room_id' => $room->id,
            'locked_by_user_id' => $owner->getKey(),
            'locked_by_username' => $owner->username,
            'locked_at' => now()->subMinute(),
            'note' => 'audit test',
        ]);

        $url = "/api/bookings/{$this->booking->id}/rooms/{$room->id}/lock-move";
        $this->actingAs($actor)->deleteJson($url)->assertForbidden();
        $this->assertSame(1, (int) $room->fresh()->is_do_not_move);
        $this->assertNull($lock->fresh()->unlocked_at);
        $this->assertNull($lock->fresh()->unlocked_by_user_id);

        $this->actingAs($owner)->deleteJson($url)->assertSuccessful();
        $unlocked = $lock->fresh();
        $this->assertSame(0, (int) $room->fresh()->is_do_not_move);
        $this->assertSame((int) $owner->getKey(), (int) $unlocked->unlocked_by_user_id);
        $this->assertNotNull($unlocked->unlocked_at);

        $this->actingAs($owner)->deleteJson($url)->assertStatus(409);
        $this->assertSame((int) $owner->getKey(), (int) $lock->fresh()->unlocked_by_user_id);
        $this->assertSame($unlocked->unlocked_at->toDateTimeString(), $lock->fresh()->unlocked_at->toDateTimeString());
        $this->assertSame(0, (int) $room->fresh()->is_do_not_move);
    }
    public function test_housekeeping_module_cannot_spoof_inhouse_rate_departure_edits(): void
    {
        $user = User::where('username', 'move_test')->firstOrFail();
        $this->grantMovePermission($user, 'fo.booking.edit');
        $room = $this->makeRoom('410', '2026-08-11', '2026-08-18', ['rate' => 1000000]);

        $this->actingAs($user)
            ->postJson("/api/bookings/{$this->booking->id}/rooms/bulk-update", [
                'room_ids' => [(string) $room->id],
                'current_module' => 'HK',
                'rate' => 2500000,
                'departure_date' => '2026-08-20',
            ])
            ->assertForbidden();

        $this->assertSame(1000000.0, (float) $room->fresh()->rate);
        $this->assertSame('2026-08-18', $room->fresh()->departure_date->toDateString());
    }
    public function test_full_move_keeps_posted_room_and_eb_bills_at_source_and_does_not_recreate_them(): void
    {
        $source = $this->makeRoom('410', '2026-08-11', '2026-08-18', [
            'rate' => 1000000,
            'extra_bed_qty' => 1,
            'extra_bed_rate' => 300000,
        ]);
        $roomBill = $this->makeBill($source, 'RM', '2026-08-16', 1000000, true);
        $ebBill = $this->makeBill($source, 'EB', '2026-08-16', 300000, true);
        $this->makePostedService($source, 'RM', '2026-08-16', 1000000, $roomBill);
        $this->makePostedService($source, 'EB', '2026-08-16', 300000, $ebBill);
        $this->makePostedService($source, 'RM', '2026-08-17', 1000000);
        $this->makePostedService($source, 'EB', '2026-08-17', 300000);

        $target = $source->moveToRoom('902', '2026-08-16', 'move_test');

        $this->assertSame(BookingRoom::STATUS_MOVED, (int) $source->fresh()->status);
        $this->assertSame('2026-08-16', $source->fresh()->departure_date->toDateString());
        $this->assertSame($source->id, (string) $roomBill->fresh()->RentalRoomId1);
        $this->assertNull($roomBill->fresh()->RentalRoomId2);
        $this->assertSame($source->id, (string) $ebBill->fresh()->RentalRoomId1);
        $this->assertSame([$target->id, $source->id], app(BookingRoomMoveService::class)->previousRoomIds($target));
        $this->assertTrue(app(BookingRoomMoveService::class)->hasPostedRoomNight($target, '2026-08-16', true));
        $this->assertCount(2, app(BookingRoomMoveService::class)->postedHistoryBills($target));

        $this->assertDatabaseHas('booking_room_services', [
            'booking_room_id' => $source->id,
            'service_code' => 'RM',
            'is_posted' => 1,
        ]);
        $this->assertSame(1, BookingRoomService::where('booking_room_id', $source->id)
            ->where('service_code', 'RM')->whereDate('service_date', '2026-08-16')->where('is_posted', 1)->count());
        $this->assertDatabaseHas('booking_room_services', [
            'booking_room_id' => $source->id,
            'service_code' => 'EB',
            'is_posted' => 1,
        ]);
        $this->assertSame(1, BookingRoomService::where('booking_room_id', $source->id)
            ->where('service_code', 'EB')->whereDate('service_date', '2026-08-16')->where('is_posted', 1)->count());
        $this->assertDatabaseHas('booking_room_services', [
            'booking_room_id' => $target->id,
            'service_code' => 'RM',
            'is_posted' => 0,
        ]);
        $this->assertSame(1, BookingRoomService::where('booking_room_id', $target->id)
            ->where('service_code', 'RM')->whereDate('service_date', '2026-08-17')->where('is_posted', 0)->count());
        $this->assertDatabaseHas('booking_room_services', [
            'booking_room_id' => $target->id,
            'service_code' => 'EB',
            'is_posted' => 0,
        ]);
        $this->assertSame(1, BookingRoomService::where('booking_room_id', $target->id)
            ->where('service_code', 'EB')->whereDate('service_date', '2026-08-17')->where('is_posted', 0)->count());

        app(BookingRoomLifecycleService::class)->synchronize($target->fresh(), false, false, true);

        $this->assertDatabaseMissing('booking_room_services', [
            'booking_room_id' => $target->id,
            'service_code' => 'RM',
            'service_date' => '2026-08-16',
        ]);
        $this->assertDatabaseMissing('booking_room_services', [
            'booking_room_id' => $target->id,
            'service_code' => 'EB',
            'service_date' => '2026-08-16',
        ]);
        $this->assertSame(2, ServiceBill::where('RegisterId1', $this->booking->id)->whereDate('Date', '2026-08-16')->count());
    }

    public function test_partial_move_copies_only_selected_guest_setup_and_keeps_a_separate_room_night(): void
    {
        $source = $this->makeRoom('907', '2026-08-11', '2026-08-19');
        $target = $this->makeRoom('410', '2026-08-16', '2026-08-19', [
            'adults' => 1,
        ]);
        $movedGuest = Guest::create(['full_name' => 'Moved guest']);
        $stayingGuest = Guest::create(['full_name' => 'Staying guest']);
        $this->makeUnpostedService($source, 'EB', '2026-08-16', 300000, $movedGuest->id);
        $this->makeUnpostedService($source, 'AUTO1', '2026-08-16', 50000, $stayingGuest->id);
        $this->makeUnpostedService($source, 'AUTO2', '2026-08-16', 70000);
        $this->makeUnpostedService($source, 'RM', '2026-08-16', 1000000);

        app(BookingRoomMoveService::class)->copyUnpostedServices(
            $source,
            $target,
            '2026-08-16',
            '2026-08-19',
            [$movedGuest->id],
        );

        $this->assertDatabaseCount('booking_room_services', 7);
        $this->assertDatabaseHas('booking_room_services', [
            'booking_room_id' => $target->id,
            'service_code' => 'EB',
            'guest_id' => $movedGuest->id,
            'is_posted' => 0,
        ]);
        $this->assertDatabaseHas('booking_room_services', [
            'booking_room_id' => $target->id,
            'service_code' => 'AUTO2',
            'guest_id' => null,
            'is_posted' => 0,
        ]);
        $this->assertDatabaseMissing('booking_room_services', [
            'booking_room_id' => $target->id,
            'guest_id' => $stayingGuest->id,
        ]);
        $this->assertSame(1, BookingRoomService::where('booking_room_id', $target->id)
            ->where('service_code', 'RM')->whereDate('service_date', '2026-08-16')->count());
        $this->assertSame([$target->id], app(BookingRoomMoveService::class)->previousRoomIds($target));

        app(BookingRoomLifecycleService::class)->synchronize($target->fresh(), false, false, true);

        $this->assertSame(1, BookingRoomService::where('booking_room_id', $target->id)
            ->where('service_code', 'RM')->whereDate('service_date', '2026-08-16')->count());
        $this->assertSame('2026-08-16', app(\App\Services\RoomAvailabilityService::class)->getSystemDate()->toDateString());
        $this->assertSame(BookingRoom::STATUS_CHECKED_IN, (int) $target->fresh()->status);
        $this->assertSame(1, BookingRoomService::where('booking_room_id', $target->id)
            ->where('service_code', 'RM')
            ->whereDate('service_date', '2026-08-16')
            ->count());
        $this->assertSame(1, BookingRoomService::where('booking_room_id', $source->id)
            ->where('service_code', 'RM')
            ->whereDate('service_date', '2026-08-16')
            ->count());
    }

    public function test_full_move_on_checkout_date_creates_zero_night_target_segment(): void
    {
        $source = $this->makeRoom('907', '2026-08-11', '2026-08-16');

        $target = $source->moveToRoom('410', '2026-08-16', 'move_test');

        $this->assertSame(BookingRoom::STATUS_MOVED, (int) $source->fresh()->status);
        $this->assertSame('2026-08-11', $source->fresh()->arrival_date->toDateString());
        $this->assertSame('2026-08-16', $source->fresh()->departure_date->toDateString());
        $this->assertSame(5, (int) $source->fresh()->ActutalNumOfDays);
        $this->assertSame('2026-08-16', $target->fresh()->arrival_date->toDateString());
        $this->assertSame('2026-08-16', $target->fresh()->departure_date->toDateString());
        $this->assertSame(0, (int) $target->fresh()->ActutalNumOfDays);
        $this->assertSame(0, (int) $target->fresh()->NumOfDays);
    }

    private function grantMovePermission(User $user, string $code = 'fo.room.move'): void
    {
        $role = \App\Models\Role::firstOrCreate(
            ['code' => 'move_billing_feature_role'],
            ['name' => 'Move billing feature role', 'level' => 3, 'department_scope' => 'FO', 'is_active' => true],
        );
        $permission = \App\Models\Permission::firstOrCreate(
            ['code' => $code], ['name' => $code, 'module' => 'FO'],
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    }
    private function makeRoom(string $roomNumber, string $arrival, string $departure, array $overrides = []): BookingRoom
    {
        return BookingRoom::create([
            'booking_id' => $this->booking->id,
            'room_class_id' => $this->roomClass->id,
            'RoomKind' => $this->roomForm->id,
            'room_number' => $roomNumber,
            'arrival_date' => $arrival,
            'departure_date' => $departure,
            'rate' => 1000000,
            'adults' => 1,
            'status' => BookingRoom::STATUS_CHECKED_IN,
            ...$overrides,
        ]);
    }

    private function makeBill(BookingRoom $room, string $code, string $date, float $amount, bool $atMaster): ServiceBill
    {
        $bill = ServiceBill::create([
            'Date' => $date . ' 00:00:00',
            'OpenTime' => '00:00',
            'Guest' => 'Move guest',
            'DepartmentId' => 'FO',
            'ServiceId' => $code,
            'DescriptionServive' => $code . ' posted before move',
            'Quantity' => 1,
            'Amount' => $amount,
            'RegisterId1' => $this->booking->id,
            'RentalRoomId1' => $room->id,
            'RegisterID2' => $this->booking->id,
            'RentalRoomId2' => $atMaster ? null : $room->id,
            'Username' => 'move_test',
            'Status' => 1,
        ]);

        if ($code === 'RM') {
            RoomNightBill::create([
                'bill_id' => $bill->Ma,
                'adult' => 1,
                'is_room_night' => 1,
                'date' => $date,
                'room' => $room->room_number,
                'rate' => $amount,
            ]);
        }

        return $bill;
    }

    private function makePostedService(BookingRoom $room, string $code, string $date, float $rate, ?ServiceBill $bill = null): void
    {
        BookingRoomService::create([
            'booking_room_id' => $room->id,
            'service_code' => $code,
            'service_name' => $code,
            'service_date' => $date,
            'quantity' => 1,
            'rate' => $rate,
            'service_bill_id' => $bill?->Ma,
            'is_posted' => $bill ? 1 : 0,
        ]);
    }

    private function makeUnpostedService(BookingRoom $room, string $code, string $date, float $rate, ?string $guestId = null): void
    {
        BookingRoomService::create([
            'booking_room_id' => $room->id,
            'guest_id' => $guestId,
            'service_code' => $code,
            'service_name' => $code,
            'service_date' => $date,
            'quantity' => 1,
            'rate' => $rate,
            'is_posted' => 0,
        ]);
    }
}
