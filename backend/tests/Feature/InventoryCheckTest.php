<?php

namespace Tests\Feature;

use App\Models\InventoryCheck;
use App\Models\InventoryCheckItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_check_and_add_products(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $wh = Warehouse::firstOrCreate(
            ['name' => 'Kho Minibar Test'],
            ['outlet_id' => 'MB', 'is_active' => true]
        );

        $cat = ProductCategory::firstOrCreate(
            ['name' => 'Nước ngọt'],
            ['outlet' => 'minibar', 'is_active' => true]
        );

        $prod = Product::firstOrCreate(
            ['name' => 'Coca Cola Test'],
            [
                'product_category_id' => $cat->id,
                'price' => 20000,
                'is_in_stock' => true,
                'track_stock' => true,
                'is_active' => true,
            ]
        );

        // 1. Tạo phiếu kiểm kê
        $resStore = $this->postJson('/api/inventory/checks', [
            'warehouse_id' => $wh->id,
            'month'        => '2026-08',
            'note'         => 'Test note',
        ]);
        $resStore->assertSuccessful();
        $checkId = $resStore->json('data.id');
        $this->assertNotNull($checkId);

        // 2. Thêm sản phẩm vào phiếu
        $resAddItems = $this->postJson("/api/inventory/checks/{$checkId}/items", [
            'product_ids' => [$prod->id],
        ]);
        $resAddItems->assertStatus(200);
        $this->assertGreaterThan(0, count($resAddItems->json('data.items')));

        // 3. Cập nhật tồn đầu kỳ và thực tế
        $itemId = $resAddItems->json('data.items.0.id');
        $resUpdate = $this->putJson("/api/inventory/checks/{$checkId}/items/{$itemId}", [
            'well_balance' => 10,
            'stoke_take'   => 12,
        ]);
        $resUpdate->assertStatus(200);
        $this->assertEquals(2, $resUpdate->json('data.different_qty'));
    }
}
