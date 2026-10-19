<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ResidenceDeclaration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResidenceDeclarationController extends Controller
{
    /**
     * Danh sách khách đã khai báo lưu trú
     * GET /api/residence-declarations?booking_room_ids[]=...
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'booking_room_ids' => 'nullable|array',
            'booking_room_ids.*' => 'string|max:50',
        ]);

        $query = ResidenceDeclaration::query()
            ->select(['guest_id', 'guest_kind', 'booking_id', 'booking_room_id', 'declare_date', 'declarer', 'status']);

        if (!empty($validated['booking_room_ids'])) {
            $query->whereIn('booking_room_id', $validated['booking_room_ids']);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    /**
     * Đánh dấu danh sách khách đã khai báo lưu trú (sau khi xuất Excel mẫu Khánh Hòa)
     * POST /api/residence-declarations
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'guests' => 'required|array|min:1',
            'guests.*.guest_id' => 'required|string|max:50',
            'guests.*.guest_kind' => 'required|in:guest,child',
            'guests.*.booking_id' => 'nullable|integer',
            'guests.*.booking_room_id' => 'required|string|max:50',
        ]);

        $user = $request->user();
        $declarer = mb_substr((string) ($user?->username ?: $user?->name ?: 'system'), 0, 50);
        $now = now();

        $rows = collect($validated['guests'])
            ->unique(fn ($g) => $g['guest_kind'] . '|' . $g['guest_id'] . '|' . $g['booking_room_id'])
            ->map(fn ($g) => [
                'guest_id' => $g['guest_id'],
                'guest_kind' => $g['guest_kind'],
                'booking_id' => $g['booking_id'] ?? null,
                'booking_room_id' => $g['booking_room_id'],
                'declare_date' => $now,
                'declarer' => $declarer,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        DB::transaction(function () use ($rows) {
            ResidenceDeclaration::upsert(
                $rows,
                ['guest_kind', 'guest_id', 'booking_room_id'],
                ['booking_id', 'declare_date', 'declarer', 'status', 'updated_at']
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Đã chuyển ' . count($rows) . ' khách sang danh sách đã khai báo lưu trú.',
            'data' => ['count' => count($rows)],
        ]);
    }
}
