<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingRoomGuest;
use App\Models\Guest;
use App\Models\HotelConfig;
use App\Models\HotelSetting;
use App\Models\RoomClass;
use App\Models\User;
use App\Support\StandardStayTimes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cấu hình hệ thống › Định nghĩa khách sạn › Thông tin khách sạn:
 * giờ nhận phòng / trả phòng tiêu chuẩn dùng làm giờ mặc định của phòng mới.
 */
class StandardStayTimesTest extends TestCase
{
    use RefreshDatabase;

    private RoomClass $roomClass;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\BookingStatusSeeder::class);
        $this->actingAs(User::factory()->withRole('super_admin')->create(['username' => 'stay_time_user']));
        HotelSetting::query()->firstOrCreate([], ['hotel_name' => 'Test Hotel']);
        $this->roomClass = RoomClass::create(['code' => 'STT', 'name' => 'Stay time', 'is_active' => true]);
    }

    private function createRoom(array $attributes = []): BookingRoom
    {
        $booking = Booking::create([
            'booking_name' => 'Stay time', 'arrival_date' => '2026-08-09', 'departure_date' => '2026-08-11',
            'num_of_days' => 2, 'booking_date' => '2026-08-09', 'created_by' => 'stay_time_user', 'status' => Booking::STATUS_RESERVATION,
        ]);

        return BookingRoom::create(array_merge([
            'booking_id' => $booking->id, 'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-09', 'departure_date' => '2026-08-11',
            'status' => BookingRoom::STATUS_BOOKED, 'rate' => 500000,
        ], $attributes));
    }

    public function test_defaults_stay_14_and_12_when_not_configured(): void
    {
        $this->assertSame('14:00:00', StandardStayTimes::checkIn());
        $this->assertSame('12:00:00', StandardStayTimes::checkOut());

        $room = $this->createRoom()->fresh();
        $this->assertSame('14:00', substr((string) $room->arrival_time, 0, 5));
        $this->assertSame('12:00', substr((string) $room->departure_time, 0, 5));
        $this->assertSame('12:00', substr((string) $room->CheckoutTime, 0, 5));

        $data = $this->getJson('/api/hotel-settings')->assertOk()->json('data');
        $this->assertSame('14:00', $data['StandardCheckInTime']);
        $this->assertSame('12:00', $data['StandardCheckOutTime']);
    }

    private function setConfig(string $name, string $value)
    {
        $id = HotelConfig::where('name', $name)->value('id');

        return $this->putJson("/api/hotel-configs/{$id}", ['name' => $name, 'value' => $value]);
    }

    public function test_configured_times_are_saved_in_config_tab_and_used_for_new_rooms_and_guests(): void
    {
        // Migration tạo sẵn 2 thông số ở tab Cấu Hình.
        $this->assertSame('14:00:00', HotelConfig::where('name', 'StandardCheckInTime')->value('value'));
        $this->assertSame('12:00:00', HotelConfig::where('name', 'StandardCheckOutTime')->value('value'));

        $this->setConfig('StandardCheckInTime', '13:30')->assertSuccessful();
        $this->setConfig('StandardCheckOutTime', '11:00')->assertSuccessful();

        $data = $this->getJson('/api/hotel-settings')->assertOk()->json('data');
        $this->assertSame('13:30', $data['StandardCheckInTime']);
        $this->assertSame('11:00', $data['StandardCheckOutTime']);

        $room = $this->createRoom()->fresh();
        $this->assertSame('13:30', substr((string) $room->arrival_time, 0, 5));
        $this->assertSame('11:00', substr((string) $room->departure_time, 0, 5));
        $this->assertSame('11:00', substr((string) $room->CheckoutTime, 0, 5));

        $guest = Guest::create(['full_name' => 'Khach gio chuan']);
        $pivot = BookingRoomGuest::create(['booking_room_id' => $room->id, 'guest_id' => $guest->id, 'is_primary' => true]);
        $this->assertSame('11:00', substr((string) $pivot->fresh()->actual_checkout_time, 0, 5));
    }

    public function test_user_entered_times_and_existing_rooms_are_not_overwritten(): void
    {
        $existing = $this->createRoom()->fresh();

        $this->setConfig('StandardCheckInTime', '15:00')->assertSuccessful();
        $this->setConfig('StandardCheckOutTime', '11:30')->assertSuccessful();

        $this->assertSame('14:00', substr((string) $existing->fresh()->arrival_time, 0, 5));
        $this->assertSame('12:00', substr((string) $existing->fresh()->departure_time, 0, 5));

        $manual = $this->createRoom(['arrival_time' => '09:15', 'departure_time' => '18:45'])->fresh();
        $this->assertSame('09:15', substr((string) $manual->arrival_time, 0, 5));
        $this->assertSame('18:45', substr((string) $manual->departure_time, 0, 5));
    }

    public function test_invalid_time_is_rejected_and_hotel_info_save_does_not_touch_times(): void
    {
        $this->setConfig('StandardCheckInTime', '25:00')->assertStatus(422)->assertJsonValidationErrors('value');
        $this->assertSame('14:00:00', StandardStayTimes::checkIn());

        // Lưu Thông tin khách sạn (dù có gửi kèm) không còn đổi giờ chuẩn.
        $this->setConfig('StandardCheckOutTime', '10:00')->assertSuccessful();
        $this->putJson('/api/hotel-settings', ['hotel_name' => 'Test Hotel', 'StandardCheckOutTime' => '12:00'])->assertSuccessful();
        $this->assertSame('10:00:00', StandardStayTimes::checkOut());

        HotelConfig::where('name', 'StandardCheckInTime')->update(['value' => 'abc']);
        $this->assertSame('14:00:00', StandardStayTimes::checkIn());
    }
}
