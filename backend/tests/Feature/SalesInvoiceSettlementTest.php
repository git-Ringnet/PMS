<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentSequence;
use App\Models\SalesInvoice;
use App\Models\ServiceBill;
use App\Models\SystemDateRoll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesInvoiceSettlementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Booking $booking;
    private BookingRoom $room;
    private Guest $guest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['username' => 'reception_user']);
        $role = \App\Models\Role::firstOrCreate(['code' => 'admin_test'], ['name' => 'Admin test', 'level' => 1, 'is_active' => true]);
        $perm = \App\Models\Permission::firstOrCreate(['code' => 'fo.payment.create'], ['name' => 'fo.payment.create', 'module' => 'FO']);
        $role->permissions()->syncWithoutDetaching([$perm->id]);
        $this->user->roles()->attach($role->id);

        $this->actingAs($this->user);

        SystemDateRoll::create([
            'system_date' => '2026-09-16',
            'actual_date' => '2026-09-16',
            'shift'       => '1',
            'username'    => $this->user->username,
        ]);

        PaymentMethod::create(['code' => 'CA', 'name' => 'Tiền mặt', 'payment_group' => 1]);
        PaymentMethod::create(['code' => 'CK', 'name' => 'Chuyển khoản', 'payment_group' => 1]);

        PaymentSequence::firstOrCreate(
            ['sequence_key' => 'settlement'],
            ['current_value' => 9000]
        );
        PaymentSequence::firstOrCreate(
            ['sequence_key' => 'sales_invoice'],
            ['current_value' => 5000]
        );

        DB::table('booking_statuses')->insertOrIgnore([
            ['id' => 0, 'name' => 'Reservation'],
            ['id' => 1, 'name' => 'Checked In'],
            ['id' => 2, 'name' => 'Checked Out'],
            ['id' => 3, 'name' => 'Deleted'],
        ]);

        $this->booking = Booking::create([
            'booking_code'   => 'BK260916001',
            'booking_name'   => 'Nguyễn Văn Test',
            'booking_date'   => '2026-09-16',
            'arrival_date'   => '2026-09-16',
            'departure_date' => '2026-09-17',
            'created_by'     => $this->user->username,
            'status'         => Booking::STATUS_CHECKIN,
        ]);

        $this->guest = Guest::create([
            'full_name' => 'Nguyễn Văn Test',
            'phone'     => '0901234567',
        ]);

        $form = \App\Models\RoomForm::create(['name' => 'Standard']);
        $class = \App\Models\RoomClass::create([
            'code'      => 'STD',
            'name'      => 'Standard',
            'is_active' => true,
        ]);
        \App\Models\Room::create([
            'room_number'   => '102',
            'room_form_id'  => $form->id,
            'room_class_id' => $class->id,
            'floor'         => 1,
            'status'        => 'occupied',
        ]);

        $this->room = BookingRoom::create([
            'booking_id'     => $this->booking->id,
            'room_class_id'  => $class->id,
            'room_number'    => '102',
            'arrival_date'   => '2026-09-16',
            'departure_date' => '2026-09-17',
            'status'         => BookingRoom::STATUS_CHECKED_IN,
        ]);
    }

    public function test_settlement_creates_sales_invoice_record_with_tax_breakdown_and_links(): void
    {
        // 1. Tạo 2 dịch vụ:
        // Service 1: 1.000.000 VND, ServiceCharge 5%, Tax 8%
        $bill1 = ServiceBill::create([
            'Guest'          => 'Nguyễn Văn Test',
            'DepartmentId'   => 'FO',
            'ServiceId'      => 'LA',
            'Username'       => $this->user->username,
            'Date'           => '2026-09-16',
            'OpenTime'       => '08:00:00',
            'Amount'         => 1000000,
            'ServiceCharge'  => 5,
            'SpecialTax'     => 0,
            'Tax'            => 8,
            'Exchange'       => 1,
            'Edit'           => 0,
            'Status'         => 1,
            'Folio'          => '1',
            'RentalRoomId1'  => (string) $this->room->id,
            'RentalRoomId2'  => (string) $this->room->id,
            'RegisterId1'    => (string) $this->booking->id,
            'RegisterID2'    => (string) $this->booking->id,
        ]);

        // Service 2: 500.000 VND, không thuế phí
        $bill2 = ServiceBill::create([
            'Guest'          => 'Nguyễn Văn Test',
            'DepartmentId'   => 'FO',
            'ServiceId'      => 'FB',
            'Username'       => $this->user->username,
            'Date'           => '2026-09-16',
            'OpenTime'       => '09:00:00',
            'Amount'         => 500000,
            'ServiceCharge'  => 0,
            'SpecialTax'     => 0,
            'Tax'            => 0,
            'Exchange'       => 1,
            'Edit'           => 0,
            'Status'         => 1,
            'Folio'          => '1',
            'RentalRoomId1'  => (string) $this->room->id,
            'RentalRoomId2'  => (string) $this->room->id,
            'RegisterId1'    => (string) $this->booking->id,
            'RegisterID2'    => (string) $this->booking->id,
        ]);

        // Tổng tiền cần thanh toán = 1.500.000 VND
        $response = $this->postJson("/api/bookings/{$this->booking->id}/settle-payment", [
            'folio_id'        => '1',
            'booking_room_id' => $this->room->id,
            'payments'        => [
                ['payment_method_id' => 'CA', 'amount' => 1500000, 'note' => 'Tiền mặt'],
            ],
            'department_id'   => 'FO',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        // 2. Kiểm tra bản ghi trong bảng vật lý sales_invoices
        $salesInvoice = SalesInvoice::where('booking_id', $this->booking->id)->first();
        $this->assertNotNull($salesInvoice);
        $this->assertEquals(1500000, (float) $salesInvoice->amount);
        $this->assertEquals('00005001', $salesInvoice->bill_id);
        $this->assertEquals('R:102', $salesInvoice->room);
        $this->assertEquals(1, $salesInvoice->status);

        // Kiểm tra đẳng thức công thức: OriginalRate + Tax + ServiceChargeAmount = Amount
        $sum = (float) $salesInvoice->original_rate + (float) $salesInvoice->tax + (float) $salesInvoice->service_charge_amount;
        $this->assertEqualsWithDelta(1500000.0, $sum, 0.05);

        // 3. Kiểm tra mối quan hệ 3 bảng vật lý: service_bills.InvoiceId = payments.invoice_id = sales_invoices.id
        $bill1->refresh();
        $bill2->refresh();
        $this->assertEquals((string) $salesInvoice->id, (string) $bill1->InvoiceId);
        $this->assertEquals((string) $salesInvoice->id, (string) $bill2->InvoiceId);

        $payment = Payment::where('booking_id', $this->booking->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals($salesInvoice->id, $payment->invoice_id);

        // Kiểm tra Eloquent Relationships
        $this->assertEquals($salesInvoice->id, $payment->salesInvoice->id);
        $this->assertCount(2, $salesInvoice->serviceBills);
        $this->assertCount(1, $salesInvoice->payments);
    }

    public function test_payment_cancellation_marks_sales_invoice_status_as_cancelled(): void
    {
        // Tạo dịch vụ 800.000
        $bill = ServiceBill::create([
            'Guest'          => 'Nguyễn Văn Test',
            'DepartmentId'   => 'FO',
            'ServiceId'      => 'LA',
            'Username'       => $this->user->username,
            'Date'           => '2026-09-16',
            'OpenTime'       => '10:00:00',
            'Amount'         => 800000,
            'Exchange'       => 1,
            'Edit'           => 0,
            'Status'         => 1,
            'Folio'          => '1',
            'RentalRoomId1'  => (string) $this->room->id,
            'RentalRoomId2'  => (string) $this->room->id,
            'RegisterId1'    => (string) $this->booking->id,
            'RegisterID2'    => (string) $this->booking->id,
        ]);

        $this->postJson("/api/bookings/{$this->booking->id}/settle-payment", [
            'folio_id'        => '1',
            'booking_room_id' => $this->room->id,
            'payments'        => [
                ['payment_method_id' => 'CA', 'amount' => 800000],
            ],
            'department_id'   => 'FO',
        ])->assertOk();

        $payment = Payment::where('booking_id', $this->booking->id)->where('status', Payment::STATUS_PAID)->first();
        $this->assertNotNull($payment);
        $this->assertNotNull($payment->invoice_id);

        // Hủy payment
        $delResponse = $this->deleteJson("/api/payments/{$payment->id}", [
            'reason' => 'Khách hủy hóa đơn',
        ]);
        $delResponse->assertOk();

        // sales_invoices phải đổi status về 0
        $salesInvoice = SalesInvoice::find($payment->invoice_id);
        $this->assertNotNull($salesInvoice);
        $this->assertEquals(0, $salesInvoice->status);

        // Bill được nhả InvoiceId
        $bill->refresh();
        $this->assertNull($bill->InvoiceId);
    }
}
