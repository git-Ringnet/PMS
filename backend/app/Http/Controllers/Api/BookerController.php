<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookerResource;
use App\Models\Booker;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class BookerController extends Controller
{
    /**
     * Display a listing of bookers.
     */
    public function index()
    {
        $bookers = Booker::orderBy('id')->get();
        return response()->json([
            'success' => true,
            'data' => BookerResource::collection($bookers),
        ]);
    }

    /**
     * Store a newly created booker.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $booker = Booker::create($validated);

        return response()->json([
            'success' => true,
            'data' => new BookerResource($booker),
        ], 201);
    }

    /**
     * Display the specified booker.
     */
    public function show($id)
    {
        $booker = Booker::find($id);
        if (!$booker) {
            return response()->json(['message' => 'Booker not found'], 404);
        }
        return response()->json([
            'success' => true,
            'data' => new BookerResource($booker),
        ]);
    }

    /**
     * Update the specified booker.
     */
    public function update(Request $request, $id)
    {
        $booker = Booker::find($id);
        if (!$booker) {
            return response()->json(['message' => 'Booker not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $booker->update($validated);

        return response()->json([
            'success' => true,
            'data' => new BookerResource($booker),
        ]);
    }

    /**
     * Remove the specified booker.
     */
    public function destroy($id)
    {
        $booker = Booker::find($id);
        if (!$booker) {
            return response()->json(['message' => 'Booker not found'], 404);
        }

        $companyCount = DB::table('companies')->where('booker_id', $booker->id)->count();
        $bookingCount = DB::table('bookings')->where('booker_id', $booker->id)->count();
        if ($companyCount || $bookingCount) {
            return response()->json(['message' => "Không thể xóa người đặt phòng '{$booker->name}' vì đang được sử dụng trong {$companyCount} công ty và {$bookingCount} booking. Vui lòng gỡ liên kết trước."], 422);
        }

        $booker->delete();

        return response()->json([
            'success' => true,
            'message' => 'Booker deleted successfully',
        ]);
    }
}
