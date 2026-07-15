<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingChild;
use App\Models\BookingRoomGuest;
use App\Models\BookingChildBreakfastDetail;
use App\Models\Guest;
use App\Models\HotelConfig;
use App\Models\HotelSetting;
use App\Models\Payment;
use App\Models\RegistrationStatus;
use App\Models\SystemDateRoll;
use App\Services\RoomAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    /**
     * Lấy danh sách booking (có filter).
     */
    public function index(Request $request)
    {
        $query = Booking::with([
            'registrationStatus',
            'company',
            'market',
            'customerSource',
            'branch',
            'booker',
            'paymentMethod',
            'bookingRooms.roomClass',
            'bookingRooms.room',
            'bookingRooms.guests.guest',
            'bookingRooms.children',
            'bookingRooms.services',
            'bookingRooms.specialRequests.specialRequest',
            'payments.paymentMethod',
        ]);

        // Filter theo ngày đến
        if ($request->arrival_date) {
            $query->whereDate('arrival_date', $request->arrival_date);
        }

        // Filter theo khoảng ngày
        if ($request->from_date && $request->to_date) {
            $query->where(function ($q) use ($request) {
                $q->whereBetween('arrival_date', [$request->from_date, $request->to_date])
                  ->orWhereBetween('departure_date', [$request->from_date, $request->to_date]);
            });
        }

        // Filter theo tên đăng ký hoặc ID (mã BK)
        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('booking_name', 'like', '%' . $search . '%')
                  ->orWhere('contact_name', 'like', '%' . $search . '%');
                
                $cleanId = preg_replace('/[^0-9]/', '', $search);
                if (!empty($cleanId)) {
                    $q->orWhere('id', $cleanId);
                }
            });
        }

        // Filter theo tình trạng phòng/booking
        if ($request->has('status')) {
            $statusVal = $request->status;
            if (is_array($statusVal)) {
                $query->whereIn('status', $statusVal);
            } elseif (is_string($statusVal) && str_contains($statusVal, ',')) {
                $query->whereIn('status', explode(',', $statusVal));
            } else {
                $query->where('status', $statusVal);
            }
        }

        // Filter theo tình trạng booking
        if ($request->registration_status_id) {
            $regStatusVal = $request->registration_status_id;
            if (is_array($regStatusVal)) {
                $query->whereIn('registration_status_id', $regStatusVal);
            } elseif (is_string($regStatusVal) && str_contains($regStatusVal, ',')) {
                $query->whereIn('registration_status_id', explode(',', $regStatusVal));
            } else {
                $query->where('registration_status_id', $regStatusVal);
            }
        }

        $bookings = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data'    => $bookings,
        ]);
    }

    /**
     * Tạo booking mới.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'booking_name'             => 'required|string|max:255',
            'arrival_date'             => 'required|date',
            'departure_date'           => 'required|date|after:arrival_date',
            'num_of_days'              => 'required|integer|min:1',
            'confirm_date'             => 'nullable|date',
            'expired_date'             => 'nullable|date',
            'arrival_flight'           => 'nullable|string|max:50',
            'arrival_flight_date'      => 'nullable|date',
            'departure_flight'         => 'nullable|string|max:50',
            'departure_flight_date'    => 'nullable|date',
            'status'                   => 'nullable|integer',
            'registration_status_id'   => 'nullable|exists:registration_statuses,id',
            'color'                    => 'nullable|string|max:20',
            'is_git'                   => 'nullable|boolean',
            'is_day_use'               => 'nullable|boolean',
            'breakfast_included'       => 'nullable|boolean',
            'has_vat'                  => 'nullable|boolean',
            'company_id'               => 'nullable|exists:companies,id',
            'market_id'                => 'nullable|exists:markets,id',
            'customer_source_id'       => 'nullable|exists:customer_sources,id',
            'branch_id'                => 'nullable|exists:branches,id',
            'booker_id'                => 'nullable|exists:bookers,id',
            'contact_name'             => 'nullable|string|max:255',
            'contact_email'            => 'nullable|email|max:255',
            'contact_phone'            => 'nullable|string|max:50',
            'payment_method_id'        => 'nullable|exists:payment_methods,id',
            'payment_value'            => 'nullable|numeric|min:0',
            'card_no'                  => 'nullable|string|max:30',
            'card_holder'              => 'nullable|string|max:100',
            'card_cvv'                 => 'nullable|string|max:10',
            'card_expired'             => 'nullable|string|max:10',
            'commission'               => 'nullable|numeric|min:0',
            'voucher_info'             => 'nullable|string|max:255',
            'external_booking_code'    => 'nullable|string|max:100',
            'event_code'               => 'nullable|string|max:50',
            'note'                     => 'nullable|string',
            'special_requests'         => 'nullable|string',
            'sales_person'             => 'nullable|string|max:100',
            'module'                   => 'nullable|string|max:50',
            'shuttle_info'             => 'nullable|array',
            'deposit_details'          => 'nullable|array',
        ]);

        // Lấy ngày hệ thống
        $systemDate = SystemDateRoll::latest('id')->first();
        $sysDateStr = $systemDate
            ? Carbon::parse($systemDate->system_date)->toDateString()
            : now()->toDateString();

        // Validate: arrival_date >= system_date (Epic 1 — backend guard)
        if ($validated['arrival_date'] < $sysDateStr) {
            return response()->json([
                'success' => false,
                'message' => 'Ngày đến không được nhỏ hơn ngày hệ thống (' . $sysDateStr . ').',
                'errors'  => ['arrival_date' => ['Ngày đến phải >= ngày hệ thống: ' . $sysDateStr]],
            ], 422);
        }

        // Tiền phòng gửi về master luôn luôn bật (is_git = 1)
        $validated['is_git'] = true;

        // Default màu BK theo ColorDefaultBookingRoomMap
        if (empty($validated['color']) || $validated['color'] === '#000000') {
            $colorConfig = \App\Models\HotelConfig::where('name', 'ColorDefaultBookingRoomMap')->first();
            $validated['color'] = $colorConfig ? $colorConfig->value : '#97D5FF';
        }

        // Lấy ngày hệ thống từ system_date_rolls (ngày nghiệp vụ hiện tại)
        $validated['booking_date'] = $sysDateStr;

        // Giờ tạo booking
        $validated['booking_time'] = now()->format('H:i:s');

        // Tự động tính Confirm Date nếu chưa có
        if (empty($validated['confirm_date'])) {
            if (!empty($validated['registration_status_id'])) {
                $statusModel = \App\Models\RegistrationStatus::find($validated['registration_status_id']);
                if ($statusModel) {
                    $statusNameLower = strtolower($statusModel->name ?? '');
                    $isDefinite = str_contains($statusNameLower, 'guaranteed') && 
                                  !str_contains($statusNameLower, 'none') && 
                                  !str_contains($statusNameLower, 'non');

                    $days = $statusModel->confirmation_days ?? 0;
                    if ($isDefinite) {
                        // Chắc chắn: mặc định bằng ngày tạo bk (system_date)
                        $validated['confirm_date'] = $sysDateStr;
                    } else {
                        // Không chắc chắn: ngày lưu trú - ngày xác nhận định nghĩa
                        $calcDate = Carbon::parse($validated['arrival_date'])->subDays($days)->toDateString();
                        if ($calcDate > Carbon::parse($validated['arrival_date'])->toDateString()) {
                            $validated['confirm_date'] = Carbon::parse($validated['arrival_date'])->toDateString();
                        } else {
                            $validated['confirm_date'] = $calcDate;
                        }
                    }
                } else {
                    $validated['confirm_date'] = Carbon::parse($validated['arrival_date'])->toDateString();
                }
            } else {
                $validated['confirm_date'] = Carbon::parse($validated['arrival_date'])->toDateString();
            }
        }

        // Người tạo
        $validated['created_by'] = Auth::user()?->username ?? 'system';

        // Tình trạng mặc định = Reservation
        $validated['status'] = $validated['status'] ?? Booking::STATUS_RESERVATION;

        // Kiểm tra is_availability = 0 → trả cảnh báo cho UI hiển thị popup
        $isAvailabilityWarning = false;
        if (!empty($validated['registration_status_id'])) {
            $regStatus = RegistrationStatus::find($validated['registration_status_id']);
            if ($regStatus && !$regStatus->is_availability) {
                $isAvailabilityWarning = true;
            }
        }

        try {
            $booking = \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $request) {
                // 1. Validate room allocations trước khi tạo booking
                if ($request->has('room_allocations') && is_array($request->room_allocations)) {
                    $this->validateRoomAllocations(
                        $request->room_allocations,
                        $validated['arrival_date'],
                        $validated['departure_date']
                    );
                }

                $booking = Booking::create($validated);

                // 2. Tạo room bookings nếu validate qua
                if ($request->has('room_allocations') && is_array($request->room_allocations)) {
                    foreach ($request->room_allocations as $alloc) {
                        $qty = (int)($alloc['quantity'] ?? 0);
                        if ($qty <= 0) continue;
                        
                        $details = $alloc['rooms'] ?? [];
                        
                        for ($i = 0; $i < $qty; $i++) {
                            $detail = $details[$i] ?? [];
                            
                            $bRoom = \App\Models\BookingRoom::create([
                                'booking_id' => $booking->id,
                                'room_number' => $detail['roomNumber'] ?? null,
                                'room_class_id' => $alloc['roomClassId'] ?? null,
                                'original_room_class_id' => $alloc['roomClassId'] ?? null,
                                'arrival_date' => $alloc['arrivalDate'] ?? $alloc['arrival_date'] ?? $validated['arrival_date'],
                                'departure_date' => $alloc['departureDate'] ?? $alloc['departure_date'] ?? $validated['departure_date'],
                                'arrival_time' => $detail['arrivalTime'] ?? null,
                                'departure_time' => $detail['hoursOut'] ?? null,
                                'rate' => $alloc['price'] ?? 0,
                                'rate_code' => $alloc['rateCode'] ?? null,
                                'breakfast' => !empty($alloc['breakfastIncluded']),
                                'discount' => $alloc['discount'] ?? null,
                                'discount_type' => $alloc['discountType'] ?? null,
                                'discount_value' => $alloc['discountValue'] ?? 0,
                                'discount_unit' => $alloc['discountUnit'] ?? null,
                                'base_price' => $alloc['basePrice'] ?? ($alloc['price'] ?? 0),
                                'adults' => $detail['adults'] ?? 2,
                                'extra_bed_qty' => (int)($detail['extraBedQty'] ?? (empty($detail['extraBedPrice']) ? 0 : 1)),
                                'extra_bed_rate' => $detail['extraBedPrice'] ?? 0,
                                'status' => \App\Models\BookingRoom::STATUS_BOOKED,
                            ]);
                            $this->upsertExtraBedServices($bRoom);

                            // Thêm khách chính (guestName)
                            $roomGuestName = trim($detail['guestName'] ?? '');
                            if (empty($roomGuestName)) {
                                $roomGuestName = $booking->booking_name;
                            }
                            $guest = \App\Models\Guest::create([
                                'full_name' => $roomGuestName,
                                'guest_status' => \App\Models\Guest::STATUS_ACTIVE,
                            ]);
                            \App\Models\BookingRoomGuest::create([
                                'booking_room_id' => $bRoom->id,
                                'guest_id' => $guest->id,
                                'is_primary' => 1,
                            ]);

                            // Thêm số lượng children / babies vào booking_children
                            $numChildren = (int)($detail['children'] ?? 0);
                            for ($c = 0; $c < $numChildren; $c++) {
                                $child = \App\Models\BookingChild::create([
                                    'booking_id' => $booking->id,
                                    'booking_room_id' => $bRoom->id,
                                    'full_name' => 'Child ' . ($c+1),
                                    'age_group' => 'child',
                                ]);
                                $this->createChildBreakfastDetails($child, $bRoom);
                            }
                            
                            $numBabies = (int)($detail['babies'] ?? 0);
                            for ($b = 0; $b < $numBabies; $b++) {
                                $baby = \App\Models\BookingChild::create([
                                    'booking_id' => $booking->id,
                                    'booking_room_id' => $bRoom->id,
                                    'full_name' => 'Baby ' . ($b+1),
                                    'age_group' => 'baby',
                                ]);
                                $this->createChildBreakfastDetails($baby, $bRoom);
                            }
                        }
                    }
                }
                // 3. Tạo bản ghi cọc trong bảng payments nếu có
                if ($request->has('deposit_details') && is_array($request->deposit_details)) {
                    foreach ($request->deposit_details as $dep) {
                        if (empty($dep['amount']) || $dep['amount'] <= 0) continue;
                        
                        // Parse date DD/MM/YYYY sang YYYY-MM-DD
                        $paymentDate = now()->toDateString();
                        if (!empty($dep['date'])) {
                            if (str_contains($dep['date'], '/')) {
                                try {
                                    $paymentDate = Carbon::createFromFormat('d/m/Y', $dep['date'])->toDateString();
                                } catch (\Exception $ex) {}
                            } else {
                                $paymentDate = Carbon::parse($dep['date'])->toDateString();
                            }
                        }

                        \App\Models\Payment::create([
                            'booking_id'        => $booking->id,
                            'company_id'        => $booking->company_id,
                            'date'              => $paymentDate,
                            'open_time'         => !empty($dep['time']) ? $dep['time'] . ':00' : now()->format('H:i:s'),
                            'guest_display'     => $booking->booking_code . ' - ' . $booking->booking_name,
                            'description'       => $dep['note'] ?? 'Đặt cọc',
                            'amount'            => $dep['amount'],
                            'pack2'             => \App\Models\Payment::PACK2_DEPOSIT,
                            'payment_method_id' => $dep['paymentMethodId'] ?? $booking->payment_method_id,
                            'status'            => \App\Models\Payment::STATUS_PENDING,
                            'edit_flag'         => 0,
                            'created_by'        => Auth::user()?->username ?? 'system',
                        ]);
                    }

                    // Sync tổng cọc vào payment_value
                    $totalDeposit = \App\Models\Payment::where('booking_id', $booking->id)
                        ->where('pack2', \App\Models\Payment::PACK2_DEPOSIT)
                        ->where('edit_flag', 0)
                        ->sum('amount');
                    $booking->update(['payment_value' => $totalDeposit]);
                }

                return $booking;
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        // Eager load relations cho response
        $booking->load([
            'registrationStatus',
            'company',
            'market',
            'customerSource',
            'branch',
            'booker',
            'paymentMethod',
            'bookingRooms.roomClass',
            'bookingRooms.room',
            'bookingRooms.guests.guest',
            'bookingRooms.children',
            'bookingRooms.services',
            'bookingRooms.specialRequests.specialRequest',
            'payments.paymentMethod',
        ]);

        return response()->json([
            'success'               => true,
            'data'                  => $booking,
            'message'               => 'Tạo đăng ký thành công!',
            'is_availability_warning' => $isAvailabilityWarning,
            'availability_warning_message' => $isAvailabilityWarning
                ? 'Tình trạng đặt phòng này không trừ vào phòng trống thực tế (Room AV). Vui lòng xác nhận để tiếp tục.'
                : null,
        ], 201);
    }

    /**
     * Xem chi tiết một booking.
     */
    public function show($id)
    {
        $booking = Booking::with([
            'registrationStatus',
            'company',
            'market',
            'customerSource',
            'branch',
            'booker',
            'paymentMethod',
            'bookingRooms.roomClass',
            'bookingRooms.room',
            'bookingRooms.guests.guest',
            'bookingRooms.children',
            'bookingRooms.services',
            'bookingRooms.specialRequests.specialRequest',
            'payments.paymentMethod',
        ])->find($id);

        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đăng ký!'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $booking,
        ]);
    }

    /**
     * Cập nhật booking.
     */
    public function update(Request $request, $id)
    {
        $booking = Booking::find($id);
        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đăng ký!'], 404);
        }

        // Không cho sửa booking đã checkout hoặc đã xóa
        if (in_array($booking->status, [Booking::STATUS_CHECKOUT, Booking::STATUS_DELETED])) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể chỉnh sửa đăng ký đã checkout hoặc đã xóa!',
            ], 422);
        }

        $validated = $request->validate([
            'booking_name'             => 'sometimes|required|string|max:255',
            'arrival_date'             => 'sometimes|required|date',
            'departure_date'           => 'sometimes|required|date|after:arrival_date',
            'num_of_days'              => 'sometimes|required|integer|min:1',
            'confirm_date'             => 'nullable|date',
            'expired_date'             => 'nullable|date',
            'arrival_flight'           => 'nullable|string|max:50',
            'arrival_flight_date'      => 'nullable|date',
            'departure_flight'         => 'nullable|string|max:50',
            'departure_flight_date'    => 'nullable|date',
            'status'                   => 'nullable|integer',
            'registration_status_id'   => 'nullable|exists:registration_statuses,id',
            'color'                    => 'nullable|string|max:20',
            'is_git'                   => 'nullable|boolean',
            'is_day_use'               => 'nullable|boolean',
            'breakfast_included'       => 'nullable|boolean',
            'has_vat'                  => 'nullable|boolean',
            'company_id'               => 'nullable|exists:companies,id',
            'market_id'                => 'nullable|exists:markets,id',
            'customer_source_id'       => 'nullable|exists:customer_sources,id',
            'branch_id'                => 'nullable|exists:branches,id',
            'booker_id'                => 'nullable|exists:bookers,id',
            'contact_name'             => 'nullable|string|max:255',
            'contact_email'            => 'nullable|email|max:255',
            'contact_phone'            => 'nullable|string|max:50',
            'payment_method_id'        => 'nullable|exists:payment_methods,id',
            'payment_value'            => 'nullable|numeric|min:0',
            'commission'               => 'nullable|numeric|min:0',
            'voucher_info'             => 'nullable|string|max:255',
            'external_booking_code'    => 'nullable|string|max:100',
            'event_code'               => 'nullable|string|max:50',
            'note'                     => 'nullable|string',
            'special_requests'         => 'nullable|string',
            'sales_person'             => 'nullable|string|max:100',
            'edit_message'             => 'nullable|string',
            'shuttle_info'             => 'nullable|array',
            'deposit_details'          => 'nullable|array',
        ]);

        // Tự động tăng edit_count và ghi nhận người sửa
        $validated['edit_count']  = $booking->edit_count + 1;
        $validated['edit_date']   = now();
        $validated['updated_by']  = Auth::user()?->username ?? 'system';
        
        // Tiền phòng gửi về master luôn luôn bật (is_git = 1)
        $validated['is_git'] = true;

        // Lấy ngày hệ thống
        $systemDate = SystemDateRoll::latest('id')->first();
        $sysDateStr = $systemDate
            ? Carbon::parse($systemDate->system_date)->toDateString()
            : now()->toDateString();

        // Chặn nếu ngày đến mới nhỏ hơn ngày hệ thống
        if (isset($validated['arrival_date']) && $validated['arrival_date'] < $sysDateStr) {
            return response()->json([
                'success' => false,
                'message' => 'Ngày đến không được nhỏ hơn ngày hệ thống (' . $sysDateStr . ').',
                'errors'  => ['arrival_date' => ['Ngày đến phải >= ngày hệ thống: ' . $sysDateStr]],
            ], 422);
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($booking, $validated, $request) {
                $booking->update($validated);

                // Đồng bộ room_allocations (từ UI gửi lên) - xử lý thông minh để cập nhật thay vì xóa/tạo lại
                if ($request->has('room_allocations') && is_array($request->room_allocations)) {
                    // Tìm các phòng đang ở trạng thái BOOKED (chưa check-in) hiện tại trong db
                    $existingBookedRooms = \App\Models\BookingRoom::where('booking_id', $booking->id)
                        ->where('status', \App\Models\BookingRoom::STATUS_BOOKED)
                        ->get();
                    $bookedRoomIds = $existingBookedRooms->pluck('id')->toArray();

                    // 1. Tạm thời xóa mềm tất cả các phòng BOOKED hiện tại để validateAvailability không tính trùng chính nó
                    if (count($bookedRoomIds) > 0) {
                        \App\Models\BookingRoom::whereIn('id', $bookedRoomIds)->delete();
                    }

                    try {
                        // Thực hiện validation dựa trên payload mới
                        $this->validateRoomAllocations(
                            $request->room_allocations,
                            $validated['arrival_date'] ?? $booking->arrival_date->toDateString(),
                            $validated['departure_date'] ?? $booking->departure_date->toDateString()
                        );
                    } catch (\Exception $e) {
                        // Restore lại toàn bộ nếu validate lỗi để không mất dữ liệu của người dùng
                        if (count($bookedRoomIds) > 0) {
                            \App\Models\BookingRoom::withTrashed()->whereIn('id', $bookedRoomIds)->restore();
                        }
                        throw $e;
                    }

                    $restoredRoomIds = [];

                    foreach ($request->room_allocations as $alloc) {
                        $qty = (int)($alloc['quantity'] ?? 0);
                        if ($qty <= 0) continue;
                        
                        $details = $alloc['rooms'] ?? [];
                        
                        for ($i = 0; $i < $qty; $i++) {
                            $detail = $details[$i] ?? [];
                            $bRoomId = $detail['bookingRoomId'] ?? null;
                            
                            $bRoom = null;
                            if (!empty($bRoomId)) {
                                if (in_array($bRoomId, $bookedRoomIds)) {
                                    // Restore phòng từ soft deleted
                                    $bRoom = \App\Models\BookingRoom::onlyTrashed()->find($bRoomId);
                                    if ($bRoom) {
                                        $bRoom->restore();
                                        $restoredRoomIds[] = $bRoomId;
                                    }
                                } else {
                                    // Tìm phòng đang hoạt động (ví dụ phòng CheckedIn)
                                    $bRoom = \App\Models\BookingRoom::find($bRoomId);
                                }
                            }
                            
                            // Kiểm tra chuyển phòng cho phòng đang CheckedIn
                            if ($bRoom && $bRoom->status === \App\Models\BookingRoom::STATUS_CHECKED_IN) {
                                $oldRoomNumber = $bRoom->room_number;
                                $newRoomNumber = $detail['roomNumber'] ?? null;
                                if (!empty($oldRoomNumber) && !empty($newRoomNumber) && $oldRoomNumber !== $newRoomNumber) {
                                    $currentUser = Auth::user()?->username ?? 'system';
                                    $systemDate = $this->avService->getSystemDate();
                                    
                                    // Thực hiện chuyển phòng và cập nhật bRoom trỏ sang phòng mới
                                    $newRoom = $bRoom->moveToRoom($newRoomNumber, $systemDate->toDateString(), $currentUser);
                                    $bRoom = $newRoom;
                                }
                            }
                            
                            $roomData = [
                                'booking_id' => $booking->id,
                                'room_number' => $detail['roomNumber'] ?? null,
                                'room_class_id' => $alloc['roomClassId'] ?? null,
                                'original_room_class_id' => $alloc['roomClassId'] ?? null,
                                'arrival_date' => $validated['arrival_date'] ?? $booking->arrival_date,
                                'departure_date' => $validated['departure_date'] ?? $booking->departure_date,
                                'actual_arrival_date' => $bRoom && $bRoom->actual_arrival_date ? $bRoom->actual_arrival_date->toDateString() : ($validated['arrival_date'] ?? $booking->arrival_date),
                                'arrival_time' => $detail['arrivalTime'] ?? null,
                                'departure_time' => $detail['hoursOut'] ?? null,
                                'rate' => $alloc['price'] ?? 0,
                                'rate_code' => $alloc['rateCode'] ?? null,
                                'breakfast' => isset($detail['breakfast']) ? !empty($detail['breakfast']) : !empty($alloc['breakfastIncluded']),
                                'is_day_use' => !empty($booking->is_day_use) || ($detail['hourly'] ?? false) || (($validated['arrival_date'] ?? $booking->arrival_date) === ($validated['departure_date'] ?? $booking->departure_date)),
                                'discount' => $alloc['discount'] ?? null,
                                'discount_type' => $alloc['discountType'] ?? null,
                                'discount_value' => $alloc['discountValue'] ?? 0,
                                'discount_unit' => $alloc['discountUnit'] ?? null,
                                'base_price' => $alloc['basePrice'] ?? ($alloc['price'] ?? 0),
                                'adults' => $detail['adults'] ?? 2,
                                'extra_bed_qty' => (int)($detail['extraBedQty'] ?? (empty($detail['extraBedPrice']) ? 0 : 1)),
                                'extra_bed_rate' => $detail['extraBedPrice'] ?? 0,
                                'status' => $bRoom ? $bRoom->status : \App\Models\BookingRoom::STATUS_BOOKED,
                            ];

                            if ($bRoom) {
                                $bRoom->update($roomData);
                            } else {
                                $bRoom = \App\Models\BookingRoom::create($roomData);
                            }
                            $this->upsertExtraBedServices($bRoom);

                            // Cập nhật hoặc thêm khách chính (guestName)
                            $roomGuestName = trim($detail['guestName'] ?? '');
                            if (empty($roomGuestName)) {
                                $roomGuestName = $booking->booking_name;
                            }
                            
                            $pivot = \App\Models\BookingRoomGuest::where('booking_room_id', $bRoom->id)->where('is_primary', 1)->first();
                            if ($pivot && $pivot->guest) {
                                $pivot->guest->update([
                                    'full_name' => $roomGuestName,
                                ]);
                            } else {
                                $guest = \App\Models\Guest::create([
                                    'full_name' => $roomGuestName,
                                    'guest_status' => \App\Models\Guest::STATUS_ACTIVE,
                                ]);
                                \App\Models\BookingRoomGuest::create([
                                    'booking_room_id' => $bRoom->id,
                                    'guest_id' => $guest->id,
                                    'is_primary' => 1,
                                ]);
                            }

                            // Đồng bộ trẻ em: Xóa cũ của phòng này và tạo lại theo số lượng mới
                            \App\Models\BookingChildBreakfastDetail::whereIn(
                                'booking_child_id',
                                \App\Models\BookingChild::where('booking_room_id', $bRoom->id)->pluck('id')
                            )->delete();
                            \App\Models\BookingChild::where('booking_room_id', $bRoom->id)->delete();

                            // Thêm số lượng children / babies vào booking_children
                            $numChildren = (int)($detail['children'] ?? 0);
                            for ($c = 0; $c < $numChildren; $c++) {
                                $child = \App\Models\BookingChild::create([
                                    'booking_id' => $booking->id,
                                    'booking_room_id' => $bRoom->id,
                                    'full_name' => 'Child ' . ($c+1),
                                    'age_group' => 'child',
                                ]);
                                $this->createChildBreakfastDetails($child, $bRoom);
                            }
                            
                            $numBabies = (int)($detail['babies'] ?? 0);
                            for ($b = 0; $b < $numBabies; $b++) {
                                $baby = \App\Models\BookingChild::create([
                                    'booking_id' => $booking->id,
                                    'booking_room_id' => $bRoom->id,
                                    'full_name' => 'Baby ' . ($b+1),
                                    'age_group' => 'baby',
                                ]);
                                $this->createChildBreakfastDetails($baby, $bRoom);
                            }
                        }
                    }

                    // 2. Những phòng BOOKED cũ thực sự bị xóa (vẫn còn soft-deleted và không được restore)
                    $unrestoredRoomIds = array_diff($bookedRoomIds, $restoredRoomIds);
                    if (count($unrestoredRoomIds) > 0) {
                        // Lấy danh sách ID khách của các phòng bị xóa hẳn này
                        $guestIds = \App\Models\BookingRoomGuest::whereIn('booking_room_id', $unrestoredRoomIds)->pluck('guest_id');
                        
                        // Xóa các trẻ em liên quan đến các phòng bị xóa hẳn này
                        \App\Models\BookingChildBreakfastDetail::whereIn(
                            'booking_child_id',
                            \App\Models\BookingChild::whereIn('booking_room_id', $unrestoredRoomIds)->pluck('id')
                        )->delete();
                        \App\Models\BookingChild::whereIn('booking_room_id', $unrestoredRoomIds)->delete();
                        
                        // Xóa các pivot guests
                        \App\Models\BookingRoomGuest::whereIn('booking_room_id', $unrestoredRoomIds)->delete();
                        
                        // Xóa các khách tương ứng để tránh bị rác database (orphaned guests)
                        if ($guestIds->count() > 0) {
                            $stillReferenced = \App\Models\BookingRoomGuest::whereIn('guest_id', $guestIds)->pluck('guest_id')->unique();
                            $orphanedGuestIds = $guestIds->diff($stillReferenced);
                            if ($orphanedGuestIds->count() > 0) {
                                \App\Models\Guest::whereIn('id', $orphanedGuestIds)->delete();
                            }
                        }
                    }
                }
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $booking->load([
            'registrationStatus',
            'company',
            'market',
            'customerSource',
            'branch',
            'booker',
            'paymentMethod',
            'bookingRooms.roomClass',
            'bookingRooms.room',
            'bookingRooms.guests.guest',
            'bookingRooms.children',
            'bookingRooms.services',
            'bookingRooms.specialRequests.specialRequest',
            'payments.paymentMethod',
        ]);

        return response()->json([
            'success' => true,
            'data'    => $booking,
            'message' => 'Cập nhật đăng ký thành công!',
        ]);
    }

    /**
     * Xóa booking (soft delete + cascade + đánh status = 3).
     * Business Rule 4.11: chỉ xóa khi TẤT CẢ phòng ở trạng thái đăng ký
     * và booking KHÔNG có cọc chưa thanh toán.
     */
    public function destroy($id)
    {
        $booking = Booking::with('bookingRooms')->find($id);
        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đăng ký!'], 404);
        }

        // Không cho xóa booking đã checkout hay đã xóa
        if (in_array($booking->status, [Booking::STATUS_CHECKOUT, Booking::STATUS_DELETED])) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xóa đăng ký đã checkout hoặc đã bị xóa!',
            ], 422);
        }

        // Kiểm tra: không có phòng nào đang inhouse
        $hasInhouse = $booking->bookingRooms->contains('status', BookingRoom::STATUS_CHECKED_IN);
        if ($hasInhouse) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xóa đăng ký khi có phòng đang check-in (inhouse)!',
            ], 422);
        }

        // Kiểm tra: không có cọc chưa thanh toán
        $hasPendingDeposit = Payment::where('booking_id', $id)
            ->where('pack2', Payment::PACK2_DEPOSIT)
            ->where('status', Payment::STATUS_PENDING)
            ->whereNull('payment_id')
            ->where('edit_flag', 0)
            ->exists();
        if ($hasPendingDeposit) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xóa đăng ký khi còn tiền cọc chưa thanh toán! Vui lòng xóa/hoàn cọc trước.',
            ], 422);
        }

        DB::transaction(function () use ($booking) {
            // Cascade: hủy tất cả phòng chưa hủy
            $activeRooms = $booking->bookingRooms->whereNotIn('status', [
                BookingRoom::STATUS_CANCELLED,
                BookingRoom::STATUS_CHECKED_OUT,
            ]);
            foreach ($activeRooms as $bRoom) {
                $bRoom->guests()->update(['status' => 3]);
                $bRoom->children()->update(['child_status' => 3]);
                $bRoom->update(['status' => BookingRoom::STATUS_CANCELLED]);
            }

            // Tự chuyển booking_status về bk_definite = 4 (nếu có)
            $cancelledStatus = RegistrationStatus::where('bk_definite', 4)->first();

            $booking->update([
                'status'                 => Booking::STATUS_DELETED,
                'registration_status_id' => $cancelledStatus?->id ?? $booking->registration_status_id,
                'updated_by'             => Auth::user()?->username ?? 'system',
            ]);
            $booking->delete(); // soft delete
        });

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa đăng ký thành công!',
        ]);
    }

    // =========================================
    // #19 — Nhân bản (Copy) booking
    // POST /bookings/{id}/copy
    // =========================================
    public function copy(Request $request, $id)
    {
        $source = Booking::with([
            'bookingRooms.guests.guest',
            'bookingRooms.children',
        ])->find($id);

        if (!$source) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đăng ký gốc!'], 404);
        }

        $request->validate([
            'arrival_date'   => 'required|date',
            'departure_date' => 'required|date|after:arrival_date',
        ]);

        $systemDate = SystemDateRoll::latest('id')->first();
        $sysDateStr = $systemDate
            ? Carbon::parse($systemDate->system_date)->toDateString()
            : now()->toDateString();

        if ($request->arrival_date < $sysDateStr) {
            return response()->json([
                'success' => false,
                'message' => 'Ngày đến không được nhỏ hơn ngày hệ thống (' . $sysDateStr . ').',
            ], 422);
        }

        // Kiểm tra tham số IsCopyAllBooking
        $copyRooms = HotelConfig::where('name', 'IsCopyAllBooking')->first()?->value != '0';
        $allowOver = HotelConfig::where('name', 'AllowOverRoomTypeRoomKind')->first()?->value == '1';

        $newArrival   = $request->arrival_date;
        $newDeparture = $request->departure_date;
        $numDays      = Carbon::parse($newArrival)->diffInDays(Carbon::parse($newDeparture));

        try {
            $newBooking = DB::transaction(function () use (
                $source, $newArrival, $newDeparture, $numDays, $copyRooms, $allowOver
            ) {
                // 1. Tạo booking mới từ booking gốc
                $newBooking = Booking::create([
                    'booking_name'           => $source->booking_name,
                    'arrival_date'           => $newArrival,
                    'departure_date'         => $newDeparture,
                    'num_of_days'            => $numDays,
                    'booking_date'           => now()->toDateString(),
                    'booking_time'           => now()->format('H:i:s'),
                    'confirm_date'           => $newArrival,
                    'status'                 => Booking::STATUS_RESERVATION,
                    'registration_status_id' => $source->registration_status_id,
                    'color'                  => $source->color,
                    'is_git'                 => true,
                    'is_day_use'             => $source->is_day_use,
                    'breakfast_included'     => $source->breakfast_included,
                    'has_vat'                => $source->has_vat,
                    'company_id'             => $source->company_id,
                    'market_id'              => $source->market_id,
                    'customer_source_id'     => $source->customer_source_id,
                    'branch_id'              => $source->branch_id,
                    'booker_id'              => $source->booker_id,
                    'contact_name'           => $source->contact_name,
                    'contact_email'          => $source->contact_email,
                    'contact_phone'          => $source->contact_phone,
                    'payment_method_id'      => $source->payment_method_id,
                    'note'                   => $source->note,
                    'sales_person'           => $source->sales_person,
                    'module'                 => $source->module,
                    'created_by'             => Auth::user()?->username ?? 'system',
                    // Không copy: deposit_details, payment_value
                ]);

                // 2. Copy phòng nếu được phép
                $avService  = app(RoomAvailabilityService::class);
                $roomsSkipped = [];

                if ($copyRooms) {
                    foreach ($source->bookingRooms as $srcRoom) {
                        // Bỏ qua phòng đã hủy
                        if ($srcRoom->status === BookingRoom::STATUS_CANCELLED) continue;

                        // Check AV cho loại phòng
                        $av = $avService->getAvailability(
                            $srcRoom->room_class_id,
                            $newArrival,
                            $newDeparture
                        );

                        if ($av <= 0 && !$allowOver) {
                            $roomsSkipped[] = $srcRoom->roomClass->name ?? 'N/A';
                            continue; // Bỏ qua phòng này
                        }

                        $newRoom = BookingRoom::create([
                            'booking_id'             => $newBooking->id,
                            'room_class_id'          => $srcRoom->room_class_id,
                            'original_room_class_id' => $srcRoom->room_class_id,
                            'room_number'            => null, // Không copy số phòng vật lý
                            'arrival_date'           => $newArrival,
                            'departure_date'         => $newDeparture,
                            'arrival_time'           => $srcRoom->arrival_time,
                            'departure_time'         => $srcRoom->departure_time,
                            'rate'                   => $srcRoom->rate,
                            'adults'                 => $srcRoom->adults,
                            'extra_bed_qty'          => $srcRoom->extra_bed_qty,
                            'extra_bed_rate'         => $srcRoom->extra_bed_rate,
                            'status'                 => BookingRoom::STATUS_BOOKED,
                            'created_by'             => Auth::user()?->username ?? 'system',
                        ]);

                        // Copy khách
                        foreach ($srcRoom->guests as $pivotGuest) {
                            BookingRoomGuest::create([
                                'booking_room_id' => $newRoom->id,
                                'guest_id'        => $pivotGuest->guest_id,
                                'is_primary'      => $pivotGuest->is_primary,
                                'status'          => 0,
                            ]);
                        }

                        // Copy trẻ em
                        foreach ($srcRoom->children as $srcChild) {
                            $newChild = BookingChild::create([
                                'booking_id'      => $newBooking->id,
                                'booking_room_id' => $newRoom->id,
                                'full_name'       => $srcChild->full_name,
                                'age_group'       => $srcChild->age_group,
                                'child_status'    => 0,
                            ]);
                            $this->createChildBreakfastDetails($newChild, $newRoom);
                        }

                        // KHÔNG copy: auto_services, deposit
                    }
                }

                return ['booking' => $newBooking, 'skipped' => $roomsSkipped];
            });
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $newBooking['booking']->load([
            'registrationStatus', 'company', 'bookingRooms.roomClass',
        ]);

        return response()->json([
            'success'       => true,
            'data'          => $newBooking['booking'],
            'rooms_skipped' => $newBooking['skipped'],
            'message'       => 'Nhân bản booking thành công!' . (
                count($newBooking['skipped']) > 0
                    ? ' Một số loại phòng không đủ AV bị bỏ qua: ' . implode(', ', $newBooking['skipped'])
                    : ''
            ),
        ], 201);
    }

    // =========================================
    // #22 — Khôi phục booking đã hủy
    // POST /bookings/{id}/restore
    // =========================================
    public function restore($id)
    {
        $booking = Booking::withTrashed()->with('bookingRooms')->find($id);

        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đăng ký!'], 404);
        }

        if ($booking->status !== Booking::STATUS_DELETED) {
            return response()->json([
                'success' => false,
                'message' => 'Booking chưa bị xóa/hủy, không cần khôi phục.',
            ], 422);
        }

        $systemDate = SystemDateRoll::latest('id')->first();
        $sysDateStr = $systemDate
            ? Carbon::parse($systemDate->system_date)->toDateString()
            : now()->toDateString();

        DB::transaction(function () use ($booking, $sysDateStr) {
            // Restore soft delete
            $booking->restore();

            // Cập nhật status booking về Reservation
            // NOTE: booking_status (registration_status_id) giữ nguyên trạng thái Cancelled
            //       để user tự kiểm tra lại thực tế (theo business rule 4.11)
            $booking->update([
                'status'     => Booking::STATUS_RESERVATION,
                'updated_by' => Auth::user()?->username ?? 'system',
            ]);

            $arrivalStr = Carbon::parse($booking->arrival_date)->toDateString();

            // Nếu arrival_date < system_date → chỉ khôi phục thông tin booking, KHÔNG khôi phục phòng
            // Nếu arrival_date >= system_date → khôi phục cả phòng
            if ($arrivalStr >= $sysDateStr) {
                foreach ($booking->bookingRooms as $bRoom) {
                    if ($bRoom->status === BookingRoom::STATUS_CANCELLED) {
                        $bRoom->restore();
                        $bRoom->update(['status' => BookingRoom::STATUS_BOOKED]);
                        // Khôi phục guests
                        $bRoom->guests()->update(['status' => 0]);
                        // Khôi phục children
                        $bRoom->children()->update(['child_status' => 0]);
                    }
                }
            }
        });

        $arrivalStr = Carbon::parse($booking->arrival_date)->toDateString();
        $roomRestored = ($arrivalStr >= $sysDateStr);

        return response()->json([
            'success'        => true,
            'data'           => $booking->fresh()->load(['registrationStatus', 'bookingRooms.roomClass', 'payments.paymentMethod']),
            'rooms_restored' => $roomRestored,
            'message'        => 'Khôi phục booking thành công!' . (
                !$roomRestored
                    ? ' Ngày đến đã qua ngày hệ thống — chỉ khôi phục thông tin booking, phòng không được khôi phục.'
                    : ''
            ),
        ]);
    }

    // =========================================
    // #12 — Xuất Excel danh sách booking
    // GET /bookings/export
    // =========================================
    public function export(Request $request)
    {
        $query = Booking::with([
            'registrationStatus',
            'company',
            'market',
            'customerSource',
            'bookingRooms.roomClass',
        ]);

        if ($request->arrival_date) {
            $query->whereDate('arrival_date', $request->arrival_date);
        }
        if ($request->from_date && $request->to_date) {
            $query->where(function ($q) use ($request) {
                $q->whereBetween('arrival_date', [$request->from_date, $request->to_date])
                  ->orWhereBetween('departure_date', [$request->from_date, $request->to_date]);
            });
        }
        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('booking_name', 'like', '%' . $search . '%');
                
                $cleanId = preg_replace('/[^0-9]/', '', $search);
                if (!empty($cleanId)) {
                    $q->orWhere('id', $cleanId);
                }
            });
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $bookings = $query->orderBy('arrival_date')->get();

        // Build CSV
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="bookings_' . now()->format('Ymd_His') . '.csv"',
        ];

        $columns = [
            'Mã ĐK', 'Tên đăng ký', 'Ngày đến', 'Ngày đi', 'Số đêm',
            'Tình trạng', 'Booking Status', 'Công ty', 'Thị trường',
            'Loại phòng', 'Số phòng', 'Ngày tạo', 'Người tạo',
        ];

        $callback = function () use ($bookings, $columns) {
            $file = fopen('php://output', 'w');
            // BOM UTF-8 cho Excel nhận dạng tiếng Việt
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, $columns);

            $statusLabels = [
                Booking::STATUS_RESERVATION => 'Đăng ký',
                Booking::STATUS_CHECKIN     => 'Checked In',
                Booking::STATUS_CHECKOUT    => 'Checked Out',
                Booking::STATUS_DELETED     => 'Đã hủy',
                Booking::STATUS_NO_SHOW     => 'No Show',
            ];

            foreach ($bookings as $bk) {
                $roomTypes = $bk->bookingRooms->pluck('roomClass.name')->filter()->unique()->implode(', ');
                $roomNums  = $bk->bookingRooms->pluck('room_number')->filter()->implode(', ');

                fputcsv($file, [
                    $bk->booking_code,
                    $bk->booking_name,
                    $bk->arrival_date?->toDateString(),
                    $bk->departure_date?->toDateString(),
                    $bk->num_of_days,
                    $statusLabels[$bk->status] ?? $bk->status,
                    $bk->registrationStatus?->name ?? '',
                    $bk->company?->name ?? '',
                    $bk->market?->name ?? '',
                    $roomTypes,
                    $roomNums,
                    $bk->booking_date?->toDateString(),
                    $bk->created_by,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Tự sinh booking_code theo prefix của khách sạn.
     * VD: prefix = "GAL" → GAL0001, GAL0002...
     */
    private function generateBookingCode(): string
    {
        $setting = HotelSetting::first();
        $prefix  = strtoupper($setting?->prefix_booking_id ?? 'BK');

        // Tìm số thứ tự tiếp theo (kể cả soft deleted)
        $lastBooking = Booking::withTrashed()
            ->where('booking_code', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTRING(booking_code, ' . (strlen($prefix) + 1) . ') AS UNSIGNED) DESC')
            ->first();

        if ($lastBooking) {
            $lastNum = (int) substr($lastBooking->booking_code, strlen($prefix));
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }

        return $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Validate room allocations against availability, OOO/OOS locks, and occupancy.
     */
    private function validateRoomAllocations(array $roomAllocations, string $arrivalDate, string $departureDate)
    {
        $avService = app(RoomAvailabilityService::class);
        $allowOver = \App\Models\HotelConfig::where('name', 'AllowOverRoomTypeRoomKind')->first()?->value == '1';
        $payloadAssignments = [];

        foreach ($roomAllocations as $alloc) {
            $roomClassId = $alloc['roomClassId'] ?? null;
            if (!$roomClassId) {
                throw new \Exception('Loại phòng không hợp lệ trong yêu cầu phân bổ phòng.');
            }

            $qty = (int)($alloc['quantity'] ?? 0);
            if ($qty <= 0) continue;

            // Validate AV
            $allocArrival = $arrivalDate;
            $allocDeparture = $departureDate;

            $av = $avService->getAvailability(
                $roomClassId,
                $allocArrival,
                $allocDeparture
            );

            if ($av < $qty && !$allowOver) {
                $roomClass = \App\Models\RoomClass::find($roomClassId);
                throw new \Exception('Không đủ phòng trống cho loại phòng ' . ($roomClass?->name ?? 'không xác định') . '. Số phòng trống hiện tại: ' . $av);
            }

            $details = $alloc['rooms'] ?? [];
            for ($i = 0; $i < $qty; $i++) {
                $detail = $details[$i] ?? [];
                if (!empty($detail['roomNumber'])) {
                    $roomNumber = $detail['roomNumber'];
                    $room = \App\Models\Room::where('room_number', $roomNumber)->first();
                    if (!$room) {
                        throw new \Exception('Số phòng ' . $roomNumber . ' không tồn tại trong hệ thống.');
                    }
                    if ($room->room_class_id != $roomClassId) {
                        throw new \Exception('Số phòng ' . $roomNumber . ' không thuộc loại phòng đã chọn.');
                    }

                    // Check duplicate assignment within the same request payload on overlapping dates
                    foreach ($payloadAssignments as $assignment) {
                        if ($assignment['room_number'] === $roomNumber) {
                            if ($allocArrival < $assignment['departure'] && $assignment['arrival'] < $allocDeparture) {
                                throw new \Exception('Số phòng ' . $roomNumber . ' bị trùng lặp trong cùng một lượt lưu với thời gian ở trùng nhau.');
                            }
                        }
                    }

                    // Record this assignment
                    $payloadAssignments[] = [
                        'room_number' => $roomNumber,
                        'arrival' => $allocArrival,
                        'departure' => $allocDeparture,
                    ];

                    // Check OOO/OOS Lock
                    $isLocked = \App\Models\RoomLock::where('room_number', $roomNumber)
                        ->where('is_active', 1)
                        ->where('start_date', '<', $allocDeparture)
                        ->where('end_date', '>', $allocArrival)
                        ->exists();

                    if ($isLocked) {
                        throw new \Exception('Số phòng ' . $roomNumber . ' đang bị khóa OOO/OOS trong giai đoạn này.');
                    }

                    // Check occupied by another booking
                    $isOccupied = $avService->isRoomNumberOccupied(
                        $roomNumber,
                        $allocArrival,
                        $allocDeparture
                    );
                    if ($isOccupied) {
                        throw new \Exception('Số phòng ' . $roomNumber . ' đã được gán cho lượt đăng ký khác trong giai đoạn này.');
                    }
                }
            }
        }
    }

    /**
     * Create child breakfast details for each day of the booking room stay.
     */
    private function createChildBreakfastDetails($child, $bRoom)
    {
        $setting = \App\Models\HotelSetting::first();
        $autoExtra = $setting?->booking_auto_extra_charge_bf_child == 1;
        $childRate = $setting?->breakfast_child_rate ?? 90000;

        $current = Carbon::parse($bRoom->arrival_date);
        $departure = Carbon::parse($bRoom->departure_date);

        while ($current->lt($departure)) {
            $isFree = true;
            $isExtra = false;
            $amount = 0;

            if ($child->age_group === 'child') {
                if ($autoExtra) {
                    $isFree = false;
                    $isExtra = true;
                    $amount = $childRate;
                }
            }

            \App\Models\BookingChildBreakfastDetail::create([
                'booking_child_id' => $child->id,
                'service_date'     => $current->toDateString(),
                'breakfast'        => true,
                'is_free'          => $isFree,
                'is_extra_charge'  => $isExtra,
                'is_room'          => true, // FIT
                'amount'           => $amount,
            ]);

            $current = $current->addDay();
        }
    }

    /**
     * Upsert dịch vụ Extra Bed (EB) theo từng ngày trong giai đoạn ở.
     */
    private function upsertExtraBedServices(BookingRoom $room): void
    {
        $arrivalDate   = $room->arrival_date->toDateString();
        $departureDate = $room->departure_date->toDateString();

        if ($room->extra_bed_qty <= 0) {
            // Chỉ xóa các dịch vụ EB chưa được post
            $room->services()
                ->where('service_code', \App\Models\BookingRoomService::CODE_EXTRA_BED)
                ->where('is_posted', 0)
                ->forceDelete();
            return;
        }

        // 1. Lấy danh sách dịch vụ EB hiện tại
        $existingServices = $room->services()
            ->where('service_code', \App\Models\BookingRoomService::CODE_EXTRA_BED)
            ->get()
            ->keyBy(function ($item) {
                return $item->service_date->toDateString();
            });

        // 2. Xóa các ngày nằm ngoài giai đoạn ở mới (nếu thu hẹp ngày ở) mà chưa post
        $current = Carbon::parse($arrivalDate);
        $end     = Carbon::parse($departureDate);
        $stayDates = [];
        while ($current->lt($end)) {
            $stayDates[] = $current->toDateString();
            $current = $current->addDay();
        }

        $room->services()
            ->where('service_code', \App\Models\BookingRoomService::CODE_EXTRA_BED)
            ->whereNotIn('service_date', $stayDates)
            ->where('is_posted', 0)
            ->forceDelete();

        // 3. Upsert cho từng ngày
        foreach ($stayDates as $dateStr) {
            $existing = $existingServices->get($dateStr);
            if ($existing && $existing->is_posted == 1) {
                // Đã post rồi thì không được ghi đè hay thay đổi giá/số lượng
                continue;
            }

            \App\Models\BookingRoomService::withTrashed()->updateOrCreate(
                [
                    'booking_room_id' => $room->id,
                    'service_code'    => \App\Models\BookingRoomService::CODE_EXTRA_BED,
                    'service_date'    => $dateStr,
                ],
                [
                    'service_name' => 'Extra Bed',
                    'quantity'     => $room->extra_bed_qty,
                    'rate'         => $room->extra_bed_rate,
                    'is_room'      => 1,
                    'is_posted'    => 0,
                    'deleted_at'   => null,
                    'created_by'   => Auth::user()?->username ?? 'system',
                ]
            );
        }
    }
}
