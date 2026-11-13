<?php

namespace App\Services;

use App\Models\Nationality;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Illuminate\Support\Str;

/** Nhập thông tin khách từ Excel/CSV vào bảng "Thông tin khách" (TC_LT_27 dòng 369). Chỉ đọc file, không ghi DB. */
class GuestExcelImportService
{
    /** key => tiêu đề cột trong file mẫu (cùng tiêu đề với nút Xuất Excel ở màn hình Thông tin khách). */
    public const COLUMNS = [
        'room_number' => 'Số Phòng',
        'title' => 'Danh Xưng',
        'full_name' => 'Họ Và Tên',
        'dob' => 'Ngày Sinh',
        'nationality_code' => 'Quốc Tịch',
        'id_type' => 'Loại Giấy Tờ',
        'id_number' => 'Số Giấy Tờ',
        'passport_expiry' => 'Ngày Hết Hạn',
        'province' => 'Tỉnh Thành',
        'district' => 'Quận/ Huyện',
        'ward' => 'Phường/ Xã',
        'phone' => 'Điện Thoại',
        'email' => 'Email',
        'address' => 'Địa Chỉ',
        'guest_type' => 'Loại Khách',
        'visa_no' => 'Số Visa',
        'residence_type' => 'Thường Trú/Tạm Trú',
        'temp_residence_to' => 'Tạm Trú Đến',
        'entry_date' => 'Ngày Nhập Cảnh',
        'visa_expiry_date' => 'Ngày Hết Hạn Visa',
        'entry_purpose' => 'Mục Đích Nhập Cảnh',
        'border_gate' => 'Cửa Khẩu',
        'note' => 'Ghi Chú',
    ];

    public const DATE_FIELDS = ['dob', 'passport_expiry', 'temp_residence_to', 'entry_date', 'visa_expiry_date'];

    private static function normalize(mixed $value): string
    {
        return Str::of(Str::ascii(trim(str_replace("ï»¿", '', (string) $value))))->lower()->replaceMatches('/[^a-z0-9]+/', '')->toString();
    }

    /** @return array{rows: array<int, array<string, string>>, skipped: int} */
    public function parse(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $matrix = $sheet->toArray(null, true, false, false);

        $headerMap = [];
        $headerIndex = null;
        foreach ($matrix as $i => $row) {
            $found = [];
            foreach ($row as $col => $cell) {
                $norm = self::normalize($cell);
                foreach (self::COLUMNS as $key => $label) {
                    if ($norm !== '' && $norm === self::normalize($label)) {
                        $found[$col] = $key;
                    }
                }
            }
            if (in_array('full_name', $found, true)) {
                $headerMap = $found;
                $headerIndex = $i;
                break;
            }
        }
        if ($headerIndex === null) {
            throw new \InvalidArgumentException('File không đúng mẫu: thiếu cột "Họ Và Tên". Vui lòng tải file mẫu.');
        }

        $nationalities = $this->nationalityLookup();
        $rows = [];
        $skipped = 0;
        foreach (array_slice($matrix, $headerIndex + 1, null, true) as $row) {
            $item = [];
            foreach ($headerMap as $col => $key) {
                $raw = $row[$col] ?? null;
                $value = is_string($raw) ? trim($raw) : $raw;
                if ($value === null || $value === '' || $value === '—') {
                    continue;
                }
                $item[$key] = match (true) {
                    in_array($key, self::DATE_FIELDS, true) => $this->date($value),
                    $key === 'nationality_code' => $nationalities[self::normalize($value)] ?? trim((string) $value),
                    default => trim((string) $value),
                };
            }
            if (empty($item['full_name'])) {
                if ($item) {
                    $skipped++;
                }
                continue;
            }
            $rows[] = array_filter($item, fn ($v) => $v !== '' && $v !== null);
        }

        return ['rows' => $rows, 'skipped' => $skipped];
    }

    /** Nội dung CSV (UTF-8 có BOM để Excel đọc đúng tiếng Việt): dòng tiêu đề + 1 dòng ví dụ. */
    public function templateCsv(): string
    {
        $out = fopen('php://temp', 'w+');
        fwrite($out, "ï»¿");
        fputcsv($out, array_values(self::COLUMNS));
        fputcsv($out, ['', 'Mr.', 'NGUYEN VAN A', '15/05/1990', 'VN', 'CCCD', '012345678901']);
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    private function date(mixed $value): string
    {
        if (is_numeric($value) && (float) $value > 20000) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }
        $text = trim((string) $value);
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd.m.Y'] as $format) {
            try {
                $parsed = Carbon::createFromFormat('!' . $format, $text);
            } catch (\Throwable) {
                continue;
            }
            if ($parsed && $parsed->format($format) === $text) {
                return $parsed->format('Y-m-d');
            }
        }

        return '';
    }

    /** Chuẩn hóa tên/mã quốc tịch -> mã quốc tịch. */
    private function nationalityLookup(): array
    {
        $map = [];
        foreach (Nationality::query()->get(['nationality_code', 'nationality_name', 'nationality_name_en']) as $n) {
            foreach ([$n->nationality_code, $n->nationality_name, $n->nationality_name_en, $n->nationality_code . ' - ' . $n->nationality_name] as $alias) {
                if ($alias) {
                    $map[self::normalize($alias)] = $n->nationality_code;
                }
            }
        }

        return $map;
    }
}
