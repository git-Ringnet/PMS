<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingRoomService;
use App\Models\HotelService;
use App\Models\HotelSetting;
use App\Models\HousekeepingServiceBill;
use App\Models\HousekeepingServiceBillDetail;
use App\Models\ServiceBill;
use App\Models\ServiceBillDetail;
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
                  ->orWhere('department', 'like', 'Reception%')
                  ->orWhere('department', 'like', 'Lễ tân%');
            })
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
            if ($services->contains(fn (BookingRoomService $service) => $service->service_code === BookingRoomService::CODE_ROOM)) {
                abort(422, 'Không được xóa dịch vụ tiền phòng.');
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
        $request->validate(['bill_ids' => 'required|array|min:1', 'bill_ids.*' => 'integer']);
        $isMaster = str_starts_with((string) $roomId, 'master-');
        $targetRoom = $isMaster ? null : BookingRoom::with('guests.guest', 'booking')->findOrFail($roomId);
        $targetBooking = $isMaster ? Booking::findOrFail((int) substr((string) $roomId, 7)) : $targetRoom->booking;
        if (!$isMaster && !in_array((int) $targetRoom->status, [BookingRoom::STATUS_BOOKED, BookingRoom::STATUS_CHECKED_IN], true)) abort(422, 'Phòng nhận phải ở trạng thái Reservation hoặc Inhouse.');
        $targetGuest = $targetRoom?->guests->firstWhere('is_primary', 1);

        DB::transaction(function () use ($request, $targetRoom, $targetBooking, $targetGuest) {
            foreach (array_unique($request->bill_ids) as $billId) {
                $bill = ServiceBill::lockForUpdate()->findOrFail($billId);
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
                $positive->Folio = '1'; $positive->DescriptionServive = trim(($bill->DescriptionServive ?: $bill->ServiceId) . ' (' . $sourceLocation . '=>' . $targetLocation . ')');
                $positive->Edit = 0; $positive->Status = 1; $positive->Pack1 = null; $positive->UpdatedDate = now(); $positive->save();
                $negative = $bill->replicate(); $negative->Amount = -abs((float) $bill->Amount); $negative->Edit = 1; $negative->Status = 4; $negative->Pack1 = (string) $positive->Ma; $negative->UpdatedDate = now(); $negative->save();
                $bill->Edit = 1; $bill->Status = 4; $bill->UpdatedDate = now(); $bill->save();
                $details = ServiceBillDetail::where('BillServiceId', $bill->Ma)->get();
                foreach ($details as $detail) { $p = $detail->replicate(); $p->BillServiceId = $positive->Ma; $p->save(); $n = $detail->replicate(); $n->BillServiceId = $negative->Ma; $n->Amount = -abs((float) $detail->Amount); $n->save(); }
                $services->each(function (BookingRoomService $service) use ($targetRoom, $targetGuest, $positive) { $copy = $service->replicate(); $service->delete(); if (!$targetRoom) return; $copy->booking_room_id = $targetRoom->id; $copy->guest_id = $targetGuest?->guest_id; $copy->service_bill_id = $positive->Ma; $copy->folio = 1; $copy->note = $positive->DescriptionServive; $copy->posted_at = $service->posted_at; $copy->created_at = $service->created_at; $copy->deleted_at = null; $copy->save(); });
                if ($isMasterSource && $targetRoom) {
                    foreach ($details as $detail) {
                        $roomService = new BookingRoomService([
                            'booking_room_id' => $targetRoom->id, 'guest_id' => $targetGuest?->guest_id,
                            'service_bill_id' => $positive->Ma, 'service_bill_detail_no' => $detail->Ma,
                            'service_code' => $positive->Outlet ?: ($detail->ServiceId ?: 'DV'),
                            'service_name' => $detail->DescriptionServive ?: $positive->DescriptionServive,
                            'service_date' => $positive->Date, 'quantity' => 1,
                            'rate' => abs((float) $detail->Amount), 'total_amount' => abs((float) $detail->Amount),
                            'department' => $positive->DepartmentId, 'note' => $positive->DescriptionServive,
                            'tax' => $detail->Tax, 'service_charge' => $detail->ServiceCharge,
                            'unit' => $positive->Currency ?: 'VND', 'folio' => 1,
                            'is_room' => 0, 'is_posted' => 1, 'posted_at' => now(),
                            'created_by' => auth()->user()?->username ?: 'system', 'updated_by' => auth()->user()?->username ?: 'system',
                        ]);
                        $roomService->preserveTotalAmount = true;
                        $roomService->created_at = $originCreatedAt;
                        $roomService->posted_at = $originCreatedAt;
                        $roomService->save();
                    }
                }
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
            $room = BookingRoom::findOrFail($roomId);

            DB::transaction(function () use ($validated, $room) {
                $services = BookingRoomService::where('booking_room_id', $room->id)
                    ->whereIn('id', $validated['service_ids'])
                    ->lockForUpdate()
                    ->get();
                if ($services->count() !== count(array_unique($validated['service_ids']))) {
                    abort(422, 'Có dịch vụ không thuộc phòng đã chọn.');
                }

                $services->each(function (BookingRoomService $service) use ($validated) {
                    $service->folio = $validated['folio'];
                    $service->save();
                });

                foreach ($services->groupBy('service_bill_id') as $billId => $billServices) {
                    if (!$billId) continue;
                    $allBillServices = BookingRoomService::where('booking_room_id', $room->id)
                        ->where('service_bill_id', $billId)
                        ->lockForUpdate()
                        ->get();
                    if ($allBillServices->count() === $billServices->count()) {
                        ServiceBill::whereKey($billId)->update(['Folio' => (string) $validated['folio']]);
                    }
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
            if ($services->contains(fn ($service) => $service->service_code === BookingRoomService::CODE_ROOM)) abort(422, 'Không được chuyển dịch vụ tiền phòng.');
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
                $positive->DescriptionServive = trim(($bill->DescriptionServive ?: $bill->ServiceId) . " ({$sourceLocation}=>{$targetLocation})");
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
                    $targetService->note = $positive->DescriptionServive;
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

    protected function canOperateOldDay(): bool
    {
        $user = Auth::user();
        if (!$user || $user->username === 'admin' || !empty($user->is_admin)) return true;
        $settings = $user->setting?->settings ?? [];
        $value = $settings['RuleUserCorrectOrPostBillPaymentOldDay'] ?? false;
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }
}
