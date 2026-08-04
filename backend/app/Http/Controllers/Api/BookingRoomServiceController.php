<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingRoomService;
use App\Models\HotelConfig;
use App\Models\HotelService;
use App\Models\HotelSetting;
use App\Models\HousekeepingServiceBill;
use App\Models\HousekeepingServiceBillDetail;
use App\Models\RoomNightBill;
use App\Models\ServiceBill;
use App\Models\ServiceBillDetail;
use App\Models\SystemDateRoll;
use App\Services\RoomAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
        $services = HotelService::where(function($q) {
                $q->where('department', 'FO')
                  ->orWhere('department', 'like', '%Reception%')
                  ->orWhere('department', 'like', '%Lễ Tân%')
                  ->orWhere('department', 'like', '%Lễ tân%')
                  ->orWhere('department', 'like', '%Front%');
            })
            ->orderBy('name')
            ->get(['code', 'name', 'price', 'unit', 'short_name', 'department']);

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
            'guest_id'      => 'nullable|string|max:50',
            'service_date'  => 'required|date',
            'quantity'      => 'nullable|numeric|min:0',
            'rate'          => 'nullable|numeric',
            'is_room'       => 'nullable|boolean',
            'folio'         => 'nullable|integer|between:1,3',
        ]);

        // Kiểm tra service_code phải tồn tại trong hệ thống
        $foService = HotelService::where('code', $request->service_code)->first();

        if (!$foService) {
            return response()->json([
                'success' => false,
                'message' => 'Mã dịch vụ "' . $request->service_code . '" không tồn tại trong hệ thống.',
            ], 422);
        }

        if ($request->filled('guest_id') && !$room->guests()->where('guest_id', $request->guest_id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Khách được chọn không thuộc phòng này.',
            ], 422);
        }

        // Nếu số lượng <= 0 → Xóa dịch vụ của ngày này nếu có
        if (isset($request->quantity) && (float)$request->quantity <= 0) {
            BookingRoomService::where('booking_room_id', $roomId)
                ->where('service_code', $request->service_code)
                ->where('service_date', $request->service_date)
                ->where('is_posted', 0)
                ->delete();

            if ($request->service_code === BookingRoomService::CODE_EXTRA_BED) {
                $remainingEB = BookingRoomService::where('booking_room_id', $roomId)
                    ->where('service_code', BookingRoomService::CODE_EXTRA_BED)
                    ->get();

                if ($remainingEB->isEmpty()) {
                    $room->update(['extra_bed_qty' => 0, 'extra_bed_rate' => 0]);
                } else {
                    $maxQty = $remainingEB->max('quantity');
                    $latestRate = $remainingEB->firstWhere('rate', '>', 0)?->rate ?? 0;
                    $room->update(['extra_bed_qty' => $maxQty, 'extra_bed_rate' => $latestRate]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Đã xóa dịch vụ do số lượng bằng 0.',
            ]);
        }

        // Nếu trùng ngày + service_code → update giá, không cộng dồn
        $service = BookingRoomService::withTrashed()->updateOrCreate(
            [
                'booking_room_id' => $roomId,
                'guest_id'         => $request->guest_id,
                'service_code'    => $request->service_code,
                'service_date'    => $request->service_date,
            ],
            [
                'service_name' => $request->service_name,
                'quantity'     => $request->quantity ?? 1,
                'rate'         => $request->rate ?? 0,
                'is_room'      => $request->is_room ?? 1,
                'folio'        => $request->folio ?? 1,
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

        $systemDate = $this->avService->getSystemDate();

        $services    = BookingRoomService::where('booking_room_id', $roomId)
            ->whereIn('id', $request->service_ids)
            ->get();

        $deletedCount = 0;
        $hasEbDeleted = false;

        foreach ($services as $svc) {
            // Check Epic 10: only allow deleting if service_date >= system_date AND is_posted == 0
            if (Carbon::parse($svc->service_date)->lt($systemDate) || $svc->is_posted == 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể xóa dịch vụ bổ sung trong quá khứ hoặc đã được post sang Folio (Dịch vụ: ' . ($svc->service_name ?: $svc->service_code) . ' ngày ' . $svc->service_date->toDateString() . ').',
                ], 422);
            }

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

    /**
     * Hủy bill dịch vụ đã post: giữ audit bằng bill âm, không xóa vật lý bill.
     */
    public function cancel(Request $request, $roomId)
    {
        $validated = $request->validate([
            'service_ids' => 'required|array|min:1',
            'service_ids.*' => 'integer',
            'reason' => 'required|string|max:255',
        ]);
        $room = BookingRoom::findOrFail($roomId);

        DB::transaction(function () use ($validated, $room) {
            $services = BookingRoomService::where('booking_room_id', $room->id)
                ->whereIn('id', $validated['service_ids'])
                ->lockForUpdate()
                ->get();
            if ($services->count() !== count(array_unique($validated['service_ids']))) {
                abort(422, 'Có dịch vụ không thuộc phòng đã chọn.');
            }
            if ($services->contains(fn (BookingRoomService $service) => !$service->service_bill_id)) {
                abort(422, 'Dịch vụ chưa có liên kết bill để thực hiện xóa.');
            }

            $systemDate = Carbon::parse($this->avService->getSystemDate())->startOfDay();
            foreach ($services->groupBy('service_bill_id') as $billId => $billServices) {
                $bill = ServiceBill::whereKey($billId)->lockForUpdate()->firstOrFail();
                if ($bill->PaymentId !== null || $bill->VatId !== null || (int) $bill->Status !== 1 || (int) $bill->Edit === 1) {
                    abort(422, 'Chỉ được xóa dịch vụ chưa thanh toán và chưa xuất hóa đơn VAT.');
                }

                $allBillServices = BookingRoomService::where('booking_room_id', $room->id)
                    ->where('service_bill_id', $bill->Ma)
                    ->lockForUpdate()
                    ->get();
                if ($allBillServices->count() !== $billServices->count()) {
                    abort(422, 'Phải chọn toàn bộ chi tiết của cùng một hóa đơn dịch vụ để xóa.');
                }

                $serviceDate = Carbon::parse($bill->Date ?: $billServices->first()->service_date)->startOfDay();
                if ($serviceDate->lt($systemDate) && !$this->canOperateOldDay()) {
                    abort(403, 'Tài khoản không có quyền xóa dịch vụ ngày cũ (RuleUserCorrectOrPostBillPaymentOldDay).');
                }

                $description = trim(($bill->DescriptionServive ?: $bill->ServiceId) . ' (Correct : ' . trim($validated['reason']) . ')');
                $negative = $bill->replicate();
                $negative->Amount = -abs((float) $bill->Amount);
                $negative->DescriptionServive = $description;
                $negative->Edit = 1;
                $negative->Status = 3;
                $negative->Pack1 = (string) $bill->Ma;
                $negative->UpdatedDate = now();
                $negative->save();

                $bill->DescriptionServive = $description;
                $bill->Edit = 1;
                $bill->Status = 3;
                $bill->Pack1 = (string) $negative->Ma;
                $bill->UpdatedDate = now();
                $bill->save();

                // Giữ bill buồng phòng để audit; bill/chi tiết đã hủy không được hiển thị tại Checkout.
                $housekeepingBills = HousekeepingServiceBill::where('BillServiceId', $bill->Ma)
                    ->lockForUpdate()
                    ->get();
                foreach ($housekeepingBills as $housekeepingBill) {
                    $housekeepingBill->Status = 3;
                    $housekeepingBill->BillEdit = 1;
                    $housekeepingBill->save();
                    HousekeepingServiceBillDetail::where('BillId', $housekeepingBill->Ma)
                        ->update(['Deleted' => 1]);
                }

                ServiceBillDetail::where('BillServiceId', $bill->Ma)->delete();
                $allBillServices->each->delete();
            }
        });

        return response()->json(['success' => true, 'message' => 'Đã xóa dịch vụ và tạo dòng đối trừ.']);
    }

    // =========================================
    // GET: Lấy giá Extra Bed mặc định từ hotel_settings (Epic 14)
    // GET /booking-rooms/{roomId}/services/extra-bed-rate
    // =========================================
    public function quickTransferCandidates($roomId)
    {
        $isMaster = str_starts_with((string) $roomId, 'master-');
        $targetRoom = $isMaster ? null : BookingRoom::findOrFail($roomId);
        $bookingId = $isMaster ? (int) substr((string) $roomId, 7) : $targetRoom->booking_id;
        $booking = Booking::findOrFail($bookingId);
        $roomIds = BookingRoom::where('booking_id', $bookingId)
            ->when($targetRoom, fn ($query) => $query->where('id', '!=', $targetRoom->id))
            ->whereIn('status', [BookingRoom::STATUS_BOOKED, BookingRoom::STATUS_CHECKED_IN])
            ->pluck('id');
        $services = BookingRoomService::whereIn('booking_room_id', $roomIds)->whereNotNull('service_bill_id')->get();
        $bills = ServiceBill::whereIn('Ma', $services->pluck('service_bill_id')->unique())
            ->whereNull('PaymentId')->where('Status', 1)->where('Edit', 0)->get()->keyBy('Ma');
        $rooms = BookingRoom::with('guests.guest', 'booking')->whereIn('id', $roomIds)->get()->keyBy('id');

        $items = $services->groupBy('service_bill_id')->map(function ($billServices, $billId) use ($bills, $rooms) {
            $bill = $bills->get($billId);
            $sourceRoom = $rooms->get($billServices->first()->booking_room_id);
            if (!$bill || !$sourceRoom) return null;
            $first = $billServices->first();
            $code = strtoupper(explode('_', $first->service_code)[0] ?: 'DV');
            $guest = optional($sourceRoom->guests->firstWhere('is_primary', 1)?->guest)->full_name ?: $bill->Guest;
            return ['bill_id' => $bill->Ma, 'category' => $code, 'booking_code' => $sourceRoom->booking?->booking_code ?: $sourceRoom->booking_id,
                'guest_name' => $guest, 'room_number' => $sourceRoom->room_number, 'amount' => (float) $bill->Amount,
                'description' => $bill->DescriptionServive, 'source_room_id' => $sourceRoom->id];
        })->filter()->values();

        // A bill already collected to Master has no booking_room_services row by design.
        // Keep it transferable to another room in the same booking.
        if ($targetRoom) {
            $masterItems = ServiceBill::where('RegisterID2', $booking->id)
                ->whereNull('RentalRoomId2')
                ->whereNull('PaymentId')
                ->where('Status', 1)
                ->where('Edit', 0)
                ->get()
                ->map(fn (ServiceBill $bill) => [
                    'bill_id' => $bill->Ma,
                    'category' => $bill->Outlet ?: ($bill->ServiceId ?: 'DV'),
                    'booking_code' => $booking->booking_code ?: $booking->id,
                    'guest_name' => $bill->Guest ?: $booking->booking_name,
                    'room_number' => 'Master',
                    'amount' => (float) $bill->Amount,
                    'description' => $bill->DescriptionServive,
                    'source_room_id' => null,
                ]);
            $items = $items->concat($masterItems)->values();
        }

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function quickTransfer(Request $request, $roomId)
    {
        $request->validate([
            'bill_ids' => 'required|array|min:1',
            'bill_ids.*' => 'integer',
            'target_guest_id' => 'nullable|string|exists:guests,id',
        ]);
        $isMaster = str_starts_with((string) $roomId, 'master-');
        $targetRoom = $isMaster ? null : BookingRoom::with('guests.guest', 'booking')->findOrFail($roomId);
        $targetBooking = $isMaster ? Booking::findOrFail((int) substr((string) $roomId, 7)) : $targetRoom->booking;
        if (!$isMaster && !in_array((int) $targetRoom->status, [BookingRoom::STATUS_BOOKED, BookingRoom::STATUS_CHECKED_IN], true)) abort(422, 'Phòng nhận phải ở trạng thái Reservation hoặc Inhouse.');
        $targetGuest = null;
        if ($targetRoom) {
            $targetGuest = $request->filled('target_guest_id')
                ? $targetRoom->guests->firstWhere('guest_id', (string) $request->target_guest_id)
                : $targetRoom->guests->firstWhere('is_primary', 1);

            if ($request->filled('target_guest_id') && !$targetGuest) {
                abort(422, 'Khách nhận không thuộc phòng đã chọn.');
            }
        }

        DB::transaction(function () use ($request, $targetRoom, $targetBooking, $targetGuest) {
            $containsRoomCharge = false;
            foreach (array_unique($request->bill_ids) as $billId) {
                $bill = ServiceBill::lockForUpdate()->findOrFail($billId);
                $containsRoomCharge = $containsRoomCharge || strtoupper((string) $bill->ServiceId) === BookingRoomService::CODE_ROOM;
                if ($bill->PaymentId !== null || (int) $bill->Status !== 1 || (int) $bill->Edit === 1) abort(422, 'Chỉ được chuyển bill chưa thanh toán.');
                $isMasterSource = (int) $bill->RegisterID2 === (int) $targetBooking->id && empty($bill->RentalRoomId2);
                $sourceRoom = $isMasterSource ? null : BookingRoom::where('id', $bill->RentalRoomId2)->where('booking_id', $targetBooking->id)->lockForUpdate()->first();
                if (!$isMasterSource && (!$sourceRoom || ($targetRoom && $sourceRoom->id === $targetRoom->id) || !in_array((int) $sourceRoom->status, [0, 1], true))) abort(422, 'Bill phải thuộc một phòng Reservation/Inhouse khác trong cùng booking.');
                if ($isMasterSource && !$targetRoom) abort(422, 'Bill đang ở Master; hãy chọn một phòng nhận dịch vụ.');
                $services = $sourceRoom
                    ? BookingRoomService::where('booking_room_id', $sourceRoom->id)->where('service_bill_id', $bill->Ma)->lockForUpdate()->get()
                    : collect();
                if ($sourceRoom && $services->isEmpty()) abort(422, 'Không tìm thấy chi tiết dịch vụ của bill.');

                $originCreatedAt = $bill->CreatedDate ?: $bill->created_at;
                $positive = $bill->replicate();
                $positive->RegisterID2 = $targetRoom ? null : $targetBooking->id; $positive->RentalRoomId2 = $targetRoom?->id; $positive->CustomerId2 = $targetGuest?->guest_id;
                $positive->CompanyId2 = $targetBooking->company_id; $positive->Guest = $targetGuest?->guest?->full_name ?: $targetBooking->booking_name;
                $sourceLocation = $sourceRoom ? 'R_' . ($sourceRoom->room_number ?: $sourceRoom->id) : 'BK_' . $targetBooking->id;
                $targetLocation = $targetRoom ? 'R_' . ($targetRoom->room_number ?: $targetRoom->id) : 'BK_' . $targetBooking->id;
                $rawDesc = trim(($bill->DescriptionServive ?: $bill->ServiceId) . ' (' . $sourceLocation . '=>' . $targetLocation . ')');
                if (mb_strlen($rawDesc) > 950) {
                    $rawDesc = mb_substr($rawDesc, 0, 950);
                }
                $positive->Folio = '1'; $positive->DescriptionServive = $rawDesc;
                $positive->Edit = 0; $positive->Status = 1; $positive->Pack1 = null; $positive->UpdatedDate = now(); $positive->save();
                $negative = $bill->replicate(); $negative->Amount = -abs((float) $bill->Amount); $negative->Edit = 1; $negative->Status = 4; $negative->Pack1 = (string) $positive->Ma; $negative->UpdatedDate = now(); $negative->save();
                $bill->Edit = 1; $bill->Status = 4; $bill->UpdatedDate = now(); $bill->save();
                $details = ServiceBillDetail::where('BillServiceId', $bill->Ma)->get();
                foreach ($details as $detail) { $p = $detail->replicate(); $p->BillServiceId = $positive->Ma; $p->save(); $n = $detail->replicate(); $n->BillServiceId = $negative->Ma; $n->Amount = -abs((float) $detail->Amount); $n->save(); }
                $services->each(function (BookingRoomService $service) use ($targetRoom, $targetGuest, $positive) { $copy = $service->replicate(); $service->delete(); if (!$targetRoom) return; $copy->booking_room_id = $targetRoom->id; $copy->guest_id = $targetGuest?->guest_id; $copy->service_bill_id = $positive->Ma; $copy->folio = 1; $copy->note = mb_substr((string)$positive->DescriptionServive, 0, 950); $copy->service_name = mb_substr((string)($service->service_name ?: $positive->DescriptionServive), 0, 950); $copy->posted_at = $service->posted_at; $copy->created_at = $service->created_at; $copy->deleted_at = null; $copy->save(); });
                if ($isMasterSource && $targetRoom) {
                    $quantity = max((float) ($positive->Quantity ?: 1), 1);
                    $roomService = new BookingRoomService([
                        'booking_room_id' => $targetRoom->id,
                        'guest_id' => $targetGuest?->guest_id,
                        'service_bill_id' => $positive->Ma,
                        'service_bill_detail_no' => null,
                        'service_code' => $positive->ServiceId ?: ($positive->Outlet ?: 'DV'),
                        'service_name' => mb_substr((string)($positive->DescriptionServive ?: $positive->ServiceId), 0, 950),
                        'service_date' => $positive->Date,
                        'quantity' => $quantity,
                        'rate' => (float) $positive->Amount / $quantity,
                        'total_amount' => (float) $positive->Amount,
                        'department' => $positive->DepartmentId,
                        'note' => mb_substr((string)$positive->DescriptionServive, 0, 950),
                        'tax' => $positive->Tax,
                        'service_charge' => $positive->ServiceCharge,
                        'unit' => $positive->Currency ?: 'VND',
                        'folio' => 1,
                        'is_room' => strtoupper((string) $positive->ServiceId) === BookingRoomService::CODE_ROOM ? 1 : 0,
                        'is_posted' => 1,
                        'posted_at' => $originCreatedAt,
                        'created_by' => auth()->user()?->username ?: 'system',
                        'updated_by' => auth()->user()?->username ?: 'system',
                    ]);
                    $roomService->preserveTotalAmount = true;
                    $roomService->created_at = $originCreatedAt;
                    $roomService->save();
                }
            }

            if ($containsRoomCharge && $targetRoom && $targetBooking->is_master_room_rate) {
                $targetBooking->is_master_room_rate = false;
                $targetBooking->save();
            }
        });
        return response()->json(['success' => true, 'message' => 'Đã tập hợp dịch vụ về phòng đã chọn.']);
    }

    public function transferFolio(Request $request, $roomId)
    {
        if ($request->has('folio') && !$request->has('target_booking_id')) {
            $validated = $request->validate([
                'service_ids'   => 'required|array|min:1',
                'service_ids.*' => 'integer',
                'folio'         => 'required|integer|between:1,3',
            ]);
            $room = BookingRoom::find($roomId) ?? BookingRoom::where('booking_id', $roomId)->first();
            if (!$room) {
                abort(404, 'Không tìm thấy phòng.');
            }

            DB::transaction(function () use ($validated, $room) {
                $serviceIds = array_map('intval', $validated['service_ids']);

                // 1. Cập nhật booking_room_services nếu có
                $services = BookingRoomService::whereIn('id', $serviceIds)
                    ->lockForUpdate()
                    ->get();

                $services->each(function (BookingRoomService $service) use ($validated) {
                    $service->folio = $validated['folio'];
                    $service->save();
                });

                // 2. Cập nhật ServiceBill (cho cả bill RM và bill lẻ)
                $billIdsFromServices = $services->pluck('service_bill_id')->filter()->toArray();
                $allBillIds = array_unique(array_merge($serviceIds, $billIdsFromServices));

                if (!empty($allBillIds)) {
                    ServiceBill::whereIn('Ma', $allBillIds)->update(['Folio' => (string) $validated['folio']]);
                }
            });

            return response()->json(['success' => true, 'message' => 'Đã chuyển dịch vụ sang Folio mới.']);
        }

        $request->validate([
            'service_ids'   => 'required|array|min:1',
            'service_ids.*' => 'integer',
            'target_booking_id' => 'required|integer|exists:bookings,id',
            'target_room_id' => 'nullable|string|exists:booking_rooms,id',
            'target_guest_id' => 'nullable|string|exists:guests,id',
        ]);

        $sourceRoom = BookingRoom::findOrFail($roomId);
        $targetBooking = Booking::findOrFail($request->target_booking_id);
        $targetRoom = $request->target_room_id
            ? BookingRoom::where('booking_id', $targetBooking->id)->findOrFail($request->target_room_id)
            : null;
        $activeRoomStatuses = [BookingRoom::STATUS_BOOKED, BookingRoom::STATUS_CHECKED_IN];
        if (!in_array((int) $sourceRoom->status, $activeRoomStatuses, true)) {
            abort(422, 'Chỉ được chuyển dịch vụ từ phòng Reservation hoặc Inhouse.');
        }
        if (!in_array((int) $targetBooking->status, [Booking::STATUS_RESERVATION, Booking::STATUS_CHECKIN], true)) {
            abort(422, 'Chỉ được chuyển dịch vụ đến booking Reservation hoặc Inhouse.');
        }
        if ($targetRoom && !in_array((int) $targetRoom->status, $activeRoomStatuses, true)) {
            abort(422, 'Chỉ được chuyển dịch vụ đến phòng Reservation hoặc Inhouse.');
        }
        $targetGuest = $targetRoom
            ? ($request->target_guest_id ? $targetRoom->guests()->with('guest')->where('guest_id', $request->target_guest_id)->firstOrFail() : $targetRoom->guests()->with('guest')->where('is_primary', 1)->first())
            : null;
        $sourceLocation = 'R_' . ($sourceRoom->room_number ?: $sourceRoom->id);
        $targetLocation = $targetRoom
            ? 'R_' . ($targetRoom->room_number ?: $targetRoom->id)
            : 'BK_' . $targetBooking->id;

        DB::transaction(function () use ($request, $sourceRoom, $targetBooking, $targetRoom, $targetGuest, $sourceLocation, $targetLocation) {
            $services = BookingRoomService::where('booking_room_id', $sourceRoom->id)
                ->whereIn('id', $request->service_ids)->lockForUpdate()->get();
            if ($services->count() !== count(array_unique($request->service_ids))) abort(422, 'Có dịch vụ không thuộc phòng đã chọn.');
            if ($services->contains(fn ($service) => !$service->service_bill_id)) abort(422, 'Dịch vụ chưa có liên kết bill để thực hiện chuyển.');

            foreach ($services->groupBy('service_bill_id') as $serviceBillId => $billServices) {
                $bill = ServiceBill::whereKey($serviceBillId)->lockForUpdate()->firstOrFail();
                if ($bill->PaymentId !== null || (int) $bill->Status !== 1 || (int) $bill->Edit === 1) abort(422, 'Chỉ được chuyển dịch vụ chưa thanh toán.');
                $allBillServices = BookingRoomService::where('booking_room_id', $sourceRoom->id)
                    ->where('service_bill_id', $bill->Ma)->lockForUpdate()->get();
                if ($allBillServices->count() !== $billServices->count()) abort(422, 'Phải chọn toàn bộ chi tiết của cùng một hóa đơn dịch vụ để chuyển.');

                $positive = $bill->replicate();
                $positive->RegisterID2 = $targetRoom ? null : $targetBooking->id;
                $positive->RentalRoomId2 = $targetRoom?->id;
                $positive->CustomerId2 = $targetGuest?->guest_id;
                $positive->CompanyId2 = $targetBooking->company_id;
                $positive->Guest = $targetGuest?->guest?->full_name ?: $targetBooking->booking_name;
                $rawDesc = trim(($bill->DescriptionServive ?: $bill->ServiceId) . " ({$sourceLocation}=>{$targetLocation})");
                if (mb_strlen($rawDesc) > 950) {
                    $rawDesc = mb_substr($rawDesc, 0, 950);
                }
                $positive->DescriptionServive = $rawDesc;
                if ($targetRoom) $positive->Folio = '1';
                $positive->Edit = 0;
                $positive->Status = 1;
                $positive->Pack1 = null;
                $positive->UpdatedDate = now();
                $positive->save();

                $negative = $bill->replicate();
                $negative->Amount = -abs((float) $bill->Amount);
                $negative->Edit = 1;
                $negative->Status = 4;
                $negative->Pack1 = (string) $positive->Ma;
                $negative->UpdatedDate = now();
                $negative->save();

                $bill->Edit = 1;
                $bill->Status = 4;
                $bill->UpdatedDate = now();
                $bill->save();

                foreach (ServiceBillDetail::where('BillServiceId', $bill->Ma)->get() as $detail) {
                    $positiveDetail = $detail->replicate();
                    $positiveDetail->BillServiceId = $positive->Ma;
                    $positiveDetail->save();
                    $negativeDetail = $detail->replicate();
                    $negativeDetail->BillServiceId = $negative->Ma;
                    $negativeDetail->Amount = -abs((float) $detail->Amount);
                    $negativeDetail->save();
                }

                $billServices->each(function (BookingRoomService $service) use ($targetRoom, $targetGuest, $positive) {
                    $targetService = $service->replicate();
                    $service->delete();
                    if (!$targetRoom) return;
                    $targetService->booking_room_id = $targetRoom->id;
                    $targetService->guest_id = $targetGuest?->guest_id;
                    $targetService->service_bill_id = $positive->Ma;
                    $targetService->note = mb_substr((string)$positive->DescriptionServive, 0, 950);
                    $targetService->service_name = mb_substr((string)($service->service_name ?: $positive->DescriptionServive), 0, 950);
                    $targetService->folio = 1;
                    $targetService->posted_at = $service->posted_at;
                    $targetService->created_at = $service->created_at;
                    $targetService->deleted_at = null;
                    $targetService->save();
                });
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Đã chuyển dịch vụ thành công.',
        ]);
    }

    public function splitFolio(Request $request, $roomId)
    {
        $request->validate([
            'service_ids'   => 'required|array|min:1',
            'service_ids.*' => 'integer',
            'folio'         => 'required|integer|between:1,3',
            'amount'        => 'required|numeric|gt:0',
        ]);

        $room = BookingRoom::findOrFail($roomId);
        $result = DB::transaction(function () use ($request, $room) {
            $services = BookingRoomService::where('booking_room_id', $room->id)
                ->whereIn('id', $request->service_ids)
                ->lockForUpdate()
                ->get();

            if ($services->count() !== count(array_unique($request->service_ids))) {
                abort(422, 'Có dịch vụ không thuộc phòng đã chọn.');
            }
            if ($services->contains(fn ($service) => $service->service_code === BookingRoomService::CODE_ROOM)) {
                abort(422, 'Không được tách dịch vụ tiền phòng.');
            }

            $serviceBillIds = $services->pluck('service_bill_id')->filter()->unique()->values();
            if ($serviceBillIds->count() !== 1 || $services->contains(fn ($service) => !$service->service_bill_id)) {
                abort(422, 'Chỉ có thể tách hóa đơn dịch vụ đã được liên kết dữ liệu bill.');
            }

            $serviceBill = ServiceBill::whereKey($serviceBillIds->first())->lockForUpdate()->firstOrFail();
            if ($serviceBill->PaymentId !== null || $serviceBill->VatId !== null || (int) $serviceBill->Status !== 1) {
                abort(422, 'Chỉ được tách dịch vụ chưa thanh toán và chưa xuất VAT.');
            }

            $allBillServices = BookingRoomService::where('booking_room_id', $room->id)
                ->where('service_bill_id', $serviceBill->Ma)
                ->lockForUpdate()
                ->get();
            if ($allBillServices->count() !== $services->count() || $allBillServices->pluck('id')->diff($services->pluck('id'))->isNotEmpty()) {
                abort(422, 'Phải chọn toàn bộ chi tiết của cùng một hóa đơn dịch vụ để tách.');
            }

            $details = ServiceBillDetail::where('BillServiceId', $serviceBill->Ma)->lockForUpdate()->get()->keyBy('Ma');
            if ($details->count() !== $services->count() || $details->contains(fn ($detail) => $detail->VatId !== null)) {
                abort(422, 'Hóa đơn dịch vụ không đủ điều kiện tách hoặc đã xuất VAT.');
            }

            $totalAmount = round((float) $serviceBill->Amount, 2);
            $requestedAmount = round((float) $request->amount, 2);
            if ($requestedAmount <= 0 || $requestedAmount >= $totalAmount) {
                abort(422, 'Số tiền tách phải lớn hơn 0 và nhỏ hơn tổng tiền hóa đơn dịch vụ.');
            }

            $operationTime = now();
            $sourceAmount = round($totalAmount - $requestedAmount, 2);
            $splitBill = $serviceBill->replicate();
            $splitBill->Amount = $requestedAmount;
            $splitBill->Folio = (string) $request->folio;
            $splitBill->UpdatedDate = $operationTime;
            $splitBill->save();

            $serviceBill->Amount = $sourceAmount;
            $serviceBill->UpdatedDate = $operationTime;
            $serviceBill->save();

            $housekeepingBill = HousekeepingServiceBill::where('BillServiceId', $serviceBill->Ma)->lockForUpdate()->first();
            $splitHousekeepingBill = $this->splitHousekeepingBill($housekeepingBill, $splitBill->Ma, $totalAmount, $requestedAmount, $operationTime);

            $allocatedAmount = 0;
            foreach ($services->values() as $index => $service) {
                $originalAmount = round((float) $service->total_amount, 2);
                $targetAmount = $index === $services->count() - 1
                    ? round($requestedAmount - $allocatedAmount, 2)
                    : round($requestedAmount * ($originalAmount / $totalAmount), 2);
                $allocatedAmount += $targetAmount;
                $sourceLineAmount = round($originalAmount - $targetAmount, 2);
                $detail = $details->get($service->service_bill_detail_no);
                if (!$detail) abort(422, 'Thiếu chi tiết hóa đơn dịch vụ để thực hiện tách.');

                $splitService = $this->splitServiceByAmount($service, (int) $request->folio, $targetAmount);
                $splitService->update([
                    'service_bill_id' => $splitBill->Ma,
                    'service_bill_detail_no' => $detail->Ma,
                    'housekeeping_service_bill_id' => $splitHousekeepingBill?->Ma,
                    'housekeeping_service_bill_detail_no' => $service->housekeeping_service_bill_detail_no,
                ]);

                ServiceBillDetail::where('BillServiceId', $serviceBill->Ma)
                    ->where('Ma', $detail->Ma)
                    ->update(['Amount' => $sourceLineAmount]);
                $splitDetail = $detail->replicate();
                $splitDetail->BillServiceId = $splitBill->Ma;
                $splitDetail->Ma = $detail->Ma;
                $splitDetail->Amount = $targetAmount;
                $splitDetail->save();

                $this->splitHousekeepingBillDetail($service, $splitHousekeepingBill, $sourceLineAmount, $targetAmount);
            }

            return $splitBill;
        });

        return response()->json(['success' => true, 'message' => 'Đã tách dịch vụ theo số tiền thành công.', 'data' => ['service_bill_id' => $result->Ma]]);
    }

    protected function splitServiceByAmount(BookingRoomService $service, int $folio, float $targetAmount): BookingRoomService
    {
        $originalAmount = round((float) $service->total_amount, 2);
        $sourceAmount = round($originalAmount - $targetAmount, 2);
        $rate = (float) $service->rate;
        $targetQty = round($targetAmount / $rate, 6);
        $sourceQty = round($sourceAmount / $rate, 6);
        if ($sourceQty <= 0 || $targetQty <= 0) abort(422, 'Không thể tách chính xác số tiền này với số lượng dịch vụ hiện có.');

        $splitService = $service->replicate();
        $splitService->folio = $folio;
        $splitService->quantity = $targetQty;
        $splitService->total_amount = $targetAmount;
        $splitService->preserveTotalAmount = true;
        $splitService->save();

        $service->preserveTotalAmount = true;
        $service->update(['quantity' => $sourceQty, 'total_amount' => $sourceAmount]);

        return $splitService;
    }

    protected function splitHousekeepingBill(?HousekeepingServiceBill $bill, int $splitServiceBillId, float $totalAmount, float $splitAmount, $operationTime): ?HousekeepingServiceBill
    {
        if (!$bill) return null;

        $sourceAmount = round($totalAmount - $splitAmount, 2);
        $splitOriginalAmount = round((float) $bill->BillOriginalAmount * ($splitAmount / $totalAmount), 6);
        $splitDiscountAmount = round((float) $bill->BillDiscountAmount * ($splitAmount / $totalAmount), 6);
        $splitBill = $bill->replicate();
        $splitBill->BillServiceId = $splitServiceBillId;
        $splitBill->BillAmount = $splitAmount;
        $splitBill->BillOriginalAmount = $splitOriginalAmount;
        $splitBill->BillDiscountAmount = $splitDiscountAmount;
        $splitBill->save();

        $bill->BillAmount = $sourceAmount;
        $bill->BillOriginalAmount = round((float) $bill->BillOriginalAmount - $splitOriginalAmount, 6);
        $bill->BillDiscountAmount = round((float) $bill->BillDiscountAmount - $splitDiscountAmount, 6);
        $bill->save();

        return $splitBill;
    }

    protected function splitHousekeepingBillDetail(BookingRoomService $service, ?HousekeepingServiceBill $splitBill, float $sourceAmount, float $splitAmount): void
    {
        if (!$splitBill || !$service->housekeeping_service_bill_id || !$service->housekeeping_service_bill_detail_no) return;

        $detail = HousekeepingServiceBillDetail::where('BillId', $service->housekeeping_service_bill_id)
            ->where('DetailId', $service->housekeeping_service_bill_detail_no)
            ->lockForUpdate()
            ->first();
        if (!$detail) return;

        HousekeepingServiceBillDetail::where('BillId', $service->housekeeping_service_bill_id)
            ->where('DetailId', $service->housekeeping_service_bill_detail_no)
            ->update(['TotalAmount' => $sourceAmount]);
        $splitDetail = $detail->replicate();
        $splitDetail->BillId = $splitBill->Ma;
        $splitDetail->DetailId = $detail->DetailId;
        $splitDetail->TotalAmount = $splitAmount;
        $splitDetail->save();
    }

    public function defaultExtraBedRate()
    {
        $setting = HotelSetting::first();
        $rate    = $setting?->extra_bed_rate ?? 0;

        return response()->json(['success' => true, 'extra_bed_rate' => $rate]);
    }

    // =========================================
    // POST: Post bill dịch vụ buồng phòng (Minibar, Giặt ủi, Đền bù)
    // Tách mỗi nhóm dịch vụ thành bill CSDL riêng biệt
    // POST /api/booking-room-services/post-housekeeping-bill
    // =========================================
    public function postHousekeepingBill(Request $request)
    {
        $request->validate([
            'booking_room_id' => 'required',
            'guest_id'         => 'nullable|string|max:50',
            'department'      => 'nullable|string|max:20',
            'posting_source'  => 'nullable|string|in:FO,HK,FB',
            'service_date'    => 'nullable|date',
            'is_free'         => 'nullable|boolean',
            'folio'           => 'nullable|integer|between:1,3',
            'note'            => 'nullable|string|max:255',
            'bills'           => 'required|array',
            'bills.*.group'   => 'required|string',
            'bills.*.items'   => 'required|array',
        ]);

        $room = BookingRoom::find($request->booking_room_id);

        if (!$room) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy phòng tương ứng.'], 404);
        }

        $postingSource = strtoupper($request->input('posting_source', 'HK'));
        if (in_array($postingSource, ['HK', 'FB'], true) && $room->no_post) {
            return response()->json([
                'success' => false,
                'message' => 'Phòng đang bật No Post, không thể post bill từ bộ phận này.',
            ], 422);
        }

        $guestPivot = $request->filled('guest_id')
            ? $room->guests()->with('guest')->where('guest_id', $request->guest_id)->first()
            : ($room->guests()->where('is_primary', 1)->with('guest')->first() ?: $room->guests()->with('guest')->first());

        if ($request->filled('guest_id') && !$guestPivot) {
            return response()->json(['success' => false, 'message' => 'Khách được chọn không thuộc phòng này.'], 422);
        }

        // Endpoint này chỉ dành cho post dịch vụ buồng phòng.
        $department = 'HK';
        $serviceDate = $request->service_date ?: now()->toDateString();
        $systemDate = $this->avService->getSystemDate();
        $serviceDateCarbon = Carbon::parse($serviceDate);
        if ($serviceDateCarbon->gt(Carbon::parse($systemDate))) {
            return response()->json(['success' => false, 'message' => 'Không thể post dịch vụ cho ngày tương lai.'], 422);
        }
        if ($serviceDateCarbon->lt(Carbon::parse($systemDate)) && !$this->canOperateOldDay()) {
            return response()->json(['success' => false, 'message' => 'Tài khoản không có quyền post dịch vụ cho ngày cũ (RuleUserCorrectOrPostBillPaymentOldDay).'], 403);
        }
        $folio = $request->folio ?? 1;
        $user = Auth::user()?->username ?? 'Admin';
        $createdRecords = [];

        DB::transaction(function () use ($request, $room, $guestPivot, $department, $serviceDate, $serviceDateCarbon, $folio, $user, &$createdRecords) {
            $groupMeta = [
                'minibar' => ['label' => 'Minibar', 'service' => 'MB', 'outlet' => 'MB'],
                'giatui'  => ['label' => 'Giặt ủi', 'service' => 'LA', 'outlet' => 'LA'],
                'dengbu'  => ['label' => 'Hàng đền bù', 'service' => 'BR', 'outlet' => 'BR'],
            ];
            $booking = $room->booking;
            $guestId = $guestPivot?->guest_id;
            $guestName = $guestPivot?->guest?->full_name ?: ($booking?->booking_name ?: 'Khách lẻ');

            foreach ($request->bills as $billGroup) {
                $groupKey = strtolower($billGroup['group'] ?? 'minibar');
                $items = $billGroup['items'] ?? [];
                if (empty($items)) continue;

                if (!isset($groupMeta[$groupKey])) continue;
                $meta = $groupMeta[$groupKey];
                $groupTitle = $meta['label'];
                $originalAmount = collect($items)->sum(fn ($item) => (float)($item['original_rate'] ?? $item['price'] ?? 0) * (float)($item['qty'] ?? 1));
                $billAmount = collect($items)->sum(fn ($item) => (float)($item['total_amount'] ?? $item['net_price'] ?? $item['price'] ?? 0));
                $discountAmount = collect($items)->sum(fn ($item) => (float)($item['discount_amount'] ?? 0));

                $targetServiceBillId = $request->filled('service_bill_id') ? (int) $request->service_bill_id : null;
                $existingServiceBill = $targetServiceBillId ? ServiceBill::whereKey($targetServiceBillId)->lockForUpdate()->first() : null;

                if ($existingServiceBill) {
                    $serviceBill = $existingServiceBill;
                    $serviceBill->update([
                        'Date' => $serviceDateCarbon->startOfDay(),
                        'Guest' => $guestName,
                        'DescriptionServive' => $groupTitle,
                        'Quantity' => 1,
                        'Amount' => $billAmount,
                        'Folio' => (string)$folio,
                        'UpdatedDate' => now(),
                        'UpdatedHour' => now()->format('H:i'),
                        'updated_at' => now(),
                    ]);

                    $bill = HousekeepingServiceBill::where('BillServiceId', $serviceBill->Ma)->lockForUpdate()->first();
                    if ($bill) {
                        $bill->update([
                            'BillOriginalAmount' => $originalAmount,
                            'BillDiscountAmount' => $discountAmount,
                            'BillAmount' => $billAmount,
                            'BillDiscount' => $originalAmount > 0 ? ($discountAmount / $originalAmount) * 100 : 0,
                            'BillNote' => $request->note,
                            'Date' => $serviceDateCarbon->startOfDay(),
                            'RoomNo' => $room->room_number,
                            'UpdatedDate' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        $bill = HousekeepingServiceBill::create([
                            'BookingId' => $booking?->id, 'GuestId' => $guestId, 'BillOriginalAmount' => $originalAmount,
                            'BillDiscountAmount' => $discountAmount, 'BillAmount' => $billAmount,
                            'BillDiscount' => $originalAmount > 0 ? ($discountAmount / $originalAmount) * 100 : 0,
                            'BillNote' => $request->note, 'Status' => 1, 'Outlet' => $meta['outlet'],
                            'Date' => $serviceDateCarbon->startOfDay(), 'Department' => $department,
                            'RoomNo' => $room->room_number, 'BillServiceId' => $serviceBill->Ma,
                            'Currency' => 'VND', 'ExchangeRate' => 1, 'BillUsername' => $user, 'BillEdit' => 0,
                        ]);
                    }

                    BookingRoomService::where('service_bill_id', $serviceBill->Ma)->delete();
                    HousekeepingServiceBillDetail::where('BillId', $bill->Ma)->delete();
                    ServiceBillDetail::where('BillServiceId', $serviceBill->Ma)->delete();
                } else {
                    $serviceBill = ServiceBill::create([
                        'Date' => $serviceDateCarbon->startOfDay(), 'OpenTime' => now()->format('H:i'),
                        'Guest' => $guestName, 'DepartmentId' => $department, 'ServiceId' => $meta['service'],
                        'DescriptionServive' => $groupTitle, 'Quantity' => 1, 'Amount' => $billAmount,
                        'Currency' => 'VND', 'Exchange' => 1, 'Edit' => 0, 'Folio' => (string)$folio,
                        'RentalRoomId1' => $room->id, 'CustomerId1' => $guestId, 'RentalRoomId2' => $room->id,
                        'CustomerId2' => $guestId, 'CompanyId2' => $booking?->company_id,
                        'Username' => $user, 'Status' => 1, 'Outlet' => $meta['outlet'],
                        'Year' => $serviceDateCarbon->year, 'Month' => $serviceDateCarbon->month, 'Day' => $serviceDateCarbon->day,
                        'CreatedUser' => $user, 'CreatedDate' => now(), 'CreatedHour' => now()->format('H:i'),
                    ]);
                    $bill = HousekeepingServiceBill::create([
                        'BookingId' => $booking?->id, 'GuestId' => $guestId, 'BillOriginalAmount' => $originalAmount,
                        'BillDiscountAmount' => $discountAmount, 'BillAmount' => $billAmount,
                        'BillDiscount' => $originalAmount > 0 ? ($discountAmount / $originalAmount) * 100 : 0,
                        'BillNote' => $request->note, 'Status' => 1, 'Outlet' => $meta['outlet'],
                        'Date' => $serviceDateCarbon->startOfDay(), 'Department' => $department,
                        'RoomNo' => $room->room_number, 'BillServiceId' => $serviceBill->Ma,
                        'Currency' => 'VND', 'ExchangeRate' => 1, 'BillUsername' => $user, 'BillEdit' => 0,
                    ]);
                }

                foreach ($items as $index => $item) {
                    $pName        = $item['name'] ?? ($item['product']['name'] ?? 'Sản phẩm buồng phòng');
                    $qty          = floatval($item['qty'] ?? 1);
                    $netPrice     = floatval($item['net_price'] ?? ($item['price'] ?? 0));
                    $prodCode     = $item['code'] ?? ($item['id'] ?? 'P');
                    $uniqueSuffix = substr(md5(uniqid(microtime(), true)), 0, 6);
                    $serviceCode  = strtoupper(substr($meta['service'] . '_' . $prodCode . '_' . $uniqueSuffix, 0, 30));

                    $taxAmt       = floatval($item['tax'] ?? 0);
                    $svcChargeAmt = floatval($item['service_charge'] ?? 0);

                    $created = BookingRoomService::create([
                        'booking_room_id' => $room->id,
                        'guest_id'         => $guestId,
                        'service_bill_id' => $serviceBill->Ma,
                        'service_bill_detail_no' => $index + 1,
                        'housekeeping_service_bill_id' => $bill->Ma,
                        'housekeeping_service_bill_detail_no' => $index + 1,
                        'service_code'    => $serviceCode,
                        'service_name'    => $pName,
                        'service_date'    => $serviceDate,
                        'quantity'        => $qty,
                        'rate'            => $netPrice,
                        'total_amount'    => $qty * $netPrice,
                        'department'      => $department,
                        'note'            => $request->note ?: "Post bill $groupTitle",
                        'tax'             => $taxAmt,
                        'service_charge'  => $svcChargeAmt,
                        'unit'            => $item['unit'] ?? 'Cái',
                        'folio'           => $folio,
                        'is_room'         => 1,
                        'is_posted'       => 1,
                        'posted_at'       => now(),
                        'created_by'      => $user,
                    ]);

                    $createdRecords[] = $created;

                    HousekeepingServiceBillDetail::create([
                        'BillId' => $bill->Ma, 'DetailId' => $index + 1,
                        'MaProduct' => is_numeric($item['id'] ?? null) ? $item['id'] : null,
                        'ProductGroupId' => $item['product_group_id'] ?? null, 'Product' => $pName,
                        'Rate' => $item['original_rate'] ?? $item['price'] ?? 0, 'Quantity' => $qty,
                        'Discount' => $item['discount_pct'] ?? 0, 'DiscountAmount' => $item['discount_amount'] ?? 0,
                        'Increase' => $item['increase_pct'] ?? 0, 'IncreaseAmount' => $item['increase_amount'] ?? 0,
                        'TotalAmount' => $item['total_amount'] ?? $item['net_price'] ?? 0, 'Note' => $request->note,
                    ]);
                    ServiceBillDetail::create([
                        'BillServiceId' => $serviceBill->Ma, 'Ma' => $index + 1,
                        'DepartmentId' => $department, 'ServiceId' => $meta['service'],
                        'DescriptionServive' => $pName, 'OriginalRate' => $item['original_rate'] ?? $item['price'] ?? 0,
                        'Amount' => $item['total_amount'] ?? $item['net_price'] ?? 0, 'Currency' => 'VND', 'Exchange' => 1,
                        'DetailBillOriginalAmount' => (float)($item['original_rate'] ?? $item['price'] ?? 0) * $qty,
                        'DiscountAmount' => $item['discount_amount'] ?? 0, 'IncreaseAmount' => $item['increase_amount'] ?? 0,
                    ]);
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Đã lưu hóa đơn dịch vụ thành công!',
            'data'    => $createdRecords
        ]);
    }

    // =========================================
    // POST: Post bill dịch vụ FO (Tab Dịch vụ)
    // POST /booking-room-services/post-fo-service-bill
    // =========================================
    public function postFoServiceBill(Request $request)
    {
        $request->validate([
            'booking_room_id' => 'required_without:booking_id|nullable|string',
            'booking_id'      => 'nullable',
            'date_from'       => 'required|date',
            'date_to'         => 'required|date|after_or_equal:date_from',
            'service_code'    => 'required|string|max:30',
            'quantity'        => 'required|numeric|min:0.01',
            'rate'            => 'required|numeric|min:0',
            'folio'           => 'nullable|integer|between:1,3',
            'description'     => 'nullable|string|max:400',
            'currency'        => 'nullable|string|max:3',
        ]);

        $roomId = $request->booking_room_id;
        $bookingId = $request->booking_id;

        $room = $roomId ? BookingRoom::find($roomId) : null;
        $booking = $room ? $room->booking : ($bookingId ? \App\Models\Booking::find($bookingId) : null);

        if (!$room && !$booking) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy phòng hoặc booking tương ứng.'], 404);
        }

        // Kiểm tra trạng thái nếu có room
        if ($room) {
            $allowCheckedOut = HotelConfig::where('name', 'AllowPostBillCheckedOutRoom')->value('value');
            if ($room->status === BookingRoom::STATUS_CHECKED_OUT && (int)$allowCheckedOut === 0) {
                return response()->json(['success' => false, 'message' => 'Phòng đã trả, không được phép post bill (AllowPostBillCheckedOutRoom=0).'], 422);
            }
        }

        $systemDate = $this->avService->getSystemDate();
        $dateFrom   = Carbon::parse($request->date_from);
        $dateTo     = Carbon::parse($request->date_to);

        // Validate ngày cũ
        if ($dateFrom->lt(Carbon::parse($systemDate)) && !$this->canOperateOldDay()) {
            return response()->json(['success' => false, 'message' => 'Không có quyền post bill cho ngày cũ.'], 403);
        }

        // Lấy thông tin dịch vụ FO
        $foService = HotelService::where('code', $request->service_code)->first();
        if (!$foService) {
            return response()->json(['success' => false, 'message' => 'Mã dịch vụ không tồn tại.'], 422);
        }

        $folio       = $request->folio ?? 1;
        $currency    = $request->currency ?? 'VND';
        $qty         = (float)$request->quantity;
        $rate        = (float)$request->rate;
        $totalAmount = $qty * $rate;
        $user        = Auth::user()?->username ?? 'system';
        $description = $request->description ?: $foService->name;

        $primaryGuest = $room ? ($room->guests()->where('is_primary', 1)->with('guest')->first() ?: $room->guests()->with('guest')->first()) : null;
        $guestId   = $primaryGuest?->guest_id;
        $guestName = $primaryGuest?->guest?->full_name ?: ($booking?->booking_name ?: 'Khách lẻ');

        $createdBills = [];

        DB::transaction(function () use (
            $room, $booking, $guestId, $guestName, $foService,
            $dateFrom, $dateTo, $folio, $currency, $qty, $rate,
            $totalAmount, $user, $description, &$createdBills
        ) {
            $current = $dateFrom->copy();
            $detailSeq = 1;

            while ($current->lte($dateTo)) {
                $bill = ServiceBill::create([
                    'Date'               => $current->startOfDay()->toDateTimeString(),
                    'OpenTime'           => now()->format('H:i'),
                    'Guest'              => $guestName,
                    'DepartmentId'       => 'FO',
                    'ServiceId'          => $foService->code,
                    'DescriptionServive' => $description,
                    'Quantity'           => $qty,
                    'Amount'             => $totalAmount,
                    'ServiceCharge'      => (float)($foService->service_charge ?? 0),
                    'SpecialTax'         => 0,
                    'Tax'                => (float)($foService->tax ?? 0),
                    'Currency'           => $currency,
                    'Exchange'           => 1,
                    'Edit'               => 0,
                    'Folio'              => (string)$folio,
                    'RegisterId1'        => $booking?->id,
                    'RentalRoomId1'      => $room?->id,
                    'CustomerId1'        => $guestId,
                    'RegisterID2'        => $booking?->id,
                    'RentalRoomId2'      => $room?->id,
                    'CustomerId2'        => $guestId,
                    'CompanyId2'         => $booking?->company_id,
                    'Username'           => $user,
                    'Status'             => 1,
                    'Outlet'             => 'FO',
                    'Year'               => $current->year,
                    'Month'              => $current->month,
                    'Day'                => $current->day,
                    'CreatedUser'        => $user,
                    'CreatedDate'        => now(),
                    'CreatedHour'        => now()->format('H:i'),
                ]);

                ServiceBillDetail::create([
                    'BillServiceId'            => $bill->Ma,
                    'Ma'                       => $detailSeq,
                    'DepartmentId'             => 'FO',
                    'ServiceId'                => $foService->code,
                    'DescriptionServive'       => $description,
                    'OriginalRate'             => $rate,
                    'ServiceCharge'            => (float)($foService->service_charge ?? 0),
                    'SpecialTax'               => 0,
                    'Tax'                      => (float)($foService->tax ?? 0),
                    'Amount'                   => $totalAmount,
                    'Currency'                 => $currency,
                    'Exchange'                 => 1,
                    'DetailBillOriginalAmount' => $rate * $qty,
                    'DiscountAmount'           => 0,
                    'IncreaseAmount'           => 0,
                ]);

                // Lưu/cập nhật vào booking_room_services chỉ khi post cho 1 phòng cụ thể
                if ($room) {
                    $existingBrs = BookingRoomService::where('booking_room_id', $room->id)
                        ->where('service_code', $foService->code)
                        ->where('service_date', $current->toDateString())
                        ->whereNull('service_bill_id')
                        ->first();

                    if ($existingBrs) {
                        $existingBrs->update([
                            'service_bill_id'        => $bill->Ma,
                            'service_bill_detail_no' => $detailSeq,
                            'quantity'               => $qty,
                            'rate'                   => $rate,
                            'total_amount'           => $totalAmount,
                            'folio'                  => $folio,
                            'is_posted'              => 1,
                            'posted_at'              => now(),
                            'note'         => $description,
                        ]);
                    } else {
                        BookingRoomService::create([
                            'booking_room_id' => $room->id,
                            'service_bill_id' => $bill->Ma,
                            'service_bill_detail_no' => $detailSeq,
                            'service_code'    => $foService->code,
                            'service_name'    => $foService->name,
                            'service_date'    => $current->toDateString(),
                            'quantity'        => $qty,
                            'rate'            => $rate,
                            'total_amount'    => $totalAmount,
                            'department'      => 'FO',
                            'note'            => $description,
                            'tax'             => (float)($foService->tax ?? 0),
                            'service_charge'  => (float)($foService->service_charge ?? 0),
                            'unit'            => $foService->unit ?? 'Lần',
                            'folio'           => $folio,
                            'is_room'         => 0,
                            'is_posted'       => 1,
                            'posted_at'       => now(),
                            'created_by'      => $user,
                        ]);
                    }
                }

                $createdBills[] = $bill->Ma;
                $current->addDay();
                $detailSeq++;
            }
        });

        return response()->json([
            'success'  => true,
            'message'  => 'Đã post ' . count($createdBills) . ' dịch vụ thành công.',
            'bill_ids' => $createdBills,
        ]);
    }

    // =========================================
    // POST: Post tiền phòng (Tab Tiền phòng)
    // POST /booking-room-services/post-room-charge
    // mode: 'auto' | 'update' | 'surcharge'
    // =========================================
    /** Điều chỉnh tiền phòng tại Master theo nghiệp vụ Hóa đơn dòng 66. */
    public function adjustRoomRate(Request $request, $bookingId)
    {
        $data = $request->validate(['booking_room_id' => 'required|string|max:50', 'service_date' => 'required|date', 'rate' => 'required|numeric|min:0', 'description' => 'nullable|string|max:400', 'reason' => 'required|string|max:400', 'update_room_rate' => 'nullable|boolean', 'update_room_rate_scope' => 'nullable|in:room,booking']);
        $booking = Booking::findOrFail($bookingId);
        $room = $booking->bookingRooms()->with('guests.guest')->findOrFail($data['booking_room_id']);
        if (!in_array((int) $room->status, [BookingRoom::STATUS_CHECKED_IN, BookingRoom::STATUS_CHECKED_OUT], true)) return response()->json(['success' => false, 'message' => 'Chỉ điều chỉnh tiền phòng của phòng đang ở hoặc đã checkout.'], 422);
        $date = Carbon::parse($data['service_date'])->startOfDay();
        $systemDate = SystemDateRoll::latest('id')->value('system_date');
        $systemDate = $systemDate ? Carbon::parse($systemDate)->startOfDay() : now('Asia/Ho_Chi_Minh')->startOfDay();
        $arrivalDate = Carbon::parse($booking->arrival_date)->startOfDay();
        $departureDate = Carbon::parse($booking->departure_date)->startOfDay();
        if ($date->lt($arrivalDate) || $date->gte($departureDate) || $date->gte($systemDate)) {
            return response()->json(['success' => false, 'message' => 'Ngày điều chỉnh phải trong thời gian ở và trước ngày hệ thống.', 'data' => ['arrival_date' => $arrivalDate->toDateString(), 'departure_date' => $departureDate->toDateString(), 'system_date' => $systemDate->toDateString()]], 422);
        }
        $original = ServiceBill::where('RegisterId1', $booking->id)->where('RentalRoomId1', $room->id)->where('ServiceId', 'RM')->whereDate('Date', $date)->where('Edit', 0)->latest('Ma')->first();
        if ($original && ($original->PaymentId !== null || $original->VatId !== null)) return response()->json(['success' => false, 'message' => 'Chỉ điều chỉnh bill tiền phòng chưa thanh toán và chưa xuất VAT.'], 422);

        $result = DB::transaction(function () use ($booking, $room, $date, $original, $data) {
            $user = Auth::user()?->username ?? 'system'; $rate = round((float) $data['rate'], 2); $reason = trim($data['reason']);
            $guest = $room->guests->firstWhere('is_primary', true) ?: $room->guests->first();
            $atMaster = (bool) $booking->is_master_room_rate; $negative = null; $oldAmount = (float) ($original?->Amount ?? 0);
            if ($original) {
                $original->update(['Edit' => 1, 'Status' => 3, 'IsAdjustment' => true, 'UpdatedDate' => now(), 'DescriptionServive' => trim(($original->DescriptionServive ?: 'Dịch vụ phòng nghỉ') . ' (Điều chỉnh: ' . $reason . ')')]);
                $negative = $original->replicate();
                $negative->Amount = -$oldAmount; $negative->Edit = 1; $negative->Status = 3; $negative->PaymentId = null; $negative->VatId = null;
                $negative->Pack1 = (string) $original->Ma; $negative->AdjustmentBillId = $original->Ma; $negative->IsAdjustment = true;
                $negative->DescriptionServive = 'Dòng âm điều chỉnh tiền phòng: ' . $reason; $negative->CreatedUser = $user; $negative->CreatedDate = now(); $negative->CreatedHour = now()->format('H:i'); $negative->save();
                ServiceBillDetail::where('BillServiceId', $original->Ma)->delete(); BookingRoomService::where('service_bill_id', $original->Ma)->delete(); RoomNightBill::where('bill_id', $original->Ma)->delete();
            }
            $baseDescription = trim((string) ($data['description'] ?? 'Dịch vụ phòng nghỉ')) ?: 'Dịch vụ phòng nghỉ';
            $bill = ServiceBill::create(['Date' => $date->toDateTimeString(), 'OpenTime' => now()->format('H:i'), 'Guest' => $atMaster ? $booking->booking_name : ($guest?->guest?->full_name ?: $booking->booking_name), 'DepartmentId' => 'FO', 'ServiceId' => 'RM', 'DescriptionServive' => $baseDescription . ' phòng ' . ($room->room_number ?: $room->id), 'Quantity' => 1, 'Amount' => $rate, 'ServiceCharge' => 0, 'SpecialTax' => 0, 'Tax' => 0, 'Currency' => $original?->Currency ?: 'VND', 'Exchange' => 1, 'Edit' => 0, 'Folio' => $original?->Folio ?: '1', 'RegisterId1' => $booking->id, 'RentalRoomId1' => $room->id, 'CustomerId1' => $guest?->guest_id, 'CompanyId1' => $booking->company_id, 'RegisterID2' => $booking->id, 'RentalRoomId2' => $atMaster ? null : $room->id, 'CustomerId2' => $atMaster ? null : $guest?->guest_id, 'CompanyId2' => $booking->company_id, 'Username' => $user, 'Status' => 1, 'Outlet' => 'FO', 'Year' => $date->year, 'Month' => $date->month, 'Day' => $date->day, 'CreatedUser' => $user, 'CreatedDate' => now(), 'CreatedHour' => now()->format('H:i'), 'AdjustmentBillId' => $original?->Ma, 'IsAdjustment' => true]);
            $adults = max(1, (int) $room->adults);
            $breakfastRate = (float) (HotelSetting::first()?->breakfast_adult_rate ?? 0);
            $breakfastAmount = $room->breakfast ? round($breakfastRate * $adults, 2) : 0;
            ServiceBillDetail::insert([
                ['BillServiceId' => $bill->Ma, 'Ma' => 1, 'DepartmentId' => 'FO', 'ServiceId' => 'RM', 'DescriptionServive' => $bill->DescriptionServive, 'OriginalRate' => $rate, 'Amount' => $rate, 'Currency' => $bill->Currency, 'Exchange' => 1, 'DetailBillOriginalAmount' => $rate],
                ['BillServiceId' => $bill->Ma, 'Ma' => 2, 'DepartmentId' => 'FO', 'ServiceId' => 'BF', 'DescriptionServive' => 'Ăn sáng phòng ' . ($room->room_number ?: $room->id), 'OriginalRate' => $breakfastAmount, 'Amount' => $breakfastAmount, 'Currency' => $bill->Currency, 'Exchange' => 1, 'DetailBillOriginalAmount' => $breakfastAmount],
                ['BillServiceId' => $bill->Ma, 'Ma' => 3, 'DepartmentId' => 'FO', 'ServiceId' => 'RM', 'DescriptionServive' => 'Giảm trừ ăn sáng phòng ' . ($room->room_number ?: $room->id), 'OriginalRate' => -$breakfastAmount, 'Amount' => -$breakfastAmount, 'Currency' => $bill->Currency, 'Exchange' => 1, 'DetailBillOriginalAmount' => -$breakfastAmount],
            ]);
            RoomNightBill::create(['bill_id' => $bill->Ma, 'adult' => $adults, 'child' => (int) $room->children_qty, 'is_room_night' => 1, 'breakfast_amount' => $breakfastAmount, 'date' => $date->toDateString(), 'room' => $room->room_number, 'room_type_id' => $room->room_class_id, 'breakfast' => $room->breakfast ? $adults : 0, 'extra_bed' => (int) $room->extra_bed_qty, 'rate_code' => $room->rate_code, 'rate' => $rate]);
            if (!$atMaster) BookingRoomService::updateOrCreate(['booking_room_id' => $room->id, 'service_code' => 'RM', 'service_date' => $date->toDateString()], ['guest_id' => $guest?->guest_id, 'service_bill_id' => $bill->Ma, 'service_bill_detail_no' => 1, 'service_name' => 'Tiền phòng', 'quantity' => 1, 'rate' => $rate, 'total_amount' => $rate, 'department' => 'FO', 'note' => $bill->DescriptionServive, 'unit' => 'Đêm', 'folio' => $bill->Folio, 'is_room' => 1, 'is_posted' => 1, 'posted_at' => now(), 'created_by' => $user]);
            if ((bool) ($data['update_room_rate'] ?? false)) {
                if (($data['update_room_rate_scope'] ?? 'room') === 'booking') {
                    $booking->bookingRooms()->update(['rate' => $rate]);
                } else {
                    $room->update(['rate' => $rate]);
                }
            }
            return ['original_bill_id' => $original?->Ma, 'negative_bill_id' => $negative?->Ma, 'new_bill_id' => $bill->Ma];
        });
        return response()->json(['success' => true, 'message' => 'Đã điều chỉnh tiền phòng.', 'data' => $result]);
    }

    public function postRoomCharge(Request $request)
    {
        $request->validate([
            'booking_room_id' => 'required_without:booking_id|nullable|string',
            'booking_id'      => 'nullable',
            'date_from'       => 'required|date',
            'date_to'         => 'required|date|after_or_equal:date_from',
            'mode'            => 'required|in:auto,update,surcharge',
            'rate'            => 'nullable|numeric|min:0',
            'charge_percent'  => 'nullable|numeric|min:0|max:100',
            'folio'           => 'nullable|integer|between:1,3',
            'description'     => 'nullable|string|max:400',
            'currency'        => 'nullable|string|max:3',
        ]);

        $roomId = $request->booking_room_id;
        $bookingId = $request->booking_id;

        $room = $roomId ? BookingRoom::find($roomId) : null;
        $booking = $room ? $room->booking : ($bookingId ? \App\Models\Booking::with('bookingRooms')->find($bookingId) : null);

        if (!$room && !$booking) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy phòng hoặc booking tương ứng.'], 404);
        }

        $isBookingPost = !$room && (bool) $booking;
        $roomsToPost = $room ? collect([$room]) : ($booking ? $booking->bookingRooms : collect());
        if ($roomsToPost->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Không có phòng nào để post tiền phòng.'], 422);
        }

        $systemDate = $this->avService->getSystemDate();
        $dateFrom   = Carbon::parse($request->date_from);
        $dateTo     = Carbon::parse($request->date_to);

        if ($isBookingPost) {
            // Khi post từ Master, bỏ qua các phòng chưa đến trong toàn bộ khoảng ngày.
            $roomsToPost = $roomsToPost->filter(function ($targetRoom) use ($dateTo) {
                if (in_array((int) $targetRoom->status, [BookingRoom::STATUS_CANCELLED, BookingRoom::STATUS_CHECKED_OUT], true)) {
                    return false;
                }
                return Carbon::parse($targetRoom->arrival_date)->startOfDay()->lte($dateTo->copy()->startOfDay());
            })->values();
            if ($roomsToPost->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'Không có phòng nào đã đến trong khoảng ngày được chọn để post tiền phòng.'], 422);
            }
        }

        foreach ($roomsToPost as $targetRoom) {
            $roomArrival = Carbon::parse($targetRoom->arrival_date)->startOfDay();
            if (in_array((int) $targetRoom->status, [BookingRoom::STATUS_CANCELLED, BookingRoom::STATUS_CHECKED_OUT], true)) {
                return response()->json(['success' => false, 'message' => 'Phòng đã hủy hoặc đã checkout, không thể post tiền phòng.'], 422);
            }
            if (!$isBookingPost && $dateFrom->copy()->startOfDay()->lt($roomArrival)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể post tiền phòng trước ngày đến của phòng ' . ($targetRoom->room_number ?: $targetRoom->id) . '.',
                ], 422);
            }
        }

        if ($dateFrom->lt(Carbon::parse($systemDate)) && !$this->canOperateOldDay()) {
            return response()->json(['success' => false, 'message' => 'Không có quyền post bill cho ngày cũ.'], 403);
        }

        $folio    = $request->folio ?? 1;
        $currency = $request->currency ?? 'VND';
        $mode     = $request->mode; // 'auto' | 'update' | 'surcharge'
        $chargePercent = (float) ($request->charge_percent ?? 100);
        $user     = Auth::user()?->username ?? 'system';
        $description = $request->description ?: 'Dịch vụ phòng nghỉ';

        $setting = HotelSetting::first();
        $createdBills = [];

        DB::transaction(function () use (
            $roomsToPost, $booking, $setting, $isBookingPost,
            $dateFrom, $dateTo, $folio, $currency, $mode,
            $request, $user, $description, $chargePercent, &$createdBills
        ) {
            foreach ($roomsToPost as $targetRoom) {
                $primaryGuest = $targetRoom->guests()->where('is_primary', 1)->with('guest')->first()
                                ?: $targetRoom->guests()->with('guest')->first();
                $guestId   = $primaryGuest?->guest_id;
                $guestName = $primaryGuest?->guest?->full_name ?: ($booking?->booking_name ?: 'Khách lẻ');
                $sendRoomRateToMaster = (bool) ($booking?->is_master_room_rate);
                $currentGuestName = $sendRoomRateToMaster
                    ? ($booking?->booking_name ?: 'Khách lẻ')
                    : $guestName;

                $current = $dateFrom->copy()->startOfDay();
                $roomArrival = Carbon::parse($targetRoom->arrival_date)->startOfDay();
                if ($isBookingPost && $current->lt($roomArrival)) {
                    $current = $roomArrival->copy();
                }

                while ($current->lte($dateTo)) {
                    // Xác định giá phòng theo mode
                    if ($mode === 'auto') {
                        // Tự động: ưu tiên giá dịch vụ tự động (booking_room_services service_code=RM)
                        $rmService = BookingRoomService::where('booking_room_id', $targetRoom->id)
                            ->where('service_code', BookingRoomService::CODE_ROOM)
                            ->where('service_date', $current->toDateString())
                            ->first();

                        $rate = 0;
                        if ($rmService && (float)$rmService->rate > 0) {
                            $rate = (float)$rmService->rate;
                        } elseif ((float)$targetRoom->rate > 0) {
                            $rate = (float)$targetRoom->rate;
                        } elseif ((float)$targetRoom->base_price > 0) {
                            $rate = (float)$targetRoom->base_price;
                        }

                        // Tra cứu theo mã giá phòng (rate_code) và loại phòng (room_class_id) nếu giá bằng 0
                        if ($rate <= 0 && !empty($targetRoom->rate_code)) {
                            $plan = \App\Models\RoomRatePlan::where('RateCode', $targetRoom->rate_code)->first();
                            if ($plan && is_array($plan->Period)) {
                                foreach ($plan->Period as $row) {
                                    if (isset($row['roomClassId']) && (string)$row['roomClassId'] === (string)$targetRoom->room_class_id) {
                                        $rate = (float)($row['price'] ?? 0);
                                        if ($rate > 0) break;
                                    }
                                }
                            }
                        }

                        // Tra cứu theo giá chuẩn (StandardRate) của loại phòng nếu vẫn bằng 0
                        if ($rate <= 0 && !empty($targetRoom->room_class_id)) {
                            $stdRate = \App\Models\StandardRate::where('room_class_id', $targetRoom->room_class_id)->value('room_price');
                            if ($stdRate && (float)$stdRate > 0) {
                                $rate = (float)$stdRate;
                            }
                        }
                    } else { // update hoặc surcharge
                        $rate = (float)$request->rate;
                    }

                    if ($mode === 'auto') $rate = round($rate * $chargePercent / 100, 2);

                    $isRoomNight = ($mode === 'surcharge') ? 0 : 1;
                    $totalAmount = $rate;

                    // Lấy giá ăn sáng (chỉ tách ăn sáng khi mode === 'auto' và có ăn sáng)
                    $breakfastAmount = 0;
                    if ($mode === 'auto' && $targetRoom->breakfast) {
                        $breakfastRate   = (float)($setting?->breakfast_adult_rate ?? 0);
                        $breakfastAmount = round($breakfastRate * max(1, (int)$targetRoom->adults) * $chargePercent / 100, 2);
                    }

                    $targetDesc = $description;
                    if ($targetRoom && $targetRoom->room_number && !str_contains($targetDesc, (string)$targetRoom->room_number)) {
                        $targetDesc = trim($targetDesc . ' ' . $targetRoom->room_number);
                    }

                    if ($mode === 'auto') {
                        $existingBill = ServiceBill::where('RegisterId1', $booking?->id)
                            ->where('RentalRoomId1', $targetRoom->id)
                            ->where('ServiceId', 'RM')
                            ->whereDate('Date', $current->toDateString())
                            ->where('Edit', 0)
                            ->first();
                        if ($existingBill) {
                            // Phòng đã có tiền phòng cho ngày này -> Bỏ qua không thêm lại
                            $current->addDay();
                            continue;
                        }
                    }

                    if ($mode === 'update') {
                        $existingBill = ServiceBill::where('RegisterId1', $booking?->id)
                            ->where('RentalRoomId1', $targetRoom->id)
                            ->where('ServiceId', 'RM')
                            ->whereDate('Date', $current->toDateString())
                            ->where('Edit', 0)
                            ->first();
                        if ($existingBill) {
                            $existingBill->update([
                                'Amount'             => $totalAmount,
                                'DescriptionServive' => $targetDesc,
                                'Folio'              => (string)$folio,
                                'Guest'              => $currentGuestName,
                                'RegisterID2'        => $booking?->id,
                                'RentalRoomId2'      => $targetRoom->id,
                                'CustomerId2'        => $guestId,
                                'CompanyId2'         => $booking?->company_id,
                                'Username'           => $user,
                            ]);
                            $bill = $existingBill;
                        } else {
                            $bill = ServiceBill::create([
                                'Date'               => $current->startOfDay()->toDateTimeString(),
                                'OpenTime'           => now()->format('H:i'),
                                'Guest'              => $currentGuestName,
                                'DepartmentId'       => 'FO',
                                'ServiceId'          => 'RM',
                                'DescriptionServive' => $targetDesc,
                                'Quantity'           => 1,
                                'Amount'             => $totalAmount,
                                'ServiceCharge'      => 0,
                                'SpecialTax'         => 0,
                                'Tax'                => 0,
                                'Currency'           => $currency,
                                'Exchange'           => 1,
                                'Edit'               => 0,
                                'Folio'              => (string)$folio,
                                'RegisterId1'        => $booking?->id,
                                'RentalRoomId1'      => $targetRoom->id,
                                'CustomerId1'        => $guestId,
                                'CompanyId1'         => $booking?->company_id,
                                'RegisterID2'        => $booking?->id,
                                'RentalRoomId2'      => $targetRoom->id,
                                'CustomerId2'        => $guestId,
                                'CompanyId2'         => $booking?->company_id,
                                'Username'           => $user,
                                'Status'             => 1,
                                'Outlet'             => 'FO',
                                'Year'               => $current->year,
                                'Month'              => $current->month,
                                'Day'                => $current->day,
                                'CreatedUser'        => $user,
                                'CreatedDate'        => now(),
                                'CreatedHour'        => now()->format('H:i'),
                            ]);
                        }
                    } else {
                        $bill = ServiceBill::create([
                            'Date'               => $current->startOfDay()->toDateTimeString(),
                            'OpenTime'           => now()->format('H:i'),
                            'Guest'              => $currentGuestName,
                            'DepartmentId'       => 'FO',
                            'ServiceId'          => 'RM',
                            'DescriptionServive' => $targetDesc,
                            'Quantity'           => 1,
                            'Amount'             => $totalAmount,
                            'ServiceCharge'      => 0,
                            'SpecialTax'         => 0,
                            'Tax'                => 0,
                            'Currency'           => $currency,
                            'Exchange'           => 1,
                            'Edit'               => 0,
                            'Folio'              => (string)$folio,
                            'RegisterId1'        => $booking?->id,
                            'RentalRoomId1'      => $targetRoom->id,
                            'CustomerId1'        => $guestId,
                            'CompanyId1'         => $booking?->company_id,
                            'RegisterID2'        => $booking?->id,
                            'RentalRoomId2'      => $targetRoom->id,
                            'CustomerId2'        => $guestId,
                            'CompanyId2'         => $booking?->company_id,
                            'Username'           => $user,
                            'Status'             => 1,
                            'Outlet'             => 'FO',
                            'Year'               => $current->year,
                            'Month'              => $current->month,
                            'Day'                => $current->day,
                            'CreatedUser'        => $user,
                            'CreatedDate'        => now(),
                            'CreatedHour'        => now()->format('H:i'),
                        ]);
                    }

                // Insert Sp3001 (service_bill_details)
                // Nghiệp vụ: Nếu mode == 'auto' và phòng có ăn sáng (breakfast=true) & breakfastAmount > 0:
                // Insert 3 dòng:
                // 1. Dòng RM: Amount = totalAmount
                // 2. Dòng BF: Amount = breakfastAmount
                // 3. Dòng RM trừ: Amount = -breakfastAmount
                if ($mode === 'auto' && $targetRoom->breakfast && $breakfastAmount > 0) {
                    // Dòng 1: RM (Tiền phòng gốc)
                    ServiceBillDetail::create([
                        'BillServiceId'            => $bill->Ma,
                        'Ma'                       => 1,
                        'DepartmentId'             => 'FO',
                        'ServiceId'                => 'RM',
                        'DescriptionServive'       => $description,
                        'OriginalRate'             => $rate,
                        'ServiceCharge'            => 0,
                        'SpecialTax'               => 0,
                        'Tax'                      => 0,
                        'Amount'                   => $totalAmount,
                        'Currency'                 => $currency,
                        'Exchange'                 => 1,
                        'DetailBillOriginalAmount' => $rate,
                        'DiscountAmount'           => 0,
                        'IncreaseAmount'           => 0,
                    ]);

                    // Dòng 2: BF (Tiền ăn sáng)
                    ServiceBillDetail::create([
                        'BillServiceId'            => $bill->Ma,
                        'Ma'                       => 2,
                        'DepartmentId'             => 'FO',
                        'ServiceId'                => 'BF',
                        'DescriptionServive'       => 'Tiền ăn sáng',
                        'OriginalRate'             => $breakfastAmount,
                        'ServiceCharge'            => 0,
                        'SpecialTax'               => 0,
                        'Tax'                      => 0,
                        'Amount'                   => $breakfastAmount,
                        'Currency'                 => $currency,
                        'Exchange'                 => 1,
                        'DetailBillOriginalAmount' => $breakfastAmount,
                        'DiscountAmount'           => 0,
                        'IncreaseAmount'           => 0,
                    ]);

                    // Dòng 3: RM trừ (Trừ tiền ăn sáng trong tiền phòng)
                    ServiceBillDetail::create([
                        'BillServiceId'            => $bill->Ma,
                        'Ma'                       => 3,
                        'DepartmentId'             => 'FO',
                        'ServiceId'                => 'RM',
                        'DescriptionServive'       => 'Trừ tiền ăn sáng',
                        'OriginalRate'             => -$breakfastAmount,
                        'ServiceCharge'            => 0,
                        'SpecialTax'               => 0,
                        'Tax'                      => 0,
                        'Amount'                   => -$breakfastAmount,
                        'Currency'                 => $currency,
                        'Exchange'                 => 1,
                        'DetailBillOriginalAmount' => -$breakfastAmount,
                        'DiscountAmount'           => 0,
                        'IncreaseAmount'           => 0,
                    ]);
                } else {
                    // Mode 'update' hoặc 'surcharge' hoặc không có ăn sáng: Chỉ 1 dòng RM
                    ServiceBillDetail::create([
                        'BillServiceId'            => $bill->Ma,
                        'Ma'                       => 1,
                        'DepartmentId'             => 'FO',
                        'ServiceId'                => 'RM',
                        'DescriptionServive'       => $description,
                        'OriginalRate'             => $rate,
                        'ServiceCharge'            => 0,
                        'SpecialTax'               => 0,
                        'Tax'                      => 0,
                        'Amount'                   => $totalAmount,
                        'Currency'                 => $currency,
                        'Exchange'                 => 1,
                        'DetailBillOriginalAmount' => $rate,
                        'DiscountAmount'           => 0,
                        'IncreaseAmount'           => 0,
                    ]);
                }

                // Insert room_night_bills (SP3004)
                // Mode 'surcharge' -> IsRoomNight = 0
                // Mode 'auto' hoặc 'update' -> IsRoomNight = 1
                RoomNightBill::create([
                    'bill_id'          => $bill->Ma,
                    'adult'            => max(1, (int)$targetRoom->adults),
                    'child'            => (int)$targetRoom->children_qty,
                    'is_room_night'    => $isRoomNight,
                    'breakfast_amount' => $breakfastAmount,
                    'extrabed_amount'  => (float)($targetRoom->extra_bed_rate ?? 0) * (int)($targetRoom->extra_bed_qty ?? 0),
                    'date'             => $current->toDateString(),
                    'room'             => $targetRoom->room_number,
                    'room_type_id'     => $targetRoom->room_class_id,
                    'breakfast'        => $targetRoom->breakfast ? max(1, (int)$targetRoom->adults) : 0,
                    'extra_bed'        => (int)($targetRoom->extra_bed_qty ?? 0),
                    'rate_code'        => $targetRoom->rate_code,
                    'rate'             => $rate,
                ]);

                // Lưu vào booking_room_services
                // Bills sent to Master render from service_bills. Keeping a room row
                // would make Checkout count the same room charge twice.
                if ($sendRoomRateToMaster) {
                    BookingRoomService::where('booking_room_id', $targetRoom->id)
                        ->where('service_bill_id', $bill->Ma)
                        ->delete();
                } elseif ($mode === 'surcharge') {
                    // Bổ sung tiền phòng: Tạo mới dịch vụ cộng thêm độc lập
                    BookingRoomService::create([
                        'booking_room_id' => $targetRoom->id,
                        'guest_id'        => $guestId,
                        'service_bill_id' => $bill->Ma,
                        'service_bill_detail_no' => 1,
                        'service_code'    => 'RMS',
                        'service_name'    => 'Bổ sung tiền phòng',
                        'service_date'    => $current->toDateString(),
                        'quantity'        => 1,
                        'rate'            => $rate,
                        'total_amount'    => $totalAmount,
                        'department'      => 'FO',
                        'note'            => $targetDesc,
                        'unit'            => 'Lần',
                        'folio'           => $folio,
                        'is_room'         => 0,
                        'is_posted'       => 1,
                        'posted_at'       => now(),
                        'created_by'      => $user,
                    ]);
                } else {
                    // Cập nhật / Tự động: Cập nhật tiền phòng chuẩn của ngày đó
                    BookingRoomService::updateOrCreate(
                        [
                            'booking_room_id' => $targetRoom->id,
                            'service_code'    => 'RM',
                            'service_date'    => $current->toDateString(),
                        ],
                        [
                            'service_name'   => 'Tiền phòng',
                            'service_bill_id' => $bill->Ma,
                            'service_bill_detail_no' => 1,
                            'quantity'       => 1,
                            'rate'           => $rate,
                            'total_amount'   => $totalAmount,
                            'department'     => 'FO',
                            'note'           => $targetDesc,
                            'tax'            => 0,
                            'service_charge' => 0,
                            'unit'           => 'Đêm',
                            'folio'          => $folio,
                            'is_room'        => 1,
                            'is_posted'      => 1,
                            'posted_at'      => now(),
                            'created_by'     => $user,
                        ]
                    );
                }

                $createdBills[] = $bill->Ma;
                $current->addDay();
            }
            }
        });

        return response()->json([
            'success'  => true,
            'message'  => 'Đã post ' . count($createdBills) . ' tiền phòng thành công.',
            'bill_ids' => $createdBills,
        ]);
    }

    protected function canOperateOldDay(): bool
    {
        $user = Auth::user();
        if (!$user) return true;
        $username = strtolower((string)($user->username ?? ''));
        if (in_array($username, ['admin', 'system'], true) || !empty($user->is_admin)) return true;
        $settings = $user->setting?->settings ?? [];
        if (!isset($settings['RuleUserCorrectOrPostBillPaymentOldDay'])) return true;
        $value = $settings['RuleUserCorrectOrPostBillPaymentOldDay'];
        return $value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'default';
    }
}
