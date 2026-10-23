<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerSourceResource;
use App\Models\CustomerSource;
use App\Services\ChainCatalogSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class CustomerSourceController extends Controller
{
    /**
     * Display a listing of customer sources.
     */
    public function index()
    {
        $sources = CustomerSource::orderBy('id')->get();
        return response()->json([
            'success' => true,
            'data' => CustomerSourceResource::collection($sources),
        ]);
    }

    /**
     * Store a newly created customer source.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:customer_sources,code',
            'name' => 'required|string|max:255',
        ]);

        $source = CustomerSource::create($validated);
        $syncWarnings = app(ChainCatalogSyncService::class)->catalogCreated('customer_sources', $source->code, $source->name);

        return response()->json([
            'success' => true,
            'data' => new CustomerSourceResource($source),
            'sync_warnings' => $syncWarnings,
        ], 201);
    }

    /**
     * Display the specified customer source.
     */
    public function show($id)
    {
        $source = CustomerSource::find($id);
        if (!$source) {
            return response()->json(['message' => 'Customer source not found'], 404);
        }
        return response()->json([
            'success' => true,
            'data' => new CustomerSourceResource($source),
        ]);
    }

    /**
     * Update the specified customer source.
     */
    public function update(Request $request, $id)
    {
        $source = CustomerSource::find($id);
        if (!$source) {
            return response()->json(['message' => 'Customer source not found'], 404);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:customer_sources,code,' . $source->id,
            'name' => 'required|string|max:255',
        ]);

        $originalCode = $source->code;
        $source->update($validated);
        $syncWarnings = app(ChainCatalogSyncService::class)->catalogUpdated('customer_sources', $originalCode, $source->code, $source->name);

        return response()->json([
            'success' => true,
            'data' => new CustomerSourceResource($source),
            'sync_warnings' => $syncWarnings,
        ]);
    }

    /**
     * Remove the specified customer source.
     */
    public function destroy($id)
    {
        $source = CustomerSource::find($id);
        if (!$source) {
            return response()->json(['message' => 'Customer source not found'], 404);
        }

        $companyCount = DB::table('companies')->where('customer_source_id', $source->id)->count();
        $bookingCount = DB::table('bookings')->where('customer_source_id', $source->id)->count();
        if ($companyCount || $bookingCount) {
            return response()->json(['message' => "Không thể xóa nguồn khách '{$source->name}' vì đang được sử dụng trong {$companyCount} công ty và {$bookingCount} booking. Vui lòng gỡ liên kết trước."], 422);
        }

        $code = $source->code;
        $source->delete();
        $syncWarnings = app(ChainCatalogSyncService::class)->catalogDeleted('customer_sources', $code);

        return response()->json([
            'success' => true,
            'message' => 'Customer source deleted successfully',
            'sync_warnings' => $syncWarnings,
        ]);
    }
}
