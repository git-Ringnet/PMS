<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\HotelConfig;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Checklist Lễ tân - Đặt cọc: dòng 146 (HTTT cọc), 150 (sửa cọc giữ ghi chú), 151-153 (tách/chuyển/xóa cọc - task 228).
 */
class PaymentDepositRows146To153Test extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'BookingStatusSeeder']);
        Shift::create(['name' => '1', 'start_time' => '06:00:00', 'end_time' => '14:00:00']);
        Shift::create(['name' => '2', 'start_time' => '14:00:00', 'end_time' => '22:00:00']);
        Shift::create(['name' => '3', 'start_time' => '22:00:00', 'end_time' => '06:00:00']);

        $this->user = User::factory()->create();
        $role = Role::firstOrCreate(['code' => 'deposit_test'], ['name' => 'Deposit test', 'level' => 3, 'department_scope' => 'FO', 'is_active' => true]);
        $permission = Permission::firstOrCreate(['code' => 'fo.payment.create'], ['name' => 'fo.payment.create', 'module' => 'FO']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        foreach (['fo.deposit.create', 'fo.deposit.edit', 'fo.deposit.delete'] as $depositCode) {
            $role->permissions()->syncWithoutDetaching([Permission::firstOrCreate(['code' => $depositCode], ['name' => $depositCode, 'module' => 'FO'])->id]);
        }
        $this->user->roles()->attach($role->id);
        HotelConfig::updateOrCreate(['name' => 'RuleUserCorrectOrPostBillPaymentOldDay'], ['value' => 'deposit_test']);

        PaymentMethod::create(['code' => 'CA', 'name' => 'Cash', 'payment_group' => 1]);
        PaymentMethod::create(['code' => 'BT', 'name' => 'Bank transfer', 'payment_group' => 2]);
        PaymentMethod::create(['code' => 'AC', 'name' => 'City ledger', 'payment_group' => 4]);
        PaymentMethod::create(['code' => 'CL', 'name' => 'Complimentary', 'payment_group' => 5, 'is_free' => true]);
        // HTTT miễn phí nhưng chưa gán nhóm (dữ liệu thật: "Miễn phí cán bộ nv")
        PaymentMethod::create(['code' => 'C2', 'name' => 'Miễn phí cán bộ nv', 'payment_group' => null, 'is_free' => true]);
        PaymentMethod::create(['code' => 'VO', 'name' => 'Voucher', 'payment_group' => 3, 'is_inactive' => true]);
    }

    private function booking(): Booking
    {
        return Booking::create([
            'booking_name' => 'GAL1', 'arrival_date' => now()->toDateString(), 'departure_date' => now()->addDay()->toDateString(),
            'num_of_days' => 1, 'booking_date' => now()->toDateString(), 'created_by' => $this->user->username,
        ]);
    }

    private function addDeposit(Booking $booking, string $method): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user)->postJson("/api/bookings/{$booking->id}/payments", [
            'date' => now()->toDateString(), 'open_time' => '10:00', 'shift_id' => '1',
            'amount' => 500000, 'payment_method_id' => $method, 'description' => 'Deposit',
        ]);
    }

    private function makeDeposit(Booking $booking, array $extra = []): Payment
    {
        return Payment::create(array_merge([
            'booking_id' => $booking->id, 'date' => now()->toDateString(), 'open_time' => '10:00:00', 'amount' => 600000,
            'pack2' => 'DPR', 'folio_id' => 1, 'status' => 1, 'edit_flag' => 0, 'payment_method_id' => 'CA',
            'description' => 'Deposit (Cash)', 'shift' => '1', 'created_by' => $this->user->username,
        ], $extra));
    }

    // ---------------- Dòng 146 ----------------

    public function test_row_146_deposit_rejects_free_debt_and_inactive_methods_and_accepts_normal_one(): void
    {
        $booking = $this->booking();

        foreach (['C2', 'CL', 'AC', 'VO'] as $code) {
            $this->addDeposit($booking, $code)->assertStatus(422);
        }
        $this->assertDatabaseCount('payments', 0);

        $this->addDeposit($booking, 'BT')->assertCreated();
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_row_146_editing_a_deposit_cannot_switch_to_a_free_method(): void
    {
        $booking = $this->booking();
        $deposit = $this->makeDeposit($booking);

        $this->actingAs($this->user)->putJson("/api/payments/{$deposit->id}", ['payment_method_id' => 'C2'])->assertStatus(422);
        $this->assertSame('CA', $deposit->fresh()->payment_method_id);
    }

    // ---------------- Task 228: lưu sai ca ----------------

    public function test_task_228_payment_shift_is_saved_by_shift_name_not_by_shift_id(): void
    {
        // Cấu hình thật: ca tên "0" (22:00-05:59) có id 1, ca tên "1" (06:00-13:59) có id 2, ca tên "2" có id 3.
        Shift::query()->delete();
        \Illuminate\Support\Facades\DB::table('shifts')->insert([
            ['id' => 1, 'name' => '0', 'start_time' => '22:00:00', 'end_time' => '05:59:00'],
            ['id' => 2, 'name' => '1', 'start_time' => '06:00:00', 'end_time' => '13:59:00'],
            ['id' => 3, 'name' => '2', 'start_time' => '14:00:00', 'end_time' => '21:59:00'],
        ]);
        $booking = $this->booking();

        // 9:30 chọn ca "1" (giao diện gửi id = 2) -> phải lưu ca 1, không phải ca 2.
        $this->actingAs($this->user)->postJson("/api/bookings/{$booking->id}/payments", [
            'date' => now()->toDateString(), 'open_time' => '09:30', 'shift_id' => '2',
            'amount' => 100000, 'payment_method_id' => 'CA',
        ])->assertCreated();
        $this->assertSame('1', (string) Payment::latest('id')->first()->shift);

        // 23:00, không chọn ca -> ca "0" (tên "0" không được rơi về id).
        $this->actingAs($this->user)->postJson("/api/bookings/{$booking->id}/payments", [
            'date' => now()->toDateString(), 'open_time' => '23:00', 'amount' => 100000, 'payment_method_id' => 'CA',
        ])->assertCreated();
        $this->assertSame('0', (string) Payment::latest('id')->first()->shift);

        // Giờ 09:30 nhưng chọn ca id 3 (ca "2") -> vẫn bị từ chối.
        $this->actingAs($this->user)->postJson("/api/bookings/{$booking->id}/payments", [
            'date' => now()->toDateString(), 'open_time' => '09:30', 'shift_id' => '3',
            'amount' => 100000, 'payment_method_id' => 'CA',
        ])->assertStatus(422)->assertJsonValidationErrors('shift_id');
    }

    // ---------------- Dòng 153 (task 228): xóa cọc ----------------

    public function test_row_153_delete_deposit_marks_both_old_rows_deleted_with_correct_note_and_shift(): void
    {
        $booking = $this->booking();
        $deposit = $this->makeDeposit($booking);

        $this->actingAs($this->user)->deleteJson("/api/payments/{$deposit->id}", ['reason' => 'khách hủy'])->assertSuccessful();

        $rows = Payment::withTrashed()->where('booking_id', $booking->id)->orderBy('id')->get();
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertSame(1, (int) $row->edit_flag);
            $this->assertSame(Payment::STATUS_DELETED, (int) $row->status);
            $this->assertSame('Deposit (Cash) Correct: khách hủy', $row->description);
        }
        $negative = $rows->firstWhere('amount', '<', 0);
        $this->assertEquals(-600000, $negative->amount);
        $this->assertNotNull($negative->shift, 'Dòng âm không được để shift NULL');
    }

    // ---------------- Dòng 152 (task 228): chuyển cọc ----------------

    public function test_row_152_transfer_deposit_marks_both_old_rows_transferred_with_same_description(): void
    {
        $source = $this->booking();
        $target = $this->booking();
        $deposit = $this->makeDeposit($source);

        $this->actingAs($this->user)->postJson("/api/payments/{$deposit->id}/transfer", ['target_booking_id' => $target->id])->assertSuccessful();

        $expected = "Deposit (Cash) (BK_{$source->id}=>BK_{$target->id})";
        $old = Payment::withTrashed()->where('booking_id', $source->id)->orderBy('id')->get();
        $this->assertCount(2, $old);
        foreach ($old as $row) {
            $this->assertSame(1, (int) $row->edit_flag);
            $this->assertSame(Payment::STATUS_TRANSFERRED, (int) $row->status);
            $this->assertSame($expected, $row->description);
        }
        $this->assertNotNull($old->firstWhere('amount', '<', 0)->shift, 'Dòng âm không được để shift NULL');

        $in = Payment::where('booking_id', $target->id)->first();
        $this->assertSame(0, (int) $in->edit_flag);
        $this->assertSame(Payment::STATUS_PENDING, (int) $in->status);
        $this->assertSame("[TRANSFER IN BK_{$source->id}=>BK_{$target->id}] Deposit (Cash)", $in->description);
        $this->assertSame('1', (string) $in->shift);
    }

    // ---------------- Dòng 151 (task 228): tách cọc ----------------

    public function test_row_151_split_deposit_keeps_total_and_copies_shift_to_new_rows(): void
    {
        $booking = $this->booking();
        $deposit = $this->makeDeposit($booking);

        $this->actingAs($this->user)->postJson("/api/payments/{$deposit->id}/split", ['amounts' => [400000, 200000]])->assertSuccessful();

        $rows = Payment::where('booking_id', $booking->id)->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertEquals(600000, $rows->sum('amount'));
        foreach ($rows as $row) {
            $this->assertSame(0, (int) $row->edit_flag);
            $this->assertSame(Payment::STATUS_PENDING, (int) $row->status);
            $this->assertSame('1', (string) $row->shift);
            $this->assertSame('Deposit (Cash)', $row->description);
        }
    }

    // ---------------- Dòng 150 ----------------

    public function test_row_150_changing_method_keeps_the_note_the_user_typed(): void
    {
        $booking = $this->booking();
        $deposit = $this->makeDeposit($booking);

        // Giao diện đã đổi mô tả theo HTTT mới, người dùng gõ thêm ghi chú rồi bấm Lưu.
        $this->actingAs($this->user)->putJson("/api/payments/{$deposit->id}", [
            'payment_method_id' => 'BT', 'description' => 'Deposit (Bank transfer) - khách VIP chuyển khoản',
        ])->assertSuccessful();

        $fresh = $deposit->fresh();
        $this->assertSame('BT', $fresh->payment_method_id);
        $this->assertSame('Deposit (Bank transfer) - khách VIP chuyển khoản', $fresh->description);
    }

    public function test_row_150_changing_method_realigns_a_stale_description_with_the_new_method(): void
    {
        $booking = $this->booking();
        $deposit = $this->makeDeposit($booking, ['description' => 'Deposit (Cash) - ghi chú cũ']);

        // Client cũ gửi lại mô tả chưa đổi: tên HTTT cũ phải được thay bằng HTTT mới, phần ghi chú giữ nguyên.
        $this->actingAs($this->user)->putJson("/api/payments/{$deposit->id}", [
            'payment_method_id' => 'BT', 'description' => 'Deposit (Cash) - ghi chú cũ',
        ])->assertSuccessful();

        $this->assertSame('Deposit (Bank transfer) - ghi chú cũ', $deposit->fresh()->description);
    }

    public function test_row_150_changing_method_without_description_generates_one(): void
    {
        $booking = $this->booking();
        $deposit = $this->makeDeposit($booking);

        $this->actingAs($this->user)->putJson("/api/payments/{$deposit->id}", ['payment_method_id' => 'BT'])->assertSuccessful();

        $this->assertSame('Deposit (Bank transfer)', $deposit->fresh()->description);
    }
}
