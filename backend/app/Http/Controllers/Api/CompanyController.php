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
}
