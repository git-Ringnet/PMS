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
     * Test Booking create log resolves booking_code, component 'Tạo đăng ký', and displays rich description without VAT.
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
        $this->assertEquals('Tạo đăng ký', $resource['component']);
        $this->assertEquals('Add', $resource['action']);
        $this->assertStringContainsString('Thông Tin Đăng Ký GAL7', $resource['description']);
        $this->assertStringNotContainsString('-VAT :', $resource['description']);
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
        $this->assertStringContainsString('Cập Nhật Thông Tin Đăng Ký GAL7', $resource['description']);
        $this->assertStringContainsString('Trạng thái : Pending -> Guaranteed', $resource['description']);
        $this->assertStringContainsString('Ngày Đi : 12-10-2026 -> 15-10-2026', $resource['description']);
        $this->assertStringContainsString('Tiền cọc : 0 -> 500,000', $resource['description']);
    }

    /**
     * Test Payment log resolves component 'Trả phòng' and correct description.
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
        $this->assertEquals('Trả phòng', $resource['component']);
        $this->assertStringContainsString('Correct Đặt Cọc Cho Đăng Ký: GAL7', $resource['description']);
        $this->assertStringContainsString('Số tiền: 500,000', $resource['description']);
    }

    /**
     * Test RoomSchedule component and Room G code resolution (Rule 1 & Hàng 3-13).
     */
    public function test_room_schedule_and_room_code_resolution()
    {
        $log = new ActivityLog([
            'id' => 9995,
            'action' => 'create',
            'module' => 'reservation',
            'component' => 'RoomSchedule',
            'target_type' => 'BookingRoom',
            'target_id' => 'G0000043',
            'target_label' => 'GAL20',
            'new_values' => [
                'id' => 'G0000043',
                'booking_id' => 20,
                'room_number' => '605',
                'price' => 540000,
                'room_class' => 'Superior Double',
            ],
            'description' => '* Phòng thuê : # Thêm mới : Mã: G0000043 - Giá: 540,000 - Loại: Superior Double - Dạng: Double - Phòng: 605',
        ]);

        $resource = (new ActivityLogResource($log))->resolve();

        $this->assertEquals('GAL20', $resource['booking_code']);
        $this->assertEquals('G0000043', $resource['room_number']);
        $this->assertEquals('RoomSchedule', $resource['component']);
    }

    /**
     * Test Report View resolution (Rule 2 & Hàng 71).
     */
    public function test_report_view_resolution()
    {
        $log = new ActivityLog([
            'id' => 9996,
            'action' => 'View',
            'module' => 'reports',
            'component' => 'report-definitions',
            'target_type' => 'ReportDefinition',
            'target_id' => '1',
            'target_label' => 'Báo cáo hủy đăng ký',
            'new_values' => [
                'parameters' => [
                    'from_date' => '2026-09-04',
                    'to_date' => '2026-09-04',
                ],
            ],
            'description' => 'Thêm mới report-definitions \'auth_token\'',
        ]);

        $resource = (new ActivityLogResource($log))->resolve();

        $this->assertEquals('Báo cáo', $resource['component']);
        $this->assertEquals('Xem báo cáo: "Báo cáo hủy đăng ký"  - Từ ngày 04-09-2026 , - Đến ngày 04-09-2026', $resource['description']);
    }

    /**
     * Test RoomLock resolution (Hàng 5, 6, 103).
     */
    public function test_room_lock_resolution()
    {
        $logAdd = new ActivityLog([
            'id' => 9997,
            'action' => 'Add',
            'module' => 'reservation',
            'component' => 'RoomSchedule',
            'target_type' => 'RoomLock',
            'target_id' => '601',
            'target_label' => '601',
            'new_values' => [
                'room_number' => '601',
                'lock_type' => 'OOO',
                'from_date' => '2026-09-03',
                'to_date' => '2026-09-03',
                'reason' => 'thay tivi',
            ],
            'description' => 'Khóa phòng 601',
        ]);

        $resAdd = (new ActivityLogResource($logAdd))->resolve();
        $this->assertEquals('RoomSchedule', $resAdd['component']);
        $this->assertEquals('601', $resAdd['room_number']);
        $this->assertEquals('Thêm Phòng OOO. Phòng 601 đã bị khóa từ 03-09-2026 đến 03-09-2026 - Lý do: thay tivi', $resAdd['description']);

        $logModify = new ActivityLog([
            'id' => 9998,
            'action' => 'Modify',
            'module' => 'reservation',
            'component' => 'Khóa phòng',
            'target_type' => 'RoomLock',
            'target_id' => '894',
            'target_label' => '602',
            'old_values' => [
                'to_date' => '2026-08-20',
                'description' => 'khóa',
            ],
            'new_values' => [
                'to_date' => '2026-08-21',
                'description' => 'khóa s',
                'lock_type' => 'OOO',
                'room_number' => '602',
            ],
            'description' => 'Cập nhật hàng loạt phòng khóa 103',
        ]);

        $resMod = (new ActivityLogResource($logModify))->resolve();
        $this->assertEquals('Khóa phòng', $resMod['component']);
        $this->assertStringContainsString('Cập nhật phòng khóa OOO mã 894, phòng 602:', $resMod['description']);
    }

    /**
     * Test Payment and Deposit resolution (Hàng 19, 22, 23).
     */
    public function test_payment_and_deposit_resolution()
    {
        $logDeposit = new ActivityLog([
            'id' => 9999,
            'action' => 'Add',
            'module' => 'reservation',
            'component' => 'Tạo đăng ký',
            'target_type' => 'Payment',
            'target_id' => '1',
            'target_label' => 'GAL22',
            'new_values' => [
                'booking_id' => 22,
                'payment_method' => 'BT',
                'amount' => 5000000,
                'description' => 'Deposit (Bank transfer)',
            ],
            'description' => '* Thanh toán cho Đăng ký GAL22: Số tiền: 0 đ',
        ]);

        $resDep = (new ActivityLogResource($logDeposit))->resolve();
        $this->assertEquals('Tạo đăng ký', $resDep['component']);
        $this->assertEquals('GAL22', $resDep['booking_code']);
        $this->assertEquals('* Thêm Đặt Cọc Cho Đăng Ký: GAL22 - Tên Booking: Test ghi log - PaymentMethod: BT - Số Tiền: 5,000,000 - Mô tả: Deposit (Bank transfer)', $resDep['description']);
    }

    /**
     * Test Alert / BookingNotification resolution (Hàng 45, 46, 47).
     */
    public function test_booking_alert_resolution()
    {
        $logAlert = new ActivityLog([
            'id' => 10000,
            'action' => 'create',
            'module' => 'reservation',
            'component' => 'Tạo đăng ký',
            'target_type' => 'BookingNotification',
            'target_id' => '1',
            'target_label' => 'GAL27',
            'new_values' => [
                'booking_id' => 27,
                'message' => 'Khách Khó',
                'department' => 'FO',
                'starts_on' => '2026-09-08',
                'ends_on' => '2026-09-17',
            ],
            'description' => '* Thêm mới Quản lý Đặt phòng BookingNotification',
        ]);

        $resAlert = (new ActivityLogResource($logAlert))->resolve();
        $this->assertEquals('Tạo đăng ký', $resAlert['component']);
        $this->assertEquals('GAL27', $resAlert['booking_code']);
        $this->assertStringContainsString('* Tạo Alert cho mã đăng ký GAL27', $resAlert['description']);
        $this->assertStringContainsString('Nội dung cảnh báo: Khách Khó', $resAlert['description']);
    }

    /**
     * Test Room Status resolution (Hàng 48, 97).
     */
    public function test_room_status_resolution()
    {
        $logStatus = new ActivityLog([
            'id' => 10001,
            'action' => 'Modify',
            'module' => 'frontdesk',
            'component' => 'Room map',
            'target_type' => 'Room',
            'target_id' => '101',
            'target_label' => '101',
            'old_values' => ['status_name' => 'Phòng chưa dọn'],
            'new_values' => ['status_name' => 'Phòng sẵn sàng', 'room_number' => '101'],
            'description' => '* Cập nhật trạng thái buồng phòng (101)',
        ]);

        $resStatus = (new ActivityLogResource($logStatus))->resolve();
        $this->assertEquals('Room map', $resStatus['component']);
        $this->assertEquals('* Phòng 101. Đổi trạng thái từ: Phòng chưa dọn -> Phòng sẵn sàng', $resStatus['description']);
    }

    /**
     * Test Checkout resolution (Hàng 62).
     */
    public function test_checkout_resolution()
    {
        $logCheckout = new ActivityLog([
            'id' => 10002,
            'action' => 'CheckOut',
            'module' => 'frontdesk',
            'component' => 'Trả phòng',
            'target_type' => 'BookingRoom',
            'target_id' => 'G0000028',
            'target_label' => 'GAL16',
            'new_values' => ['room_number' => '405'],
            'description' => '* Check out cho phòng 405',
        ]);

        $resCheckout = (new ActivityLogResource($logCheckout))->resolve();
        $this->assertEquals('Trả phòng', $resCheckout['component']);
        $this->assertEquals('GAL16', $resCheckout['booking_code']);
        $this->assertEquals('G0000028', $resCheckout['room_number']);
        $this->assertEquals('* Check Out Cho Đăng Ký GAL16 - Phòng: 405 (G0000028)', $resCheckout['description']);
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

    /**
     * Test booking creation with complex nested array/object new_values (no Array to string conversion error).
     */
    public function test_booking_creation_with_nested_objects_in_new_values()
    {
        $log = new ActivityLog([
            'id' => 9999,
            'action' => 'Add',
            'module' => 'reservation',
            'component' => 'Tạo đăng ký',
            'target_type' => 'Booking',
            'target_id' => '1',
            'target_label' => 'GAL1',
            'description' => 'Tạo mới Booking GAL1',
            'new_values' => [
                'booking_name' => 'ae',
                'arrival_date' => '2026-08-29T17:00:00.000000Z',
                'departure_date' => '2026-08-30T17:00:00.000000Z',
                'company' => ['id' => 3, 'name' => 'Công ty TNHH Saigontourist'],
                'market' => ['id' => 4, 'name' => 'Đại lý lữ hành (Travel Agent)'],
                'customer_source' => ['id' => 7, 'name' => 'Traveloka'],
                'booker' => ['id' => 4, 'name' => 'Phạm Thị D', 'phone' => '0934567890'],
                'booking_rooms' => [
                    [
                        'id' => 'G0000001',
                        'room_class' => ['name' => 'Superior Double'],
                        'price' => '540000.00',
                        'room_number' => '101',
                        'adults' => 2,
                        'children' => 0,
                        'breakfast' => true,
                    ]
                ]
            ],
        ]);

        $res = (new ActivityLogResource($log))->toArray(Request::create('/api/activity-logs', 'GET'));
        $this->assertStringContainsString('Công ty TNHH Saigontourist', $res['description']);
        $this->assertStringContainsString('0934567890', $res['description']);
        $this->assertStringContainsString('540,000', $res['description']);
        $this->assertStringContainsString('G0000001', $res['description']);
    }
}
