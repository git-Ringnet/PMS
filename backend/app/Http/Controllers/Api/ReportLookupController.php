<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportLookupController extends Controller
{
    public function index(Request $request, string $lookup)
    {
        $search = trim((string) $request->query('search', ''));
        abort_if(mb_strlen($search) > 100, 422, 'Từ khóa tìm kiếm quá dài.');

        $options = match ($lookup) {
            'areas' => $this->areas(),
            'companies' => $this->companies($search),
            'bookings' => $this->bookings($search),
            'room-classes' => $this->roomClasses(),
            'registration-statuses' => $this->registrationStatuses(),
            'users' => $this->users($search),
            'hotel-services' => $this->hotelServices($search),
            default => abort(404, 'Danh mục tham số báo cáo không tồn tại.'),
        };

        return response()->json(['success' => true, 'data' => $options]);
    }

    private function areas(): array
    {
        return DB::table('rooms')
            ->whereNotNull('area')
            ->where('area', '<>', '')
            ->distinct()
            ->orderBy('area')
            ->pluck('area')
            ->map(fn ($area) => ['value' => $area, 'label' => $area])
            ->values()
            ->all();
    }

    private function companies(string $search): array
    {
        return DB::table('companies')
            ->where('is_active', true)
            ->when($search !== '', fn ($query) => $query->where(function ($nested) use ($search) {
                $nested->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'code', 'name'])
            ->map(fn ($company) => [
                'value' => $company->id,
                'label' => trim("{$company->code} - {$company->name}", ' -'),
            ])->all();
    }

    private function bookings(string $search): array
    {
        return DB::table('bookings')
            ->whereNull('deleted_at')
            ->whereIn('status', [0, 1, 2])
            ->when($search !== '', fn ($query) => $query->where(function ($nested) use ($search) {
                $nested->where('id', 'like', "%{$search}%")
                    ->orWhere('booking_name', 'like', "%{$search}%")
                    ->orWhere('external_booking_code', 'like', "%{$search}%");
            }))
            ->orderByDesc('arrival_date')
            ->limit(200)
            ->get(['id', 'booking_name', 'arrival_date'])
            ->map(fn ($booking) => [
                'value' => $booking->id,
                'label' => "{$booking->id} - {$booking->booking_name} ({$booking->arrival_date})",
            ])->all();
    }

    private function roomClasses(): array
    {
        return DB::table('room_classes')
            ->where('is_active', true)
            ->orderBy('orders')
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn ($roomClass) => [
                'value' => $roomClass->id,
                'label' => trim("{$roomClass->code} - {$roomClass->name}", ' -'),
            ])->all();
    }

    private function registrationStatuses(): array
    {
        return DB::table('registration_statuses')
            ->where('is_hidden', false)
            ->orderBy('order_index')
            ->orderBy('name')
            ->get(['id', 'name', 'vietnamese'])
            ->map(fn ($status) => [
                'value' => $status->id,
                'label' => $status->vietnamese ?: $status->name,
            ])->all();
    }

    private function users(string $search): array
    {
        return User::query()
            ->where('is_active_user', true)
            ->when($search !== '', fn ($query) => $query->where(function ($nested) use ($search) {
                $nested->where('username', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%");
            }))
            ->orderBy('username')
            ->limit(200)
            ->get(['username', 'name'])
            ->map(fn ($user) => [
                'value' => $user->username,
                'label' => trim("{$user->username} - {$user->name}", ' -'),
            ])->all();
    }

    private function hotelServices(string $search): array
    {
        return DB::table('hotel_services')
            ->where('is_active', true)
            ->where('code', '<>', 'RM')
            ->when($search !== '', fn ($query) => $query->where(function ($nested) use ($search) {
                $nested->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->limit(500)
            ->get(['code', 'name'])
            ->map(fn ($service) => [
                'value' => $service->code,
                'label' => trim("{$service->code} - {$service->name}", ' -'),
            ])->all();
    }
}
