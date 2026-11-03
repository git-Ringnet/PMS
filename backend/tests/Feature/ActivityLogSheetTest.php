<?php

namespace Tests\Feature;

use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\Booker;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingRoomService;
use App\Models\Company;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\SpecialRequest;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Support\ActivityScreen;
use App\Support\BookingLogText;
use App\Support\CatalogLogText;
use App\Support\GuestLogText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sheet "GHI LOG" (dòng 1-103): nội dung lịch sử thao tác, cột Màn hình, Mã đăng ký, Mã phòng (mã G).
 */
class ActivityLogSheetTest extends TestCase
{
    use RefreshDatabase;

    private Booking $booking;
    private BookingRoom $room;
    private RoomClass $double;
    private RoomClass $twin;
    private RoomForm $formDouble;
    private RoomForm $formTwin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->withRole('super_admin')->create(['username' => 'sheet_user', 'name' => 'Test User']));
        DB::table('booking_statuses')->insertOrIgnore([['id' => 0, 'name' => 'Reservation'], ['id' => 1, 'name' => 'Checked In']]);

        $this->formDouble = RoomForm::create(['name' => 'Double']);
        $this->formTwin = RoomForm::create(['name' => 'Twin']);
        $this->double = RoomClass::create(['code' => 'SUPD', 'name' => 'Superior Double', 'is_active' => true]);
        $this->twin = RoomClass::create(['code' => 'SUPT', 'name' => 'Superior Twin', 'is_active' => true]);
        Room::create(['room_number' => '605', 'room_form_id' => $this->formDouble->id, 'room_class_id' => $this->double->id, 'floor' => 6, 'status' => 'vacant_ready', 'room_status_code' => 'vacant_dirty']);

        $booker = Booker::create(['name' => 'Trần Thị B', 'phone' => '0912345678', 'email' => 'tranthib@gmail.com']);
        $this->booking = Booking::create([
            'booking_name' => 'test ghi log',
            'booking_date' => '2026-09-01',
            'arrival_date' => '2026-09-08',
            'departure_date' => '2026-09-14',
            'num_of_days' => 6,
            'is_git' => true,
            'booker_id' => $booker->id,
            'status' => Booking::STATUS_RESERVATION,
            'created_by' => 'sheet_user',
        ]);
        $this->room = BookingRoom::create([
            'booking_id' => $this->booking->id,
            'room_number' => '605',
            'room_class_id' => $this->double->id,
            'RoomKind' => $this->formDouble->id,
            'arrival_date' => '2026-09-08',
            'departure_date' => '2026-09-14',
            'rate' => 700000,
            'adults' => 2,
            'status' => BookingRoom::STATUS_BOOKED,
        ]);
        $this->room->forceFill(['RoomKind' => $this->formDouble->id])->saveQuietly();

        ActivityLog::query()->delete();
        ActivityLogResource::flushCache();
    }

    private function code(): string
    {
        return 'GAL' . $this->booking->id;
    }

    private function requestOn(string $tab, array $input = []): Request
    {
        $request = Request::create('/api/x', 'POST', $input);
        $request->headers->set('X-Screen', $tab);
        $request->setUserResolver(fn () => auth()->user());
        app()->instance('request', $request);

        return $request;
    }

    // ------------------------------------------------------------------ nền tảng

    public function test_screen_is_resolved_from_header_then_referer(): void
    {
        $this->assertSame(ActivityScreen::ROOM_SCHEDULE, ActivityScreen::label($this->requestOn('room-plan')));

        $request = Request::create('/api/x', 'POST');
        $request->headers->set('referer', 'http://pms.local/frontdesk?tab=checkout');
        $this->assertSame(ActivityScreen::CHECKOUT, ActivityScreen::label($request));
        $this->assertSame('Fallback', ActivityScreen::label(Request::create('/api/x'), 'Fallback'));
    }

    public function test_v2_log_is_displayed_verbatim_with_booking_and_g_room_codes(): void
    {
        $log = ActivityLogService::record('Modify', '* Nội dung gốc', [
            'force_screen' => ActivityScreen::ROOM_SCHEDULE,
            'booking_code' => $this->booking->id,
            'room_code' => ['G0000048', 'G0000049', 'G0000048'],
        ]);

        $res = (new ActivityLogResource($log->fresh()))->resolve();

        $this->assertSame('* Nội dung gốc', $res['description']);
        $this->assertSame($this->code(), $res['booking_code']);
        $this->assertSame('G0000048, G0000049', $res['room_number']);
        $this->assertSame('RoomSchedule', $res['component']);
    }

    // ------------------------------------------------------------------ dòng 3, 4, 14, 15

    public function test_booking_created_has_booker_phone_real_price_and_bed_form(): void
    {
        $this->requestOn('room-plan');
        ActivityLogService::logBookingCreated($this->booking->fresh());

        $info = ActivityLog::where('target_type', 'Booking')->latest('id')->first();
        $rooms = ActivityLog::where('target_type', 'BookingRoom')->latest('id')->first();

        $this->assertSame('RoomSchedule', $info->component);
        $this->assertStringStartsWith("* Thông Tin Đăng Ký {$this->code()} : -Tên nhóm : test ghi log -FIT : GIT", $info->description);
        $this->assertStringNotContainsString('VAT', $info->description);
        $this->assertStringContainsString('-Liên hệ : 0912345678', $info->description);
        $this->assertStringContainsString('-Booker : Trần Thị B', $info->description);

        $this->assertStringContainsString("Mã: {$this->room->id} - Giá: 700,000 - Loại: Superior Double - Dạng: Double - Phòng: 605", $rooms->description);
        $this->assertSame($this->code(), $rooms->booking_code);
        $this->assertSame((string) $this->room->id, $rooms->room_code);
    }

    public function test_quick_checkin_booking_creation_is_not_logged_separately(): void
    {
        $this->requestOn('room-map', ['quick_checkin' => true]);
        ActivityLogService::logBookingCreated($this->booking->fresh());

        $this->assertSame(0, ActivityLog::count());
    }

    // ------------------------------------------------------------------ dòng 17, 18, 33

    public function test_booking_update_logs_only_changed_fields_and_room_lines(): void
    {
        $request = $this->requestOn('create-res');
        $snapshot = ActivityLogService::snapshotBooking($this->booking);

        $this->booking->update(['departure_date' => '2026-09-17', 'num_of_days' => 9, 'color' => '#97D5FF']);
        $this->room->update(['departure_date' => '2026-09-17', 'rate' => 750000]);

        ActivityLogService::logBookingChanges($this->booking, $snapshot, $request);

        $logs = ActivityLog::orderBy('id')->pluck('description')->all();
        $this->assertCount(2, $logs);
        $this->assertSame("* Cập Nhật Thông Tin Đăng Ký {$this->code()} : -Ngày Đi: 14-09-2026 -> 17-09-2026 -Số Ngày: 6 -> 9", $logs[0]);
        $this->assertSame("* Cập Nhật Phòng Thuê ({$this->room->id}): - Ngày Checkout 14-09-2026 -> 17-09-2026 - Giá 700,000 -> 750,000", $logs[1]);
        $this->assertStringNotContainsString('{', implode(' ', $logs));
    }

    public function test_booking_update_without_changes_writes_nothing(): void
    {
        $request = $this->requestOn('create-res');
        $snapshot = ActivityLogService::snapshotBooking($this->booking);

        ActivityLogService::logBookingChanges($this->booking, $snapshot, $request);

        $this->assertSame(0, ActivityLog::count());
    }

    // ------------------------------------------------------------------ dòng 9, 17, 27, 30, 35

    public function test_room_diff_formats_follow_sheet(): void
    {
        $id = 'G0000045';

        $classChange = BookingLogText::roomDiff($id,
            ['room_number' => '705', 'room_class_id' => $this->double->id, 'RoomKind' => $this->formDouble->id],
            ['room_number' => '207', 'room_class_id' => $this->twin->id, 'RoomKind' => $this->formTwin->id]);
        $this->assertSame("* Cập nhật phòng thuê: điều chỉnh loại dạng phòng cho {$id}\nPhòng: 705 -> 207\nLoại: Superior Double -> Superior Twin\nDạng: Double -> Twin", $classChange);

        $this->assertSame('* Cập Nhật Phòng Thuê (G0000045): - Phòng 205 -> 605',
            BookingLogText::roomDiff($id, ['room_number' => '205'], ['room_number' => '605']));

        $this->assertSame('* Cập nhật phòng (G0000045) ngày đến từ 08-09-2026 => 08-09-2026, ngày đi từ 14-09-2026 => 16-09-2026',
            BookingLogText::roomDiff($id, ['arrival_date' => '2026-09-08', 'departure_date' => '2026-09-14'], ['arrival_date' => '2026-09-08', 'departure_date' => '2026-09-16']));

        $this->assertSame('Extra Bed phòng (G0000045) Thêm giường: 0 -> 1 Giá thêm giường: 0 -> 300,000',
            BookingLogText::roomDiff($id, ['extra_bed_qty' => 0, 'extra_bed_rate' => 0], ['extra_bed_qty' => 1, 'extra_bed_rate' => 300000]));

        $this->assertNull(BookingLogText::roomDiff($id, ['rate' => '700000.00'], ['rate' => 700000]));
    }

    public function test_room_info_change_from_room_map_uses_room_rate_wording(): void
    {
        $request = $this->requestOn('room-map');
        ActivityLogService::logBookingRoomUpdated($this->room, ['rate' => 540000, 'departure_date' => '2026-09-04'], ['rate' => 600000, 'departure_date' => '2026-09-05'], $request);

        $log = ActivityLog::latest('id')->first();
        $this->assertSame("Thay đổi Mã Đăng Ký {$this->code()} - Phòng: 605 ({$this->room->id})\n- Ngày Checkout: 04-09-2026 -> 05-09-2026\n- Room Rate: 540,000 -> 600,000", $log->description);
        $this->assertSame('Room map', $log->component);
    }

    public function test_room_departure_change_logs_booking_line_only_when_booking_dates_change(): void
    {
        $request = $this->requestOn('room-plan');
        $before = $this->booking->getRawOriginal();

        // Ngày đi phòng rút ngắn, đăng ký giữ nguyên ngày đi -> không có dòng đăng ký
        ActivityLogService::logBookingRoomUpdated($this->room, ['departure_date' => '2026-09-14'], ['departure_date' => '2026-09-12'], $request, $before);
        $this->assertSame(0, ActivityLog::where('target_type', 'Booking')->count());

        $this->booking->update(['departure_date' => '2026-09-16', 'num_of_days' => 8]);
        ActivityLogService::logBookingRoomUpdated($this->room, ['departure_date' => '2026-09-14'], ['departure_date' => '2026-09-16'], $request, $before);
        $this->assertSame("* Cập Nhật Thông Tin Đăng Ký {$this->code()} : -Ngày Đi: 14-09-2026 -> 16-09-2026 -Số Ngày: 6 -> 8",
            ActivityLog::where('target_type', 'Booking')->value('description'));
    }

    // ------------------------------------------------------------------ dòng 24, 25, 26

    public function test_shuttle_add_update_remove(): void
    {
        $row = ['id' => 1, 'type' => 'Đón', 'vehicle' => '7 Seater car', 'code' => '79N1-12344', 'date' => '2026-09-03', 'time' => '12:00', 'price' => 0, 'location' => 'Airport', 'note' => 'hhhh'];

        [$added] = BookingLogText::shuttleDiff('GAL44', [], [$row]);
        $this->assertSame('* Cập nhật đăng ký GAL44: Thêm thông tin đưa đón khách:- Loại hình: -> Đón, - Phương tiện: -> 7 Seater car, - Mã: -> 79N1-12344, - Ngày: -> 03/09/2026, - Giờ: -> 12:00, - Hiện giá: -> 0, - Địa điểm: -> Airport, - Ghi chú: -> hhhh.', $added);

        [$updated] = BookingLogText::shuttleDiff('GAL44', [$row], [array_merge($row, ['type' => 'Tiễn', 'time' => '14:00'])]);
        $this->assertStringContainsString('Cập nhật thông tin đưa đón khách:- Loại hình: Tiễn', $updated);
        $this->assertStringContainsString('- Giờ: 12:00 -> 14:00', $updated);

        [$removed] = BookingLogText::shuttleDiff('GAL22', [$row], []);
        $this->assertStringStartsWith('* Xóa thông tin đưa đón khách cho đăng ký GAL22: Loại hình: Đón, - Phương tiện: 7 Seater car', $removed);
    }

    // ------------------------------------------------------------------ dòng 28, 38

    public function test_added_services_of_one_action_are_grouped_into_date_ranges(): void
    {
        $make = fn (string $date) => BookingRoomService::create([
            'booking_room_id' => $this->room->id, 'service_code' => 'DN', 'service_name' => 'Dinner / Ăn tối',
            'service_date' => $date, 'quantity' => 1, 'rate' => 250000, 'is_room' => 1,
        ]);
        $request = $this->requestOn('create-res', ['log_batch' => 'b1']);

        foreach (['2026-09-12', '2026-09-14', '2026-09-13'] as $date) {
            ActivityLogService::logRoomService($this->room, $make($date), true, $request);
        }

        $this->assertSame(1, ActivityLog::count());
        $this->assertSame("* Thêm Dịch Vụ Tự Động Cho Mã Đăng Ký {$this->code()} - Phòng: {$this->room->id} - Dịch Vụ: Dinner / Ăn tối - Từ ngày: 12-09-2026 - Đến ngày:14-09-2026 - Số lượng: 1 - Số tiền: 250,000 - FIT",
            ActivityLog::value('description'));
    }

    public function test_non_contiguous_service_dates_are_separate_segments_and_single_night_edit(): void
    {
        $request = $this->requestOn('create-res', ['log_batch' => 'b2']);
        foreach (['2026-09-12', '2026-09-14'] as $date) {
            $svc = BookingRoomService::create(['booking_room_id' => $this->room->id, 'service_code' => 'DN', 'service_name' => 'Dinner / Ăn tối', 'service_date' => $date, 'quantity' => 1, 'rate' => 250000, 'is_room' => 1]);
            ActivityLogService::logRoomService($this->room, $svc, true, $request);
        }
        $this->assertSame(2, substr_count(ActivityLog::value('description'), 'Từ ngày'));

        $rm = BookingRoomService::create(['booking_room_id' => $this->room->id, 'service_code' => 'RM', 'service_date' => '2026-09-13', 'quantity' => 1, 'rate' => 0]);
        ActivityLogService::logRoomService($this->room, $rm, false, $this->requestOn('create-res'));
        $this->assertSame("Cập nhật dịch vụ tự động cho mã đăng ký {$this->code()} - Phòng: {$this->room->id} - Dịch vụ: RM - Ngày: 13-09-2026 - Số lượng: 1 - Số tiền: 0",
            ActivityLog::latest('id')->value('description'));
    }

    // ------------------------------------------------------------------ dòng 53, 67

    public function test_room_charges_from_day_close_are_grouped_by_batch(): void
    {
        $request = $this->requestOn('day-close', ['log_batch' => 'night1']);
        ActivityLogService::logRoomCharge(1, ['G0000061' => '102'], 'auto', '2026-09-04', '2026-09-04', null, $request);
        ActivityLogService::logRoomCharge(2, ['G0000062' => '109'], 'auto', '2026-09-04', '2026-09-04', null, $request);

        $log = ActivityLog::sole();
        $this->assertStringStartsWith('* Thêm tiền phòng cho các phòng:102(G0000061), 109(G0000062) - Từ ngày: 04-09-2026 - Đến ngày: 04-09-2026', $log->description);
        $this->assertStringEndsWith('Mã dịch vụ RM -Mô tả dịch vụ: Dịch vụ phòng nghỉ (Room rate night)', $log->description);
        $this->assertSame('G0000061, G0000062', $log->room_code);
        $this->assertNull($log->booking_code);
        $this->assertSame('Sang ngày', $log->component);
    }

    public function test_manual_room_charge_states_occupancy(): void
    {
        $request = $this->requestOn('checkout');
        ActivityLogService::logRoomCharge(27, ['G0000063' => '102'], 'surcharge', '2026-09-04', '2026-09-05', 300000, $request);

        $desc = ActivityLog::latest('id')->value('description');
        $this->assertStringStartsWith('* Thêm tiền phòng (nhập tay số tiền) cho đăng ký GAL27 -số phòng:102', $desc);
        $this->assertStringEndsWith('-Số tiền: 300,000 (enter room charge - không tính công suất phòng)', $desc);
    }

    // ------------------------------------------------------------------ dòng 37, 52, 62

    public function test_check_in_and_check_out_lines(): void
    {
        ActivityLogService::logCheckIn($this->booking->id, $this->room, $this->requestOn('room-map'));
        $this->assertSame("Check in cho đăng ký {$this->code()} - các phòng: 605", ActivityLog::latest('id')->value('description'));
        $this->assertSame((string) $this->room->id, ActivityLog::latest('id')->value('room_code'));

        ActivityLogService::logCheckIn($this->booking->id, $this->room, $this->requestOn('room-map', ['quick_checkin' => true]));
        $this->assertStringStartsWith("* Giao phòng nhanh cho phòng 605 ({$this->room->id}) từ Room map có:", ActivityLog::latest('id')->value('description'));

        ActivityLogService::logCheckOut($this->booking->id, null, $this->requestOn('checkout'));
        $this->assertSame("* Check Out Cho Đăng Ký {$this->code()}.", ActivityLog::latest('id')->value('description'));
        ActivityLogService::logCheckOut($this->booking->id, [$this->room], $this->requestOn('checkout'));
        $this->assertSame("* Check Out Cho Đăng Ký {$this->code()} - Phòng: 605 ({$this->room->id})", ActivityLog::latest('id')->value('description'));
    }

    // ------------------------------------------------------------------ dòng 70, 71

    public function test_day_close_and_report_view(): void
    {
        ActivityLogService::logDayClose('2026-10-14', '2026-10-15', $this->requestOn('day-close'));
        $this->assertStringStartsWith('* Sang ngày thành công: Từ ngày 14/10/2026 sang ngày 15/10/2026 - User:', ActivityLog::latest('id')->value('description'));

        ActivityLogService::logReportView('Báo cáo hủy đăng ký', '04-09-2026', '04-09-2026', $this->requestOn('reports'));
        $log = ActivityLog::latest('id')->first();
        $this->assertSame('Xem báo cáo: "Báo cáo hủy đăng ký"  - Từ ngày 04-09-2026 , - Đến ngày 04-09-2026', $log->description);
        $this->assertSame('Báo cáo', $log->component);
    }

    // ------------------------------------------------------------------ dòng 36, 49, 100, 102

    public function test_guest_diff_only_lists_changed_fields(): void
    {
        $diff = GuestLogText::diff(
            ['title' => 'Mr.', 'full_name' => 'Guest 1', 'dob' => null, 'phone' => '0909'],
            ['title' => 'Ms.', 'full_name' => 'IUDINA ANNA', 'dob' => '2015-01-12', 'phone' => '0909']
        );

        $this->assertSame('- Danh xưng: Mr. -> Ms. - Họ tên: Guest 1 -> IUDINA ANNA - Ngày sinh:  -> 12-01-2015', $diff);
    }

    // ------------------------------------------------------------------ dòng 64-66, 79, 82, 93

    public function test_catalog_texts(): void
    {
        $this->assertSame('* Cập Nhật Công Ty:- ID: CTY0005 , - Thanh toán công nợ: Không ➜ Có',
            CatalogLogText::describe('Company', 'update', ['code' => 'CTY0005', 'name' => 'Hana', 'sync_acc' => 0], ['code' => 'CTY0005', 'name' => 'Hana', 'sync_acc' => 1]));

        $this->assertSame('* Thêm mới thị trường: ID: 123, - Tên: 123', CatalogLogText::describe('Market', 'create', null, ['code' => '123', 'name' => '123']));
        $this->assertSame("* Cập nhật thị trường: FOC\n- Tên: Nội bộ (Free of Charge -) ➜ Nội bộ (Free of Charge)",
            CatalogLogText::describe('Market', 'update', ['code' => 'FOC', 'name' => 'Nội bộ (Free of Charge -)'], ['code' => 'FOC', 'name' => 'Nội bộ (Free of Charge)']));
        $this->assertSame('* Xóa nguồn khách: - ID: S1, - Tên: Walk-in', CatalogLogText::describe('CustomerSource', 'delete', ['code' => 'S1', 'name' => 'Walk-in'], null));

        $this->assertSame("* Cập nhật người đặt phòng: ID: 5\n- Số điện thoại: 0905785224➜ 0905785223",
            CatalogLogText::describe('Booker', 'update', ['id' => 5, 'phone' => '0905785224'], ['id' => 5, 'phone' => '0905785223']));

        $this->assertSame('* Ẩn nhân viên: - Tên: Phan Thị Quỳnh', CatalogLogText::describe('HkStaff', 'update', ['name' => 'Phan Thị Quỳnh', 'is_hidden' => false], ['name' => 'Phan Thị Quỳnh', 'is_hidden' => true]));

        $this->assertSame('* Cập nhật Lost & Found: - ID: 1, - Ngày nhận:  -> 10-09-2026, - Phương thức xử lý:  -> khách đến lấy',
            CatalogLogText::describe('LostAndFoundItem', 'update', ['log_no' => 1, 'date_handling' => null, 'method_handling' => null], ['log_no' => 1, 'date_handling' => '2026-09-10', 'method_handling' => 'khách đến lấy']));

        // Không đổi gì -> không ghi log
        $this->assertNull(CatalogLogText::describe('Market', 'update', ['code' => 'A', 'name' => 'X'], ['code' => 'A', 'name' => 'X']));
    }

    // ------------------------------------------------------------------ HTTP: dòng 31, 32, 45-47, 48, 63, 64

    public function test_http_no_post_room_and_booking(): void
    {
        $this->withHeaders(['X-Screen' => 'checkout'])->patchJson("/api/booking-rooms/{$this->room->id}/no-post", ['no_post' => true])->assertOk();
        $log = ActivityLog::latest('id')->first();
        $this->assertSame("* Bật trạng thái No Post cho Mã đăng ký {$this->code()}, phòng: 605", $log->description);
        $this->assertSame((string) $this->room->id, $log->room_code);

        $this->withHeaders(['X-Screen' => 'checkout'])->patchJson("/api/bookings/{$this->booking->id}/no-post", ['no_post' => false])->assertOk();
        $log = ActivityLog::latest('id')->first();
        $this->assertSame("* Tắt trạng thái No Post cho Mã đăng ký {$this->code()}", $log->description);
        $this->assertNull($log->room_code);
    }

    public function test_http_special_request_add_and_remove(): void
    {
        $sr = SpecialRequest::create(['code' => 'HNYM', 'name' => 'Honeymoon', 'is_active' => true]);

        $item = $this->withHeaders(['X-Screen' => 'create-res'])
            ->postJson("/api/booking-rooms/{$this->room->id}/special-requests", ['special_request_id' => $sr->id])->assertCreated()->json('data');
        $this->assertSame("* Thêm Special - Phòng thuê: {$this->room->id} - Đăng ký: {$this->code()} - Special: HNYM", ActivityLog::latest('id')->value('description'));

        $this->withHeaders(['X-Screen' => 'create-res'])->deleteJson("/api/booking-rooms/{$this->room->id}/special-requests/{$item['id']}")->assertOk();
        $log = ActivityLog::latest('id')->first();
        $this->assertSame("* Xoá Special - Phòng thuê: {$this->room->id} - Đăng ký: {$this->code()} - Special: HNYM", $log->description);
        $this->assertSame((string) $this->room->id, $log->room_code);
        $this->assertSame(2, ActivityLog::count(), 'Middleware không được ghi thêm log chung');
    }

    public function test_http_booking_alert_create_update_delete(): void
    {
        $headers = ['X-Screen' => 'create-res', 'X-Module' => 'frontdesk'];
        $payload = ['scope_type' => 'booking', 'starts_on' => '2026-09-08', 'ends_on' => '2026-09-17', 'description' => 'Khách Khó'];

        $id = $this->withHeaders($headers)->postJson("/api/bookings/{$this->booking->id}/notifications", $payload)->assertCreated()->json('data.id');
        $this->assertSame("* Tạo Alert cho mã đăng ký {$this->code()}\n- Nội dung cảnh báo: Khách Khó\n- Department: FO\n- Từ ngày: 08-09-2026 - Đến ngày: 17-09-2026",
            ActivityLog::latest('id')->value('description'));

        $this->withHeaders($headers)->putJson("/api/bookings/{$this->booking->id}/notifications/{$id}", array_merge($payload, ['description' => 'đoàn quan trọng']))->assertOk();
        $this->assertSame("* Sửa Alert mã đăng ký {$this->code()}:\n- Nội dung cảnh báo: Khách Khó ➜ đoàn quan trọng\n- Từ ngày: 08-09-2026 - Đến ngày: 17-09-2026",
            ActivityLog::latest('id')->value('description'));

        $this->withHeaders($headers)->deleteJson("/api/bookings/{$this->booking->id}/notifications/{$id}")->assertOk();
        $this->assertStringStartsWith("* Xóa Alert mã đăng ký {$this->code()}\n- Nội dung cảnh báo: đoàn quan trọng", ActivityLog::latest('id')->value('description'));
    }

    public function test_http_room_status_change_shows_from_and_to_status(): void
    {
        DB::table('room_statuses')->insertOrIgnore([
            ['code' => 'vacant_dirty', 'name_vi' => 'Phòng chưa dọn', 'is_occupied' => 0, 'is_active' => 1, 'sort_order' => 1],
            ['code' => 'vacant_ready', 'name_vi' => 'Phòng sẵn sàng', 'is_occupied' => 0, 'is_active' => 1, 'sort_order' => 2],
        ]);
        $room = Room::where('room_number', '605')->first();

        $this->withHeaders(['X-Screen' => 'room-map'])->putJson("/api/rooms/{$room->id}/status", ['room_status_code' => 'vacant_ready'])->assertOk();

        $log = ActivityLog::where('target_type', 'Room')->latest('id')->first();
        $this->assertSame('* Phòng 605. Đổi trạng thái từ: Phòng chưa dọn -> Phòng sẵn sàng', $log->description);
        $this->assertSame('Room map', $log->component);

        $this->withHeaders(['X-Screen' => 'room-map'])->putJson("/api/rooms/{$room->id}/housekeeping-flags", ['housekeeping_flags' => ['is_do_not_disturb' => true]])->assertOk();
        $this->assertSame('* Phòng 605. Bật trạng thái Do Not Disturb (Không làm phiền)', ActivityLog::latest('id')->value('description'));
    }

    public function test_http_company_create_and_update_through_middleware(): void
    {
        $headers = ['X-Screen' => 'company'];
        $id = $this->withHeaders($headers)->postJson('/api/companies', ['name' => 'Hana Travel', 'trading_name' => 'Hana Travel'])->assertSuccessful()->json('data.id');
        $created = ActivityLog::latest('id')->first();
        $this->assertSame(2, (int) $created->log_version);
        $this->assertStringStartsWith('* Thêm Công Ty : ID: ' . Company::find($id)->code . ' , Công Ty: Hana Travel , Tên Giao Dịch: Hana Travel', $created->description);
        $this->assertSame('Công ty', $created->component);

        $this->withHeaders($headers)->putJson("/api/companies/{$id}", ['name' => 'Hana Travel', 'sync_acc' => true])->assertSuccessful();
        $this->assertSame('* Cập Nhật Công Ty:- ID: ' . Company::find($id)->code . ' , - Thanh toán công nợ: Không ➜ Có', ActivityLog::latest('id')->value('description'));
    }

    // ------------------------------------------------------------------ dòng 81

    public function test_lost_found_guest_info_room_is_split_out(): void
    {
        $this->assertSame(['Lê Hồng Khánh Nguyên', '605'], CatalogLogText::splitGuestRoom('Lê Hồng Khánh Nguyên, 605'));
        $this->assertSame(['Lê Văn A, 0905123456', '605'], CatalogLogText::splitGuestRoom('Lê Văn A - P.605 - 0905123456'));
        $this->assertSame(['Khách không rõ 999', null], CatalogLogText::splitGuestRoom('Khách không rõ 999'));

        $desc = CatalogLogText::describe('LostAndFoundItem', 'create', null, ['log_no' => 1, 'status' => 'found', 'guest_info' => 'Lê Hồng Khánh Nguyên, 605', 'item_found' => 'Dây chuyền']);
        $this->assertStringContainsString('- Tên khách: Lê Hồng Khánh Nguyên, - Phòng 605, - Mặt hàng: Dây chuyền', $desc);
    }

    // ------------------------------------------------------------------ dòng 102 (trẻ em)

    public function test_http_add_and_remove_child_logs_with_room_g_code(): void
    {
        $headers = ['X-Screen' => 'room-map'];
        $child = $this->withHeaders($headers)->postJson("/api/bookings/{$this->booking->id}/children", [
            'booking_room_id' => $this->room->id,
            'full_name' => 'Bé Na',
            'title' => 'Girl',
            'age_group' => 'child',
        ])->assertCreated()->json('data');

        $log = ActivityLog::where('target_type', 'BookingChild')->latest('id')->first();
        $this->assertStringStartsWith("Thêm trẻ em: Mã trẻ: {$child['id']}\n- Danh xưng: Girl - Họ tên: Bé Na", $log->description);
        $this->assertSame((string) $this->room->id, $log->room_code);
        $this->assertSame($this->code(), $log->booking_code);

        // Nghiệp vụ chỉ cho xóa trẻ trong ngày nhận phòng: đưa ngày đến về ngày hệ thống
        $today = app(\App\Services\RoomAvailabilityService::class)->getSystemDate()->toDateString();
        DB::table('booking_room_children')->where('booking_child_id', $child['id'])->update(['actual_arrival_date' => $today]);
        $this->room->update(['arrival_date' => $today]);
        $resDel = $this->withHeaders($headers)->deleteJson("/api/bookings/{$this->booking->id}/children/{$child['id']}");
        $resDel->assertOk();
        $this->assertSame("Xóa trẻ em: Mã trẻ: {$child['id']} - Tên: Bé Na", ActivityLog::where('target_type', 'BookingChild')->latest('id')->value('description'));
    }

    // ------------------------------------------------------------------ dòng 85, 86, 89

    public function test_http_inventory_check_create_update_and_carry_over(): void
    {
        $outletCode = 'MB';
        DB::table('housekeeping_outlets')->insertOrIgnore(['code' => $outletCode, 'name' => 'Minibar', 'is_active' => 1]);
        $warehouseId = DB::table('warehouses')->insertGetId(['name' => 'Minibar', 'outlet_id' => $outletCode, 'is_active' => 1]);
        $categoryId = DB::table('product_categories')->insertGetId(['name' => 'Soft drink', 'outlet' => $outletCode]);
        $productId = DB::table('products')->insertGetId(['name' => 'Coca Cola', 'product_category_id' => $categoryId, 'price' => 25000]);
        $headers = ['X-Screen' => 'inventory'];
        $payload = fn (float $balance, bool $carry = false) => [
            'warehouse_id' => $warehouseId, 'month' => '2026-09', 'carry_over' => $carry,
            'items' => [['product_id' => $productId, 'well_balance' => $balance, 'stoke_take' => 10]],
        ];

        $this->withHeaders($headers)->postJson('/api/inventory/checks/save', $payload(10))->assertOk();
        $created = ActivityLog::latest('id')->value('description');
        $this->assertStringStartsWith('* Thêm mới phiếu kiểm kê: - Mã phiếu: KK-', $created);
        $this->assertStringContainsString('- Tháng: 09-2026 , - Kho: Minibar', $created);
        $this->assertStringContainsString("- Mã SP: {$productId} , - Tên SP: Coca Cola , - Tồn đầu kỳ: 10 , - Số lượng thực tế: 10", $created);

        $this->withHeaders($headers)->postJson('/api/inventory/checks/save', $payload(15))->assertOk();
        $this->assertStringEndsWith("- Mã SP: {$productId} , - Tên Sp: Coca Cola , - Tồn đầu kỳ: 10 -> 15", ActivityLog::latest('id')->value('description'));

        $this->withHeaders($headers)->postJson('/api/inventory/checks/save', $payload(4, true))->assertOk();
        $this->assertStringStartsWith("Kết chuyển tồn kho Minibar từ tháng 08-2026 sang tháng 09-2026\nChi tiết sản phẩm:\n- Mã SP: {$productId} , - Tên SP: Coca Cola , - Tồn đầu kỳ: 4",
            ActivityLog::latest('id')->value('description'));
    }

    // ------------------------------------------------------------------ bộ lọc màn hình Lịch sử thao tác

    private function seedFilterLogs(): void
    {
        \App\Models\SystemDateRoll::create(['system_date' => '2026-09-03', 'actual_date' => now(), 'shift' => '1', 'username' => 'sheet_user']);
        ActivityLogService::record('Add', 'log ngày 03', ['force_screen' => 'Room map', 'booking_code' => 22, 'room_code' => $this->room->id]);
        \App\Models\SystemDateRoll::create(['system_date' => '2026-09-04', 'actual_date' => now(), 'shift' => '1', 'username' => 'sheet_user']);
        ActivityLogService::record('Cancel', 'log ngày 04, số tiền 220,000', ['force_screen' => 'Trả phòng', 'booking_code' => 220, 'room_code' => 'G0009999']);
        // Log cũ (log_version 1) dùng action "create"
        ActivityLog::create(['user_name' => 'x', 'action' => 'create', 'module' => 'config', 'description' => 'log cũ', 'created_at' => now()]);
    }

    public function test_operation_date_is_system_date_not_machine_date(): void
    {
        $this->seedFilterLogs();

        $rows = collect($this->getJson('/api/activity-logs?per_page=50')->assertOk()->json('data'))->keyBy('description');
        $this->assertSame('03/09/2026', $rows['log ngày 03']['created_date']);
        $this->assertSame('04/09/2026', $rows['log ngày 04, số tiền 220,000']['created_date']);
    }

    public function test_date_filters_use_system_date(): void
    {
        $this->seedFilterLogs();
        $descs = fn (string $q) => collect($this->getJson("/api/activity-logs?per_page=50&{$q}")->assertOk()->json('data'))->pluck('description')->all();

        $this->assertSame(['log ngày 03'], $descs('date_from=2026-09-03&date_to=2026-09-03'));
        $this->assertEqualsCanonicalizing(['log cũ', 'log ngày 04, số tiền 220,000'], $descs('date_from=2026-09-04&date_to=2026-09-04'));
        $this->assertCount(3, $descs('date_from=2026-08-29&date_to=2026-09-04'));
        $this->assertSame([], $descs('date_from=2026-09-05'));
    }

    public function test_registration_room_and_action_filters(): void
    {
        $this->seedFilterLogs();
        $descs = fn (string $q) => collect($this->getJson("/api/activity-logs?per_page=50&{$q}")->assertOk()->json('data'))->pluck('description')->all();

        // "22" chỉ khớp GAL22, không khớp GAL220 hay số tiền 220,000
        $this->assertSame(['log ngày 03'], $descs('registration_code=22'));
        $this->assertSame(['log ngày 03'], $descs('registration_code=gal22'));
        // Số phòng 605 → mã G của phòng thuê ở phòng 605; mã G nhập trực tiếp cũng được
        $this->assertSame(['log ngày 03'], $descs('room_code=605'));
        $this->assertSame(['log ngày 04, số tiền 220,000'], $descs('room_code=G0009999'));
        // "Add" gồm cả log cũ lưu "create"
        $this->assertEqualsCanonicalizing(['log ngày 03', 'log cũ'], $descs('action=Add'));
        $this->assertSame(['log ngày 04, số tiền 220,000'], $descs('action=Cancel'));
    }
}
