<?php

namespace Tests\Feature;

use App\Services\Reports\TotalRevenueDataAdapter;
use App\Services\TemplateRendererService;
use Tests\TestCase;

class TotalRevenueReportTest extends TestCase
{
    private const MIGRATION = 'migrations/2026_10_30_100000_rebuild_total_revenue_reports.php';

    private function template(string $file = 'total_revenue_reference.php'): object
    {
        return require database_path('report_templates/'.$file);
    }

    private function row(string $period, string $date, string $service, string $department, int $roomBill, float $amount, float $net = 0): array
    {
        return [
            'period_code' => $period,
            'business_date' => $date,
            'service_id' => $service,
            'department_id' => $department,
            'is_room_bill' => $roomBill,
            'amount' => $amount,
            'original_rate' => $net ?: $amount * 0.85,
            'service_charge_amount' => $amount * 0.05,
            'tax_amount' => $amount * 0.08,
        ];
    }

    private function dataset(array $rows, array $parameters = []): array
    {
        return [
            'parameters' => array_replace([
                'p_from_date' => '2026-08-20',
                'p_to_date' => '2026-08-20',
                'p_view_by_time' => false,
                'p_from_time' => '00:00',
                'p_to_time' => '23:59',
                'p_department' => '',
                'p_outlet' => '',
                'p_user' => '',
                'p_view_free' => false,
                'p_amount_type' => 'TOTAL',
            ], $parameters),
            'rows' => $rows,
        ];
    }

    private function drill(string $attribute): array
    {
        return json_decode(html_entity_decode($attribute, ENT_QUOTES, 'UTF-8'), true);
    }

    public function test_migration_rebuilds_legacy_procedures_and_drill_down_report(): void
    {
        $migration = file_get_contents(database_path(self::MIGRATION));

        $this->assertStringContainsString('CREATE PROCEDURE `rpt_total_revenue`', $migration);
        $this->assertStringContainsString('CREATE PROCEDURE `rpt_service_revenue_detail`', $migration);
        $this->assertStringContainsString('INNER JOIN service_bill_details AS d', $migration);
        $this->assertStringContainsString("SELECT 'DAY' AS period_code", $migration);
        $this->assertStringContainsString("'MTD'", $migration);
        $this->assertStringContainsString("'YTD'", $migration);
        $this->assertStringContainsString("'CTM'", $migration);
        $this->assertStringContainsString('pm.is_free', $migration);
        $this->assertStringContainsString("THEN 'BS'", $migration);
        $this->assertStringContainsString("'SERVICE_REVENUE_DETAIL'", $migration);
        $this->assertStringNotContainsString('paid_commission', $migration);
    }

    public function test_adapter_maps_service_codes_to_legacy_columns(): void
    {
        $data = (new TotalRevenueDataAdapter())->adapt($this->dataset([
            $this->row('DAY', '2026-08-20', 'RM', 'FO', 1, 8483500),
            $this->row('DAY', '2026-08-20', 'BF', 'FO', 1, 1720000),
            $this->row('DAY', '2026-08-20', 'BF', 'FO', 0, 100000),
            $this->row('DAY', '2026-08-20', 'EB', 'FO', 0, 600000),
            $this->row('DAY', '2026-08-20', 'EP', 'FO', 0, 250000),
            $this->row('DAY', '2026-08-20', 'TO', 'FO', 0, 200000),
            $this->row('DAY', '2026-08-20', 'TS', 'FO', 0, 500000),
            $this->row('DAY', '2026-08-20', 'KE', 'FO', 0, 1200000),
            $this->row('DAY', '2026-08-20', 'LA', 'HK', 0, 80000),
            $this->row('DAY', '2026-08-20', 'MB', 'HK', 0, 355000),
            $this->row('DAY', '2026-08-20', 'BR', 'HK', 0, 60000),
            $this->row('MTD', '', 'RM', 'FO', 1, 807678391),
            $this->row('YTD', '', 'RM', 'FO', 1, 7112037356),
        ]));

        $day = $data['revenue']['days'][0];
        $this->assertSame('20-08-2026', $day['Label']);
        $this->assertEquals(8483500, $day['Room']);
        $this->assertEquals(1720000, $day['MoneyBreakfastRoom']);
        $this->assertEquals(100000, $day['BF']);
        $this->assertEquals(600000, $day['EB']);
        $this->assertEquals(250000, $day['EP']);
        $this->assertEquals(200000, $day['Tour']);
        $this->assertEquals(500000, $day['Transportation']);
        $this->assertEquals(1200000, $day['Miscell']);
        $this->assertEquals(80000, $day['Laundry']);
        $this->assertEquals(355000, $day['Minibar']);
        $this->assertEquals(60000, $day['Broken']);
        $this->assertEquals(13548500, $day['Total']);

        $sum = $data['revenue']['sum'];
        $this->assertEquals(11233500, $sum['FO']);
        $this->assertEquals(495000, $sum['HK']);
        $this->assertEquals(1820000, $sum['FB']);
        $this->assertEquals(13548500, $sum['Total']);
        $this->assertEquals(round(13548500 * 0.05), $sum['ServiceCharge']);
        $this->assertEquals(round(13548500 * 0.08), $sum['Tax']);

        [$mtd, $ytd, $ctm] = $data['revenue']['periods'];
        $this->assertSame(['M.T.D', 'Y.T.D', 'CTM'], [$mtd['Label'], $ytd['Label'], $ctm['Label']]);
        $this->assertEquals(807678391, $mtd['Room']);
        $this->assertEquals(7112037356, $ytd['Total']);
        $this->assertEquals(0, $ctm['Total']);
        $this->assertSame('', $ctm['Total_drill']);
    }

