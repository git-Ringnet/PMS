<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

    // =========================================
    // GET: Lấy giá Extra Bed mặc định từ hotel_settings (Epic 14)
    // GET /booking-rooms/{roomId}/services/extra-bed-rate
    // =========================================
    public function transferFolio(Request $request, $roomId)
    {
        BookingRoom::findOrFail($roomId);

        $request->validate([
            'service_ids'   => 'required|array|min:1',
            'service_ids.*' => 'integer',
            'folio'         => 'required|integer|between:1,3',
        ]);

        $services = BookingRoomService::where('booking_room_id', $roomId)
            ->whereIn('id', $request->service_ids)
            ->get();

        if ($services->count() !== count(array_unique($request->service_ids))) {
            return response()->json(['success' => false, 'message' => 'Có dịch vụ không thuộc phòng đã chọn.'], 422);
        }

        if ($services->contains(fn ($service) => $service->is_posted == 1)) {
            return response()->json(['success' => false, 'message' => 'Không thể chuyển dịch vụ đã post.'], 422);
        }

        BookingRoomService::whereIn('id', $services->pluck('id'))
            ->update([
                'folio'      => $request->folio,
                'updated_by' => Auth::user()?->username ?? 'system',
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã chuyển dịch vụ sang Folio ' . $request->folio . '.',
        ]);
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

        DB::transaction(function () use ($request, $room, $department, $serviceDate, $serviceDateCarbon, $folio, $user, &$createdRecords) {
            $groupMeta = [
                'minibar' => ['label' => 'Minibar', 'service' => 'MB', 'outlet' => 'MB'],
                'giatui'  => ['label' => 'Giặt ủi', 'service' => 'LA', 'outlet' => 'LA'],
                'dengbu'  => ['label' => 'Hàng đền bù', 'service' => 'BR', 'outlet' => 'BR'],
            ];
            $booking = $room->booking;
            $primaryGuest = $room->guests()->where('is_primary', 1)->with('guest')->first() ?: $room->guests()->with('guest')->first();
            $guestId = $primaryGuest?->guest_id;
            $guestName = $primaryGuest?->guest?->full_name ?: ($booking?->booking_name ?: 'Khách lẻ');

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
                    'BookingId' => $booking?->id, 'BillOriginalAmount' => $originalAmount,
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
                        ->first();

                    if ($existingBrs) {
                        $newQty = (float)$existingBrs->quantity + $qty;
                        $existingBrs->update([
                            'quantity'     => $newQty,
                            'total_amount' => $newQty * $rate,
                            'note'         => $description,
                        ]);
                    } else {
                        BookingRoomService::create([
                            'booking_room_id' => $room->id,
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
    public function postRoomCharge(Request $request)
    {
        $request->validate([
            'booking_room_id' => 'required_without:booking_id|nullable|string',
            'booking_id'      => 'nullable',
            'date_from'       => 'required|date',
            'date_to'         => 'required|date|after_or_equal:date_from',
            'mode'            => 'required|in:auto,update,surcharge',
            'rate'            => 'nullable|numeric|min:0',
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

        $roomsToPost = $room ? collect([$room]) : ($booking ? $booking->bookingRooms : collect());
        if ($roomsToPost->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Không có phòng nào để post tiền phòng.'], 422);
        }

        $systemDate = $this->avService->getSystemDate();
        $dateFrom   = Carbon::parse($request->date_from);
        $dateTo     = Carbon::parse($request->date_to);

        if ($dateFrom->lt(Carbon::parse($systemDate)) && !$this->canOperateOldDay()) {
            return response()->json(['success' => false, 'message' => 'Không có quyền post bill cho ngày cũ.'], 403);
        }

        $folio    = $request->folio ?? 1;
        $currency = $request->currency ?? 'VND';
        $mode     = $request->mode; // 'auto' | 'update' | 'surcharge'
        $user     = Auth::user()?->username ?? 'system';
        $description = $request->description ?: 'Dịch vụ phòng nghỉ';

        $setting = HotelSetting::first();
        $createdBills = [];

        DB::transaction(function () use (
            $roomsToPost, $booking, $setting,
            $dateFrom, $dateTo, $folio, $currency, $mode,
            $request, $user, $description, &$createdBills
        ) {
            foreach ($roomsToPost as $targetRoom) {
                $primaryGuest = $targetRoom->guests()->where('is_primary', 1)->with('guest')->first()
                                ?: $targetRoom->guests()->with('guest')->first();
                $guestId   = $primaryGuest?->guest_id;
                $guestName = $primaryGuest?->guest?->full_name ?: ($booking?->booking_name ?: 'Khách lẻ');

                $current = $dateFrom->copy();

                while ($current->lte($dateTo)) {
                    // Xác định giá phòng theo mode
                    if ($mode === 'auto') {
                        // Tự động: ưu tiên giá dịch vụ tự động (booking_room_services service_code=RM)
                        $rmService = BookingRoomService::where('booking_room_id', $targetRoom->id)
                            ->where('service_code', BookingRoomService::CODE_ROOM)
                            ->where('service_date', $current->toDateString())
                            ->first();
                        $rate = $rmService ? (float)$rmService->rate : (float)$targetRoom->rate;
                    } else { // update hoặc surcharge
                        $rate = (float)$request->rate;
                    }

                    $isRoomNight = ($mode === 'surcharge') ? 0 : 1;
                    $totalAmount = $rate;

                    // Lấy giá ăn sáng (chỉ tách ăn sáng khi mode === 'auto' và có ăn sáng)
                    $breakfastAmount = 0;
                    if ($mode === 'auto' && $targetRoom->breakfast) {
                        $breakfastRate   = (float)($setting?->breakfast_adult_rate ?? 0);
                        $breakfastAmount = $breakfastRate * max(1, (int)$targetRoom->adults);
                    }

                $bill = ServiceBill::create([
                    'Date'               => $current->startOfDay()->toDateTimeString(),
                    'OpenTime'           => now()->format('H:i'),
                    'Guest'              => $guestName,
                    'DepartmentId'       => 'FO',
                    'ServiceId'          => 'RM',
                    'DescriptionServive' => $description,
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
                BookingRoomService::updateOrCreate(
                    [
                        'booking_room_id' => $targetRoom->id,
                        'service_code'    => 'RM',
                        'service_date'    => $current->toDateString(),
                    ],
                    [
                        'service_name'   => 'Tiền phòng',
                        'quantity'       => 1,
                        'rate'           => $rate,
                        'total_amount'   => $totalAmount,
                        'department'     => 'FO',
                        'note'           => $description,
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
        if (!$user || $user->username === 'admin' || !empty($user->is_admin)) return true;
        $settings = $user->setting?->settings ?? [];
        $value = $settings['RuleUserCorrectOrPostBillPaymentOldDay'] ?? false;
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }
}
