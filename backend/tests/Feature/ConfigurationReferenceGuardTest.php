<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\HotelConfig;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConfigurationReferenceGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create(['username' => 'config_tester']);
        $role = Role::create(['code' => 'super_admin', 'name' => 'Super admin', 'level' => 0, 'department_scope' => 'ALL', 'is_active' => true]);
        $user->roles()->attach($role->id);
        Sanctum::actingAs($user);
    }

    public function test_room_used_by_booking_room_cannot_be_deleted_or_renumbered(): void
    {
        $room = Room::create([
            'room_number' => '101',
            'floor' => 1,
            'room_class_id' => RoomClass::create(['name' => 'Superior', 'code' => 'SUP', 'is_active' => true])->id,
            'room_form_id' => RoomForm::create(['name' => 'Double', 'max_adults' => 2])->id,
        ]);
        DB::table('booking_statuses')->insertOrIgnore(['id' => 0, 'name' => 'Booking']);
        DB::table('booking_rooms')->insert([
            'id' => 'BR-TEST-1', 'room_number' => '101', 'arrival_date' => '2026-10-23', 'departure_date' => '2026-10-24',
        ]);

        $this->deleteJson("/api/rooms/{$room->id}")
            ->assertStatus(422)
            ->assertJsonPath('errors.references.0', fn ($message) => str_contains($message, 'chi tiết phòng của booking'));
        $this->assertDatabaseHas('rooms', ['id' => $room->id]);

        $this->putJson("/api/rooms/{$room->id}", array_merge($room->toArray(), ['room_number' => '102', 'floor' => '1', 'max_guests' => 2]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('room_number');
    }

    public function test_currency_used_by_payment_cannot_be_deleted_or_recoded(): void
    {
        $currency = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'country' => 'USA', 'is_main' => false]);
        DB::table('payments')->insert(['currency' => 'USD', 'amount' => 10, 'date' => '2026-10-23']);

        $this->deleteJson("/api/currencies/{$currency->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('references');
        $this->assertDatabaseHas('currencies', ['id' => $currency->id]);

        $this->putJson("/api/currencies/{$currency->id}", ['code' => 'USX', 'name' => 'US Dollar', 'country' => 'USA'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_unused_currency_can_be_deleted_but_main_currency_cannot(): void
    {
        $unused = Currency::create(['code' => 'EUR', 'name' => 'Euro', 'country' => 'EU', 'is_main' => false]);
        $main = Currency::create(['code' => 'VND', 'name' => 'Việt Nam Đồng', 'country' => 'VN', 'is_main' => true]);

        $this->deleteJson("/api/currencies/{$unused->id}")->assertOk();
        $this->deleteJson("/api/currencies/{$main->id}")->assertStatus(422);
        $this->assertDatabaseHas('currencies', ['id' => $main->id]);
    }

    public function test_shift_used_by_payment_cannot_be_deleted_or_renamed(): void
    {
        $shift = Shift::create(['name' => '1', 'start_time' => '06:00', 'end_time' => '14:00']);
        DB::table('payments')->insert(['shift' => '1', 'amount' => 10, 'date' => '2026-10-23']);

        $this->deleteJson("/api/shifts/{$shift->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('references');
        $this->assertDatabaseHas('shifts', ['id' => $shift->id]);

        $this->putJson("/api/shifts/{$shift->id}", ['name' => '9', 'start_time' => '06:00', 'end_time' => '14:00'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        // Time changes do not alter historical rows.
        $this->putJson("/api/shifts/{$shift->id}", ['name' => '1', 'start_time' => '07:00', 'end_time' => '15:00'])
            ->assertOk();
    }

    public function test_shift_with_housekeeping_assignment_is_not_cascade_deleted(): void
    {
        $shift = Shift::create(['name' => 'Ca sáng', 'start_time' => '06:00', 'end_time' => '14:00']);
        DB::table('hk_assignments')->insert(['work_date' => '2026-10-23', 'shift_id' => $shift->id]);

        $this->deleteJson("/api/shifts/{$shift->id}")->assertStatus(422);
        $this->assertDatabaseHas('hk_assignments', ['shift_id' => $shift->id]);
    }

    public function test_hotel_config_name_cannot_be_changed(): void
    {
        $config = HotelConfig::create(['name' => 'AllowOverRoomTypeRoomKind', 'value' => '1']);

        $this->putJson("/api/hotel-configs/{$config->id}", ['name' => 'AllowOverRoomType', 'value' => '1'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $this->putJson("/api/hotel-configs/{$config->id}", ['name' => 'AllowOverRoomTypeRoomKind', 'value' => '0'])
            ->assertOk();
    }

    /**
     * Checklist Định nghĩa phòng (note "vẫn xóa được phòng khi có dữ liệu phát sinh"):
     * lịch sử đổi tình trạng phòng, phân công buồng phòng, snapshot sang ngày cũng là dữ liệu phát sinh.
     */
    private function makeRoom(string $number): Room
    {
        return Room::create([
            'room_number' => $number,
            'floor' => 1,
            'room_class_id' => RoomClass::firstOrCreate(['code' => 'SUP'], ['name' => 'Superior', 'is_active' => true])->id,
            'room_form_id' => RoomForm::firstOrCreate(['name' => 'Double'], ['max_adults' => 2])->id,
        ]);
    }

    private function nightAuditRunId(): int
    {
        return DB::table('night_audit_runs')->insertGetId([
            'source_system_date' => '2026-10-23', 'target_system_date' => '2026-10-24',
            'actual_started_at' => now(), 'idempotency_key' => uniqid('na', true),
        ]);
    }

    public function test_room_with_status_change_history_cannot_be_deleted(): void
    {
        $room = $this->makeRoom('201');
        // Bảng chỉ được migration tạo trên MySQL; test SQLite tự tạo bản tối thiểu.
        if (!\Illuminate\Support\Facades\Schema::hasTable('room_status_change_logs')) {
            \Illuminate\Support\Facades\Schema::create('room_status_change_logs', function ($table) {
                $table->id();
                $table->dateTime('changed_at')->nullable();
                $table->string('username', 50)->nullable();
                $table->string('room', 50)->nullable();
            });
        }
        DB::table('room_status_change_logs')->insert(['room' => '201', 'changed_at' => now(), 'username' => 'hk']);

        $this->deleteJson("/api/rooms/{$room->id}")
            ->assertStatus(422)
            ->assertJsonPath('errors.references.0', fn ($message) => str_contains($message, 'lịch sử tình trạng phòng'));
        $this->assertDatabaseHas('rooms', ['id' => $room->id]);
    }

    public function test_room_in_housekeeping_assignment_or_night_audit_snapshot_cannot_be_deleted(): void
    {
        $assigned = $this->makeRoom('202');
        $audited = $this->makeRoom('203');
        $shiftId = Shift::create(['name' => 'Ca 1'])->id;
        $assignmentId = DB::table('hk_assignments')->insertGetId(['work_date' => '2026-10-23', 'shift_id' => $shiftId]);
        $groupId = DB::table('hk_assignment_groups')->insertGetId(['assignment_id' => $assignmentId]);
        DB::table('hk_assignment_group_rooms')->insert(['group_id' => $groupId, 'room_id' => $assigned->id]);
        DB::table('night_audit_inhouse_snapshots')->insert([
            'night_audit_run_id' => $this->nightAuditRunId(), 'snapshot_date' => '2026-10-23', 'status' => 1, 'room' => '203', 'booking_id' => 1,
        ]);

        $this->deleteJson("/api/rooms/{$assigned->id}")->assertStatus(422)->assertJsonValidationErrors('references');
        $this->deleteJson("/api/rooms/{$audited->id}")->assertStatus(422)->assertJsonValidationErrors('references');
        $this->assertDatabaseHas('rooms', ['id' => $assigned->id]);
        $this->assertDatabaseHas('rooms', ['id' => $audited->id]);

        $unused = $this->makeRoom('204');
        $this->deleteJson("/api/rooms/{$unused->id}")->assertOk();
    }

    public function test_room_class_in_night_audit_snapshot_cannot_be_deleted(): void
    {
        RoomClass::create(['name' => 'Deluxe', 'code' => 'DLX', 'is_active' => true]);
        $roomClass = RoomClass::create(['name' => 'Suite', 'code' => 'STE', 'is_active' => true]);
        DB::table('night_audit_room_type_snapshots')->insert([
            'night_audit_run_id' => $this->nightAuditRunId(), 'snapshot_date' => '2026-10-23', 'room_type_code' => 'STE', 'room_type_id' => $roomClass->id,
        ]);

        $this->deleteJson("/api/room-classes/{$roomClass->id}")->assertStatus(422);
        $this->assertDatabaseHas('room_classes', ['id' => $roomClass->id]);
    }
}
