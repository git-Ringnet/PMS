<?php

namespace Tests\Feature;

use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\HotelConfig;
use App\Models\Room;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ActivityLogEnhancementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Test HotelConfig log does not set room_number or booking_code, and has detailed description.
     */
    public function test_hotel_config_log_resolution()
    {
        $log = new ActivityLog([
            'id' => 9991,
            'action' => 'update',
            'module' => 'config',
            'component' => 'hotel-configs',
            'target_type' => 'HotelConfig',
            'target_id' => '43',
            'target_label' => 'AllowInputOverAV',
            'old_values' => ['config_value' => '0'],
            'new_values' => ['config_value' => '1'],
            'description' => 'Cập nhật dữ liệu trên hotel-configs',
        ]);

        $resource = (new ActivityLogResource($log))->resolve();

        $this->assertNull($resource['booking_code']);
        $this->assertNull($resource['room_number']);
        $this->assertEquals('Cấu hình Khách sạn', $resource['component']);
        $this->assertEquals('Cài đặt', $resource['module_label']);
        $this->assertStringContainsString("cấu hình khách sạn 'AllowInputOverAV': 0 ➜ 1", $resource['description']);
    }

    /**
     * Test Booking create log resolves booking_code and displays rich description.
     */
    public function test_booking_create_log_resolution()
    {
        $log = new ActivityLog([
            'id' => 9992,
            'action' => 'create',
            'module' => 'reservation',
            'component' => 'bookings',
            'target_type' => 'Booking',
            'target_id' => '7',
            'target_label' => 'GAL7',
            'old_values' => null,
            'new_values' => [
                'id' => 7,
                'booking_name' => 'Khách VIP Nguyễn Văn A',
                'arrival_date' => '2026-10-10',
                'departure_date' => '2026-10-12',
                'num_of_days' => 2,
                'total_amount' => 1500000,
                'deposit_amount' => 500000,
            ],
            'description' => 'Tạo mới dữ liệu trên bookings',
        ]);

        $resource = (new ActivityLogResource($log))->resolve();

        $this->assertEquals('GAL7', $resource['booking_code']);
        $this->assertNull($resource['room_number']);
        $this->assertEquals('Booking', $resource['component']);
        $this->assertEquals('Add', $resource['action']);
        $this->assertStringContainsString('Thông Tin Đăng Ký 7', $resource['description']);
        $this->assertStringContainsString('Khách VIP Nguyễn Văn A', $resource['description']);
    }

    /**
     * Test Booking update log resolves diff cleanly with Vietnamese labels.
     */
    public function test_booking_update_log_resolution()
    {
        $log = new ActivityLog([
            'id' => 9993,
            'action' => 'update',
            'module' => 'reservation',
            'component' => 'bookings',
            'target_type' => 'Booking',
            'target_id' => '7',
            'target_label' => 'GAL7',
            'old_values' => [
                'status' => 'Pending',
                'departure_date' => '2026-10-12',
                'deposit_amount' => 0,
            ],
            'new_values' => [
                'status' => 'Guaranteed',
                'departure_date' => '2026-10-15',
                'deposit_amount' => 500000,
            ],
            'description' => 'Cập nhật dữ liệu trên bookings',
        ]);

        $resource = (new ActivityLogResource($log))->resolve();

        $this->assertEquals('GAL7', $resource['booking_code']);
        $this->assertNull($resource['room_number']);
        $this->assertEquals('Modify', $resource['action']);
        $this->assertStringContainsString('Cập Nhật Thông Tin Đăng Ký 7', $resource['description']);
        $this->assertStringContainsString('Trạng thái : Pending -> Guaranteed', $resource['description']);
        $this->assertStringContainsString('Ngày đi : 12-10-2026 -> 15-10-2026', $resource['description']);
        $this->assertStringContainsString('Tiền cọc : 0 đ -> 500.000 đ', $resource['description']);
    }

    /**
     * Test Payment log resolves booking_code and payment amount description.
     */
    public function test_payment_log_resolution()
    {
        $log = new ActivityLog([
            'id' => 9994,
            'action' => 'delete',
            'module' => 'frontdesk',
            'component' => 'payments',
            'target_type' => 'Payment',
            'target_id' => '12',
            'target_label' => null,
            'old_values' => [
                'booking_id' => 7,
                'amount' => 500000,
                'description' => 'Thu cọc tiền mặt',
            ],
            'new_values' => null,
            'description' => 'Xóa dữ liệu trên payments',
        ]);

        $resource = (new ActivityLogResource($log))->resolve();

        $this->assertEquals('GAL7', $resource['booking_code']);
        $this->assertNull($resource['room_number']);
        $this->assertEquals('Thu ngân & Thanh toán', $resource['component']);
        $this->assertStringContainsString('Xóa khoản thanh toán của Đăng ký GAL7: Số tiền: 500.000 đ', $resource['description']);
    }

    /**
     * Test ActivityLogService marks _activity_logged to prevent double-logging.
     */
    public function test_activity_log_service_marks_request_flag()
    {
        $request = Request::create('/api/test-log', 'POST');
        $this->app->instance('request', $request);

        $this->assertNull($request->attributes->get('_activity_logged'));

        ActivityLogService::log([
            'action' => 'test',
            'module' => 'test',
            'component' => 'TestComp',
            'description' => 'Test log description',
        ]);

        $this->assertTrue($request->attributes->get('_activity_logged'));
    }
}
