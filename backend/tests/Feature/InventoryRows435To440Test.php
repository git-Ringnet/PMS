<?php

namespace Tests\Feature;

use App\Models\InventoryCheck;
use App\Models\InventoryCheckItem;
use App\Models\InventoryDailyLog;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Dòng 438, 440: Tồn kho - chuyển kho và Get bill.
 */
class InventoryRows435To440Test extends TestCase
{
    use RefreshDatabase;

    private Warehouse $minibar;
    private Warehouse $amenity;
    private Product $coca;
    private Product $mirinda;
    private Product $pepsi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->withRole('branch_admin')->create());

        $this->minibar = Warehouse::create(['name' => 'Kho Minibar', 'outlet_id' => 'MB', 'is_active' => true]);
        $this->amenity = Warehouse::create(['name' => 'Kho Amenity', 'outlet_id' => 'AM', 'is_active' => true]);
        $drinks = ProductCategory::create(['name' => 'Nước ngọt', 'outlet' => 'minibar', 'is_active' => true]);
        foreach (['coca' => 'Coca Cola', 'mirinda' => 'Mirinda', 'pepsi' => 'Pepsi'] as $key => $name) {
            $this->{$key} = Product::create(['product_category_id' => $drinks->id, 'name' => $name, 'price' => 20000, 'track_stock' => true, 'is_active' => true, 'inventory_id' => (string) $this->minibar->id]);
        }
    }

    private function check(Warehouse $warehouse, array $balances, string $month = '2026-09'): InventoryCheck
    {
        $check = InventoryCheck::create(['warehouse_id' => $warehouse->id, 'month' => $month]);
        foreach ($balances as $productId => $qty) {
            InventoryCheckItem::create(['check_id' => $check->id, 'product_id' => $productId, 'well_balance' => $qty, 'stoke_take' => $qty]);
        }
        return $check;
    }

    private function log(Warehouse $warehouse, Product $product, string $date, array $values): void
    {
        InventoryDailyLog::create(array_merge(['warehouse_id' => $warehouse->id, 'date' => $date, 'product_id' => $product->id], $values));
    }

    private function logOf(Warehouse $warehouse, Product $product, string $date): ?InventoryDailyLog
    {
        return InventoryDailyLog::where(['warehouse_id' => $warehouse->id, 'date' => $date, 'product_id' => $product->id])->first();
    }

    private function transfer(Product $product, string $date, float $qty)
    {
        return $this->postJson('/api/inventory/transfer', [
            'warehouse_id' => $this->minibar->id, 'transfer_to_warehouse_id' => $this->amenity->id,
            'product_id' => $product->id, 'date' => $date, 'quantity' => $qty,
        ]);
    }

    // ---------------- Dòng 438 ----------------

    public function test_row438_transferred_product_appears_in_destination_warehouse(): void
    {
        $this->check($this->minibar, [$this->coca->id => 50]);

        $this->transfer($this->coca, '2026-09-05', 20)->assertOk();

        $rows = $this->getJson("/api/inventory/checks?warehouse_id={$this->amenity->id}&month=2026-09")->assertOk()->json('data.items');
        $this->assertSame([$this->coca->id], array_column($rows, 'product_id'));
        $this->assertEquals(20, $this->getJson("/api/inventory/logs?warehouse_id={$this->amenity->id}&month=2026-09")->json("data.{$this->coca->id}.2026-09-05.receive"));
    }

    public function test_row438_transfer_keeps_existing_destination_items(): void
    {
        $this->check($this->minibar, [$this->coca->id => 50]);
        $this->check($this->amenity, [$this->pepsi->id => 7]);

        $this->transfer($this->coca, '2026-09-05', 20)->assertOk();

        $items = InventoryCheck::where('warehouse_id', $this->amenity->id)->first()->items->keyBy('product_id');
        $this->assertCount(2, $items);
        $this->assertEquals(7, $items[$this->pepsi->id]->stoke_take);
        $this->assertEquals(0, $items[$this->coca->id]->stoke_take);
    }

    public function test_row438_saving_a_transfer_does_not_overwrite_other_log_rows(): void
    {
        // Minibar đã có phát sinh của nhiều sản phẩm/ngày trước khi chuyển kho.
        $this->check($this->minibar, [$this->coca->id => 50, $this->mirinda->id => 50, $this->pepsi->id => 50]);
        $this->log($this->minibar, $this->coca, '2026-09-02', ['receive' => 25, 'export' => 2]);
        $this->log($this->minibar, $this->pepsi, '2026-09-02', ['export' => 2]);
        $this->log($this->minibar, $this->mirinda, '2026-09-05', ['export' => 1]);
        $this->log($this->amenity, $this->mirinda, '2026-09-05', ['receive' => 3]);

        // Chuyển Mirinda 2 lần cùng ngày: lần 2 cập nhật dòng đã có (trước đây UPDATE không WHERE).
        $this->transfer($this->mirinda, '2026-09-05', 5)->assertOk();
        $this->transfer($this->mirinda, '2026-09-05', 4)->assertOk();

        $this->assertEquals(9, $this->logOf($this->minibar, $this->mirinda, '2026-09-05')->transfer);
        $this->assertEquals(1, $this->logOf($this->minibar, $this->mirinda, '2026-09-05')->export);
        $this->assertEquals(12, $this->logOf($this->amenity, $this->mirinda, '2026-09-05')->receive);
        // Các dòng khác giữ nguyên.
        $coca = $this->logOf($this->minibar, $this->coca, '2026-09-02');
        $this->assertEquals([25, 2, 0], [$coca->receive, $coca->export, $coca->transfer]);
        $pepsi = $this->logOf($this->minibar, $this->pepsi, '2026-09-02');
        $this->assertEquals([0, 2, 0], [$pepsi->receive, $pepsi->export, $pepsi->transfer]);
        $this->assertSame(4, InventoryDailyLog::count());
    }

    public function test_row438_editing_one_cell_only_changes_that_cell(): void
    {
        $this->log($this->minibar, $this->coca, '2026-09-02', ['receive' => 10]);
        $this->log($this->minibar, $this->pepsi, '2026-09-03', ['receive' => 4]);

        $this->putJson('/api/inventory/logs', [
            'warehouse_id' => $this->minibar->id, 'date' => '2026-09-02', 'product_id' => $this->coca->id, 'receive' => 25,
        ])->assertOk();

        $this->assertEquals(25, $this->logOf($this->minibar, $this->coca, '2026-09-02')->receive);
        $this->assertEquals(4, $this->logOf($this->minibar, $this->pepsi, '2026-09-03')->receive);
    }

    public function test_row438_transfer_restores_a_soft_deleted_destination_check(): void
    {
        $this->check($this->minibar, [$this->coca->id => 50]);
        $this->check($this->amenity, [])->delete();

        $this->transfer($this->coca, '2026-09-05', 5)->assertOk();

        $check = InventoryCheck::where('warehouse_id', $this->amenity->id)->where('month', '2026-09')->first();
        $this->assertNotNull($check);
        $this->assertSame([$this->coca->id], $check->items->pluck('product_id')->all());
    }

    // ---------------- Dòng 440 ----------------

    private function bill(string $date, array $lines): void
    {
        $billId = DB::table('housekeeping_service_bills')->insertGetId([
            'Date' => $date, 'Outlet' => 'MB', 'Department' => 'HK', 'BillEdit' => 0, 'Status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ], 'Ma');
        $detailId = 0;
        foreach ($lines as $productId => $qty) {
            DB::table('housekeeping_service_bill_details')->insert([
                'BillId' => $billId, 'DetailId' => ++$detailId, 'Product' => (string) $productId,
                'MaProduct' => $productId, 'Quantity' => $qty, 'Deleted' => 0,
            ]);
        }
    }

    public function test_row440_get_bill_only_takes_products_in_the_check(): void
    {
        $check = $this->check($this->minibar, [$this->coca->id => 10, $this->mirinda->id => 10]);
        $this->bill('2026-09-05', [$this->mirinda->id => 2, $this->pepsi->id => 1]);

        $res = $this->postJson('/api/inventory/get-bill', ['warehouse_id' => $this->minibar->id, 'month' => '2026-09'])->assertOk();

        $this->assertSame(1, $res->json('updated'));
        $this->assertSame(1, $res->json('skipped'));
        $this->assertEqualsCanonicalizing([$this->coca->id, $this->mirinda->id], $check->items()->pluck('product_id')->all());
        $this->assertEquals(2, $this->logOf($this->minibar, $this->mirinda, '2026-09-05')->export);
        $this->assertNull($this->logOf($this->minibar, $this->pepsi, '2026-09-05'));
    }

    public function test_row440_get_bill_for_a_single_day_also_skips_products_outside_the_check(): void
    {
        $this->check($this->minibar, [$this->coca->id => 10]);
        $this->bill('2026-09-05', [$this->coca->id => 3, $this->pepsi->id => 1]);

        $this->postJson('/api/inventory/get-bill', ['warehouse_id' => $this->minibar->id, 'date' => '2026-09-05'])->assertOk();

        $this->assertEquals(3, $this->logOf($this->minibar, $this->coca, '2026-09-05')->export);
        $this->assertNull($this->logOf($this->minibar, $this->pepsi, '2026-09-05'));
    }

    public function test_row440_get_bill_without_a_check_asks_to_create_one_first(): void
    {
        $this->bill('2026-09-05', [$this->coca->id => 3]);

        $this->postJson('/api/inventory/get-bill', ['warehouse_id' => $this->minibar->id, 'month' => '2026-09'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(0, InventoryCheck::count());
        $this->assertSame(0, InventoryDailyLog::count());
    }
}
