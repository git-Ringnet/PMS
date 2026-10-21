<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentDebtSettlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentDebtSettlementLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_list_returns_active_debt_settlement_total_per_payment(): void
    {
        $this->seed(\Database\Seeders\BookingStatusSeeder::class);
        $this->actingAs(User::factory()->create(['username' => 'debt_label_user']));
        $booking = Booking::create([
            'booking_name' => 'Debt label', 'booking_date' => '2026-09-03',
            'arrival_date' => '2026-09-03', 'departure_date' => '2026-09-04',
            'status' => Booking::STATUS_CHECKIN, 'created_by' => 'debt_label_user',
        ]);
        $makePayment = fn (float $amount) => Payment::create([
            'booking_id' => $booking->id, 'date' => '2026-09-03', 'amount' => $amount, 'currency' => 'VND',
            'payment_method_id' => 'AC', 'edit_flag' => 0, 'status' => Payment::STATUS_PAID, 'folio_id' => 1,
        ]);
        $settle = fn (Payment $payment, float $amount, int $editFlag = 0) => PaymentDebtSettlement::create([
            'payment_id' => $payment->id, 'payment_date' => '2026-09-03', 'payment_time' => '16:33:00',
            'payment_method_id' => 'BT', 'amount' => $amount, 'currency' => 'VND', 'edit_flag' => $editFlag,
        ]);

        $full = $makePayment(3764500);
        $settle($full, 3000000);
        $settle($full, 764500);
        $partial = $makePayment(1000000);
        $settle($partial, 400000);
        $settle($partial, 600000, 1); // Dòng giải trừ đã hủy không được cộng.
        $unpaid = $makePayment(500000);

        $payments = collect($this->getJson('/api/bookings?with_billing=true')
            ->assertSuccessful()
            ->json('data.0.payments'))->keyBy('id');

        $this->assertEquals(3764500, $payments[$full->id]['settled_amount']);
        $this->assertEquals(400000, $payments[$partial->id]['settled_amount']);
        $this->assertEquals(0, (float) $payments[$unpaid->id]['settled_amount']);
    }
}
