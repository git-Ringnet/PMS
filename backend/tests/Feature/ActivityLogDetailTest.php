<?php

namespace Tests\Feature;

use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Company;
use App\Models\CustomerSource;
use App\Models\Market;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Dòng 325: nội dung Lịch sử thao tác phải cụ thể (mã đăng ký, mã phòng, tên danh mục)
 * và không chứa dữ liệu mẫu.
 */
class ActivityLogDetailTest extends TestCase
{
    use RefreshDatabase;

    private Booking $booking;
    private BookingRoom $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['username' => 'log_user', 'name' => 'Lễ tân A']));
        DB::table('booking_statuses')->insert([['id' => 0, 'name' => 'Reservation'], ['id' => 1, 'name' => 'Checked In']]);

        $class = RoomClass::create(['code' => 'DLX', 'name' => 'Deluxe Double', 'is_active' => true]);
        $form = RoomForm::create(['name' => 'Double']);
        Room::create(['room_number' => '301', 'room_form_id' => $form->id, 'room_class_id' => $class->id, 'floor' => 3, 'status' => 'vacant_ready']);

        $this->booking = Booking::create([
            'booking_name' => 'Đoàn Hà Nội',
            'booking_date' => '2026-09-01',
            'arrival_date' => '2026-09-02',
            'departure_date' => '2026-09-04',
            'status' => Booking::STATUS_RESERVATION,
            'created_by' => 'log_user',
        ]);
        $this->room = BookingRoom::create([
            'booking_id' => $this->booking->id,
            'room_number' => '301',
            'room_class_id' => $class->id,
            'arrival_date' => '2026-09-02',
            'departure_date' => '2026-09-04',
            'rate' => 650000,
            'status' => BookingRoom::STATUS_BOOKED,
        ]);
        ActivityLog::query()->delete();
        ActivityLogResource::flushCache();
        \App\Support\ActivityLogFormatter::flushCache();
    }

    /** Gọi phần dựng mô tả của middleware ghi log với dữ liệu thay đổi. */
    private function middlewareDescription(string $action, string $component, ?string $targetType, ?string $targetId, ?string $targetLabel, ?array $old, ?array $new): string
    {
        $method = new \ReflectionMethod(\App\Http\Middleware\LogActivity::class, 'buildDetailedDescription');

        return $method->invoke(
            new \App\Http\Middleware\LogActivity(), $action, 'config', $component, $targetType, $targetId, $targetLabel,
            $old, $new, \Illuminate\Http\Request::create('/api/x', 'POST'), new \Illuminate\Http\Response('', 200)
        );
    }

    public function test_middleware_company_log_shows_catalog_names_instead_of_ids(): void
    {
        $market = Market::create(['code' => 'OTA', 'name' => 'Online Travel Agent']);
        $other = Market::create(['code' => 'GIT', 'name' => 'Khách đoàn']);
        $source = CustomerSource::create(['code' => 'AGODA', 'name' => 'Agoda']);

        $created = $this->middlewareDescription('create', 'Công ty', 'Company', '5', 'Công ty HKT', null, [
            'code' => 'CTY0005', 'name' => 'Công ty HKT', 'trading_name' => 'HKT Travel',
            'market_id' => $market->id, 'customer_source_id' => $source->id, 'max_debt' => 5000000, 'sync_acc' => false,
        ]);
        $this->assertStringContainsString('Thị trường: Online Travel Agent', $created);
        $this->assertStringContainsString('Nguồn khách: Agoda', $created);
        $this->assertStringContainsString('Tên giao dịch: HKT Travel', $created);
        $this->assertStringContainsString('Công nợ tối đa: 5,000,000', $created);
        $this->assertStringContainsString('Đồng bộ ACC: Không', $created);
        $this->assertStringNotContainsString('market_id', $created);

        $updated = $this->middlewareDescription('update', 'Công ty', 'Company', '5', 'Công ty HKT',
            ['market_id' => $market->id, 'code' => 'CTY0005', 'name' => 'Công ty HKT'],
            ['market_id' => $other->id, 'code' => 'CTY0005', 'name' => 'Công ty HKT']);
        $this->assertStringContainsString('Thị trường: Online Travel Agent ➜ Khách đoàn', $updated);
        $this->assertStringNotContainsString('Mã:', $updated); // trường ngữ cảnh không đổi không hiện thành dòng thay đổi
    }

    public function test_middleware_booking_room_and_payment_descriptions_have_context_without_placeholders(): void
    {
        $roomUpdate = $this->middlewareDescription('update', 'Booking', 'BookingRoom', $this->room->id, 'GAL1 - P.301',
            ['adults' => 2, 'booking_id' => $this->booking->id, 'room_number' => '301'],
            ['adults' => 3, 'booking_id' => $this->booking->id, 'room_number' => '301']);
        $this->assertStringContainsString("(GAL{$this->booking->id} - P.301 ({$this->room->id}))", $roomUpdate);
        $this->assertStringContainsString('Người lớn: 2 ➜ 3', $roomUpdate);

        $roomCreate = $this->middlewareDescription('create', 'Booking', 'BookingRoom', 'G0000099', null, null,
            ['booking_id' => $this->booking->id, 'room_class_id' => $this->room->room_class_id]);
        $this->assertStringContainsString('Loại phòng : Deluxe Double', $roomCreate);
        $this->assertStringNotContainsString('540,000', $roomCreate);
        $this->assertStringNotContainsString('Superior Double', $roomCreate);

        $payment = $this->middlewareDescription('create', 'Thu ngân', 'Payment', '1', null, null,
            ['booking_id' => $this->booking->id, 'amount' => 300000, 'description' => 'Cọc']);
        $this->assertStringContainsString('GAL' . $this->booking->id, $payment);
        $this->assertStringContainsString('Tên booking : Đoàn Hà Nội', $payment);
        $this->assertStringContainsString('Số tiền : 300,000', $payment);
        $this->assertStringNotContainsString('Test ghi log', $payment);
        $this->assertStringNotContainsString('GAL22', $payment);

        $lock = $this->middlewareDescription('create', 'Khóa phòng', 'RoomLock', '1', '301', null, ['room_number' => '301', 'lock_type' => 'OOS']);
        $this->assertStringNotContainsString('thay tivi', $lock);
        $this->assertStringContainsString('Phòng 301', $lock);
    }

    public function test_booking_room_update_description_has_booking_and_room(): void
    {
        $log = new ActivityLog([
            'id' => 1, 'action' => 'Modify', 'module' => 'reservation', 'component' => 'Booking',
            'target_type' => 'BookingRoom', 'target_id' => $this->room->id,
            'old_values' => ['adults' => 2, 'booking_id' => $this->booking->id, 'room_number' => '301'],
            'new_values' => ['adults' => 3, 'booking_id' => $this->booking->id, 'room_number' => '301'],
            'description' => 'Cập nhật',
        ]);

        $res = (new ActivityLogResource($log))->resolve();

        $this->assertSame('GAL' . $this->booking->id, $res['booking_code']);
        $this->assertStringContainsString("({$this->room->id} - Phòng 301 - Đăng ký GAL{$this->booking->id})", $res['description']);
        $this->assertStringContainsString('Người Lớn 2 -> 3', $res['description']);
    }

    public function test_booking_room_add_uses_real_values_without_placeholders(): void
    {
        $log = new ActivityLog([
            'id' => 2, 'action' => 'Add', 'module' => 'reservation', 'component' => 'Booking',
            'target_type' => 'BookingRoom', 'target_id' => 'G9999999',
            'new_values' => ['booking_id' => $this->booking->id, 'room_class_id' => $this->room->room_class_id, 'arrival_date' => '2026-09-02', 'departure_date' => '2026-09-04'],
            'description' => '',
        ]);

        $res = (new ActivityLogResource($log))->resolve();

        $this->assertStringContainsString('Đăng ký: GAL' . $this->booking->id, $res['description']);
        $this->assertStringContainsString('Loại: Deluxe Double', $res['description']);
        $this->assertStringContainsString('Phòng: Chưa gán', $res['description']);
        $this->assertStringNotContainsString('540,000', $res['description']);
        $this->assertStringNotContainsString('Superior Double', $res['description']);
    }

    public function test_cancel_room_without_reason_has_no_invented_reason(): void
    {
        $log = new ActivityLog([
            'id' => 3, 'action' => 'Cancel', 'module' => 'reservation', 'component' => 'Booking',
            'target_type' => 'BookingRoom', 'target_id' => $this->room->id,
            'old_values' => ['booking_id' => $this->booking->id, 'room_number' => '301'],
            'description' => '',
        ]);

        $res = (new ActivityLogResource($log))->resolve();

        $this->assertSame("* Hủy phòng thuê: 301 ({$this->room->id}) - Đăng ký: GAL{$this->booking->id}", $res['description']);
    }

    public function test_payment_log_uses_real_booking_name_and_method(): void
    {
        $method = DB::table('payment_methods')->insertGetId(['code' => 'CA', 'name' => 'Tiền mặt', 'created_at' => now(), 'updated_at' => now()]);
        $log = new ActivityLog([
            'id' => 4, 'action' => 'Add', 'module' => 'frontdesk', 'component' => 'payments',
            'target_type' => 'Payment', 'target_id' => '1', 'target_label' => 'GAL' . $this->booking->id,
            'new_values' => ['booking_id' => $this->booking->id, 'amount' => 1200000, 'payment_method_id' => $method, 'description' => 'Cọc lần 1'],
            'description' => 'Tạo mới dữ liệu trên payments',
        ]);

        $res = (new ActivityLogResource($log))->resolve();

        $this->assertStringContainsString('Tên Booking: Đoàn Hà Nội', $res['description']);
        $this->assertStringContainsString('PaymentMethod: Tiền mặt', $res['description']);
        $this->assertStringContainsString('Số Tiền: 1,200,000', $res['description']);
        $this->assertStringContainsString('Mô tả: Cọc lần 1', $res['description']);
    }

    public function test_booking_created_log_has_no_placeholder_values(): void
    {
        \App\Services\ActivityLogService::logBookingCreated($this->booking->fresh());

        $info = ActivityLog::where('target_type', 'Booking')->latest('id')->first()->description;
        $rooms = ActivityLog::where('target_type', 'BookingRoom')->latest('id')->first()->description;

        $this->assertStringContainsString('Đoàn Hà Nội', $info);
        $this->assertStringNotContainsString('Traveloka', $info);
        $this->assertStringNotContainsString('Đại lý lữ hành', $info);
        $this->assertStringContainsString('P.301', $rooms);
        $this->assertStringContainsString('Deluxe Double', $rooms);
        $this->assertStringContainsString('650,000', $rooms);
        $this->assertStringNotContainsString('540,000', $rooms);
    }
}
