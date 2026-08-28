<?php

namespace App\Services\Reports;

use App\Models\Template;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\Writer\Word2007;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    public function download(string $format, Template $template, array $data, string $html, string $reportCode): Response|StreamedResponse
    {
        $fileBase = Str::lower($reportCode).'_'.now()->format('Ymd_His');

        return match ($format) {
            'pdf' => $this->pdf($template, $html, $fileBase),
            'xlsx' => $this->spreadsheet($template, $data, $fileBase),
            'docx' => $this->word($template, $data, $fileBase),
        };
    }

    private function pdf(Template $template, string $html, string $fileBase): Response
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($this->paperName($template->page_size), $template->page_orientation === 'landscape' ? 'landscape' : 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$fileBase.'.pdf"',
        ]);
    }

    private function spreadsheet(Template $template, array $data, string $fileBase): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Report');
        $sheet->getPageSetup()
            ->setOrientation($template->page_orientation === 'landscape' ? PageSetup::ORIENTATION_LANDSCAPE : PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(match (strtoupper((string) $template->page_size)) {
                'A5' => PageSetup::PAPERSIZE_A5,
                'LETTER' => PageSetup::PAPERSIZE_LETTER,
                'LEGAL' => PageSetup::PAPERSIZE_LEGAL,
                default => PageSetup::PAPERSIZE_A4,
            });
        $sheet->getPageMargins()
            ->setTop(($template->margin_top ?? 10) / 25.4)
            ->setBottom(($template->margin_bottom ?? 10) / 25.4)
            ->setLeft(($template->margin_left ?? 10) / 25.4)
            ->setRight(($template->margin_right ?? 10) / 25.4);
        $sheet->setCellValue('A1', (string) ($data['report']['name'] ?? $template->name));
        $sheet->mergeCells('A1:Z1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $fields = $this->fields($data);
        foreach ($fields as $index => $field) {
            $column = $index + 1;
            $cell = Coordinate::stringFromColumnIndex($column).'3';
            $sheet->setCellValue($cell, $field);
            $sheet->getStyle($cell)->getFont()->setBold(true);
        }
        foreach ($data['rows'] ?? [] as $rowIndex => $row) {
            foreach ($fields as $columnIndex => $field) {
                $cell = Coordinate::stringFromColumnIndex($columnIndex + 1).($rowIndex + 4);
                $sheet->setCellValue($cell, (string) ($row[$field] ?? ''));
            }
        }
        foreach (range(1, max(1, count($fields))) as $column) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $fileBase.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function word(Template $template, array $data, string $fileBase): StreamedResponse
    {
        $word = new PhpWord();
        $section = $word->addSection($this->wordPageSettings($template));
        $section->addText((string) ($data['report']['name'] ?? $template->name), ['bold' => true, 'size' => 14], ['alignment' => 'center']);
        $section->addText('Ngày xuất: '.($data['report']['generated_at'] ?? ''), ['size' => 9], ['alignment' => 'right']);

        $fields = $this->fields($data);
        $table = $section->addTable(['borderSize' => 4, 'borderColor' => '94A3B8', 'cellMargin' => 60]);
        $table->addRow();
        foreach ($fields as $field) $table->addCell()->addText($field, ['bold' => true, 'size' => 8]);
        foreach ($data['rows'] ?? [] as $row) {
            $table->addRow();
            foreach ($fields as $field) $table->addCell()->addText((string) ($row[$field] ?? ''), ['size' => 8]);
        }

        return response()->streamDownload(function () use ($word) {
            (new Word2007($word))->save('php://output');
        }, $fileBase.'.docx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
    }

    private function fields(array $data): array
    {
        return array_values(array_filter(array_map(fn ($field) => is_array($field) ? ($field['name'] ?? null) : $field, $data['fields'] ?? [])))
            ?: array_keys($data['rows'][0] ?? []);
    }

    private function paperName(?string $pageSize): string
    {
        return in_array(strtolower((string) $pageSize), ['a4', 'a5', 'letter', 'legal'], true) ? strtolower((string) $pageSize) : 'a4';
    }

    private function wordPageSettings(Template $template): array
    {
        $dimensions = match (strtoupper((string) $template->page_size)) {
            'A5' => [148, 210], 'LETTER' => [215.9, 279.4], 'LEGAL' => [215.9, 355.6], default => [210, 297],
        };
        if ($template->page_orientation === 'landscape') $dimensions = array_reverse($dimensions);

        return [
            'pageSizeW' => Converter::cmToTwip($dimensions[0] / 10),
            'pageSizeH' => Converter::cmToTwip($dimensions[1] / 10),
            'marginTop' => Converter::cmToTwip(($template->margin_top ?? 10) / 10),
            'marginBottom' => Converter::cmToTwip(($template->margin_bottom ?? 10) / 10),
            'marginLeft' => Converter::cmToTwip(($template->margin_left ?? 10) / 10),
            'marginRight' => Converter::cmToTwip(($template->margin_right ?? 10) / 10),
        ];
    }
}
