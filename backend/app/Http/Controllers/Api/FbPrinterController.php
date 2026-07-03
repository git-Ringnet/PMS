<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FbPrinter;
use Illuminate\Http\Request;

class FbPrinterController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = FbPrinter::with('outlet');

        if ($request->has('outlet_id') && $request->outlet_id) {
            $query->where('outlet_id', $request->outlet_id);
        }

        $printers = $query->orderBy('outlet_id')->orderBy('id')->get();

        return response()->json($printers);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'outlet_id' => 'required|exists:outlets,id',
            'name' => 'required|string|max:255',
            'type' => 'required|integer',
            'num_of_prints' => 'required|integer|min:1',
            'driver_name' => 'nullable|string|max:255',
        ]);

        $printer = FbPrinter::create($validated);

        return response()->json($printer->load('outlet'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $printer = FbPrinter::with('outlet')->findOrFail($id);
        return response()->json($printer);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $printer = FbPrinter::findOrFail($id);

        $validated = $request->validate([
            'outlet_id' => 'required|exists:outlets,id',
            'name' => 'required|string|max:255',
            'type' => 'required|integer',
            'num_of_prints' => 'required|integer|min:1',
            'driver_name' => 'nullable|string|max:255',
        ]);

        $printer->update($validated);

        return response()->json($printer->load('outlet'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $printer = FbPrinter::findOrFail($id);
        $printer->delete();

        return response()->json(['message' => 'Xóa máy in thành công!']);
    }
}
