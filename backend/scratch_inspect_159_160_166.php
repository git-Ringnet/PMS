<?php
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$filePath = 'C:/Users/Nguyen Tho Thang/Downloads/DANH MỤC BÁO CÁO.xlsx';
$reader = IOFactory::createReaderForFile($filePath);
$spreadsheet = $reader->load($filePath);

$indices = [62 => 'Dòng 159', 71 => 'Dòng 160', 16 => 'Dòng 166'];
foreach ($indices as $idx => $label) {
    $sheet = $spreadsheet->getSheet($idx);
    echo "=== $label: Sheet {$sheet->getTitle()} ===\n";
    $count = 0;
    foreach ($sheet->getRowIterator() as $row) {
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(true);
        $cells = [];
        foreach ($cellIterator as $cell) {
            $v = trim((string)$cell->getFormattedValue());
            if ($v !== '') {
                $cells[] = $cell->getCoordinate() . ': ' . $v;
            }
        }
        if (!empty($cells)) {
            $count++;
            if ($count <= 25) {
                echo "Row " . $row->getRowIndex() . ": " . implode(" | ", $cells) . "\n";
            }
        }
    }
    echo "Total rows with data: $count\n\n";
}
