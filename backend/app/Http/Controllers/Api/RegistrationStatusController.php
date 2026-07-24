<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RegistrationStatusResource;
use App\Models\RegistrationStatus;
use Illuminate\Http\Request;

class RegistrationStatusController extends Controller
{
    public function index(Request $request)
    {
        $query = RegistrationStatus::query();
        if ($request->has('is_availability')) {
            $query->where('is_availability', filter_var($request->is_availability, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->has('is_hidden')) {
            $query->where('is_hidden', filter_var($request->is_hidden, FILTER_VALIDATE_BOOLEAN));
        }
        $statuses = $query->get();
        return response()->json([
            'success' => true,
            'data' => RegistrationStatusResource::collection($statuses)
        ]);
    }

    public function store(Request $request)
    {
        $rawConfirmation = $request->input('confirmation_days');
        $rawCutoff = $request->input('cut_off_day');
        $effectiveCutoff = ($rawConfirmation !== null && $rawConfirmation !== '') 
            ? (int)$rawConfirmation 
            : (($rawCutoff !== null && $rawCutoff !== '') ? (int)$rawCutoff : 0);

        $validated = $request->validate([
            'id' => 'nullable|integer',
            'booking_status_id' => 'nullable|integer',
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:50',
            'cut_off_day' => 'nullable|integer|min:0',
            'confirmation_days' => 'nullable|integer|min:0',
            'description' => 'nullable|string|max:255',
            'status_value' => 'nullable|string|max:255',
            'is_hidden' => 'nullable|boolean',
            'is_availability' => 'nullable|boolean',
            'bk_definite' => 'nullable|integer',
            'vietnamese' => 'nullable|string|max:255',
            'english' => 'nullable|string|max:255',
            'order_index' => 'nullable|integer',
        ]);

        $validated['cut_off_day'] = $effectiveCutoff;
        unset($validated['confirmation_days']);

        $status = RegistrationStatus::create($validated);

        return response()->json([
            'success' => true,
            'data' => new RegistrationStatusResource($status)
        ], 201);
    }

    public function show($id)
    {
        $status = RegistrationStatus::find($id);
        if (!$status) {
            return response()->json(['message' => 'Registration status not found'], 404);
        }
        return response()->json([
            'success' => true,
            'data' => new RegistrationStatusResource($status)
        ]);
    }

    public function update(Request $request, $id)
    {
        $status = RegistrationStatus::find($id);
        if (!$status) {
            return response()->json(['message' => 'Registration status not found'], 404);
        }

        $rawConfirmation = $request->input('confirmation_days');
        $rawCutoff = $request->input('cut_off_day');
        $effectiveCutoff = ($rawConfirmation !== null && $rawConfirmation !== '') 
            ? (int)$rawConfirmation 
            : (($rawCutoff !== null && $rawCutoff !== '') ? (int)$rawCutoff : $status->cut_off_day);

        $validated = $request->validate([
            'booking_status_id' => 'nullable|integer',
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:50',
            'cut_off_day' => 'nullable|integer|min:0',
            'confirmation_days' => 'nullable|integer|min:0',
            'description' => 'nullable|string|max:255',
            'status_value' => 'nullable|string|max:255',
            'is_hidden' => 'nullable|boolean',
            'is_availability' => 'nullable|boolean',
            'bk_definite' => 'nullable|integer',
            'vietnamese' => 'nullable|string|max:255',
            'english' => 'nullable|string|max:255',
            'order_index' => 'nullable|integer',
        ]);

        $validated['cut_off_day'] = $effectiveCutoff;
        unset($validated['confirmation_days']);

        $status->update($validated);

        return response()->json([
            'success' => true,
            'data' => new RegistrationStatusResource($status)
        ]);
    }

    public function destroy($id)
    {
        $status = RegistrationStatus::find($id);
        if (!$status) {
            return response()->json(['message' => 'Registration status not found'], 404);
        }

        $status->delete();

        return response()->json([
            'success' => true,
            'message' => 'Registration status deleted successfully'
        ]);
    }
}
