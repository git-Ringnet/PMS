<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LostAndFoundItem;
use App\Models\SystemDateRoll;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LostAndFoundController extends Controller
{
    public function index(Request $request)
    {
        $query = LostAndFoundItem::query();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('from')) {
            $query->whereDate('date_reported', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('date_reported', '<=', $request->input('to'));
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('item_found', 'like', "%{$search}%")
                    ->orWhere('where_found', 'like', "%{$search}%")
                    ->orWhere('who_found', 'like', "%{$search}%")
                    ->orWhere('guest_info', 'like', "%{$search}%");
            });
        }

        return response()->json($query->orderByDesc('created_at')->get());
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);
        $validated['log_no'] = $validated['log_no'] ?? ((int) LostAndFoundItem::max('log_no')) + 1;
        $validated['date_reported'] = $validated['date_reported']
            ?? SystemDateRoll::latest('id')->value('system_date')
            ?? now()->toDateString();
        $validated['status'] = $validated['status'] ?? 'lost';

        return response()->json(LostAndFoundItem::create($validated), 201);
    }

    public function show($id)
    {
        return response()->json(LostAndFoundItem::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $item = LostAndFoundItem::findOrFail($id);
        $item->update($this->validatePayload($request));

        return response()->json($item->fresh());
    }

    public function destroy($id)
    {
        LostAndFoundItem::findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }

    private function validatePayload(Request $request): array
    {
        $data = $request->all();
        foreach (['method_handling', 'delieved_handling', 'received_handling', 'remarks', 'image', 'log_no', 'guest_info', 'storage_location', 'date_reported'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        return Validator::make($data, [
            'item_found' => 'required|string|max:200',
            'date_found' => 'nullable|date',
            'where_found' => 'nullable|string|max:200',
            'who_found' => 'nullable|string|max:200',
            'received' => 'nullable|string|max:200',
            'log_no' => 'nullable|integer',
            'guest_info' => 'nullable|string|max:200',
            'storage_location' => 'nullable|string|max:200',
            'date_reported' => 'nullable|date',
            'date_handling' => 'nullable|date',
            'method_handling' => 'nullable|string|max:200',
            'delieved_handling' => 'nullable|string|max:200',
            'received_handling' => 'nullable|string|max:200',
            'remarks' => 'nullable|string|max:500',
            'status' => 'nullable|in:lost,found',
            'image' => 'nullable|array',
            'image.*' => 'string',
        ])->validate();
    }
}
