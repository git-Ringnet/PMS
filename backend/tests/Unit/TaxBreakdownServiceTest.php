<?php

namespace Tests\Unit;

use App\Services\TaxBreakdownService;
use PHPUnit\Framework\TestCase;

class TaxBreakdownServiceTest extends TestCase
{
    /**
     * Test công thức chuẩn khi có đủ cả 3 loại thuế phí:
     * Phí dịch vụ 5%, Thuế TTĐB 10%, Thuế VAT 10%.
     */
    public function test_breakdown_with_all_taxes_and_service_charge(): void
    {
        // Amount = 1.270.500
        // Net = 1.000.000, SC = 50.000, ST = 105.000, VAT = 115.500
        $result = TaxBreakdownService::breakdown(1270500, 5, 10, 10);

        $this->assertEquals(1000000.0, $result['net_total']);
        $this->assertEquals(1000000.0, $result['original_rate']);
        $this->assertEquals(50000.0, $result['service_charge_amount']);
        $this->assertEquals(105000.0, $result['special_tax_amount']);
        $this->assertEquals(115500.0, $result['tax_amount']);
        $this->assertEquals(1270500.0, $result['amount']);

        // Đẳng thức bất biến
        $sum = $result['net_total'] + $result['service_charge_amount'] + $result['special_tax_amount'] + $result['tax_amount'];
        $this->assertEquals(1270500.0, $sum);
    }

    /**
     * Test trường hợp phổ biến: Phí DV 5% và VAT 10%, Thuế TTĐB 0%.
     */
    public function test_breakdown_with_service_charge_and_vat(): void
    {
        $result = TaxBreakdownService::breakdown(1155000, 5, 0, 10);

        $this->assertEquals(1000000.0, $result['net_total']);
        $this->assertEquals(50000.0, $result['service_charge_amount']);
        $this->assertEquals(0.0, $result['special_tax_amount']);
        $this->assertEquals(105000.0, $result['tax_amount']);
        $this->assertEquals(1155000.0, $result['amount']);

        $sum = $result['net_total'] + $result['service_charge_amount'] + $result['special_tax_amount'] + $result['tax_amount'];
        $this->assertEquals(1155000.0, $sum);
    }

    /**
     * Test trường hợp chỉ có VAT 10%, không có SC và ST.
     */
    public function test_breakdown_with_only_vat(): void
    {
        $result = TaxBreakdownService::breakdown(1100000, 0, 0, 10);

        $this->assertEquals(1000000.0, $result['net_total']);
        $this->assertEquals(0.0, $result['service_charge_amount']);
        $this->assertEquals(0.0, $result['special_tax_amount']);
        $this->assertEquals(100000.0, $result['tax_amount']);

        $sum = $result['net_total'] + $result['service_charge_amount'] + $result['special_tax_amount'] + $result['tax_amount'];
        $this->assertEquals(1100000.0, $sum);
    }

    /**
     * Test trường hợp không có thuế phí nào (0%).
     */
    public function test_breakdown_with_zero_tax(): void
    {
        $result = TaxBreakdownService::breakdown(500000, 0, 0, 0);

        $this->assertEquals(500000.0, $result['net_total']);
        $this->assertEquals(0.0, $result['service_charge_amount']);
        $this->assertEquals(0.0, $result['special_tax_amount']);
        $this->assertEquals(0.0, $result['tax_amount']);
        $this->assertEquals(500000.0, $result['amount']);
    }

    /**
     * Test số lượng (Quantity > 1).
     */
    public function test_breakdown_with_quantity(): void
    {
        // 2 suất ăn, tổng tiền 231.000 (SC 5%, VAT 10%)
        // Net total = 200.000 -> original_rate = 100.000 / suất
        $result = TaxBreakdownService::breakdown(231000, 5, 0, 10, 2);

        $this->assertEquals(200000.0, $result['net_total']);
        $this->assertEquals(100000.0, $result['original_rate']);
        $this->assertEquals(10000.0, $result['service_charge_amount']);
        $this->assertEquals(21000.0, $result['tax_amount']);
        $this->assertEquals(231000.0, $result['amount']);
    }

    /**
     * Test số âm (dòng giảm trừ dịch vụ / khấu trừ tiền ăn sáng).
     */
    public function test_breakdown_with_negative_amount(): void
    {
        $result = TaxBreakdownService::breakdown(-1155000, 5, 0, 10);

        $this->assertEquals(-1000000.0, $result['net_total']);
        $this->assertEquals(-50000.0, $result['service_charge_amount']);
        $this->assertEquals(0.0, $result['special_tax_amount']);
        $this->assertEquals(-105000.0, $result['tax_amount']);

        $sum = $result['net_total'] + $result['service_charge_amount'] + $result['special_tax_amount'] + $result['tax_amount'];
        $this->assertEquals(-1155000.0, $sum);
    }

    /**
     * Test số lẻ không tròn, đảm bảo không lệch 1 xu nhờ cơ chế bù trừ.
     */
    public function test_breakdown_odd_number_rounding_equality(): void
    {
        $amounts = [100000, 333333, 777777, 1234567, 999999];
        foreach ($amounts as $amt) {
            $result = TaxBreakdownService::breakdown($amt, 5, 10, 10);
            $sum = $result['net_total'] + $result['service_charge_amount'] + $result['special_tax_amount'] + $result['tax_amount'];
            $this->assertEquals((float) $amt, round($sum, 2), "Failed on amount $amt");
        }
    }
}
