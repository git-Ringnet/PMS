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
use App\Models\SystemDateRoll;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dòng 500: nhân bản BK theo thông số IsCopyAllBooking.
 *  - =0: không cần chọn ngày, giữ ngày của BK gốc (kể cả quá khứ), không copy phòng, ngày confirm = ngày hệ thống.
 *  - =1: như cũ (phải chọn ngày mới, ngày đến không nhỏ hơn ngày hệ thống), ngày confirm = ngày hệ thống.
 *  - Ghi log "Nhân bản Đăng Ký {mã mới} từ đăng ký {mã gốc}".
 */
class CopyBookingRow500Test extends TestCase
{
    use RefreshDatabase;

    private const SYSTEM_DATE = '2026-08-16';

    private Booking $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\BookingStatusSeeder::class);
        $user = User::factory()->create();
        $role = Role::create(['code' => 'copy_row500', 'name' => 'Copy row 500', 'level' => 3]);
        $permission = Permission::firstOrCreate(['code' => 'fo.booking.create'], ['name' => 'Create booking', 'module' => 'FO']);
        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);
        $this->actingAs($user);
        SystemDateRoll::create(['system_date' => self::SYSTEM_DATE, 'actual_date' => self::SYSTEM_DATE, 'shift' => '1', 'username' => $user->username]);

        $class = RoomClass::create(['code' => 'CP500', 'name' => 'Copy 500', 'is_active' => true]);
        $form = RoomForm::create(['name' => 'Copy 500 form']);
        Room::create(['room_number' => '501', 'room_class_id' => $class->id, 'room_form_id' => $form->id, 'floor' => 5, 'status' => 'available']);
        $this->source = Booking::create(['booking_name' => 'Source 500', 'arrival_date' => '2026-08-10', 'departure_date' => '2026-08-13', 'num_of_days' => 3, 'booking_date' => '2026-08-01', 'confirm_date' => '2026-08-01', 'created_by' => $user->username]);
        BookingRoom::create(['booking_id' => $this->source->id, 'room_class_id' => $class->id, 'arrival_date' => '2026-08-10', 'departure_date' => '2026-08-13', 'rate' => 1000000, 'adults' => 1, 'status' => BookingRoom::STATUS_BOOKED]);
    }

    private function flag(string $value): void
    {
        HotelConfig::updateOrCreate(['name' => 'IsCopyAllBooking'], ['value' => $value]);
    }

    private function copy(array $body = [])
    {
        return $this->postJson("/api/bookings/{$this->source->id}/copy", $body);
    }

    // ------------------------------------------------------------ IsCopyAllBooking = 0

    public function test_zero_copies_without_sending_any_date_and_keeps_source_dates(): void
    {
        $this->flag('0');

        $res = $this->copy()->assertStatus(201);

        $copy = Booking::findOrFail($res->json('data.id'));
        $this->assertSame('2026-08-10', $copy->arrival_date->toDateString());
        $this->assertSame('2026-08-13', $copy->departure_date->toDateString());
        $this->assertSame(3, (int) $copy->num_of_days);
        $this->assertSame(0, $copy->bookingRooms()->count());
    }

    public function test_zero_allows_source_with_arrival_before_system_date(): void
    {
        $this->flag('0');
        $this->assertLessThan(self::SYSTEM_DATE, $this->source->arrival_date->toDateString());

        $this->copy()->assertStatus(201);
    }

    public function test_zero_sets_confirm_date_to_system_date_not_source_confirm_date(): void
    {
        $this->flag('0');

        $res = $this->copy()->assertStatus(201);

        $copy = Booking::findOrFail($res->json('data.id'));
        $this->assertSame(self::SYSTEM_DATE, $copy->confirm_date->toDateString());
        $this->assertSame('2026-08-01', $this->source->fresh()->confirm_date->toDateString());
    }

    public function test_zero_accepts_dates_in_the_past_when_they_are_sent(): void
    {
        $this->flag('0');

        $res = $this->copy(['arrival_date' => '2026-08-01', 'departure_date' => '2026-08-02'])->assertStatus(201);

        $this->assertSame('2026-08-01', Booking::findOrFail($res->json('data.id'))->arrival_date->toDateString());
    }

    // ------------------------------------------------------------ IsCopyAllBooking = 1

    public function test_one_still_rejects_arrival_before_system_date(): void
    {
        $this->flag('1');

        $this->copy(['arrival_date' => '2026-08-10', 'departure_date' => '2026-08-12'])->assertStatus(422);
        $this->assertSame(1, Booking::count());
    }

    public function test_one_still_requires_new_dates(): void
    {
        $this->flag('1');

        $this->copy()->assertStatus(422);
        $this->assertSame(1, Booking::count());
    }

    public function test_one_copies_rooms_with_new_dates_and_confirm_date_is_system_date(): void
    {
        $this->flag('1');

        $res = $this->copy(['arrival_date' => '2026-08-26', 'departure_date' => '2026-08-28'])->assertStatus(201);

        $copy = Booking::findOrFail($res->json('data.id'));
        $this->assertSame('2026-08-26', $copy->arrival_date->toDateString());
        $this->assertSame(1, $copy->bookingRooms()->count());
        $this->assertSame(self::SYSTEM_DATE, $copy->confirm_date->toDateString());
    }

    // ------------------------------------------------------------ log

    public function test_copy_writes_activity_log_with_new_and_source_codes_for_zero(): void
    {
        $this->flag('0');

        $res = $this->copy()->assertStatus(201);

        $this->assertCopyLogged($res->json('data.id'));
    }

    public function test_copy_writes_activity_log_with_new_and_source_codes_for_one(): void
    {
        $this->flag('1');

        $res = $this->copy(['arrival_date' => '2026-08-26', 'departure_date' => '2026-08-28'])->assertStatus(201);

        $this->assertCopyLogged($res->json('data.id'));
    }

    private function assertCopyLogged(int|string $newId): void
    {
        $newCode = ActivityLogService::bookingCode($newId);
        $sourceCode = ActivityLogService::bookingCode($this->source->id);
        $this->assertDatabaseHas('activity_logs', ['description' => "Nhân bản Đăng Ký {$newCode} từ đăng ký {$sourceCode}"]);
    }
}
