<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingRoomGuest;
use App\Models\BookingRoomService;
use App\Models\Guest;
use App\Models\HotelConfig;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomDoNotMoveLock;
use App\Models\RoomNightBill;
use App\Models\RoomForm;
use App\Models\ServiceBill;
use App\Models\SystemDateRoll;
use App\Models\User;
use App\Services\BookingRoomLifecycleService;
use App\Services\BookingRoomMoveService;
use App\Services\RoomAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kiểm thử đối chiếu trực tiếp 13 Section theo tài liệu "Các vấn đề liên quan tới booking 3.docx"
 * và sheet "MÔ TẢ NGHIỆP VỤ !E25".
 */
class BookingThreeSectionsWordDocValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private RoomClass $roomClass;
    private RoomForm $roomForm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['username' => 'test_operator']);

        // Gán đầy đủ các quyền nghiệp vụ FO
        $role = Role::create([
            'code' => 'operator_role',
            'name' => 'Operator Role',
            'level' => 3,
            'department_scope' => 'FO',
            'is_active' => true,
        ]);

        $permissions = [
            'fo.booking.create',
            'fo.booking.edit',
            'fo.booking.move',
            'fo.room.move',
            'fo.checkin',
            'fo.checkout',
            'fo.frontdesk.view',
            'fo.service.add',
            'fo.service.edit',
            'fo.service.delete',
            'fo.payment.create',
        ];

        foreach ($permissions as $code) {
            $p = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'FO']);
            $role->permissions()->syncWithoutDetaching([$p->id]);
        }
        $this->user->roles()->attach($role->id);
        $this->actingAs($this->user);

        // Khởi tạo trạng thái booking
        DB::table('booking_statuses')->insertOrIgnore([
            ['id' => Booking::STATUS_RESERVATION, 'name' => 'Reservation'],
            ['id' => Booking::STATUS_CHECKIN, 'name' => 'Checked In'],
            ['id' => Booking::STATUS_CHECKOUT, 'name' => 'Checked Out'],
            ['id' => Booking::STATUS_DELETED, 'name' => 'Deleted'],
            ['id' => Booking::STATUS_NO_SHOW, 'name' => 'No Show'],
            ['id' => Booking::STATUS_TRANSFER, 'name' => 'Moved'],
        ]);

        // Mặc định ngày hệ thống PMS: 16/08/2026
        SystemDateRoll::create([
            'system_date' => '2026-08-16',
            'actual_date' => '2026-08-16',
            'shift' => '1',
            'username' => 'test_operator',
        ]);

        // Tạo loại phòng và các phòng vật lý trong tài liệu Word (1501, 902, 410, 907)
        $this->roomClass = RoomClass::create(['code' => 'STD', 'name' => 'Standard', 'is_active' => true]);
        $this->roomForm = RoomForm::create(['name' => 'Standard']);

        foreach (['1501', '902', '410', '907'] as $num) {
            Room::firstOrCreate(['room_number' => $num], [
                'room_class_id' => $this->roomClass->id,
                'room_form_id' => $this->roomForm->id,
                'floor' => 1,
                'status' => 'available',
            ]);
        }
    }

    private function createBooking(string $arrival, string $departure, array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'booking_name' => 'Test Booking Word',
            'booking_date' => $arrival,
            'arrival_date' => $arrival,
            'departure_date' => $departure,
            'status' => Booking::STATUS_CHECKIN,
            'created_by' => 'test_operator',
        ], $overrides));
    }

    private function createRoom(Booking $b, string $roomNum, string $arrival, string $departure, array $overrides = []): BookingRoom
    {
        return BookingRoom::create(array_merge([
            'booking_id' => $b->id,
            'room_number' => $roomNum,
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => $arrival,
            'departure_date' => $departure,
            'rate' => 1000000,
            'status' => BookingRoom::STATUS_CHECKED_IN,
            'adults' => 1,
        ], $overrides));
    }

    /**
     * Section 1: Chuyển phòng tại ngày phòng vừa nhận phòng
     * - Tạo booking ngày đến = ngày hệ thống lấy phòng 1501, check in rồi chuyển sang 902.
     * - Phòng cũ (1501): ngày đi = ngày chuyển (16/08), 0 đêm, không chi tiết ngày vì chưa có bill.
     * - Phòng mới (902): hiển thị đầy đủ thông tin và chi tiết từng ngày.
     */
    public function test_section_01_chuyen_phong_ngay_nhan_phong_khong_co_bill(): void
    {
        $booking = $this->createBooking('2026-08-16', '2026-08-19');
        $room1501 = $this->createRoom($booking, '1501', '2026-08-16', '2026-08-19');
        $guest = Guest::create(['full_name' => 'Khach 1501']);
        BookingRoomGuest::create([
            'booking_room_id' => $room1501->id,
            'guest_id' => $guest->id,
            'is_primary' => true,
            'status' => BookingRoomGuest::STATUS_CHECKED_IN,
        ]);

        // Thực hiện chuyển phòng sang 902
        $room902 = $room1501->moveToRoom('902', '2026-08-16', 'test_operator');

        // Phòng cũ (1501): 0 đêm, ngày đến = ngày đi = 16/08, status Moved (100)
        $room1501Fresh = $room1501->fresh();
        $this->assertSame(BookingRoom::STATUS_MOVED, (int) $room1501Fresh->status);
        $this->assertSame('2026-08-16', $room1501Fresh->arrival_date->toDateString());
        $this->assertSame('2026-08-16', $room1501Fresh->departure_date->toDateString());
        $this->assertSame(0, (int) $room1501Fresh->ActutalNumOfDays);
        $this->assertSame(0, ServiceBill::where('RentalRoomId1', $room1501->id)->count());

        // Phòng mới (902): 3 đêm, ngày đến 16/08, ngày đi 19/08, status Checked In
        $this->assertSame(BookingRoom::STATUS_CHECKED_IN, (int) $room902->status);
        $this->assertSame('2026-08-16', $room902->arrival_date->toDateString());
        $this->assertSame('2026-08-19', $room902->departure_date->toDateString());
        $this->assertSame(3, (int) $room902->ActutalNumOfDays);
        $this->assertSame('902', $room902->room_number);
    }

    /**
     * Section 2: Chuyển phòng sau khi post trước tiền phòng và có EB
     * - Check in phòng có setup EB, post trước tiền phòng đêm 16 và chuyển sang phòng mới.
     * - Phòng cũ: ngày đi = ngày chuyển, giữ bill đã post.
     * - Phòng mới: kế thừa setup EB, kế thừa bill đã post đêm 16 và KHÔNG bị post trùng khi sang ngày.
     */
    public function test_section_02_chuyen_phong_sau_khi_post_truoc_tien_phong_va_co_eb_khong_trung_sang_ngay(): void
    {
        $booking = $this->createBooking('2026-08-16', '2026-08-19');
        $source = $this->createRoom($booking, '1501', '2026-08-16', '2026-08-19', [
            'extra_bed_qty' => 1,
            'extra_bed_rate' => 300000,
        ]);

        // Post trước tiền phòng đêm 16
        $roomBill = ServiceBill::create([
            'OpenTime' => '12:00',
            'Guest' => 'Khach Test',
            'DepartmentId' => 'FO',
            'ServiceId' => 'RM',
            'DescriptionServive' => 'Tiền phòng 16/08',
            'Username' => 'test_operator',
            'RegisterId1' => $booking->id,
            'RentalRoomId1' => $source->id,
            'RegisterID2' => $booking->id,
            'RentalRoomId2' => null,
            'Date' => '2026-08-16',
            'Amount' => 1000000,
            'Quantity' => 1,
            'Status' => 1,
            'Edit' => 0,
        ]);

        RoomNightBill::create([
            'bill_id' => $roomBill->Ma,
            'adult' => 1,
            'is_room_night' => 1,
            'date' => '2026-08-16',
            'room' => $source->room_number,
            'rate' => 1000000,
        ]);

        BookingRoomService::create([
            'booking_room_id' => $source->id,
            'service_code' => 'RM',
            'service_name' => 'Tiền phòng',
            'service_date' => '2026-08-16',
            'quantity' => 1,
            'rate' => 1000000,
            'total_amount' => 1000000,
            'is_posted' => 1,
            'service_bill_id' => $roomBill->Ma,
        ]);

        // Cài đặt EB chưa post cho đêm 17, 18
        BookingRoomService::create([
            'booking_room_id' => $source->id,
            'service_code' => 'EB',
            'service_name' => 'Giường phụ',
            'service_date' => '2026-08-17',
            'quantity' => 1,
            'rate' => 300000,
            'total_amount' => 300000,
            'is_posted' => 0,
        ]);

        // Chuyển sang phòng 902
        $target = $source->moveToRoom('902', '2026-08-16', 'test_operator');

        // Phòng cũ giữ bill đêm 16
        $this->assertSame($source->id, (string) $roomBill->fresh()->RentalRoomId1);

        // Move service nhận diện đêm 16 đã post, không post trùng
        $moveService = app(BookingRoomMoveService::class);
        $this->assertTrue($moveService->hasPostedRoomNight($target, '2026-08-16', true));

        // Phòng mới kế thừa dịch vụ EB của đêm 17
        $this->assertDatabaseHas('booking_room_services', [
            'booking_room_id' => $target->id,
            'service_code' => 'EB',
        ]);
        $this->assertSame(1, BookingRoomService::where('booking_room_id', $target->id)
            ->where('service_code', 'EB')
            ->whereDate('service_date', '2026-08-17')
            ->count());
    }

    /**
     * Section 3: Chuyển phòng sau khi ở qua đêm
     * - Phòng 410 ở từ 15 đến 18/08, ngày hệ thống là 16/08.
     * - Phòng cũ: ngày đi = 16/08, hiển thị bill đêm 15/08 đã phát sinh tại phòng cũ.
     * - Phòng mới: ngày đến 16/08, ngày đi 18/08, chi tiết bill đêm 16, 17/08.
     */
    public function test_section_03_chuyen_phong_sau_khi_o_qua_dem(): void
    {
        $booking = $this->createBooking('2026-08-15', '2026-08-18');
        $room410 = $this->createRoom($booking, '410', '2026-08-15', '2026-08-18');

        // Bill đêm 15/08
        ServiceBill::create([
            'OpenTime' => '12:00',
            'Guest' => 'Khach 410',
            'DepartmentId' => 'FO',
            'ServiceId' => 'RM',
            'DescriptionServive' => 'Tiền phòng đêm 15/08',
            'Username' => 'test_operator',
            'RegisterId1' => $booking->id,
            'RentalRoomId1' => $room410->id,
            'Date' => '2026-08-15',
            'Amount' => 1000000,
            'Quantity' => 1,
            'Status' => 1,
            'Edit' => 0,
        ]);

        // Chuyển sang phòng 902 vào ngày hệ thống 16/08
        $target = $room410->moveToRoom('902', '2026-08-16', 'test_operator');

        // Phòng cũ 410: ngày đi cập nhật thành 16/08, 1 đêm ở thực tế
        $room410Fresh = $room410->fresh();
        $this->assertSame('2026-08-15', $room410Fresh->arrival_date->toDateString());
        $this->assertSame('2026-08-16', $room410Fresh->departure_date->toDateString());
        $this->assertSame(1, (int) $room410Fresh->ActutalNumOfDays);
        $this->assertSame(1, ServiceBill::where('RentalRoomId1', $room410->id)->whereDate('Date', '2026-08-15')->count());

        // Phòng mới 902: 16/08 đến 18/08 (2 đêm)
        $this->assertSame('2026-08-16', $target->arrival_date->toDateString());
        $this->assertSame('2026-08-18', $target->departure_date->toDateString());
        $this->assertSame(2, (int) $target->ActutalNumOfDays);
    }

    /**
     * Section 4: Chuyển khách qua phòng mới (Tách khách)
     * - Phòng 907 ở 11/08 đến 19/08 (2 khách), ngày hệ thống 16/08.
     * - Chuyển khách chính sang phòng trống mới 410. Đăng ký hiển thị 2 phòng.
     * - Phòng 907: khách về 1, khách còn lại thành khách chính.
     * - Phòng 410: ngày đến = 16/08, ngày đi = 19/08 (đêm 16, 17, 18).
     */
    public function test_section_04_chuyen_khach_chinh_sang_phong_moi_tach_thanh_hai_phong(): void
    {
        $booking = $this->createBooking('2026-08-11', '2026-08-19');
        $room907 = $this->createRoom($booking, '907', '2026-08-11', '2026-08-19', ['adults' => 2]);

        $mainGuest = Guest::create(['full_name' => 'Khach Chinh']);
        $subGuest = Guest::create(['full_name' => 'Khach Phu']);

        BookingRoomGuest::create([
            'booking_room_id' => $room907->id,
            'guest_id' => $mainGuest->id,
            'is_primary' => true,
            'status' => BookingRoomGuest::STATUS_CHECKED_IN,
        ]);
        BookingRoomGuest::create([
            'booking_room_id' => $room907->id,
            'guest_id' => $subGuest->id,
            'is_primary' => false,
            'status' => BookingRoomGuest::STATUS_CHECKED_IN,
        ]);

        // Thực hiện chuyển khách chính sang phòng 410 qua API
        $this->postJson("/api/bookings/{$booking->id}/rooms/{$room907->id}/move", [
            'move_type' => 'available',
            'target_room_number' => '410',
            'selected_guest_ids' => [$mainGuest->id],
            'reason' => 'Tách khách sang phòng riêng',
            'is_change_rate' => false,
        ])->assertSuccessful();

        // Booking hiển thị 2 phòng active
        $activeRooms = BookingRoom::where('booking_id', $booking->id)
            ->where('status', BookingRoom::STATUS_CHECKED_IN)
            ->get();
        $this->assertCount(2, $activeRooms);

        // Phòng 907: adults = 1, khách phụ trở thành khách chính
        $room907Fresh = $room907->fresh();
        $this->assertSame(1, (int) $room907Fresh->adults);
        $this->assertDatabaseHas('booking_room_guests', [
            'booking_room_id' => $room907->id,
            'guest_id' => $subGuest->id,
            'is_primary' => true,
            'status' => BookingRoomGuest::STATUS_CHECKED_IN,
        ]);

        // Phòng 410: ngày đến 16/08, ngày đi 19/08, 1 khách chính
        $room410 = BookingRoom::where('booking_id', $booking->id)->where('room_number', '410')->firstOrFail();
        $this->assertSame('2026-08-16', $room410->arrival_date->toDateString());
        $this->assertSame('2026-08-19', $room410->departure_date->toDateString());
        $this->assertSame(1, (int) $room410->adults);
        $this->assertDatabaseHas('booking_room_guests', [
            'booking_room_id' => $room410->id,
            'guest_id' => $mainGuest->id,
            'is_primary' => true,
            'status' => BookingRoomGuest::STATUS_CHECKED_IN,
        ]);
    }

    /**
     * Section 5: Chuyển phòng tại ngày phòng check-out
     * - Chọn phòng có ngày đi = ngày hệ thống (hôm nay check out) chuyển sang phòng mới.
     * - Phòng cũ: giữ đủ bill chi tiết các đêm trước.
     * - Phòng mới: ngày đến = ngày đi = ngày chuyển, số đêm = 0.
     */
    public function test_section_05_chuyen_phong_tai_ngay_phong_check_out_ve_khong_dem(): void
    {
        $booking = $this->createBooking('2026-08-13', '2026-08-16');
        $source = $this->createRoom($booking, '1501', '2026-08-13', '2026-08-16');

        // Chuyển sang phòng 902 vào ngày 16/08
        $target = $source->moveToRoom('902', '2026-08-16', 'test_operator');

        // Phòng mới: ngày đến = ngày đi = 16/08, số đêm = 0
        $this->assertSame('2026-08-16', $target->arrival_date->toDateString());
        $this->assertSame('2026-08-16', $target->departure_date->toDateString());
        $this->assertSame(0, (int) $target->ActutalNumOfDays);
        $this->assertSame(0, (int) $target->NumOfDays);
    }

    /**
     * Section 6: Sửa ngày phòng không tự động kéo ngày Booking
     * - Booking đến 31/08 đi 03/09.
     * - Khi phòng thay đổi ngày (đến 01/09, đi 02/09), ngày Booking giữ nguyên (31/08 - 03/09).
     */
    public function test_section_06_sua_ngay_phong_khong_tu_dong_keo_ngay_booking(): void
    {
        $booking = $this->createBooking('2026-08-31', '2026-09-03');
        $room = $this->createRoom($booking, '1501', '2026-08-31', '2026-09-03');

        // Cập nhật ngày của phòng thu hẹp lại
        $room->update([
            'arrival_date' => '2026-09-01',
            'departure_date' => '2026-09-02',
        ]);

        // Booking header vẫn bảo toàn ngày người dùng đã tạo ban đầu
        $bookingFresh = $booking->fresh();
        $this->assertSame('2026-08-31', $bookingFresh->arrival_date->toDateString());
        $this->assertSame('2026-09-03', $bookingFresh->departure_date->toDateString());
    }

    /**
     * Section 7: Chặn lấy phòng có ngày đến nhỏ hơn ngày hệ thống
     * - Ngày hệ thống = 01/09.
     * - Lấy phòng mới có arrival_date = 31/08 -> Bị chặn với thông báo chuẩn.
     */
    public function test_section_07_chan_lay_phong_co_ngay_den_nho_hon_ngay_he_thong(): void
    {
        SystemDateRoll::create([
            'system_date' => '2026-09-01',
            'actual_date' => '2026-09-01',
            'shift' => '1',
            'username' => 'test_operator',
        ]);

        $booking = $this->createBooking('2026-09-01', '2026-09-05');

        // Gửi yêu cầu thêm phòng có ngày đến 31/08 (< 01/09)
        $response = $this->postJson("/api/bookings/{$booking->id}/add-rooms", [
            'intent' => 'add_only',
            'room_allocations' => [
                [
                    'roomClassId' => $this->roomClass->id,
                    'quantity' => 1,
                    'price' => 1000000,
                    'rooms' => [
                        [
                            'arrivalDate' => '2026-08-31',
                            'departureDate' => '2026-09-03',
                        ],
                    ],
                ],
            ],
        ])->assertStatus(422);

        $response->assertJsonFragment([
            'message' => 'Ngày đến của phòng nhỏ hơn ngày hệ thống, vui lòng kiểm tra lại thông tin.',
        ]);
        $this->assertSame(0, $booking->bookingRooms()->count());
    }

    /**
     * Section 8: Modul Sale xem hóa đơn ở chế độ chỉ đọc
     * - Khi mở hóa đơn từ Sale, các endpoint mutation đều bị chặn 403 Forbidden.
     */
    public function test_section_08_modun_sale_xem_hoa_don_o_che_do_chi_doc_chan_mutations(): void
    {
        $saleUser = User::factory()->create(['username' => 'sale_viewer']);
        $saleRole = Role::create([
            'code' => 'sale_role',
            'name' => 'Sale Role',
            'level' => 3,
            'department_scope' => 'SALE',
            'is_active' => true,
        ]);
        $p1 = Permission::firstOrCreate(['code' => 'fo.booking.edit'], ['name' => 'fo.booking.edit', 'module' => 'FO']);
        $p2 = Permission::firstOrCreate(['code' => 'fo.service.view'], ['name' => 'fo.service.view', 'module' => 'FO']);
        $saleRole->permissions()->syncWithoutDetaching([$p1->id, $p2->id]);
        $saleUser->roles()->attach($saleRole->id);

        $booking = $this->createBooking('2026-08-16', '2026-08-19');
        $room = $this->createRoom($booking, '1501', '2026-08-16', '2026-08-19');

        // User Sale thử gọi endpoint mutation checkout hoặc no-post
        $this->actingAs($saleUser)
            ->postJson("/api/bookings/{$booking->id}/checkout")
            ->assertStatus(403);

        $this->actingAs($saleUser)
            ->patchJson("/api/bookings/{$booking->id}/no-post", ['no_post' => true])
            ->assertStatus(403);
    }

    /**
     * Section 9: Nhân bản Booking theo MÔ TẢ NGHIỆP VỤ !E25
     * - IsCopyAllBooking = 0: chỉ copy header.
     * - IsCopyAllBooking = 1: copy cả phòng, kiểm tra AV, hỏi xác nhận nếu âm phòng/hết phòng.
     */
    public function test_section_09_nhan_ban_booking_theo_mo_ta_nghiep_vu_e25(): void
    {
        $booking = $this->createBooking('2026-08-16', '2026-08-19', ['booking_name' => 'Goc Copy']);
        $room = $this->createRoom($booking, '1501', '2026-08-16', '2026-08-19');

        // 1. IsCopyAllBooking = 0 -> Chỉ copy header
        HotelConfig::updateOrCreate(['name' => 'IsCopyAllBooking'], ['value' => '0']);
        $res0 = $this->postJson("/api/bookings/{$booking->id}/copy", [
            'arrival_date' => '2026-08-20',
            'departure_date' => '2026-08-23',
        ])->assertStatus(201);
        $copy0 = Booking::findOrFail($res0->json('data.id'));
        $this->assertSame(0, $copy0->bookingRooms()->count());

        // 2. IsCopyAllBooking = 1 -> Copy cả phòng
        HotelConfig::updateOrCreate(['name' => 'IsCopyAllBooking'], ['value' => '1']);
        $res1 = $this->postJson("/api/bookings/{$booking->id}/copy", [
            'arrival_date' => '2026-08-20',
            'departure_date' => '2026-08-23',
        ])->assertStatus(201);
        $copy1 = Booking::findOrFail($res1->json('data.id'));
        $this->assertSame(1, $copy1->bookingRooms()->count());
        $this->assertSame('2026-08-16', $copy1->booking_date->toDateString()); // Ngày hệ thống

        // 3. Thiếu phòng + AllowOver = 1 -> Hỏi xác nhận phòng âm
        HotelConfig::updateOrCreate(['name' => 'AllowOverRoomTypeRoomKind'], ['value' => '1']);
        $this->mock(RoomAvailabilityService::class)->shouldReceive('getAvailability')->once()->andReturn(0);
        $resOver = $this->postJson("/api/bookings/{$booking->id}/copy", [
            'arrival_date' => '2026-08-20',
            'departure_date' => '2026-08-23',
        ])->assertStatus(422);
        $resOver->assertJsonPath('require_confirm', 'over_warning');
        $resOver->assertJsonPath('message', 'Phòng âm bạn có muốn tiếp tục thao tác');

        // 4. Thiếu phòng + AllowOver = 0 -> Hỏi xác nhận không còn phòng trống
        HotelConfig::updateOrCreate(['name' => 'AllowOverRoomTypeRoomKind'], ['value' => '0']);
        $this->mock(RoomAvailabilityService::class)->shouldReceive('getAvailability')->once()->andReturn(0);
        $resNoAv = $this->postJson("/api/bookings/{$booking->id}/copy", [
            'arrival_date' => '2026-08-20',
            'departure_date' => '2026-08-23',
        ])->assertStatus(422);
        $resNoAv->assertJsonPath('require_confirm', 'no_rooms_available');
        $resNoAv->assertJsonPath('message', 'Không còn phòng trống, bạn có muốn tiếp tục thao tác');
    }

    /**
     * Section 10: Modul Sale cập nhật phòng Inhouse theo AllowReserUpdateRate_DeptDateRoomInhouse
     * - Giá trị = 0: không cho phép Sale điều chỉnh giá phòng / ngày đi / giờ đi phòng Inhouse.
     * - Giá trị = 1: cho phép Sale điều chỉnh.
     */
    public function test_section_10_modun_sale_cap_nhat_phong_inhouse_theo_thong_so(): void
    {
        $saleUser = User::factory()->create(['username' => 'sale_editor']);
        $role = Role::create([
            'code' => 'sale_inhouse_role',
            'name' => 'Sale Inhouse Role',
            'level' => 3,
            'department_scope' => 'SALE',
            'is_active' => true,
        ]);
        $p = Permission::firstOrCreate(['code' => 'fo.booking.edit'], ['name' => 'fo.booking.edit', 'module' => 'FO']);
        $role->permissions()->attach($p->id);
        $saleUser->roles()->attach($role->id);

        $booking = $this->createBooking('2026-08-16', '2026-08-20');
        $room = $this->createRoom($booking, '1501', '2026-08-16', '2026-08-20');

        // 1. Cấu hình = 0 -> Chặn Sale sửa giá và ngày đi phòng Inhouse
        HotelConfig::updateOrCreate(['name' => 'AllowReserUpdateRate_DeptDateRoomInhouse'], ['value' => '0']);
        $this->actingAs($saleUser)
            ->postJson("/api/bookings/{$booking->id}/rooms/bulk-update", [
                'room_ids' => [$room->id],
                'rate' => 1500000,
                'departure_date' => '2026-08-22',
            ])->assertStatus(403);

        // 2. Cấu hình = 1 -> Cho phép Sale sửa
        HotelConfig::updateOrCreate(['name' => 'AllowReserUpdateRate_DeptDateRoomInhouse'], ['value' => '1']);
        $this->actingAs($saleUser)
            ->postJson("/api/bookings/{$booking->id}/rooms/bulk-update", [
                'room_ids' => [$room->id],
                'rate' => 1500000,
                'departure_date' => '2026-08-22',
            ])->assertSuccessful();

        $this->assertEquals(1500000, (float) $room->fresh()->rate);
        $this->assertSame('2026-08-22', $room->fresh()->departure_date->toDateString());
    }

    /**
     * Section 11: Phân quyền cập nhật booking đã checkout qua RoleUserUpdateCheckoutBooking
     * - Cho phép cập nhật thông tin đăng ký đối với BK đã checkout (bookings.status=2).
     * - Chỉ sửa 4 trường metadata, chặn sửa trường tài chính/ngày.
     */
    public function test_section_11_role_user_cap_nhat_thong_tin_dang_ky_da_checkout(): void
    {
        $checkoutBooking = $this->createBooking('2026-08-10', '2026-08-14', [
            'status' => Booking::STATUS_CHECKOUT,
        ]);

        $authorizedUser = User::factory()->create(['username' => 'auth_checkout_user']);
        $authRole = Role::create([
            'code' => 'role_checkout_updater',
            'name' => 'Checkout Updater',
            'level' => 3,
            'department_scope' => 'FO',
            'is_active' => true,
        ]);
        $p = Permission::firstOrCreate(['code' => 'fo.booking.edit'], ['name' => 'fo.booking.edit', 'module' => 'FO']);
        $authRole->permissions()->attach($p->id);
        $authorizedUser->roles()->attach($authRole->id);

        HotelConfig::updateOrCreate(['name' => 'RoleUserUpdateCheckoutBooking'], ['value' => 'role_checkout_updater']);

        // User chưa có role cập nhật bị 403
        $unauthorizedUser = User::factory()->create(['username' => 'unauth_user']);
        $this->actingAs($unauthorizedUser)
            ->putJson("/api/bookings/{$checkoutBooking->id}", ['booking_name' => 'Ten Moi'])
            ->assertStatus(403);

        // User có role được phép cập nhật metadata
        $this->actingAs($authorizedUser)
            ->putJson("/api/bookings/{$checkoutBooking->id}", [
                'booking_name' => 'Ten Booking Sau Checkout',
                'note' => 'Ghi chu bo sung',
            ])->assertSuccessful();

        $this->assertSame('Ten Booking Sau Checkout', $checkoutBooking->fresh()->booking_name);
        $this->assertSame('Ghi chu bo sung', $checkoutBooking->fresh()->note);

        // Thử sửa trường cấm (ngày đến) -> Bị từ chối 422
        $this->actingAs($authorizedUser)
            ->putJson("/api/bookings/{$checkoutBooking->id}", [
                'arrival_date' => '2026-08-11',
            ])->assertStatus(422);
    }

    /**
     * Section 12: Mở khóa Do Not Move theo RoleUserOpenDoNotMove và fallback người tạo khóa
     * - Người nào khóa Do Not Move thì người đó được phép mở nếu thông số chưa được phân quyền.
     * - Khi thông số được phân quyền: User có role được phép mở.
     */
    public function test_section_12_role_user_open_do_not_move_va_owner_fallback(): void
    {
        $booking = $this->createBooking('2026-08-16', '2026-08-20');
        $room = $this->createRoom($booking, '1501', '2026-08-16', '2026-08-20', ['is_do_not_move' => 1]);

        $lockOwner = User::factory()->create(['username' => 'lock_creator']);
        $otherUser = User::factory()->create(['username' => 'other_user']);
        $p = Permission::firstOrCreate(['code' => 'fo.booking.edit'], ['name' => 'fo.booking.edit', 'module' => 'FO']);

        foreach ([$lockOwner, $otherUser] as $u) {
            $r = Role::create(['code' => 'role_' . $u->username, 'name' => $u->username, 'level' => 3, 'department_scope' => 'FO', 'is_active' => true]);
            $r->permissions()->attach($p->id);
            $u->roles()->attach($r->id);
        }

        RoomDoNotMoveLock::create([
            'booking_room_id' => $room->id,
            'locked_by_user_id' => $lockOwner->getKey(),
            'locked_by_username' => $lockOwner->username,
            'locked_at' => now(),
        ]);

        // 1. Chưa cấu hình role: Người tạo khóa mở được, người khác bị 403
        HotelConfig::where('name', 'RoleUserOpenDoNotMove')->delete();
        $this->actingAs($otherUser)
            ->deleteJson("/api/bookings/{$booking->id}/rooms/{$room->id}/lock-move")
            ->assertStatus(403);

        $this->actingAs($lockOwner)
            ->deleteJson("/api/bookings/{$booking->id}/rooms/{$room->id}/lock-move")
            ->assertSuccessful();

        $this->assertSame(0, (int) $room->fresh()->is_do_not_move);

        // 2. Khóa phòng 902 và cấu hình RoleUserOpenDoNotMove cho other_user
        $room2 = $this->createRoom($booking, '902', '2026-08-16', '2026-08-20', ['is_do_not_move' => 1]);
        $someoneElse = User::factory()->create(['username' => 'someone_else']);
        RoomDoNotMoveLock::create([
            'booking_room_id' => $room2->id,
            'locked_by_user_id' => $someoneElse->getKey(),
            'locked_by_username' => $someoneElse->username,
            'locked_at' => now(),
        ]);
        HotelConfig::updateOrCreate(['name' => 'RoleUserOpenDoNotMove'], ['value' => 'role_other_user']);

        $this->actingAs($otherUser)
            ->deleteJson("/api/bookings/{$booking->id}/rooms/{$room2->id}/lock-move")
            ->assertSuccessful();

        $this->assertSame(0, (int) $room2->fresh()->is_do_not_move);
    }
}
