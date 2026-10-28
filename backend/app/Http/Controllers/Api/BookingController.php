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
use App\Models\BankAccount;
use App\Models\PaymentMethod;
use App\Models\RegistrationStatus;
use App\Models\RoomClass;
use App\Models\RoomLock;
use App\Models\RoomRateCode;
use App\Models\StandardRate;
use App\Models\SystemDateRoll;
use App\Services\RoomAvailabilityService;
use App\Services\RegistrationStatusMapper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Support\ModuleCode;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    private array $rateCodePricingCache = [];
    private array $roomClassPricingContextCache = [];

    private function serviceOnlyMutationError(Booking $booking)
    {
        if (!$booking->is_service_only) {
            return null;
        }

        return response()->json([
            'success' => false,
            'code' => 'service_only_folio',
            'message' => 'Folio dịch vụ chỉ dùng để ghi nhận dịch vụ và thanh toán; không thể thay đổi nghiệp vụ lưu trú.',
        ], 422);
    }

    private function resolveDepositBankAccount($bankAccountId): ?BankAccount
    {
        if ($bankAccountId === null || $bankAccountId === '' || in_array(strtolower((string) $bankAccountId), ['null', 'undefined'], true)) {
            return null;
        }

        $bankAccount = BankAccount::query()
            ->whereKey($bankAccountId)
            ->where('is_active', true)
            ->where('is_intermediary', false)
            ->first();
        if (!$bankAccount) {
            abort(422, 'Tài khoản ngân hàng không tồn tại hoặc đã ngừng sử dụng.');
        }

        return $bankAccount;
    }

    /**
     * Lấy tất cả dữ liệu dropdown cần thiết cho màn tạo/sửa booking trong 1 request duy nhất.
     */
    public function initDropdowns(Request $request)
    {
        $latest = \App\Models\SystemDateRoll::latest('id')->first();
        $systemDate = $latest
            ? \Carbon\Carbon::parse($latest->system_date)->toDateString()
            : now()->timezone('Asia/Ho_Chi_Minh')->toDateString();
        $shift = $latest ? $latest->shift : '1';

        $roomClasses = \App\Models\RoomClass::with(['roomClassGroup', 'standardRates.roomForm'])->get();

        $hotelSettingModel = \App\Models\HotelSetting::first() ?? new \App\Models\HotelSetting();
        $hotelSettingsData = (new \App\Http\Resources\HotelSettingResource($hotelSettingModel))->resolve();
        $currentUser = Auth::user();
        $branchId = $request->attributes->get('_branch_id')
            ?? $currentUser?->primary_branch_id;
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
                'booking_capabilities' => [
                    'checkout_invoice_mutation_allowed' => app(\App\Services\CheckoutRoleConfigService::class)
                        ->canMutateCheckoutInvoice($currentUser, $branchId !== null ? (int) $branchId : null),
                    'can_update_checkout_booking' => app(\App\Services\CheckoutRoleConfigService::class)
                        ->canUpdateCheckedOutBooking($currentUser, $branchId !== null ? (int) $branchId : null),
                ],
                'hotel_settings' => $hotelSettingsData,
                'child_breakfast' => (function () {
                    $childBreakfast = app(\App\Services\ChildBreakfastService::class);

                    return [
                        'service_code' => $childBreakfast->configCode(),
                        'service_name' => $childBreakfast->serviceName(),
                        'detail_mode' => $childBreakfast->isDetailMode(),
                    ];
                })(),
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
        $relations = [
            'registrationStatus',
            'company',
            'market',
            'customerSource',
            'branch',
            'booker',
            'paymentMethod',
            'bookingRooms.roomClass',
            'bookingRooms.room',
            'bookingRooms.movedToRoom.room',
            'bookingRooms.movedFromRoom.room',
            'bookingRooms.guests.guest',
            'bookingRooms.children.breakfastDetails',
            'bookingRooms.services',
            'bookingRooms.serviceBills',
            'bookingRooms.currentServiceBills',
            'bookingRooms.specialRequests.specialRequest',
        ];

        $withBilling = $request->boolean('with_billing') || $request->input('with_billing') === 'true';
        if ($withBilling) {
            $relations[] = 'serviceBills.employeeOperator:id,employee_code,name';
            $relations[] = 'serviceBills.usernameOperator:id,username,name';
            $relations[] = 'serviceBills.user:id,name,username';
            $relations[] = 'serviceBills.hotelService:id,code,name';
            $relations[] = 'bookingRooms.serviceBills.employeeOperator:id,employee_code,name';
            $relations[] = 'bookingRooms.serviceBills.usernameOperator:id,username,name';
            $relations[] = 'bookingRooms.serviceBills.user:id,name,username';
            $relations[] = 'bookingRooms.serviceBills.hotelService:id,code,name';
            $relations[] = 'bookingRooms.currentServiceBills.employeeOperator:id,employee_code,name';
            $relations[] = 'bookingRooms.currentServiceBills.usernameOperator:id,username,name';
            $relations[] = 'bookingRooms.currentServiceBills.user:id,name,username';
            $relations[] = 'bookingRooms.currentServiceBills.hotelService:id,code,name';
            $relations[] = 'masterServiceBills.employeeOperator:id,employee_code,name';
            $relations[] = 'masterServiceBills.usernameOperator:id,username,name';
            $relations[] = 'masterServiceBills.user:id,name,username';
            $relations[] = 'masterServiceBills.hotelService:id,code,name';
            // Tổng giải trừ của từng dòng thanh toán để Hóa đơn hiển thị cột Giải trừ CN (công nợ AC).
            $relations['payments'] = fn ($paymentQuery) => $paymentQuery->withSum([
                'debtSettlements as settled_amount' => fn ($settlementQuery) => $settlementQuery
                    ->where('edit_flag', 0)
                    ->whereNull('deleted_at'),
            ], 'amount');
            $relations[] = 'payments.paymentMethod';
            $relations[] = 'payments.user';
            $relations[] = 'payments.bankAccount';
            // Ngày đến ban đầu của phòng late check-in để Hóa đơn cho post lại các đêm cũ.
            $relations[] = 'bookingRooms.lateCheckins:id,booking_room_id,late_checkin_date';
        }

        $stayOnly = $request->boolean('stay_only');
        $query = Booking::with($relations);
        if ($stayOnly) {
            // Keep the legacy default response intact for financial and shared
            // consumers. Accommodation screens opt in to this narrower view.
            $query->where('is_service_only', false)
                ->where(function ($bookings): void {
                    $bookings->whereDoesntHave('bookingRooms')
                        ->orWhereHas('bookingRooms', fn ($rooms) => $rooms->stayOnly());
                })
                ->with(['bookingRooms' => fn ($rooms) => $rooms->stayOnly()]);
        }
        $query->withSum([
            'payments as active_deposit_total' => function ($paymentQuery) {
                $paymentQuery
                    ->where('pack2', Payment::PACK2_DEPOSIT)
                    ->where('edit_flag', 0)
                    ->whereNull('deleted_at');
            },
        ], 'amount');
        if (!$withBilling) {
            // Booking cards need the active deposit rows and their aggregate,
            // while the full history remains available from the payment modal.
            $query->with([
                'payments' => function ($paymentQuery) {
                    $paymentQuery
                        ->where('pack2', Payment::PACK2_DEPOSIT)
                        ->where('edit_flag', 0)
                        ->whereNull('deleted_at')
                        ->with(['paymentMethod', 'bankAccount', 'bookingRoom.room', 'user']);
                },
            ]);
        }

        // Filter theo ngày đến
        if ($request->arrival_date) {
            $pmsDate = Carbon::parse($request->arrival_date, 'Asia/Ho_Chi_Minh')->startOfDay();
            $pmsDateStartUtc = $pmsDate->copy()->utc();
            $pmsDateEndUtc = $pmsDate->copy()->addDay()->utc();

            $arrivalRoomFilter = function ($roomQuery) use ($request, $pmsDateStartUtc, $pmsDateEndUtc, $stayOnly) {
                if ($stayOnly) {
                    $roomQuery->stayOnly();
                }
                $statusValue = $request->input('status', '0,1');
                $statuses = is_array($statusValue)
                    ? array_map('intval', $statusValue)
                    : array_map('intval', explode(',', (string) $statusValue));
                $isDepartureView = $request->input('list_mode') === 'departures';

                $roomQuery->where(function ($dateQuery) use ($request, $statuses, $isDepartureView, $pmsDateStartUtc, $pmsDateEndUtc) {
                    $dateQuery->where(function ($bookedQuery) use ($request, $statuses) {
                        if (in_array(BookingRoom::STATUS_BOOKED, $statuses, true)) {
                            $bookedQuery->where('status', BookingRoom::STATUS_BOOKED)
                                ->whereDate('arrival_date', $request->arrival_date);
                        } else {
                            $bookedQuery->whereRaw('1 = 0');
                        }
                    })->orWhere(function ($inhouseQuery) use ($request, $statuses, $isDepartureView, $pmsDateEndUtc) {
                        if (in_array(BookingRoom::STATUS_CHECKED_IN, $statuses, true)) {
                            $inhouseQuery->where('status', BookingRoom::STATUS_CHECKED_IN);
                            if ($isDepartureView) {
                                $inhouseQuery->where('departure_date', '<', $pmsDateEndUtc);
                            } else {
                                $inhouseQuery->whereDate('arrival_date', '<=', $request->arrival_date)
                                    ->whereDate('departure_date', '>=', $request->arrival_date);
                            }
                        } else {
                            $inhouseQuery->whereRaw('1 = 0');
                        }
                    })->orWhere(function ($checkedOutQuery) use ($request, $statuses, $pmsDateStartUtc, $pmsDateEndUtc) {
                        if (in_array(BookingRoom::STATUS_CHECKED_OUT, $statuses, true)) {
                            $checkedOutQuery->where('status', BookingRoom::STATUS_CHECKED_OUT)
                                ->where('departure_date', '>=', $pmsDateStartUtc)
                                ->where('departure_date', '<', $pmsDateEndUtc);
                        } else {
                            $checkedOutQuery->whereRaw('1 = 0');
                        }
                    });
                });
            };

            $query->whereHas('bookingRooms', $arrivalRoomFilter);
            $query->with(['bookingRooms' => $arrivalRoomFilter]);
        }

        // Filter theo khoảng ngày
        if ($request->from_date && $request->to_date) {
            $dateType = $request->date_type;
            $query->where(function ($q) use ($request, $dateType, $stayOnly) {
                if ($dateType === 'arrival') {
                    // NgÃ y Ä‘áº¿n nghiá»‡p vá»¥ nÃ³i Ä‘áº¿n phÃ²ng, khÃ´ng pháº£i luÃ´n lÃ  ngÃ y header Booking.
                    $fromUtc = Carbon::parse($request->from_date, 'Asia/Ho_Chi_Minh')->startOfDay()->utc();
                    $toUtc = Carbon::parse($request->to_date, 'Asia/Ho_Chi_Minh')->endOfDay()->utc();
                    $q->whereHas('bookingRooms', function ($roomQuery) use ($fromUtc, $toUtc, $stayOnly) {
                        if ($stayOnly) {
                            $roomQuery->stayOnly();
                        }
                        $roomQuery->whereBetween('arrival_date', [$fromUtc, $toUtc]);
                    });
                } elseif ($dateType === 'departure') {
                    $q->whereBetween('departure_date', [$request->from_date, $request->to_date]);
                } else {
                    $q->whereBetween('arrival_date', [$request->from_date, $request->to_date])
                      ->orWhereBetween('departure_date', [$request->from_date, $request->to_date]);
                }
            });
        }

        // Filter theo tên đăng ký hoặc ID (mã BK) - tìm kiếm tương đối
        if ($request->search) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $like = '%' . $search . '%';

                $q->where('booking_name', 'like', $like)
                    ->orWhere('contact_name', 'like', $like)
                    ->orWhere('contact_phone', 'like', $like)
                    ->orWhere('external_booking_code', 'like', $like)
                    ->orWhere('id', 'like', $like)
                    ->orWhereHas('company', function ($companyQuery) use ($like, $search) {
                        $companyQuery->where('name', 'like', $like);
                        if (!ctype_digit($search) || strlen($search) >= 2) {
                            $companyQuery->orWhere('code', 'like', $like);
                        }
                    });

                // booking_code là accessor (ví dụ GAL4), tra phần số vào id theo giá trị tương đối (LIKE)
                $cleanId = preg_replace('/[^0-9]/', '', $search);
                if ($cleanId !== '') {
                    $q->orWhere('id', 'like', '%' . $cleanId . '%');
                }

                // Nếu chuỗi tìm kiếm khớp tiền tố mã booking (ví dụ GAL, gal)
                $setting = \App\Models\HotelSetting::first();
                $prefix = strtoupper($setting?->prefix_booking_id ?? 'GAL');
                $searchUpper = strtoupper($search);
                if ($searchUpper === $prefix || (strlen($searchUpper) >= 2 && stripos($prefix, $searchUpper) === 0)) {
                    $q->orWhereNotNull('id');
                }
            });
        }

        // Filter theo tình trạng phòng/booking
        if ($request->has('status') && !$request->arrival_date) {
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
        $bookings->each(fn (Booking $booking) => $this->hideCancelledRoomsForActiveBooking($booking));
        if ($withBilling && $request->boolean('with_service_amounts')) {
            $this->attachServiceBillAmounts($bookings);
        }

        return response()->json([
            'success' => true,
            'data'    => $bookings,
        ]);
    }

    /** Add persisted monetary tax/service-charge values for Checkout's service panel only. */
    private function attachServiceBillAmounts(\Illuminate\Database\Eloquent\Collection $bookings): void
    {
        $schema = DB::connection()->getSchemaBuilder();
        if (!$schema->hasColumn('service_bill_details', 'ServiceChargeAmount')
            || !$schema->hasColumn('service_bill_details', 'TaxAmount')) {
            return;
        }

        $billRecords = collect();
        $bookingRooms = collect();
        foreach ($bookings as $booking) {
            foreach (['serviceBills', 'masterServiceBills'] as $relation) {
                if ($booking->relationLoaded($relation)) {
                    $billRecords = $billRecords->concat($booking->getRelation($relation));
                }
            }

            if (!$booking->relationLoaded('bookingRooms')) {
                continue;
            }

            foreach ($booking->getRelation('bookingRooms') as $room) {
                $bookingRooms->push($room);
                foreach (['serviceBills', 'currentServiceBills'] as $relation) {
                    if ($room->relationLoaded($relation)) {
                        $billRecords = $billRecords->concat($room->getRelation($relation));
                    }
                }
            }
        }

        $billIds = $billRecords->pluck('Ma')->filter()->map(fn ($id) => (string) $id)->unique()->values();
        if ($billIds->isEmpty()) {
            return;
        }

        $detailsByBill = DB::table('service_bill_details')
            ->whereIn('BillServiceId', $billIds)
            ->get(['BillServiceId', 'Ma', 'ServiceChargeAmount', 'TaxAmount'])
            ->groupBy(fn ($detail) => (string) $detail->BillServiceId);

        $sumStoredAmount = static function ($details, string $column) {
            $values = $details->pluck($column)->filter(fn ($value) => $value !== null && $value !== '');
            return $values->isEmpty() ? null : $values->sum(fn ($value) => (float) $value);
        };

        foreach ($billRecords as $bill) {
            $billDetails = $detailsByBill->get((string) $bill->Ma, collect());
            $bill->setAttribute('ServiceChargeAmount', $sumStoredAmount($billDetails, 'ServiceChargeAmount'));
            $bill->setAttribute('TaxAmount', $sumStoredAmount($billDetails, 'TaxAmount'));
        }

        foreach ($bookingRooms as $room) {
            if (!$room->relationLoaded('services')) {
                continue;
            }

            foreach ($room->getRelation('services') as $service) {
                if (!$service->service_bill_id) {
                    continue;
                }

                $billDetails = $detailsByBill->get((string) $service->service_bill_id, collect());
                $detailNo = $service->service_bill_detail_no;
                $detail = $detailNo !== null && $detailNo !== ''
                    ? $billDetails->first(fn ($candidate) => (string) $candidate->Ma === (string) $detailNo)
                    : null;
                if (!$detail && $billDetails->count() === 1) {
                    $detail = $billDetails->first();
                }
                if ($detail) {
                    $service->setAttribute('service_charge_amount', $detail->ServiceChargeAmount);
                    $service->setAttribute('tax_amount', $detail->TaxAmount);
                }
            }
        }
    }

    /**
     * Tạo booking mới.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'booking_name'             => 'required|string|max:255',
            'arrival_date'             => 'required|date',
            'departure_date'           => 'required|date|after_or_equal:arrival_date',
            'num_of_days'              => 'required|integer|min:0',
            'confirm_date'             => 'nullable|date',
            'expired_date'             => 'nullable|date',
            'arrival_flight'           => 'nullable|string|max:50',
            'arrival_flight_date'      => 'nullable|date',
            'departure_flight'         => 'nullable|string|max:50',
            'departure_flight_date'    => 'nullable|date',
            'status'                   => 'nullable|integer',
            'registration_status_id'   => 'required|exists:registration_statuses,booking_status_id',
            'color'                    => 'nullable|string|max:20',
            'is_git'                   => 'nullable|boolean',
            'is_master_room_rate'      => 'nullable|boolean',
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

        // A day-use booking is an in/out-on-the-same-day stay, therefore it
        // has zero nights even when an older client sends num_of_days = 1.
        if (!empty($validated['is_day_use'])) {
            $validated['num_of_days'] = 0;
        }

        // Tự động tính Confirm Date nếu chưa có
        if (empty($validated['confirm_date'])) {
            if (!empty($validated['registration_status_id'])) {
                $statusModel = RegistrationStatus::where('booking_status_id', (int) $validated['registration_status_id'])->first();
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
                        $arrivalDate = Carbon::parse($validated['arrival_date'])->toDateString();
                        // Confirmation cannot be earlier than the PMS
                        // business date when the cut-off is already past.
                        $validated['confirm_date'] = max($sysDateStr, min($calcDate, $arrivalDate));
                    }
                } else {
                    $validated['confirm_date'] = Carbon::parse($validated['arrival_date'])->toDateString();
                }
            } else {
                $validated['confirm_date'] = Carbon::parse($validated['arrival_date'])->toDateString();
            }
        }

        // Enforce the PMS business-date cutoff for both calculated and
        // explicitly supplied values. A confirmation date cannot be in the
        // past relative to the current system date and cannot be after arrival.
        if (!empty($validated['confirm_date'])) {
            $confirmDate = Carbon::parse($validated['confirm_date'])->toDateString();
            $arrivalDate = Carbon::parse($validated['arrival_date'])->toDateString();
            $validated['confirm_date'] = max($sysDateStr, min($confirmDate, $arrivalDate));
        }

        // Người tạo
        $validated['created_by'] = Auth::user()?->username ?? 'system';
        $validated['created_by_user_id'] = Auth::id();
        $validated['module'] = ModuleCode::normalize(
            $validated['module'] ?? $request->input('created_module', ModuleCode::RESERVATION)
        );

        // Tình trạng mặc định = Reservation
        $validated['status'] = $validated['status'] ?? Booking::STATUS_RESERVATION;

        // Kiểm tra is_availability = 0 → trả cảnh báo cho UI hiển thị popup
        $isAvailabilityWarning = false;
        $isWaitingStatus = false;
        if (!empty($validated['registration_status_id'])) {
            $regStatus = RegistrationStatus::where('booking_status_id', (int) $validated['registration_status_id'])->first();
            if ($regStatus && !$regStatus->is_availability) {
                $isAvailabilityWarning = true;
                $isWaitingStatus = true;
            }
        }

        try {
            $booking = \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $request, $isWaitingStatus) {
                // 1. Validate room allocations trước khi tạo booking
                if ($request->has('room_allocations') && is_array($request->room_allocations)) {
                    $this->validateRoomAllocations(
                        $request->room_allocations,
                        $validated['arrival_date'],
                        $validated['departure_date'],
                        null,
                        $isWaitingStatus
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
                            
                            $syncRoomDates = \App\Models\HotelConfig::where('name', 'SyncRoomDateByBookingDate')->first()?->value == '1';

                            $roomArrival = $detail['arrivalDate'] ?? $detail['checkIn'] ?? null;
                            $roomDeparture = $detail['departureDate'] ?? $detail['checkOut'] ?? null;
                            $hasExplicitRoomDates = !empty($roomArrival) && !empty($roomDeparture);

                            $parseDate = function ($date) {
                                if (!$date) return null;
                                $date = trim($date);
                                if (strpos($date, '/') !== false) {
                                    $parts = explode('/', $date);
                                    if (count($parts) === 3) {
                                        if (strlen($parts[2]) === 4) {
                                            return "{$parts[2]}-{$parts[1]}-{$parts[0]}";
                                        }
                                    }
                                }
                                return Carbon::parse($date)->toDateString();
                            };

                            // Preserve explicitly selected room periods even when legacy synchronization is enabled.
                            if ($syncRoomDates && !$hasExplicitRoomDates) {
                                $roomArrival = $validated['arrival_date'];
                                $roomDeparture = $validated['departure_date'];
                            } else {
                                $roomArrival = $roomArrival ?? $validated['arrival_date'];
                                $roomDeparture = $roomDeparture ?? $validated['departure_date'];
                            }

                            $roomArrival = $parseDate($roomArrival);
                            $roomDeparture = $parseDate($roomDeparture);

                            // A same-day room is day-use only when the room (or,
                            // without a room flag, the booking) asks for it. A
                            // same-day room without day-use is allowed: it does
                            // not take AV and carries no room charge.
                            $isRoomDayUse = ($roomArrival === $roomDeparture)
                                && $this->detailDayUseFlag($detail, (bool) ($validated['is_day_use'] ?? false));
                            $detail = $this->applyConfiguredRateCodePricing(
                                $detail,
                                $alloc,
                                $roomArrival,
                                $roomDeparture,
                                $isRoomDayUse
                            );
                            $roomRate = (float) ($detail['price'] ?? $alloc['price'] ?? 0);

                            $bRoom = \App\Models\BookingRoom::create([
                                'booking_id' => $booking->id,
                                'room_number' => $detail['roomNumber'] ?? null,
                                'room_class_id' => $alloc['roomClassId'] ?? null,
                                'original_room_class_id' => $alloc['roomClassId'] ?? null,
                                'arrival_date' => $roomArrival,
                                'departure_date' => $roomDeparture,
                                'arrival_time' => $detail['arrivalTime'] ?? null,
                                'departure_time' => $detail['hoursOut'] ?? null,
                                'rate' => $detail['price'] ?? $alloc['price'] ?? 0,
                                'rate_code' => array_key_exists('rateCode', $detail)
                                    ? (filled($detail['rateCode']) ? $detail['rateCode'] : null)
                                    : ($alloc['rateCode'] ?? null),
                                'breakfast' => isset($detail['breakfast']) ? !empty($detail['breakfast']) : !empty($alloc['breakfastIncluded']),
                                'discount' => $alloc['discount'] ?? null,
                                'discount_type' => $alloc['discountType'] ?? null,
                                'discount_value' => $alloc['discountValue'] ?? 0,
                                'discount_unit' => $alloc['discountUnit'] ?? null,
                                // Keep rate and base_price in sync when a
                                // room price is edited at booking creation.
                                'base_price' => $roomRate,
                                'adults' => $detail['adults'] ?? 2,
                                'babies' => $detail['babies'] ?? 0,
                                'children_qty' => $detail['children'] ?? 0,
                                'is_day_use' => $isRoomDayUse,
                                'extra_bed_qty' => (int)($detail['extraBedQty'] ?? (empty($detail['extraBedPrice']) ? 0 : 1)),
                                'extra_bed_rate' => $detail['extraBedPrice'] ?? 0,
                                'status' => \App\Models\BookingRoom::STATUS_BOOKED,
                            ]);
                            $this->upsertRoomChargeServices($bRoom, $detail);
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
                    $depositDepartmentId = ModuleCode::normalize($validated['module'] ?? null) === ModuleCode::FRONTDESK
                        ? ModuleCode::FRONTDESK
                        : 'MR';
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

                        $paymentMethodInput = $dep['paymentMethodId'] ?? $booking->payment_method_id;
                        $paymentMethod = is_numeric($paymentMethodInput)
                            ? PaymentMethod::find($paymentMethodInput)
                            : PaymentMethod::where('code', $paymentMethodInput)->first();
                        $bankAccountId = $dep['bankAccountId'] ?? $dep['bank_account_id'] ?? null;
                        $bankAccount = $this->resolveDepositBankAccount($bankAccountId);

                        \App\Models\Payment::create([
                            'booking_id'        => $booking->id,
                            'company_id'        => $booking->company_id,
                            'date'              => $paymentDate,
                            'open_time'         => !empty($dep['time']) ? $dep['time'] . ':00' : now()->format('H:i:s'),
                            'guest_display'     => $booking->booking_code . ' - ' . $booking->booking_name,
                            'description'       => $dep['note'] ?? 'Đặt cọc',
                            'amount'            => $dep['amount'],
                            'currency'          => $dep['currency'] ?? 'VND',
                            'pack2'             => \App\Models\Payment::PACK2_DEPOSIT,
                            // Cọc tạo cùng đăng ký luôn là cọc chung của Master tại Folio 1.
                            'folio_id'          => 1,
                            'payment_method_id' => $paymentMethod?->code ?? $paymentMethodInput,
                            'debit_account'     => $dep['debit_account'] ?? $bankAccount?->accounting_account,
                            'bank_account_id'   => $bankAccount?->id,
                            'department_id'     => $depositDepartmentId,
                            'outlet'            => 'RC',
                            'status'            => \App\Models\Payment::STATUS_PENDING,
                            'edit_flag'         => 0,
                            'created_by'        => Auth::user()?->username ?? 'system',
                        ]);
                    }

                    // Sync tổng cọc vào payment_value
                    $totalDeposit = \App\Models\Payment::where('booking_id', $booking->id)
                        ->where('pack2', \App\Models\Payment::PACK2_DEPOSIT)
                        ->where('edit_flag', 0)
                        ->whereNull('deleted_at')
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
            'bookingRooms.movedToRoom.room',
            'bookingRooms.movedFromRoom.room',
            'bookingRooms.guests.guest',
            'bookingRooms.children.breakfastDetails',
            'bookingRooms.services',
            'bookingRooms.serviceBills',
            'bookingRooms.currentServiceBills',
            'masterServiceBills',
            'bookingRooms.specialRequests.specialRequest',
            'payments.paymentMethod',
            'payments.user',
            'payments.bankAccount',
        ]);

        try {
            \App\Services\ActivityLogService::logBookingCreated($booking, $request);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Log Booking create error: ' . $e->getMessage());
        }

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
            'bookingRooms.children.breakfastDetails',
            'bookingRooms.services',
            'bookingRooms.serviceBills',
            'bookingRooms.currentServiceBills',
            'masterServiceBills',
            'bookingRooms.specialRequests.specialRequest',
            'payments.paymentMethod',
            'payments.user',
            'payments.bankAccount',
        ])->withSum([
            'payments as active_deposit_total' => function ($paymentQuery) {
                $paymentQuery
                    ->where('pack2', Payment::PACK2_DEPOSIT)
                    ->where('edit_flag', 0)
                    ->whereNull('deleted_at');
            },
        ], 'amount')->find($id);

        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đăng ký!'], 404);
        }

        $this->hideCancelledRoomsForActiveBooking($booking);

        return response()->json([
            'success' => true,
            'data'    => $booking,
        ]);
    }

    /**
     * A room cancellation does not cancel its booking header.  Keep cancelled
     * room history available only when the header itself is cancelled, which
     * matches the legacy Booking screen and avoids showing a false cancelled
     * room under active bookings.
     */
    private function hideCancelledRoomsForActiveBooking(Booking $booking): void
    {
        if ((int) $booking->status === Booking::STATUS_DELETED
            || !$booking->relationLoaded('bookingRooms')) {
            return;
        }

        $booking->setRelation(
            'bookingRooms',
            $booking->bookingRooms
                ->reject(fn (BookingRoom $room) => (int) $room->status === BookingRoom::STATUS_CANCELLED)
                ->values(),
        );
    }

    /**
     * Append-only save for the reservation form's "Lấy phòng" tab.
     */
    public function addRooms(Request $request, $id)
    {
        $booking = Booking::find($id);
        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đăng ký!'], 404);
        }

        if ($error = $this->serviceOnlyMutationError($booking)) {
            return $error;
        }

        if ((int) $booking->status === Booking::STATUS_CHECKOUT) {
            return $this->updateCheckedOutBookingMetadata($request, $booking);
        }

        if (in_array($booking->status, [Booking::STATUS_CHECKOUT, Booking::STATUS_DELETED], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể thêm phòng vào đăng ký đã checkout hoặc đã xóa!',
            ], 422);
        }

        $validated = $request->validate([
            'intent'           => 'required|in:add_only',
            'room_allocations' => 'required|array',
        ]);

        try {
            DB::transaction(function () use ($booking, $validated) {
                $allocations = $validated['room_allocations'];
                $this->validateAddOnlyRoomAllocations($booking, $allocations);

                $arrivalDate = Carbon::parse($booking->arrival_date)->toDateString();
                $departureDate = Carbon::parse($booking->departure_date)->toDateString();
                $regId = $validated['registration_status_id'] ?? $booking->registration_status_id;
                $isWaiting = false;
                if ($regId) {
                    $reg = RegistrationStatus::where('booking_status_id', (int) $regId)->first();
                    $isWaiting = $reg && !$reg->is_availability;
                }
                $this->validateRoomAllocations($allocations, $arrivalDate, $departureDate, $booking->id, $isWaiting);

                foreach ($allocations as $allocation) {
                    $quantity = (int) $allocation['quantity'];
                    $details = $allocation['rooms'] ?? [];

                    for ($index = 0; $index < $quantity; $index++) {
                        $this->createAdditionalBookingRoom(
                            $booking,
                            $allocation,
                            $details[$index] ?? []
                        );
                    }
                }

                app(\App\Services\BookingStatusSyncService::class)->sync($booking, Booking::STATUS_RESERVATION);
            });
        } catch (\Throwable $e) {
            Log::error('Exception in add-only booking rooms: ' . $e->getMessage(), [
                'booking_id' => $booking->id,
            ]);

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
            'bookingRooms.children.breakfastDetails',
            'bookingRooms.services',
            'bookingRooms.specialRequests.specialRequest',
            'payments.paymentMethod',
        ]);

        return response()->json([
            'success' => true,
            'data' => $booking,
            'message' => 'Thêm phòng thành công!',
        ]);
    }

    /**
     * Cập nhật booking.
     */
    private function updateCheckedOutBookingMetadata(Request $request, Booking $booking)
    {
        $user = $request->user();
        $branchId = $request->attributes->get('_branch_id') ?? $user?->primary_branch_id;
        $canUpdate = app(\App\Services\CheckoutRoleConfigService::class)
            ->canUpdateCheckedOutBooking($user, $branchId !== null ? (int) $branchId : null);
        if (!$canUpdate) {
            return response()->json(['success' => false, 'message' => 'Bạn không có quyền chỉnh sửa thông tin đăng ký đã checkout.'], 403);
        }

        $allowedFields = ['booking_name', 'customer_source_id', 'booker_id', 'note'];
        $forbiddenFields = array_values(array_diff(array_keys($request->all()), $allowedFields));
        if ($forbiddenFields !== []) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ được chỉnh sửa tên đăng ký, nguồn khách, người đặt và ghi chú sau checkout.',
                'errors' => ['fields' => $forbiddenFields],
            ], 422);
        }
        if (array_intersect(array_keys($request->all()), $allowedFields) === []) {
            return response()->json(['success' => false, 'message' => 'Không có thông tin được phép chỉnh sửa.'], 422);
        }

        $validated = $request->validate([
            'booking_name' => 'sometimes|required|string|max:255',
            'customer_source_id' => 'sometimes|nullable|exists:customer_sources,id',
            'booker_id' => 'sometimes|nullable|exists:bookers,id',
            'note' => 'sometimes|nullable|string',
        ]);

        $changes = [];
        $fieldLabels = ['booking_name' => 'Tên đăng ký', 'customer_source_id' => 'Nguồn khách', 'booker_id' => 'Người đặt', 'note' => 'Ghi chú'];
        foreach ($fieldLabels as $field => $label) {
            if (array_key_exists($field, $validated) && $booking->{$field} != $validated[$field]) {
                $changes[] = '-'.$label.': '.(string) ($validated[$field] ?? '');
            }
        }
        $validated['edit_count'] = (int) $booking->edit_count + 1;
        $validated['edit_date'] = now();
        $validated['updated_by'] = $user?->username ?? 'system';
        $validated['updated_by_user_id'] = $user?->getKey();
        $booking->fill($validated)->save();
        $booking->refresh()->load(['customerSource', 'booker']);
        try {
            \App\Services\ActivityLogService::logBookingUpdated($booking, $changes, $request);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Log checked-out booking update error: '.$e->getMessage());
        }

        return response()->json([
            'success' => true,
            'data' => $booking,
            'message' => 'Cập nhật thông tin đăng ký đã checkout thành công.',
        ]);
    }
    public function update(Request $request, $id)
    {
        $booking = Booking::find($id);
        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đăng ký!'], 404);
        }

        if ($error = $this->serviceOnlyMutationError($booking)) {
            return $error;
        }

        if ((int) $booking->status === Booking::STATUS_CHECKOUT) {
            return $this->updateCheckedOutBookingMetadata($request, $booking);
        }

        // Không cho sửa booking đã checkout hoặc đã xóa
        if ((int) $booking->status === Booking::STATUS_DELETED) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể chỉnh sửa đăng ký đã xóa!',
            ], 422);
        }

        $allowAddingRoomsOnly = (int) $booking->status === Booking::STATUS_CHECKIN
            && $booking->bookingRooms()
                ->whereIn('status', [
                    BookingRoom::STATUS_CHECKED_IN,
                    BookingRoom::STATUS_CHECKED_OUT,
                    BookingRoom::STATUS_CANCELLED,
                    100,
                ])
                ->exists();

        try {
            $validated = $request->validate([
                'booking_name'             => 'sometimes|required|string|max:255',
                'arrival_date'             => 'sometimes|required|date',
                'departure_date'           => 'sometimes|required|date|after_or_equal:arrival_date',
                'num_of_days'              => 'sometimes|required|integer|min:0',
                'confirm_date'             => 'nullable|date',
                'expired_date'             => 'nullable|date',
                'arrival_flight'           => 'nullable|string|max:50',
                'arrival_flight_date'      => 'nullable|date',
                'departure_flight'         => 'nullable|string|max:50',
                'departure_flight_date'    => 'nullable|date',
                'status'                   => 'nullable|integer',
                'registration_status_id'   => 'required|exists:registration_statuses,booking_status_id',
                'color'                    => 'nullable|string|max:20',
                'is_git'                   => 'nullable|boolean',
                'is_master_room_rate'      => 'nullable|boolean',
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

        // Confirmation date is calculated once when a booking is created.
        // Legacy edits may still submit the form field, but changing arrival
        // date/status must never recalculate or overwrite the stored value.
        unset($validated['confirm_date']);

        // Tự động tăng edit_count và ghi nhận người sửa
        $validated['edit_count']  = $booking->edit_count + 1;
        $validated['edit_date']   = now();
        $validated['updated_by']  = Auth::user()?->username ?? 'system';
        $validated['updated_by_user_id'] = Auth::id();
        
        // Tiền phòng gửi về master luôn luôn bật (is_git = 1)
        $validated['is_git'] = true;

        $bArr = isset($validated['arrival_date']) ? Carbon::parse($validated['arrival_date'])->toDateString() : Carbon::parse($booking->arrival_date)->toDateString();
        $bDep = isset($validated['departure_date']) ? Carbon::parse($validated['departure_date'])->toDateString() : Carbon::parse($booking->departure_date)->toDateString();

        $effectiveDayUse = array_key_exists('is_day_use', $validated)
            ? (bool) $validated['is_day_use']
            : ($bArr === $bDep && (bool) $booking->is_day_use);
        if ($bArr !== $bDep) {
            $effectiveDayUse = false;
        }
        $validated['is_day_use'] = $effectiveDayUse;
        // Dòng 366: số đêm luôn tính từ ngày đến/ngày đi, không tin số đêm
        // cũ mà màn hình gửi lên sau khi gia hạn.
        $validated['num_of_days'] = $effectiveDayUse
            ? 0
            : Carbon::parse($bArr)->diffInDays(Carbon::parse($bDep));

        // Lấy ngày hệ thống
        $systemDate = SystemDateRoll::latest('id')->first();
        $sysDateStr = $systemDate
            ? Carbon::parse($systemDate->system_date)->toDateString()
            : now()->toDateString();

        // Chặn nếu ngày đến mới thay đổi và nhỏ hơn ngày hệ thống
        if (isset($validated['arrival_date']) 
            && $validated['arrival_date'] !== Carbon::parse($booking->arrival_date)->toDateString()
            && $validated['arrival_date'] < $sysDateStr
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Ngày đến không được nhỏ hơn ngày hệ thống (' . $sysDateStr . ').',
                'errors'  => ['arrival_date' => ['Ngày đến phải >= ngày hệ thống: ' . $sysDateStr]],
            ], 422);
        }

        // Chặn nếu cập nhật ngày booking làm ngày phòng nằm ngoài giai đoạn đăng ký mới
        $newArrival = $validated['arrival_date'] ?? Carbon::parse($booking->arrival_date)->toDateString();
        $newDeparture = $validated['departure_date'] ?? Carbon::parse($booking->departure_date)->toDateString();
        $syncRoomDates = \App\Models\HotelConfig::where('name', 'SyncRoomDateByBookingDate')->first()?->value == '1';
        $bookingDatesChanged = (array_key_exists('arrival_date', $validated)
                && $validated['arrival_date'] !== Carbon::parse($booking->getOriginal('arrival_date'))->toDateString())
            || (array_key_exists('departure_date', $validated)
                && $validated['departure_date'] !== Carbon::parse($booking->getOriginal('departure_date'))->toDateString());
        // Row 275 (23/10): a past header arrival must not suppress departure
        // synchronization for reservation rooms added later to an in-house booking.
        $canSyncArrivalDate = $newArrival >= $sysDateStr;
        $canSyncRoomDates = $syncRoomDates && ($canSyncArrivalDate || $newDeparture >= $sysDateStr);

        // Dòng 394: giai đoạn của mọi phòng lưu trú (đăng ký, đang ở, đã trả)
        // phải nằm trong giai đoạn đăng ký. Phòng đăng ký được đồng bộ theo
        // SyncRoomDateByBookingDate thì sẽ đi theo đăng ký nên bỏ qua.
        // Chỉ kiểm tra DB khi request KHÔNG gửi room_allocations; khi có
        // room_allocations, kiểm tra lại ở cuối transaction.
        if (!($request->has('room_allocations') && is_array($request->room_allocations))) {
            $rooms = $booking->bookingRooms()->whereIn('status', self::PERIOD_BOUND_ROOM_STATUSES)->get();
            foreach ($rooms as $room) {
                $isBooked = (int) $room->status === \App\Models\BookingRoom::STATUS_BOOKED;
                if ($isBooked && $canSyncRoomDates && $bookingDatesChanged) {
                    continue;
                }

                if ($this->roomOutsideBookingPeriod($room, $newArrival, $newDeparture)) {
                    return response()->json([
                        'success' => false,
                        'message' => self::BOOKING_PERIOD_MISMATCH_MESSAGE,
                    ], 422);
                }
            }
        }

        // Dòng 446: đổi tình trạng đăng ký giữa nhóm trừ phòng trống (is_availability = 1)
        // và nhóm không trừ phòng trống (is_availability = 0, VD Waiting).
        $oldRegistration = $booking->registration_status_id !== null
            ? RegistrationStatus::where('booking_status_id', (int) $booking->registration_status_id)->first()
            : null;
        $newRegistration = array_key_exists('registration_status_id', $validated)
            && (int) $validated['registration_status_id'] !== (int) $booking->registration_status_id
            ? RegistrationStatus::where('booking_status_id', (int) $validated['registration_status_id'])->first()
            : null;
        $isInHouseBooking = (int) $booking->status === Booking::STATUS_CHECKIN
            || $booking->bookingRooms()->where('status', BookingRoom::STATUS_CHECKED_IN)->exists();
        if ($oldRegistration?->is_availability && $newRegistration && !$newRegistration->is_availability && $isInHouseBooking) {
            return response()->json([
                'success' => false,
                'message' => "Đăng ký đang ở không được chuyển sang tình trạng {$newRegistration->name} (không tính vào phòng trống).",
            ], 422);
        }
        $becomesAvailable = $oldRegistration && !$oldRegistration->is_availability
            && $newRegistration && $newRegistration->is_availability;
        $overBlock = null;

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($booking, $validated, $request, $allowAddingRoomsOnly, $bookingDatesChanged, $canSyncRoomDates, $canSyncArrivalDate, $syncRoomDates, $becomesAvailable, &$overBlock) {
                // Đồng bộ ngày của phòng theo cấu hình SyncRoomDateByBookingDate
                if ($canSyncRoomDates
                    && $bookingDatesChanged
                    && !($request->has('room_allocations') && is_array($request->room_allocations))) {
                    // Header edits can omit room_allocations.  Still run the
                    // same AV/over-room validation for every reservation room
                    // before propagating the new header period.
                    $syncAllocations = $booking->bookingRooms()
                        ->where('status', \App\Models\BookingRoom::STATUS_BOOKED)
                        ->get()
                        ->groupBy('room_class_id')
                        ->map(fn ($classRooms, $roomClassId) => [
                            'roomClassId' => (int) $roomClassId,
                            'quantity' => $classRooms->count(),
                            'rooms' => $classRooms->map(fn ($room) => [
                                'bookingRoomId' => $room->id,
                                'roomNumber' => $room->room_number,
                                'arrivalDate' => $canSyncArrivalDate
                                    ? ($validated['arrival_date'] ?? $booking->arrival_date)
                                    : $room->arrival_date,
                                'departureDate' => $validated['departure_date'] ?? $booking->departure_date,
                            ])->values()->all(),
                        ])->values()->all();

                    $regId = $validated['registration_status_id'] ?? $booking->registration_status_id;
                    $isWaiting = false;
                    if ($regId) {
                        $reg = RegistrationStatus::where('booking_status_id', (int) $regId)->first();
                        $isWaiting = $reg && !$reg->is_availability;
                    }

                    $this->validateRoomAllocations(
                        $syncAllocations,
                        $validated['arrival_date'] ?? $booking->arrival_date,
                        $validated['departure_date'] ?? $booking->departure_date,
                        $booking->id,
                        $isWaiting,
                    );
                }

                if ($canSyncRoomDates && $bookingDatesChanged
                    && !($request->has('room_allocations') && is_array($request->room_allocations))) {
                    $roomsToSync = $booking->bookingRooms()->where('status', \App\Models\BookingRoom::STATUS_BOOKED)->get();
                    foreach ($roomsToSync as $roomToSync) {
                        $syncData = [];
                        if ($canSyncArrivalDate && array_key_exists('arrival_date', $validated)) {
                            $syncData['arrival_date'] = $validated['arrival_date'];
                        }
                        if (array_key_exists('departure_date', $validated)) {
                            $syncData['departure_date'] = $validated['departure_date'];
                        }
                        $roomToSync->update($syncData);
                        app(\App\Services\BookingRoomLifecycleService::class)
                            ->synchronize($roomToSync->fresh(), false, true, true);
                    }
                }

                $booking->update($validated);
                $dayUseChanged = $booking->wasChanged('is_day_use');
                $companyChanged = $booking->wasChanged('company_id');
                // Do not use a mass update here: it bypasses BookingRoom's
                // lifecycle hooks (night count and guest actual dates).
                $booking->bookingRooms()
                    ->where('status', \App\Models\BookingRoom::STATUS_BOOKED)
                    ->get()
                    ->each(function (\App\Models\BookingRoom $room) use ($booking, $dayUseChanged, $request) {
                        // room_allocations carry the per-room day-use switch and
                        // are applied below; the header flag only decides when
                        // the request has no room detail.
                        $sameDay = $room->arrival_date->toDateString() === $room->departure_date->toDateString();
                        $roomDayUse = $sameDay && ($request->has('room_allocations') && is_array($request->room_allocations)
                            ? (bool) $room->is_day_use
                            : (bool) $booking->is_day_use);
                        $room->update(['is_day_use' => $roomDayUse]);
                        app(\App\Services\BookingRoomLifecycleService::class)
                            ->synchronize($room->fresh(), false, $dayUseChanged, false);
                    });

                // Đồng bộ room_allocations (từ UI gửi lên) - xử lý thông minh để cập nhật thay vì xóa/tạo lại
                if ($request->has('room_allocations') && is_array($request->room_allocations)) {
                    // Normalize the UI contract before touching persisted rows:
                    // quantity is the current-room target, while cancelled,
                    // no-show and moved rows are history only.
                    $roomAllocations = $this->normalizeRoomAllocations(
                        $request->room_allocations,
                        $booking->id
                    );

                    // Tìm các phòng đang ở trạng thái BOOKED (chưa check-in) hiện tại trong db
                    $existingBookedRooms = \App\Models\BookingRoom::where('booking_id', $booking->id)
                        ->where('status', \App\Models\BookingRoom::STATUS_BOOKED)
                        ->get();
                    $bookedRoomIds = $existingBookedRooms->pluck('id')->toArray();

                    // The header-date sync owns reservation-room dates when
                    // enabled.  Normalize those dates before availability
                    // validation; otherwise the UI's stale old room dates
                    // would be rejected as outside the newly edited header
                    // period before the sync can be applied.
                    if ($canSyncRoomDates && $bookingDatesChanged) {
                        foreach ($roomAllocations as &$allocation) {
                            $allocation['rooms'] ??= [];
                            foreach ($allocation['rooms'] as &$allocationRoom) {
                                if (in_array($allocationRoom['bookingRoomId'] ?? null, $bookedRoomIds, true)) {
                                    $allocationRoom['arrivalDate'] = $canSyncArrivalDate
                                        ? ($validated['arrival_date'] ?? $booking->arrival_date)
                                        : $existingBookedRooms->firstWhere('id', $allocationRoom['bookingRoomId'])->arrival_date->toDateString();
                                    $allocationRoom['departureDate'] = $validated['departure_date'] ?? $booking->departure_date;
                                }
                            }
                            unset($allocationRoom);
                        }
                        unset($allocation);
                    }

                    // 1. Tạm thời xóa mềm tất cả các phòng BOOKED hiện tại để validateAvailability không tính trùng chính nó
                    if (count($bookedRoomIds) > 0) {
                        \App\Models\BookingRoom::whereIn('id', $bookedRoomIds)->delete();
                    }

                    try {
                        // Thực hiện validation dựa trên payload mới
                        $bArrDate = $booking->arrival_date ? Carbon::parse($booking->arrival_date)->toDateString() : now()->toDateString();
                        $bDepDate = $booking->departure_date ? Carbon::parse($booking->departure_date)->toDateString() : now()->toDateString();
                        $regId = $validated['registration_status_id'] ?? $booking->registration_status_id;
                        $isWaiting = false;
                        if ($regId) {
                            $reg = RegistrationStatus::where('booking_status_id', (int) $regId)->first();
                            $isWaiting = $reg && !$reg->is_availability;
                        }

                        $this->validateRoomAllocations(
                            $roomAllocations,
                            $validated['arrival_date'] ?? $bArrDate,
                            $validated['departure_date'] ?? $bDepDate,
                            $booking->id,
                            $isWaiting
                        );
                    } catch (\Exception $e) {
                        // Restore lại toàn bộ nếu validate lỗi để không mất dữ liệu của người dùng
                        if (count($bookedRoomIds) > 0) {
                            \App\Models\BookingRoom::withTrashed()->whereIn('id', $bookedRoomIds)->restore();
                        }
                        throw $e;
                    }

                    $restoredRoomIds = [];

                    foreach ($roomAllocations as $alloc) {
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
                            if (!$allowAddingRoomsOnly && $bRoom && $bRoom->status === \App\Models\BookingRoom::STATUS_CHECKED_IN) {
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

                            if ($allowAddingRoomsOnly && $bRoom && $bRoom->status !== \App\Models\BookingRoom::STATUS_BOOKED) {
                                continue;
                            }
                            
                            $roomArrival = $detail['arrivalDate'] ?? $detail['checkIn'] ?? null;
                            $roomDeparture = $detail['departureDate'] ?? $detail['checkOut'] ?? null;

                            $parseDate = function ($date) {
                                if (!$date) return null;
                                $date = trim($date);
                                if (strpos($date, '/') !== false) {
                                    $parts = explode('/', $date);
                                    if (count($parts) === 3) {
                                        if (strlen($parts[2]) === 4) {
                                            return "{$parts[2]}-{$parts[1]}-{$parts[0]}";
                                        }
                                    }
                                }
                                return Carbon::parse($date)->toDateString();
                            };

                            $roomArrival = $parseDate($roomArrival);
                            $roomDeparture = $parseDate($roomDeparture);

                            if ($bRoom && $bRoom->status !== \App\Models\BookingRoom::STATUS_BOOKED) {
                                // For checked-in or checked-out rooms: keep their original dates when booking dates change.
                                // We only use values from $detail if explicitly provided.
                                $roomArrival = $roomArrival ?? Carbon::parse($bRoom->arrival_date)->toDateString();
                                $roomDeparture = $roomDeparture ?? Carbon::parse($bRoom->departure_date)->toDateString();
                            } else {
                                // For reservation rooms (STATUS_BOOKED), the
                                // header edit owns child dates only when the
                                // legacy synchronization flag is enabled.
                                // Dòng 366: ngày phòng sửa trực tiếp trên màn hình
                                // booking luôn được lưu; cấu hình chỉ quyết định
                                // việc đổi ngày đăng ký có kéo theo ngày phòng.
                                if (!$syncRoomDates && $bRoom) {
                                    $roomArrival = $roomArrival ?? Carbon::parse($bRoom->arrival_date)->toDateString();
                                    $roomDeparture = $roomDeparture ?? Carbon::parse($bRoom->departure_date)->toDateString();
                                } elseif ($syncRoomDates && $bookingDatesChanged && $canSyncRoomDates) {
                                    $roomArrival = $canSyncArrivalDate
                                        ? $parseDate($validated['arrival_date'] ?? $booking->arrival_date)
                                        : ($bRoom ? Carbon::parse($bRoom->arrival_date)->toDateString() : ($roomArrival ?? $parseDate($booking->arrival_date)));
                                    $roomDeparture = $parseDate($validated['departure_date'] ?? $booking->departure_date);
                                } elseif ($syncRoomDates) {
                                    $roomArrival = $roomArrival
                                        ?? ($bRoom
                                            ? Carbon::parse($bRoom->arrival_date)->toDateString()
                                            : $parseDate($validated['arrival_date'] ?? $booking->arrival_date));
                                    $roomDeparture = $roomDeparture
                                        ?? ($bRoom
                                            ? Carbon::parse($bRoom->departure_date)->toDateString()
                                            : $parseDate($validated['departure_date'] ?? $booking->departure_date));
                                } else {
                                    $roomArrival = $roomArrival ?? $parseDate($validated['arrival_date'] ?? $booking->arrival_date);
                                    $roomDeparture = $roomDeparture ?? $parseDate($validated['departure_date'] ?? $booking->departure_date);
                                }
                            }

                            // The room's own "Ở theo giờ" switch is authoritative.
                            // A same-day room with the switch off stays a
                            // non day-use room (no AV, no room charge).
                            $isRoomDayUse = $roomArrival === $roomDeparture
                                && $this->detailDayUseFlag(
                                    $detail,
                                    $bRoom ? (bool) $bRoom->is_day_use : (bool) $booking->is_day_use
                                );
                            $detail = $this->applyConfiguredRateCodePricing(
                                $detail,
                                $alloc,
                                $roomArrival,
                                $roomDeparture,
                                $isRoomDayUse
                            );
                            $roomRate = (float) ($detail['price'] ?? $alloc['price'] ?? ($bRoom?->rate ?? 0));

                            $roomData = [
                                'booking_id' => $booking->id,
                                'room_number' => $detail['roomNumber'] ?? null,
                                'room_class_id' => $alloc['roomClassId'] ?? null,
                                'original_room_class_id' => $alloc['roomClassId'] ?? null,
                                'arrival_date' => $roomArrival,
                                'departure_date' => $roomDeparture,
                                'actual_arrival_date' => ($bRoom && (int)$bRoom->status !== \App\Models\BookingRoom::STATUS_BOOKED && $bRoom->actual_arrival_date)
                                    ? $bRoom->actual_arrival_date->toDateString()
                                    : $roomArrival,
                                'arrival_time' => $detail['arrivalTime'] ?? null,
                                'departure_time' => $detail['hoursOut'] ?? null,
                                'rate' => $roomRate,
                                'rate_code' => array_key_exists('rateCode', $detail)
                                    ? (filled($detail['rateCode']) ? $detail['rateCode'] : null)
                                    : ($alloc['rateCode'] ?? null),
                                'breakfast' => isset($detail['breakfast']) ? !empty($detail['breakfast']) : !empty($alloc['breakfastIncluded']),
                                'is_day_use' => $isRoomDayUse,
                                'discount' => $detail['discount'] ?? $alloc['discount'] ?? null,
                                'discount_type' => $detail['discountType'] ?? $alloc['discountType'] ?? null,
                                'discount_value' => $detail['discountValue'] ?? $alloc['discountValue'] ?? 0,
                                'discount_unit' => $detail['discountUnit'] ?? $alloc['discountUnit'] ?? null,
                                // An edited room price is the new base for
                                // subsequent nightly adjustments as well.
                                'base_price' => $roomRate,
                                'adults' => $detail['adults'] ?? 2,
                                'babies' => $detail['babies'] ?? 0,
                                'children_qty' => $detail['children'] ?? 0,
                                'extra_bed_qty' => (int)($detail['extraBedQty'] ?? (empty($detail['extraBedPrice']) ? 0 : 1)),
                                'extra_bed_rate' => $detail['extraBedPrice'] ?? 0,
                                'status' => $bRoom ? $bRoom->status : \App\Models\BookingRoom::STATUS_BOOKED,
                            ];

                            $roomStayChanged = false;
                            if ($bRoom) {
                                $roomStayChanged = $bRoom->arrival_date?->toDateString() !== $roomArrival
                                    || $bRoom->departure_date?->toDateString() !== $roomDeparture;
                                $bRoom->update($roomData);
                            } else {
                                $bRoom = \App\Models\BookingRoom::create($roomData);
                            }
                            $this->upsertBookingRoomServices($bRoom, $detail);

                            // Projected room charge/extra bed rows belong to the
                            // stay nights only. Additional services (SP2102) are
                            // kept outside the stay so an extension restores them;
                            // screens only count the rows inside the stay period.
                            $stayBoundServiceCodes = collect([
                                \App\Models\BookingRoomService::CODE_ROOM,
                                \App\Models\BookingRoomService::catalogCode(\App\Models\BookingRoomService::CODE_ROOM),
                                \App\Models\BookingRoomService::CODE_EXTRA_BED,
                                \App\Models\BookingRoomService::catalogCode(\App\Models\BookingRoomService::CODE_EXTRA_BED),
                            ])->unique()->values()->all();
                            // Day-use keeps its single charge on the arrival date.
                            $stayEnd = $bRoom->is_day_use
                                ? $bRoom->arrival_date->copy()->addDay()->toDateString()
                                : $bRoom->departure_date->toDateString();
                            \App\Models\BookingRoomService::where('booking_room_id', $bRoom->id)
                                ->whereIn('service_code', $stayBoundServiceCodes)
                                ->where('is_posted', 0)
                                ->where(function ($query) use ($bRoom, $stayEnd) {
                                    $query->where('service_date', '>=', $stayEnd)
                                        ->orWhere('service_date', '<', $bRoom->arrival_date->toDateString());
                                })
                                ->delete();

                            if ($roomStayChanged) {
                                // Keep child-breakfast price and surcharge flags,
                                // moving only their service dates to the new stay.
                                // Đêm gia hạn mới phải có tiền phòng; đêm cũ giữ giá.
                                app(\App\Services\BookingRoomLifecycleService::class)
                                    ->synchronize($bRoom->fresh(), false, true, true);
                            }

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
                                $pivotUpdate = ['status' => $bRoom->status];
                                if ((int) $bRoom->status === \App\Models\BookingRoom::STATUS_BOOKED) {
                                    $pivotUpdate['actual_arrival_date'] = $bRoom->arrival_date;
                                    $pivotUpdate['actual_checkout_date'] = $bRoom->departure_date;
                                }
                                $pivot->update($pivotUpdate);
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
                            
                            // Cập nhật status và ngày cho khách phụ hiện có
                            foreach ($secondaries as $subPivot) {
                                $subPivotUpdate = ['status' => $bRoom->status];
                                if ((int) $bRoom->status === \App\Models\BookingRoom::STATUS_BOOKED) {
                                    $subPivotUpdate['actual_arrival_date'] = $bRoom->arrival_date;
                                    $subPivotUpdate['actual_checkout_date'] = $bRoom->departure_date;
                                }
                                $subPivot->update($subPivotUpdate);
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

                                    // Chỉ dọn dẹp khách ảo (Guest X) tự sinh nếu không còn bất kỳ liên kết phòng/dịch vụ nào
                                    if ($gId) {
                                        try {
                                            $hasOtherReferences = \App\Models\BookingRoomGuest::where('guest_id', $gId)->exists()
                                                || \App\Models\BookingRoomService::where('guest_id', $gId)->exists()
                                                || \App\Models\Payment::where('guest_id', $gId)->exists();
                                            if (!$hasOtherReferences) {
                                                $guestObj = \App\Models\Guest::find($gId);
                                                if ($guestObj && str_starts_with($guestObj->full_name, 'Guest ') && empty($guestObj->id_number) && empty($guestObj->passport_number)) {
                                                    $guestObj->delete();
                                                }
                                            }
                                        } catch (\Throwable $ignored) {
                                            // Không để lỗi dọn dẹp khách ảo làm gián đoạn cập nhật đặt phòng
                                        }
                                    }
                                }
                            }

                            // Preserve existing children/details on a normal
                            // booking edit:
                            // - Tăng: chỉ tạo thêm trẻ mới, giữ nguyên trẻ cũ
                            // - Giảm: chỉ xóa các trẻ có mã lớn nhất (từ trên xuống)
                            $syncChildCount = function (string $ageGroup, string $field, string $label) use ($detail, $bRoom, $booking): void {
                                if (!array_key_exists($field, $detail)) {
                                    return;
                                }

                                $target = max(0, (int) $detail[$field]);
                                $existing = \App\Models\BookingChild::where('booking_room_id', $bRoom->id)
                                    ->where('age_group', $ageGroup)
                                    ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) ASC')
                                    ->get();
                                $current = $existing->count();

                                if ($target > $current) {
                                    $diff = $target - $current;
                                    for ($i = 0; $i < $diff; $i++) {
                                        $seq = $current + $i + 1;
                                        $child = \App\Models\BookingChild::create([
                                            'booking_id'      => $booking->id,
                                            'booking_room_id' => $bRoom->id,
                                            'full_name'       => $label . ' ' . $seq,
                                            'age_group'       => $ageGroup,
                                            'child_status'    => 1,
                                        ]);
                                        $this->createChildBreakfastDetails($child, $bRoom);
                                    }
                                } elseif ($target < $current) {
                                    $toRemove = $current - $target;
                                    $childrenToRemove = \App\Models\BookingChild::where('booking_room_id', $bRoom->id)
                                        ->where('age_group', $ageGroup)
                                        ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                                        ->take($toRemove)
                                        ->get();

                                    $removeIds = $childrenToRemove->pluck('id')->toArray();
                                    if (!empty($removeIds)) {
                                        \App\Models\BookingChildBreakfastDetail::whereIn('booking_child_id', $removeIds)->delete();
                                        \App\Models\BookingRoomChild::whereIn('booking_child_id', $removeIds)->delete();
                                        \App\Models\BookingChild::whereIn('id', $removeIds)->delete();
                                    }
                                    app(\App\Services\ChildBreakfastService::class)->removeProjectedRowsWithoutChildren($bRoom);
                                }

                                $col = $ageGroup === 'child' ? 'children_qty' : 'babies';
                                $bRoom->update([$col => $target]);
                            };

                            $syncChildCount('child', 'children', 'Child');
                            $syncChildCount('baby', 'babies', 'Baby');
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
                        
                        // Xóa các khách ảo tương ứng để tránh bị rác database (chỉ dọn placeholder guests tự sinh)
                        if ($guestIds->count() > 0) {
                            try {
                                $stillReferenced = \App\Models\BookingRoomGuest::whereIn('guest_id', $guestIds)->pluck('guest_id')->unique();
                                $orphanedGuestIds = $guestIds->diff($stillReferenced);
                                if ($orphanedGuestIds->count() > 0) {
                                    foreach ($orphanedGuestIds as $oId) {
                                        $guestObj = \App\Models\Guest::find($oId);
                                        if ($guestObj && str_starts_with($guestObj->full_name, 'Guest ') && empty($guestObj->id_number) && empty($guestObj->passport_number)) {
                                            $guestObj->delete();
                                        }
                                    }
                                }
                            } catch (\Throwable $ignored) {
                                // Không để lỗi dọn dẹp khách ảo làm gián đoạn cập nhật đặt phòng
                            }
                        }
                    }
                }

                if ($companyChanged) {
                    $this->cascadeBookingCompanyUpdate($booking, $booking->company_id);
                }

                // Dòng 394: kiểm tra trạng thái cuối cùng (sau đồng bộ ngày và
                // room_allocations). Lỗi sẽ rollback toàn bộ, không lưu gì.
                if ($bookingDatesChanged || ($request->has('room_allocations') && is_array($request->room_allocations))) {
                    $booking->refresh();
                    $finalArrival = Carbon::parse($booking->arrival_date)->toDateString();
                    $finalDeparture = Carbon::parse($booking->departure_date)->toDateString();
                    $outside = $booking->bookingRooms()
                        ->whereIn('status', self::PERIOD_BOUND_ROOM_STATUSES)
                        ->get()
                        ->contains(fn ($room) => $this->roomOutsideBookingPeriod($room, $finalArrival, $finalDeparture));
                    if ($outside) {
                        throw new \Exception(self::BOOKING_PERIOD_MISMATCH_MESSAGE);
                    }
                }

                // Dòng 446 / 549: chuyển từ tình trạng không trừ phòng trống sang tình trạng
                // trừ phòng trống thì phòng của BK bắt đầu chiếm quỹ phòng -> kiểm tra over.
                // Kiểm tra trên dữ liệu cuối cùng (đã tính phòng của BK), lỗi thì rollback.
                if ($becomesAvailable) {
                    $overRooms = $this->overRoomNightsForBooking($booking);
                    if ($overRooms) {
                        $allowOver = (string) (HotelConfig::where('name', 'AllowOverRoomTypeRoomKind')->value('value') ?? '0') === '1';
                        if (!$allowOver || !$request->boolean('force_over')) {
                            $overBlock = ['allow_over' => $allowOver, 'over_rooms' => $overRooms];
                            throw new \RuntimeException('over_on_registration_status_change');
                        }
                    }
                }
            });
        } catch (\Exception $e) {
            if ($overBlock !== null) {
                return $overBlock['allow_over']
                    ? response()->json([
                        'success' => false,
                        'needs_over_confirm' => true,
                        'over_rooms' => $overBlock['over_rooms'],
                        'message' => 'Loại phòng đang bị over bạn có muốn tiếp tục',
                    ], 409)
                    : response()->json([
                        'success' => false,
                        'blocked_by_over' => true,
                        'over_rooms' => $overBlock['over_rooms'],
                        'message' => 'Loại phòng đang bị over không thể cập nhật thông tin',
                    ], 422);
            }
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
            'bookingRooms.children.breakfastDetails',
            'bookingRooms.services',
            'bookingRooms.serviceBills',
            'bookingRooms.currentServiceBills',
            'bookingRooms.specialRequests.specialRequest',
            'payments.paymentMethod',
        ]);
        $this->hideCancelledRoomsForActiveBooking($booking);

        try {
            $changes = [];
            if ($booking->wasChanged('registration_status_id')) {
                $statusName = $booking->registrationStatus?->name ?? 'Mới';
                $changes[] = "-Trạng thái: {$statusName}";
            }
            if ($booking->wasChanged('arrival_date') || $booking->wasChanged('departure_date')) {
                $arr = $booking->arrival_date ? \Carbon\Carbon::parse($booking->arrival_date)->format('d/m/Y') : '';
                $dep = $booking->departure_date ? \Carbon\Carbon::parse($booking->departure_date)->format('d/m/Y') : '';
                $changes[] = "-Ngày ở: {$arr} ~ {$dep}";
            }
            if ($booking->wasChanged('note')) {
                $changes[] = "-Ghi chú: {$booking->note}";
            }
            if ($booking->wasChanged('company_id')) {
                $changes[] = "-Công ty: " . ($booking->company?->name ?? '');
            }
            \App\Services\ActivityLogService::logBookingUpdated($booking, $changes, $request);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Log Booking update error: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'data'    => $booking,
            'message' => 'Cập nhật đăng ký thành công!',
        ]);
    }

    /**
     * Dòng 446: các đêm (từ ngày hệ thống) mà loại phòng bị âm phòng trống sau khi
     * phòng của booking được tính vào quỹ phòng.
     */
    private function overRoomNightsForBooking(Booking $booking): array
    {
        $systemDate = SystemDateRoll::latest('id')->value('system_date');
        $systemDate = $systemDate ? Carbon::parse($systemDate)->toDateString() : now('Asia/Ho_Chi_Minh')->toDateString();
        $availability = app(RoomAvailabilityService::class);
        $rooms = $booking->bookingRooms()->stayOnly()
            ->whereIn('status', [BookingRoom::STATUS_BOOKED, BookingRoom::STATUS_CHECKED_IN])
            ->get();

        $overRooms = [];
        foreach ($rooms->groupBy('room_class_id') as $classId => $classRooms) {
            $className = RoomClass::find($classId)?->name ?? "ID {$classId}";
            foreach ($classRooms as $room) {
                $start = Carbon::parse($room->arrival_date)->max(Carbon::parse($systemDate));
                $end = Carbon::parse($room->departure_date);
                if ($start->gte($end) && !$room->is_day_use) continue;
                if ($start->gte($end)) $end = $start->copy()->addDay();

                for ($date = $start->copy(); $date->lt($end); $date->addDay()) {
                    $key = "{$classId}_{$date->toDateString()}";
                    if (isset($overRooms[$key])) continue;
                    $av = $availability->getAvailability((int) $classId, $date->toDateString(), $date->copy()->addDay()->toDateString());
                    if ($av < 0) {
                        $overRooms[$key] = ['room_class_id' => (int) $classId, 'class_name' => $className, 'date' => $date->toDateString(), 'av' => $av];
                    }
                }
            }
        }

        return array_values($overRooms);
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

        if ($error = $this->serviceOnlyMutationError($booking)) {
            return $error;
        }

        if ((int) $booking->status === Booking::STATUS_CHECKOUT) {
            return $this->updateCheckedOutBookingMetadata($request, $booking);
        }

        // Kiểm tra cấu hình CheckModuleBeforeDelete
        $checkModuleConfig = \Illuminate\Support\Facades\DB::table('hotel_configs')
            ->where('name', 'CheckModuleBeforeDelete')
            ->value('value');

        if ($checkModuleConfig === '1' || $checkModuleConfig === 1) {
            $currentModule = ModuleCode::normalize($request->input('current_module', ModuleCode::RESERVATION));
            $bookingModule = ModuleCode::normalize($booking->module, ModuleCode::RESERVATION);

            if ($bookingModule === ModuleCode::RESERVATION && $currentModule === ModuleCode::FRONTDESK) {
                return response()->json([
                    'success' => false,
                    'message' => 'Đăng ký được tạo bởi bộ phận đặt phòng. Bạn không có quyền được hủy.'
                ], 403);
            }
            if ($bookingModule === ModuleCode::FRONTDESK && $currentModule === ModuleCode::RESERVATION) {
                return response()->json([
                    'success' => false,
                    'message' => 'Đăng ký được tạo bởi bộ phận lễ tân. Bạn không có quyền được hủy.'
                ], 403);
            }
        }

        // Không cho xóa booking đã checkout hay đã xóa
        if ((int) $booking->status === Booking::STATUS_DELETED) {
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

        $systemDate = SystemDateRoll::latest('id')->value('system_date');
        $cancelledAt = Carbon::parse($systemDate ?? now())
            ->setTimeFromTimeString(now()->format('H:i:s'));

        DB::transaction(function () use ($booking, $id, $request, $reasonText, $cancelledAt) {
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
                'cancelled_at'          => $cancelledAt,
            ]);

            // 2. Cascade: Hủy và ghi log cho từng phòng trong booking (SP8052)
            $allRooms = $booking->bookingRooms;
            foreach ($allRooms as $bRoom) {
                // Phòng đã hủy từ lần trước đã có log riêng, không ghi lại lần nữa
                if ((int) $bRoom->status === BookingRoom::STATUS_CANCELLED) {
                    continue;
                }

                $guestIds = $bRoom->guests()->pluck('guest_id');
                $bRoom->guests()->update(['status' => 3]);
                app(\App\Services\GuestStatusSyncService::class)->syncForGuestIds($guestIds);
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
                    'cancelled_at'          => $cancelledAt,
                ]);
            }

            // Section 18: Thông số RegistrationStatusId_BookingCancel
            // Nếu giá trị khác 0: Bookings.registration_status_id lưu theo giá trị của thông số (VD: 28)
            // Nếu thông số = 0: giữ nguyên không thay đổi Bookings.registration_status_id
            $configuredStatusId = HotelConfig::where('name', 'RegistrationStatusId_BookingCancel')->value('value');
            $newRegistrationStatusId = $booking->registration_status_id;

            if ($configuredStatusId !== null && $configuredStatusId !== '' && is_numeric($configuredStatusId)) {
                $statusVal = (int) $configuredStatusId;
                if ($statusVal !== 0) {
                    $newRegistrationStatusId = $statusVal;
                }
            }

            // Giai đoạn đăng ký luôn bao trọn các phòng (kể cả phòng đã hủy
            // trước đó) để lần khôi phục sau không có phòng nằm ngoài giai đoạn.
            $headerDates = $this->bookingPeriodFromRooms($allRooms, (bool) $booking->is_day_use);

            $booking->update(array_merge([
                'status'                 => Booking::STATUS_DELETED,
                'registration_status_id' => $newRegistrationStatusId,
                'updated_by'             => $currentUsername,
            ], $headerDates));
        });

        try {
            $bCode = $booking->booking_code ?? ('GAL' . $booking->id);
            \App\Services\ActivityLogService::logBusiness([
                'action' => 'Cancel',
                'module' => 'reservation',
                'component' => 'CreateRegistrationPage',
                'description' => "* Hủy đăng ký {$bCode}" . ($reasonText ? ". Lý do: {$reasonText}" : ""),
                'target_type' => 'Booking',
                'target_id' => (string) $booking->id,
                'target_label' => $bCode,
            ], $request);
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa đăng ký thành công!',
        ]);
    }

    /**
     * Booking period covering the given rooms: min arrival, max departure and
     * the resulting night count. Returns [] when there is no dated room.
     */
    private function bookingPeriodFromRooms($rooms, bool $isDayUse): array
    {
        $rooms = collect($rooms)->filter(fn ($room) => $room->arrival_date && $room->departure_date);
        if ($rooms->isEmpty()) {
            return [];
        }

        $arrival = $rooms->map(fn ($room) => Carbon::parse($room->arrival_date)->toDateString())->min();
        $departure = $rooms->map(fn ($room) => Carbon::parse($room->departure_date)->toDateString())->max();

        return [
            'arrival_date' => $arrival,
            'departure_date' => $departure,
            'num_of_days' => $isDayUse && $arrival === $departure
                ? 0
                : max(0, (int) Carbon::parse($arrival)->diffInDays(Carbon::parse($departure))),
        ];
    }

    /**
     * Danh sách phòng đã hủy của đăng ký để chọn khôi phục (mới hủy trước).
     * GET /bookings/{id}/cancelled-rooms
     */
    public function cancelledRooms($id)
    {
        $booking = Booking::withTrashed()->find($id);
        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đăng ký!'], 404);
        }

        $sysDateStr = app(RoomAvailabilityService::class)->getSystemDate()->toDateString();

        $rooms = BookingRoom::withTrashed()
            ->with(['roomClass', 'cancelLogs' => fn ($query) => $query->latest('cancelled_at')->latest('id')])
            ->where('booking_id', $booking->id)
            ->where(function ($query) {
                $query->where('status', BookingRoom::STATUS_CANCELLED)
                    ->orWhereNotNull('deleted_at');
            })
            ->get()
            ->map(function (BookingRoom $room) use ($sysDateStr) {
                $log = $room->cancelLogs->first();
                $arrival = $room->arrival_date?->toDateString();
                $isPast = $arrival !== null && $arrival < $sysDateStr;

                return [
                    'id' => $room->id,
                    'room_number' => $room->room_number,
                    'room_class_id' => $room->room_class_id,
                    'room_class_code' => $room->roomClass?->code,
                    'room_class_name' => $room->roomClass?->name,
                    'arrival_date' => $room->arrival_date?->toDateString(),
                    'departure_date' => $room->departure_date?->toDateString(),
                    'cancelled_at' => $log?->cancelled_at ? Carbon::parse($log->cancelled_at)->format('Y-m-d H:i:s') : null,
                    'cancelled_by' => $log?->cancelled_by_username,
                    'reason' => $room->reason ?: $log?->note,
                    // Phòng có ngày đến đã qua ngày hệ thống không được khôi phục.
                    'restorable' => !$isPast,
                    'not_restorable_reason' => $isPast ? 'Ngày đến đã qua ngày hệ thống' : null,
                ];
            })
            ->sortByDesc(fn ($room) => $room['cancelled_at'] ?? '')
            ->values();

        return response()->json(['success' => true, 'data' => $rooms]);
    }

    // =========================================
    // #19 — Nhân bản (Copy) booking
    // POST /bookings/{id}/copy
    // =========================================
    public function copy(Request $request, $id)
    {
        $source = Booking::with([
            'bookingRooms.guests.guest',
            'bookingRooms.children.breakfastDetails',
        ])->find($id);

        if (!$source) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đăng ký gốc!'], 404);
        }

        if ($error = $this->serviceOnlyMutationError($source)) {
            return $error;
        }

        $request->validate([
            'arrival_date'   => 'required|date',
            'departure_date' => 'required|date|after_or_equal:arrival_date',
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
        $copyRooms = HotelConfig::where('name', 'IsCopyAllBooking')->first()?->value !== '0';
        $allowOver = HotelConfig::where('name', 'AllowOverRoomTypeRoomKind')->first()?->value == '1';

        $newArrival   = $request->arrival_date;
        $newDeparture = $request->departure_date;
        $numDays      = Carbon::parse($newArrival)->diffInDays(Carbon::parse($newDeparture));

        // Kiểm tra phòng trống AV nếu được bật sao chép cả phòng
        if ($copyRooms) {
            if ($request->boolean('copy_header_only')) {
                // Người dùng xác nhận chỉ sao chép thông tin booking khi không còn phòng trống
                $copyRooms = false;
            } else {
                $srcRooms = $source->bookingRooms()->where('status', '!=', BookingRoom::STATUS_CANCELLED)->get();
                if ($srcRooms->isNotEmpty()) {
                    $neededByClass = [];
                    foreach ($srcRooms as $r) {
                        $neededByClass[$r->room_class_id] = ($neededByClass[$r->room_class_id] ?? 0) + 1;
                    }
                    $avService = app(RoomAvailabilityService::class);
                    $hasInsufficientAv = false;
                    foreach ($neededByClass as $classId => $needed) {
                        $av = $avService->getAvailability($classId, $newArrival, $newDeparture);
                        if ($av < $needed) {
                            $hasInsufficientAv = true;
                            break;
                        }
                    }

                    if ($hasInsufficientAv) {
                        if ($allowOver) {
                            // AllowOverRoomTypeRoomKind = 1: Cảnh báo phòng âm và hỏi người dùng
                            if (!$request->boolean('confirm_over')) {
                                return response()->json([
                                    'success' => false,
                                    'require_confirm' => 'over_warning',
                                    'message' => 'Phòng âm bạn có muốn tiếp tục thao tác',
                                ], 422);
                            }
                        } else {
                            // AllowOverRoomTypeRoomKind = 0: Cảnh báo không còn phòng trống
                            if (!$request->boolean('copy_header_only')) {
                                return response()->json([
                                    'success' => false,
                                    'require_confirm' => 'no_rooms_available',
                                    'message' => 'Không còn phòng trống, bạn có muốn tiếp tục thao tác',
                                ], 422);
                            }
                            $copyRooms = false;
                        }
                    }
                }
            }
        }

        try {
            $newBooking = DB::transaction(function () use (
                $source, $newArrival, $newDeparture, $numDays, $copyRooms, $sysDateStr
            ) {
                // 1. Tạo booking mới từ booking gốc (Sp2000)
                $newBooking = Booking::create([
                    'booking_name'           => $source->booking_name,
                    'arrival_date'           => $newArrival,
                    'departure_date'         => $newDeparture,
                    'num_of_days'            => $numDays,
                    'booking_date'           => $sysDateStr,
                    'booking_time'           => now()->format('H:i:s'),
                    'confirm_date'           => $sysDateStr,
                    'status'                 => Booking::STATUS_RESERVATION,
                    'registration_status_id' => $source->registration_status_id,
                    'color'                  => $source->color,
                    'is_git'                 => true,
                    'is_master_room_rate'    => $source->is_master_room_rate,
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

                // 2. Copy phòng nếu được phép (Sp2100, Sp2200, Sp2300, Sp2400, Sp2500)
                if ($copyRooms) {
                    $srcRooms = $source->bookingRooms()->where('status', '!=', BookingRoom::STATUS_CANCELLED)->get();
                    foreach ($srcRooms as $srcRoom) {
                        $newRoom = BookingRoom::create([
                            'booking_id'             => $newBooking->id,
                            'room_class_id'          => $srcRoom->room_class_id,
                            'original_room_class_id' => $srcRoom->room_class_id,
                            'room_number'            => null, // Không copy số phòng vật lý
                            'RoomKind'               => $srcRoom->RoomKind,
                            'arrival_date'           => $newArrival,
                            'departure_date'         => $newDeparture,
                            'arrival_time'           => $srcRoom->arrival_time,
                            'departure_time'         => $srcRoom->departure_time,
                            'rate'                   => $srcRoom->rate,
                            'rate_code'              => $srcRoom->rate_code,
                            'adults'                 => $srcRoom->adults,
                            'children_qty'           => $srcRoom->children_qty,
                            'babies'                 => $srcRoom->babies,
                            'breakfast'              => $srcRoom->breakfast,
                            'extra_bed_qty'          => $srcRoom->extra_bed_qty,
                            'extra_bed_rate'         => $srcRoom->extra_bed_rate,
                            'status'                 => BookingRoom::STATUS_BOOKED,
                            'booking_date'           => $sysDateStr,
                            'created_by'             => Auth::user()?->username ?? 'system',
                            'updated_by'             => Auth::user()?->username ?? 'system',
                        ]);

                        // Copy khách (Sp2200)
                        foreach ($srcRoom->guests as $pivotGuest) {
                            BookingRoomGuest::create([
                                'booking_room_id'     => $newRoom->id,
                                'guest_id'            => $pivotGuest->guest_id,
                                'is_primary'          => $pivotGuest->is_primary,
                                'status'              => $newRoom->status,
                                'actual_arrival_date' => $newRoom->arrival_date,
                                'checkin_by'          => Auth::user()?->username ?? 'system',
                                'breakfast'           => $pivotGuest->breakfast,
                            ]);
                        }

                        // Copy trẻ em (Sp2400 & Sp2500)
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

                return $newBooking;
            });
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $newBooking->load([
            'registrationStatus', 'company', 'bookingRooms.roomClass',
        ]);

        return response()->json([
            'success' => true,
            'data'    => $newBooking,
            'message' => 'Nhân bản booking thành công!',
        ], 201);
    }

    // =========================================
    // #22 — Khôi phục booking đã hủy
    // POST /bookings/{id}/restore
    // =========================================
    // =========================================
    // #22 — Khôi phục booking đã hủy
    // POST /bookings/{id}/restore
    // =========================================
    public function restore(Request $request, $id)
    {
        try {
            $booking = Booking::withTrashed()->with(['bookingRooms' => fn ($q) => $q->withTrashed()])->find($id);

            if (!$booking) {
                return response()->json(['success' => false, 'message' => 'Không tìm thấy đăng ký!'], 404);
            }

            if ($error = $this->serviceOnlyMutationError($booking)) {
                return $error;
            }

            $request->validate([
                'room_ids'   => 'nullable|array',
                'room_ids.*' => 'string',
            ]);

            $cancelledRooms = $booking->bookingRooms->filter(fn ($room) =>
                (int) $room->status === BookingRoom::STATUS_CANCELLED || !empty($room->deleted_at)
            );

            // Section 9: người dùng chọn đúng những phòng hủy cần khôi phục.
            $selectedRoomIds = collect($request->input('room_ids', []))->map(fn ($roomId) => (string) $roomId)->unique()->values();
            $isRoomSelection = $request->has('room_ids');
            if ($isRoomSelection) {
                if ($selectedRoomIds->isEmpty()) {
                    return response()->json(['success' => false, 'message' => 'Vui lòng chọn phòng cần khôi phục.'], 422);
                }
                $unknownIds = $selectedRoomIds->diff($cancelledRooms->pluck('id')->map(fn ($roomId) => (string) $roomId));
                if ($unknownIds->isNotEmpty()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Phòng ' . $unknownIds->implode(', ') . ' không phải phòng đã hủy của đăng ký này.',
                    ], 422);
                }
                $cancelledRooms = $cancelledRooms->filter(fn ($room) => $selectedRoomIds->contains((string) $room->id));
            }

            $allRoomsCancelled = $booking->bookingRooms->count() > 0
                && $booking->bookingRooms->every(fn ($room) =>
                    (int) $room->status === BookingRoom::STATUS_CANCELLED || !empty($room->deleted_at)
                );
            $isDeletedBooking = (int) $booking->status === Booking::STATUS_DELETED || $booking->trashed();

            if (!$isDeletedBooking && !$allRoomsCancelled && $cancelledRooms->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking chưa bị xóa/hủy, không cần khôi phục.',
                ], 422);
            }

            $availability = app(RoomAvailabilityService::class);
            $systemDate = SystemDateRoll::latest('id')->first();
            $sysDateStr = $systemDate
                ? Carbon::parse($systemDate->system_date)->toDateString()
                : now()->toDateString();
            $roomArrival = fn ($room) => Carbon::parse($room->arrival_date ?? $booking->arrival_date)->toDateString();
            $roomDeparture = fn ($room) => Carbon::parse($room->departure_date ?? $booking->departure_date)->toDateString();
            // Dòng 294: phòng có ngày đến = ngày đi vẫn chiếm phòng trong ngày đến.
            $roomStayEnd = function ($room) use ($roomArrival, $roomDeparture) {
                $arr = $roomArrival($room);
                $dep = $roomDeparture($room);
                return $dep > $arr ? $dep : Carbon::parse($arr)->addDay()->toDateString();
            };
            $roomLabel = fn ($room) => $room->room_number
                ? (string) $room->room_number
                : 'Phòng chưa gán số ' . ($room->roomClass?->code ?? '') . ' (' . Carbon::parse($roomArrival($room))->format('d/m') . ' - ' . Carbon::parse($roomDeparture($room))->format('d/m') . ')';
            $ineligibleIds = [];

            // Phòng có ngày đến đã qua ngày hệ thống không được khôi phục.
            $pastRooms = $cancelledRooms->filter(fn ($room) => $roomArrival($room) < $sysDateStr);
            $ineligible = [];
            if ($isRoomSelection) {
                foreach ($pastRooms as $room) {
                    $ineligible[] = $roomLabel($room) . ': ngày đến ' . Carbon::parse($roomArrival($room))->format('d/m/Y') . ' nhỏ hơn ngày hệ thống';
                    $ineligibleIds[] = $room->id;
                }
            }
            $roomsToRestore = $cancelledRooms->reject(fn ($room) => $roomArrival($room) < $sysDateStr)->values();

            // ========================================================
            // BẮT BUỘC: KIỂM TRA TRÙNG SỐ PHÒNG VẬT LÝ TUYỆT ĐỐI
            // Cho dù AllowOverRoomTypeRoomKind = 1, hệ thống chỉ cho phép
            // âm số lượng phòng trống của loại phòng (AV < 0), TUYỆT ĐỐI
            // KHÔNG cho phép trùng số phòng vật lý giữa các phòng đã đặt.
            // ========================================================

            // 1. Kiểm tra trùng số phòng nội bộ: giữa các phòng sẽ khôi phục và
            // với các phòng đang hoạt động của chính đăng ký.
            $activeOwnRooms = $booking->bookingRooms->filter(fn ($room) =>
                empty($room->deleted_at)
                && in_array((int) $room->status, [BookingRoom::STATUS_BOOKED, BookingRoom::STATUS_CHECKED_IN, BookingRoom::STATUS_CHECKED_OUT], true)
            );
            $seenAssigned = [];
            foreach ($activeOwnRooms->concat($roomsToRestore) as $bRoom) {
                if (empty($bRoom->room_number)) continue;
                $rn = trim((string) $bRoom->room_number);
                $arrStr = $roomArrival($bRoom);
                $depStr = $roomStayEnd($bRoom);

                foreach ($seenAssigned[$rn] ?? [] as $prevSpan) {
                    if ($arrStr < $prevSpan['dep'] && $depStr > $prevSpan['arr']) {
                        $message = "Không thể khôi phục vì trùng số phòng {$rn} ngay trong danh sách phòng của đăng ký.";
                        if ($isRoomSelection) {
                            $ineligible[] = "{$rn}: trùng số phòng trong cùng đăng ký";
                            $ineligibleIds[] = $bRoom->id;
                            continue 2;
                        }
                        return response()->json(['success' => false, 'message' => $message], 422);
                    }
                }
                $seenAssigned[$rn][] = ['arr' => $arrStr, 'dep' => $depStr];
            }

            // 2. Kiểm tra trùng số phòng với các phòng đang đặt/ở và phòng đang khóa OOO/OOS trong hệ thống
            $duplicateRooms = [];
            foreach ($roomsToRestore as $bRoom) {
                if (empty($bRoom->room_number)) continue;
                $rn = trim((string) $bRoom->room_number);
                $arrDate = $roomArrival($bRoom);
                $depDate = $roomDeparture($bRoom);
                $stayEnd = $roomStayEnd($bRoom);

                // 2.1 Kiểm tra trùng với BookingRoom đang hoạt động. Phòng có ngày đến = ngày đi
                // (của cả hai phía) được coi là chiếm phòng trọn ngày đến.
                $conflict = BookingRoom::with('booking')
                    ->where('room_number', $rn)
                    ->where('id', '!=', $bRoom->id)
                    ->where('booking_id', '!=', $booking->id)
                    ->whereIn('status', [
                        BookingRoom::STATUS_BOOKED,
                        BookingRoom::STATUS_CHECKED_IN,
                        BookingRoom::STATUS_CHECKED_OUT,
                    ])
                    ->where(function ($q) use ($arrDate, $depDate, $stayEnd) {
                        $q->where(function ($sub) use ($arrDate, $stayEnd) {
                            $sub->whereDate('arrival_date', '<', $stayEnd)
                                ->where(function ($end) use ($arrDate) {
                                    $end->whereDate('departure_date', '>', $arrDate)
                                        ->orWhere(function ($sameDay) use ($arrDate) {
                                            $sameDay->whereRaw('DATE(departure_date) <= DATE(arrival_date)')
                                                ->whereDate('arrival_date', '>=', $arrDate);
                                        });
                                });
                        })->orWhere(function ($dayuseSub) use ($arrDate, $depDate) {
                            $dayuseSub->where('is_day_use', 1)
                                      ->whereBetween('arrival_date', [$arrDate . ' 00:00:00', $depDate . ' 23:59:59']);
                        });
                    })
                    ->whereHas('booking', fn ($q) => $q->whereNotIn('status', [Booking::STATUS_DELETED, Booking::STATUS_NO_SHOW]))
                    ->first();

                if ($conflict) {
                    $bCode = $conflict->booking?->booking_code ?? ('BK#' . $conflict->booking_id);
                    $duplicateRooms[] = [
                        'room_number'  => $rn,
                        'booking_code' => $bCode,
                        'is_locked'    => false,
                    ];
                } elseif ($availability->hasBlockingLock($rn, $arrDate, $depDate)) {
                    // 2.2 Kiểm tra trùng với phòng đang khóa OOO / OOS (RoomLock)
                    $duplicateRooms[] = [
                        'room_number'  => $rn,
                        'booking_code' => null,
                        'is_locked'    => true,
                    ];
                }
            }

            // Một phòng không đạt điều kiện thì không khôi phục phòng nào đã chọn.
            if ($isRoomSelection && count($ineligible) > 0) {
                return response()->json([
                    'success'          => false,
                    'ineligible_rooms' => array_values(array_unique($ineligible)),
                    'ineligible_room_ids' => array_values(array_unique($ineligibleIds)),
                    'message'          => "Không thể khôi phục vì có phòng không đủ điều kiện:\n- " . implode("\n- ", array_unique($ineligible)),
                ], 422);
            }

            // Dòng 294: trùng số phòng (với đăng ký khác hoặc phòng khóa) hỏi xác nhận xóa số phòng
            // cả khi khôi phục cả booking lẫn khi chọn phòng hủy cần khôi phục.
            $uniqueDuplicateRooms = collect($duplicateRooms)->unique('room_number')->values()->all();
            $clearDuplicateRooms = $request->boolean('clear_duplicate_rooms');

            if (count($uniqueDuplicateRooms) > 0 && !$clearDuplicateRooms) {
                // Nếu có phòng trùng và người dùng chưa xác nhận xóa số phòng bị trùng -> Bật cảnh báo xác nhận
                $conflictLines = collect($uniqueDuplicateRooms)->map(function ($item) {
                    $line = "- R: {$item['room_number']}";
                    if (!empty($item['booking_code'])) {
                        $line .= " - BK: {$item['booking_code']}";
                    }
                    return $line;
                })->implode("\n");

                return response()->json([
                    'success'                 => false,
                    'needs_duplicate_confirm' => true,
                    'duplicate_rooms'         => $uniqueDuplicateRooms,
                    'conflict_lines'          => $conflictLines,
                    'message'                 => "Đăng ký được khôi phục có số phòng đã được đặt bởi đăng ký khác hoặc đang bị khóa. Bạn có muốn tiếp tục? (Số phòng của những phòng bị trùng sẽ được xóa khi khôi phục)\n{$conflictLines}",
                ], 200);
            }

            // ========================================================
            // KIỂM TRA OVER PHÒNG THEO AllowOverRoomTypeRoomKind
            // ========================================================
            $allowOver = (string) (HotelConfig::where('name', 'AllowOverRoomTypeRoomKind')->value('value') ?? '1') === '1';
            $overRooms = [];

            // Gom nhóm kiểm tra AV theo từng loại phòng để tránh lặp trùng lặp
            $roomsByClass = [];
            foreach ($roomsToRestore as $bRoom) {
                $rawClassId = $bRoom->room_class_id ?: $bRoom->original_room_class_id;
                $classId = (int) explode('-', (string) $rawClassId)[0];
                if ($classId > 0) {
                    $roomsByClass[$classId][] = $bRoom;
                }
            }

            foreach ($roomsByClass as $classId => $classRooms) {
                $roomClassModel = RoomClass::find($classId);
                $className = $roomClassModel ? $roomClassModel->name : "ID {$classId}";

                foreach ($classRooms as $bRoom) {
                    $start = Carbon::parse($roomArrival($bRoom));
                    $end = Carbon::parse($roomDeparture($bRoom));

                    if ($start->gte($end)) {
                        $end = $start->copy()->addDay();
                    }

                    for ($date = $start->copy(); $date->lt($end); $date->addDay()) {
                        $dateStr = $date->toDateString();
                        $nextDateStr = $date->copy()->addDay()->toDateString();

                        $av = $availability->getAvailability($classId, $dateStr, $nextDateStr);
                        $needed = collect($classRooms)->filter(function ($room) use ($dateStr, $roomArrival, $roomDeparture) {
                            return $roomArrival($room) <= $dateStr && $roomDeparture($room) > $dateStr;
                        })->count();

                        if ($av - $needed < 0) {
                            $overKey = "{$classId}_{$dateStr}";
                            $overRooms[$overKey] = [
                                'room_class_id' => $classId,
                                'class_name'    => $className,
                                'date'          => $dateStr,
                                'av'            => $av,
                                'needed'        => $needed,
                            ];
                        }
                    }
                }
            }

            // Nếu AllowOverRoomTypeRoomKind = 0: Tuyệt đối không cho phép over phòng
            if (count($overRooms) > 0 && !$allowOver) {
                return response()->json([
                    'success'         => false,
                    'blocked_by_over' => true,
                    'over_rooms'      => array_values($overRooms),
                    'message'         => $isRoomSelection
                        ? 'Loại phòng đang bị over không thể khôi phục các phòng đã chọn'
                        : 'Loại phòng đang bị over không thể khôi phục booking',
                ], 422);
            }

            // Nếu AllowOverRoomTypeRoomKind = 1: Bật cảnh báo xác nhận nếu chưa có cờ force / force_over
            $forceOver = $request->boolean('force') || $request->boolean('force_over');
            if (count($overRooms) > 0 && !$forceOver) {
                return response()->json([
                    'success'            => false,
                    'needs_over_confirm' => true,
                    'needs_confirm'      => true,
                    'over_rooms'         => array_values($overRooms),
                    'message'            => 'Loại phòng đang bị over bạn có muốn tiếp tục',
                ], 200);
            }

            // ========================================================
            // TIẾN HÀNH KHÔI PHỤC BOOKING & PHÒNG
            // ========================================================
            DB::transaction(function () use ($booking, $roomsToRestore, $clearDuplicateRooms, $uniqueDuplicateRooms, $isDeletedBooking) {
                $currentUsername = Auth::user()?->username ?? 'system';
                $duplicateRoomNumbers = collect($uniqueDuplicateRooms)->pluck('room_number')->all();

                // 1. Khôi phục soft delete booking
                if ($booking->trashed()) {
                    $booking->restore();
                }

                // 2. Booking đã hủy quay về Reservation; booking đang hoạt động giữ trạng thái.
                if ($isDeletedBooking) {
                    $booking->update([
                        'status'     => Booking::STATUS_RESERVATION,
                        'updated_by' => $currentUsername,
                    ]);
                }

                // 3. Khôi phục các phòng có ngày đến >= ngày hệ thống
                foreach ($roomsToRestore as $bRoom) {
                    if ($bRoom->trashed()) {
                        $bRoom->restore();
                    }

                    $roomUpdate = [
                        'status'     => BookingRoom::STATUS_BOOKED,
                        'updated_by' => $currentUsername,
                    ];

                    // Nếu xóa số phòng bị trùng: set null cho room_number
                    if ($clearDuplicateRooms && !empty($bRoom->room_number) && in_array(trim((string)$bRoom->room_number), $duplicateRoomNumbers, true)) {
                        $roomUpdate['room_number'] = null;
                    }

                    $bRoom->update($roomUpdate);

                    // Khôi phục guests
                    $bRoom->guests()->update(['status' => BookingRoomGuest::STATUS_ACTIVE]);
                    app(\App\Services\GuestStatusSyncService::class)->syncForGuestIds($bRoom->guests()->pluck('guest_id'));

                    // Khôi phục children
                    $bRoom->children()->update(['child_status' => 0]);
                }

                // 4. Section 8: giai đoạn đăng ký khớp với các phòng đang hoạt động.
                $activeRooms = BookingRoom::where('booking_id', $booking->id)->active()->get();
                $headerDates = $this->bookingPeriodFromRooms($activeRooms, (bool) $booking->is_day_use);
                if ($headerDates) {
                    $booking->update($headerDates + ['updated_by' => $currentUsername]);
                }

                if (!$isDeletedBooking) {
                    app(\App\Services\BookingStatusSyncService::class)->sync($booking);
                }
            });

            try {
                $bCode = $booking->booking_code ?? ('GAL' . $booking->id);
                $restoredLabels = $roomsToRestore->map(fn ($room) => $room->room_number ?: $room->id)->implode(', ');
                \App\Services\ActivityLogService::logBusiness([
                    'action'       => 'Restore',
                    'module'       => 'reservation',
                    'component'    => 'CreateRegistrationPage',
                    'description'  => "* Khôi phục đăng ký {$bCode}" . ($restoredLabels !== '' ? " - Phòng: {$restoredLabels}" : ''),
                    'target_type'  => 'Booking',
                    'target_id'    => (string) $booking->id,
                    'target_label' => $bCode,
                ], $request);
            } catch (\Throwable $e) {}

            $roomRestored = $roomsToRestore->isNotEmpty();
            $skippedPastRooms = !$isRoomSelection && $pastRooms->isNotEmpty();

            $freshBooking = Booking::withTrashed()->with([
                'registrationStatus',
                'bookingRooms.roomClass',
                'payments.paymentMethod',
                'payments.user',
            ])->find($booking->id);

            return response()->json([
                'success'          => true,
                'data'             => $freshBooking,
                'rooms_restored'   => $roomRestored,
                'restored_room_ids' => $roomsToRestore->pluck('id')->values(),
                'message'          => ($isRoomSelection ? 'Khôi phục ' . $roomsToRestore->count() . ' phòng thành công!' : 'Khôi phục booking thành công!') . (
                    $skippedPastRooms
                        ? ($roomRestored
                            ? ' Các phòng có ngày đến đã qua ngày hệ thống không được khôi phục.'
                            : ' Ngày đến đã qua ngày hệ thống — chỉ khôi phục thông tin booking, phòng không được khôi phục.')
                        : ''
                ),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Restore booking error: ' . $e->getMessage(), [
                'booking_id' => $id,
                'trace'      => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi máy chủ khi khôi phục booking: ' . $e->getMessage(),
            ], 500);
        }
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
     * Normalize the room allocation contract before it reaches persistence.
     *
     * quantity is the target number of current rooms. A cancelled, no-show or
     * moved BookingRoom remains a visible audit row, but it is removed from
     * the allocation details and cannot create a replacement implicitly.
     */
    private function normalizeRoomAllocations(array $roomAllocations, $bookingId = null): array
    {
        $activeStatuses = [
            BookingRoom::STATUS_BOOKED,
            BookingRoom::STATUS_CHECKED_IN,
            BookingRoom::STATUS_CHECKED_OUT,
        ];
        $historyStatuses = [
            BookingRoom::STATUS_CANCELLED,
            BookingRoom::STATUS_NOSHOW,
            BookingRoom::STATUS_MOVED,
        ];

        $normalized = [];
        $seenBookingRoomIds = [];
        foreach ($roomAllocations as $alloc) {
            if (!is_array($alloc)) {
                throw new \Exception('Dữ liệu phân bổ phòng không hợp lệ.');
            }

            $details = $alloc['rooms'] ?? [];
            if (!is_array($details)) {
                throw new \Exception('Chi tiết phân bổ phòng không hợp lệ.');
            }

            $rawQuantity = max((int) ($alloc['quantity'] ?? 0), 0);
            $normalizedDetails = [];
            foreach ($details as $detail) {
                if (!is_array($detail)) {
                    throw new \Exception('Chi tiết phòng trong yêu cầu phân bổ không hợp lệ.');
                }

                $bookingRoomId = $detail['bookingRoomId'] ?? null;
                $hasBookingRoomId = $bookingRoomId !== null
                    && $bookingRoomId !== ''
                    && trim((string) $bookingRoomId) !== '';

                if (!$hasBookingRoomId) {
                    $detailRoomClassId = $detail['roomClassId'] ?? null;
                    if ($detailRoomClassId !== null
                        && $detailRoomClassId !== ''
                        && (string) $detailRoomClassId !== (string) ($alloc['roomClassId'] ?? null)) {
                        throw new \Exception('Chi tiết phòng không thuộc loại phòng đã chọn.');
                    }
                    $normalizedDetails[] = $detail;
                    continue;
                }

                if ($bookingId === null) {
                    throw new \Exception('Không thể dùng phòng đã lưu khi tạo đăng ký mới.');
                }

                $bookingRoomKey = (string) $bookingRoomId;
                if (isset($seenBookingRoomIds[$bookingRoomKey])) {
                    throw new \Exception('Một phòng đã lưu không được lặp lại trong cùng yêu cầu phân bổ.');
                }
                $seenBookingRoomIds[$bookingRoomKey] = true;

                // Scope every persisted id to the booking being edited. This
                // also finds a booked row temporarily soft-deleted for AV
                // validation while excluding rows owned by another booking.
                $bookingRoom = BookingRoom::withTrashed()
                    ->where('booking_id', $bookingId)
                    ->find($bookingRoomId);
                if (!$bookingRoom) {
                    throw new \Exception('Phòng trong yêu cầu phân bổ không thuộc đăng ký đang chỉnh sửa.');
                }

                $roomStatus = (int) $bookingRoom->status;
                if (in_array($roomStatus, $historyStatuses, true)) {
                    // The current FE omits history rows altogether. A
                    // zero-demand allocation may still carry one from an
                    // older tab snapshot; ignore it without reviving it.
                    if ($rawQuantity > 0) {
                        throw new \Exception('Phòng đã hủy/no-show/chuyển phải được bỏ khỏi chi tiết phân bổ hiện tại.');
                    }
                    continue;
                }
                if (!in_array($roomStatus, $activeStatuses, true)) {
                    throw new \Exception('Trạng thái phòng trong yêu cầu phân bổ không hợp lệ.');
                }

                $detailRoomClassId = $detail['roomClassId'] ?? null;
                if ($detailRoomClassId !== null
                    && $detailRoomClassId !== ''
                    && (string) $detailRoomClassId !== (string) ($alloc['roomClassId'] ?? null)) {
                    throw new \Exception('Chi tiết phòng không thuộc loại phòng đã chọn.');
                }
                $normalizedDetails[] = $detail;
            }

            $normalizedAlloc = $alloc;
            $normalizedAlloc['quantity'] = $rawQuantity;
            $normalizedAlloc['rooms'] = array_values($normalizedDetails);
            $normalized[] = $normalizedAlloc;
        }

        return $normalized;
    }

    /**
     * Validate the isolated "Lấy phòng" contract.
     *
     * This request is append-only. It must never carry a persisted
     * bookingRoomId, and its quantity is the number of new rows to insert.
     */
    private function validateAddOnlyRoomAllocations(Booking $booking, array $roomAllocations): void
    {
        $existingRooms = $booking->bookingRooms()
            ->whereIn('status', [
                BookingRoom::STATUS_BOOKED,
                BookingRoom::STATUS_CHECKED_IN,
                BookingRoom::STATUS_CHECKED_OUT,
            ])
            ->whereNotNull('room_number')
            ->get(['room_number', 'arrival_date', 'departure_date', 'is_day_use']);

        $parseDate = static function ($date): ?string {
            if (!$date) {
                return null;
            }
            $date = trim((string) $date);
            if (str_contains($date, '/')) {
                $parts = explode('/', $date);
                if (count($parts) === 3 && strlen($parts[2]) === 4) {
                    return "{$parts[2]}-{$parts[1]}-{$parts[0]}";
                }
            }
            return Carbon::parse($date)->toDateString();
        };

        $bookingArrival = Carbon::parse($booking->arrival_date)->toDateString();
        $bookingDeparture = Carbon::parse($booking->departure_date)->toDateString();

        $datesOverlap = static function ($arr1, $dep1, $dayUse1, $arr2, $dep2, $dayUse2): bool {
            if ($dayUse1 && $dayUse2) {
                return $arr1 === $arr2;
            }
            if ($dayUse1 && $arr1 === $dep1) {
                return $arr2 <= $arr1 && $arr1 < $dep2;
            }
            if ($dayUse2 && $arr2 === $dep2) {
                return $arr1 <= $arr2 && $arr2 < $dep1;
            }
            return $arr1 < $dep2 && $dep1 > $arr2;
        };

        $incomingRooms = [];
        $totalQuantity = 0;
        foreach ($roomAllocations as $allocation) {
            if (!is_array($allocation)) {
                throw new \Exception('Dữ liệu phân bổ phòng không hợp lệ.');
            }

            $rawQuantity = $allocation['quantity'] ?? 0;
            if (!is_numeric($rawQuantity) || (float) $rawQuantity < 0 || floor((float) $rawQuantity) !== (float) $rawQuantity) {
                throw new \Exception('Số lượng phòng mới không hợp lệ.');
            }
            $quantity = (int) $rawQuantity;
            $totalQuantity += $quantity;

            $details = $allocation['rooms'] ?? [];
            if (!is_array($details)) {
                throw new \Exception('Chi tiết phân bổ phòng không hợp lệ.');
            }
            if ($quantity > count($details)) {
                throw new \Exception('Chi tiết phòng mới không đủ theo số lượng yêu cầu.');
            }
            if ($quantity > 0 && empty($allocation['roomClassId'])) {
                throw new \Exception('Loại phòng không hợp lệ trong yêu cầu thêm phòng.');
            }

            foreach ($details as $detail) {
                if (!is_array($detail)) {
                    throw new \Exception('Chi tiết phòng mới không hợp lệ.');
                }

                $bookingRoomId = $detail['bookingRoomId'] ?? null;
                if ($bookingRoomId !== null && trim((string) $bookingRoomId) !== '') {
                    throw new \Exception('Luồng thêm phòng không được gửi phòng đã lưu.');
                }

                $detailRoomClassId = $detail['roomClassId'] ?? null;
                if ($detailRoomClassId !== null
                    && $detailRoomClassId !== ''
                    && (string) $detailRoomClassId !== (string) ($allocation['roomClassId'] ?? null)) {
                    throw new \Exception('Chi tiết phòng không thuộc loại phòng đã chọn.');
                }

                $roomNumber = trim((string) ($detail['roomNumber'] ?? ''));
                if ($roomNumber !== '') {
                    $roomArrival = $parseDate($detail['arrivalDate'] ?? $detail['checkIn'] ?? null) ?? $bookingArrival;
                    $roomDeparture = $parseDate($detail['departureDate'] ?? $detail['checkOut'] ?? null) ?? $bookingDeparture;
                    $isRoomDayUse = (bool) ($detail['is_day_use'] ?? $detail['hourly'] ?? false) || ($roomArrival === $roomDeparture);

                    // Check conflict with existing rooms of this booking
                    foreach ($existingRooms as $ex) {
                        if ((string) $ex->room_number === $roomNumber) {
                            $exArr = Carbon::parse($ex->arrival_date)->toDateString();
                            $exDep = Carbon::parse($ex->departure_date)->toDateString();
                            $exDayUse = (bool) $ex->is_day_use || ($exArr === $exDep);

                            if ($datesOverlap($roomArrival, $roomDeparture, $isRoomDayUse, $exArr, $exDep, $exDayUse)) {
                                throw new \Exception('Số phòng ' . $roomNumber . ' bị trùng lặp với phòng đã có trong đăng ký này trong cùng khoảng thời gian.');
                            }
                        }
                    }

                    // Check conflict with other incoming rooms in this same request
                    foreach ($incomingRooms as $inc) {
                        if ($inc['room_number'] === $roomNumber) {
                            if ($datesOverlap($roomArrival, $roomDeparture, $isRoomDayUse, $inc['arrival'], $inc['departure'], $inc['is_day_use'])) {
                                throw new \Exception('Số phòng ' . $roomNumber . ' bị trùng lặp trong cùng một lượt thêm với thời gian ở trùng nhau.');
                            }
                        }
                    }

                    $incomingRooms[] = [
                        'room_number' => $roomNumber,
                        'arrival' => $roomArrival,
                        'departure' => $roomDeparture,
                        'is_day_use' => $isRoomDayUse,
                    ];
                }
            }
        }

        if ($totalQuantity <= 0) {
            throw new \Exception('Vui lòng chọn số lượng phòng cần thêm!');
        }
    }

    /**
     * Insert one new booking_room for the append-only add-room endpoint.
     */
    private function createAdditionalBookingRoom(Booking $booking, array $allocation, array $detail): BookingRoom
    {
        $parseDate = static function ($date): ?string {
            if (!$date) {
                return null;
            }

            $date = trim((string) $date);
            if (str_contains($date, '/')) {
                $parts = explode('/', $date);
                if (count($parts) === 3 && strlen($parts[2]) === 4) {
                    return "{$parts[2]}-{$parts[1]}-{$parts[0]}";
                }
            }

            return Carbon::parse($date)->toDateString();
        };

        $bookingArrival = Carbon::parse($booking->arrival_date)->toDateString();
        $bookingDeparture = Carbon::parse($booking->departure_date)->toDateString();
        $roomArrival = $parseDate($detail['arrivalDate'] ?? $detail['checkIn'] ?? null);
        $roomDeparture = $parseDate($detail['departureDate'] ?? $detail['checkOut'] ?? null);
        $hasExplicitRoomDates = $roomArrival !== null && $roomDeparture !== null;
        $syncRoomDates = HotelConfig::where('name', 'SyncRoomDateByBookingDate')->value('value') === '1';

        // Preserve explicitly selected room periods even when legacy synchronization is enabled
        if ($syncRoomDates && !$hasExplicitRoomDates) {
            $roomArrival = $bookingArrival;
            $roomDeparture = $bookingDeparture;
        } else {
            $roomArrival = $roomArrival ?? $bookingArrival;
            $roomDeparture = $roomDeparture ?? $bookingDeparture;
        }

        $detail = $this->applyConfiguredRateCodePricing(
            $detail,
            $allocation,
            $roomArrival,
            $roomDeparture
        );

        $bookingRoom = BookingRoom::create([
            'booking_id' => $booking->id,
            'room_number' => $detail['roomNumber'] ?? null,
            'room_class_id' => $allocation['roomClassId'] ?? null,
            'original_room_class_id' => $allocation['roomClassId'] ?? null,
            'arrival_date' => $roomArrival,
            'departure_date' => $roomDeparture,
            'arrival_time' => $detail['arrivalTime'] ?? null,
            'departure_time' => $detail['hoursOut'] ?? null,
            'rate' => $detail['price'] ?? $allocation['price'] ?? 0,
            'rate_code' => array_key_exists('rateCode', $detail)
                ? (filled($detail['rateCode']) ? $detail['rateCode'] : null)
                : ($allocation['rateCode'] ?? null),
            'breakfast' => isset($detail['breakfast'])
                ? !empty($detail['breakfast'])
                : !empty($allocation['breakfastIncluded']),
            'discount' => $detail['discount'] ?? $allocation['discount'] ?? null,
            'discount_type' => $detail['discountType'] ?? $allocation['discountType'] ?? null,
            'discount_value' => $detail['discountValue'] ?? $allocation['discountValue'] ?? 0,
            'discount_unit' => $detail['discountUnit'] ?? $allocation['discountUnit'] ?? null,
            'base_price' => $detail['basePrice']
                ?? $allocation['basePrice']
                ?? $detail['price']
                ?? $allocation['price']
                ?? 0,
            'adults' => $detail['adults'] ?? 2,
            'babies' => $detail['babies'] ?? 0,
            'children_qty' => $detail['children'] ?? 0,
            'is_day_use' => (bool) $booking->is_day_use,
            'extra_bed_qty' => (int) ($detail['extraBedQty'] ?? (empty($detail['extraBedPrice']) ? 0 : 1)),
            'extra_bed_rate' => $detail['extraBedPrice'] ?? 0,
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        $this->upsertBookingRoomServices($bookingRoom, $detail);

        $roomGuestName = trim((string) ($detail['guestName'] ?? ''));
        if ($roomGuestName === '') {
            $roomGuestName = 'Guest 1';
        }

        $guest = Guest::create([
            'full_name' => $roomGuestName,
            'title' => 'Mr.',
            'nationality_code' => 'VN',
            'guest_status' => Guest::STATUS_ACTIVE,
        ]);
        BookingRoomGuest::create([
            'booking_room_id' => $bookingRoom->id,
            'guest_id' => $guest->id,
            'is_primary' => 1,
            'status' => $bookingRoom->status,
            'actual_arrival_date' => $bookingRoom->arrival_date,
            'checkin_by' => Auth::user()?->username ?? 'system',
            'breakfast' => $bookingRoom->breakfast,
        ]);

        $numAdults = (int) ($detail['adults'] ?? 2);
        for ($adult = 2; $adult <= $numAdults; $adult++) {
            $secondaryGuest = Guest::create([
                'full_name' => 'Guest ' . $adult,
                'title' => 'Mr.',
                'nationality_code' => 'VN',
                'guest_status' => Guest::STATUS_ACTIVE,
            ]);
            BookingRoomGuest::create([
                'booking_room_id' => $bookingRoom->id,
                'guest_id' => $secondaryGuest->id,
                'is_primary' => 0,
                'status' => $bookingRoom->status,
                'actual_arrival_date' => $bookingRoom->arrival_date,
                'checkin_by' => Auth::user()?->username ?? 'system',
                'breakfast' => $bookingRoom->breakfast,
            ]);
        }

        $numChildren = (int) ($detail['children'] ?? 0);
        for ($childIndex = 0; $childIndex < $numChildren; $childIndex++) {
            $child = BookingChild::create([
                'booking_id' => $booking->id,
                'booking_room_id' => $bookingRoom->id,
                'full_name' => 'Child ' . ($childIndex + 1),
                'age_group' => 'child',
            ]);
            $this->createChildBreakfastDetails($child, $bookingRoom);
        }

        $numBabies = (int) ($detail['babies'] ?? 0);
        for ($babyIndex = 0; $babyIndex < $numBabies; $babyIndex++) {
            $baby = BookingChild::create([
                'booking_id' => $booking->id,
                'booking_room_id' => $bookingRoom->id,
                'full_name' => 'Baby ' . ($babyIndex + 1),
                'age_group' => 'baby',
            ]);
            $this->createChildBreakfastDetails($baby, $bookingRoom);
        }

        return $bookingRoom;
    }

    /**
     * Validate room allocations against availability, OOO/OOS locks, and occupancy.
     */
    private function validateRoomAllocations(array $roomAllocations, string $arrivalDate, string $departureDate, $excludeBookingId = null, bool $isWaitingBooking = false)
    {
        $roomAllocations = $this->normalizeRoomAllocations($roomAllocations, $excludeBookingId);
        $avService = app(RoomAvailabilityService::class);
        $systemDate = SystemDateRoll::latest('id')->value('system_date');
        $systemDate = $systemDate
            ? Carbon::parse($systemDate)->toDateString()
            : now()->timezone('Asia/Ho_Chi_Minh')->toDateString();
        $allowOver = \App\Models\HotelConfig::where('name', 'AllowOverRoomTypeRoomKind')->first()?->value == '1';
        $allowInputOver = \App\Models\HotelConfig::where('name', 'AllowInputOverAV')->first()?->value == '1';
        $payloadAssignments = [];
        $newDemandByPeriod = [];
        $newTimedDayUseDemands = [];
        $periods = [];
        $occupiedRoomNumbers = [];

        $parseDate = function ($date) {
            if (!$date) return null;
            $date = trim((string) $date);
            if (strpos($date, '/') !== false) {
                $parts = explode('/', $date);
                if (count($parts) === 3 && strlen($parts[2]) === 4) {
                    return "{$parts[2]}-{$parts[1]}-{$parts[0]}";
                }
            }
            return Carbon::parse($date)->toDateString();
        };

        $arrivalDate = $parseDate($arrivalDate);
        $departureDate = $parseDate($departureDate);

        foreach ($roomAllocations as $alloc) {
            $roomClassId = $alloc['roomClassId'] ?? null;
            if (!$roomClassId) {
                throw new \Exception('Loại phòng không hợp lệ trong yêu cầu phân bổ phòng.');
            }

            $qty = max((int) ($alloc['quantity'] ?? 0), 0);
            if ($qty <= 0) continue;

            $details = $alloc['rooms'] ?? [];
            for ($i = 0; $i < $qty; $i++) {
                $detail = $details[$i] ?? [];
                $bookingRoomId = $detail['bookingRoomId'] ?? null;
                $hasBookingRoomId = $bookingRoomId !== null
                    && $bookingRoomId !== ''
                    && trim((string) $bookingRoomId) !== '';
                $bookingRoom = $hasBookingRoomId && $excludeBookingId !== null
                    ? BookingRoom::withTrashed()
                        ->where('booking_id', $excludeBookingId)
                        ->find($bookingRoomId)
                    : null;

                // normalizeRoomAllocations has already checked ownership; a
                // missing model here only means a malformed direct call.
                if ($hasBookingRoomId && !$bookingRoom) {
                    throw new \Exception('Phòng trong yêu cầu phân bổ không thuộc đăng ký đang chỉnh sửa.');
                }

                $roomArrival = $parseDate($detail['arrivalDate'] ?? $detail['checkIn'] ?? $arrivalDate);
                $roomDeparture = $parseDate($detail['departureDate'] ?? $detail['checkOut'] ?? $departureDate);
                $isPersistedStay = $bookingRoom !== null;
                $bookingRoomStatus = $isPersistedStay ? (int) $bookingRoom->status : null;
                $isInhouseOrCheckedOut = $isPersistedStay
                    && in_array((int) $bookingRoom->status, [
                        BookingRoom::STATUS_CHECKED_IN,
                        BookingRoom::STATUS_CHECKED_OUT,
                    ], true);

                $originalRoomArrival = $isPersistedStay && $bookingRoom->arrival_date
                    ? Carbon::parse($bookingRoom->arrival_date)->toDateString()
                    : null;
                $originalRoomDeparture = $isPersistedStay && $bookingRoom->departure_date
                    ? Carbon::parse($bookingRoom->departure_date)->toDateString()
                    : null;
                $isRoomDayUse = (bool) ($detail['is_day_use'] ?? $detail['hourly'] ?? false)
                    || (!$isPersistedStay && $roomArrival === $roomDeparture)
                    || ($isPersistedStay && (bool) $bookingRoom->is_day_use);
                $roomArrivalTime = $this->normalizeDayUseTime($detail['arrivalTime'] ?? null);
                $roomDepartureTime = $this->normalizeDayUseTime($detail['hoursOut'] ?? null);
                $isTimedDayUse = $isRoomDayUse
                    && $roomArrival === $roomDeparture
                    && $roomArrivalTime !== null
                    && $roomDepartureTime !== null
                    && $roomArrivalTime < $roomDepartureTime;

                // Check the PMS date before booking containment so callers get
                // the section 7 message for an allocation date in the past.
                $isNewPastStay = !$isPersistedStay && $roomArrival < $systemDate;
                $isMovedIntoPast = $isPersistedStay
                    && $bookingRoomStatus === BookingRoom::STATUS_BOOKED
                    && $originalRoomArrival !== null
                    && $originalRoomArrival >= $systemDate
                    && $roomArrival < $systemDate;
                if ($isNewPastStay || $isMovedIntoPast) {
                    throw new \Exception('Ngày đến của phòng nhỏ hơn ngày hệ thống, vui lòng kiểm tra lại thông tin.');
                }

                if ($isInhouseOrCheckedOut
                    && (int) $bookingRoom->room_class_id !== (int) $roomClassId) {
                    throw new \Exception('Không thể đổi loại phòng của phòng đang ở/đã trả trong lượt cập nhật này.');
                }

                // Existing inhouse/checkout rows may intentionally retain a
                // stay period outside a later booking-header edit. The update
                // path preserves them; only current reservations and drafts
                // are constrained to the booking period.
                if (!$isInhouseOrCheckedOut
                    && ($roomArrival < $arrivalDate || $roomDeparture > $departureDate)) {
                    throw new \Exception("Thời gian ở của phòng phải nằm trong khoảng thời gian của booking (từ {$arrivalDate} đến {$departureDate}).");
                }

                $periodKey = implode('|', [(string) $roomClassId, $roomArrival, $roomDeparture]);
                $periods[$periodKey] = [
                    'room_class_id' => (int) $roomClassId,
                    'arrival' => $roomArrival,
                    'departure' => $roomDeparture,
                ];
                $sameReservationPeriod = $isPersistedStay
                    && $bookingRoomStatus === BookingRoom::STATUS_BOOKED
                    && (int) $bookingRoom->room_class_id === (int) $roomClassId
                    && $originalRoomArrival === $roomArrival
                    && $originalRoomDeparture === $roomDeparture;

                if (!$isPersistedStay || ($bookingRoomStatus === BookingRoom::STATUS_BOOKED && !$sameReservationPeriod)) {
                    if ($isTimedDayUse) {
                        $newTimedDayUseDemands[] = [
                            'room_class_id' => (int) $roomClassId,
                            'arrival' => $roomArrival,
                            'departure' => $roomDeparture,
                            'arrival_time' => $roomArrivalTime,
                            'departure_time' => $roomDepartureTime,
                        ];
                    } else {
                        $newDemandByPeriod[$periodKey] = ($newDemandByPeriod[$periodKey] ?? 0) + 1;
                    }
                }

                if (!empty($detail['roomNumber'])) {
                    // Section 7: Bỏ qua phòng tình trạng 100 (phòng đã chuyển) vì nó là phòng cũ trong lịch sử, không check trùng với phòng hiện tại
                    if ($bookingRoomStatus === 100 || (int)($detail['status'] ?? 0) === 100 || $bookingRoom?->move_room !== null) {
                        continue;
                    }

                    $roomNumber = $detail['roomNumber'];
                    $room = \App\Models\Room::where('room_number', $roomNumber)->first();
                    if (!$room) {
                        throw new \Exception('Số phòng ' . $roomNumber . ' không tồn tại trong hệ thống.');
                    }
                    if ($room->room_class_id != $roomClassId) {
                        throw new \Exception('Số phòng ' . $roomNumber . ' không thuộc loại phòng đã chọn.');
                    }

                    // Check duplicate assignment within the same request
                    // payload. Day Use may reuse the same room only when its
                    // half-open time ranges do not overlap.
                    foreach ($payloadAssignments as $assignment) {
                        $overlap = $roomArrival < $assignment['departure']
                            && $assignment['arrival'] < $roomDeparture;
                        if ($isRoomDayUse && $roomArrival === $roomDeparture) {
                            $overlap = $assignment['is_day_use']
                                ? ($roomArrival === $assignment['arrival']
                                    && (!$isTimedDayUse || !$assignment['is_timed_day_use']
                                        || ($roomArrivalTime < $assignment['departure_time']
                                            && $assignment['arrival_time'] < $roomDepartureTime)))
                                : ($assignment['arrival'] <= $roomArrival && $roomArrival < $assignment['departure']);
                        } elseif ($assignment['is_day_use'] && $assignment['arrival'] === $assignment['departure']) {
                            $overlap = $assignment['arrival'] >= $roomArrival
                                && $assignment['arrival'] < $roomDeparture;
                        }

                        if ($assignment['room_number'] === $roomNumber && $overlap) {
                            throw new \Exception('Số phòng ' . $roomNumber . ' bị trùng lặp trong cùng một lượt lưu với thời gian ở trùng nhau.');
                        }
                    }

                    $payloadAssignments[] = [
                        'room_number' => $roomNumber,
                        'arrival' => $roomArrival,
                        'departure' => $roomDeparture,
                        'is_day_use' => $isRoomDayUse,
                        'is_timed_day_use' => $isTimedDayUse,
                        'arrival_time' => $roomArrivalTime,
                        'departure_time' => $roomDepartureTime,
                    ];

                    $isLocked = $avService->hasBlockingLock($roomNumber, $roomArrival, $roomDeparture);
                    if ($isLocked) {
                        throw new \Exception('Số phòng ' . $roomNumber . ' đang bị khóa OOO/OOS trong giai đoạn này.');
                    }

                    // Dòng 400: gom mọi số phòng đã bị BK khác gán để báo một lần.
                    $conflict = $avService->findRoomNumberConflict(
                        $roomNumber,
                        $roomArrival,
                        $roomDeparture,
                        $bookingRoomId,
                        $excludeBookingId,
                        $roomArrivalTime,
                        $roomDepartureTime
                    );
                    if ($conflict) {
                        $code = $conflict->booking?->booking_code ?: ('#' . $conflict->booking_id);
                        $occupiedRoomNumbers[$roomNumber] = $roomNumber . ' (' . $code . ')';
                    }
                }
            }
        }

        if ($occupiedRoomNumbers) {
            throw new \Exception('Số phòng ' . implode(', ', $occupiedRoomNumbers) . ' đã được gán cho lượt đăng ký khác trong giai đoạn này.');
        }

        // Availability is checked only for new demand. Existing reservation
        // rows are temporarily excluded by the update transaction, while
        // inhouse/checkout rows remain occupied and are added back as the
        // owner's already satisfied capacity.
        foreach ($newDemandByPeriod as $periodKey => $newDemand) {
            $period = $periods[$periodKey];
            $availability = $avService->getAvailability(
                $period['room_class_id'],
                $period['arrival'],
                $period['departure']
            );
            $ownActiveCount = 0;
            if ($excludeBookingId !== null) {
                $ownActiveCount = BookingRoom::query()
                    ->where('booking_id', $excludeBookingId)
                    ->whereIn('status', [
                        BookingRoom::STATUS_CHECKED_IN,
                        BookingRoom::STATUS_CHECKED_OUT,
                    ])
                    ->where('room_class_id', $period['room_class_id'])
                    ->where('arrival_date', '<', $period['departure'])
                    ->where('departure_date', '>', $period['arrival'])
                    ->count();
            }
            $effectiveAvailability = $availability + $ownActiveCount;

            $canOver = ($allowOver && $allowInputOver) || $isWaitingBooking;

            if ($effectiveAvailability < $newDemand && !$canOver) {
                $roomClass = \App\Models\RoomClass::find($period['room_class_id']);
                throw new \Exception(
                    'Không đủ phòng trống cho loại phòng ' . ($roomClass?->name ?? 'không xác định')
                    . '. Số phòng trống hiện tại: ' . $effectiveAvailability
                );
            }
        }

        foreach ($newTimedDayUseDemands as $demand) {
            $simultaneousDemand = 0;
            foreach ($newTimedDayUseDemands as $candidate) {
                if ($candidate['room_class_id'] === $demand['room_class_id']
                    && $candidate['arrival'] === $demand['arrival']
                    && $candidate['arrival_time'] < $demand['departure_time']
                    && $demand['arrival_time'] < $candidate['departure_time']) {
                    $simultaneousDemand++;
                }
            }

            $availability = $avService->getAvailability(
                $demand['room_class_id'],
                $demand['arrival'],
                $demand['departure'],
                null,
                $demand['arrival_time'],
                $demand['departure_time']
            );
            $canOver = ($allowOver && $allowInputOver) || $isWaitingBooking;
            if ($availability < $simultaneousDemand && !$canOver) {
                $roomClass = \App\Models\RoomClass::find($demand['room_class_id']);
                throw new \Exception(
                    'Không đủ phòng trống cho loại phòng ' . ($roomClass?->name ?? 'không xác định')
                    . ' trong khung giờ Day Use đã chọn. Số phòng trống hiện tại: ' . $availability
                );
            }
        }
    }

    /**
     * Create child breakfast details for each day of the booking room stay.
     */
    private function createChildBreakfastDetails($child, $bRoom)
    {
        $isBaby = $child->age_group === 'baby';

        // Đọc tham số hệ thống từ hotel_configs
        $autoExtraChargeVal = \App\Models\HotelConfig::where('name', 'Booking_AutoExtraChargeBFChild')->value('value');
        $autoExtraCharge = (int) $autoExtraChargeVal === 1;

        // Xác định amount và extra charge
        $setting = \App\Models\HotelSetting::first();
        if ($isBaby) {
            $isFree = true;
            $isExtra = false;
            $amount = 0.0;
        } else {
            $isFree = false;
            $isExtra = $autoExtraCharge;
            if ($autoExtraCharge) {
                $amount = (float) ($setting?->breakfast_child_rate ?? 0);
            } else {
                $bfRateChild = \App\Models\HotelConfig::where('name', 'BreakfastRateChild')->value('value');
                $amount = (float) ($bfRateChild ?? $setting?->breakfast_child_rate ?? 0);
            }
        }

        // Dòng 417: phòng không ăn sáng thì trẻ em/em bé thêm vào cũng không ăn sáng.
        $roomBreakfast = $bRoom->breakfast === null ? true : (bool) $bRoom->breakfast;

        $current = Carbon::parse($bRoom->arrival_date);
        $departure = Carbon::parse($bRoom->departure_date);

        while ($current->lt($departure)) {
            \App\Models\BookingChildBreakfastDetail::create([
                'booking_child_id' => $child->id,
                'service_date'     => $current->toDateString(),
                'breakfast'        => $roomBreakfast,
                'is_free'          => $isFree,
                'is_extra_charge'  => $isExtra,
                'is_room'          => !$isExtra, // Nếu extra charge thì không phân bổ vào phòng (GIT)
                'amount'           => $roomBreakfast ? $amount : 0,
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

        // Dòng 368: đêm EB mới (gia hạn/điều chỉnh) theo cờ phòng/master của các đêm EB đã có.
        $defaultIsRoom = !($existingServices->isNotEmpty()
            && $existingServices->every(fn ($service) => (int) $service->is_room === 0));

        $current = Carbon::parse($arrivalDate);
        $end     = Carbon::parse($departureDate);
        $stayDates = [];
        while ($current->lt($end)) {
            $stayDates[] = $current->toDateString();
            $current = $current->addDay();
        }

        // So sánh theo ngày đã chuẩn hóa (không phụ thuộc kiểu cột date/datetime)
        // để không xóa nhầm EB của các đêm vẫn thuộc giai đoạn ở.
        $existingServices
            ->filter(fn ($service, $dateStr) => !in_array($dateStr, $stayDates, true) && (int) $service->is_posted === 0)
            ->each(fn ($service) => $service->forceDelete());

        // Ghi vào đúng dòng EB của đêm (kể cả dòng đã xóa mềm) thay vì
        // updateOrCreate theo chuỗi ngày, để không sinh dòng EB trùng ngày.
        $trashedByDate = \App\Models\BookingRoomService::onlyTrashed()
            ->where('booking_room_id', $room->id)
            ->where('service_code', \App\Models\BookingRoomService::CODE_EXTRA_BED)
            ->orderBy('id')
            ->get()
            ->keyBy(fn ($item) => Carbon::parse($item->service_date)->toDateString());
        $saveExtraBed = function (string $dateStr, array $values) use ($room, $existingServices, $trashedByDate): void {
            $row = $existingServices->get($dateStr) ?? $trashedByDate->get($dateStr);
            if ($row) {
                $row->fill($values)->save();
                return;
            }
            \App\Models\BookingRoomService::create([
                'booking_room_id' => $room->id,
                'service_code'    => \App\Models\BookingRoomService::CODE_EXTRA_BED,
                'service_date'    => $dateStr,
            ] + $values);
        };

        $latestEBRate = null;
        $latestEBQty  = null;

        foreach ($stayDates as $dateStr) {
            $existing = $existingServices->get($dateStr);
            if ($existing && $existing->is_posted == 1) {
                if ($existing->rate !== null && $latestEBRate === null) {
                    $latestEBRate = (float)$existing->rate;
                    $latestEBQty  = (int)$existing->quantity;
                }
                continue;
            }

            $debInfo = $dailyEBMap[$dateStr] ?? null;
            if ($debInfo) {
                $qty  = (int)($debInfo['quantity'] ?? 0);
                $rate = (float)($debInfo['rate'] ?? 0);
                $isRoom = !empty($debInfo['isRoom']) || (!isset($debInfo['isRoom']) && ($debInfo['is_room'] ?? ($defaultIsRoom ? 1 : 0)) != 0);
            } elseif ($hasExplicitDailyEB && $existing) {
                // Dòng 366: đêm đã có EB nhưng màn hình chưa gửi chi tiết
                // (vd. vừa gia hạn) thì giữ nguyên số lượng/giá đã cấu hình.
                continue;
            } elseif ($hasExplicitDailyEB) {
                // Đêm gia hạn mới: lấy số lượng/giá EB mặc định của phòng.
                $qty  = (int)$room->extra_bed_qty;
                $rate = (float)$room->extra_bed_rate;
                $isRoom = $defaultIsRoom;
            } else {
                // Chưa từng có dữ liệu chi tiết -> dùng phẳng từ phòng
                $qty  = (int)$room->extra_bed_qty;
                $rate = (float)$room->extra_bed_rate;
                $isRoom = $defaultIsRoom;
            }

            if ($qty > 0 && $rate > 0 && $latestEBRate === null) {
                $latestEBRate = $rate;
                $latestEBQty  = $qty;
            }

            if ($qty <= 0) {
                // Đêm không đặt thêm giường và chưa có dòng EB: không tạo dòng
                // EB số lượng 0 (khách không set EB thì không hiện trong chi tiết).
                if (!$existing) {
                    continue;
                }
                // Đối với Extra Bed: lưu quantity = 0 vào booking_room_services khi được chỉnh về 0
                $saveExtraBed($dateStr, [
                    'service_name' => \App\Models\BookingRoomService::catalogName(\App\Models\BookingRoomService::CODE_EXTRA_BED, 'Extra Bed'),
                    'quantity'     => 0,
                    'rate'         => $rate,
                    'department'   => 'FO',
                    // Dòng 368: đưa EB một đêm về 0 không đổi cờ phòng/master của đêm đó.
                    'is_room'      => (int) $existing->is_room,
                    'is_posted'    => 0,
                    'deleted_at'   => null,
                    'created_by'   => Auth::user()?->username ?? 'system',
                ]);
                continue;
            }

            $saveExtraBed($dateStr, [
                'service_name' => \App\Models\BookingRoomService::catalogName(\App\Models\BookingRoomService::CODE_EXTRA_BED, 'Extra Bed'),
                'quantity'     => $qty,
                'rate'         => $rate,
                'department'   => 'FO',
                'is_room'      => $isRoom ? 1 : 0,
                'is_posted'    => 0,
                'deleted_at'   => null,
                'created_by'   => Auth::user()?->username ?? 'system',
            ]);
        }

        // Giá EB của phòng là giá mặc định cho đêm mới; giá sửa riêng một đêm
        // (kể cả 0đ) không được ghi đè lên nó. Chỉ bổ sung khi phòng chưa có.
        $roomEBUpdate = [];
        if ($latestEBRate !== null && (float)$room->extra_bed_rate <= 0) {
            $roomEBUpdate['extra_bed_rate'] = $latestEBRate;
        }
        if ($latestEBQty !== null && (int)$room->extra_bed_qty <= 0) {
            $roomEBUpdate['extra_bed_qty'] = $latestEBQty;
        }
        if ($roomEBUpdate) {
            $room->update($roomEBUpdate);
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

        $childBreakfast = app(\App\Services\ChildBreakfastService::class);
        foreach ($services as $svc) {
            $code = $svc['service_code'] ?? null;
            if (!$code || $code === \App\Models\BookingRoomService::CODE_ROOM || $code === 'ROOM_CHARGE' || $code === \App\Models\BookingRoomService::CODE_EXTRA_BED) {
                continue;
            }
            // Detail mode: the child-breakfast surcharge lives only in
            // booking_child_breakfast_details.
            if ($childBreakfast->isDetailMode() && $childBreakfast->isChildBreakfastCode($code)) {
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
                    'department'   => 'FO',
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
                    'service_name' => \App\Models\BookingRoomService::catalogName(\App\Models\BookingRoomService::CODE_ROOM, 'Dịch vụ phòng nghỉ'),
                    'quantity'     => 1,
                    'rate'         => $rate,
                    'department'   => 'FO',
                    'is_room'      => 1,
                    'is_posted'    => 0,
                    'deleted_at'   => null,
                    'created_by'   => Auth::user()?->username ?? 'system',
                ]
            );
        }
    }

    /**
     * Resolve an existing Rate Code from persisted setup instead of trusting prices sent by the browser.
     */
    /** Stay rows whose period must stay inside the booking period (row 394). */
    private const PERIOD_BOUND_ROOM_STATUSES = [
        BookingRoom::STATUS_BOOKED,
        BookingRoom::STATUS_CHECKED_IN,
        BookingRoom::STATUS_CHECKED_OUT,
    ];

    private const BOOKING_PERIOD_MISMATCH_MESSAGE = 'Ngày đến hoặc ngày đi của đăng ký không khớp với giai đoạn chi tiết của phòng. Vui lòng kiểm tra thông tin.';

    private function roomOutsideBookingPeriod(BookingRoom $room, string $bookingArrival, string $bookingDeparture): bool
    {
        if (!$room->arrival_date || !$room->departure_date) {
            return false;
        }

        return Carbon::parse($room->arrival_date)->toDateString() < $bookingArrival
            || Carbon::parse($room->departure_date)->toDateString() > $bookingDeparture;
    }

    /**
     * Per-room "Ở theo giờ" switch sent by the registration screen. When the
     * client did not send it, fall back to the caller's default.
     */
    private function detailDayUseFlag(array $detail, bool $default): bool
    {
        foreach (['is_day_use', 'hourly'] as $key) {
            if (array_key_exists($key, $detail) && $detail[$key] !== null) {
                return filter_var($detail[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $default;
    }

    private function normalizeDayUseTime(mixed $value): ?string
    {
        if ($value === null || !preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim((string) $value), $matches)) {
            return null;
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];
        if ($hours > 23 || $minutes > 59) {
            return null;
        }

        return sprintf('%02d:%02d:00', $hours, $minutes);
    }

    private function applyConfiguredRateCodePricing(
        array $detail,
        array $allocation,
        ?string $arrivalDate,
        ?string $departureDate,
        bool $isDayUse = false
    ): array {
        $rateCodeValue = array_key_exists('rateCode', $detail)
            ? $detail['rateCode']
            : ($allocation['rateCode'] ?? null);
        $rateCode = trim((string) ($rateCodeValue ?? ''));
        $roomClassId = $allocation['roomClassId'] ?? null;

        if ($rateCode === '' || !$roomClassId || !$arrivalDate || !$departureDate) {
            return $detail;
        }

        if (!array_key_exists($rateCode, $this->rateCodePricingCache)) {
            $this->rateCodePricingCache[$rateCode] = RoomRateCode::with(['ratePlans', 'dailyMappings'])
                ->find($rateCode);
        }
        /** @var RoomRateCode|null $configuredRateCode */
        $configuredRateCode = $this->rateCodePricingCache[$rateCode];
        if (!$configuredRateCode) {
            return $detail;
        }

        // Rate Code cố định chỉ dùng giá nhập tay khi cấu hình cho phép đổi giá.
        // Rate Code theo ngày luôn phải giữ bảng giá theo ngày đã khai báo.
        // Dòng 397: cờ có thể gửi theo từng phòng (giá sửa ở dòng phòng).
        if (!$configuredRateCode->IsDaily
            && (!empty($detail['manualRateOverride']) || !empty($allocation['manualRateOverride']))
            && $configuredRateCode->AllowChangeRate
        ) {
            $detail['rateCode'] = $rateCode;
            return $detail;
        }

        if (!array_key_exists((string) $roomClassId, $this->roomClassPricingContextCache)) {
            $standardRate = StandardRate::with(['roomClass', 'roomForm'])
                ->where('room_class_id', $roomClassId)
                ->first();
            $this->roomClassPricingContextCache[(string) $roomClassId] = [
                'class_code' => $standardRate?->roomClass?->code,
                'form_name' => $standardRate?->roomForm?->name,
            ];
        }
        $roomContext = $this->roomClassPricingContextCache[(string) $roomClassId];
        if (empty($roomContext['class_code']) || empty($roomContext['form_name'])) {
            return $detail;
        }

        $arrival = Carbon::parse($arrivalDate)->startOfDay();
        $departure = Carbon::parse($departureDate)->startOfDay();
        $dailyPrices = [];
        // A day-use room is charged once, on its arrival date.
        if ($isDayUse && $departure->equalTo($arrival)) {
            $departure = $arrival->copy()->addDay();
        }

        for ($date = $arrival->copy(); $date->lt($departure); $date->addDay()) {
            $dailyPrices[$date->toDateString()] = $this->resolvePersistedRateCodePrice(
                $configuredRateCode,
                $roomContext['class_code'],
                $roomContext['form_name'],
                $date
            );
        }

        if (empty($dailyPrices)) {
            return $detail;
        }

        $firstRate = (float) reset($dailyPrices);
        $detail['rateCode'] = $rateCode;
        $detail['price'] = $firstRate;
        $detail['basePrice'] = $firstRate;
        $detail['dailyRoomPrices'] = $dailyPrices;

        return $detail;
    }

    private function resolvePersistedRateCodePrice(
        RoomRateCode $rateCode,
        string $roomClassCode,
        string $roomFormName,
        Carbon $date
    ): float {
        $plan = null;

        if ($rateCode->IsDaily) {
            $mapping = $rateCode->dailyMappings->first(
                fn ($item) => Carbon::parse($item->Date)->toDateString() === $date->toDateString()
            );
            if ($mapping) {
                $plan = $rateCode->ratePlans->firstWhere('Code', $mapping->Code);
            }
        } else {
            $plan = $rateCode->ratePlans->firstWhere('Code', 'DEFAULT');
        }

        if (!$plan) {
            return 0;
        }

        $period = is_string($plan->Period) ? json_decode($plan->Period, true) : $plan->Period;
        if (!is_array($period)) {
            return 0;
        }

        foreach ([
            $plan->Code . '_' . $roomClassCode . '_' . $roomFormName,
            $rateCode->Ma . '_' . $roomClassCode . '_' . $roomFormName,
        ] as $key) {
            if (array_key_exists($key, $period) && is_numeric($period[$key])) {
                return (float) $period[$key];
            }
        }

        return 0;
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

    /**
     * Khôi phục booking noshow (khi chọn no_charge)
     * POST /bookings/{id}/revert-noshow
     */
    public function revertNoshow(Request $request, $id)
    {
        $booking = Booking::with(['bookingRooms', 'registrationStatus'])->find($id);

        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đăng ký!'], 404);
        }

        if ($error = $this->serviceOnlyMutationError($booking)) {
            return $error;
        }

        if ((int) $booking->status === Booking::STATUS_CHECKOUT) {
            return $this->updateCheckedOutBookingMetadata($request, $booking);
        }

        // Đăng ký được coi là noshow nếu status = 4 hoặc tất cả các phòng đều có status = 4 (Noshow)
        $allRoomsNoshow = $booking->bookingRooms->count() > 0 && $booking->bookingRooms->every(function ($r) {
            return intval($r->status) === BookingRoom::STATUS_NOSHOW;
        });

        $isBookingNoshow = intval($booking->status) === Booking::STATUS_NO_SHOW || $allRoomsNoshow;

        if (!$isBookingNoshow) {
            return response()->json([
                'success' => false,
                'message' => 'Đăng ký không ở trạng thái Noshow, không thể khôi phục.',
            ], 422);
        }

        $systemDate = SystemDateRoll::latest('id')->first();
        $sysDateStr = $systemDate
            ? Carbon::parse($systemDate->system_date)->toDateString()
            : now()->toDateString();

        // 1. Validate Ngày đến: nếu có phòng nào có arrival_date < system_date -> báo lỗi
        foreach ($booking->bookingRooms as $bRoom) {
            $arrivalStr = Carbon::parse($bRoom->arrival_date)->toDateString();
            if ($arrivalStr < $sysDateStr) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ngày đến của phòng nhỏ hơn ngày hệ thống. Vui lòng kiểm tra lại thông tin.',
                ], 422);
            }
        }

        // 2. Validate AV (Phòng trống)
        $allowOver = HotelConfig::where('name', 'AllowOverRoomTypeRoomKind')->first()?->value == '1';
        $force = filter_var($request->input('force'), FILTER_VALIDATE_BOOLEAN);

        if (!$force) {
            $avService = app(RoomAvailabilityService::class);
            // Gom các phòng theo room_class_id
            $roomsByClass = [];
            foreach ($booking->bookingRooms as $bRoom) {
                if (intval($bRoom->status) === BookingRoom::STATUS_NOSHOW) {
                    $roomsByClass[$bRoom->room_class_id][] = $bRoom;
                }
            }

            foreach ($roomsByClass as $classId => $bRooms) {
                $roomClass = \App\Models\RoomClass::find($classId);
                $className = $roomClass ? $roomClass->name : "ID $classId";

                // Duyệt qua tất cả các ngày trong khoảng ngày của các phòng này để kiểm tra over
                foreach ($bRooms as $bRoom) {
                    $start = Carbon::parse($bRoom->arrival_date);
                    $end = Carbon::parse($bRoom->departure_date);
                    
                    for ($d = $start->copy(); $d->lt($end); $d->addDay()) {
                        $dateStr = $d->toDateString();
                        // AV hiện tại cho 1 ngày này
                        $av = $avService->getAvailability($classId, $dateStr, $d->copy()->addDay()->toDateString());
                        
                        // Đếm số phòng của chính booking này bao phủ ngày dateStr và đang có status là Noshow (nên chưa được tính trong booked count)
                        $needed = 0;
                        foreach ($bRooms as $r) {
                            if ($r->arrival_date <= $dateStr && $r->departure_date > $dateStr) {
                                $needed++;
                            }
                        }

                        if ($av - $needed < 0) {
                            if (!$allowOver) {
                                return response()->json([
                                    'success' => false,
                                    'message' => "Loại phòng $className đã bị Over, không thể khôi phục thông tin.",
                                ], 422);
                            } else {
                                return response()->json([
                                    'success' => false,
                                    'needs_confirm' => true,
                                    'message' => 'Số lượng phòng trống sau khi khôi phục sẽ bị âm. Bạn có muốn tiếp tục thao tác?',
                                ], 200);
                            }
                        }
                    }
                }
            }
        }

        // 3. Thực hiện khôi phục
        DB::transaction(function () use ($booking) {
            $newStatusId = RegistrationStatusMapper::codeFromLegacyCode(1);

            $booking->update([
                'status'                 => Booking::STATUS_RESERVATION,
                'registration_status_id' => $newStatusId ?? $booking->registration_status_id,
                'updated_by'             => Auth::user()?->username ?? 'system',
            ]);

            foreach ($booking->bookingRooms as $bRoom) {
                if (intval($bRoom->status) === BookingRoom::STATUS_NOSHOW) {
                    $bRoom->update([
                        'status' => BookingRoom::STATUS_BOOKED,
                    ]);

                    // Khôi phục guests
                    $guestIds = BookingRoomGuest::where('booking_room_id', $bRoom->id)->pluck('guest_id');
                    BookingRoomGuest::where('booking_room_id', $bRoom->id)
                        ->where('status', 4)
                        ->update(['status' => 0]);
                    app(\App\Services\GuestStatusSyncService::class)->syncForGuestIds($guestIds);

                    // Khôi phục children
                    BookingChild::where('booking_room_id', $bRoom->id)
                        ->where('child_status', 4)
                        ->update(['child_status' => 0]);
                }
            }
        });

        event(new \App\Events\ReservationUpdated($booking->id, 'revert_noshow', "Khôi phục booking noshow {$booking->booking_code} thành công"));

        return response()->json([
            'success' => true,
            'message' => 'Khôi phục booking noshow thành công!',
            'data'    => $booking->fresh()->load(['registrationStatus', 'bookingRooms.roomClass']),
        ]);
    }

    /**
     * Đồng bộ mã công ty sang các bảng liên quan (payments, sales_invoices, service_bills)
     * khi booking được cập nhật công ty mới.
     */
    protected function cascadeBookingCompanyUpdate(Booking $booking, $newCompanyId): void
    {
        if (empty($newCompanyId)) {
            return;
        }

        $bIdStr = (string) $booking->id;
        $bIdInt = (int) $booking->id;
        $roomIds = $booking->bookingRooms()->pluck('id')->filter()->values()->all();

        // 1. Cập nhật payments (cọc và thanh toán thuộc booking hoặc các phòng của booking)
        \App\Models\Payment::where(function ($q) use ($bIdInt, $roomIds) {
            $q->where('booking_id', $bIdInt);
            if (!empty($roomIds)) {
                $q->orWhereIn('booking_room_id', $roomIds);
            }
        })->update(['company_id' => $newCompanyId]);

        // 2. Cập nhật sales_invoices (hóa đơn thanh toán/settlement)
        \App\Models\SalesInvoice::where(function ($q) use ($bIdInt, $roomIds) {
            $q->where('booking_id', $bIdInt)
                ->orWhere('legacy_booking_id', $bIdInt);
            if (!empty($roomIds)) {
                $q->orWhereIn('booking_room_id', $roomIds)
                    ->orWhereIn('legacy_rental_room_id', $roomIds)
                    ->orWhereIn('guest_room_id', $roomIds);
            }
        })->update(['company_id' => $newCompanyId]);

        // 3. Cập nhật service_bills (SP3000):
        // 3.1. CompanyId2: Dành cho các bill đang thuộc/quản lý bởi booking này
        \App\Models\ServiceBill::where(function ($q) use ($bIdStr, $bIdInt, $roomIds) {
            $q->where('RegisterID2', $bIdStr)
                ->orWhere('RegisterID2', $bIdInt)
                ->orWhere(function ($sub) use ($bIdStr, $bIdInt, $roomIds) {
                    $sub->where(function ($reg) {
                        $reg->whereNull('RegisterID2')
                            ->orWhere('RegisterID2', '')
                            ->orWhere('RegisterID2', '0');
                    })->where(function ($owner) use ($bIdStr, $bIdInt, $roomIds) {
                        $owner->where('RegisterId1', $bIdStr)
                            ->orWhere('RegisterId1', $bIdInt);
                        if (!empty($roomIds)) {
                            $owner->orWhereIn('RentalRoomId1', $roomIds)
                                ->orWhereIn('RentalRoomId2', $roomIds);
                        }
                    });
                });
        })->update(['CompanyId2' => $newCompanyId]);

        // 3.2. CompanyId1: Dành cho các bill ban đầu sinh ra từ booking này (hoặc các phòng của booking)
        \App\Models\ServiceBill::where(function ($q) use ($bIdStr, $bIdInt, $roomIds) {
            $q->where('RegisterId1', $bIdStr)
                ->orWhere('RegisterId1', $bIdInt);
            if (!empty($roomIds)) {
                $q->orWhereIn('RentalRoomId1', $roomIds);
            }
        })->update(['CompanyId1' => $newCompanyId]);
    }
}
