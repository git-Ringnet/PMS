<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FbTable;
use App\Models\FbLocation;
use Illuminate\Http\Request;

class FbTableController extends Controller
{
    public function index(Request $request)
    {
        $query = FbTable::with(['orders' => function($q) {
            $q->whereIn('status', ['serving', 'waiting']);
        }]);

        if ($request->has('location_id')) {
            $query->where('location_id', $request->query('location_id'));
        } elseif ($request->has('outlet_code')) {
            $locationIds = FbLocation::where('outlet_code', $request->query('outlet_code'))->pluck('id');
            $query->whereIn('location_id', $locationIds);
        }

        $tables = $query->orderBy('row_index')->orderBy('col_index')->get();

        // Transform tables to include aggregated order info
        $tables->transform(function ($table) {
            $guestCount = 0;
            $totalAmount = 0;
            $checkinTime = null;

            foreach ($table->orders as $order) {
                $guestCount += $order->guest_count;
                $totalAmount += $order->total_amount;
                if (!$checkinTime || $order->created_at < $checkinTime) {
                    $checkinTime = $order->created_at;
                }
            }

            $table->guest_name = $guestCount; // Using guest_name field for guest count in frontend
            $table->checkin_time = $checkinTime ? $checkinTime->format('H:i') : null;
            $table->total_amount = $totalAmount;
            
            unset($table->orders); // Remove the orders relation to avoid heavy payload
            return $table;
        });

        return response()->json([
            'success' => true,
            'data' => $tables
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'location_id' => 'required|exists:fb_locations,id',
            'row_index' => 'integer',
            'col_index' => 'integer',
            'max_seats' => 'integer',
            'status' => 'string|max:10',
            'image' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        // Auto-generate table_code if not provided
        if (empty($validated['table_code'])) {
            $validated['table_code'] = 'TB' . rand(1000, 9999);
        }

        // Auto-assign col_index if empty or 0
        if (empty($validated['col_index']) || $validated['col_index'] == 0) {
            $rowIndex = $validated['row_index'] ?? 1;
            $locationId = $validated['location_id'];
            $maxCol = FbTable::where('location_id', $locationId)
                ->where('row_index', $rowIndex)
                ->max('col_index') ?: 0;
            $validated['col_index'] = $maxCol + 1;
        }

        $table = FbTable::create($validated);

        \App\Services\ActivityLogService::logCreate(
            $request,
            $table,
            'fnb',
            'FbTableController',
            "Tạo bàn: {$table->name}",
            $table->table_code
        );

        return response()->json([
            'success' => true,
            'data' => $table
        ], 201);
    }

    public function show($id)
    {
        $table = FbTable::findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $table
        ]);
    }

    public function update(Request $request, $id)
    {
        $table = FbTable::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'location_id' => 'required|exists:fb_locations,id',
            'row_index' => 'integer',
            'col_index' => 'integer',
            'max_seats' => 'integer',
            'status' => 'string|max:10',
            'image' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        $table->update($validated);

        \App\Services\ActivityLogService::logUpdate(
            $request,
            $table,
            [],
            'fnb',
            'FbTableController',
            "Cập nhật bàn: {$table->name}",
            $table->table_code
        );

        return response()->json([
            'success' => true,
            'data' => $table
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $table = FbTable::findOrFail($id);
        
        $reason = $request->input('reason', 'Không có lý do');
        \App\Services\ActivityLogService::logDelete(
            $request,
            $table,
            'fnb',
            'FbTableController',
            "Xoá bàn: {$table->name} - Lý do: {$reason}",
            $table->table_code
        );

        $table->delete();

        return response()->json([
            'success' => true,
            'message' => 'Deleted successfully'
        ]);
    }

    public function bulkCreate(Request $request)
    {
        $validated = $request->validate([
            'location_id' => 'required|exists:fb_locations,id',
            'prefix_code' => 'required|string|max:10',
            'total_tables' => 'required|integer|min:0|max:100',
            'row_index' => 'required|integer|min:0',
        ]);

        $locationId = $validated['location_id'];
        $prefix = $validated['prefix_code'];
        $total = $validated['total_tables'];
        $rowIndex = $validated['row_index'];

        // Count existing tables in this location for incremental name index
        $existingCount = FbTable::where('location_id', $locationId)->count();
        
        // Get max col_index in this specific row to determine column starting position
        $existingMaxCol = FbTable::where('location_id', $locationId)
            ->where('row_index', $rowIndex)
            ->max('col_index') ?: 0;
        
        $createdTables = [];

        for ($i = 1; $i <= $total; $i++) {
            $tableNum = $existingCount + $i;
            $name = $prefix . $tableNum;
            $currentCol = $existingMaxCol + $i;

            $table = FbTable::create([
                'table_code' => $prefix . str_pad($tableNum, 3, '0', STR_PAD_LEFT),
                'name' => $name,
                'location_id' => $locationId,
                'row_index' => $rowIndex,
                'col_index' => $currentCol,
                'max_seats' => 4,
                'status' => 'Active',
                'is_active' => true,
            ]);

            $createdTables[] = $table;
        }

        \App\Services\ActivityLogService::logCreate(
            $request,
            null,
            'fnb',
            'FbTableController',
            "Tạo hàng loạt {$total} bàn ở khu vực ID {$locationId}"
        );

        return response()->json([
            'success' => true,
            'message' => 'Bulk tables created successfully',
            'data' => $createdTables
        ], 201);
    }

    public function deleteRow(Request $request)
    {
        $validated = $request->validate([
            'location_id' => 'required|exists:fb_locations,id',
            'row_index' => 'required|integer',
        ]);

        $reason = $request->input('reason', 'Không có lý do');
        \App\Services\ActivityLogService::logDelete(
            $request,
            null,
            'fnb',
            'FbTableController',
            "Xoá hàng {$validated['row_index']} ở khu vực ID {$validated['location_id']} - Lý do: {$reason}"
        );

        FbTable::where('location_id', $validated['location_id'])
            ->where('row_index', $validated['row_index'])
            ->delete();

        // Optional: Re-align row indexes for remaining tables in this location
        $remainingRows = FbTable::where('location_id', $validated['location_id'])
            ->orderBy('row_index')
            ->get()
            ->groupBy('row_index');

        $newRowIdx = 1;
        foreach ($remainingRows as $oldRowIdx => $tables) {
            foreach ($tables as $table) {
                $table->update(['row_index' => $newRowIdx]);
            }
            $newRowIdx++;
        }

        return response()->json([
            'success' => true,
            'message' => 'Row deleted and remaining layout adjusted successfully'
        ]);
    }
}
