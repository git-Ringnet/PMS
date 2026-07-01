<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FbLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FbLocationController extends Controller
{
    public function index(Request $request)
    {
        $query = FbLocation::query();

        if ($request->has('outlet_code')) {
            $query->where('outlet_code', $request->query('outlet_code'));
        }

        $locations = $query->get();

        return response()->json([
            'success' => true,
            'data' => $locations
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'note' => 'nullable|string|max:100',
            'outlet_code' => 'required|exists:outlets,code',
            'color' => 'nullable|string|max:50',
            'letter' => 'nullable|string|max:10',
            'is_active' => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('locations', 'public');
            $validated['image'] = $path;
        }

        // Generate unique 10-char ID matching legacy char(10) type
        do {
            $id = 'L' . rand(100000000, 999999999);
        } while (FbLocation::where('id', $id)->exists());

        $validated['id'] = $id;

        $location = FbLocation::create($validated);

        return response()->json([
            'success' => true,
            'data' => $location
        ], 201);
    }

    public function show($id)
    {
        $location = FbLocation::findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $location
        ]);
    }

    public function update(Request $request, $id)
    {
        $location = FbLocation::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'note' => 'nullable|string|max:100',
            'outlet_code' => 'required|exists:outlets,code',
            'color' => 'nullable|string|max:50',
            'letter' => 'nullable|string|max:10',
            'is_active' => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            if ($location->image) {
                Storage::disk('public')->delete($location->image);
            }
            $path = $request->file('image')->store('locations', 'public');
            $validated['image'] = $path;
        }

        $location->update($validated);

        return response()->json([
            'success' => true,
            'data' => $location
        ]);
    }

    public function destroy($id)
    {
        $location = FbLocation::findOrFail($id);

        if ($location->image) {
            Storage::disk('public')->delete($location->image);
        }

        $location->delete();

        return response()->json([
            'success' => true,
            'message' => 'Deleted successfully'
        ]);
    }
}
