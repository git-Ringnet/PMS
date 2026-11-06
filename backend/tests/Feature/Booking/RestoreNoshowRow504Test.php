<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\HotelConfig;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\RoomLock;
use App\Models\SystemDateRoll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dòng 504: khôi phục BK/phòng noshow phải
 *  - kiểm tra over phòng theo AllowOverRoomTypeRoomKind (=0 chặn hẳn kể cả khi gửi force, =1 hỏi xác nhận);
 *  - không để trùng số phòng (BK khác đang giữ / phòng khóa OOO-OOS): hỏi, chọn Có thì xóa số phòng bị trùng.
 * Thứ tự như dòng 294: kiểm tra over + thông số trước, rồi mới hỏi trùng số phòng.
 */
class RestoreNoshowRow504Test extends TestCase
{
    use RefreshDatabase;

    private RoomClass $class;
    private int $statusId;

    public function createApplication()
    {
        $app = parent::createApplication();

        config(['database.default' => 'sqlite']);
        foreach (['sqlite', 'mysql', 'mysql_data', 'mysql_db', 'mysql_hkt1', 'mysql_hkt2', 'mysql_hkt3', 'mysql_hkt4'] as $conn) {
            config(["database.connections.{$conn}" => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ]]);
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['SystemConfigurationSeeder', 'DepartmentSeeder', 'HotelDefinitionSeeder', 'SystemDefinitionSeeder', 'BookingStatusSeeder'] as $seeder) {
            $this->seed("Database\\Seeders\\{$seeder}");
        }
        // bookings.registration_status_id nối với registration_statuses.booking_status_id (20 = None Guaranteed, tính vào AV).
        $this->statusId = (int) \App\Models\RegistrationStatus::where('booking_status_id', 20)->firstOrFail()->booking_status_id;
        $user = User::factory()->create();
        $role = Role::create(['code' => 'noshow_row504', 'name' => 'Noshow 504', 'level' => 3]);
        $permission = Permission::firstOrCreate(['code' => 'fo.booking.edit'], ['name' => 'Edit booking', 'module' => 'FO']);
        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);
        $this->actingAs($user);
        SystemDateRoll::create(['system_date' => '2026-10-01', 'actual_date' => '2026-10-01', 'shift' => '1', 'username' => $user->username]);

        $this->class = RoomClass::create(['code' => 'N504', 'name' => 'Noshow 504', 'is_active' => true]);
        $form = RoomForm::create(['name' => 'Noshow 504 form']);
        foreach (['1051', '1052'] as $n) {
            Room::create(['room_number' => $n, 'room_class_id' => $this->class->id, 'room_form_id' => $form->id, 'floor' => 5, 'status' => 'available']);
        }
    }

    private function allowOver(string $v): void
    {
        HotelConfig::updateOrCreate(['name' => 'AllowOverRoomTypeRoomKind'], ['value' => $v]);
    }

    private function booking(int $status, string $arrival, string $departure): Booking
    {
        return Booking::create([
            'booking_name' => 'B' . uniqid(), 'arrival_date' => $arrival, 'departure_date' => $departure,
            'num_of_days' => 2, 'booking_date' => '2026-09-20', 'status' => $status, 'created_by' => 'x', 'registration_status_id' => $this->statusId,
        ]);
    }

    private function room(Booking $b, ?string $roomNumber, int $status, string $arrival, string $departure): BookingRoom
    {
        return BookingRoom::create([
            'id' => 'BR' . uniqid(), 'booking_id' => $b->id, 'room_number' => $roomNumber, 'room_class_id' => $this->class->id,
            'arrival_date' => $arrival, 'departure_date' => $departure, 'rate' => 1000000, 'adults' => 1, 'status' => $status,
        ]);
    }

    /** BK noshow 1 phòng 10/10-12/10 (số phòng tùy chọn). */
    private function noshowBooking(?string $roomNumber = null): array
    {
        $b = $this->booking(Booking::STATUS_NO_SHOW, '2026-10-10', '2026-10-12');
        return [$b, $this->room($b, $roomNumber, BookingRoom::STATUS_NOSHOW, '2026-10-10', '2026-10-12')];
    }

    /** Làm loại phòng hết chỗ đúng đêm 10/10 (2 phòng đều bị giữ đêm đó) để khôi phục noshow thành over. */
    private function fillClassOnFirstNight(): void
    {
        foreach (['x1', 'x2'] as $i) {
            $b = $this->booking(Booking::STATUS_RESERVATION, '2026-10-10', '2026-10-11');
            $r = $this->room($b, null, BookingRoom::STATUS_BOOKED, '2026-10-10', '2026-10-11');
            // SQLite so sánh chuỗi: lưu Y-m-d thuần để ngày đi 11/10 không bị tính là còn ở đêm 11/10.
            \DB::table('booking_rooms')->where('id', $r->id)->update(['arrival_date' => '2026-10-10', 'departure_date' => '2026-10-11']);
        }
    }

    private function restoreBooking(Booking $b, array $body = [])
    {
        return $this->postJson("/api/bookings/{$b->id}/revert-noshow", $body);
    }

    private function restoreRoom(Booking $b, BookingRoom $r, array $body = [])
    {
        return $this->postJson("/api/bookings/{$b->id}/rooms/{$r->id}/revert-noshow", $body);
    }

    public function test_fixture_class_is_full_on_first_night_and_free_on_second_night(): void
    {
        $this->fillClassOnFirstNight();
        $av = app(\App\Services\RoomAvailabilityService::class);

        $this->assertSame(0, $av->getAvailability($this->class->id, '2026-10-10', '2026-10-11'));
        $this->assertSame(2, $av->getAvailability($this->class->id, '2026-10-11', '2026-10-12'));
    }

    // ------------------------------------------------------------ over (khôi phục cả BK)

    public function test_booking_over_on_first_night_only_is_detected(): void
    {
        $this->allowOver('1');
        $this->fillClassOnFirstNight();
        [$b] = $this->noshowBooking();

        $this->restoreBooking($b)->assertStatus(200)->assertJson(['success' => false, 'needs_confirm' => true]);
        $this->assertSame(Booking::STATUS_NO_SHOW, (int) $b->fresh()->status);
    }

    public function test_booking_over_with_allow_over_zero_is_blocked_even_when_force_is_sent(): void
    {
        $this->allowOver('0');
        $this->fillClassOnFirstNight();
        [$b] = $this->noshowBooking();

        $this->restoreBooking($b)->assertStatus(422);
        $this->restoreBooking($b, ['force' => true])->assertStatus(422);
        $this->assertSame(Booking::STATUS_NO_SHOW, (int) $b->fresh()->status);
    }

    public function test_booking_over_with_allow_over_one_restores_after_confirm(): void
    {
        $this->allowOver('1');
        $this->fillClassOnFirstNight();
        [$b, $r] = $this->noshowBooking();

        $this->restoreBooking($b, ['force' => true])->assertStatus(200)->assertJson(['success' => true]);
        $this->assertSame(BookingRoom::STATUS_BOOKED, (int) $r->fresh()->status);
    }

    public function test_booking_without_over_restores_directly(): void
    {
        $this->allowOver('0');
        [$b, $r] = $this->noshowBooking();

        $this->restoreBooking($b)->assertStatus(200)->assertJson(['success' => true]);
        $this->assertSame(BookingRoom::STATUS_BOOKED, (int) $r->fresh()->status);
    }

    // ------------------------------------------------------------ trùng số phòng (khôi phục cả BK)

    public function test_booking_duplicate_room_number_asks_then_clears_room_number_of_restored_room_only(): void
    {
        $this->allowOver('1');
        $other = $this->booking(Booking::STATUS_RESERVATION, '2026-10-10', '2026-10-12');
        $otherRoom = $this->room($other, '1051', BookingRoom::STATUS_BOOKED, '2026-10-10', '2026-10-12');
        [$b, $r] = $this->noshowBooking('1051');

        $res = $this->restoreBooking($b)->assertStatus(200);
        $res->assertJson(['success' => false, 'needs_duplicate_confirm' => true]);
        $this->assertStringContainsString('- R: 1051', $res->json('conflict_lines'));
        $this->assertSame(BookingRoom::STATUS_NOSHOW, (int) $r->fresh()->status);

        $this->restoreBooking($b, ['clear_duplicate_rooms' => true])->assertStatus(200)->assertJson(['success' => true]);
        $this->assertNull($r->fresh()->room_number);
        $this->assertSame(BookingRoom::STATUS_BOOKED, (int) $r->fresh()->status);
        $this->assertSame('1051', $otherRoom->fresh()->room_number);
    }

    public function test_booking_duplicate_with_locked_room_asks_without_booking_code(): void
    {
        $this->allowOver('1');
        RoomLock::create(['room_number' => '1051', 'start_date' => '2026-10-10 12:00:00', 'end_date' => '2026-10-12 12:00:00', 'is_active' => 1, 'lock_type' => 'OOO']);
        [$b] = $this->noshowBooking('1051');

        $res = $this->restoreBooking($b)->assertStatus(200);
        $res->assertJson(['success' => false, 'needs_duplicate_confirm' => true]);
        $this->assertSame('- R: 1051', trim($res->json('conflict_lines')));
    }

    public function test_booking_duplicate_and_over_is_blocked_immediately_when_allow_over_zero(): void
    {
        $this->allowOver('0');
        $other = $this->booking(Booking::STATUS_RESERVATION, '2026-10-10', '2026-10-12');
        $this->room($other, '1051', BookingRoom::STATUS_BOOKED, '2026-10-10', '2026-10-12');
        $this->fillClassOnFirstNight();
        [$b, $r] = $this->noshowBooking('1051');

        $res = $this->restoreBooking($b)->assertStatus(422);
        $this->assertArrayNotHasKey('needs_duplicate_confirm', $res->json());
        $this->assertSame('1051', $r->fresh()->room_number);
    }

    public function test_booking_duplicate_and_over_asks_duplicate_then_over_when_allow_over_one(): void
    {
        $this->allowOver('1');
        $other = $this->booking(Booking::STATUS_RESERVATION, '2026-10-10', '2026-10-12');
        $this->room($other, '1051', BookingRoom::STATUS_BOOKED, '2026-10-10', '2026-10-12');
        $this->fillClassOnFirstNight();
        [$b, $r] = $this->noshowBooking('1051');

        $this->restoreBooking($b)->assertJson(['needs_duplicate_confirm' => true]);
        $this->restoreBooking($b, ['clear_duplicate_rooms' => true])->assertJson(['needs_confirm' => true, 'success' => false]);
        $this->restoreBooking($b, ['clear_duplicate_rooms' => true, 'force' => true])->assertStatus(200)->assertJson(['success' => true]);
        $this->assertNull($r->fresh()->room_number);
    }

    public function test_booking_room_number_not_used_by_others_is_kept(): void
    {
        $this->allowOver('1');
        [$b, $r] = $this->noshowBooking('1051');

        $this->restoreBooking($b)->assertStatus(200)->assertJson(['success' => true]);
        $this->assertSame('1051', $r->fresh()->room_number);
    }

    // ------------------------------------------------------------ khôi phục từng phòng noshow

    public function test_room_duplicate_room_number_asks_then_clears(): void
    {
        $this->allowOver('1');
        $other = $this->booking(Booking::STATUS_RESERVATION, '2026-10-10', '2026-10-12');
        $this->room($other, '1051', BookingRoom::STATUS_BOOKED, '2026-10-10', '2026-10-12');
        [$b, $r] = $this->noshowBooking('1051');

        $res = $this->restoreRoom($b, $r)->assertStatus(200);
        $res->assertJson(['success' => false, 'needs_duplicate_confirm' => true]);
        $this->assertStringContainsString('- R: 1051', $res->json('conflict_lines'));
        $this->assertSame(BookingRoom::STATUS_NOSHOW, (int) $r->fresh()->status);

        $this->restoreRoom($b, $r, ['clear_duplicate_rooms' => true])->assertStatus(200)->assertJson(['success' => true]);
        $this->assertNull($r->fresh()->room_number);
        $this->assertSame(BookingRoom::STATUS_BOOKED, (int) $r->fresh()->status);
    }

    public function test_room_over_with_allow_over_zero_is_blocked_even_when_force_is_sent(): void
    {
        $this->allowOver('0');
        $this->fillClassOnFirstNight();
        [$b, $r] = $this->noshowBooking();

        $this->restoreRoom($b, $r)->assertStatus(422);
        $this->restoreRoom($b, $r, ['force' => true])->assertStatus(422);
        $this->assertSame(BookingRoom::STATUS_NOSHOW, (int) $r->fresh()->status);
    }

    public function test_room_duplicate_and_over_is_blocked_immediately_when_allow_over_zero(): void
    {
        $this->allowOver('0');
        $other = $this->booking(Booking::STATUS_RESERVATION, '2026-10-10', '2026-10-12');
        $this->room($other, '1051', BookingRoom::STATUS_BOOKED, '2026-10-10', '2026-10-12');
        $this->fillClassOnFirstNight();
        [$b, $r] = $this->noshowBooking('1051');

        $res = $this->restoreRoom($b, $r)->assertStatus(422);
        $this->assertArrayNotHasKey('needs_duplicate_confirm', $res->json());
    }

    public function test_room_over_with_allow_over_one_asks_then_restores_with_force(): void
    {
        $this->allowOver('1');
        $this->fillClassOnFirstNight();
        [$b, $r] = $this->noshowBooking();

        $this->restoreRoom($b, $r)->assertStatus(200)->assertJson(['success' => false, 'needs_confirm' => true]);
        $this->restoreRoom($b, $r, ['force' => true])->assertStatus(200)->assertJson(['success' => true]);
    }
}
