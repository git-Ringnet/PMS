<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\RoomLock;
use App\Models\HotelConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomLockTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $hkmUser;
    protected User $fomUser;
    protected Room $room101;
    protected RoomClass $supdClass;
    protected RoomForm $doubleForm;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Seed users
        $this->adminUser = User::create([
            'name' => 'Admin User',
            'username' => 'admin',
            'email' => 'admin@pms.com',
            'job_title' => 'Tổng giám đốc',
            'job_title_code' => 'RL001',
            'department_code' => 'MGMT',
            'department' => 'BỘ PHẬN QUẢN LÝ',
            'password' => bcrypt('password'),
        ]);

        $this->hkmUser = User::create([
            'name' => 'Housekeeping Manager',
            'username' => 'hkm',
            'email' => 'hkm@pms.com',
            'job_title' => 'Trưởng HK',
            'job_title_code' => 'RL016',
            'department_code' => 'HK',
            'department' => 'BỘ PHẬN BUỒNG PHÒNG',
            'password' => bcrypt('password'),
        ]);

        $this->fomUser = User::create([
            'name' => 'Front Office Manager',
            'username' => 'thaovy',
            'email' => 'fom@pms.com',
            'job_title' => 'Trưởng Bộ Phận',
            'job_title_code' => 'RL017',
            'department_code' => 'FO',
            'department' => 'BỘ PHẬN LỄ TÂN',
            'password' => bcrypt('password'),
        ]);

        // 2. Seed room class and form
        $this->supdClass = RoomClass::create([
            'name' => 'Superior Double',
            'code' => 'SUPD',
            'is_active' => true,
        ]);

        $this->doubleForm = RoomForm::create([
            'name' => 'Double',
            'max_adults' => 2,
        ]);

        // 3. Seed rooms
        $this->room101 = Room::create([
            'room_number' => '101',
            'room_class_id' => $this->supdClass->id,
            'room_form_id' => $this->doubleForm->id,
            'max_guests' => 2,
            'floor' => '1',
            'status' => 'available',
        ]);

        // 4. Seed configs & system date
        $this->seed(\Database\Seeders\BookingStatusSeeder::class);

        \App\Models\SystemDateRoll::create([
            'system_date' => '2026-06-01 00:00:00',
            'actual_date' => '2026-06-01 00:00:00',
            'shift' => '1',
            'username' => 'admin',
        ]);

        HotelConfig::updateOrCreate(['name' => 'AllowOverRoomTypeRoomKind'], ['value' => '0']);
        HotelConfig::updateOrCreate(['name' => 'AllowLockRoomCauseUnassignableRoomBK'], ['value' => '0']);
        HotelConfig::updateOrCreate(['name' => 'OOOCheckDepartment'], ['value' => '0']);
        HotelConfig::updateOrCreate(['name' => 'OOSCheckDepartment'], ['value' => '0']);
        HotelConfig::updateOrCreate(['name' => 'OOORoleUserUnlock'], ['value' => 'Admin,FOM,Sales,HKM']);
        HotelConfig::updateOrCreate(['name' => 'OOSRoleUserUnlock'], ['value' => 'Admin,FOM,Sales,HKM']);
        HotelConfig::updateOrCreate(['name' => 'FrmOOO_DefineLockByTime'], ['value' => '23:59']);
    }

    public function test_lock_period_validation()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/room-locks', [
            'room_number' => $this->room101->room_number,
            'start_date' => '2026-07-02 12:00:00',
            'end_date' => '2026-07-02 11:00:00', // end < start
            'lock_type' => 'OOO',
            'reason' => 'Bảo trì',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Giờ kết thúc không được nhỏ hơn giờ bắt đầu (trong cùng ngày).'
        ]);
    }

    public function test_overlap_locks_not_allowed()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);

        // Create first lock
        RoomLock::create([
            'room_number' => $this->room101->room_number,
            'start_date' => '2026-07-02 10:00:00',
            'end_date' => '2026-07-02 12:00:00',
            'lock_type' => 'OOO',
            'is_active' => 1,
        ]);

        // Attempt overlapping lock
        $response = $this->postJson('/api/room-locks', [
            'room_number' => $this->room101->room_number,
            'start_date' => '2026-07-02 11:00:00',
            'end_date' => '2026-07-02 13:00:00',
            'lock_type' => 'OOS',
            'reason' => 'Chồng chéo',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Không được phép khóa phòng do phòng đã có lịch khóa OOO/OOS khác trùng lặp thời gian này.'
        ]);
    }

    public function test_booking_overlap_prevention_strict()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);

        $room401 = Room::create([
            'room_number' => '401',
            'room_class_id' => $this->supdClass->id,
            'room_form_id' => $this->doubleForm->id,
            'max_guests' => 2,
            'floor' => '4',
            'status' => 'available',
        ]);

        $booking = \App\Models\Booking::create([
            'booking_code' => 'GAL5333',
            'booking_name' => 'Test Booking',
            'booking_date' => '2026-06-09 00:00:00',
            'arrival_date' => '2026-06-09 00:00:00',
            'departure_date' => '2026-06-13 00:00:00',
            'created_by' => 'admin',
            'status' => 0,
        ]);
        \App\Models\BookingRoom::create([
            'booking_id' => $booking->id,
            'room_number' => '401',
            'room_class_id' => $this->supdClass->id,
            'arrival_date' => '2026-06-09 00:00:00',
            'departure_date' => '2026-06-13 00:00:00',
            'status' => 0,
        ]);

        HotelConfig::where('name', 'AllowLockRoomCauseUnassignableRoomBK')->update(['value' => '0']);

        $response = $this->postJson('/api/room-locks', [
            'room_number' => $room401->room_number,
            'start_date' => '2026-06-10 10:00:00',
            'end_date' => '2026-06-12 12:00:00',
            'lock_type' => 'OOO',
            'reason' => 'Trùng lịch',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Không được phép khóa phòng vì trùng lịch với booking BK-1 (09/06/2026 ~ 13/06/2026).'
        ]);
    }

    public function test_booking_overlap_prevention_warning()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);

        $room401 = Room::create([
            'room_number' => '401_warn',
            'room_class_id' => $this->supdClass->id,
            'room_form_id' => $this->doubleForm->id,
            'max_guests' => 2,
            'floor' => '4',
            'status' => 'available',
        ]);

        $booking = \App\Models\Booking::create([
            'booking_name' => 'Test Booking Warn',
            'booking_date' => '2026-06-09 00:00:00',
            'arrival_date' => '2026-06-09 00:00:00',
            'departure_date' => '2026-06-13 00:00:00',
            'created_by' => 'admin',
            'status' => 0,
        ]);
        \App\Models\BookingRoom::create([
            'booking_id' => $booking->id,
            'room_number' => '401_warn',
            'room_class_id' => $this->supdClass->id,
            'arrival_date' => '2026-06-09 00:00:00',
            'departure_date' => '2026-06-13 00:00:00',
            'status' => 0,
        ]);

        // Strict block is enforced for booking overlap
        $response = $this->postJson('/api/room-locks', [
            'room_number' => $room401->room_number,
            'start_date' => '2026-06-10 10:00:00',
            'end_date' => '2026-06-12 12:00:00',
            'lock_type' => 'OOO',
            'reason' => 'Trùng lịch cảnh báo',
        ]);

        $response->assertStatus(422);
    }

    public function test_edit_lock_start_date_restrictions()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);

        // Create a lock starting in past/current time relative to our fake now()
        // Wait, Laravel's now() will be current test run time, so let's use past date
        $lock = RoomLock::create([
            'room_number' => $this->room101->room_number,
            'start_date' => now()->subDay()->format('Y-m-d H:i:s'),
            'end_date' => now()->addDay()->format('Y-m-d H:i:s'),
            'lock_type' => 'OOO',
            'is_active' => true,
        ]);
        // Attempt to edit start_date
        $response = $this->putJson("/api/room-locks/{$lock->id}", [
            'start_date' => now()->subHours(12)->format('Y-m-d H:i:s'), // changed
            'end_date' => now()->addDay()->format('Y-m-d H:i:s'),
            'lock_type' => 'OOO',
        ]);


        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Không được phép điều chỉnh ngày bắt đầu đối với phòng đang trong giai đoạn khóa.'
        ]);

        // Attempt to edit end_date only (should be allowed)
        $response2 = $this->putJson("/api/room-locks/{$lock->id}", [
            'start_date' => $lock->start_date->format('Y-m-d H:i:s'), // unchanged
            'end_date' => now()->addDays(2)->format('Y-m-d H:i:s'), // changed
            'lock_type' => 'OOO',
        ]);

        $response2->assertStatus(200);
    }

    public function test_unlock_department_permission_checking()
    {
        // Setup: Locked by thaovy (BỘ PHẬN LỄ TÂN)
        $lock = RoomLock::create([
            'room_number' => $this->room101->room_number,
            'start_date' => now()->format('Y-m-d H:i:s'),
            'end_date' => now()->addDay()->format('Y-m-d H:i:s'),
            'lock_type' => 'OOO',
            'username' => 'thaovy',
            'is_active' => true,
        ]);

        // Enable department verification
        HotelConfig::where('name', 'OOOCheckDepartment')->update(['value' => '1']);

        // Try unlocking as hkmUser (BỘ PHẬN BUỒNG PHÒNG) -> should fail
        \Laravel\Sanctum\Sanctum::actingAs($this->hkmUser);
        $response = $this->deleteJson("/api/room-locks/{$lock->id}");
        $response->assertStatus(403);
        $response->assertJsonFragment([
            'message' => 'Bạn không thuộc bộ phận đã thực hiện khóa phòng này (Bộ phận: BỘ PHẬN LỄ TÂN), không thể mở khóa.'
        ]);

        // Try unlocking as adminUser (BỘ PHẬN QUẢN LÝ) -> should fail because of department mismatch
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);
        $response2 = $this->deleteJson("/api/room-locks/{$lock->id}");
        $response2->assertStatus(403);

        // Disable department verification -> should pass (assuming role is OK)
        HotelConfig::where('name', 'OOOCheckDepartment')->update(['value' => '0']);
        $response3 = $this->deleteJson("/api/room-locks/{$lock->id}");
        $response3->assertStatus(200);
    }

    public function test_unlock_role_permission_checking()
    {
        $lock = RoomLock::create([
            'room_number' => $this->room101->room_number,
            'start_date' => now()->format('Y-m-d H:i:s'),
            'end_date' => now()->addDay()->format('Y-m-d H:i:s'),
            'lock_type' => 'OOO',
            'username' => 'admin',
            'is_active' => true,
        ]);

        // Set allowed roles to HKM only
        HotelConfig::where('name', 'OOORoleUserUnlock')->update(['value' => 'HKM']);

        // Try unlocking as FOM (thaovy) -> should fail
        \Laravel\Sanctum\Sanctum::actingAs($this->fomUser);
        $response = $this->deleteJson("/api/room-locks/{$lock->id}");
        $response->assertStatus(403);
        $response->assertJsonFragment([
            'message' => 'Tài khoản của bạn không có vai trò được phép mở khóa phòng OOO (Quyền yêu cầu: HKM).'
        ]);

        // Try unlocking as HKM -> should pass
        \Laravel\Sanctum\Sanctum::actingAs($this->hkmUser);
        $response2 = $this->deleteJson("/api/room-locks/{$lock->id}");
        $response2->assertStatus(200);
    }

    public function test_edit_past_lock_restrictions()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);

        // Create a lock in the past
        $lock = RoomLock::create([
            'room_number' => $this->room101->room_number,
            'start_date' => now()->subDays(5)->format('Y-m-d H:i:s'),
            'end_date' => now()->subDays(3)->format('Y-m-d H:i:s'),
            'lock_type' => 'OOO',
            'is_active' => true,
        ]);

        // Attempt to edit it
        $response = $this->putJson("/api/room-locks/{$lock->id}", [
            'start_date' => now()->subDays(5)->format('Y-m-d H:i:s'),
            'end_date' => now()->subDays(2)->format('Y-m-d H:i:s'),
            'lock_type' => 'OOO',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Không được phép chỉnh sửa lịch khóa phòng đã kết thúc trong quá khứ.'
        ]);
    }
}
