<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Models\HotelConfig;
use App\Services\ChainCatalogSyncService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    private const REQUIRED_FIELDS_CONFIG = 'CreateCompanyRequiredField';

    private const SPREADSHEET_HEADERS = [
        'Mã', 'Tên', 'Tên Giao Dịch', 'Địa Chỉ', 'Mã Số Thuế', 'Số Điện Thoại',
        'Email', 'Nguồn Khách', 'Thị Trường', 'Công Nợ Tối Đa', 'Tài Khoản Ngân Hàng',
        'Người Đặt Phòng', 'Mã Giá Phòng', 'Chi Nhánh', 'Trạng Thái Sử Dụng',
    ];

    private const CONFIGURABLE_REQUIRED_FIELDS = [
        'address', 'tax_code', 'phone', 'email', 'customer_source_id', 'market_id',
        'max_debt', 'bank_account', 'booker_id', 'sales_person_id', 'branch_id',
    ];

    /**
     * Display a listing of companies with relationships.
     */
    public function index(Request $request)
    {
        $query = Company::with(['customerSource', 'market', 'branch', 'booker', 'salesPerson']);

        // Search by name or code
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('trading_name', 'like', "%{$search}%");
            });
        }

        // Filter by market
        if ($request->has('market_id') && !empty($request->market_id)) {
            $query->where('market_id', $request->market_id);
        }

        // Filter by customer source
        if ($request->has('customer_source_id') && !empty($request->customer_source_id)) {
            $query->where('customer_source_id', $request->customer_source_id);
        }

        // Filter by branch
        if ($request->has('branch_id') && !empty($request->branch_id)) {
            $query->where('branch_id', $request->branch_id);
        }

        $perPage = $request->input('per_page', 100);
        $companies = $query->orderBy('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => CompanyResource::collection($companies->items()),
            'meta' => [
                'current_page' => $companies->currentPage(),
                'last_page' => $companies->lastPage(),
                'per_page' => $companies->perPage(),
                'total' => $companies->total(),
                'company_required_fields' => $this->requiredCompanyFields(),
            ],
        ]);
    }

    /**
     * Store a newly created company.
     */
    public function store(Request $request)
    {
        $validated = $this->validateCompanyPayload($request->all());

        // Đồng bộ chuỗi: mã công ty tính trên tất cả chi nhánh để dùng chung một mã
        $chainSync = app(ChainCatalogSyncService::class);
        if ($chainSync->enabled()) {
            $validated['code'] = $chainSync->nextCompanyCode();
        }

        $company = Company::create($validated);
        $syncWarnings = $chainSync->companyCreated($company);
        $company->load(['customerSource', 'market', 'branch', 'booker', 'salesPerson']);

        return response()->json([
            'success' => true,
            'data' => new CompanyResource($company),
            'sync_warnings' => $syncWarnings,
        ], 201);
    }

    /**
     * Display the specified company.
     */
    public function show($id)
    {
        $company = Company::with(['customerSource', 'market', 'branch', 'booker', 'salesPerson'])->find($id);
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }
        return response()->json([
            'success' => true,
            'data' => new CompanyResource($company),
        ]);
    }

    /**
     * Update the specified company.
     */
    public function update(Request $request, $id)
    {
        $company = Company::find($id);
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validated = $this->validateCompanyPayload($request->all(), $company);

        $original = $company->getOriginal();
        $company->update($validated);
        $syncWarnings = app(ChainCatalogSyncService::class)->companyUpdated($company, $original);
        $company->load(['customerSource', 'market', 'branch', 'booker', 'salesPerson']);

        return response()->json([
            'success' => true,
            'data' => new CompanyResource($company),
            'sync_warnings' => $syncWarnings,
        ]);
    }

    /**
     * Remove the specified company.
     */
    public function destroy($id)
    {
        $company = Company::find($id);
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $chainSync = app(ChainCatalogSyncService::class);
        $issues = $chainSync->companyDeletionIssues($company);
        if ($issues !== []) {
            return response()->json([
                'message' => "Không thể xóa công ty '{$company->name}': " . $this->formatCompanyDeletionIssues($issues),
            ], 422);
        }

        if ($chainSync->enabled()) {
            $result = $chainSync->deactivateCompanyCopies($company->getAttributes());
            if (!$result['success']) {
                return response()->json(['message' => $result['message'] ?? 'Không thể ẩn công ty trên toàn bộ chi nhánh.'], 503);
            }

            $company->update(['is_active' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Công ty đã được ẩn trên toàn bộ chi nhánh.',
                'sync_warnings' => [],
            ]);
        }

        $company->delete();

        return response()->json([
            'success' => true,
            'message' => 'Company deleted successfully',
            'sync_warnings' => [],
        ]);
    }

    /** @return string[] */
    private function requiredCompanyFields(): array
    {
        $value = (string) HotelConfig::where('name', self::REQUIRED_FIELDS_CONFIG)->value('value');
        $fields = preg_split('/[,;|]+/', strtolower($value)) ?: [];

        return array_values(array_unique(array_filter(
            array_map('trim', $fields),
            fn (string $field) => in_array($field, self::CONFIGURABLE_REQUIRED_FIELDS, true)
        )));
    }

    /** @return array<string, mixed> */
    private function validateCompanyPayload(array $payload, ?Company $company = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'trading_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'tax_code' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'customer_source_id' => ['nullable', 'exists:customer_sources,id'],
            'market_id' => ['nullable', 'exists:markets,id'],
            'sync_acc' => ['nullable', 'boolean'],
            'max_debt' => ['nullable', 'numeric', 'min:0'],
            'bank_account' => ['nullable', 'string', 'max:255'],
            'booker_id' => ['nullable', 'exists:bookers,id'],
            'sales_person_id' => ['nullable', Rule::exists('mysql_system.users', 'id')],
            'rate_code' => ['nullable', 'string', 'max:100'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'is_active' => ['nullable', 'boolean'],
        ];
        if (!$company) {
            $rules['code'] = ['nullable', 'string', 'max:50', 'unique:companies,code'];
        }

        foreach ($this->requiredCompanyFields() as $field) {
            $rules[$field] = array_values(array_filter(
                ['required', ...$rules[$field]],
                fn ($rule) => $rule !== 'nullable'
            ));
        }

        return validator($payload, $rules)->validate();
    }

    /** @param array<int, array{branch:string, reason:string, references?:array<string,int>}> $issues */
    private function formatCompanyDeletionIssues(array $issues): string
    {
        return collect($issues)->map(function (array $issue) {
            if ($issue['reason'] === 'used') {
                $references = collect($issue['references'] ?? [])
                    ->map(fn (int $count, string $name) => "{$count} {$name}")
                    ->implode(', ');
                return "Chi nhánh {$issue['branch']} đang sử dụng ({$references})";
            }
            if ($issue['reason'] === 'default') {
                return "Chi nhánh {$issue['branch']} đang đặt làm Công ty mặc định";
            }
            if ($issue['reason'] === 'conflict') {
                return "Chi nhánh {$issue['branch']} có công ty cùng mã nhưng dữ liệu không khớp";
            }
            return "không thể kiểm tra chi nhánh {$issue['branch']}";
        })->implode('; ') . '.';
    }

    /**
     * Synchronize companies with ACC accounting system.
     */
    public function sync(Request $request)
    {
        $count = Company::query()->update(['sync_acc' => true]);

        return response()->json([
            'success' => true,
            'message' => "Đồng bộ thành công! Đã đồng bộ {$count} công ty với hệ thống kế toán ACC."
        ]);
    }

    /** Xuất danh sách công ty dưới dạng XLSX, mỗi thuộc tính là một cột. */
    public function export(Request $request)
    {
        $companies = Company::with(['customerSource', 'market', 'branch', 'booker', 'salesPerson'])->get();

        return $this->downloadSpreadsheet(
            $companies->map(fn (Company $company) => $this->companySpreadsheetRow($company))->all(),
            'companies.xlsx'
        );
    }

    /** Nhập XLSX mới hoặc CSV legacy; mỗi dòng trả trạng thái xử lý riêng. */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls',
        ]);

        try {
            $rows = $this->readImportRows($request->file('file'));
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $lookups = [
            'source' => \App\Models\CustomerSource::all(),
            'market' => \App\Models\Market::all(),
            'branch' => \App\Models\Branch::all(),
            'booker' => \App\Models\Booker::all(),
        ];
        $results = [];
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        $seenIdentifiers = [];

        foreach ($rows as $row) {
            $result = $this->importCompanyRow($row['number'], $row['values'], $lookups, $seenIdentifiers);
            $results[] = $result;
            $counts[$result['status']]++;
        }

        return response()->json([
            'success' => $counts['failed'] === 0,
            'message' => "Đã tạo {$counts['created']}, cập nhật {$counts['updated']}, bỏ qua {$counts['skipped']}, lỗi {$counts['failed']} dòng.",
            'summary' => $counts + ['total' => count($results)],
            'rows' => $results,
        ], $counts['failed'] > 0 && $counts['created'] === 0 && $counts['updated'] === 0 ? 422 : 200);
    }

    /** Tải file XLSX mẫu; CSV legacy vẫn được chấp nhận ở API import. */
    public function template(Request $request)
    {
        return $this->downloadSpreadsheet([[
            'CTY0001', 'Công ty TNHH Du lịch Việt', 'Viet Travel', '123 Nguyễn Huệ, Quận 1, TP. Hồ Chí Minh',
            '0102030405', '02839999999', 'info@viettravel.com', 'AGODA', 'OTA', 50000000,
            '1234567890', 'Nguyễn Văn A', 'GP01', 'HKT1', 'Đang sử dụng',
        ]], 'company_template.xlsx');
    }

    /** @return array<int, mixed> */
    private function companySpreadsheetRow(Company $company): array
    {
        return [
            $company->code, $company->name, $company->trading_name, $company->address, $company->tax_code,
            $company->phone, $company->email, $company->customerSource?->name ?? '', $company->market?->name ?? '',
            (float) $company->max_debt, $company->bank_account, $company->booker?->name ?? '', $company->rate_code,
            $company->branch?->name ?? '', $company->is_active ? 'Đang sử dụng' : 'Không sử dụng',
        ];
    }

    /** @param array<int, array<int, mixed>> $rows */
    private function downloadSpreadsheet(array $rows, string $filename)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Công Ty');
        $sheet->fromArray(self::SPREADSHEET_HEADERS, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count(self::SPREADSHEET_HEADERS));
        $sheet->freezePane('A2');
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF006BDB']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ]);
        foreach (range(1, count(self::SPREADSHEET_HEADERS)) as $index) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }
        $sheet->getStyle("J2:J" . max(2, count($rows) + 1))->getNumberFormat()->setFormatCode('#,##0');

        return response()->streamDownload(function () use ($spreadsheet) {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** @return array<int, array{number:int, values:array<string, string>}> */
    private function readImportRows(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (in_array($extension, ['xlsx', 'xls'], true)) {
            $matrix = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath())->getActiveSheet()->toArray('', true, true, false);
        } else {
            $handle = fopen($file->getRealPath(), 'r');
            if (!$handle) throw new \RuntimeException('Không thể đọc file upload.');
            $first = fread($handle, 3);
            if ($first !== "\xEF\xBB\xBF") rewind($handle);
            $matrix = [];
            while (($csvRow = fgetcsv($handle)) !== false) $matrix[] = $csvRow;
            fclose($handle);
        }
        if ($matrix === []) throw new \RuntimeException('File nhập trống.');

        $header = array_shift($matrix);
        $headerMap = [];
        foreach ($header as $index => $label) {
            $field = $this->companyImportField((string) $label);
            if ($field) $headerMap[$index] = $field;
        }
        if (!in_array('name', $headerMap, true)) throw new \RuntimeException('File phải có cột Tên.');

        $rows = [];
        foreach ($matrix as $index => $rawRow) {
            $values = [];
            foreach ($headerMap as $column => $field) $values[$field] = trim((string) ($rawRow[$column] ?? ''));
            $rows[] = ['number' => $index + 2, 'values' => $values];
        }
        return $rows;
    }

    private function companyImportField(string $header): ?string
    {
        return [
            'ma' => 'code', 'ten' => 'name', 'tengiaodich' => 'trading_name', 'diachi' => 'address',
            'tax' => 'tax_code', 'masothue' => 'tax_code', 'sodienthoai' => 'phone', 'email' => 'email',
            'nguonkhach' => 'customer_source', 'thitruong' => 'market', 'congnotoida' => 'max_debt',
            'taikhoannganhang' => 'bank_account', 'nguoidatphong' => 'booker', 'magiarphong' => 'rate_code',
            'chinhanh' => 'branch', 'trangthaisudung' => 'is_active',
        ][$this->normalizeImportValue($header)] ?? null;
    }

    private function normalizeImportValue(string $value): string
    {
        return Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '')->toString();
    }

    /** @param array<string,string> $values @param array<string,\Illuminate\Support\Collection> $lookups @param array<string,int> $seenIdentifiers */
    private function importCompanyRow(int $rowNumber, array $values, array $lookups, array &$seenIdentifiers): array
    {
        if (collect($values)->filter(fn ($value) => $value !== '')->isEmpty()) {
            return ['row' => $rowNumber, 'status' => 'skipped', 'message' => 'Dòng trống.'];
        }
        $name = trim($values['name'] ?? '');
        if ($name === '') return ['row' => $rowNumber, 'status' => 'failed', 'message' => 'Thiếu Tên công ty.'];
        if (($values['email'] ?? '') !== '' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            return ['row' => $rowNumber, 'status' => 'failed', 'message' => 'Email không hợp lệ.'];
        }

        $code = trim($values['code'] ?? '');
        foreach (array_filter([
            $code !== '' ? 'code:' . $this->normalizeImportValue($code) : null,
            'name:' . $this->normalizeImportValue($name),
        ]) as $identifier) {
            if (isset($seenIdentifiers[$identifier])) {
                return ['row' => $rowNumber, 'status' => 'failed', 'message' => "Trùng dữ liệu với dòng {$seenIdentifiers[$identifier]} trong file."];
            }
        }

        $references = [];
        $referenceLabels = [
            'customer_source' => 'Nguồn khách', 'market' => 'Thị trường',
            'branch' => 'Chi nhánh', 'booker' => 'Người đặt phòng',
        ];
        foreach (['customer_source' => 'source', 'market' => 'market', 'branch' => 'branch', 'booker' => 'booker'] as $field => $lookup) {
            $value = $values[$field] ?? '';
            if ($value === '') { $references[$field . '_id'] = null; continue; }
            $matched = $lookups[$lookup]->first(fn ($item) => $this->normalizeImportValue((string) ($item->code ?? '')) === $this->normalizeImportValue($value)
                || $this->normalizeImportValue((string) ($item->name ?? '')) === $this->normalizeImportValue($value)
                || ($lookup === 'booker' && $this->normalizeImportValue((string) ($item->email ?? '')) === $this->normalizeImportValue($value)));
            if (!$matched) return ['row' => $rowNumber, 'status' => 'failed', 'message' => "Không tìm thấy {$referenceLabels[$field]}: {$value}." ];
            $references[$field . '_id'] = $matched->id;
        }

        $debtText = str_replace([',', '.', ' '], '', $values['max_debt'] ?? '0');
        if ($debtText !== '' && !is_numeric($debtText)) return ['row' => $rowNumber, 'status' => 'failed', 'message' => 'Công nợ tối đa không hợp lệ.'];
        $byCode = $code !== '' ? Company::where('code', $code)->first() : null;
        $byName = Company::where('name', $name)->first();
        if ($byCode && $byName && $byCode->id !== $byName->id) {
            return ['row' => $rowNumber, 'status' => 'failed', 'message' => 'Mã và tên đang thuộc hai công ty khác nhau.'];
        }
        $company = $byCode ?: $byName;
        $data = [
            'name' => $name, 'trading_name' => ($values['trading_name'] ?? '') ?: null,
            'address' => ($values['address'] ?? '') ?: null, 'tax_code' => ($values['tax_code'] ?? '') ?: null,
            'phone' => ($values['phone'] ?? '') ?: null, 'email' => ($values['email'] ?? '') ?: null,
            'max_debt' => $debtText === '' ? 0 : (float) $debtText, 'bank_account' => ($values['bank_account'] ?? '') ?: null,
            'rate_code' => ($values['rate_code'] ?? '') ?: null,
            'is_active' => !in_array($this->normalizeImportValue($values['is_active'] ?? 'dangsudung'), ['0', 'false', 'khongsudung'], true),
        ] + $references;
        if ($company) {
            DB::transaction(fn () => $company->update($data));
            foreach (array_filter([$code !== '' ? 'code:' . $this->normalizeImportValue($code) : null, 'name:' . $this->normalizeImportValue($name)]) as $identifier) $seenIdentifiers[$identifier] = $rowNumber;
            return ['row' => $rowNumber, 'status' => 'updated', 'message' => 'Đã cập nhật.', 'company_id' => $company->id];
        }
        if ($code !== '') $data['code'] = $code;
        $company = DB::transaction(fn () => Company::create($data));
        foreach (array_filter([$code !== '' ? 'code:' . $this->normalizeImportValue($code) : null, 'name:' . $this->normalizeImportValue($name)]) as $identifier) $seenIdentifiers[$identifier] = $rowNumber;
        return ['row' => $rowNumber, 'status' => 'created', 'message' => 'Đã tạo mới.', 'company_id' => $company->id];
    }
}
