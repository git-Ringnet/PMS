<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoomStatusChangeLogService
{
    public function log(
        string $roomNumber,
        ?string $oldStatusCode,
        ?string $newStatusCode,
        ?Request $request = null
    ): void {
        if ($oldStatusCode === $newStatusCode) {
            return;
        }

        $statusIds = DB::table('room_statuses')
            ->whereIn('code', array_values(array_filter([$oldStatusCode, $newStatusCode])))
            ->pluck('id', 'code');
        $user = $request?->user() ?? auth()->user();
        $systemDate = DB::table('system_date_rolls')->latest('id')->value('system_date');

        DB::table('room_status_change_logs')->insert([
            'legacy_id' => null,
            'changed_at' => now(),
            'business_date' => $systemDate ? Carbon::parse($systemDate)->toDateString() : now()->toDateString(),
            'username' => $user?->username ?? $user?->name ?? 'system',
            'room' => $roomNumber,
            'status_from_id' => $oldStatusCode ? $statusIds->get($oldStatusCode) : null,
            'status_to_id' => $newStatusCode ? $statusIds->get($newStatusCode) : null,
            'legacy_status_from_code' => null,
            'legacy_status_to_code' => null,
            'source' => 'runtime',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
