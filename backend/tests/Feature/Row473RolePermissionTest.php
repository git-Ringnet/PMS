<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Task 473 (TC_LT_22 dòng 297/298): quyền đặt cọc tách riêng, sửa/xóa vai trò. */
class Row473RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'BookingStatusSeeder']);
        Shift::create(['name' => '1', 'start_time' => '06:00:00', 'end_time' => '14:00:00']);
        PaymentMethod::create(['code' => 'CA', 'name' => 'Cash', 'payment_group' => 1]);
    }

    private function userWith(array $permissionCodes, string $roleCode = 'row473'): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['code' => $roleCode], ['name' => $roleCode, 'level' => 3, 'is_active' => true]);
        foreach ($permissionCodes as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'FO']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $user->roles()->attach($role->id);

        return $user;
    }

    private function booking(User $user): Booking
    {
        return Booking::create([
            'booking_name' => 'ROW473', 'arrival_date' => now()->toDateString(), 'departure_date' => now()->addDay()->toDateString(),
            'num_of_days' => 1, 'booking_date' => now()->toDateString(), 'created_by' => $user->username,
        ]);
    }

    private function depositPayload(array $extra = []): array
    {
        return array_merge([
            'date' => now()->toDateString(), 'open_time' => '10:00', 'shift_id' => '1',
            'amount' => 500000, 'payment_method_id' => 'CA', 'description' => 'Deposit',
        ], $extra);
    }

    public function test_user_without_deposit_create_cannot_add_a_deposit_even_with_payment_create(): void
    {
        $user = $this->userWith(['fo.payment.create']);
        $booking = $this->booking($user);

        $this->actingAs($user)->postJson("/api/bookings/{$booking->id}/payments", $this->depositPayload())
            ->assertStatus(403);
        $this->assertSame(0, Payment::count());
    }

    public function test_user_with_deposit_create_can_add_a_deposit(): void
    {
        $user = $this->userWith(['fo.deposit.create']);
        $booking = $this->booking($user);

        $this->actingAs($user)->postJson("/api/bookings/{$booking->id}/payments", $this->depositPayload())
            ->assertStatus(201);
    }

    public function test_advance_payment_still_uses_the_payment_permission(): void
    {
        $user = $this->userWith(['fo.payment.create']);
        $booking = $this->booking($user);

        $this->actingAs($user)->postJson("/api/bookings/{$booking->id}/payments", $this->depositPayload(['pack4' => Payment::PACK4_ADVANCE]))
            ->assertStatus(201);
    }

    public function test_edit_and_delete_deposit_need_their_own_permissions(): void
    {
        $creator = $this->userWith(['fo.deposit.create']);
        $booking = $this->booking($creator);
        $paymentId = $this->actingAs($creator)->postJson("/api/bookings/{$booking->id}/payments", $this->depositPayload())
            ->assertStatus(201)->json('data.id');

        $this->actingAs($creator)->putJson("/api/payments/{$paymentId}", ['description' => 'Deposit (Cash) đổi'])->assertStatus(403);
        $this->actingAs($creator)->deleteJson("/api/payments/{$paymentId}", ['reason' => 'test'])->assertStatus(403);

        $editor = $this->userWith(['fo.deposit.edit'], 'row473_edit');
        $this->actingAs($editor)->putJson("/api/payments/{$paymentId}", ['description' => 'Deposit (Cash) đổi'])->assertOk();
        $this->actingAs($editor)->deleteJson("/api/payments/{$paymentId}", ['reason' => 'test'])->assertStatus(403);

        $deleter = $this->userWith(['fo.deposit.delete'], 'row473_delete');
        $this->actingAs($deleter)->deleteJson("/api/payments/{$paymentId}", ['reason' => 'test'])->assertOk();
    }

    public function test_role_update_changes_name_but_never_the_code(): void
    {
        $admin = $this->userWith(['system.user.manage'], 'row473_admin');
        $role = Role::create(['code' => 'row473_target', 'name' => 'Tên cũ', 'level' => 3, 'is_active' => true]);

        $this->actingAs($admin)->putJson("/api/roles/{$role->id}", ['name' => 'Tên mới', 'code' => 'doi_ma'])->assertOk();

        $role->refresh();
        $this->assertSame('Tên mới', $role->name);
        $this->assertSame('row473_target', $role->code);
    }

    public function test_role_assigned_to_an_employee_cannot_be_deleted(): void
    {
        $admin = $this->userWith(['system.user.manage'], 'row473_admin');
        $role = Role::create(['code' => 'row473_used', 'name' => 'Đang dùng', 'level' => 3, 'is_active' => true]);
        User::factory()->create()->roles()->attach($role->id);
        $free = Role::create(['code' => 'row473_free', 'name' => 'Chưa dùng', 'level' => 3, 'is_active' => true]);

        $this->actingAs($admin)->deleteJson("/api/roles/{$role->id}")->assertStatus(422);
        $this->assertDatabaseHas('roles', ['id' => $role->id]);

        $this->actingAs($admin)->deleteJson("/api/roles/{$free->id}")->assertOk();
        $this->assertDatabaseMissing('roles', ['id' => $free->id]);
    }
}
