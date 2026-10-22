<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PrintTemplateSlot;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentReceiptPrintTest extends TestCase
{
    use RefreshDatabase;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\BookingStatusSeeder::class);
        $this->actingAs(User::factory()->create(['username' => 'receipt_user']));
        $this->booking = Booking::create([
            'booking_name' => 'Doan Receipt', 'booking_date' => '2026-09-03',
            'arrival_date' => '2026-09-03', 'departure_date' => '2026-09-04',
            'status' => Booking::STATUS_CHECKIN, 'created_by' => 'receipt_user',
        ]);
    }

    private function makePayment(float $amount, string $description, ?int $bookingId = null): Payment
    {
        return Payment::create([
            'booking_id' => $bookingId ?? $this->booking->id, 'date' => '2026-09-03', 'amount' => $amount,
            'currency' => 'VND', 'payment_method_id' => 'CA', 'description' => $description,
            'edit_flag' => 0, 'status' => Payment::STATUS_PAID, 'folio_id' => 1,
        ]);
    }

    public function test_payment_receipt_renders_real_payment_data_with_the_assigned_template(): void
    {
        $template = Template::create([
            'name' => 'Phieu thu test', 'group' => 'Receipt',
            'content_html' => '<p>{{guest_name}}|{{amount}}|{{amount_in_words}}|{{content}}|{{booking_code}}|{{date}}</p>',
        ]);
        PrintTemplateSlot::where('code', 'PAYMENT_RECEIPT')->update(['template_id' => $template->id]);
        $first = $this->makePayment(1500000, 'Thanh toán tiền phòng');
        $second = $this->makePayment(500000, 'Thanh toán minibar');

        $html = $this->postJson('/api/print-template-slots/payment-receipt', ['payment_ids' => [$first->id, $second->id]])
            ->assertSuccessful()
            ->json('html');

        $this->assertStringContainsString('2,000,000 đ', $html);
        $this->assertStringContainsString('Hai triệu', $html);
        $this->assertStringContainsString('Thanh toán tiền phòng; Thanh toán minibar', $html);
        $this->assertStringContainsString('03/09/2026', $html);
    }

    public function test_payment_receipt_falls_back_to_the_default_template_and_requires_a_single_booking(): void
    {
        PrintTemplateSlot::where('code', 'PAYMENT_RECEIPT')->update(['template_id' => null]);
        $payment = $this->makePayment(100000, 'Cash');
        $this->postJson('/api/print-template-slots/payment-receipt', ['payment_ids' => [$payment->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('template_id');

        // Vị trí chưa gán thiết kế: dùng mẫu mặc định của nhóm Receipt.
        Template::create(['name' => 'Phieu thu mac dinh', 'group' => 'Receipt', 'is_default' => true, 'content_html' => '<p>{{amount}}</p>']);
        $this->postJson('/api/print-template-slots/payment-receipt', ['payment_ids' => [$payment->id]])
            ->assertSuccessful();
        $this->assertStringContainsString('100,000 đ', $this->postJson('/api/print-template-slots/payment-receipt', ['payment_ids' => [$payment->id]])->json('html'));

        $other = Booking::create([
            'booking_name' => 'Khac', 'booking_date' => '2026-09-03', 'arrival_date' => '2026-09-03',
            'departure_date' => '2026-09-04', 'status' => Booking::STATUS_CHECKIN, 'created_by' => 'receipt_user',
        ]);
        $otherPayment = $this->makePayment(200000, 'Cash', $other->id);
        $this->postJson('/api/print-template-slots/payment-receipt', ['payment_ids' => [$payment->id, $otherPayment->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('payment_ids');
    }
}
