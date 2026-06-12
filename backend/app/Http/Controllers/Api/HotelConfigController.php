<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\HotelConfigResource;
use App\Models\HotelConfig;
use Illuminate\Http\Request;

class HotelConfigController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $configs = HotelConfig::all();
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
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:hotel_configs,name',
            'value' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

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

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:hotel_configs,name,' . $config->id,
            'value' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

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
}
