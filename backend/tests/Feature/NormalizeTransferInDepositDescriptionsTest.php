<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 228 (tracker dòng 228): dòng nhận cọc cũ "[TRANSFER IN X=>Y] nội dung" được đổi sang
 * "nội dung (X=>Y)" giống 2 dòng cũ của lần chuyển; dòng đã xóa giữ " Correct: lý do" ở cuối.
 */
class NormalizeTransferInDepositDescriptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'BookingStatusSeeder']);
    }

    private function migration(): object
    {
        $files = glob(database_path('migrations/*_normalize_transfer_in_deposit_descriptions.php')) ?: [];
        $this->assertNotEmpty($files, 'Thiếu migration chuẩn hóa mô tả dòng nhận cọc.');

        return require $files[0];
    }

    private function payment(Booking $booking, string $description, array $extra = []): Payment
    {
        return Payment::create(array_merge([
            'booking_id' => $booking->id, 'date' => now()->toDateString(), 'open_time' => '10:00:00', 'amount' => 100000,
            'pack2' => 'DPR', 'folio_id' => 1, 'status' => 1, 'edit_flag' => 0, 'payment_method_id' => 'CA',
            'description' => $description, 'shift' => '1',
        ], $extra));
    }

    public function test_old_transfer_in_descriptions_use_the_same_format_as_the_transferred_rows(): void
    {
        $booking = Booking::create([
            'booking_name' => 'GAL1', 'arrival_date' => now()->toDateString(), 'departure_date' => now()->addDay()->toDateString(),
            'num_of_days' => 1, 'booking_date' => now()->toDateString(), 'created_by' => 'tester',
        ]);

        $active = $this->payment($booking, '[TRANSFER IN BK_23=>BK_39] Deposit (Cash)');
        $room = $this->payment($booking, '[TRANSFER IN R_608=>R_705] Advance Payment (Bank transfer)', ['pack4' => 'AP']);
        $deletedPositive = $this->payment($booking, '[TRANSFER IN BK_7=>BK_11] Deposit (Cash) Correct: huy', ['status' => 3, 'edit_flag' => 1]);
        $deletedNegative = $this->payment($booking, '[TRANSFER IN BK_7=>BK_11] Deposit (Cash) Correct: huy', ['status' => 3, 'edit_flag' => 1, 'amount' => -100000]);
        $deletedPositive->delete(); // dòng gốc đã xóa mềm
        $untouched = $this->payment($booking, 'Deposit (Cash) (BK_7=>BK_11)', ['status' => 4, 'edit_flag' => 1]);
        $plain = $this->payment($booking, 'Deposit (Cash)');

        $this->migration()->up();
        $this->migration()->up(); // chạy lại không đổi thêm

        $this->assertSame('Deposit (Cash) (BK_23=>BK_39)', $active->fresh()->description);
        $this->assertSame('Advance Payment (Bank transfer) (R_608=>R_705)', $room->fresh()->description);
        $this->assertSame('Deposit (Cash) (BK_7=>BK_11) Correct: huy', Payment::withTrashed()->find($deletedPositive->id)->description);
        $this->assertSame('Deposit (Cash) (BK_7=>BK_11) Correct: huy', $deletedNegative->fresh()->description);
        $this->assertSame('Deposit (Cash) (BK_7=>BK_11)', $untouched->fresh()->description);
        $this->assertSame('Deposit (Cash)', $plain->fresh()->description);
    }
}
