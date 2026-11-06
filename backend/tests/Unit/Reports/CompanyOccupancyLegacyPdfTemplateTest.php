<?php

namespace Tests\Unit\Reports;

use App\Services\Reports\CompanyOccupancyDataAdapter;
use App\Services\TemplateRendererService;
use Tests\Fixtures\CompanyOccupancyLegacyPdfFixture;
use Tests\TestCase;

class CompanyOccupancyLegacyPdfTemplateTest extends TestCase
{
    public function test_summary_template_keeps_legacy_pdf_columns_and_totals(): void
    {
        $template = require database_path('report_templates/company_occupancy_reference.php');
        $definition = $template->definition();
        $data = (new CompanyOccupancyDataAdapter())->adapt(CompanyOccupancyLegacyPdfFixture::summary() + ['aggregate' => ['rows' => []]]);
        $rendered = app(TemplateRendererService::class)->render($template->html(), $template->css(), $data);

        $this->assertSame(14, count($definition['content_json']['detail'][0]['columns']));
        $this->assertSame(100.0, array_sum(array_map(static fn (array $column): float => (float) rtrim($column['width'], '%'), $definition['content_json']['detail'][0]['columns'])));
        $this->assertSame('GroupHeaderId', $definition['content_json']['detail'][0]['groups'][0]['field']);
        $this->assertSame('parameters.p_is_grouped', $definition['content_json']['detail'][0]['groups'][0]['enabledBy']);
        $this->assertStringContainsString('Doanh Thu TB', $rendered);
        $this->assertStringContainsString('Công ty A', $rendered);
        $this->assertStringContainsString('72.200.000', $rendered);
        $this->assertStringNotContainsString('{{', $rendered);
    }

    public function test_summary_detail_switch_only_adds_the_three_configured_columns(): void
    {
        $template = require database_path('report_templates/company_occupancy_reference.php');
        $data = (new CompanyOccupancyDataAdapter())->adapt(CompanyOccupancyLegacyPdfFixture::summary() + ['aggregate' => ['rows' => []]]);

        $compact = app(TemplateRendererService::class)->render($template->html(), $template->css(), $data);
        $this->assertStringNotContainsString('Đêm Khách', $compact);
        $this->assertStringNotContainsString('Tiền Phòng', $compact);
        $this->assertStringNotContainsString('Giá Thêm Giường', $compact);

        $data['parameters']['p_show_detail'] = true;
        $detailed = app(TemplateRendererService::class)->render($template->html(), $template->css(), $data);
        $this->assertStringContainsString('Đêm Khách', $detailed);
        $this->assertStringContainsString('Tiền Phòng', $detailed);
        $this->assertStringContainsString('Giá Thêm Giường', $detailed);
    }

    public function test_detail_template_keeps_legacy_pdf_grouping_and_totals(): void
    {
        $template = require database_path('report_templates/company_occupancy_detail_reference.php');
        $definition = $template->definition();
        $rendered = app(TemplateRendererService::class)->render($template->html(), $template->css(), CompanyOccupancyLegacyPdfFixture::detail());

        $this->assertSame(11, count($definition['content_json']['detail'][0]['columns']));
        $this->assertSame(100.0, array_sum(array_map(static fn (array $column): float => (float) rtrim($column['width'], '%'), $definition['content_json']['detail'][0]['columns'])));
        $this->assertStringContainsString('data-group-by="CompanyCode"', $template->html());
        $this->assertStringContainsString('Công ty A', $rendered);
        $this->assertStringContainsString('BK001', $rendered);
        $this->assertStringContainsString('Tổng Giai Đoạn 2 BK', $rendered);
        $this->assertStringNotContainsString('{{', $rendered);
    }
}
