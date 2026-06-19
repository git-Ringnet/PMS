<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LostAndFoundItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LostAndFoundController extends Controller
{
    public function index(Request $request)
    {
        $query = LostAndFoundItem::query();

        // Optional basic search/filter logic if needed (frontend currently handles filtering, but good to have)
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('item_found', 'like', "%{$search}%")
                  ->orWhere('where_found', 'like', "%{$search}%")
                  ->orWhere('who_found', 'like', "%{$search}%");
        }

        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_found' => 'required|string|max:200',
            'time_found' => 'required',
            'date_found' => 'required|date',
            'where_found' => 'required|string|max:200',
            'who_found' => 'required|string|max:200',
            'received' => 'required|string|max:200',
            'log_no' => 'nullable|integer',
            'date_handling' => 'nullable|date',
            'time_handling' => 'nullable',
            'method_handling' => 'nullable|string|max:200',
            'delieved_handling' => 'nullable|string|max:200',
            'received_handling' => 'nullable|string|max:200',
            'remarks' => 'nullable|string|max:500',
            'status' => 'nullable|boolean',
            'image' => 'nullable|string|max:200', // Could handle file upload logic here if needed
        ]);

        $item = LostAndFoundItem::create($validated);
        return response()->json($item, 201);
    }

    public function update(Request $request, $id)
    {
        $item = LostAndFoundItem::findOrFail($id);

        $validated = $request->validate([
            'item_found' => 'required|string|max:200',
            'time_found' => 'required',
            'date_found' => 'required|date',
            'where_found' => 'required|string|max:200',
            'who_found' => 'required|string|max:200',
            'received' => 'required|string|max:200',
            'log_no' => 'nullable|integer',
            'date_handling' => 'nullable|date',
            'time_handling' => 'nullable',
            'method_handling' => 'nullable|string|max:200',
            'delieved_handling' => 'nullable|string|max:200',
            'received_handling' => 'nullable|string|max:200',
            'remarks' => 'nullable|string|max:500',
            'status' => 'nullable|boolean',
            'image' => 'nullable|string|max:200',
        ]);

        $item->update($validated);
        return response()->json($item);
    }

    public function destroy($id)
    {
        $item = LostAndFoundItem::findOrFail($id);
        $item->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

}
