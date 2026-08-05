<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PaymentDebtSettlement;
use App\Models\PaymentMethod;
use App\Models\SystemDateRoll;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebtSettlementTest extends TestCase
{
    use RefreshDatabase;

    private Payment $debtPayment;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['username' => 'debt_settlement_user']);
        $this->actingAs($this->user);
        SystemDateRoll::create([
            'system_date' => '2026-08-05',
            'actual_date' => '2026-08-05',
            'shift' => '1',
            'username' => $this->user->username,
        ]);

        PaymentMethod::create(['code' => 'AC', 'name' => 'Công nợ', 'payment_group' => 4]);
        PaymentMethod::create(['code' => 'CA', 'name' => 'Tiền mặt', 'payment_group' => 1]);
        PaymentMethod::create(['code' => 'IN', 'name' => 'Ngưng dùng', 'payment_group' => 1, 'is_inactive' => true]);
        PaymentMethod::create(['code' => 'FR', 'name' => 'Miễn phí', 'payment_group' => 5, 'is_free' => true]);

        $this->debtPayment = Payment::create([
            'date' => '2026-08-05',
            'amount' => 1000000,
            'payment_method_id' => 'AC',
            'payment_id' => 10001,
            'status' => Payment::STATUS_PAID,
            'edit_flag' => 0,
        ]);
    }

    public function test_creates_debt_settlement_with_valid_method_and_time(): void
    {
        $this->postJson("/api/payments/{$this->debtPayment->id}/debt-settlements", [
            'payment_date' => '2026-08-05',
            'payment_time' => '10:53',
            'payment_method_id' => 'CA',
            'amount' => 350000,
            'description' => 'Thu công nợ đợt 1',
        ])->assertCreated();

        $this->assertDatabaseHas('payment_debt_settlements', [
            'payment_id' => $this->debtPayment->id,
            'payment_method_id' => 'CA',
            'payment_time' => '10:53',
            'amount' => 350000,
            'edit_flag' => 0,
        ]);
    }

    public function test_rejects_invalid_method_and_amount_over_remaining_debt(): void
    {
        $payload = [
            'payment_date' => '2026-08-05',
            'payment_time' => '10:53',
            'amount' => 100000,
        ];

        $this->postJson("/api/payments/{$this->debtPayment->id}/debt-settlements", [...$payload, 'payment_method_id' => 'AC'])
            ->assertStatus(422);
        $this->postJson("/api/payments/{$this->debtPayment->id}/debt-settlements", [...$payload, 'payment_method_id' => 'IN'])
            ->assertStatus(422);
        $this->postJson("/api/payments/{$this->debtPayment->id}/debt-settlements", [...$payload, 'payment_method_id' => 'FR'])
            ->assertStatus(422);
        $this->postJson("/api/payments/{$this->debtPayment->id}/debt-settlements", [...$payload, 'payment_method_id' => 'UNKNOWN'])
            ->assertStatus(422);
        $this->postJson("/api/payments/{$this->debtPayment->id}/debt-settlements", [...$payload, 'payment_method_id' => 'CA', 'amount' => 1000001])
            ->assertStatus(422);
    }

    public function test_blocks_old_day_without_permission_and_soft_deletes_settlement(): void
    {
        UserSetting::create([
            'user_id' => $this->user->id,
            'settings' => ['RuleUserCorrectOrPostBillPaymentOldDay' => false],
        ]);

        $this->postJson("/api/payments/{$this->debtPayment->id}/debt-settlements", [
            'payment_date' => '2026-08-04',
            'payment_time' => '10:53',
            'payment_method_id' => 'CA',
            'amount' => 100000,
        ])->assertForbidden();

        $settlement = PaymentDebtSettlement::create([
            'payment_id' => $this->debtPayment->id,
            'payment_date' => '2026-08-05',
            'payment_time' => '10:53',
            'payment_method_id' => 'CA',
            'amount' => 100000,
            'currency' => 'VND',
            'edit_flag' => 0,
            'created_by' => $this->user->username,
        ]);

        $this->deleteJson("/api/payments/{$this->debtPayment->id}/debt-settlements/{$settlement->id}")
            ->assertSuccessful();

        $this->assertDatabaseHas('payment_debt_settlements', [
            'id' => $settlement->id,
            'edit_flag' => 1,
            'deleted_by' => $this->user->username,
        ]);
    }

    public function test_rejects_settlement_for_deleted_debt_payment(): void
    {
        $this->debtPayment->update(['edit_flag' => 1, 'status' => Payment::STATUS_DELETED]);

        $this->postJson("/api/payments/{$this->debtPayment->id}/debt-settlements", [
            'payment_date' => '2026-08-05',
            'payment_time' => '10:53',
            'payment_method_id' => 'CA',
            'amount' => 100000,
        ])->assertStatus(422);
    }
}
