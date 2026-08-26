<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerSourceResource;
use App\Models\CustomerSource;
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

        return response()->json([
            'success' => true,
            'data' => new CustomerSourceResource($source),
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

        $source->update($validated);

        return response()->json([
            'success' => true,
            'data' => new CustomerSourceResource($source),
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

        $source->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer source deleted successfully',
        ]);
    }
}
