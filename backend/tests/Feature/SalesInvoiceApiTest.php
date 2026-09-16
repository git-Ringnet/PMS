<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\SalesInvoiceController;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Company;
use App\Models\Guest;
use App\Models\HotelSetting;
use App\Models\PaymentMethod;
use App\Models\PaymentSequence;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\SalesInvoice;
use App\Models\ServiceBill;
use App\Models\SystemDateRoll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesInvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Booking $booking;
    private BookingRoom $room;
    private Guest $guest;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['username' => 'reception_test']);
        $role = Role::firstOrCreate(['code' => 'admin_test'], ['name' => 'Admin test', 'level' => 1, 'is_active' => true]);
        $perm = \App\Models\Permission::firstOrCreate(['code' => 'fo.payment.create'], ['name' => 'fo.payment.create', 'module' => 'FO']);
        $role->permissions()->syncWithoutDetaching([$perm->id]);
        $this->user->roles()->attach($role->id);
        $this->actingAs($this->user);

        HotelSetting::create([
            'hotel_name' => 'Khách Sạn Biển Xanh',
            'address'    => '123 Đường Trần Phú, Nha Trang',
            'phone'      => '0258 3888 999',
            'tax_code'   => '4201234567',
        ]);

        SystemDateRoll::create([
            'system_date' => '2026-09-16',
            'actual_date' => '2026-09-16',
            'shift'       => '2',
            'username'    => $this->user->username,
        ]);

        PaymentMethod::create(['code' => 'CA', 'name' => 'Tiền mặt', 'payment_group' => 1]);
        PaymentMethod::create(['code' => 'CK', 'name' => 'Chuyển khoản', 'payment_group' => 1]);

        PaymentSequence::firstOrCreate(['sequence_key' => 'settlement'], ['current_value' => 8000]);
        PaymentSequence::firstOrCreate(['sequence_key' => 'sales_invoice'], ['current_value' => 6000]);

        DB::table('booking_statuses')->insertOrIgnore([
            ['id' => 0, 'name' => 'Reservation'],
            ['id' => 1, 'name' => 'Checked In'],
            ['id' => 2, 'name' => 'Checked Out'],
            ['id' => 3, 'name' => 'Deleted'],
        ]);

        $this->company = Company::create([
            'name'     => 'Công ty Du Lịch ABC',
            'tax_code' => '0102030405',
        ]);

        $this->booking = Booking::create([
            'booking_code'   => 'BK260916002',
            'booking_name'   => 'Trần Thị Hoa',
            'booking_date'   => '2026-09-16',
            'arrival_date'   => '2026-09-16',
            'departure_date' => '2026-09-18',
            'company_id'     => $this->company->id,
            'created_by'     => $this->user->username,
            'status'         => Booking::STATUS_CHECKIN,
        ]);

        $this->guest = Guest::create([
            'full_name' => 'Trần Thị Hoa',
            'phone'     => '0912345678',
        ]);

        $form = RoomForm::create(['name' => 'Deluxe']);
        $class = RoomClass::create(['code' => 'DLX', 'name' => 'Deluxe', 'is_active' => true]);
        Room::create([
            'room_number'   => '201',
            'room_form_id'  => $form->id,
            'room_class_id' => $class->id,
            'floor'         => 2,
            'status'        => 'occupied',
        ]);

        $this->room = BookingRoom::create([
            'booking_id'     => $this->booking->id,
            'room_class_id'  => $class->id,
            'room_number'    => '201',
            'arrival_date'   => '2026-09-16',
            'departure_date' => '2026-09-18',
            'status'         => BookingRoom::STATUS_CHECKED_IN,
        ]);
    }

    private function createSettledInvoice(float $amount = 2160000): SalesInvoice
    {
        // 1 bill dịch vụ có thuế 8% và phí phục vụ 5%
        ServiceBill::create([
            'Guest'              => 'Trần Thị Hoa',
            'DepartmentId'       => 'FO',
            'ServiceId'          => 'ROOM',
            'DescriptionServive' => 'Tiền phòng 2 đêm',
            'Username'           => $this->user->username,
            'Date'               => '2026-09-16',
            'OpenTime'           => '10:00:00',
            'Quantity'           => 2,
            'Amount'             => $amount,
            'ServiceCharge'      => 5,
            'Tax'                => 8,
            'Exchange'           => 1,
            'Edit'               => 0,
            'Status'             => 1,
            'Folio'              => '1',
            'RentalRoomId1'      => (string) $this->room->id,
            'RentalRoomId2'      => (string) $this->room->id,
            'RegisterId1'        => (string) $this->booking->id,
            'RegisterID2'        => (string) $this->booking->id,
        ]);

        $res = $this->postJson("/api/bookings/{$this->booking->id}/settle-payment", [
            'folio_id'        => '1',
            'booking_room_id' => $this->room->id,
            'payments'        => [
                ['payment_method_id' => 'CA', 'amount' => $amount, 'note' => 'Tiền mặt'],
            ],
            'department_id'   => 'FO',
            'shift_id'        => '2',
        ]);

        $res->assertOk();

        return SalesInvoice::where('booking_id', $this->booking->id)->latest('id')->firstOrFail();
    }

    public function test_index_lists_sales_invoices_with_summary_and_pagination(): void
    {
        $this->createSettledInvoice(2160000);

        $response = $this->getJson('/api/sales-invoices');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'bill_id',
                    'invoice_date',
                    'payment_date',
                    'amount',
                    'original_rate',
                    'service_charge_amount',
                    'tax',
                    'room',
                    'guest_name',
                    'department',
                    'ca',
                    'status',
                    'booking',
                    'company',
                ],
            ],
            'summary' => [
                'total_invoices',
                'total_amount',
                'total_original_rate',
                'total_service_charge',
                'total_tax',
                'total_discount',
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ]);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals(1, $response->json('summary.total_invoices'));
        $this->assertEquals(2160000, (float) $response->json('summary.total_amount'));
        $this->assertEquals('2', $response->json('data.0.ca'));
    }

    public function test_index_filters_by_date_room_and_status(): void
    {
        $this->createSettledInvoice(2160000);

        // Lọc đúng phòng 201
        $resMatching = $this->getJson('/api/sales-invoices?room=201');
        $resMatching->assertOk();
        $this->assertCount(1, $resMatching->json('data'));

        // Lọc phòng khác không có kết quả
        $resNonMatching = $this->getJson('/api/sales-invoices?room=999');
        $resNonMatching->assertOk();
        $this->assertCount(0, $resNonMatching->json('data'));

        // Lọc theo khoảng ngày
        $resDate = $this->getJson('/api/sales-invoices?from_date=2026-09-16&to_date=2026-09-16');
        $resDate->assertOk();
        $this->assertCount(1, $resDate->json('data'));

        // Lọc theo status = 1 (hoạt động)
        $resActive = $this->getJson('/api/sales-invoices?status=1');
        $resActive->assertOk();
        $this->assertCount(1, $resActive->json('data'));

        // Lọc status = 0 (đã hủy)
        $resCancelled = $this->getJson('/api/sales-invoices?status=0');
        $resCancelled->assertOk();
        $this->assertCount(0, $resCancelled->json('data'));
    }

    public function test_show_returns_invoice_with_service_bills_and_payments(): void
    {
        $invoice = $this->createSettledInvoice(2160000);

        $response = $this->getJson("/api/sales-invoices/{$invoice->id}");

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.id', $invoice->id);
        $response->assertJsonPath('data.bill_id', $invoice->bill_id);
        $response->assertJsonPath('data.guest_name', 'Trần Thị Hoa');

        // Kiểm tra nạp kèm relations
        $this->assertNotEmpty($response->json('data.service_bills'));
        $this->assertNotEmpty($response->json('data.payments'));
        $this->assertEquals(2160000, (float) $response->json('data.payments.0.amount'));
        $this->assertEquals('Tiền mặt', $response->json('data.payments.0.payment_method.name'));
    }

    public function test_by_booking_returns_invoices_for_booking(): void
    {
        $invoice = $this->createSettledInvoice(2160000);

        $response = $this->getJson("/api/bookings/{$this->booking->id}/sales-invoices");

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($invoice->id, $response->json('data.0.id'));
    }

    public function test_print_data_returns_full_receipt_payload_with_vietnamese_words(): void
    {
        $invoice = $this->createSettledInvoice(2160000);

        $response = $this->getJson("/api/sales-invoices/{$invoice->id}/print");

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.hotel.name', 'Khách Sạn Biển Xanh');
        $response->assertJsonPath('data.invoice.bill_id', $invoice->bill_id);
        $response->assertJsonPath('data.invoice.currency', 'VND');
        $this->assertNotEmpty($response->json('data.items'));
        $this->assertNotEmpty($response->json('data.payments'));

        // Kiểm tra đọc số tiền bằng chữ
        $words = $response->json('data.invoice.amount_in_words');
        $this->assertStringContainsString('đồng chẵn', $words);
        $this->assertStringContainsString('Hai triệu', $words);
    }

    public function test_vietnamese_words_converter_handles_various_numbers(): void
    {
        $this->assertEquals('Không đồng', SalesInvoiceController::numberToVietnameseWords(0));
        $this->assertEquals('Một triệu đồng chẵn', SalesInvoiceController::numberToVietnameseWords(1000000));
        $this->assertEquals('Hai triệu một trăm sáu mươi nghìn đồng chẵn', SalesInvoiceController::numberToVietnameseWords(2160000));
        $this->assertEquals('Hai mươi lăm nghìn đồng chẵn', SalesInvoiceController::numberToVietnameseWords(25000));
        $this->assertEquals('Mười lăm triệu đồng chẵn', SalesInvoiceController::numberToVietnameseWords(15000000));
        $this->assertEquals('Một trăm linh năm nghìn đồng chẵn', str_replace('lẻ', 'linh', SalesInvoiceController::numberToVietnameseWords(105000)));
    }
}
