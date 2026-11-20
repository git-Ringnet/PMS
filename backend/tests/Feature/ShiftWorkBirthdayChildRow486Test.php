<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingChild;
use App\Models\BookingRoom;
use App\Models\BookingRoomGuest;
use App\Models\Guest;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\SystemDateRoll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Dòng 486: tab "Sinh nhật khách" (Danh sách công việc) bỏ sót trẻ em / em bé.
class ShiftWorkBirthdayChildRow486Test extends TestCase
{
    use RefreshDatabase;

    private BookingRoom $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['username' => 'shift_user']));
        DB::table('booking_statuses')->insert([['id' => 0, 'name' => 'Reservation'], ['id' => 1, 'name' => 'Checked In']]);
        SystemDateRoll::create(['system_date' => '2026-09-04', 'actual_date' => '2026-09-04', 'shift' => '1', 'username' => 'shift_user']);

        $class = RoomClass::create(['code' => 'STD', 'name' => 'Standard', 'is_active' => true]);
        $form = RoomForm::create(['name' => 'Standard']);
        Room::create(['room_number' => '203', 'room_form_id' => $form->id, 'room_class_id' => $class->id, 'floor' => 2, 'status' => 'vacant_ready']);

        $booking = Booking::create([
            'booking_name' => 'NV58', 'booking_date' => '2026-09-01',
            'arrival_date' => '2026-09-04', 'departure_date' => '2026-09-07',
            'status' => Booking::STATUS_RESERVATION, 'created_by' => 'shift_user',
        ]);
        $this->room = BookingRoom::create([
            'booking_id' => $booking->id, 'room_number' => '203', 'room_class_id' => $class->id,
            'arrival_date' => '2026-09-04', 'departure_date' => '2026-09-07', 'rate' => 950000,
            'status' => BookingRoom::STATUS_BOOKED,
        ]);
        $guest = Guest::create(['full_name' => 'Guest 1', 'dob' => '1990-01-01']);
        BookingRoomGuest::create(['booking_room_id' => $this->room->id, 'guest_id' => $guest->id, 'status' => BookingRoomGuest::STATUS_ACTIVE, 'is_primary' => true]);
    }

    private function child(string $name, ?string $dob, string $group = 'child', int $status = BookingRoomGuest::STATUS_ACTIVE): BookingChild
    {
        return BookingChild::create([
            'booking_id' => $this->room->booking_id, 'booking_room_id' => $this->room->id,
            'full_name' => $name, 'dob' => $dob, 'age_group' => $group, 'child_status' => $status,
        ]);
    }

    public function test_birthdays_tab_lists_children_and_babies_in_range(): void
    {
        $this->child('Child 1', '2007-09-06');
        $this->child('Baby 1', '2025-09-05', 'baby');

        $rows = collect($this->getJson('/api/shift-work/birthdays?from_date=2026-09-04&to_date=2026-09-07')->assertOk()->json('data'));

        $this->assertEqualsCanonicalizing(['Child 1', 'Baby 1'], $rows->pluck('guestName')->all());
        $child = $rows->firstWhere('guestName', 'Child 1');
        $this->assertSame('06/09/2007', $child['birthday']);
        $this->assertSame('06/09/2026', $child['birthdayDate']);
        $this->assertSame(19, $child['age']);
        $this->assertSame('203', $child['roomNumber']);
    }

    public function test_birthdays_tab_skips_children_without_dob_outside_range_or_checked_out(): void
    {
        $this->child('Không ngày sinh', null);
        $this->child('Ngoài kỳ', '2010-10-10');
        $this->child('Đã trả', '2007-09-06', 'child', BookingRoomGuest::STATUS_CHECKED_OUT);

        $rows = $this->getJson('/api/shift-work/birthdays?from_date=2026-09-04&to_date=2026-09-07')->assertOk()->json('data');

        $this->assertCount(0, $rows);
    }

    // Dòng 530: ngày sinh trong kỳ xem nhưng ngoài thời gian ở của phòng không được hiển thị.
    public function test_birthdays_tab_requires_birthday_to_be_within_the_room_stay(): void
    {
        $adult = Guest::create(['full_name' => 'Khách trước ngày đến', 'dob' => '1990-09-02']);
        BookingRoomGuest::create([
            'booking_room_id' => $this->room->id,
            'guest_id' => $adult->id,
            'status' => BookingRoomGuest::STATUS_ACTIVE,
        ]);
        $this->child('Trước ngày đến', '2007-09-02');
        $this->child('Đúng ngày đi', '2007-09-07');

        $rows = collect($this->getJson('/api/shift-work/birthdays?from_date=2026-09-01&to_date=2026-09-10')
            ->assertOk()
            ->json('data'));

        $this->assertNull($rows->firstWhere('guestName', 'Khách trước ngày đến'));
        $this->assertNull($rows->firstWhere('guestName', 'Trước ngày đến'));
        $this->assertNotNull($rows->firstWhere('guestName', 'Đúng ngày đi'));
    }

    public function test_multiple_guests_with_different_birth_years_share_same_birthday_date_in_view_period(): void
    {
        $guest1995 = Guest::create(['full_name' => 'Guest 1', 'dob' => '1995-09-03']);
        $guest1997 = Guest::create(['full_name' => 'Guest 2', 'dob' => '1997-09-03']);
        BookingRoomGuest::create([
            'booking_room_id' => $this->room->id,
            'guest_id' => $guest1995->id,
            'status' => BookingRoomGuest::STATUS_ACTIVE,
        ]);
        BookingRoomGuest::create([
            'booking_room_id' => $this->room->id,
            'guest_id' => $guest1997->id,
            'status' => BookingRoomGuest::STATUS_ACTIVE,
        ]);
        $this->child('Child 2009', '2009-09-03');

        // Phòng ở từ 2026-09-01 đến 2026-09-07, kỳ xem từ 2026-09-02 đến 2026-09-05
        $this->room->update(['arrival_date' => '2026-09-01', 'departure_date' => '2026-09-07']);

        $rows = collect($this->getJson('/api/shift-work/birthdays?from_date=2026-09-02&to_date=2026-09-05')
            ->assertOk()
            ->json('data'));

        $matching = $rows->whereIn('guestName', ['Guest 1', 'Guest 2', 'Child 2009']);
        $this->assertCount(3, $matching);
        foreach ($matching as $item) {
            $this->assertSame('03/09/2026', $item['birthdayDate']);
        }
    }
}
