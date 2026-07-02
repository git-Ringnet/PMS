<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    /**
     * Display a listing of companies with relationships.
     */
    public function index(Request $request)
    {
        $query = Company::with(['customerSource', 'market', 'branch', 'booker']);

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

        $perPage = $request->get('per_page', 100);
        $companies = $query->orderBy('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => CompanyResource::collection($companies->items()),
            'meta' => [
                'current_page' => $companies->currentPage(),
                'last_page' => $companies->lastPage(),
                'per_page' => $companies->perPage(),
                'total' => $companies->total(),
            ],
        ]);
    }

    /**
     * Store a newly created company.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'trading_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'tax_code' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'customer_source_id' => 'nullable|exists:customer_sources,id',
            'market_id' => 'nullable|exists:markets,id',
            'sync_acc' => 'nullable|boolean',
            'max_debt' => 'nullable|numeric|min:0',
            'bank_account' => 'nullable|string|max:255',
            'booker_id' => 'nullable|exists:bookers,id',
            'rate_code' => 'nullable|string|max:100',
            'branch_id' => 'nullable|exists:branches,id',
            'is_active' => 'nullable|boolean',
        ]);

        $company = Company::create($validated);
        $company->load(['customerSource', 'market', 'branch', 'booker']);

        return response()->json([
            'success' => true,
            'data' => new CompanyResource($company),
        ], 201);
    }

    /**
     * Display the specified company.
     */
    public function show($id)
    {
        $company = Company::with(['customerSource', 'market', 'branch', 'booker'])->find($id);
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

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'trading_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'tax_code' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'customer_source_id' => 'nullable|exists:customer_sources,id',
            'market_id' => 'nullable|exists:markets,id',
            'sync_acc' => 'nullable|boolean',
            'max_debt' => 'nullable|numeric|min:0',
            'bank_account' => 'nullable|string|max:255',
            'booker_id' => 'nullable|exists:bookers,id',
            'rate_code' => 'nullable|string|max:100',
            'branch_id' => 'nullable|exists:branches,id',
            'is_active' => 'nullable|boolean',
        ]);

        $company->update($validated);
        $company->load(['customerSource', 'market', 'branch', 'booker']);

        return response()->json([
            'success' => true,
            'data' => new CompanyResource($company),
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

        $company->delete();

        return response()->json([
            'success' => true,
            'message' => 'Company deleted successfully',
        ]);
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

    /**
     * Export all companies as CSV file formatted for Excel.
     */
    public function export(Request $request)
    {
        $companies = Company::with(['customerSource', 'market', 'branch', 'booker'])->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="companies.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function() use ($companies) {
            $file = fopen('php://output', 'w');
            
            // Add UTF-8 BOM for Vietnamese character support in Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // CSV Headers
            fputcsv($file, [
                'Mã',
                'Tên',
                'Tên giao dịch',
                'Địa chỉ',
                'Tax',
                'Số điện thoại',
                'Email',
                'Nguồn khách',
                'Thị trường',
                'Công nợ tối đa',
                'Tài khoản ngân hàng',
                'Người đặt phòng',
                'Mã giá phòng',
                'Chi nhánh'
            ]);

            // CSV Rows
            foreach ($companies as $c) {
                fputcsv($file, [
                    $c->code,
                    $c->name,
                    $c->trading_name,
                    $c->address,
                    $c->tax_code,
                    $c->phone,
                    $c->email,
                    $c->customerSource?->name ?? '',
                    $c->market?->name ?? '',
                    $c->max_debt,
                    $c->bank_account,
                    $c->booker?->name ?? '',
                    $c->rate_code,
                    $c->branch?->name ?? ''
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Import companies from uploaded CSV file.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file'
        ]);

        $file = $request->file('file');
        $filePath = $file->getRealPath();

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể đọc file upload.'
            ], 422);
        }

        // Check and remove BOM if present
        $bom = fread($handle, 3);
        if ($bom !== chr(0xEF) . chr(0xBB) . chr(0xBF)) {
            rewind($handle);
        }

        // Read header
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return response()->json([
                'success' => false,
                'message' => 'File CSV trống.'
            ], 422);
        }

        $importedCount = 0;

        // Cache lookups for speed
        $sources = \App\Models\CustomerSource::all();
        $markets = \App\Models\Market::all();
        $branches = \App\Models\Branch::all();
        $bookers = \App\Models\Booker::all();

        while (($row = fgetcsv($handle)) !== false) {
            // Skip empty rows
            if (empty($row) || count($row) < 2 || empty(trim($row[1]))) {
                continue;
            }

            $code = trim($row[0] ?? '');
            $name = trim($row[1] ?? '');
            $tradingName = trim($row[2] ?? '');
            $address = trim($row[3] ?? '');
            $taxCode = trim($row[4] ?? '');
            $phone = trim($row[5] ?? '');
            $email = trim($row[6] ?? '');
            $sourceNameOrCode = trim($row[7] ?? '');
            $marketNameOrCode = trim($row[8] ?? '');
            $maxDebt = floatval(str_replace(['.', ','], '', trim($row[9] ?? '0')));
            $bankAccount = trim($row[10] ?? '');
            $bookerNameOrEmail = trim($row[11] ?? '');
            $rateCode = trim($row[12] ?? '');
            $branchNameOrCode = trim($row[13] ?? '');

            // Lookup customer source
            $sourceId = null;
            if (!empty($sourceNameOrCode)) {
                $src = $sources->first(fn($s) => strtolower($s->code) === strtolower($sourceNameOrCode) || strtolower($s->name) === strtolower($sourceNameOrCode));
                if ($src) $sourceId = $src->id;
            }

            // Lookup market
            $marketId = null;
            if (!empty($marketNameOrCode)) {
                $mkt = $markets->first(fn($m) => strtolower($m->code) === strtolower($marketNameOrCode) || strtolower($m->name) === strtolower($marketNameOrCode));
                if ($mkt) $marketId = $mkt->id;
            }

            // Lookup branch
            $branchId = null;
            if (!empty($branchNameOrCode)) {
                $br = $branches->first(fn($b) => strtolower($b->code) === strtolower($branchNameOrCode) || strtolower($b->name) === strtolower($branchNameOrCode));
                if ($br) $branchId = $br->id;
            }

            // Lookup booker
            $bookerId = null;
            if (!empty($bookerNameOrEmail)) {
                $bkr = $bookers->first(fn($b) => strtolower($b->name) === strtolower($bookerNameOrEmail) || strtolower($b->email) === strtolower($bookerNameOrEmail));
                if ($bkr) $bookerId = $bkr->id;
            }

            $companyData = [
                'name' => $name,
                'trading_name' => $tradingName ?: null,
                'address' => $address ?: null,
                'tax_code' => $taxCode ?: null,
                'phone' => $phone ?: null,
                'email' => $email ?: null,
                'customer_source_id' => $sourceId,
                'market_id' => $marketId,
                'max_debt' => $maxDebt,
                'bank_account' => $bankAccount ?: null,
                'booker_id' => $bookerId,
                'rate_code' => $rateCode ?: null,
                'branch_id' => $branchId,
                'is_active' => true,
            ];

            // If code is provided and exists, update. Otherwise try to find by name, or create new.
            $existingCompany = null;
            if (!empty($code)) {
                $existingCompany = Company::where('code', $code)->first();
            }
            if (!$existingCompany) {
                $existingCompany = Company::where('name', $name)->first();
            }

            if ($existingCompany) {
                $existingCompany->update($companyData);
            } else {
                if (!empty($code)) {
                    $companyData['code'] = $code;
                }
                Company::create($companyData);
            }

            $importedCount++;
        }

        fclose($handle);

        return response()->json([
            'success' => true,
            'message' => "Nhập excel thành công! Đã xử lý {$importedCount} dòng dữ liệu công ty."
        ]);
    }

    /**
     * Download CSV template for company import.
     */
    public function template(Request $request)
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="company_template.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function() {
            $file = fopen('php://output', 'w');
            
            // Add UTF-8 BOM for Vietnamese character support in Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // CSV Headers
            fputcsv($file, [
                'Mã',
                'Tên',
                'Tên giao dịch',
                'Địa chỉ',
                'Tax',
                'Số điện thoại',
                'Email',
                'Nguồn khách',
                'Thị trường',
                'Công nợ tối đa',
                'Tài khoản ngân hàng',
                'Người đặt phòng',
                'Mã giá phòng',
                'Chi nhánh'
            ]);

            // CSV Example Row
            fputcsv($file, [
                'CTY0001',
                'Công ty TNHH Du lịch Việt',
                'Viet Travel',
                '123 Nguyễn Huệ, Quận 1, TP. Hồ Chí Minh',
                '0102030405',
                '02839999999',
                'info@viettravel.com',
                'AGODA',
                'OTA',
                '50000000',
                '1234567890',
                'Nguyễn Văn A',
                'GP01',
                'HKT1'
            ]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
