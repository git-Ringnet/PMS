<?php

namespace Tests\Unit;

use App\Models\Template;
use App\Services\Reports\ReportExportService;
use App\Services\Reports\ReportSpreadsheetExportService;
use Tests\TestCase;

class ReportExportServiceTest extends TestCase
{
    public function test_it_exports_pdf_excel_and_word_files(): void
    {
        $template = new Template([
            'name' => 'Mẫu test', 'page_size' => 'A4', 'page_orientation' => 'portrait',
            'margin_top' => 6, 'margin_bottom' => 6, 'margin_left' => 5, 'margin_right' => 5,
        ]);
        $data = [
            'report' => ['name' => 'Báo cáo test', 'generated_at' => '09/08/2026 10:00:00'],
            'fields' => [['name' => 'Room'], ['name' => 'GuestName']],
            'rows' => [['Room' => '101', 'GuestName' => 'Nguyễn Văn A']],
        ];
        $service = app(ReportExportService::class);

        $pdf = $service->download('pdf', $template, $data, '<html><body><h1>Báo cáo test</h1></body></html>', 'TEST');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        foreach (['xlsx', 'docx'] as $format) {
            $response = $service->download($format, $template, $data, '', 'TEST');
            ob_start();
            $response->sendContent();
            $content = ob_get_clean();
            $this->assertStringStartsWith('PK', $content);
            $this->assertValidOfficeArchive($content, $format);
        }
    }

    public function test_it_exports_rendered_report_layout_to_excel(): void
    {
        $template = new Template([
            'name' => 'Mẫu báo cáo', 'page_size' => 'A4', 'page_orientation' => 'portrait',
            'margin_top' => 6, 'margin_bottom' => 6, 'margin_left' => 5, 'margin_right' => 5,
        ]);
        $html = <<<'HTML'
<!doctype html>
<html><head><style>
body { font-family: Arial; font-size: 10px; }
h1 { text-align: center; font-size: 20px; }
.grid th, .grid td { border: 1px solid #94A3B8; padding: 4px; }
.grid th { background-color: #D9E1EC; text-align: center; }
.total { font-weight: 700; background-color: #E2E8F0; text-align: right; }
</style></head><body>
<h1>BÁO CÁO KIỂM THỬ</h1>
<table class="grid">
  <tr><th>Mã ĐK</th><th colspan="2">Tên khách</th></tr>
  <tr><td>001</td><td colspan="2">Nguyễn Văn A</td></tr>
  <tr><td colspan="3" class="total">Tổng: 1</td></tr>
</table>
</body></html>
HTML;

        $response = app(ReportExportService::class)->download('xlsx', $template, [], $html, 'TEST');
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();
        $path = tempnam(sys_get_temp_dir(), 'pms-report-layout-');
        file_put_contents($path, $content);

        try {
            $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();

            $this->assertSame('BÁO CÁO KIỂM THỬ', $sheet->getCell('A1')->getValue());
            $this->assertContains('A1:C1', $sheet->getMergeCells());
            $this->assertSame('Tên khách', $sheet->getCell('B2')->getValue());
            $this->assertContains('B2:C2', $sheet->getMergeCells());
            $this->assertSame('D9E1EC', $sheet->getStyle('A2')->getFill()->getStartColor()->getRGB());
            $this->assertTrue($sheet->getStyle('A2')->getFont()->getBold());
            $this->assertSame('Tổng: 1', $sheet->getCell('A4')->getValue());
            $this->assertTrue($sheet->getStyle('A4')->getFont()->getBold());
        } finally {
            @unlink($path);
        }
    }

    public function test_it_maps_secondary_tables_to_the_primary_report_width(): void
    {
        $template = new Template(['name' => 'Mẫu báo cáo', 'page_size' => 'A4', 'page_orientation' => 'portrait']);
        $html = <<<'HTML'
<html><body>
<table><tr><th>A</th><th>B</th><th>C</th><th>D</th></tr></table>
<table><tr><th>Tổng 1</th><th>Tổng 2</th></tr></table>
</body></html>
HTML;

        $sheet = app(ReportSpreadsheetExportService::class)->build($template, $html)->getActiveSheet();

        $this->assertSame('Tổng 1', $sheet->getCell('A2')->getValue());
        $this->assertSame('Tổng 2', $sheet->getCell('C2')->getValue());
        $this->assertContains('A2:B2', $sheet->getMergeCells());
        $this->assertContains('C2:D2', $sheet->getMergeCells());
    }

    public function test_it_honors_column_widths_important_styles_and_wrapped_row_height(): void
    {
        $template = new Template(['name' => 'Mẫu báo cáo', 'page_size' => 'A4', 'page_orientation' => 'portrait']);
        $html = <<<'HTML'
<html><head><style>
th { background-color: #F8FAFC; }
.report th { background: #E2E8F0; }
.report .date-group { text-align: left !important; background: #F8FAFC; }
.report td { padding: 4px 3px; overflow-wrap: anywhere; }
</style></head><body>
<table class="report"><colgroup><col style="width:10%"><col style="width:20%"></colgroup>
  <tr><th>Mã</th><th>Nội dung</th></tr>
  <tr><td colspan="2" class="date-group">Ngày: 09/08/2026</td></tr>
  <tr><td>1</td><td>Nội dung minibar rất dài cần xuống nhiều dòng để kiểm tra chiều cao hàng Excel được tính đúng và không bị cắt khi xuất báo cáo.</td></tr>
</table>
</body></html>
HTML;

        $sheet = app(ReportSpreadsheetExportService::class)->build($template, $html)->getActiveSheet();

        $this->assertSame('E2E8F0', $sheet->getStyle('A1')->getFill()->getStartColor()->getRGB());
        $this->assertSame('left', $sheet->getStyle('A2')->getAlignment()->getHorizontal());
        $this->assertGreaterThan($sheet->getColumnDimension('A')->getWidth(), $sheet->getColumnDimension('B')->getWidth());
        $this->assertGreaterThan(30, $sheet->getRowDimension(3)->getRowHeight());
    }

    private function assertValidOfficeArchive(string $content, string $format): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pms-report-');
        file_put_contents($path, $content);

        $archive = new \ZipArchive();
        try {
            $this->assertTrue($archive->open($path) === true);
            $this->assertNotFalse($archive->locateName('[Content_Types].xml'));
            $this->assertNotFalse($archive->locateName($format === 'xlsx' ? 'xl/workbook.xml' : 'word/document.xml'));
        } finally {
            $archive->close();
            @unlink($path);
        }
    }
}
