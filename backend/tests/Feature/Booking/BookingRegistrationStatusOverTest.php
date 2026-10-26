<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\HotelConfig;
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

/**
 * Dòng 446 (và 549): đổi tình trạng đăng ký giữa nhóm trừ phòng trống
 * (is_availability = 1) và nhóm không trừ phòng trống (is_availability = 0).
 * Loại STD có 2 phòng, ngày hệ thống 07/08/2026.
 */
class BookingRegistrationStatusOverTest extends TestCase
{
    use RefreshDatabase;

    private RoomClass $roomClass;
    private RegistrationStatus $guaranteed;
    private RegistrationStatus $waiting;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create(['username' => 'test_user']);
        $role = Role::create(['code' => 'row446_test', 'name' => 'Row 446 test', 'level' => 3, 'department_scope' => 'FO', 'is_active' => true]);
        foreach (['fo.booking.create', 'fo.booking.edit', 'fo.frontdesk.view'] as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'FO']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $user->roles()->attach($role->id);
        $this->actingAs($user);

        DB::table('booking_statuses')->insertOrIgnore([
            ['id' => 0, 'name' => 'Reservation'], ['id' => 1, 'name' => 'Checked In'], ['id' => 2, 'name' => 'Checked Out'],
            ['id' => 3, 'name' => 'Cancelled'], ['id' => 4, 'name' => 'No Show'], ['id' => 100, 'name' => 'Chuyển phòng'],
        ]);
        SystemDateRoll::create(['system_date' => '2026-08-07 00:00:00', 'actual_date' => '2026-08-07 00:00:00', 'shift' => '1', 'username' => 'test_user']);

        $form = RoomForm::create(['name' => 'Standard Form']);
        $this->roomClass = RoomClass::create(['code' => 'STD', 'name' => 'Standard Class', 'is_active' => true]);
        foreach (['101', '102'] as $number) {
            Room::create(['room_number' => $number, 'room_class_id' => $this->roomClass->id, 'room_form_id' => $form->id, 'floor' => 1, 'status' => 'available']);
        }

        $this->guaranteed = RegistrationStatus::create(['name' => 'Guaranteed', 'booking_status_id' => 1, 'is_availability' => true]);
        $this->waiting = RegistrationStatus::create(['name' => 'Waiting', 'booking_status_id' => 99, 'is_availability' => false]);

