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

    /**
     * Test Row 20 & 21: Tách cọc & Chuyển cọc.
     */
    public function test_deposit_split_and_transfer_logging()
    {
        // Row 20: Tách cọc
        $splitLog = new ActivityLog([
            'id' => 10001,
            'action' => 'Modify',
            'module' => 'frontdesk',
            'component' => 'payments',
            'target_type' => 'Payment',
            'target_id' => '12',
            'request_url' => 'http://localhost/api/payments/12/split',
            'new_values' => [
                'total_amount_before_split' => 5000000,
                'amount' => 2500000,
                'folio_id' => 1,
            ],
            'description' => 'Tách cọc',
        ]);
        $splitRes = (new ActivityLogResource($splitLog))->resolve();
        $this->assertEquals('* Deposit (Bank transfer) số tiền: 5,000,000, tách làm hai, số tiền 1: 2,500,000, folio 1, số tiền 2: 2,500,000, folio 1', $splitRes['description']);

        // Row 21: Chuyển cọc
        $transferLog = new ActivityLog([
            'id' => 10002,
            'action' => 'Modify',
            'module' => 'frontdesk',
            'component' => 'payments',
            'target_type' => 'Payment',
            'target_id' => '15',
            'request_url' => 'http://localhost/api/payments/15/transfer',
            'old_values' => ['booking_id' => 22],
            'new_values' => ['amount' => 2500000, 'target_booking_id' => 20],
            'description' => '[TRANSFER IN BK_22=>BK_20] Deposit (Bank transfer)',
        ]);
        $transferRes = (new ActivityLogResource($transferLog))->resolve();
        $this->assertEquals('Chuyển đặt cọc (2.Deposit (Bank transfer) - 2,500,000) từ mã đăng ký: 22 đến mã đăng ký: 20', $transferRes['description']);
    }

    /**
     * Test Row 63: No Post tại cấp Booking và BookingRoom.
     */
    public function test_no_post_logging_at_booking_and_room_level()
    {
        // Booking level: Bật No Post, mã phòng null
        $bLog = new ActivityLog([
            'id' => 10003,
            'action' => 'Modify',
            'module' => 'reservation',
            'component' => 'Checkout',
            'target_type' => 'Booking',
            'target_id' => '27',
            'target_label' => 'GAL27',
            'old_values' => ['no_post' => false],
            'new_values' => ['no_post' => true],
            'description' => 'Cập nhật Booking',
        ]);
        $bRes = (new ActivityLogResource($bLog))->resolve();
        $this->assertNull($bRes['room_number']);
        $this->assertEquals('* Bật trạng thái No Post cho Mã đăng ký GAL27', $bRes['description']);

        // Room level: Bật No Post cho phòng 405
        $rLog = new ActivityLog([
            'id' => 10004,
            'action' => 'Modify',
            'module' => 'reservation',
            'component' => 'Checkout',
            'target_type' => 'BookingRoom',
            'target_id' => 'G0000028',
            'old_values' => ['no_post' => false, 'room_number' => '405', 'booking_id' => 27],
            'new_values' => ['no_post' => true, 'room_number' => '405', 'booking_id' => 27],
            'description' => 'Cập nhật phòng',
        ]);
        $rRes = (new ActivityLogResource($rLog))->resolve();
        $this->assertEquals('* Bật trạng thái No Post cho Mã đăng ký GAL27, phòng: 405', $rRes['description']);
    }

    /**
     * Test Row 48 & 97: Room status change and DND.
     */
    public function test_room_status_and_dnd_logging()
    {
        // DND enabled
        $dndLog = new ActivityLog([
            'id' => 10005,
            'action' => 'Modify',
            'module' => 'frontdesk',
            'component' => 'RoomMapPage',
            'target_type' => 'Room',
            'target_id' => '702',
            'old_values' => ['room_number' => '702', 'room_status_code' => 'vacant_ready'],
            'new_values' => ['room_number' => '702', 'room_status_code' => 'dnd'],
            'description' => 'Cập nhật phòng',
        ]);
        $dndRes = (new ActivityLogResource($dndLog))->resolve();
        $this->assertEquals('* Phòng 702. Bật trạng thái Do Not Disturb (Không làm phiền)', $dndRes['description']);

        // Housekeeping status change
        $stLog = new ActivityLog([
            'id' => 10006,
            'action' => 'Modify',
            'module' => 'frontdesk',
            'component' => 'RoomMapPage',
            'target_type' => 'Room',
            'target_id' => '101',
            'old_values' => ['room_number' => '101', 'room_status_code' => 'vacant_dirty'],
            'new_values' => ['room_number' => '101', 'room_status_code' => 'vacant_ready'],
            'description' => 'Cập nhật phòng',
        ]);
        $stRes = (new ActivityLogResource($stLog))->resolve();
        $this->assertEquals('* Phòng 101. Đổi trạng thái từ: Phòng chưa dọn -> Phòng sẵn sàng', $stRes['description']);
    }

    /**
     * Test Row 34 & 98: Auto assign rooms and Undo check-in.
     */
    public function test_undo_checkin_and_auto_assign_logging()
    {
        // Row 98: Undo check-in
        $undoLog = new ActivityLog([
            'id' => 10007,
            'action' => 'Modify',
            'module' => 'reservation',
            'component' => 'RoomMapPage',
            'target_type' => 'BookingRoom',
            'target_id' => 'G0000063',
            'request_url' => 'http://localhost/api/bookings/6787/rooms/G0000063/undo-checkin',
            'old_values' => ['status' => 1, 'room_number' => '406', 'booking_id' => 6787],
            'new_values' => ['status' => 0, 'room_number' => '406', 'booking_id' => 6787],
            'description' => 'Cập nhật phòng',
        ]);
        $undoRes = (new ActivityLogResource($undoLog))->resolve();
        $this->assertEquals("Cancel checkin cho Mã Đăng Ký: GAL6787\n- Phòng: 406\n- tình trạng phòng: Phòng sạch", $undoRes['description']);

        // Row 34: Auto assign
        $assignLog = new ActivityLog([
            'id' => 10008,
            'action' => 'Modify',
            'module' => 'reservation',
            'component' => 'Tạo đăng ký',
            'target_type' => 'BookingRoom',
            'target_id' => 'G0000048',
            'request_url' => 'http://localhost/api/bookings/22/rooms/G0000048/auto-assign',
            'new_values' => ['room_number' => '109'],
            'description' => 'Cập nhật phòng',
        ]);
        $assignRes = (new ActivityLogResource($assignLog))->resolve();
        $this->assertEquals('* Tự động assigned rooms: - Mã phòng: G0000048 -> 109', $assignRes['description']);
    }

    /**
     * Test BookingRoomService resolves booking_code, room_number, and rich detail description.
     */
    public function test_booking_room_service_resolution_and_detail_description()
    {
        $log = new ActivityLog([
            'id' => 10009,
            'action' => 'Add',
            'module' => 'housekeeping',
            'component' => 'ServicesModal',
            'target_type' => 'BookingRoomService',
            'target_id' => 'G0000001',
            'target_label' => 'GAL1',
            'description' => '* Thêm mới Phòng Đặt BookingRoomService',
            'new_values' => [
                'booking_id' => 1,
                'booking_room_id' => 'G0000001',
                'service_name' => 'Bia Heineken',
                'room_number' => '101',
                'quantity' => 2,
                'price' => 35000,
                'total_amount' => 70000,
            ],
        ]);

        $res = (new ActivityLogResource($log))->resolve();
        $this->assertEquals('GAL1', $res['booking_code']);
        $this->assertEquals('G0000001', $res['room_number']);
        $this->assertEquals('Thêm DV Buồng phòng', $res['component']);
        $this->assertStringContainsString('Bia Heineken', $res['description']);
        $this->assertStringContainsString('35,000 đ', $res['description']);
        $this->assertStringContainsString('70,000 đ', $res['description']);
    }

    /**
     * Test rich creation details and Vietnamese diff labels for generic models (F&B / Inventory / Config).
     */
    public function test_rich_creation_and_vietnamese_diff_for_generic_models()
    {
        // Add FB Product
        $addLog = new ActivityLog([
            'id' => 10010,
            'action' => 'Add',
            'module' => 'fnb',
            'component' => 'Món ăn & Đồ uống F&B',
            'target_type' => 'FbProduct',
            'target_id' => '15',
            'target_label' => 'Cà phê muối',
            'description' => "* Thêm mới Món ăn & Đồ uống F&B 'Cà phê muối'",
            'new_values' => [
                'code' => 'CF-MUOI',
                'name' => 'Cà phê muối',
                'price' => 35000,
                'unit' => 'Ly',
                'is_active' => true,
            ],
        ]);

        $addRes = (new ActivityLogResource($addLog))->resolve();
        $this->assertStringContainsString('Mã: CF-MUOI', $addRes['description']);
        $this->assertStringContainsString('Tên: Cà phê muối', $addRes['description']);
        $this->assertStringContainsString('Đơn giá: 35,000', $addRes['description']);
        $this->assertStringContainsString('Kích hoạt: Có', $addRes['description']);

        // Modify FB Product
        $modLog = new ActivityLog([
            'id' => 10011,
            'action' => 'Modify',
            'module' => 'fnb',
            'component' => 'Món ăn & Đồ uống F&B',
            'target_type' => 'FbProduct',
            'target_id' => '15',
            'target_label' => 'Cà phê muối',
            'description' => "* Cập nhật Món ăn & Đồ uống F&B 'Cà phê muối'",
            'old_values' => ['price' => 35000, 'is_active' => true],
            'new_values' => ['price' => 40000, 'is_active' => false],
        ]);

        $modRes = (new ActivityLogResource($modLog))->resolve();
        $this->assertStringContainsString('Đơn giá: 35,000 ➜ 40,000', $modRes['description']);
        $this->assertStringContainsString('Kích hoạt: Có ➜ Không', $modRes['description']);
    }

    /**
     * Test route and component mapping for residence-declarations.
     */
    public function test_residence_declarations_component_mapping()
    {
        $log = new ActivityLog([
            'id' => 10012,
            'action' => 'Add',
            'module' => 'frontdesk',
            'component' => 'residence-declarations',
            'target_type' => 'ResidenceDeclaration',
            'target_id' => '5',
            'target_label' => 'John Doe',
            'description' => "* Thêm mới Khai báo tạm trú 'John Doe'",
            'new_values' => [
                'full_name' => 'John Doe',
                'passport_no' => 'B1234567',
                'nationality' => 'USA',
            ],
        ]);

        $res = (new ActivityLogResource($log))->resolve();
        $this->assertEquals('Khai báo tạm trú', $res['component']);
        $this->assertEquals('Lễ tân', $res['module_label']);
        $this->assertStringContainsString('Số hộ chiếu: B1234567', $res['description']);
    }
}


