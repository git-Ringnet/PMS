<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\HotelConfigResource;
use App\Models\Company;
use App\Models\HotelConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HotelConfigController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = HotelConfig::query();

        // Filter theo name nếu có (hỗ trợ ?name=BreakfastRateChild)
        if ($request->filled('name')) {
            $query->where('name', $request->name);
        }

        $configs = $query->get();
        return response()->json([
            'success' => true,
            'data' => HotelConfigResource::collection($configs)
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $config = HotelConfig::create($validated);

        return response()->json([
            'success' => true,
            'data' => new HotelConfigResource($config)
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $config = HotelConfig::find($id);
        if (!$config) {
            return response()->json(['message' => 'Config not found'], 404);
        }
        return response()->json([
            'success' => true,
            'data' => new HotelConfigResource($config)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $config = HotelConfig::find($id);
        if (!$config) {
            return response()->json(['message' => 'Config not found'], 404);
        }

        $validated = $this->validatePayload($request, $config);

        $config->update($validated);

        return response()->json([
            'success' => true,
            'data' => new HotelConfigResource($config)
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $config = HotelConfig::find($id);
        if (!$config) {
            return response()->json(['message' => 'Config not found'], 404);
        }

        $config->delete();

        return response()->json([
            'success' => true,
            'message' => 'Config deleted successfully'
        ]);
    }

    /**
     * Validate generic hotel configs and the verified runtime contract of CompanyDefault.
     */
    private function validatePayload(Request $request, ?HotelConfig $config = null): array
    {
        $nameRule = 'required|string|max:255|unique:hotel_configs,name';
        if ($config) {
            $nameRule .= ',' . $config->id;
        }

        $validator = Validator::make($request->all(), [
            'name' => $nameRule,
            'value' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $validator->after(function ($validator) use ($request): void {
            if ($request->input('name') !== 'CompanyDefault') {
                return;
            }

            $companyId = trim((string) $request->input('value', ''));
            if ($companyId === '' || !ctype_digit($companyId) || (int) $companyId < 1) {
                $validator->errors()->add('value', 'CompanyDefault phải là ID công ty hợp lệ.');
                return;
            }

            $isActiveCompany = Company::query()
                ->whereKey((int) $companyId)
                ->where('is_active', true)
                ->exists();

            if (!$isActiveCompany) {
                $validator->errors()->add('value', 'CompanyDefault phải tham chiếu đến công ty đang hoạt động.');
            }
        });

        return $validator->validate();
    }
}
