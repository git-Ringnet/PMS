<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\CustomerSource;
use App\Models\RegistrationStatus;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\RoomLock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoomOccupancyStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_room_map_statistics_follow_business_rules(): void
    {
        $this->actingAs(User::factory()->create());

        DB::table('booking_statuses')->insert([
            ['id' => 0, 'name' => 'Reservation'],
            ['id' => 1, 'name' => 'Checked In'],
            ['id' => 2, 'name' => 'Checked Out'],
        ]);

        $availableStatus = RegistrationStatus::create(['name' => 'Confirmed', 'is_availability' => true]);
        $ignoredStatus = RegistrationStatus::create(['name' => 'Waiting', 'is_availability' => false]);
        $walkInSource = CustomerSource::create(['code' => 'WALKIN', 'name' => 'Khách vãng lai']);
        $form = RoomForm::create(['name' => 'Double']);
        $class = RoomClass::create(['code' => 'DLX', 'name' => 'Deluxe', 'is_active' => true]);

        foreach (['101', '102', '103', '105', '106'] as $roomNumber) {
            Room::create([
                'room_number' => $roomNumber,
                'room_form_id' => $form->id,
                'room_class_id' => $class->id,
                'floor' => 1,
                'room_status_code' => 'vacant_ready',
            ]);
        }
        // Phòng thật ở tầng trệt vẫn phải được tính nếu số phòng không bắt đầu bằng 0.
        Room::create([
            'room_number' => '104',
            'room_form_id' => $form->id,
            'room_class_id' => $class->id,
            'floor' => 0,
            'grid_row' => 0,
            'grid_column' => 0,
            'is_internal' => false,
        ]);
        Room::create([
            'room_number' => '900',
            'room_form_id' => $form->id,
            'room_class_id' => $class->id,
            'floor' => 9,
            'is_internal' => true,
        ]);
        Room::create([
            'room_number' => '001',
            'room_form_id' => $form->id,
            'room_class_id' => $class->id,
            'floor' => 1,
            'is_internal' => false,
        ]);

        $this->createLock('103', 'OOO');
        $this->createLock('104', 'OOS');

        $occupiedRoom = $this->createBookingRoom(
            '101',
            $class->id,
            $availableStatus->id,
            BookingRoom::STATUS_CHECKED_IN
        );
        $occupiedRoom->update([
            'actual_arrival_date' => '2026-09-04',
            'planned_departure_date' => '2026-09-04',
            'NumOfDays' => 3,
        ]);
        $occupiedRoom->update(['departure_date' => '2026-09-08']);
        $this->assertSame('2026-09-04', $occupiedRoom->fresh()->planned_departure_date->toDateString());
        $this->assertSame(3, $occupiedRoom->fresh()->NumOfDays);

        // Các booking này không được tính vì tình trạng đăng ký hoặc loại phòng không hợp lệ.
        $this->createBookingRoom('102', $class->id, $ignoredStatus->id, BookingRoom::STATUS_CHECKED_IN);
        $this->createBookingRoom('900', $class->id, $availableStatus->id, BookingRoom::STATUS_CHECKED_IN);
        $this->createBookingRoom('001', $class->id, $availableStatus->id, BookingRoom::STATUS_CHECKED_IN);

        $arrivalBooking = Booking::create([
            'booking_name' => 'Arrival today',
            'booking_date' => '2026-09-04',
            'arrival_date' => '2026-09-04',
            'departure_date' => '2026-09-06',
            'num_of_days' => 2,
            'status' => Booking::STATUS_RESERVATION,
            'registration_status_id' => $availableStatus->id,
            'customer_source_id' => $walkInSource->id,
            'created_by' => 'room_stats_test',
        ]);
        BookingRoom::create([
            'booking_id' => $arrivalBooking->id,
            'room_number' => '105',
            'room_class_id' => $class->id,
            'arrival_date' => '2026-09-04',
            'departure_date' => '2026-09-06',
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        // Reservation hợp lệ chưa gán số phòng vẫn phải tính vào Phòng đến và dự kiến cuối ngày.
        $unassignedBooking = Booking::create([
            'booking_name' => 'Unassigned arrival today',
            'booking_date' => '2026-09-01',
            'arrival_date' => '2026-09-04',
            'departure_date' => '2026-09-06',
            'num_of_days' => 2,
            'status' => Booking::STATUS_RESERVATION,
            'registration_status_id' => $availableStatus->id,
            'created_by' => 'room_stats_test',
        ]);
        BookingRoom::create([
            'booking_id' => $unassignedBooking->id,
            'room_number' => null,
            'room_class_id' => $class->id,
            'arrival_date' => '2026-09-04',
            'departure_date' => '2026-09-06',
            'adults' => 2,
            'status' => BookingRoom::STATUS_BOOKED,
        ]);
        $dayUseBooking = Booking::create([
            'booking_name' => 'Day use today',
            'booking_date' => '2026-09-04',
            'arrival_date' => '2026-09-04',
            'departure_date' => '2026-09-04',
            'num_of_days' => 1,
            'is_day_use' => true,
            'status' => Booking::STATUS_CHECKOUT,
            'registration_status_id' => $availableStatus->id,
            'created_by' => 'room_stats_test',
        ]);
        BookingRoom::create([
            'booking_id' => $dayUseBooking->id,
            'room_number' => '106',
            'room_class_id' => $class->id,
            'arrival_date' => '2026-09-04',
            'departure_date' => '2026-09-04',
            'planned_departure_date' => '2026-09-04',
            'NumOfDays' => 1,
            'CheckoutDate' => '2026-09-04',
            'is_day_use' => true,
            'status' => BookingRoom::STATUS_CHECKED_OUT,
        ]);

        $earlyBooking = Booking::create([
            'booking_name' => 'Early checkout',
            'booking_date' => '2026-09-01',
            'arrival_date' => '2026-09-01',
            'departure_date' => '2026-09-04',
            'num_of_days' => 3,
            'status' => Booking::STATUS_CHECKOUT,
            'registration_status_id' => $availableStatus->id,
            'created_by' => 'room_stats_test',
        ]);
        BookingRoom::create([
            'booking_id' => $earlyBooking->id,
            'room_number' => '102',
            'room_class_id' => $class->id,
            'arrival_date' => '2026-09-01',
            'departure_date' => '2026-09-04',
            'planned_departure_date' => '2026-09-06',
            'NumOfDays' => 5,
            'CheckoutDate' => '2026-09-04',
            'status' => BookingRoom::STATUS_CHECKED_OUT,
        ]);

        $response = $this->getJson('/api/rooms/stats?date=2026-09-04')->assertSuccessful();

        $response->assertJsonPath('data.total_rooms', 6)
            ->assertJsonPath('data.ooo', 1)
            ->assertJsonPath('data.oos', 1)
            ->assertJsonPath('data.saleable_rooms', 4)
            ->assertJsonPath('data.occupancy_base_rooms', 5)
            ->assertJsonPath('data.occupied_current', 1)
            ->assertJsonPath('data.occupied_current_pax', 1)
            ->assertJsonPath('data.occupied_projected', 3)
            ->assertJsonPath('data.occupied_projected_pax', 4)
            ->assertJsonPath('data.vacant_projected', 1)
            ->assertJsonPath('data.occupancy_rate', 60)
            ->assertJsonPath('data.arrivals_checked_in', 2)
            ->assertJsonPath('data.arrivals_checked_in_pax', 2)
            ->assertJsonPath('data.arrivals_pending', 2)
            ->assertJsonPath('data.arrivals_pending_pax', 3)
            ->assertJsonPath('data.arrivals_total', 4)
            ->assertJsonPath('data.arrivals_total_pax', 5)
            ->assertJsonPath('data.arrivals_assigned', 3)
            ->assertJsonPath('data.arrivals_assigned_pax', 3)
            ->assertJsonPath('data.departures_checked_out', 2)
            ->assertJsonPath('data.departures_checked_out_pax', 2)
            ->assertJsonPath('data.departures_pending', 0)
            ->assertJsonPath('data.departures_total', 2)
            ->assertJsonPath('data.early_departures', 1)
            ->assertJsonPath('data.extended_stays', 1)
            ->assertJsonPath('data.day_rooms', 1)
            ->assertJsonPath('data.same_day_reservations', 2)
            ->assertJsonPath('data.same_day_reservations_pax', 2)
            ->assertJsonPath('data.walk_in_rooms', 1)
            ->assertJsonPath('data.walk_in_pax', 1);

        // Ca biên: toàn bộ phòng là OOO thì mẫu số và công suất phải về 0, không chia cho 0.
        RoomLock::where('room_number', '104')->delete();
        foreach (['101', '102', '104', '105', '106'] as $roomNumber) {
            $this->createLock($roomNumber, 'OOO');
        }

        $this->getJson('/api/rooms/stats?date=2026-09-04')
            ->assertSuccessful()
            ->assertJsonPath('data.ooo', 6)
            ->assertJsonPath('data.saleable_rooms', 0)
            ->assertJsonPath('data.occupancy_base_rooms', 0)
            ->assertJsonPath('data.occupied_projected', 0)
            ->assertJsonPath('data.vacant_projected', 0)
            ->assertJsonPath('data.occupancy_rate', 0);
    }

    private function createBookingRoom(
        string $roomNumber,
        int $roomClassId,
        int $registrationStatusId,
        int $status
    ): BookingRoom {
        $booking = Booking::create([
            'booking_name' => 'Booking ' . $roomNumber,
            'booking_date' => '2026-09-01',
            'arrival_date' => '2026-09-01',
            'departure_date' => '2026-09-06',
            'num_of_days' => 5,
            'status' => $status,
            'registration_status_id' => $registrationStatusId,
            'created_by' => 'room_stats_test',
        ]);

        return BookingRoom::create([
            'booking_id' => $booking->id,
            'room_number' => $roomNumber,
            'room_class_id' => $roomClassId,
            'arrival_date' => '2026-09-01',
            'departure_date' => '2026-09-06',
            'status' => $status,
        ]);
    }

    private function createLock(string $roomNumber, string $lockType): RoomLock
    {
        return RoomLock::create([
            'room_number' => $roomNumber,
            'start_date' => '2026-09-04 00:00:00',
            'end_date' => '2026-09-04 23:59:59',
            'lock_type' => $lockType,
            'is_active' => RoomLock::STATUS_ACTIVE,
        ]);
    }
}