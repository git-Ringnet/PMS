<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingRoomService;
use App\Models\ServiceBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dòng 458: dịch vụ đã chuyển bill đi nơi khác vẫn là dịch vụ phát sinh gốc của phòng,
 * màn Đăng ký lấy qua quan hệ transferredOutServices; dòng bị xóa/hủy thật không được hồi sinh.
 */
class RegistrationTransferredServicesRow458Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'BookingStatusSeeder']);
    }

    private function room(): BookingRoom
    {
        $this->actingAs(User::factory()->create());
        $booking = Booking::create([
            'booking_name' => 'ROW458', 'arrival_date' => '2026-09-10', 'departure_date' => '2026-09-12',
            'num_of_days' => 2, 'booking_date' => '2026-09-10', 'created_by' => 'test',
        ]);

        return BookingRoom::create(['booking_id' => $booking->id, 'arrival_date' => '2026-09-10', 'departure_date' => '2026-09-12', 'status' => 1]);
    }

    private function bill(BookingRoom $room, int $status, int $edit, string $code = 'EP', float $amount = 200000): ServiceBill
    {
        $bill = new ServiceBill();
        $bill->forceFill([
            'Date' => '2026-09-10 00:00:00', 'OpenTime' => '00:00', 'Guest' => 'Guest', 'Username' => 'test', 'ServiceId' => $code, 'Amount' => $amount, 'Quantity' => 1, 'DepartmentId' => 'FO',
            'RegisterId1' => $room->booking_id, 'RentalRoomId1' => $room->id, 'Status' => $status, 'Edit' => $edit,
        ])->save();

        return $bill;
    }

    private function service(BookingRoom $room, ServiceBill $bill, string $code = 'EP'): BookingRoomService
    {
        $service = new BookingRoomService([
            'booking_room_id' => $room->id, 'service_bill_id' => $bill->Ma, 'service_code' => $code, 'service_name' => $code,
            'service_date' => '2026-09-10', 'quantity' => 1, 'rate' => 200000, 'total_amount' => 200000, 'is_posted' => 1, 'folio' => 1,
        ]);
        $service->preserveTotalAmount = true;
        $service->save();

        return $service;
    }

    public function test_service_moved_out_to_master_is_still_returned_for_the_origin_room(): void
    {
        $room = $this->room();
        $oldBill = $this->bill($room, 4, 1);
        $this->bill($room, 1, 0); // bill mới còn hiệu lực sau khi chuyển
        $service = $this->service($room, $oldBill);
        $service->delete();

        $this->assertSame([$service->id], $room->transferredOutServices()->pluck('id')->all());
        $this->assertCount(0, $room->services()->get());
    }

    public function test_cancelled_service_is_not_resurrected(): void
    {
        $room = $this->room();
        $cancelledBill = $this->bill($room, 3, 1);
        $this->service($room, $cancelledBill)->delete();

        $this->assertCount(0, $room->transferredOutServices()->get());
    }

    public function test_moved_out_service_without_a_live_replacement_bill_is_not_returned(): void
    {
        $room = $this->room();
        $oldBill = $this->bill($room, 4, 1);
        $this->service($room, $oldBill)->delete();

        $this->assertCount(0, $room->transferredOutServices()->get());
    }
}
