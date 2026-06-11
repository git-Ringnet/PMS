<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MarketResource;
use App\Models\Market;
use Illuminate\Http\Request;

class MarketController extends Controller
{
    /**
     * Display a listing of markets.
     */
    public function index()
    {
        $markets = Market::orderBy('id')->get();
        return response()->json([
            'success' => true,
            'data' => MarketResource::collection($markets),
        ]);
    }

    /**
     * Store a newly created market.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:markets,code',
            'name' => 'required|string|max:255',
        ]);

        $market = Market::create($validated);

        return response()->json([
            'success' => true,
            'data' => new MarketResource($market),
        ], 201);
    }

    /**
     * Display the specified market.
     */
    public function show($id)
    {
        $market = Market::find($id);
        if (!$market) {
            return response()->json(['message' => 'Market not found'], 404);
        }
        return response()->json([
            'success' => true,
            'data' => new MarketResource($market),
        ]);
    }

    /**
     * Update the specified market.
     */
    public function update(Request $request, $id)
    {
        $market = Market::find($id);
        if (!$market) {
            return response()->json(['message' => 'Market not found'], 404);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:markets,code,' . $market->id,
            'name' => 'required|string|max:255',
        ]);

        $market->update($validated);

        return response()->json([
            'success' => true,
            'data' => new MarketResource($market),
        ]);
    }

    /**
     * Remove the specified market.
     */
    public function destroy($id)
    {
        $market = Market::find($id);
        if (!$market) {
            return response()->json(['message' => 'Market not found'], 404);
        }

        $market->delete();

        return response()->json([
            'success' => true,
            'message' => 'Market deleted successfully',
        ]);
    }
}
