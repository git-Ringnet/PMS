<?php

namespace App\Services\Reports;

use App\Models\Template;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Converts the already rendered report HTML to a printable XLSX worksheet.
 *
 * This deliberately consumes rendered HTML rather than report fields so the
 * exported workbook follows the same title, grouped rows, totals and static
 * tables that the Report Viewer displays.
 */
class ReportSpreadsheetExportService
{
    /** @var array<int, array{selector: string, declarations: array<string, string>, order: int}> */
    private array $cssRules = [];

    /** @var array<int, array<int, bool>> */
    private array $occupiedCells = [];

    public function download(Template $template, string $html, string $fileBase): StreamedResponse
    {
        $spreadsheet = $this->build($template, $html);

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $fileBase.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function build(Template $template, string $html): Spreadsheet
    {
        $document = $this->loadDocument($html);
        $this->cssRules = $this->parseCss($document);
        $this->occupiedCells = [];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->sheetTitle($document, $template));
        $sheet->setShowGridlines(false);
        $sheet->setPrintGridlines(false);
        $this->applyPageSetup($sheet, $template);

        $columnCount = max(1, $this->maximumTableColumns($document));
        $this->applyPrimaryTableColumnWidths($sheet, $document, $columnCount);
        $row = 1;
        $body = $document->getElementsByTagName('body')->item(0);

        if ($body instanceof DOMElement) {
            $this->addVerticalSpacing($sheet, $this->computedStyleFor($body), 'padding-top', $row);
            foreach ($this->childElements($body) as $element) {
                $this->writeElement($sheet, $element, $row, $columnCount);
            }
        }

        if ($row === 1) {
            $sheet->setCellValue('A1', $template->name);
        }

        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
        $lastRow = max(1, $row - 1);
        $sheet->getPageSetup()->setPrintArea("A1:{$lastColumn}{$lastRow}");
        $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getSheetView()->setZoomScale(100);

        return $spreadsheet;
    }

