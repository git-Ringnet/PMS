<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentMethodResource;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function index()
    {
        $methods = PaymentMethod::all();
        return response()->json([
            'success' => true,
            'data' => PaymentMethodResource::collection($methods)
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:payment_methods,code',
            'name' => 'required|string|max:255',
            'account' => 'nullable|string|max:255',
            'account_name' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'service_charge' => 'nullable|numeric|min:0',
            'department' => 'nullable|string|max:255',
            'payment_group' => 'nullable|integer|in:1,2,3,4,5',
            'is_free' => 'nullable|boolean',
            'is_inactive' => 'nullable|boolean',
        ]);

        $method = PaymentMethod::create($validated);

        return response()->json([
            'success' => true,
            'data' => new PaymentMethodResource($method)
        ], 201);
    }

    public function show($id)
    {
        $method = PaymentMethod::find($id);
        if (!$method) {
            return response()->json(['message' => 'Payment method not found'], 404);
        }
        return response()->json([
            'success' => true,
            'data' => new PaymentMethodResource($method)
        ]);
    }

    public function update(Request $request, $id)
    {
        $method = PaymentMethod::find($id);
        if (!$method) {
            return response()->json(['message' => 'Payment method not found'], 404);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:payment_methods,code,' . $method->id,
            'name' => 'required|string|max:255',
            'account' => 'nullable|string|max:255',
            'account_name' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'service_charge' => 'nullable|numeric|min:0',
            'department' => 'nullable|string|max:255',
            'payment_group' => 'nullable|integer|in:1,2,3,4,5',
            'is_free' => 'nullable|boolean',
            'is_inactive' => 'nullable|boolean',
        ]);

        $method->update($validated);

        return response()->json([
            'success' => true,
            'data' => new PaymentMethodResource($method)
        ]);
    }

    public function destroy($id)
    {
        $method = PaymentMethod::find($id);
        if (!$method) {
            return response()->json(['message' => 'Payment method not found'], 404);
        }

        $method->delete();

        return response()->json([
            'success' => true,
            'message' => 'Payment method deleted successfully'
        ]);
    }
}
