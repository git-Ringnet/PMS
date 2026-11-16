<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\InventoryCheckController;
use App\Models\InventoryCheck;
use App\Models\InventoryCheckItem;
use App\Models\InventoryDailyLog;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dòng 372: kiểm kê tồn kho định kỳ.
 */
class InventoryPeriodicCheckTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $minibar;
    private Warehouse $laundry;
    private Product $coca;
    private Product $pepsi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->withRole('branch_admin')->create());

        $this->minibar = Warehouse::create(['name' => 'Kho Minibar', 'outlet_id' => 'MB', 'is_active' => true]);
        $this->laundry = Warehouse::create(['name' => 'Kho Giặt ủi', 'outlet_id' => 'GU', 'is_active' => true]);
        $drinks = ProductCategory::create(['name' => 'Nước ngọt', 'outlet' => 'minibar', 'is_active' => true]);
        $laundry = ProductCategory::create(['name' => 'Giặt ủi', 'outlet' => 'giatui', 'is_active' => true]);

        $this->coca = Product::create(['product_category_id' => $drinks->id, 'name' => 'Coca Cola', 'price' => 20000, 'track_stock' => true, 'is_active' => true, 'inventory_id' => (string) $this->minibar->id]);
        $this->pepsi = Product::create(['product_category_id' => $drinks->id, 'name' => 'Pepsi', 'price' => 20000, 'track_stock' => true, 'is_active' => true, 'inventory_id' => (string) $this->minibar->id]);
        Product::create(['product_category_id' => $drinks->id, 'name' => 'Nước lon chưa gán kho', 'price' => 18000, 'track_stock' => true, 'is_active' => true]);
        // Không theo dõi tồn kho
        Product::create(['product_category_id' => $drinks->id, 'name' => 'Nước suối free', 'price' => 0, 'track_stock' => false, 'is_active' => true, 'inventory_id' => (string) $this->minibar->id]);
        // Thuộc kho khác
        Product::create(['product_category_id' => $laundry->id, 'name' => 'Giặt áo sơ mi', 'price' => 30000, 'track_stock' => true, 'is_active' => true, 'inventory_id' => (string) $this->laundry->id]);
    }

    private function save(array $items, bool $confirmNegative = false, string $month = '2026-09')
    {
        return $this->postJson('/api/inventory/checks/save', [
            'warehouse_id' => $this->minibar->id,
            'month' => $month,
            'note' => 'Kiểm kê tháng 9',
            'confirm_negative' => $confirmNegative,
            'items' => $items,
        ]);
    }

    public function test_add_product_list_shows_tracked_products_of_the_warehouse_and_unassigned_products(): void
    {
        $tree = $this->getJson("/api/inventory/products-in-stock?warehouse_id={$this->minibar->id}")->assertOk()->json('data');

        $names = collect($tree)->flatMap(fn ($wh) => collect($wh['categories'])->flatMap(fn ($c) => array_column($c['products'], 'name')))->sort()->values()->all();
        $this->assertSame(['Coca Cola', 'Nước lon chưa gán kho', 'Pepsi'], $names);
        // Kho kiểm kê có thể gồm nhiều outlet; nhóm theo outlet của sản phẩm.
        $this->assertSame('minibar', $tree[0]['name']);
        $this->assertSame('Chưa gán kho (Kiểm kê nội bộ)', $tree[1]['name']);
    }

    public function test_check_is_only_created_when_saved(): void
    {
        $this->assertSame(0, InventoryCheck::count());

        $response = $this->save([
            ['product_id' => $this->coca->id, 'well_balance' => 10, 'stoke_take' => 12],
            ['product_id' => $this->pepsi->id, 'well_balance' => 5, 'stoke_take' => 5],
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertSame(1, InventoryCheck::count());
        $items = collect($response->json('data.items'))->keyBy('product_name');
        $this->assertEquals(2, $items['Coca Cola']['different_qty']);
        $this->assertEquals(0, $items['Pepsi']['different_qty']);
        $this->assertFalse($response->json('data.has_logs'));
    }

    public function test_saving_again_updates_and_removes_items_not_in_list(): void
    {
        $this->save([
            ['product_id' => $this->coca->id, 'well_balance' => 10, 'stoke_take' => 10],
            ['product_id' => $this->pepsi->id, 'well_balance' => 5, 'stoke_take' => 5],
        ])->assertOk();

        $this->save([['product_id' => $this->coca->id, 'well_balance' => 8, 'stoke_take' => 7]])->assertOk();

        $this->assertSame(1, InventoryCheck::count());
        $this->assertSame(1, InventoryCheckItem::count());
        $this->assertEquals(8, InventoryCheckItem::first()->well_balance);
    }

    public function test_negative_opening_stock_requires_confirmation(): void
    {
        $items = [['product_id' => $this->coca->id, 'well_balance' => -3, 'stoke_take' => 0]];

        $this->save($items)->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('needs_negative_confirm', true)
            ->assertJsonPath('negative_products.0', 'Coca Cola');
        $this->assertSame(0, InventoryCheck::count());

        $this->save($items, true)->assertOk()->assertJsonPath('success', true);
        $this->assertEquals(-3, InventoryCheckItem::first()->well_balance);
    }

    public function test_check_with_activity_cannot_edit_numbers_or_delete_but_can_append_products(): void
    {
        $checkId = $this->save([['product_id' => $this->coca->id, 'well_balance' => 10, 'stoke_take' => 10]])->json('data.id');
        InventoryDailyLog::create(['warehouse_id' => $this->minibar->id, 'date' => '2026-09-05', 'product_id' => $this->coca->id, 'receive' => 0, 'export' => 2, 'transfer' => 0]);

        $this->getJson("/api/inventory/checks?warehouse_id={$this->minibar->id}&month=2026-09")->assertJsonPath('data.has_logs', true);

        // Sửa số liệu sản phẩm đã có -> chặn
        $this->save([['product_id' => $this->coca->id, 'well_balance' => 99, 'stoke_take' => 99]])
            ->assertStatus(422)->assertJsonPath('message', InventoryCheckController::LOCKED_MESSAGE);
        // Bỏ sản phẩm đã có khỏi phiếu -> chặn
        $this->save([['product_id' => $this->pepsi->id, 'well_balance' => 1, 'stoke_take' => 1]])->assertStatus(422);
        $itemId = InventoryCheckItem::first()->id;
        $this->putJson("/api/inventory/checks/{$checkId}/items/{$itemId}", ['well_balance' => 50])->assertStatus(422);
        // Xóa phiếu -> chặn
        $this->deleteJson("/api/inventory/checks/{$checkId}")->assertStatus(422);
        $this->assertEquals(10, InventoryCheckItem::first()->well_balance);

        // Thêm sản phẩm mới (giữ nguyên sản phẩm cũ) -> cho phép
        $this->save([
            ['product_id' => $this->coca->id, 'well_balance' => 10, 'stoke_take' => 10],
            ['product_id' => $this->pepsi->id, 'well_balance' => 5, 'stoke_take' => 4],
        ])->assertOk()->assertJsonPath('success', true);
        $this->assertSame(2, InventoryCheckItem::count());
        $this->assertEquals(10, InventoryCheckItem::where('product_id', $this->coca->id)->first()->well_balance);
        $this->assertEquals(5, InventoryCheckItem::where('product_id', $this->pepsi->id)->first()->well_balance);

        // Thêm bằng nút Thêm sản phẩm trực tiếp -> cũng cho phép
        $other = Product::create(['product_category_id' => $this->coca->product_category_id, 'name' => 'Fanta', 'price' => 20000, 'track_stock' => true, 'is_active' => true, 'inventory_id' => (string) $this->minibar->id]);
        $this->postJson("/api/inventory/checks/{$checkId}/items", ['product_ids' => [$other->id]])->assertOk();
        $this->assertSame(3, InventoryCheckItem::count());
    }

    public function test_add_product_list_groups_by_outlet_name_not_warehouse_name(): void
    {
        $snack = ProductCategory::create(['name' => 'Bánh snack', 'outlet' => 'Minibar', 'is_active' => true]);
        $amenity = ProductCategory::create(['name' => 'Đồ dùng', 'outlet' => 'Amenity', 'is_active' => true]);
        foreach (['Oishi', 'Bánh Danisa'] as $n) {
            Product::create(['product_category_id' => $snack->id, 'name' => $n, 'price' => 5000, 'track_stock' => true, 'is_active' => true, 'inventory_id' => (string) $this->minibar->id]);
        }
        Product::create(['product_category_id' => $amenity->id, 'name' => 'Bàn chải', 'price' => 5000, 'track_stock' => true, 'is_active' => true, 'inventory_id' => (string) $this->minibar->id]);

        $tree = collect($this->getJson("/api/inventory/products-in-stock?warehouse_id={$this->minibar->id}")->assertOk()->json('data'));

        $this->assertNotContains('Kho Minibar', $tree->pluck('name')->all());
        $this->assertContains('Amenity', $tree->pluck('name')->all());
        $minibarGroup = $tree->firstWhere('name', 'Minibar');
        $snackNames = collect($minibarGroup['categories'])->firstWhere('name', 'Bánh snack')['products'];
        $this->assertEqualsCanonicalizing(['Oishi', 'Bánh Danisa'], array_column($snackNames, 'name'));
    }

    public function test_warehouse_with_check_cannot_be_deleted_but_can_be_hidden(): void
    {
        $this->save([['product_id' => $this->coca->id, 'well_balance' => 10, 'stoke_take' => 10]])->assertOk();

        $this->deleteJson("/api/warehouses/{$this->minibar->id}")->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertNotNull(Warehouse::find($this->minibar->id));

        $this->putJson("/api/warehouses/{$this->minibar->id}", ['is_active' => false])->assertOk();
        $ids = collect($this->getJson('/api/warehouses')->json('data'))->pluck('id')->all();
        $this->assertNotContains($this->minibar->id, $ids);
        $this->assertSame(1, InventoryCheck::count());

        // Kho chưa có phiếu/nhật ký vẫn xóa được
        $this->deleteJson("/api/warehouses/{$this->laundry->id}")->assertOk();
    }

    public function test_sync_previous_month_returns_draft_values_without_saving(): void
    {
        // Tháng 8: tồn 5, xuất 8 -> tồn cuối -3 (âm)
        $this->save([['product_id' => $this->coca->id, 'well_balance' => 5, 'stoke_take' => 5]], false, '2026-08');
        InventoryDailyLog::create(['warehouse_id' => $this->minibar->id, 'date' => '2026-08-10', 'product_id' => $this->coca->id, 'receive' => 0, 'export' => 8, 'transfer' => 0]);

        $response = $this->postJson('/api/inventory/checks/sync-previous-month', ['warehouse_id' => $this->minibar->id, 'month' => '2026-09'])
            ->assertOk();

        $this->assertEquals(-3, $response->json('items.0.well_balance'));
        $this->assertSame('Coca Cola', $response->json('items.0.product_name'));
        $this->assertSame(1, $response->json('negative_count'));
        $this->assertNull(InventoryCheck::where('month', '2026-09')->first());
    }
}