        DB::table('markets')->insertOrIgnore([['id' => 1, 'name' => 'FIT', 'code' => 'FIT', 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('customer_sources')->insertOrIgnore([['id' => 1, 'name' => 'WalkIn', 'code' => 'WI', 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('companies')->insertOrIgnore([['id' => 1, 'name' => 'WalkIn Company', 'code' => 'WIC', 'created_at' => now(), 'updated_at' => now()]]);
        HotelConfig::create(['name' => 'AllowOverRoomTypeRoomKind', 'value' => '0']);
    }

    private function allowOver(bool $allow): void
    {
        HotelConfig::where('name', 'AllowOverRoomTypeRoomKind')->update(['value' => $allow ? '1' : '0']);
    }

    private function createBooking(string $name, RegistrationStatus $status, int $rooms): Booking
    {
        $this->postJson('/api/bookings', [
            'booking_name' => $name, 'arrival_date' => '2026-08-07', 'departure_date' => '2026-08-09', 'num_of_days' => 2,
            'registration_status_id' => $status->booking_status_id, 'company_id' => 1, 'market_id' => 1, 'customer_source_id' => 1,
            'room_allocations' => [[
                'roomClassId' => $this->roomClass->id, 'quantity' => $rooms, 'price' => 1000000,
                'rooms' => array_fill(0, $rooms, ['roomNumber' => null, 'guestName' => 'Guest']),
            ]],
        ])->assertSuccessful();

        return Booking::where('booking_name', $name)->firstOrFail();
    }

    private function changeStatus(Booking $booking, RegistrationStatus $status, array $extra = [])
    {
        return $this->putJson("/api/bookings/{$booking->id}", array_merge([
            'registration_status_id' => $status->booking_status_id,
            'company_id' => 1, 'market_id' => 1, 'customer_source_id' => 1,
        ], $extra));
    }

    // ---------- Phần 1: Waiting (0) -> Guaranteed (1) ----------

    public function test_waiting_to_guaranteed_is_blocked_when_it_overbooks_and_over_is_not_allowed(): void
    {
        $this->createBooking('Full house', $this->guaranteed, 2);
        $waiting = $this->createBooking('Waiting group', $this->waiting, 1);

        $this->changeStatus($waiting, $this->guaranteed)
            ->assertStatus(422)
            ->assertJsonPath('blocked_by_over', true)
            ->assertJsonPath('message', 'Loại phòng đang bị over không thể cập nhật thông tin');

        $this->assertSame($this->waiting->booking_status_id, (int) $waiting->fresh()->registration_status_id);
    }

    public function test_waiting_to_guaranteed_asks_for_confirmation_when_over_is_allowed(): void
    {
        $this->allowOver(true);
        $this->createBooking('Full house', $this->guaranteed, 2);
        $waiting = $this->createBooking('Waiting group', $this->waiting, 1);

        $this->changeStatus($waiting, $this->guaranteed)
            ->assertStatus(409)
            ->assertJsonPath('needs_over_confirm', true)
            ->assertJsonPath('message', 'Loại phòng đang bị over bạn có muốn tiếp tục')
            ->assertJsonPath('over_rooms.0.date', '2026-08-07');

        // Người dùng chọn "Không": không lưu gì.
        $this->assertSame($this->waiting->booking_status_id, (int) $waiting->fresh()->registration_status_id);
    }

    public function test_waiting_to_guaranteed_is_saved_after_the_user_confirms(): void
    {
        $this->allowOver(true);
        $this->createBooking('Full house', $this->guaranteed, 2);
        $waiting = $this->createBooking('Waiting group', $this->waiting, 1);

        $this->changeStatus($waiting, $this->guaranteed, ['force_over' => true])->assertOk();

        $this->assertSame($this->guaranteed->booking_status_id, (int) $waiting->fresh()->registration_status_id);
    }

    public function test_waiting_to_guaranteed_without_over_saves_directly(): void
    {
        $this->createBooking('Half house', $this->guaranteed, 1);
        $waiting = $this->createBooking('Waiting group', $this->waiting, 1);

        $this->changeStatus($waiting, $this->guaranteed)->assertOk();

        $this->assertSame($this->guaranteed->booking_status_id, (int) $waiting->fresh()->registration_status_id);
    }

    public function test_other_edits_of_a_waiting_booking_are_not_checked_for_over(): void
    {
        $this->createBooking('Full house', $this->guaranteed, 2);
        $waiting = $this->createBooking('Waiting group', $this->waiting, 1);

        $this->changeStatus($waiting, $this->waiting, ['note' => 'Khách chờ xác nhận'])->assertOk();

        $this->assertSame('Khách chờ xác nhận', $waiting->fresh()->note);
    }

    // ---------- Phần 2: Guaranteed (1) -> Waiting (0) khi đang ở ----------

    public function test_in_house_booking_cannot_switch_to_a_status_without_availability(): void
    {
        $booking = $this->createBooking('In house', $this->guaranteed, 1);
        $booking->update(['status' => Booking::STATUS_CHECKIN]);
        $booking->bookingRooms()->update(['status' => BookingRoom::STATUS_CHECKED_IN, 'room_number' => '101']);

        $this->changeStatus($booking, $this->waiting)
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame($this->guaranteed->booking_status_id, (int) $booking->fresh()->registration_status_id);
    }

    public function test_booking_with_a_checked_in_room_cannot_switch_to_waiting(): void
    {
        $booking = $this->createBooking('Partly in house', $this->guaranteed, 2);
        $booking->bookingRooms()->first()->update(['status' => BookingRoom::STATUS_CHECKED_IN, 'room_number' => '101']);

        $this->changeStatus($booking, $this->waiting)->assertStatus(422);

        $this->assertSame($this->guaranteed->booking_status_id, (int) $booking->fresh()->registration_status_id);
    }

    public function test_reservation_not_checked_in_can_still_switch_to_waiting(): void
    {
        $booking = $this->createBooking('Reservation', $this->guaranteed, 1);

        $this->changeStatus($booking, $this->waiting)->assertOk();

        $this->assertSame($this->waiting->booking_status_id, (int) $booking->fresh()->registration_status_id);
    }
}