    private function loadDocument(string $html): DOMDocument
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8">'.$html,
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $loaded) {
            throw new \RuntimeException('Không thể đọc bố cục HTML của báo cáo để xuất Excel.');
        }

        return $document;
    }

    /** @return array<int, array{selector: string, declarations: array<string, string>, order: int}> */
    private function parseCss(DOMDocument $document): array
    {
        $rules = [];
        $order = 0;
        $css = '';
        foreach ($document->getElementsByTagName('style') as $style) {
            $css .= "\n".$style->textContent;
        }

        $css = preg_replace('~/\*.*?\*/~s', '', $css) ?? '';
        $css = preg_replace('~@media[^\{]*\{(?:[^{}]|\{[^{}]*\})*\}~s', '', $css) ?? '';
        preg_match_all('~([^{}@]+)\{([^{}]*)\}~s', $css, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $declarations = $this->parseDeclarations($match[2]);
            if ($declarations === []) {
                continue;
            }
            foreach (explode(',', $match[1]) as $selector) {
                $selector = trim($selector);
                if ($selector === '') {
                    continue;
                }
                $rules[] = ['selector' => $selector, 'declarations' => $declarations, 'order' => $order++];
            }
        }

        return $rules;
    }

    /** @return array<string, string> */
    private function parseDeclarations(string $declarations): array
    {
        $result = [];
        foreach (explode(';', $declarations) as $declaration) {
            [$name, $value] = array_pad(explode(':', $declaration, 2), 2, null);
            $name = strtolower(trim((string) $name));
            $value = trim((string) $value);
            if ($name === '' || $value === '') {
                continue;
            }
            $result[$name] = $value;
        }

        return $result;
    }

    private function maximumTableColumns(DOMDocument $document): int
    {
        $maximum = 1;
        foreach ($document->getElementsByTagName('table') as $table) {
            if ($table instanceof DOMElement) {
                $maximum = max($maximum, $this->tableColumnCount($table));
            }
        }

        return $maximum;
    }

    private function tableColumnCount(DOMElement $table): int
    {
        $maximum = 0;
        foreach ($this->tableRows($table) as $row) {
            $count = 0;
            foreach ($this->tableCells($row) as $cell) {
                $count += max(1, (int) $cell->getAttribute('colspan'));
            }
            $maximum = max($maximum, $count);
        }

        return $maximum;
    }

    private function writeElement(Worksheet $sheet, DOMElement $element, int &$row, int $columnCount): void
    {
        $tag = strtolower($element->tagName);
        if (in_array($tag, ['style', 'script', 'title', 'meta', 'head'], true)) {
            return;
        }
        if ($tag === 'table') {
            $this->writeTable($sheet, $element, $row, $columnCount);

            return;
        }
        if ($tag === 'hr') {
            $this->writeDivider($sheet, $row, $columnCount);

            return;
        }
        if ($this->hasClass($element, 'hotel-header')) {
            $this->writeHotelHeader($sheet, $element, $row, $columnCount);

            return;
        }
        if (in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p'], true) || $this->isTextBlock($element)) {
            $this->writeTextBlock($sheet, $element, $row, $columnCount);

            return;
        }
        foreach ($this->childElements($element) as $child) {
            $this->writeElement($sheet, $child, $row, $columnCount);
        }
    }

    private function writeHotelHeader(Worksheet $sheet, DOMElement $header, int &$row, int $columnCount): void
    {
        $logoColumns = $this->hotelLogoColumnCount($sheet, $header, $columnCount);
        $logoEnd = Coordinate::stringFromColumnIndex($logoColumns);
        $infoStart = min($columnCount, $logoColumns + 1);
        $infoColumn = Coordinate::stringFromColumnIndex($infoStart);
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
        $height = 2;

        if ($logoColumns > 1) {
            $sheet->mergeCells("A{$row}:{$logoEnd}".($row + $height - 1));
        }
        $this->writeLogo($sheet, $header, "A{$row}");

        $information = $this->firstByClass($header, 'hotel-information');
        $lines = $information instanceof DOMElement ? $this->childElements($information, 'div') : [];
        if ($lines === []) {
            $lines = [$header];
        }
        foreach (array_slice($lines, 0, $height) as $index => $line) {
            $current = $row + $index;
            if ($infoStart < $columnCount) {
                $sheet->mergeCells("{$infoColumn}{$current}:{$lastColumn}{$current}");
            }
            $cell = "{$infoColumn}{$current}";
            $this->setNodeValue($sheet, $cell, $line);
            $this->applyStyle($sheet, $cell, $this->computedStyleFor($line));
            $sheet->getRowDimension($current)->setRowHeight(16);
        }

        $row += $height;
    }

    private function hotelLogoColumnCount(Worksheet $sheet, DOMElement $header, int $columnCount): int
    {
        $grid = $this->computedStyleFor($header)['grid-template-columns'] ?? '';
        if (preg_match('/^\s*([\d.]+)px\b/', $grid, $matches)) {
            $targetWidth = (float) $matches[1] / 7;
            $width = 0.0;
            for ($column = 1; $column < $columnCount; ++$column) {
                $width += (float) $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->getWidth();
                if ($width >= $targetWidth) {
                    return $column;
                }
            }
        }

        return min(max(1, (int) round($columnCount * 0.3)), max(1, $columnCount - 1));
    }

    private function writeLogo(Worksheet $sheet, DOMElement $header, string $cell): void
    {
        $images = $header->getElementsByTagName('img');
        $image = $images->item(0);
        if ($image instanceof DOMElement) {
            $path = $this->localImagePath($image->getAttribute('src'));
            if ($path !== null) {
                $drawing = new Drawing();
                $drawing->setPath($path);
                $drawing->setHeight(52);
                $drawing->setCoordinates($cell);
                $drawing->setWorksheet($sheet);

                return;
            }
        }

        $sheet->setCellValue($cell, 'Logo');
        $sheet->getStyle($cell)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function localImagePath(string $source): ?string
    {
        $path = parse_url($source, PHP_URL_PATH) ?: $source;
        if (! is_string($path) || $path === '' || str_starts_with($path, 'data:')) {
            return null;
        }
        $candidate = public_path(ltrim(str_replace('\\', '/', $path), '/'));

        return is_file($candidate) ? $candidate : null;
    }

    private function writeDivider(Worksheet $sheet, int &$row, int $columnCount): void
    {
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
        $range = "A{$row}:{$lastColumn}{$row}";
        $sheet->getStyle($range)->getBorders()->getBottom()
            ->setBorderStyle(Border::BORDER_THIN)
            ->setColor(new Color(Color::COLOR_BLACK));
        $sheet->getRowDimension($row)->setRowHeight(5);
        ++$row;
    }

    private function writeTextBlock(Worksheet $sheet, DOMElement $element, int &$row, int $columnCount): void
    {
        $text = $this->nodeText($element);
        if ($text === '') {
            return;
        }
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
        $style = $this->computedStyleFor($element);
        $tag = strtolower($element->tagName);
        if ($tag === 'h1') {
            $style = array_replace(['font-size' => '20px', 'font-weight' => '700', 'text-align' => 'center'], $style);
        } elseif ($tag === 'h2') {
            $style = array_replace(['font-size' => '15px', 'font-weight' => '700', 'text-align' => 'center'], $style);
        }
        $this->addVerticalSpacing($sheet, $style, 'margin-top', $row);
        $cell = "A{$row}";
        if ($columnCount > 1) {
            $sheet->mergeCells("{$cell}:{$lastColumn}{$row}");
        }
        $this->setNodeValue($sheet, $cell, $element);
        $this->applyStyle($sheet, $cell, $style);
        $sheet->getRowDimension($row)->setRowHeight($this->textRowHeight($text, $style));
        ++$row;
        $this->addVerticalSpacing($sheet, $style, 'margin-bottom', $row);
    }

    private function writeTable(Worksheet $sheet, DOMElement $table, int &$row, int $columnCount): void
    {
        $rows = $this->tableRows($table);
        if ($rows === []) {
            return;
        }
        $tableColumns = max(1, $this->tableColumnCount($table));
        $tableStyle = $this->computedStyleFor($table);
        [$tableStart, $tableEnd] = $this->tableGridBounds($sheet, $table, $columnCount);
        $tableGridColumns = $tableEnd - $tableStart + 1;
        $this->addVerticalSpacing($sheet, $tableStyle, 'margin-top', $row);

        foreach ($rows as $tr) {
            $logicalColumn = 1;
            $rowHeight = 14.5;
            foreach ($this->tableCells($tr) as $cellElement) {
                $column = $tableStart + $this->gridColumnStart($logicalColumn, $tableColumns, $tableGridColumns) - 1;
                while (($this->occupiedCells[$row][$column] ?? false) === true) {
                    ++$logicalColumn;
                    $column = $tableStart + $this->gridColumnStart($logicalColumn, $tableColumns, $tableGridColumns) - 1;
                }
                $colspan = max(1, (int) ($cellElement->getAttribute('colspan') ?: 1));
                $rowspan = max(1, (int) ($cellElement->getAttribute('rowspan') ?: 1));
                $endColumn = $tableStart + $this->gridColumnEnd($logicalColumn + $colspan - 1, $tableColumns, $tableGridColumns) - 1;
                $startCoordinate = Coordinate::stringFromColumnIndex($column).$row;
                $endCoordinate = Coordinate::stringFromColumnIndex($endColumn).($row + $rowspan - 1);
                $range = $startCoordinate.':'.$endCoordinate;
                $cellStyle = $this->computedStyleFor($cellElement);
                $text = $this->nodeText($cellElement);
                $this->setNodeValue($sheet, $startCoordinate, $cellElement);
                if ($rowspan > 1 || $endColumn > $column) {
                    $sheet->mergeCells($range);
                    for ($coveredRow = $row; $coveredRow < $row + $rowspan; ++$coveredRow) {
                        for ($coveredColumn = $column; $coveredColumn <= $endColumn; ++$coveredColumn) {
                            if ($coveredRow !== $row || $coveredColumn !== $column) {
                                $this->occupiedCells[$coveredRow][$coveredColumn] = true;
                            }
                        }
                    }
                }
                if (strtolower($cellElement->tagName) === 'th') {
                    $cellStyle['font-weight'] = '700';
                }
                $this->applyStyle($sheet, $range, $cellStyle);
                $rowHeight = max($rowHeight, $this->tableTextRowHeight($sheet, $text, $cellStyle, $column, $endColumn));
                $logicalColumn += $colspan;
            }
            $sheet->getRowDimension($row)->setRowHeight($rowHeight);
            ++$row;
        }
        $this->addVerticalSpacing($sheet, $tableStyle, 'margin-bottom', $row);
    }

    /** @return array{int, int} */
    private function tableGridBounds(Worksheet $sheet, DOMElement $table, int $gridColumnCount): array
    {
        $width = $this->computedStyleFor($table)['width'] ?? '100%';
        if (! is_string($width) || ! preg_match('/^\s*([\d.]+)%\s*$/', $width, $matches) || (float) $matches[1] >= 99.5) {
            return [1, $gridColumnCount];
        }

        $widths = [0 => 0.0];
        for ($column = 1; $column <= $gridColumnCount; ++$column) {
            $widths[$column] = (float) $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->getWidth();
        }
        $prefix = [0 => 0.0];
        for ($column = 1; $column <= $gridColumnCount; ++$column) {
            $prefix[$column] = $prefix[$column - 1] + $widths[$column];
        }
        $total = $prefix[$gridColumnCount];
        $target = $total * ((float) $matches[1] / 100);
        $best = [1, $gridColumnCount, INF];
        for ($start = 1; $start <= $gridColumnCount; ++$start) {
            $current = 0.0;
            for ($end = $start; $end <= $gridColumnCount; ++$end) {
                $current += $widths[$end];
                $centerOffset = abs(($prefix[$start - 1] + ($current / 2)) - ($total / 2));
                $score = abs($current - $target) + ($centerOffset * 0.2);
                if ($score < $best[2]) {
                    $best = [$start, $end, $score];
                }
            }
        }

        return [$best[0], $best[1]];
    }

    private function applyPrimaryTableColumnWidths(Worksheet $sheet, DOMDocument $document, int $gridColumnCount): void
    {
        $primaryTable = null;
        $primaryColumnCount = 0;
        foreach ($document->getElementsByTagName('table') as $table) {
            if (! $table instanceof DOMElement) {
                continue;
            }
            $tableColumns = $this->tableColumnCount($table);
            if ($tableColumns > $primaryColumnCount) {
                $primaryTable = $table;
                $primaryColumnCount = $tableColumns;
            }
        }
        if (! $primaryTable instanceof DOMElement) {
            return;
        }

        $widths = $this->tableColumnWidths($primaryTable, $primaryColumnCount);
        if (array_filter($widths, static fn ($width): bool => $width !== null) === []) {
            foreach ($this->tableRows($primaryTable) as $tr) {
                $column = 1;
                foreach ($this->tableCells($tr) as $cell) {
                    $span = max(1, (int) ($cell->getAttribute('colspan') ?: 1));
                    $style = $this->computedStyleFor($cell);
                    $width = $style['width'] ?? $cell->getAttribute('width');
                    if (is_string($width) && $width !== '') {
                        $widths[$column] ??= $width;
                    }
                    $column += $span;
                }
                if (array_filter($widths, static fn ($width): bool => $width !== null) !== []) {
                    break;
                }
            }
        }
        foreach ($widths as $index => $width) {
            $start = $this->gridColumnStart($index, $primaryColumnCount, $gridColumnCount);
            $end = $this->gridColumnEnd($index, $primaryColumnCount, $gridColumnCount);
            $widthPerGridColumn = $this->excelColumnWidth($width, $primaryColumnCount) / ($end - $start + 1);
            for ($gridColumn = $start; $gridColumn <= $end; ++$gridColumn) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($gridColumn))->setWidth($widthPerGridColumn);
            }
        }
    }

    /** @return array<int, string|null> */
    private function tableColumnWidths(DOMElement $table, int $columnCount): array
    {
        $widths = array_fill(1, $columnCount, null);
        $column = 1;
        foreach ($table->getElementsByTagName('col') as $col) {
            if (! $col instanceof DOMElement || $this->closestTable($col) !== $table) {
                continue;
            }
            $span = max(1, (int) ($col->getAttribute('span') ?: 1));
            $style = $this->computedStyleFor($col);
            $width = $style['width'] ?? $col->getAttribute('width');
            for ($offset = 0; $offset < $span && $column <= $columnCount; ++$offset, ++$column) {
                if (is_string($width) && $width !== '') {
                    $widths[$column] = $width;
                }
            }
        }

        return $widths;
    }

    private function gridColumnStart(int $logicalColumn, int $tableColumns, int $gridColumnCount): int
    {
        return max(1, (int) floor(($logicalColumn - 1) * $gridColumnCount / $tableColumns) + 1);
    }

    private function gridColumnEnd(int $logicalColumn, int $tableColumns, int $gridColumnCount): int
    {
        return min($gridColumnCount, max(1, (int) floor($logicalColumn * $gridColumnCount / $tableColumns)));
    }

    private function excelColumnWidth(mixed $width, int $columnCount): float
    {
        if (is_string($width) && preg_match('/^([\d.]+)%$/', trim($width), $matches)) {
            return max(4.0, (float) $matches[1] * 1.15);
        }
        if (is_string($width) && preg_match('/^([\d.]+)px$/', trim($width), $matches)) {
            return max(4.0, (float) $matches[1] / 7.0);
        }
        if (is_numeric($width)) {
            return max(4.0, (float) $width);
        }

        return max(8.0, 110 / max(1, $columnCount));
    }

    /** @return array<int, DOMElement> */
    private function tableRows(DOMElement $table): array
    {
        $rows = [];
        foreach ($table->getElementsByTagName('tr') as $row) {
            if ($row instanceof DOMElement && $this->closestTable($row) === $table) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** @return array<int, DOMElement> */
    private function tableCells(DOMElement $row): array
    {
        $cells = [];
        foreach ($this->childElements($row) as $child) {
            if (in_array(strtolower($child->tagName), ['td', 'th'], true)) {
                $cells[] = $child;
            }
        }

        return $cells;
    }

    private function closestTable(DOMElement $element): ?DOMElement
    {
        for ($node = $element->parentNode; $node instanceof DOMElement; $node = $node->parentNode) {
            if (strtolower($node->tagName) === 'table') {
                return $node;
            }
        }

        return null;
    }

    private function applyPageSetup(Worksheet $sheet, Template $template): void
    {
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
    }

    /** @return array<string, string> */
    private function styleFor(DOMElement $element, bool $includeInline = true): array
    {
        $style = [];
        $important = [];
        $apply = static function (string $name, string $value) use (&$style, &$important): void {
            $isImportant = preg_match('/\s*!important\s*$/i', $value) === 1;
            if (($important[$name] ?? false) && ! $isImportant) {
                return;
            }
            $style[$name] = trim(preg_replace('/\s*!important\s*$/i', '', $value) ?? $value);
            $important[$name] = $isImportant;
        };
        foreach ($this->cssRules as $rule) {
            if ($this->matchesSelector($element, $rule['selector'])) {
                foreach ($rule['declarations'] as $name => $value) {
                    $apply($name, $value);
                    if ($name === 'background') {
                        $apply('background-color', $value);
                    }
                }
            }
        }
        if ($includeInline) {
            foreach ($this->parseDeclarations($element->getAttribute('style')) as $name => $value) {
                $apply($name, $value);
                if ($name === 'background') {
                    $apply('background-color', $value);
                }
            }
        }

        return $style;
    }

    /**
     * CSS font and text properties are inherited by descendants in the report
     * viewer. Recreate that limited inheritance without leaking layout rules
     * such as a parent's margin, border or background into a child cell.
     *
     * @return array<string, string>
     */
    private function computedStyleFor(DOMElement $element): array
    {
        $ancestors = [];
        for ($node = $element->parentNode; $node instanceof DOMElement; $node = $node->parentNode) {
            $ancestors[] = $node;
        }

        $style = [];
        $inheritable = array_flip([
            'color', 'font-family', 'font-size', 'font-style', 'font-weight',
            'line-height', 'text-align', 'vertical-align', 'white-space',
        ]);
        foreach (array_reverse($ancestors) as $ancestor) {
            $style = array_replace($style, array_intersect_key($this->styleFor($ancestor), $inheritable));
        }

        return array_replace($style, $this->styleFor($element));
    }

    private function matchesSelector(DOMElement $element, string $selector): bool
    {
        $selector = trim(preg_replace('/\s*>\s*/', ' ', $selector) ?? '');
        if ($selector === '' || str_contains($selector, ':hover') || str_contains($selector, ':focus')) {
            return false;
        }
        $parts = preg_split('/\s+/', $selector, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($parts === []) {
            return false;
        }
        $current = $element;
        for ($index = count($parts) - 1; $index >= 0; --$index) {
            $part = $parts[$index];
            if ($index === count($parts) - 1) {
                if (! $this->matchesSimpleSelector($current, $part)) {
                    return false;
                }
                continue;
            }
            $matched = false;
            for ($node = $current->parentNode; $node instanceof DOMElement; $node = $node->parentNode) {
                if ($this->matchesSimpleSelector($node, $part)) {
                    $current = $node;
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                return false;
            }
        }

        return true;
    }

    private function matchesSimpleSelector(DOMElement $element, string $selector): bool
    {
        $selector = trim($selector);
        if ($selector === '*' || $selector === '') {
            return true;
        }
        $nth = null;
        if (preg_match('/:nth-child\((\d+)\)/', $selector, $matches)) {
            $nth = (int) $matches[1];
            $selector = str_replace($matches[0], '', $selector);
        }
        $firstChild = str_contains($selector, ':first-child');
        $selector = str_replace(':first-child', '', $selector);
        if ($nth !== null && $this->elementPosition($element) !== $nth) {
            return false;
        }
        if ($firstChild && $this->elementPosition($element) !== 1) {
            return false;
        }
        if (preg_match('/#([A-Za-z0-9_-]+)/', $selector, $id) && $element->getAttribute('id') !== $id[1]) {
            return false;
        }
        if (preg_match('/^([A-Za-z][A-Za-z0-9_-]*)/', $selector, $tag) && strtolower($element->tagName) !== strtolower($tag[1])) {
            return false;
        }
        if (preg_match_all('/\.([A-Za-z0-9_-]+)/', $selector, $classes)) {
            foreach ($classes[1] as $class) {
                if (! $this->hasClass($element, $class)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function elementPosition(DOMElement $element): int
    {
        $position = 0;
        for ($node = $element->parentNode?->firstChild; $node !== null; $node = $node->nextSibling) {
            if ($node instanceof DOMElement) {
                ++$position;
                if ($node === $element) {
                    return $position;
                }
            }
        }

        return 1;
    }

    private function applyStyle(Worksheet $sheet, string $range, array $style): void
    {
        $spreadsheetStyle = $sheet->getStyle($range);
        $font = $spreadsheetStyle->getFont();
        if (isset($style['font-family'])) {
            $font->setName(trim(explode(',', $style['font-family'])[0], " '\""));
        }
        if (isset($style['font-size'])) {
            $font->setSize($this->points($style['font-size'], 9));
        }
        if (isset($style['font-weight'])) {
            $font->setBold(in_array(strtolower($style['font-weight']), ['bold', 'bolder', '600', '700', '800', '900'], true));
        }
        if (isset($style['font-style'])) {
            $font->setItalic(strtolower($style['font-style']) === 'italic');
        }
        if (($color = $this->rgb($style['color'] ?? null)) !== null) {
            $font->getColor()->setRGB($color);
        }
        if (($background = $this->rgb($style['background-color'] ?? $style['background'] ?? null)) !== null) {
            $spreadsheetStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($background);
        }

        $alignment = $spreadsheetStyle->getAlignment();
        $alignment->setHorizontal(match (strtolower($style['text-align'] ?? 'left')) {
            'center' => Alignment::HORIZONTAL_CENTER,
            'right' => Alignment::HORIZONTAL_RIGHT,
            'justify' => Alignment::HORIZONTAL_JUSTIFY,
            default => Alignment::HORIZONTAL_LEFT,
        });
        $alignment->setVertical(match (strtolower($style['vertical-align'] ?? 'middle')) {
            'top' => Alignment::VERTICAL_TOP,
            'bottom' => Alignment::VERTICAL_BOTTOM,
            default => Alignment::VERTICAL_CENTER,
        });
        $alignment->setWrapText(! in_array(strtolower($style['white-space'] ?? ''), ['nowrap', 'pre'], true));
        if (($style['text-align'] ?? 'left') === 'left') {
            $alignment->setIndent($this->paddingIndent($style));
        }

        $this->applyBorders($spreadsheetStyle, $style);
    }

    private function applyBorders(\PhpOffice\PhpSpreadsheet\Style\Style $style, array $css): void
    {
        $borderMap = ['border' => 'allBorders', 'border-top' => 'top', 'border-right' => 'right', 'border-bottom' => 'bottom', 'border-left' => 'left'];
        foreach ($borderMap as $property => $target) {
            if (! isset($css[$property])) {
                continue;
            }
            $definition = $this->borderDefinition($css[$property]);
            if ($definition === null) {
                continue;
            }
            if ($target === 'allBorders') {
                $style->getBorders()->getAllBorders()->setBorderStyle($definition['style'])->setColor(new Color($definition['color']));
            } else {
                $style->getBorders()->{$this->borderGetter($target)}()->setBorderStyle($definition['style'])->setColor(new Color($definition['color']));
            }
        }
    }

    /** @return array{style: string, color: string}|null */
    private function borderDefinition(string $value): ?array
    {
        if (str_contains(strtolower($value), 'none')) {
            return null;
        }
        $color = $this->rgb($value) ?? 'B7B7B7';
        $style = str_contains($value, '2px') || str_contains($value, '3px') ? Border::BORDER_MEDIUM : Border::BORDER_THIN;

        return ['style' => $style, 'color' => $color];
    }

    private function borderGetter(string $side): string
    {
        return 'get'.ucfirst($side);
    }

    private function points(string $value, float $fallback): float
    {
        if (preg_match('/([\d.]+)px/', $value, $matches)) {
            return max(1, (float) $matches[1] * 0.75);
        }
        if (preg_match('/([\d.]+)pt/', $value, $matches)) {
            return max(1, (float) $matches[1]);
        }
        if (is_numeric($value)) {
            return (float) $value;
        }

        return $fallback;
    }

    private function tableTextRowHeight(Worksheet $sheet, string $text, array $style, int $startColumn, int $endColumn): float
    {
        $width = 0.0;
        for ($column = $startColumn; $column <= $endColumn; ++$column) {
            $width += (float) $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->getWidth();
        }

        return $this->textRowHeight($text, $style, $width);
    }

    private function textRowHeight(string $text, array $style, ?float $availableWidth = null): float
    {
        $fontSize = $this->points((string) ($style['font-size'] ?? '9px'), 9);
        $lines = max(1, substr_count($text, "\n") + 1);
        if ($availableWidth !== null && ! in_array(strtolower($style['white-space'] ?? ''), ['nowrap', 'pre'], true)) {
            $lines = 0;
            foreach (explode("\n", $text) as $line) {
                $length = function_exists('mb_strwidth') ? mb_strwidth($line, 'UTF-8') : strlen($line);
                $lines += max(1, (int) ceil($length / max(1.0, $availableWidth)));
            }
        }
        $padding = $this->pixels($style['padding'] ?? '0') * 1.5;

        return max(14.5, $fontSize * 1.45 * $lines + $padding);
    }

    private function paddingIndent(array $style): int
    {
        $padding = $this->pixels((string) ($style['padding-left'] ?? $style['padding'] ?? '0'));

        return $padding >= 3 ? 1 : 0;
    }

    private function pixels(string $value): float
    {
        return preg_match('/([\d.]+)px/', $value, $matches) ? (float) $matches[1] : 0.0;
    }

    private function addVerticalSpacing(Worksheet $sheet, array $style, string $side, int &$row): void
    {
        $value = $style[$side] ?? $this->boxSideValue((string) ($style[str_starts_with($side, 'padding') ? 'padding' : 'margin'] ?? ''), $side);
        $points = $this->lengthToPoints((string) $value);
        if ($points > 0) {
            $sheet->getRowDimension($row)->setRowHeight($points);
            ++$row;
        }
    }

    private function boxSideValue(string $value, string $side): string
    {
        $parts = preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($parts === []) {
            return '0';
        }
        $index = match ($side) {
            'margin-top', 'padding-top' => 0,
            'margin-right', 'padding-right' => count($parts) === 1 ? 0 : 1,
            'margin-bottom', 'padding-bottom' => count($parts) < 3 ? 0 : 2,
            default => count($parts) === 1 ? 0 : (count($parts) === 2 ? 1 : 3),
        };

        return $parts[$index] ?? '0';
    }

    private function lengthToPoints(string $value): float
    {
        if (preg_match('/([\d.]+)mm/', $value, $matches)) {
            return (float) $matches[1] * 2.83465;
        }
        if (preg_match('/([\d.]+)px/', $value, $matches)) {
            return (float) $matches[1] * 0.75;
        }
        if (preg_match('/([\d.]+)pt/', $value, $matches)) {
            return (float) $matches[1];
        }

        return 0.0;
    }

    private function rgb(?string $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        if (preg_match('/#([0-9a-f]{3}|[0-9a-f]{6})\b/i', $value, $matches)) {
            $hex = strtoupper($matches[1]);

            return strlen($hex) === 3 ? $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2] : $hex;
        }
        if (preg_match('/rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/i', $value, $matches)) {
            return sprintf('%02X%02X%02X', $matches[1], $matches[2], $matches[3]);
        }
        $named = ['black' => '000000', 'white' => 'FFFFFF', 'red' => 'FF0000', 'green' => '008000', 'blue' => '0000FF', 'transparent' => null];

        return $named[strtolower(trim($value))] ?? null;
    }

    private function nodeText(DOMNode $node): string
    {
        $text = $this->nodeTextRecursive($node);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */', "\n", $text) ?? $text;

        return trim($text);
    }

    private function setNodeValue(Worksheet $sheet, string $cell, DOMNode $node): void
    {
        if (! $this->hasInlineFormatting($node)) {
            $sheet->setCellValueExplicit($cell, $this->nodeText($node), DataType::TYPE_STRING);

            return;
        }

        $richText = new RichText();
        $this->appendRichText($richText, $node);
        $sheet->setCellValue($cell, $richText);
    }

    private function appendRichText(RichText $richText, DOMNode $node, bool $bold = false, bool $italic = false): void
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $text = preg_replace('/\s+/u', ' ', $node->nodeValue ?? '') ?? '';
            if ($text !== '') {
                $run = $richText->createTextRun($text);
                $run->getFont()->setBold($bold)->setItalic($italic);
            }

            return;
        }
        if ($node instanceof DOMElement && strtolower($node->tagName) === 'br') {
            $richText->createText("\n");

            return;
        }
        $tag = $node instanceof DOMElement ? strtolower($node->tagName) : '';
        $bold = $bold || in_array($tag, ['b', 'strong'], true);
        $italic = $italic || in_array($tag, ['i', 'em'], true);
        foreach ($node->childNodes as $child) {
            $this->appendRichText($richText, $child, $bold, $italic);
        }
    }

    private function hasInlineFormatting(DOMNode $node): bool
    {
        if ($node instanceof DOMElement && in_array(strtolower($node->tagName), ['b', 'strong', 'i', 'em'], true)) {
            return true;
        }
        foreach ($node->childNodes as $child) {
            if ($this->hasInlineFormatting($child)) {
                return true;
            }
        }

        return false;
    }

    private function nodeTextRecursive(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return preg_replace('/\s+/u', ' ', $node->nodeValue ?? '') ?? '';
        }
        $result = '';
        $previous = null;
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && strtolower($child->tagName) === 'br') {
                $result .= "\n";
                $previous = $child;
                continue;
            }
            $text = $this->nodeTextRecursive($child);
            if ($this->needsInlineSpace($previous, $child, $result, $text)) {
                $result .= ' ';
            }
            $result .= $text;
            $previous = $child;
        }

        return $result;
    }

    private function needsInlineSpace(?DOMNode $previous, DOMNode $current, string $result, string $text): bool
    {
        if (! $previous instanceof DOMElement || ! $current instanceof DOMElement || $result === '' || $text === '') {
            return false;
        }
        $inline = ['b', 'strong', 'span', 'i', 'em'];
        if (! in_array(strtolower($previous->nodeName), $inline, true) || ! in_array(strtolower($current->nodeName), $inline, true)) {
            return false;
        }

        return ! preg_match('/\s$/u', $result) && ! preg_match('/^[,.;:%)]/u', $text);
    }

    private function hasBoldText(DOMElement $element): bool
    {
        if (in_array(strtolower($element->tagName), ['b', 'strong'], true)) {
            return true;
        }

        return $element->getElementsByTagName('b')->length > 0 || $element->getElementsByTagName('strong')->length > 0;
    }

    private function isTextBlock(DOMElement $element): bool
    {
        $tag = strtolower($element->tagName);
        if (! in_array($tag, ['div', 'span', 'section'], true)) {
            return false;
        }
        $allowed = ['span', 'b', 'strong', 'i', 'em', 'br', 'img'];
        foreach ($this->childElements($element) as $child) {
            if (! in_array(strtolower($child->tagName), $allowed, true)) {
                return false;
            }
        }

        return $this->nodeText($element) !== '';
    }

    /** @return array<int, DOMElement> */
    private function childElements(DOMElement $element, ?string $tagName = null): array
    {
        $children = [];
        foreach ($element->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }
            if ($tagName === null || strtolower($child->tagName) === strtolower($tagName)) {
                $children[] = $child;
            }
        }

        return $children;
    }

    private function hasClass(DOMElement $element, string $class): bool
    {
        return in_array($class, preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [], true);
    }

    private function firstByClass(DOMElement $element, string $class): ?DOMElement
    {
        $xpath = new DOMXPath($element->ownerDocument);
        $nodes = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")]', $element);
        $node = $nodes?->item(0);

        return $node instanceof DOMElement ? $node : null;
    }

    private function sheetTitle(DOMDocument $document, Template $template): string
    {
        foreach (['h1', 'h2'] as $tag) {
            $heading = $document->getElementsByTagName($tag)->item(0);
            if ($heading instanceof DOMElement && $this->nodeText($heading) !== '') {
                return $this->safeSheetTitle($this->nodeText($heading));
            }
        }

        return $this->safeSheetTitle($template->name ?: 'Report');
    }

    private function safeSheetTitle(string $title): string
    {
        $title = preg_replace('/[\\\\\[\]\*\?:\/]/', ' ', $title) ?? 'Report';
        $title = trim(Str::limit($title, 31, ''));

        return $title !== '' ? $title : 'Report';
    }
}
