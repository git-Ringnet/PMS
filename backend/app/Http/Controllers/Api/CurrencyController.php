<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CurrencyResource;
use App\Models\Currency;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    public function index()
    {
        $currencies = Currency::all();
        return response()->json([
            'success' => true,
            'data' => CurrencyResource::collection($currencies)
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:currencies,code',
            'name' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:255',
            'decimals_to_round' => 'nullable|integer',
            'is_main' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'exchange_rate' => 'nullable|numeric|min:0',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('uploads/currencies'), $filename);
            $validated['image_path'] = 'uploads/currencies/' . $filename;
        }

        $currency = Currency::create($validated);

        return response()->json([
            'success' => true,
            'data' => new CurrencyResource($currency)
        ], 201);
    }

    public function show($id)
    {
        $currency = Currency::find($id);
        if (!$currency) {
            return response()->json(['message' => 'Currency not found'], 404);
        }
        return response()->json([
            'success' => true,
            'data' => new CurrencyResource($currency)
        ]);
    }

    public function update(Request $request, $id)
    {
        $currency = Currency::find($id);
        if (!$currency) {
            return response()->json(['message' => 'Currency not found'], 404);
        }

        // To support PUT with files, standard Laravel requires parsing multipart form data,
        // but typically clients send POST with _method=PUT. We can validate and handle both.
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:currencies,code,' . $currency->id,
            'name' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:255',
            'decimals_to_round' => 'nullable|integer',
            'is_main' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'exchange_rate' => 'nullable|numeric|min:0',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            if ($currency->image_path && file_exists(public_path($currency->image_path))) {
                @unlink(public_path($currency->image_path));
            }
            $file = $request->file('image');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('uploads/currencies'), $filename);
            $validated['image_path'] = 'uploads/currencies/' . $filename;
        }

        if ($request->input('remove_image') === 'true' || $request->input('remove_image') === 1) {
            if ($currency->image_path && file_exists(public_path($currency->image_path))) {
                @unlink(public_path($currency->image_path));
            }
            $validated['image_path'] = null;
        }

        $currency->update($validated);

        return response()->json([
            'success' => true,
            'data' => new CurrencyResource($currency)
        ]);
    }

    public function destroy($id)
    {
        $currency = Currency::find($id);
        if (!$currency) {
            return response()->json(['message' => 'Currency not found'], 404);
        }

        if ($currency->image_path && file_exists(public_path($currency->image_path))) {
            @unlink(public_path($currency->image_path));
        }

        $currency->delete();

        return response()->json([
            'success' => true,
            'message' => 'Currency deleted successfully'
        ]);
    }
}
