<?php

namespace Tests\Unit;

use App\Models\Template;
use App\Services\Reports\ReportExportService;
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
