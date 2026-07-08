<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingRoom;
use App\Models\BookingRoomService;
use App\Models\HotelService;
use App\Models\HotelSetting;
use App\Services\RoomAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * BookingRoomServiceController
 * Quản lý dịch vụ bổ sung set-up trước (Epic 4) và phụ thu Extra Bed (Epic 14)
 */
class BookingRoomServiceController extends Controller
{
    public function __construct(protected RoomAvailabilityService $avService) {}

    // =========================================
    // GET: Danh sách dịch vụ của 1 phòng
    // GET /booking-rooms/{roomId}/services
    // =========================================
    public function index($roomId)
    {
        $room     = BookingRoom::findOrFail($roomId);
        $services = $room->services()
            ->where('service_date', '>=', $this->avService->getSystemDate()->toDateString())
            ->orderBy('service_date')
            ->get();

        return response()->json(['success' => true, 'data' => $services]);
    }

    // =========================================
    // GET: Danh sách dịch vụ FO khả dụng (dùng để populate dropdown chọn dịch vụ)
    // GET /booking-room-services/fo-list
    // =========================================
    public function foServiceList()
    {
        $services = HotelService::where('department', 'FO')
            ->orderBy('name')
            ->get(['code', 'name', 'price', 'unit', 'short_name']);

        return response()->json(['success' => true, 'data' => $services]);
    }

    // =========================================
    // POST: Thêm/Cập nhật dịch vụ cho phòng (Epic 4)
    // POST /booking-rooms/{roomId}/services
    // =========================================
    public function store(Request $request, $roomId)
    {
        $room = BookingRoom::findOrFail($roomId);

        if (!in_array($room->status, [BookingRoom::STATUS_BOOKED, BookingRoom::STATUS_CHECKED_IN])) {
            return response()->json(['success' => false, 'message' => 'Phòng không ở trạng thái hợp lệ để thêm dịch vụ.'], 422);
        }

        $request->validate([
            'service_code'  => 'required|string|max:30',
            'service_name'  => 'nullable|string|max:100',
            'service_date'  => 'required|date',
            'quantity'      => 'nullable|numeric|min:0.01',
            'rate'          => 'nullable|numeric|min:0',
            'is_room'       => 'nullable|boolean',
        ]);

        // Kiểm tra service_code phải thuộc department FO
        $foService = HotelService::where('code', $request->service_code)
            ->where('department', 'FO')
            ->first();

        if (!$foService) {
            // Kiểm tra xem service code có tồn tại không (tồn tại nhưng sai department)
            $anyService = HotelService::where('code', $request->service_code)->exists();
            return response()->json([
                'success' => false,
                'message' => $anyService
                    ? 'Dịch vụ "' . $request->service_code . '" không thuộc bộ phận Front Office (FO). Chỉ cho phép thêm dịch vụ FO tại màn hình này.'
                    : 'Mã dịch vụ "' . $request->service_code . '" không tồn tại trong hệ thống.',
            ], 422);
        }

        $systemDate = $this->avService->getSystemDate()->toDateString();

        // Không cho thêm dịch vụ ở ngày quá khứ
        if ($request->service_date < $systemDate) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể thêm dịch vụ cho ngày đã qua (' . $request->service_date . ').',
            ], 422);
        }

        // Nếu trùng ngày + service_code → update giá, không cộng dồn
        $service = BookingRoomService::withTrashed()->updateOrCreate(
            [
                'booking_room_id' => $roomId,
                'service_code'    => $request->service_code,
                'service_date'    => $request->service_date,
            ],
            [
                'service_name' => $request->service_name,
                'quantity'     => $request->quantity ?? 1,
                'rate'         => $request->rate ?? 0,
                'is_room'      => $request->is_room ?? 1,
                'is_posted'    => 0,
                'deleted_at'   => null,
                'created_by'   => Auth::user()?->username ?? 'system',
            ]
        );

        return response()->json([
            'success' => true,
            'data'    => $service,
            'message' => 'Đã thêm/cập nhật dịch vụ thành công.',
        ], 201);
    }

    // =========================================
    // DELETE: Xóa hàng loạt dịch vụ (Epic 10)
    // DELETE /booking-rooms/{roomId}/services
    // =========================================
    public function bulkDelete(Request $request, $roomId)
    {
        $room = BookingRoom::findOrFail($roomId);

        if (!in_array($room->status, [BookingRoom::STATUS_BOOKED, BookingRoom::STATUS_CHECKED_IN])) {
            return response()->json(['success' => false, 'message' => 'Phòng không ở trạng thái hợp lệ để xóa dịch vụ.'], 422);
        }

        $request->validate([
            'service_ids'   => 'required|array',
            'service_ids.*' => 'integer',
        ]);

        $systemDate  = $this->avService->getSystemDate()->toDateString();
        $services    = BookingRoomService::where('booking_room_id', $roomId)
            ->whereIn('id', $request->service_ids)
            ->where('service_date', '>=', $systemDate) // Chỉ xóa ngày >= system_date
            ->get();

        $deletedCount = 0;
        $hasEbDeleted = false;

        foreach ($services as $svc) {
            if ($svc->service_code === BookingRoomService::CODE_EXTRA_BED) {
                $hasEbDeleted = true;
            }
            $svc->delete();
            $deletedCount++;
        }

        // Nếu xóa hết EB → reset extra_bed_qty = 0
        if ($hasEbDeleted) {
            $remainingEB = $room->services()
                ->where('service_code', BookingRoomService::CODE_EXTRA_BED)
                ->count();
            if ($remainingEB === 0) {
                $room->update(['extra_bed_qty' => 0, 'extra_bed_rate' => 0]);
            }
        }

        return response()->json([
            'success' => true,
            'deleted' => $deletedCount,
            'message' => "Đã xóa $deletedCount dịch vụ.",
        ]);
    }

    // =========================================
    // GET: Lấy giá Extra Bed mặc định từ hotel_settings (Epic 14)
    // GET /booking-rooms/{roomId}/services/extra-bed-rate
    // =========================================
    public function defaultExtraBedRate()
    {
        $setting = HotelSetting::first();
        $rate    = $setting?->extra_bed_rate ?? 0;

        return response()->json(['success' => true, 'extra_bed_rate' => $rate]);
    }
}
