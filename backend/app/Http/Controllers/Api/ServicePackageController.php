<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServicePackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ServicePackageController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => ServicePackage::with('items.hotelService.department')->orderBy('code')->get(),
        ]);
    }

    public function show(ServicePackage $servicePackage)
    {
        return response()->json([
            'data' => $servicePackage->load('items.hotelService.department'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $servicePackage = DB::transaction(function () use ($validated) {
            $items = $validated['items'];
            unset($validated['items']);
            $package = ServicePackage::create($validated);
            $this->syncItems($package, $items);

            return $package->load('items.hotelService.department');
        });

        return response()->json(['data' => $servicePackage], 201);
    }

    public function update(Request $request, ServicePackage $servicePackage)
    {
        $validated = $this->validatePayload($request, $servicePackage);

        $servicePackage = DB::transaction(function () use ($validated, $servicePackage) {
            $items = $validated['items'];
            unset($validated['items']);
            $servicePackage->update($validated);
            $this->syncItems($servicePackage, $items);

            return $servicePackage->fresh()->load('items.hotelService.department');
        });

        return response()->json(['data' => $servicePackage]);
    }

    public function destroy(ServicePackage $servicePackage)
    {
        if ($servicePackage->bookings()->exists()) {
            throw ValidationException::withMessages([
                'service_package' => 'Gói dịch vụ đã được áp dụng cho đăng ký, không thể xóa.',
            ]);
        }

        $servicePackage->delete();

        return response()->json(['message' => 'Đã xóa gói dịch vụ.']);
    }

    private function validatePayload(Request $request, ?ServicePackage $servicePackage = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('service_packages', 'code')->ignore($servicePackage?->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'max:10'],
            'nights' => ['required', 'integer', 'min:1', 'max:365'],
            'display_price' => ['required', 'numeric', 'min:0'],
            'begin_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:begin_date'],
            'days_of_week' => ['nullable', 'array'],
            'days_of_week.*' => ['integer', 'between:0,6', 'distinct'],
            'is_inactive' => ['nullable', 'boolean'],
            'items' => ['present', 'array', 'min:1'],
            'items.*.service_code' => ['required', 'string', 'max:50', 'exists:hotel_services,code'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'items.*.service_charge' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.special_tax' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.order_day' => ['required', 'integer', 'min:1', 'max:365'],
            'items.*.outlet' => ['nullable', 'string', 'max:250'],
            'items.*.note' => ['nullable', 'string', 'max:200'],
        ]);
    }

    private function syncItems(ServicePackage $servicePackage, array $items): void
    {
        $servicePackage->items()->delete();

        foreach (array_values($items) as $index => $item) {
            $servicePackage->items()->create([
                ...$item,
                'detail_no' => $index + 1,
            ]);
        }
    }
}
