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
     * Lấy tất cả dữ liệu dropdown cần thiết cho màn tạo/sửa booking trong 1 request duy nhất.
     */
    public function initDropdowns()
    {
        $latest = \App\Models\SystemDateRoll::latest('id')->first();
        $systemDate = $latest
            ? \Carbon\Carbon::parse($latest->system_date)->toDateString()
            : now()->timezone('Asia/Ho_Chi_Minh')->toDateString();
        $shift = $latest ? $latest->shift : '1';

        $roomClasses = \App\Models\RoomClass::with(['roomClassGroup', 'standardRates.roomForm'])->get();

        $hotelSettingModel = \App\Models\HotelSetting::first() ?? new \App\Models\HotelSetting();
        $hotelSettingsData = (new \App\Http\Resources\HotelSettingResource($hotelSettingModel))->resolve();
        $configs = \App\Models\HotelConfig::pluck('value', 'name');
        foreach ($configs as $k => $v) {
            $hotelSettingsData[$k] = $v;
        }
        $bfConfig = $configs->get('DefaultBreakfast');
        $hotelSettingsData['DefaultBreakfast'] = $bfConfig !== null ? intval($bfConfig) : 1;
        $hotelSettingsData['default_breakfast'] = $hotelSettingsData['DefaultBreakfast'];

        return response()->json([
            'success' => true,
            'data' => [
                'markets' => \App\Http\Resources\MarketResource::collection(\App\Models\Market::all()),
                'customer_sources' => \App\Http\Resources\CustomerSourceResource::collection(\App\Models\CustomerSource::all()),
                'bookers' => \App\Http\Resources\BookerResource::collection(\App\Models\Booker::orderBy('id')->get()),
                'companies' => \App\Http\Resources\CompanyResource::collection(\App\Models\Company::all()),
                'payment_methods' => \App\Http\Resources\PaymentMethodResource::collection(\App\Models\PaymentMethod::all()),
                'registration_statuses' => \App\Http\Resources\RegistrationStatusResource::collection(\App\Models\RegistrationStatus::all()),
                'users' => \App\Models\User::all(),
                'room_classes' => \App\Http\Resources\RoomClassResource::collection($roomClasses),
                'room_forms' => \App\Http\Resources\RoomFormResource::collection(\App\Models\RoomForm::all()),
                'room_rate_codes' => \App\Models\RoomRateCode::with('ratePlans', 'dailyMappings')->get(),
                'currencies' => \App\Http\Resources\CurrencyResource::collection(\App\Models\Currency::all()),
                'hotel_services' => \App\Http\Resources\HotelServiceResource::collection(\App\Models\HotelService::all()),
                'hotel_settings' => $hotelSettingsData,
                'system_time' => now()->timezone('Asia/Ho_Chi_Minh')->toIso8601String(),
                'system_date' => [
                    'system_date' => $systemDate,
                    'shift' => $shift
                ]
            ]
        ]);
    }

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
            'registration_status_id'   => 'required|exists:registration_statuses,id',
            'color'                    => 'nullable|string|max:20',
            'is_git'                   => 'nullable|boolean',
            'is_day_use'               => 'nullable|boolean',
            'breakfast_included'       => 'nullable|boolean',
            'has_vat'                  => 'nullable|boolean',
            'company_id'               => 'required|exists:companies,id',
            'market_id'                => 'required|exists:markets,id',
            'customer_source_id'       => 'required|exists:customer_sources,id',
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
        ], [
            'registration_status_id.required' => 'Vui lòng chọn Tình trạng đăng ký!',
            'registration_status_id.exists'   => 'Tình trạng đăng ký không tồn tại!',
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
        if (empty($validated['module'])) {
            $validated['module'] = $request->input('created_module', 'reservation');
        }

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
                                'babies' => $detail['babies'] ?? 0,
                                'children_qty' => $detail['children'] ?? 0,
                                'extra_bed_qty' => (int)($detail['extraBedQty'] ?? (empty($detail['extraBedPrice']) ? 0 : 1)),
                                'extra_bed_rate' => $detail['extraBedPrice'] ?? 0,
                                'status' => \App\Models\BookingRoom::STATUS_BOOKED,
                            ]);
                            $this->upsertBookingRoomServices($bRoom, $detail);

                            // Thêm khách chính (guestName)
                            $roomGuestName = trim($detail['guestName'] ?? '');
                            if (empty($roomGuestName)) {
                                $roomGuestName = 'Guest 1';
                            }
                            $guest = \App\Models\Guest::create([
                                'full_name' => $roomGuestName,
                                'title' => 'Mr.',
                                'nationality_code' => 'VN',
                                'guest_status' => \App\Models\Guest::STATUS_ACTIVE,
                            ]);
                            \App\Models\BookingRoomGuest::create([
                                'booking_room_id'     => $bRoom->id,
                                'guest_id'            => $guest->id,
                                'is_primary'          => 1,
                                'status'              => $bRoom->status,
                                'actual_arrival_date' => $bRoom->arrival_date,
                                'checkin_by'          => Auth::user()?->username ?? 'system',
                                'breakfast'           => $bRoom->breakfast,
                            ]);

                            // Tự động tạo thêm khách phụ cho đủ số lượng adults
                            $numAdults = (int)($detail['adults'] ?? 2);
                            if ($numAdults > 1) {
                                for ($a = 2; $a <= $numAdults; $a++) {
                                    $subGuest = \App\Models\Guest::create([
                                        'full_name'    => 'Guest ' . $a,
                                        'title'        => 'Mr.',
                                        'nationality_code' => 'VN',
                                        'guest_status' => \App\Models\Guest::STATUS_ACTIVE,
                                    ]);
                                    \App\Models\BookingRoomGuest::create([
                                        'booking_room_id'     => $bRoom->id,
                                        'guest_id'            => $subGuest->id,
                                        'is_primary'          => 0,
                                        'status'              => $bRoom->status,
                                        'actual_arrival_date' => $bRoom->arrival_date,
                                        'checkin_by'          => Auth::user()?->username ?? 'system',
                                        'breakfast'           => $bRoom->breakfast,
                                    ]);
                                }
                            }

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

        try {
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
                'registration_status_id'   => 'required|exists:registration_statuses,id',
                'color'                    => 'nullable|string|max:20',
                'is_git'                   => 'nullable|boolean',
                'is_day_use'               => 'nullable|boolean',
                'breakfast_included'       => 'nullable|boolean',
                'has_vat'                  => 'nullable|boolean',
                'company_id'               => 'required|exists:companies,id',
                'market_id'                => 'required|exists:markets,id',
                'customer_source_id'       => 'required|exists:customer_sources,id',
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
            ], [
                'registration_status_id.required' => 'Vui lòng chọn Tình trạng đăng ký!',
                'registration_status_id.exists'   => 'Tình trạng đăng ký không tồn tại!',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Illuminate\Support\Facades\Log::error('Validation failed on update booking: ' . json_encode($e->errors()));
            throw $e;
        }

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
                        $bArrDate = $booking->arrival_date ? Carbon::parse($booking->arrival_date)->toDateString() : now()->toDateString();
                        $bDepDate = $booking->departure_date ? Carbon::parse($booking->departure_date)->toDateString() : now()->toDateString();
                        $this->validateRoomAllocations(
                            $request->room_allocations,
                            $validated['arrival_date'] ?? $bArrDate,
                            $validated['departure_date'] ?? $bDepDate,
                            $booking->id
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
                                    $sysDateRecord = \App\Models\SystemDateRoll::latest('id')->first();
                                    $systemDateStr = $sysDateRecord ? Carbon::parse($sysDateRecord->system_date)->toDateString() : now()->toDateString();
                                    
                                    // Thực hiện chuyển phòng và cập nhật bRoom trỏ sang phòng mới
                                    $newRoom = $bRoom->moveToRoom($newRoomNumber, $systemDateStr, $currentUser);
                                    $bRoom = $newRoom;
                                }
                            }
                            
                            $roomArrival = $detail['arrivalDate'] ?? $detail['checkIn'] ?? $validated['arrival_date'] ?? $booking->arrival_date;
                            $roomDeparture = $detail['departureDate'] ?? $detail['checkOut'] ?? $validated['departure_date'] ?? $booking->departure_date;

                            $roomData = [
                                'booking_id' => $booking->id,
                                'room_number' => $detail['roomNumber'] ?? null,
                                'room_class_id' => $alloc['roomClassId'] ?? null,
                                'original_room_class_id' => $alloc['roomClassId'] ?? null,
                                'arrival_date' => $roomArrival,
                                'departure_date' => $roomDeparture,
                                'actual_arrival_date' => $bRoom && $bRoom->actual_arrival_date ? $bRoom->actual_arrival_date->toDateString() : $roomArrival,
                                'arrival_time' => $detail['arrivalTime'] ?? null,
                                'departure_time' => $detail['hoursOut'] ?? null,
                                'rate' => $detail['price'] ?? $alloc['price'] ?? 0,
                                'rate_code' => $detail['rateCode'] ?? $alloc['rateCode'] ?? null,
                                'breakfast' => isset($detail['breakfast']) ? !empty($detail['breakfast']) : !empty($alloc['breakfastIncluded']),
                                'is_day_use' => !empty($booking->is_day_use) || ($detail['hourly'] ?? false) || ($roomArrival === $roomDeparture),
                                'discount' => $detail['discount'] ?? $alloc['discount'] ?? null,
                                'discount_type' => $detail['discountType'] ?? $alloc['discountType'] ?? null,
                                'discount_value' => $detail['discountValue'] ?? $alloc['discountValue'] ?? 0,
                                'discount_unit' => $detail['discountUnit'] ?? $alloc['discountUnit'] ?? null,
                                'base_price' => $detail['basePrice'] ?? $alloc['basePrice'] ?? $detail['price'] ?? 0,
                                'adults' => $detail['adults'] ?? 2,
                                'babies' => $detail['babies'] ?? 0,
                                'children_qty' => $detail['children'] ?? 0,
                                'extra_bed_qty' => (int)($detail['extraBedQty'] ?? (empty($detail['extraBedPrice']) ? 0 : 1)),
                                'extra_bed_rate' => $detail['extraBedPrice'] ?? 0,
                                'status' => $bRoom ? $bRoom->status : \App\Models\BookingRoom::STATUS_BOOKED,
                            ];

                            if ($bRoom) {
                                $bRoom->update($roomData);
                            } else {
                                $bRoom = \App\Models\BookingRoom::create($roomData);
                            }
                            $this->upsertBookingRoomServices($bRoom, $detail);

                            // Tự động xóa dịch vụ chưa post ở ngày >= ngày đi khi bị giảm số đêm
                            \App\Models\BookingRoomService::where('booking_room_id', $bRoom->id)
                                ->where('service_date', '>=', $bRoom->departure_date->toDateString())
                                ->where('is_posted', 0)
                                ->delete();

                            // Cập nhật hoặc thêm khách chính (guestName)
                            $roomGuestName = trim($detail['guestName'] ?? '');
                            if (empty($roomGuestName)) {
                                $roomGuestName = 'Guest 1';
                            }
                            
                            $pivot = \App\Models\BookingRoomGuest::where('booking_room_id', $bRoom->id)->where('is_primary', 1)->first();
                            if ($pivot && $pivot->guest) {
                                $pivot->guest->update([
                                    'full_name' => $roomGuestName,
                                ]);
                                $pivot->update([
                                    'status' => $bRoom->status,
                                ]);
                            } else {
                                $guest = \App\Models\Guest::create([
                                    'full_name'        => $roomGuestName,
                                    'title'            => 'Mr.',
                                    'nationality_code' => 'VN',
                                    'guest_status'     => \App\Models\Guest::STATUS_ACTIVE,
                                ]);
                                \App\Models\BookingRoomGuest::create([
                                    'booking_room_id'     => $bRoom->id,
                                    'guest_id'            => $guest->id,
                                    'is_primary'          => 1,
                                    'status'              => $bRoom->status,
                                    'actual_arrival_date' => $bRoom->arrival_date,
                                    'checkin_by'          => Auth::user()?->username ?? 'system',
                                    'breakfast'           => $bRoom->breakfast,
                                ]);
                            }

                            // Đồng bộ số lượng adults khách phụ (non-primary)
                            $targetAdults = (int)($detail['adults'] ?? 2);
                            $secondaries = \App\Models\BookingRoomGuest::where('booking_room_id', $bRoom->id)
                                ->where('is_primary', 0)
                                ->get();
                            
                            // Cập nhật status cho khách phụ hiện có
                            foreach ($secondaries as $subPivot) {
                                $subPivot->update([
                                    'status' => $bRoom->status,
                                ]);
                            }

                            $totalCurrentGuests = 1 + $secondaries->count();

                            if ($totalCurrentGuests < $targetAdults) {
                                // Cần tạo thêm khách phụ
                                $needed = $targetAdults - $totalCurrentGuests;
                                for ($a = 0; $a < $needed; $a++) {
                                    $seq = $totalCurrentGuests + $a + 1;
                                    $subGuest = \App\Models\Guest::create([
                                        'full_name'        => 'Guest ' . $seq,
                                        'title'            => 'Mr.',
                                        'nationality_code' => 'VN',
                                        'guest_status'     => \App\Models\Guest::STATUS_ACTIVE,
                                    ]);
                                    \App\Models\BookingRoomGuest::create([
                                        'booking_room_id'     => $bRoom->id,
                                        'guest_id'            => $subGuest->id,
                                        'is_primary'          => 0,
                                        'status'              => $bRoom->status,
                                        'actual_arrival_date' => $bRoom->arrival_date,
                                        'checkin_by'          => Auth::user()?->username ?? 'system',
                                        'breakfast'           => $bRoom->breakfast,
                                    ]);
                                }
                            } elseif ($totalCurrentGuests > $targetAdults) {
                                // Cần xóa bớt khách phụ thừa
                                $toRemove = $totalCurrentGuests - $targetAdults;
                                $secondariesToRemove = \App\Models\BookingRoomGuest::where('booking_room_id', $bRoom->id)
                                    ->where('is_primary', 0)
                                    ->orderBy('id', 'desc')
                                    ->take($toRemove)
                                    ->get();
                                foreach ($secondariesToRemove as $pivotToRemove) {
                                    $gId = $pivotToRemove->guest_id;
                                    $pivotToRemove->delete();
                                    \App\Models\Guest::where('id', $gId)->delete();
                                }
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
            \Illuminate\Support\Facades\Log::error('Exception in update booking: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
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
    public function destroy(Request $request, $id)
    {
        $booking = Booking::with('bookingRooms')->find($id);
        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đăng ký!'], 404);
        }

        // Kiểm tra cấu hình CheckModuleBeforeDelete
        $checkModuleConfig = \Illuminate\Support\Facades\DB::table('hotel_configs')
            ->where('name', 'CheckModuleBeforeDelete')
            ->value('value');

        if ($checkModuleConfig === '1' || $checkModuleConfig === 1) {
            $currentModule = strtolower($request->input('current_module', 'reservation'));
            $bookingModule = strtolower($booking->module ?? 'reservation');

            if ($bookingModule === 'reservation' && $currentModule === 'reception') {
                return response()->json([
                    'success' => false,
                    'message' => 'Đăng ký được tạo bởi bộ phận đặt phòng. Bạn không có quyền được hủy.'
                ], 403);
            }
            if ($bookingModule === 'reception' && $currentModule === 'reservation') {
                return response()->json([
                    'success' => false,
                    'message' => 'Đăng ký được tạo bởi bộ phận lễ tân. Bạn không có quyền được hủy.'
                ], 403);
            }
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

        $request->validate([
            'cancel_reason_id' => 'nullable|exists:cancel_reasons,id',
            'note'             => 'nullable|string',
        ]);

        $reasonText = $request->note;
        if ($request->cancel_reason_id && empty($reasonText)) {
            $cReason = \App\Models\CancelReason::find($request->cancel_reason_id);
            $reasonText = $cReason?->name;
        }

        DB::transaction(function () use ($booking, $id, $request, $reasonText) {
            $currentUserId = Auth::id() ?? 0;
            $currentUsername = Auth::user()?->username ?? 'system';

            // 1. Ghi log hủy toàn bộ booking (SP8053)
            \App\Models\BookingCancelLog::create([
                'cancel_type'           => 'booking',
                'booking_id'            => $id,
                'booking_room_id'       => null,
                'cancel_reason_id'      => $request->cancel_reason_id,
                'note'                  => $request->note,
                'cancelled_by_user_id'  => $currentUserId,
                'cancelled_by_username' => $currentUsername,
                'cancelled_at'          => now(),
            ]);

            // 2. Cascade: Hủy và ghi log cho từng phòng trong booking (SP8052)
            $allRooms = $booking->bookingRooms;
            foreach ($allRooms as $bRoom) {
                $bRoom->guests()->update(['status' => 3]);
                $bRoom->children()->update(['child_status' => 3]);
                $bRoom->update([
                    'status' => BookingRoom::STATUS_CANCELLED,
                    'reason' => $reasonText,
                ]);

                // Log cho từng phòng (SP8052)
                \App\Models\BookingCancelLog::create([
                    'cancel_type'           => 'room',
                    'booking_id'            => $id,
                    'booking_room_id'       => $bRoom->id,
                    'cancel_reason_id'      => $request->cancel_reason_id,
                    'note'                  => $request->note,
                    'cancelled_by_user_id'  => $currentUserId,
                    'cancelled_by_username' => $currentUsername,
                    'cancelled_at'          => now(),
                ]);
            }

            // Tự chuyển booking_status về bk_definite = 4 (nếu có)
            $cancelledStatus = RegistrationStatus::where('bk_definite', 4)->first();

            $booking->update([
                'status'                 => Booking::STATUS_DELETED,
                'registration_status_id' => $cancelledStatus?->id ?? $booking->registration_status_id,
                'updated_by'             => $currentUsername,
            ]);
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

                        // Copy khách — actual_arrival_date = arrival_date phòng mới (không copy ngày cũ)
                        foreach ($srcRoom->guests as $pivotGuest) {
                            BookingRoomGuest::create([
                                'booking_room_id'     => $newRoom->id,
                                'guest_id'            => $pivotGuest->guest_id,
                                'is_primary'          => $pivotGuest->is_primary,
                                'status'              => $newRoom->status,
                                'actual_arrival_date' => $newRoom->arrival_date,
                                'checkin_by'          => Auth::user()?->username ?? 'system',
                                'breakfast'           => $newRoom->breakfast,
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
    private function validateRoomAllocations(array $roomAllocations, string $arrivalDate, string $departureDate, $excludeBookingId = null)
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

            $details = $alloc['rooms'] ?? [];
            for ($i = 0; $i < $qty; $i++) {
                $detail = $details[$i] ?? [];
                
                // Lấy ngày đến/đi riêng của phòng con này nếu có, nếu không lấy của booking
                $roomArrival = $detail['arrivalDate'] ?? $detail['checkIn'] ?? $arrivalDate;
                $roomDeparture = $detail['departureDate'] ?? $detail['checkOut'] ?? $departureDate;

                // Validate AV cho khoảng ngày của phòng con này
                $av = $avService->getAvailability(
                    $roomClassId,
                    $roomArrival,
                    $roomDeparture
                );

                if ($av < $qty && !$allowOver) {
                    $roomClass = \App\Models\RoomClass::find($roomClassId);
                    throw new \Exception('Không đủ phòng trống cho loại phòng ' . ($roomClass?->name ?? 'không xác định') . '. Số phòng trống hiện tại: ' . $av);
                }

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
                            if ($roomArrival < $assignment['departure'] && $assignment['arrival'] < $roomDeparture) {
                                throw new \Exception('Số phòng ' . $roomNumber . ' bị trùng lặp trong cùng một lượt lưu với thời gian ở trùng nhau.');
                            }
                        }
                    }

                    // Record this assignment
                    $payloadAssignments[] = [
                        'room_number' => $roomNumber,
                        'arrival' => $roomArrival,
                        'departure' => $roomDeparture,
                    ];

                    // Check OOO/OOS Lock
                    $isLocked = \App\Models\RoomLock::where('room_number', $roomNumber)
                        ->where('is_active', 1)
                        ->where('start_date', '<', $roomDeparture)
                        ->where('end_date', '>', $roomArrival)
                        ->exists();

                    if ($isLocked) {
                        throw new \Exception('Số phòng ' . $roomNumber . ' đang bị khóa OOO/OOS trong giai đoạn này.');
                    }

                    // Check occupied by another booking
                    $isOccupied = $avService->isRoomNumberOccupied(
                        $roomNumber,
                        $roomArrival,
                        $roomDeparture,
                        $detail['bookingRoomId'] ?? null,
                        $excludeBookingId
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
    private function upsertExtraBedServices(BookingRoom $room, array $detail = []): void
    {
        $arrivalDate   = Carbon::parse($room->arrival_date)->toDateString();
        $departureDate = Carbon::parse($room->departure_date)->toDateString();

        $dailyEBMap = [];
        $dailyExtraBeds = $detail['dailyExtraBeds'] ?? $detail['daily_extra_beds'] ?? null;
        if (is_array($dailyExtraBeds)) {
            foreach ($dailyExtraBeds as $deb) {
                $rawDate = $deb['dateStr'] ?? $deb['date'] ?? null;
                if ($rawDate) {
                    try {
                        $dStr = Carbon::parse($rawDate)->toDateString();
                        $dailyEBMap[$dStr] = $deb;
                    } catch (\Exception $e) {}
                }
            }
        }
        
        if (isset($detail['services']) && is_array($detail['services'])) {
            foreach ($detail['services'] as $svc) {
                if (($svc['service_code'] ?? '') === \App\Models\BookingRoomService::CODE_EXTRA_BED) {
                    $rawDate = $svc['service_date'] ?? $svc['dateStr'] ?? null;
                    if ($rawDate) {
                        try {
                            $dStr = Carbon::parse($rawDate)->toDateString();
                            if (!isset($dailyEBMap[$dStr])) {
                                $dailyEBMap[$dStr] = $svc;
                            }
                        } catch (\Exception $e) {}
                    }
                }
            }
        }

        $hasExplicitDailyEB = !empty($dailyEBMap);

        // Lấy danh sách dịch vụ EB hiện tại
        $existingServices = $room->services()
            ->where('service_code', \App\Models\BookingRoomService::CODE_EXTRA_BED)
            ->get()
            ->keyBy(function ($item) {
                return Carbon::parse($item->service_date)->toDateString();
            });

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

        $latestEBRate = null;
        $latestEBQty  = null;

        foreach ($stayDates as $dateStr) {
            $existing = $existingServices->get($dateStr);
            if ($existing && $existing->is_posted == 1) {
                if ($existing->rate > 0 && $latestEBRate === null) {
                    $latestEBRate = (float)$existing->rate;
                    $latestEBQty  = (int)$existing->quantity;
                }
                continue;
            }

            $debInfo = $dailyEBMap[$dateStr] ?? null;
            if ($debInfo) {
                $qty  = (int)($debInfo['quantity'] ?? 0);
                $rate = (float)($debInfo['rate'] ?? 0);
                $isRoom = !empty($debInfo['isRoom']) || (!isset($debInfo['isRoom']) && ($debInfo['is_room'] ?? 1) != 0);
            } elseif ($hasExplicitDailyEB) {
                // Đã có dữ liệu chi tiết hàng ngày mà ngày này không có trong map -> qty = 0
                $qty  = 0;
                $rate = 0;
                $isRoom = true;
            } else {
                // Chưa từng có dữ liệu chi tiết -> dùng phẳng từ phòng
                $qty  = (int)$room->extra_bed_qty;
                $rate = (float)$room->extra_bed_rate;
                $isRoom = true;
            }

            if ($qty > 0 && $rate > 0 && $latestEBRate === null) {
                $latestEBRate = $rate;
                $latestEBQty  = $qty;
            }

            if ($qty <= 0) {
                if ($existing && $existing->is_posted == 0) {
                    $existing->delete();
                }
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
                    'quantity'     => $qty,
                    'rate'         => $rate,
                    'is_room'      => $isRoom ? 1 : 0,
                    'is_posted'    => 0,
                    'deleted_at'   => null,
                    'created_by'   => Auth::user()?->username ?? 'system',
                ]
            );
        }

        // Cập nhật lại extra_bed_rate của booking_room nếu có đơn giá mới
        if ($latestEBRate !== null && $latestEBRate != $room->extra_bed_rate) {
            $room->update([
                'extra_bed_rate' => $latestEBRate,
                'extra_bed_qty'  => $latestEBQty ?? $room->extra_bed_qty
            ]);
        }
    }

    /**
     * Upsert các dịch vụ bổ sung khác (ngoài RM và EB) từ mảng services gửi lên.
     */
    private function upsertAdditionalServices(BookingRoom $room, array $detail = []): void
    {
        $services = $detail['services'] ?? null;
        if (!is_array($services) || empty($services)) {
            return;
        }

        foreach ($services as $svc) {
            $code = $svc['service_code'] ?? null;
            if (!$code || $code === \App\Models\BookingRoomService::CODE_ROOM || $code === 'ROOM_CHARGE' || $code === \App\Models\BookingRoomService::CODE_EXTRA_BED) {
                continue;
            }

            $rawDate = $svc['service_date'] ?? null;
            if (!$rawDate) continue;
            try {
                $dateStr = Carbon::parse($rawDate)->toDateString();
            } catch (\Exception $e) {
                continue;
            }

            $existing = \App\Models\BookingRoomService::where('booking_room_id', $room->id)
                ->where('service_code', $code)
                ->where('service_date', $dateStr)
                ->first();

            if ($existing && $existing->is_posted == 1) {
                continue;
            }

            $qty = isset($svc['quantity']) ? (float)$svc['quantity'] : 1;
            $rate = isset($svc['rate']) ? (float)$svc['rate'] : 0;
            $isDeleted = !empty($svc['deleted']) || $qty <= 0;

            if ($isDeleted) {
                if ($existing && $existing->is_posted == 0) {
                    $existing->delete();
                }
                continue;
            }

            \App\Models\BookingRoomService::withTrashed()->updateOrCreate(
                [
                    'booking_room_id' => $room->id,
                    'service_code'    => $code,
                    'service_date'    => $dateStr,
                ],
                [
                    'service_name' => $svc['service_name'] ?? 'Dịch vụ bổ sung',
                    'quantity'     => $qty,
                    'rate'         => $rate,
                    'is_room'      => isset($svc['is_room']) ? ($svc['is_room'] ? 1 : 0) : 1,
                    'is_posted'    => 0,
                    'deleted_at'   => null,
                    'created_by'   => Auth::user()?->username ?? 'system',
                ]
            );
        }
    }

    /**
     * Upsert dịch vụ Tiền phòng (RM) theo từng ngày trong giai đoạn ở khi có giá tùy chỉnh theo ngày (SP2401).
     */
    private function upsertRoomChargeServices(BookingRoom $room, array $detail): void
    {
        $dailyPrices = $detail['dailyRoomPrices'] ?? $detail['daily_prices'] ?? null;
        if (!is_array($dailyPrices) || empty($dailyPrices)) {
            return;
        }

        $sysDateRecord = \App\Models\SystemDateRoll::latest('id')->first();
        $sysDateStr = $sysDateRecord ? Carbon::parse($sysDateRecord->system_date)->toDateString() : null;

        foreach ($dailyPrices as $rawDate => $rate) {
            try {
                $dateStr = Carbon::parse($rawDate)->toDateString();
            } catch (\Exception $e) {
                $dateStr = $rawDate;
            }

            if ($sysDateStr && $dateStr < $sysDateStr) {
                continue;
            }

            $existing = \App\Models\BookingRoomService::where('booking_room_id', $room->id)
                ->where('service_code', \App\Models\BookingRoomService::CODE_ROOM)
                ->where('service_date', $dateStr)
                ->first();

            if ($existing && $existing->is_posted == 1) {
                continue;
            }

            \App\Models\BookingRoomService::withTrashed()->updateOrCreate(
                [
                    'booking_room_id' => $room->id,
                    'service_code'    => \App\Models\BookingRoomService::CODE_ROOM,
                    'service_date'    => $dateStr,
                ],
                [
                    'service_name' => 'Dịch vụ phòng nghỉ',
                    'quantity'     => 1,
                    'rate'         => $rate,
                    'is_room'      => 1,
                    'is_posted'    => 0,
                    'deleted_at'   => null,
                    'created_by'   => Auth::user()?->username ?? 'system',
                ]
            );
        }
    }

    /**
     * Hàm tổng hợp upsert toàn bộ dịch vụ phòng (Room Charge, Extra Bed, Dịch vụ bổ sung).
     */
    private function upsertBookingRoomServices(BookingRoom $room, array $detail = []): void
    {
        $this->upsertRoomChargeServices($room, $detail);
        $this->upsertExtraBedServices($room, $detail);
        $this->upsertAdditionalServices($room, $detail);
    }
}