    public function test_not_vat_uses_original_rate_and_free_invoices_count_cn_as_room(): void
    {
        $rows = [
            $this->row('DAY', '2026-08-20', 'RM', 'FO', 1, 1000000, 800000),
            $this->row('DAY', '2026-08-20', 'CN', 'FO', 0, 500000, 400000),
        ];

        $total = (new TotalRevenueDataAdapter())->adapt($this->dataset($rows))['revenue']['sum'];
        $this->assertEquals(1000000, $total['Room']);
        $this->assertEquals(500000, $total['Miscell']);

        $notVat = (new TotalRevenueDataAdapter())->adapt($this->dataset($rows, ['p_amount_type' => 'NOT_VAT', 'p_view_free' => true]))['revenue']['sum'];
        $this->assertEquals(1200000, $notVat['Room']);
        $this->assertEquals(0, $notVat['Miscell']);
        $this->assertEquals(1200000, $notVat['Total']);
    }

    public function test_each_cell_drills_into_detail_report_with_its_service_codes(): void
    {
        $data = (new TotalRevenueDataAdapter())->adapt($this->dataset([
            $this->row('DAY', '2026-08-20', 'BF', 'FO', 1, 1720000),
            $this->row('DAY', '2026-08-20', 'BF', 'FO', 0, 100000),
            $this->row('DAY', '2026-08-20', 'BD', 'FO', 0, 50000),
            $this->row('DAY', '2026-08-20', 'KE', 'FO', 0, 70000),
            $this->row('CTM', '', 'RM', 'FO', 1, 900000),
        ], ['p_from_date' => '2026-08-01', 'p_view_by_time' => true, 'p_from_time' => '14:00', 'p_to_time' => '13:59', 'p_outlet' => 'FO']));

        $day = $data['revenue']['days'][0];
        $breakfastRoom = $this->drill($day['MoneyBreakfastRoom_drill']);
        $this->assertSame('SERVICE_REVENUE_DETAIL', $breakfastRoom['report']);
        $this->assertSame('BF', $breakfastRoom['parameters']['p_services']);
        $this->assertSame('2026-08-20', $breakfastRoom['parameters']['p_from_date']);
        $this->assertSame('2026-08-20', $breakfastRoom['parameters']['p_to_date']);
        $this->assertSame(1, $breakfastRoom['parameters']['p_view_by_time']);
        $this->assertSame('14:00', $breakfastRoom['parameters']['p_from_time']);
        $this->assertSame('FO', $breakfastRoom['parameters']['p_outlet']);
        $this->assertSame('BD,BS', $this->drill($day['BF_drill'])['parameters']['p_services']);
        $this->assertSame('KE', $this->drill($day['Miscell_drill'])['parameters']['p_services']);
        $this->assertSame('', $this->drill($day['Total_drill'])['parameters']['p_services']);
        $this->assertSame('', $day['Room_drill']);

        $sumDrill = $this->drill($data['revenue']['sum']['BF_drill']);
        $this->assertSame('2026-08-01', $sumDrill['parameters']['p_from_date']);

        $ctmDrill = $this->drill($data['revenue']['periods'][2]['Room_drill']);
        $this->assertSame(1, $ctmDrill['parameters']['p_unpaid_only']);
        $this->assertSame('2026-08-01', $ctmDrill['parameters']['p_from_date']);
        $this->assertSame('(14:00 31-07-2026)', '('.$data['revenue']['window_from'].')');
    }

