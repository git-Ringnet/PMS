<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * TC_BC_BP_01 (BP dòng 162 / Lễ tân dòng 314): báo cáo hóa đơn minibar theo sản phẩm
 * chỉ lấy bill Status = 1 (chưa thanh toán) nên mất các bill đã thanh toán (Status = 2).
 */
class MinibarProductReportPaidBillsTest extends TestCase
{
    private const DATE = '2031-01-15';

    public function test_migration_makes_product_report_include_paid_bills(): void
    {
        $files = glob(database_path('migrations/*_fix_minibar_product_report_include_paid_bills.php')) ?: [];
        $this->assertNotEmpty($files, 'Thiếu migration tạo lại thủ tục rpt_minibar_invoices_by_product.');

        $source = file_get_contents($files[0]);
        $this->assertStringContainsString('h.Status <> 4', $source);
    }

    public function test_procedure_returns_paid_and_unpaid_bills_but_not_cancelled(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL driver required for stored procedure test.');
        }

        DB::beginTransaction();
        try {
            $this->bill(1, 'Pepsi', 1, 150000, deleted: 0);     // chưa thanh toán
            $this->bill(2, 'Coca Cola', 2, 20000, deleted: 0);  // đã thanh toán
            $this->bill(3, 'Sprite', 1, 200000, deleted: 1);    // bill cũ đã sửa: chi tiết đã xóa
            $this->bill(4, '7Up', 1, 15000, deleted: 0);        // đã hủy

            $rows = DB::select('CALL rpt_minibar_invoices_by_product(?,?,?,?,?,?,?)', [
                self::DATE, self::DATE, '', '', '', 0, 0,
            ]);
            $byProduct = collect($rows)->mapWithKeys(fn ($r) => [$r->Product => (float) $r->Quantity])->all();

            $this->assertSame(['Coca Cola' => 2.0, 'Pepsi' => 1.0], $byProduct);
        } finally {
            DB::rollBack();
        }
    }

    private function bill(int $status, string $product, int $qty, int $rate, int $deleted): void
    {
        $id = DB::table('housekeeping_service_bills')->insertGetId([
            'Outlet' => 'MB',
            'Department' => 'HK',
            'Status' => $status,
            'Date' => self::DATE.' 10:00:00',
            'Currency' => 'VND',
        ], 'Ma');

        DB::table('housekeeping_service_bill_details')->insert([
            'BillId' => $id,
            'DetailId' => $id,
            'Product' => $product,
            'MaProduct' => 900000 + $status,
            'Quantity' => $qty,
            'Rate' => $rate,
            'TotalAmount' => $qty * $rate,
            'DiscountAmount' => 0,
            'Deleted' => $deleted,
        ]);
    }
}
