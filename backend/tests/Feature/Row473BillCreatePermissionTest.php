<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Task 473 (TC_LT_22 dòng 297 "Check lại option add"): ô Add của màn "Hóa đơn phòng (Bill / Folio)"
 * = fo.bill.create ("Tạo hóa đơn / post charge") phải có tác dụng thật: tạo bill (post dịch vụ,
 * thanh toán) cần cả quyền của màn tương ứng lẫn fo.bill.create.
 */
class Row473BillCreatePermissionTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $codes): User
    {
        $user = User::factory()->create();
        $role = Role::create(['code' => 'r473b_' . uniqid(), 'name' => 'r473b', 'level' => 3, 'is_active' => true]);
        foreach ($codes as $code) {
            $role->permissions()->syncWithoutDetaching([Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'FO'])->id]);
        }
        $user->roles()->attach($role->id);

        return $user;
    }

    /** @return array<string, array{0: string, 1: string, 2: array<int, string>}> */
    public static function billCreatingRoutes(): array
    {
        return [
            'thanh toán booking' => ['/api/bookings/1/settle-payment', 'fo.payment.create', []],
            'thanh toán phòng ảo' => ['/api/virtual-room-folios/1/settle-payment', 'fo.payment.create', []],
            'post bill dịch vụ' => ['/api/booking-room-services/post-fo-service-bill', 'fo.service.add', []],
        ];
    }

    #[DataProvider('billCreatingRoutes')]
    public function test_creating_a_bill_is_blocked_without_bill_create(string $url, string $screenPermission): void
    {
        $user = $this->userWith([$screenPermission, 'fo.bill.view']);

        $this->actingAs($user)->postJson($url, [])->assertStatus(403);
    }

    #[DataProvider('billCreatingRoutes')]
    public function test_creating_a_bill_passes_the_permission_gate_with_bill_create(string $url, string $screenPermission): void
    {
        $user = $this->userWith([$screenPermission, 'fo.bill.create']);

        $status = $this->actingAs($user)->postJson($url, [])->getStatusCode();

        $this->assertNotSame(403, $status, 'Có đủ quyền màn hình + fo.bill.create thì không được bị chặn 403');
    }

    public function test_bill_create_alone_is_not_enough_without_the_screen_permission(): void
    {
        $user = $this->userWith(['fo.bill.create']);

        $this->actingAs($user)->postJson('/api/bookings/1/settle-payment', [])->assertStatus(403);
        $this->actingAs($user)->postJson('/api/booking-room-services/post-fo-service-bill', [])->assertStatus(403);
    }
}
