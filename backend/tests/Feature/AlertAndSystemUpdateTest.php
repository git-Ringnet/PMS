<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingRoomGuest;
use App\Models\BookingRoomSpecialRequest;
use App\Models\Guest;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\SpecialRequest;
use App\Models\SystemDateRoll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AlertAndSystemUpdateTest extends TestCase
{
    use RefreshDatabase;

    private BookingRoom $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['username' => 'normal_user']));

        DB::table('booking_statuses')->insert([['id' => 0, 'name' => 'Reservation'], ['id' => 1, 'name' => 'Checked In']]);
        SystemDateRoll::create(['system_date' => '2026-08-18', 'actual_date' => '2026-08-18', 'shift' => '1', 'username' => 'normal_user']);

        $class = RoomClass::create(['code' => 'STD', 'name' => 'Standard', 'is_active' => true]);
        $form = RoomForm::create(['name' => 'Standard']);
        Room::create(['room_number' => '1201', 'room_form_id' => $form->id, 'room_class_id' => $class->id, 'floor' => 12, 'status' => 'vacant_ready']);

        $booking = Booking::create([
            'booking_name' => 'TRAVEL CONCIERGE', 'booking_date' => '2026-08-01',
            'arrival_date' => '2026-08-08', 'departure_date' => '2026-08-20',
            'status' => Booking::STATUS_RESERVATION, 'created_by' => 'normal_user',
        ]);
        $this->room = BookingRoom::create([
            'booking_id' => $booking->id, 'room_number' => '1201', 'room_class_id' => $class->id,
            'arrival_date' => '2026-08-08', 'departure_date' => '2026-08-20', 'rate' => 500000,
            'status' => BookingRoom::STATUS_BOOKED,
        ]);
    }

    private function addGuest(string $name, ?string $dob): Guest
    {
        $guest = Guest::create(['full_name' => $name, 'dob' => $dob]);
        BookingRoomGuest::create([
            'booking_room_id' => $this->room->id, 'guest_id' => $guest->id,
            'status' => BookingRoomGuest::STATUS_ACTIVE, 'is_primary' => true,
        ]);

        return $guest;
    }

    public function test_alert_list_returns_birthdays_in_range_and_special_requests(): void
    {
        $this->addGuest('TAIKESHEVA YELENA', '1981-08-18');
        $request = SpecialRequest::create(['code' => 'HM', 'name' => 'Honeymoon', 'is_active' => true]);
        BookingRoomSpecialRequest::create(['booking_room_id' => $this->room->id, 'special_request_id' => $request->id, 'note' => 'Hoa hồng']);

        $rows = collect($this->getJson('/api/alerts?from_date=2026-08-18&to_date=2026-08-21')->assertOk()->json('data'));

        $birthday = $rows->firstWhere('eventType', 'birthday');
        $this->assertSame('Sinh nhật', $birthday['eventName']);
        $this->assertSame('18/08/1981', $birthday['birthday']);
        $this->assertSame('1201', $birthday['roomNumber']);

        $special = $rows->firstWhere('eventType', 'special_request');
        $this->assertSame('Honeymoon - Hoa hồng', $special['eventName']);
        $this->assertSame(0, $special['status']);
    }

    public function test_alert_list_ignores_birthday_outside_range_or_stay(): void
    {
        $this->addGuest('Khách A', '1990-09-30');

        $this->assertCount(0, $this->getJson('/api/alerts?from_date=2026-08-18&to_date=2026-08-21')->assertOk()->json('data'));

        // Ngày sinh nằm trong khoảng xem nhưng sau ngày đi của phòng (20/8) thì không báo
        Guest::query()->update(['dob' => '1990-08-25']);
        $this->assertCount(0, $this->getJson('/api/alerts?from_date=2026-08-21&to_date=2026-08-28')->assertOk()->json('data'));
    }

    public function test_alert_status_can_be_updated_and_is_returned(): void
    {
        $this->addGuest('TAIKESHEVA YELENA', '1981-08-18');
        $key = $this->getJson('/api/alerts?from_date=2026-08-18&to_date=2026-08-18')->json('data.0.eventKey');

        $this->putJson('/api/alerts/status', ['event_key' => $key, 'status' => 1])->assertOk();

        $row = $this->getJson('/api/alerts?from_date=2026-08-18&to_date=2026-08-18')->json('data.0');
        $this->assertSame(1, $row['status']);
        $this->assertSame('Đã xử lý', $row['statusLabel']);
        $this->assertSame(0, $this->getJson('/api/alerts?from_date=2026-08-18&to_date=2026-08-18')->json('meta.pending'));

        $this->putJson('/api/alerts/status', ['event_key' => 'bad key', 'status' => 1])->assertStatus(422);
    }

    public function test_alert_list_rejects_invalid_range(): void
    {
        $this->getJson('/api/alerts?from_date=2026-08-21&to_date=2026-08-18')->assertStatus(422);
    }

    public function test_only_admin_can_create_system_update(): void
    {
        $this->postJson('/api/system-updates', ['content' => '- Fix lỗi'])->assertStatus(403);

        $admin = User::factory()->create(['username' => 'admin_user']);
        $role = Role::create(['code' => 'admin', 'name' => 'Admin', 'level' => 1, 'department_scope' => 'ALL', 'is_active' => true]);
        $admin->roles()->attach($role->id);
        $this->actingAs($admin);

        $this->postJson('/api/system-updates', ['content' => "- Booking: fix gán số phòng\n- Báo cáo mới", 'update_date' => '2026-10-21'])
            ->assertStatus(201);
        $this->postJson('/api/system-updates', ['content' => ''])->assertStatus(422);
    }

    public function test_system_updates_unread_count_and_mark_read_per_user(): void
    {
        $admin = User::factory()->create(['username' => 'admin_user']);
        $role = Role::create(['code' => 'admin', 'name' => 'Admin', 'level' => 1, 'department_scope' => 'ALL', 'is_active' => true]);
        $admin->roles()->attach($role->id);
        $this->actingAs($admin)->postJson('/api/system-updates', ['content' => '- Nội dung 1'])->assertStatus(201);
        $this->postJson('/api/system-updates', ['content' => '- Nội dung 2'])->assertStatus(201);

        $this->actingAs(User::factory()->create(['username' => 'staff']));
        $list = $this->getJson('/api/system-updates')->assertOk();
        $list->assertJsonPath('meta.unread', 2)->assertJsonPath('meta.can_manage', false);

        // Đọc từng nội dung: chỉ nội dung đó chuyển sang đã đọc
        $firstId = $list->json('data.0.id');
        $this->postJson('/api/system-updates/read', ['ids' => [$firstId]])->assertOk();
        $this->getJson('/api/system-updates')->assertJsonPath('meta.unread', 1);

        $this->postJson('/api/system-updates/read')->assertOk();
        $this->getJson('/api/system-updates')->assertJsonPath('meta.unread', 0);

        // User khác vẫn chưa đọc
        $this->actingAs($admin)->getJson('/api/system-updates')->assertJsonPath('meta.unread', 2);

        // Nội dung mới sau khi đọc làm tăng lại số chưa đọc
        $this->postJson('/api/system-updates', ['content' => '- Nội dung 3'])->assertStatus(201);
        $this->actingAs(User::where('username', 'staff')->first())->getJson('/api/system-updates')->assertJsonPath('meta.unread', 1);
    }
}
