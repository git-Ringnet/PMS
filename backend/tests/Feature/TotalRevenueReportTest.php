<?php

namespace Tests\Feature;

use App\Services\TemplateRendererService;
use Tests\TestCase;

class TotalRevenueReportTest extends TestCase
{
    private function template(): object
    {
        return require database_path('report_templates/total_revenue_reference.php');
    }

    public function test_phase_one_migration_contains_runtime_sheet_72_contract_only(): void
    {
        $path = database_path('migrations/2026_09_24_120000_create_total_revenue_report.php');
        $this->assertFileExists($path);

        $migration = file_get_contents($path);
        $this->assertStringContainsString('CREATE PROCEDURE `rpt_total_revenue`', $migration);
        $this->assertStringContainsString('booking_rooms AS br', $migration);
        $this->assertStringContainsString('br.id = p.booking_room_id', $migration);
        $this->assertStringContainsString('br.room_number', $migration);
        $this->assertStringContainsString('pm.code = p.payment_method_id', $migration);
        $this->assertStringContainsString('pm.payment_group', $migration);
        $this->assertStringContainsString("p.pack2", $migration);
        $this->assertStringContainsString('p.edit_flag', $migration);
        $this->assertStringContainsString("p.status, 1) <> 3", $migration);
        $this->assertStringContainsString("'BD', 'BF'", $migration);
        $this->assertStringContainsString('grand_total_revenue - rc.paid_cash - rc.paid_bank - rc.paid_commission', $migration);
        $this->assertStringNotContainsString('AT7620', $migration);
        $this->assertStringNotContainsString('AT7621', $migration);
        $this->assertStringNotContainsString('sp_TotalRevenueFromReportSetup', $migration);
    }

    public function test_template_has_approved_stable_22_column_order_and_designer_contract(): void
    {
        $template = $this->template();
        $definition = $template->definition();
        $table = $definition['content_json']['detail'][0];
        $fields = array_column($table['columns'], 'field');

        $this->assertSame('TOTAL_REVENUE', $definition['code']);
        $this->assertSame('TOTAL_REVENUE_REFERENCE', $definition['report']);
        $this->assertSame('A4', $definition['page_size']);
        $this->assertSame('landscape', $definition['page_orientation']);
        $this->assertCount(22, $fields);
        $this->assertSame([
            'stt', 'booking_code', 'guest_name_rooms', 'company_name', 'arrival_date_display', 'departure_date_display',
            'room_count', 'room_revenue', 'extra_room_revenue', 'minibar_revenue', 'laundry_revenue', 'damage_revenue',
            'fb_revenue', 'other_service_revenue', 'daily_total_revenue', 'previous_days_revenue', 'grand_total_revenue',
            'paid_cash', 'paid_bank', 'paid_commission', 'paid_debt', 'inhouse_balance',
        ], $fields);
        $this->assertSame(7, $table['topHeader'][7]['colspan']);
        $this->assertSame(4, $table['topHeader'][11]['colspan']);
        $this->assertSame('rows', $table['dataSource']);
        $this->assertSame($definition['content_html'], $template->html());
        $this->assertStringContainsString('total-revenue-table', $definition['css']);
    }

    public function test_template_renders_rows_totals_and_five_signatures(): void
    {
        $template = $this->template();
        $renderer = app(TemplateRendererService::class);
        $rows = [[
            'stt' => 1,
            'booking_code' => 'GAL101',
            'guest_name_rooms' => 'Nguyễn Văn A - 101',
            'company_name' => 'KHÁCH LẺ',
            'arrival_date_display' => '22/09/2026',
            'departure_date_display' => '24/09/2026',
            'room_count' => 1,
            'room_revenue' => 1000000,
            'extra_room_revenue' => 100000,
            'minibar_revenue' => 50000,
            'laundry_revenue' => 0,
            'damage_revenue' => 0,
            'fb_revenue' => 150000,
            'other_service_revenue' => 0,
            'daily_total_revenue' => 1300000,
            'previous_days_revenue' => 200000,
            'grand_total_revenue' => 1500000,
            'paid_cash' => 500000,
            'paid_bank' => 700000,
            'paid_commission' => 100000,
            'paid_debt' => 100000,
            'inhouse_balance' => 100000,
        ]];

        $accountingTotal = $rows[0]['paid_cash'] + $rows[0]['paid_bank'] + $rows[0]['paid_commission']
            + $rows[0]['paid_debt'] + $rows[0]['inhouse_balance'];
        $this->assertSame($rows[0]['grand_total_revenue'], $accountingTotal);

        $html = $renderer->render($template->html(), $template->css(), [
            'hotel' => [
                'name' => 'Golden Crest Quy Nhơn',
                'address' => '66 Hàn Mặc Tử',
                'city' => 'Quy Nhơn',
                'logo' => '<img src="/logo.png" alt="Logo">',
            ],
            'report' => ['generated_by' => 'admin'],
            'parameters' => ['p_date' => '23/09/2026'],
            'rows' => $rows,
        ]);

        $this->assertStringContainsString('BÁO CÁO DOANH THU', $html);
        $this->assertStringContainsString('GAL101', $html);
        $this->assertStringContainsString('1.500.000', $html);
        $this->assertStringContainsString('Tổng số BK: 1', $html);
        $this->assertStringContainsString('Chữ Ký Người Lập', $html);
        $this->assertStringContainsString('Trưởng Bộ Phận', $html);
        $this->assertStringContainsString('Kế Toán', $html);
        $this->assertStringContainsString('Tổng Quản Lý', $html);
        $this->assertStringContainsString('Giám Đốc', $html);
    }
}