    public function test_adapter_is_idempotent_for_template_re_render(): void
    {
        $adapter = new TotalRevenueDataAdapter();
        $data = $adapter->adapt($this->dataset([$this->row('DAY', '2026-08-20', 'RM', 'FO', 1, 100)]));
        $data['parameters']['p_from_date'] = '20/08/2026';

        $this->assertSame($data, $adapter->adapt($data));
    }

    public function test_template_renders_legacy_layout_with_drill_cells(): void
    {
        $definition = $this->template()->definition();
        $data = (new TotalRevenueDataAdapter())->adapt($this->dataset([
            $this->row('DAY', '2026-08-20', 'RM', 'FO', 1, 8483500),
            $this->row('DAY', '2026-08-20', 'SR', 'FO', 0, 10000),
        ]));
        $html = (new TemplateRendererService())->render($definition['content_html'], $definition['css'], $data);

        $this->assertSame('TOTAL_REVENUE', $definition['code']);
        $this->assertSame('landscape', $definition['page_orientation']);
        $this->assertCount(18, $definition['columns']);
        foreach (['BÁO CÁO TỔNG DOANH THU', 'FO', 'HK', 'FB', 'Tiền phòng', 'Ăn sáng (tách từ tiền phòng)', 'Showroom', 'M.T.D', 'Y.T.D', 'CTM', 'Phí phục vụ', 'Phí VAT', 'Số tiền đã thanh toán', '20-08-2026', '8.483.500', '8.493.500', '<svg'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringContainsString('data-pms-drill="{&quot;report&quot;:&quot;SERVICE_REVENUE_DETAIL&quot;', $html);
        $this->assertStringContainsString('(Doanh thu ghi nhận từ 00:00 20-08-2026 đến 23:59 20-08-2026)', $html);
        $this->assertDoesNotMatchRegularExpression('/\{\{[^}]+\}\}/', $html);
    }

    public function test_detail_template_groups_by_service_and_escapes_user_text(): void
    {
        $definition = $this->template('service_revenue_detail_reference.php')->definition();
        $rows = [
            ['STT' => 1, 'BillId' => 36, 'BillDate' => '20/08/2026', 'BookingCode' => 'GAL4', 'Room' => '107', 'GuestName' => '<b>DDD</b>', 'ArrivalDate' => '20/08/2026', 'DepartureDate' => '21/08/2026', 'ServiceId' => 'BF', 'ServiceName' => 'BF - Ăn sáng (tách từ tiền phòng)', 'Description' => '{{hotel.name}}', 'OriginalRate' => 317460, 'ServiceChargeAmount' => 15873, 'TaxAmount' => 26667, 'Amount' => 360000, 'PaymentId' => '10', 'PaymentMode' => 'CA', 'Username' => 'admin', 'OutletName' => 'FO'],
            ['STT' => 2, 'BillId' => 37, 'BillDate' => '20/08/2026', 'BookingCode' => 'GAL4', 'Room' => '112', 'GuestName' => 'DDD', 'ArrivalDate' => '20/08/2026', 'DepartureDate' => '21/08/2026', 'ServiceId' => 'RM', 'ServiceName' => 'RM - Tiền phòng', 'Description' => 'Phòng 112', 'OriginalRate' => 100, 'ServiceChargeAmount' => 5, 'TaxAmount' => 8, 'Amount' => 113, 'PaymentId' => '', 'PaymentMode' => '', 'Username' => 'admin', 'OutletName' => 'MB'],
        ];
        $data = (new TotalRevenueDataAdapter())->adaptDetail(['parameters' => ['p_from_date' => '20/08/2026', 'p_to_date' => '20/08/2026'], 'rows' => $rows, 'hotel' => ['name' => 'Leak']]);
        $html = (new TemplateRendererService())->render($definition['content_html'], $definition['css'], $data);

        $this->assertSame('SERVICE_REVENUE_DETAIL', $definition['code']);
        $this->assertStringContainsString('BÁO CÁO DOANH THU DỊCH VỤ CHI TIẾT', $html);
        $this->assertStringContainsString('Dịch vụ: BF - Ăn sáng (tách từ tiền phòng)', $html);
        $this->assertSame(2, substr_count($html, 'Tổng doanh thu theo dịch vụ'));
        $this->assertStringContainsString('360.113', $html);
        $this->assertStringContainsString('Tổng doanh thu theo outlet', $html);
        $this->assertStringContainsString('data-pms-booking="GAL4"', $html);
        $this->assertStringContainsString('&lt;b&gt;DDD&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('Leak', $html);
        $this->assertCount(2, $data['outlet_summary']);
    }
}
