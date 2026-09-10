<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\PaymentController;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class PaymentSequenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_settlement_codes_continue_after_legacy_numeric_codes_without_rewriting_history(): void
    {
        Payment::create([
            'date' => '2026-09-09',
            'amount' => 100,
            'payment_id' => 17000,
            'status' => Payment::STATUS_PAID,
            'edit_flag' => 0,
        ]);

        $method = new ReflectionMethod(PaymentController::class, 'nextSettlementCode');
        $method->setAccessible(true);
        $controller = app(PaymentController::class);

        [$first, $second] = DB::transaction(function () use ($method, $controller): array {
            return [
                $method->invoke($controller),
                $method->invoke($controller),
            ];
        });

        $this->assertSame('17001', $first);
        $this->assertSame('17002', $second);
        $this->assertDatabaseHas('payments', [
            'payment_id' => 17000,
        ]);
        $this->assertDatabaseHas('payment_sequences', [
            'sequence_key' => 'settlement',
            'current_value' => 17002,
        ]);
    }
}
