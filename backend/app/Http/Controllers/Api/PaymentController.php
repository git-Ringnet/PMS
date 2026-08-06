<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Payment;
use App\Models\PaymentDebtSettlement;
use App\Models\PaymentMethod;
use App\Services\RoomAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * PaymentController — Quản lý đặt cọc (Deposit) & Advance Payment
 * Theo nghiệp vụ mục 4.9 — PLAN_NGHIEP_VU_DAT_PHONG.md
 *
 * Endpoints:
 *   GET    /bookings/{bookingId}/payments          — Danh sách cọc của booking
 *   POST   /bookings/{bookingId}/payments          — Tạo cọc mới
 *   PUT    /payments/{id}                          — Cập nhật cọc
 *   DELETE /payments/{id}                          — Xóa cọc (tạo dòng âm đối trừ)
 *   POST   /payments/{id}/split                    — Tách cọc
 *   POST   /payments/{id}/transfer                 — Chuyển cọc sang booking khác
 *   GET    /payment-methods                        — Lấy danh sách phương thức thanh toán
 */
class PaymentController extends Controller
{
    public function __construct(protected RoomAvailabilityService $avService) {}

    protected function getSystemDate(): string
    {
        $latest = \App\Models\SystemDateRoll::latest('id')->first();
        return $latest
            ? Carbon::parse($latest->system_date)->toDateString()
            : now()->timezone('Asia/Ho_Chi_Minh')->toDateString();
    }

    protected function canOperateOldDay(): bool
    {
        $user = Auth::user();
        if (!$user) return true;
        $username = strtolower((string)($user->username ?? ''));
        if (in_array($username, ['admin', 'system'], true) || !empty($user->is_admin)) return true;

        $settings = $user->setting?->settings ?? [];
        if (!isset($settings['RuleUserCorrectOrPostBillPaymentOldDay'])) return true;
        $val = $settings['RuleUserCorrectOrPostBillPaymentOldDay'];
        return $val === true || $val === 1 || $val === '1' || $val === 'true' || $val === 'default';
    }

    private function resolvePaymentMethodCode($input)
    {
        if (empty($input)) return null;
        if (is_numeric($input)) {
            $pm = PaymentMethod::find($input);
            return $pm ? $pm->code : (string)$input;
        }
        return (string)$input;
    }

    private function assertActiveDebtPayment(Payment $payment): void
    {
        if (strtoupper((string) $payment->payment_method_id) !== 'AC') {
            abort(422, 'Chỉ có thể giải trừ dòng thanh toán công nợ.');
        }

        if ((int) $payment->edit_flag !== 0 || (int) $payment->status === Payment::STATUS_DELETED) {
            abort(422, 'Dòng thanh toán công nợ đã bị xóa hoặc không còn hiệu lực.');
        }
    }

    private function resolveDebtSettlementMethod($input): PaymentMethod
    {
        $code = $this->resolvePaymentMethodCode($input);
        $method = PaymentMethod::where('code', $code)->first();

        if (!$method || $method->is_inactive || $method->is_free || in_array((int) $method->payment_group, [4, 5], true)) {
            abort(422, 'Hình thức giải trừ công nợ không hợp lệ.');
        }

        return $method;
    }

    private function getDepartmentId(Request $request)
    {
        return $request->input('department_id') 
            ?? $request->header('X-Department-ID') 
            ?? 'MR';
    }

    // =========================================
    // GET: Danh sách cọc của booking
    // =========================================
    public function index($bookingId)
    {
        $booking = Booking::findOrFail($bookingId);

        $payments = Payment::withTrashed()
            ->where('booking_id', $bookingId)
            ->with(['paymentMethod', 'bookingRoom.room'])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $totalDeposit = $payments
            ->where('pack2', Payment::PACK2_DEPOSIT)
            ->where('edit_flag', 0)
            ->whereNull('deleted_at')
            ->sum('amount');

        return response()->json([
            'success'       => true,
            'data'          => $payments,
            'total_deposit' => $totalDeposit,
        ]);
    }

    /** GET /payments/{id}/debt-settlements */
    public function debtSettlements($id)
    {
        $payment = Payment::with(['booking.company'])->findOrFail($id);
        $this->assertActiveDebtPayment($payment);

        $settlements = PaymentDebtSettlement::with('paymentMethod')
            ->where('payment_id', $payment->id)
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();
        $settledAmount = $settlements->where('edit_flag', 0)->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'payment' => [
                    'id' => $payment->id,
                    'payment_code' => $payment->payment_id,
                    'company_name' => $payment->booking?->company?->name ?? 'KHÁCH LẺ',
                    'amount' => (float) $payment->amount,
                    'settled_amount' => (float) $settledAmount,
                    'remaining_amount' => max(0, (float) $payment->amount - (float) $settledAmount),
                    'currency' => $payment->currency ?? 'VND',
                ],
                'settlements' => $settlements,
            ],
        ]);
    }

    /** POST /payments/{id}/debt-settlements */
    public function storeDebtSettlement(Request $request, $id)
    {
        $validated = $request->validate([
            'payment_date' => ['required', 'date'],
            'payment_time' => ['nullable', 'date_format:H:i'],
            'payment_method_id' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $systemDate = $this->getSystemDate();
        if (Carbon::parse($validated['payment_date'])->toDateString() < $systemDate && !$this->canOperateOldDay()) {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản không được phân quyền giải trừ công nợ cho ngày cũ (RuleUserCorrectOrPostBillPaymentOldDay).',
            ], 403);
        }

        $settlement = DB::transaction(function () use ($validated, $id) {
            $payment = Payment::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->assertActiveDebtPayment($payment);
            $paymentMethod = $this->resolveDebtSettlementMethod($validated['payment_method_id']);

            $settledAmount = (float) PaymentDebtSettlement::where('payment_id', $payment->id)
                ->where('edit_flag', 0)
                ->sum('amount');
            $remainingAmount = max(0, (float) $payment->amount - $settledAmount);
            if ((float) $validated['amount'] > $remainingAmount + 0.00001) {
                abort(422, 'Số tiền giải trừ không được lớn hơn công nợ còn lại.');
            }

            return PaymentDebtSettlement::create([
                'payment_id' => $payment->id,
                'payment_date' => $validated['payment_date'],
                'payment_time' => $validated['payment_time'] ?? now()->format('H:i'),
                'payment_method_id' => $paymentMethod->code,
                'amount' => $validated['amount'],
                'currency' => $payment->currency ?? 'VND',
                'description' => $validated['description'] ?? null,
                'edit_flag' => 0,
                'created_by' => Auth::user()?->username ?? 'system',
            ]);
        });

        return response()->json(['success' => true, 'message' => 'Đã thêm giải trừ công nợ.', 'data' => $settlement], 201);
    }

    /** DELETE /payments/{id}/debt-settlements/{settlementId} */
    public function destroyDebtSettlement($id, $settlementId)
    {
        $payment = Payment::findOrFail($id);
        $this->assertActiveDebtPayment($payment);

        $settlement = PaymentDebtSettlement::where('payment_id', $payment->id)->findOrFail($settlementId);
        if ((int) $settlement->edit_flag === 1) {
            return response()->json(['success' => false, 'message' => 'Dòng giải trừ đã bị xóa.'], 422);
        }

        $systemDate = $this->getSystemDate();
        if (Carbon::parse($settlement->payment_date)->toDateString() < $systemDate && !$this->canOperateOldDay()) {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản không được phân quyền xóa giải trừ công nợ ngày cũ (RuleUserCorrectOrPostBillPaymentOldDay).',
            ], 403);
        }

        $settlement->update([
            'edit_flag' => 1,
            'deleted_by' => Auth::user()?->username ?? 'system',
            'deleted_at' => now(),
            'updated_by' => Auth::user()?->username ?? 'system',
        ]);

        return response()->json(['success' => true, 'message' => 'Đã xóa giải trừ công nợ.']);
    }

    // =========================================
    // POST: Tạo cọc mới cho booking
    // POST /bookings/{bookingId}/payments
    // =========================================
    public function store(Request $request, $bookingId)
    {
        $booking = Booking::findOrFail($bookingId);

        // Không cho cọc booking đã checkout / đã xóa
        if (in_array($booking->status, [Booking::STATUS_CHECKOUT, Booking::STATUS_DELETED])) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể thêm cọc cho booking đã checkout hoặc đã hủy!',
            ], 422);
        }

        $systemDate = $this->getSystemDate();
        if (!$request->has('date') || empty($request->date)) {
            $request->merge(['date' => $systemDate]);
        }

        $bookingRoomId = $request->booking_room_id;
        if ($bookingRoomId !== null && $bookingRoomId !== '' && $bookingRoomId !== 'null' && $bookingRoomId !== 'undefined') {
            $bookingRoomId = (string)$bookingRoomId;
            $exists = BookingRoom::where('booking_id', $bookingId)
                ->where(function($q) use ($bookingRoomId) {
                    $q->where('id', $bookingRoomId)
                      ->orWhere('room_number', $bookingRoomId);
                })
                ->first();
            if ($exists) {
                $bookingRoomId = $exists->id;
            } else {
                $bookingRoomId = null;
            }
        } else {
            $bookingRoomId = null;
        }
        $request->merge(['booking_room_id' => $bookingRoomId]);

        $request->validate([
            'date'              => 'required|date',
            'amount'            => 'required|numeric|min:0.01',
            'payment_method_id' => 'required',
            'description'       => 'nullable|string|max:255',
            'debit_account'     => 'nullable|string|max:100',
            'booking_room_id'   => 'nullable',
            'guest_id'          => 'nullable|string|max:50',
            'folio_id'          => 'nullable|integer|between:1,3',
            'pack4'             => 'nullable|string|max:20',
            'open_time'         => 'nullable|string|max:20',
            'currency'          => 'nullable|string|max:10',
            'shift_id'          => 'nullable|string|max:20',
            'image'             => 'nullable|file|image|max:4096',
        ]);

        // Kiểm tra quyền tạo cọc ngày cũ
        if (Carbon::parse($request->date)->toDateString() < $systemDate && !$this->canOperateOldDay()) {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản không được phân quyền thêm mới đặt cọc cho ngày cũ (RuleUserCorrectOrPostBillPaymentOldDay).',
            ], 403);
        }

        // Upload ảnh chứng từ cọc nếu có
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('payments', 'public');
        }

        // Resolve payment_method code
        $pmCode = $this->resolvePaymentMethodCode($request->payment_method_id);
        $departmentId = $this->getDepartmentId($request);

        $method = PaymentMethod::where('code', $pmCode)->orWhere('id', $request->payment_method_id)->first();
        if (!$method) {
            return response()->json([
                'success' => false,
                'message' => 'Hình thức thanh toán không hợp lệ.',
            ], 422);
        }

        if (in_array($method->payment_group, [4, 5])) {
            return response()->json([
                'success' => false,
                'message' => 'Hình thức "' . $method->name . '" không được dùng cho đặt cọc / thanh toán trước. Chỉ chấp nhận Tiền mặt, Thẻ/CK hoặc Voucher.',
            ], 422);
        }

        // Tạo mô tả mặc định nếu chưa có
        $description = $request->description
            ?? ($request->pack4 === 'AP' ? 'Advance Payment - ' . $method->name : 'Đặt cọc - ' . $method->name . ' - ' . $booking->booking_code);

        // Tạo hiển thị guest: mã booking + tên
        $guestDisplay = $booking->booking_code . ' - ' . $booking->booking_name;

        $payment = DB::transaction(function () use ($request, $bookingId, $booking, $description, $guestDisplay, $imagePath, $pmCode, $departmentId) {
            $payment = Payment::create([
                'booking_id'        => $bookingId,
                'booking_room_id'   => $request->booking_room_id,
                'guest_id'          => $request->guest_id,
                'customer_id'       => $request->guest_id,
                'company_id'        => $booking->company_id,
                'date'              => $request->date,
                'open_time'         => $request->open_time ?: now()->format('H:i:s'),
                'guest_display'     => $guestDisplay,
                'description'       => $description,
                'amount'            => $request->amount,
                'currency'          => $request->currency ?: 'VND',
                'pack2'             => $request->pack4 === 'AP' ? null : Payment::PACK2_DEPOSIT,
                'pack4'             => $request->pack4 ?: null,
                'folio_id'          => $request->booking_room_id ? ($request->folio_id ?? 1) : 1,
                'payment_method_id' => $pmCode,
                'debit_account'     => $request->debit_account,
                'department_id'     => $departmentId,
                'status'            => Payment::STATUS_PENDING,
                'edit_flag'         => 0,
                'shift'             => $request->shift_id ?: ($request->shift ?: '1'),
                'created_by'        => Auth::user()?->username ?? 'system',
                'image_path'        => $imagePath,
            ]);

            // Cập nhật payment_value trên booking header = tổng cọc & tạm ứng
            $totalDeposit = Payment::where('booking_id', $bookingId)
                ->where(function($q) {
                    $q->where('pack2', Payment::PACK2_DEPOSIT)->orWhere('pack4', Payment::PACK4_ADVANCE);
                })
                ->where('edit_flag', 0)
                ->whereNull('deleted_at')
                ->sum('amount');

            $booking->update(['payment_value' => $totalDeposit]);

            return $payment;
        });

        return response()->json([
            'success' => true,
            'data'    => $payment->load('paymentMethod'),
            'message' => 'Tạo cọc thành công!',
        ], 201);
    }

    // =========================================
    // PUT: Cập nhật cọc (Không cho phép sửa ngày và số tiền)
    // PUT /payments/{id}
    // =========================================
    public function update(Request $request, $id)
    {
        $payment = Payment::findOrFail($id);

        if ($payment->edit_flag !== 0 || $payment->status !== Payment::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể sửa cọc đã thanh toán hoặc đã hủy.',
            ], 422);
        }

        $request->validate([
            'payment_method_id' => 'sometimes',
            'booking_room_id'   => 'nullable',
            'description'       => 'nullable|string|max:255',
            'debit_account'     => 'nullable|string|max:100',
            'image'             => 'nullable|file|image|max:4096',
        ]);

        DB::transaction(function () use ($request, $payment) {
            // Không cho sửa date và amount
            $data = $request->only(['description', 'debit_account', 'booking_room_id']);
            if ($request->has('payment_method_id')) {
                $data['payment_method_id'] = $this->resolvePaymentMethodCode($request->payment_method_id);
            }
            if ($request->hasFile('image')) {
                $data['image_path'] = $request->file('image')->store('payments', 'public');
            }
            $payment->update(array_merge(
                $data,
                ['updated_by' => Auth::user()?->username ?? 'system']
            ));

            // Sync payment_value trên booking header
            $totalDeposit = Payment::where('booking_id', $payment->booking_id)
                ->where('pack2', Payment::PACK2_DEPOSIT)
                ->where('edit_flag', 0)
                ->whereNull('deleted_at')
                ->sum('amount');

            Booking::where('id', $payment->booking_id)->update(['payment_value' => $totalDeposit]);
        });

        return response()->json([
            'success' => true,
            'data'    => $payment->fresh()->load('paymentMethod'),
            'message' => 'Cập nhật cọc thành công!',
        ]);
    }

    /** Chuyển Folio cho cọc chưa được dùng để thanh toán. */
    public function transferFolio(Request $request, $id)
    {
        $validated = $request->validate([
            'folio_id' => 'required|integer|between:1,3',
            'payment_ids' => 'nullable|array|min:1',
            'payment_ids.*' => 'integer',
        ]);
        $paymentIds = array_unique(array_merge([(int) $id], array_map('intval', $validated['payment_ids'] ?? [])));

        DB::transaction(function () use ($paymentIds, $validated) {
            $payments = Payment::whereIn('id', $paymentIds)->lockForUpdate()->get();
            if ($payments->count() !== count($paymentIds)) {
                abort(404, 'Không tìm thấy cọc.');
            }
            if ($payments->contains(fn (Payment $payment) => $payment->edit_flag !== 0 || $payment->status !== Payment::STATUS_PENDING || !empty($payment->payment_id))) {
                abort(422, 'Chỉ được chuyển Folio cho cọc chưa dùng để thanh toán.');
            }

            $payments->each(function (Payment $payment) use ($validated) {
                $payment->update([
                    'folio_id' => $validated['folio_id'],
                    'updated_by' => Auth::user()?->username ?? 'system',
                ]);
            });
        });

        return response()->json([
            'success' => true,
            'message' => count($paymentIds) > 1 ? 'Đã chuyển các cọc sang Folio mới.' : 'Đã chuyển cọc sang Folio mới.',
        ]);
    }

    // =========================================
    // DELETE: Xóa cọc — tạo dòng âm đối trừ (reversal)
    // DELETE /payments/{id}
    // =========================================
    public function destroy(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:1|max:1000',
        ]);
        $payment = Payment::findOrFail($id);

        if ($payment->edit_flag !== 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cọc / thanh toán này đã bị hủy hoặc đã được xử lý trước đó.',
            ], 422);
        }

        $systemDate = $this->getSystemDate();
        $paymentDate = Carbon::parse($payment->date)->toDateString();
        if ($paymentDate < $systemDate && !$this->canOperateOldDay()) {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản không được phân quyền xóa cọc / thanh toán cho ngày cũ (RuleUserCorrectOrPostBillPaymentOldDay). Chỉ được xóa cọc có ngày = ngày hệ thống.',
            ], 403);
        }

        $departmentId = $this->getDepartmentId($request);

        DB::transaction(function () use ($payment, $systemDate, $departmentId, $validated) {
            // Tạo dòng âm đối trừ với ngày hệ thống hiện tại
            $reversal = Payment::create([
                'booking_id'        => $payment->booking_id,
                'booking_room_id'   => $payment->booking_room_id,
                'company_id'        => $payment->company_id,
                'date'              => $systemDate,
                'open_time'         => now()->format('H:i:s'),
                'guest_display'     => $payment->guest_display,
                'description'       => '[REVERSAL] ' . $payment->description,
                'reason'            => $validated['reason'],
                'amount'            => -abs($payment->amount), // Số âm
                'guest_id'          => $payment->guest_id,
                'pack2'             => $payment->pack2,
                'pack4'             => $payment->pack4,
                'payment_method_id' => $payment->payment_method_id,
                'department_id'     => $departmentId,
                'image_path'        => $payment->image_path,
                'reversal_ref'      => $payment->id,
                'status'            => Payment::STATUS_DELETED,
                'edit_flag'         => 1,
                'created_by'        => Auth::user()?->username ?? 'system',
            ]);

            // Nếu đây là bản ghi thanh toán (có payment_id), nhả payment_id trên các bill liên quan
            if (!empty($payment->payment_id)) {
                $serviceBillIds = \App\Models\ServiceBill::where('PaymentID', $payment->payment_id)->pluck('Ma');
                \App\Models\ServiceBill::where('PaymentID', $payment->payment_id)
                    ->update(['PaymentID' => null, 'Status' => 1, 'InvoiceId' => null]);
                if (\Illuminate\Support\Facades\Schema::hasColumn('booking_room_services', 'payment_id')) {
                    \App\Models\BookingRoomService::where('payment_id', $payment->payment_id)
                        ->update(['payment_id' => null, 'status' => 1]);
                } elseif (\Illuminate\Support\Facades\Schema::hasColumn('booking_room_services', 'status')) {
                    $roomServiceQuery = \App\Models\BookingRoomService::whereIn('service_bill_id', $serviceBillIds);
                    $roomServiceQuery->update(['status' => 1]);
                }
                if ($serviceBillIds->isNotEmpty()) {
                    \App\Models\HousekeepingServiceBill::whereIn('BillServiceId', $serviceBillIds)
                        ->update(['Status' => 1, 'BillEdit' => 0]);
                }
            }

            // Đánh dấu dòng gốc đã hủy, lưu ref sang dòng âm
            $payment->update([
                'edit_flag'    => 1,
                'reversal_ref' => $reversal->id,
                'updated_by'   => Auth::user()?->username ?? 'system',
            ]);

            // Hoàn nguyên các phiếu cọc đã được settlement này sử dụng.
            // Nếu không reset, phiếu cọc vẫn giữ mã thanh toán và hiển thị Đã thanh toán.
            if (!empty($payment->payment_id)) {
                Payment::where('payment_id', $payment->payment_id)
                    ->where('edit_flag', 0)
                    ->whereNull('deleted_at')
                    ->update([
                        'payment_id' => null,
                        'status' => Payment::STATUS_PENDING,
                        'updated_by' => Auth::user()?->username ?? 'system',
                    ]);
            }

            $payment->delete(); // Soft delete để cập nhật deleted_at

            // Sync payment_value
            $totalDeposit = Payment::where('booking_id', $payment->booking_id)
                ->where('pack2', Payment::PACK2_DEPOSIT)
                ->where('edit_flag', 0)
                ->whereNull('deleted_at')
                ->sum('amount');
            Booking::where('id', $payment->booking_id)->update(['payment_value' => $totalDeposit]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa cọc / thanh toán thành công (tạo dòng đối trừ).',
        ]);
    }

    // =========================================
    // POST: Tách cọc thành nhiều phần
    // POST /payments/{id}/split
    // Body: amounts[] = danh sách số tiền sau khi tách (tổng phải = amount gốc)
    // =========================================
    // =========================================
    // POST: Tách cọc thành nhiều phần
    // POST /payments/{id}/split
    // Body: amounts[] = danh sách số tiền sau khi tách (tổng phải = amount gốc)
    // =========================================
    public function split(Request $request, $id)
    {
        $payment = Payment::findOrFail($id);

        if ($payment->edit_flag !== 0 || $payment->status !== Payment::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ có thể tách cọc đang chờ thanh toán.',
            ], 422);
        }

        if (!empty($payment->payment_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể tách cọc đã được dùng để thanh toán.',
            ], 422);
        }

        $request->validate([
            'amounts'   => 'required|array|min:2',
            'amounts.*' => 'required|numeric|min:0.01',
            'folio_id'  => 'nullable|integer|between:1,3',
        ]);

        $sumNew = array_sum($request->amounts);
        if (abs($sumNew - $payment->amount) > 0.01) {
            return response()->json([
                'success' => false,
                'message' => 'Tổng số tiền sau khi tách (' . number_format($sumNew, 2) . ') phải bằng số tiền gốc (' . number_format($payment->amount, 2) . ').',
            ], 422);
        }

        DB::transaction(function () use ($request, $payment) {
            $originalAmount = $payment->total_amount_before_split ?? $payment->amount;

            // 1. Cập nhật dòng gốc: đổi amount thành số tiền phần 1, lưu total_amount_before_split
            // Giữ nguyên edit_flag = 0, status = PENDING, KHÔNG xóa dòng gốc (SP3002 spec)
            $payment->update([
                'amount'                    => $request->amounts[0],
                'total_amount_before_split' => $originalAmount,
                'updated_by'                => Auth::user()?->username ?? 'system',
                'updated_at'                => now(),
            ]);

            // 2. Tạo các dòng cọc mới tách (phần 2, phần 3...)
            // SP3002 Spec: Date và CreateDate/created_by lấy giống dòng gốc
            $count = count($request->amounts);
            for ($i = 1; $i < $count; $i++) {
                $amt = $request->amounts[$i];
                Payment::create([
                    'booking_id'                => $payment->booking_id,
                    'booking_room_id'           => $payment->booking_room_id,
                    'guest_id'                  => $payment->guest_id,
                    'company_id'                => $payment->company_id,
                    'date'                      => $payment->date, // Ngày dòng gốc
                    'open_time'                 => $payment->open_time ?? now()->format('H:i:s'),
                    'guest_display'             => $payment->guest_display,
                    'description'               => $payment->description,
                    'amount'                    => $amt,
                    'total_amount_before_split' => $originalAmount,
                    'pack2'                     => Payment::PACK2_DEPOSIT,
                    // Checkout may place the new split line in another Folio.
                    // Legacy callers without folio_id keep the source Folio.
                    'folio_id'                  => $request->input('folio_id', $payment->folio_id),
                    'payment_method_id'         => $payment->payment_method_id,
                    'debit_account'             => $payment->debit_account,
                    // Tách cọc giữ nguyên bộ phận của dòng gốc; chỉ dấu vết cập nhật là thời điểm thao tác.
                    'department_id'             => $payment->department_id,
                    'image_path'                => $payment->image_path,
                    'reversal_ref'              => $payment->id,
                    'status'                    => Payment::STATUS_PENDING,
                    'edit_flag'                 => 0,
                    'created_by'                => $payment->created_by ?? Auth::user()?->username ?? 'system',
                    'created_at'                => $payment->created_at ?? now(),
                    'updated_at'                => now(),
                ]);
            }

            // Sync payment_value trên booking header
            $totalDeposit = Payment::where('booking_id', $payment->booking_id)
                ->where('pack2', Payment::PACK2_DEPOSIT)
                ->where('edit_flag', 0)
                ->whereNull('deleted_at')
                ->sum('amount');

            Booking::where('id', $payment->booking_id)->update(['payment_value' => max(0, $totalDeposit)]);
        });

        $activePayments = Payment::where('booking_id', $payment->booking_id)
            ->where('pack2', Payment::PACK2_DEPOSIT)
            ->where('edit_flag', 0)
            ->whereNull('deleted_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $activePayments,
            'message' => 'Tách cọc thành công! Đã tách thành ' . count($request->amounts) . ' phần.',
        ], 200);
    }

    // =========================================
    // POST: Chuyển cọc sang Master/phòng của booking đích
    // POST /payments/{id}/transfer
    // Body: target_booking_id (required), target_room_id/target_guest_id (optional)
    // =========================================
    public function transferMany(Request $request)
    {
        $request->validate([
            'payment_ids'        => 'required|array|min:1',
            'payment_ids.*'      => 'integer|distinct|exists:payments,id',
            'target_booking_id'  => 'required|exists:bookings,id',
            'target_room_id'     => 'nullable|exists:booking_rooms,id',
            'target_guest_id'    => 'nullable|exists:guests,id',
        ]);

        $targetBooking = Booking::findOrFail($request->target_booking_id);
        if (!in_array((int) $targetBooking->status, [0, 1], true)) {
            abort(422, 'Chỉ có thể chuyển cọc sang Booking ở trạng thái Đăng ký (0) hoặc Đang ở (1).');
        }

        $targetRoom = $request->target_room_id
            ? BookingRoom::where('booking_id', $targetBooking->id)->findOrFail($request->target_room_id)
            : null;
        if ($targetRoom && !in_array((int) $targetRoom->status, [BookingRoom::STATUS_BOOKED, BookingRoom::STATUS_CHECKED_IN], true)) {
            abort(422, 'Phòng nhận cọc phải ở trạng thái Reservation hoặc Inhouse.');
        }
        $targetGuest = null;
        if ($targetRoom) {
            $targetGuest = $request->target_guest_id
                ? $targetRoom->guests()->with('guest')->where('guest_id', $request->target_guest_id)->firstOrFail()
                : $targetRoom->guests()->with('guest')->where('is_primary', 1)->first();
        }

        DB::transaction(function () use ($request, $targetBooking, $targetRoom, $targetGuest) {
            $payments = Payment::whereIn('id', $request->payment_ids)->lockForUpdate()->get();
            if ($payments->count() !== count($request->payment_ids)) abort(422, 'Không tìm thấy đủ các dòng cọc cần chuyển.');

            foreach ($payments as $payment) {
                if ($payment->edit_flag !== 0 || $payment->status !== Payment::STATUS_PENDING || !empty($payment->payment_id)) {
                    abort(422, 'Chỉ có thể chuyển các dòng cọc chưa được thanh toán.');
                }

                $sourceGuestId = $payment->guest_id;
                if (!$sourceGuestId && $payment->booking_room_id) {
                    $sourceRoom = $payment->bookingRoom;
                    $sourcePrimaryGuest = $sourceRoom?->guests()->where('is_primary', 1)->first();
                    $sourceGuestId = $sourcePrimaryGuest?->guest_id;
                }
                $targetGuestId = $targetGuest?->guest_id;

                $isSameLocation = (int) $payment->booking_id === (int) $targetBooking->id
                    && (string) ($payment->booking_room_id ?? '') === (string) ($targetRoom?->id ?? '')
                    && (string) ($sourceGuestId ?? '') === (string) ($targetGuestId ?? '');
                if ($isSameLocation) abort(422, 'Nơi nhận phải khác vị trí cọc hiện tại.');
            }

            foreach ($payments as $payment) {
                $response = $this->transfer($request, $payment->id);
                if ($response->getStatusCode() >= 400) {
                    abort($response->getStatusCode(), $response->getData(true)['message'] ?? 'Không thể chuyển cọc.');
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Chuyển ' . count($request->payment_ids) . ' dòng cọc thành công!',
        ]);
    }

    public function transfer(Request $request, $id)
    {
        $payment = Payment::findOrFail($id);

        if ($payment->edit_flag !== 0 || $payment->status !== Payment::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ có thể chuyển cọc đang chờ thanh toán.',
            ], 422);
        }

        // Không cho chuyển cọc đã được dùng để thanh toán (payment_id có giá trị)
        if (!empty($payment->payment_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể chuyển cọc đã được dùng để thanh toán.',
            ], 422);
        }

        $request->validate([
            'target_booking_id' => 'required|exists:bookings,id',
            'target_room_id'    => 'nullable|exists:booking_rooms,id',
            'target_guest_id'   => 'nullable|exists:guests,id',
        ]);

        $targetBooking = Booking::findOrFail($request->target_booking_id);

        if (!in_array((int)$targetBooking->status, [0, 1])) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ có thể chuyển cọc sang Booking ở trạng thái Đăng ký (0) hoặc Đang ở (1).',
            ], 422);
        }

        $targetRoom = $request->target_room_id
            ? BookingRoom::where('booking_id', $targetBooking->id)->findOrFail($request->target_room_id)
            : null;
        if ($targetRoom && !in_array((int) $targetRoom->status, [BookingRoom::STATUS_BOOKED, BookingRoom::STATUS_CHECKED_IN], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Phòng nhận cọc phải ở trạng thái Reservation hoặc Inhouse.',
            ], 422);
        }

        $targetGuest = null;
        if ($targetRoom) {
            $targetGuest = $request->target_guest_id
                ? $targetRoom->guests()->with('guest')->where('guest_id', $request->target_guest_id)->firstOrFail()
                : $targetRoom->guests()->with('guest')->where('is_primary', 1)->first();
        } elseif ($request->target_guest_id) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ được chọn khách khi nơi nhận là một phòng cụ thể.',
            ], 422);
        }

        $sourceGuestId = $payment->guest_id;
        if (!$sourceGuestId && $payment->booking_room_id) {
            $sourceRoom = $payment->bookingRoom;
            $sourcePrimaryGuest = $sourceRoom?->guests()->where('is_primary', 1)->first();
            $sourceGuestId = $sourcePrimaryGuest?->guest_id;
        }
        $targetGuestId = $targetGuest?->guest_id;

        $isSameLocation = (int) $payment->booking_id === (int) $targetBooking->id
            && (string) ($payment->booking_room_id ?? '') === (string) ($targetRoom?->id ?? '')
            && (string) ($sourceGuestId ?? '') === (string) ($targetGuestId ?? '');
        if ($isSameLocation) {
            return response()->json([
                'success' => false,
                'message' => 'Nơi nhận phải khác vị trí cọc hiện tại.',
            ], 422);
        }

        $systemDate = $this->getSystemDate();
        $departmentId = $this->getDepartmentId($request);

        DB::transaction(function () use ($request, $payment, $targetBooking, $targetRoom, $targetGuest, $systemDate, $departmentId) {
            $sourceBookingId = $payment->booking_id;
            $sourceBooking = Booking::findOrFail($sourceBookingId);
            $sourceLocation = $payment->booking_room_id ? 'R_' . ($payment->bookingRoom?->room_number ?: $payment->booking_room_id) : 'BK_' . $sourceBookingId;
            $targetLocation = $targetRoom ? 'R_' . ($targetRoom->room_number ?: $targetRoom->id) : 'BK_' . $targetBooking->id;

            // 1. Tạo dòng âm trên booking nguồn (ngày hệ thống, user thao tác, edit_flag=1) - SP3002 Spec
            $reversal = Payment::create([
                'booking_id'        => $sourceBookingId,
                'booking_room_id'   => $payment->booking_room_id,
                'guest_id'          => $payment->guest_id,
                'company_id'        => $payment->company_id,
                'date'              => $systemDate,
                'open_time'         => now()->format('H:i:s'),
                'guest_display'     => $payment->guest_display,
                'description'       => '[TRANSFER OUT ' . $sourceLocation . '=>' . $targetLocation . '] ' . $payment->description,
                'amount'            => -abs($payment->amount),
                'total_amount_before_split' => $payment->total_amount_before_split,
                'pack2'             => Payment::PACK2_DEPOSIT,
                'pack4'             => $payment->pack4,
                'folio_id'          => $payment->folio_id,
                'payment_method_id' => $payment->payment_method_id,
                'debit_account'     => $payment->debit_account,
                'department_id'     => $departmentId,
                'image_path'        => $payment->image_path,
                'reversal_ref'      => $payment->id,
                'status'            => Payment::STATUS_DELETED,
                'edit_flag'         => 1,
                'created_by'        => Auth::user()?->username ?? 'system',
            ]);

            // Đánh dấu dòng gốc đã chuyển (edit_flag=1, reversal_ref trỏ sang dòng âm)
            $payment->update([
                'edit_flag'    => 1,
                'reversal_ref' => $reversal->id,
                'updated_by'   => Auth::user()?->username ?? 'system',
            ]);
            $payment->delete(); // Soft delete cọc gốc đã chuyển

            // 2. Tạo dòng dương mới trên booking đích (edit_flag=0)
            // SP3002 Spec: Date và CreateUser/created_at GIỐNG VỚI DÒNG GỐC BAN ĐẦU
            $newPayment = Payment::create([
                'booking_id'        => $request->target_booking_id,
                'booking_room_id'   => $targetRoom?->id,
                'guest_id'          => $targetGuest?->guest_id,
                'company_id'        => $targetBooking->company_id,
                'date'              => $payment->date, // Ngày dòng gốc ban đầu
                'open_time'         => $payment->open_time ?? now()->format('H:i:s'),
                'guest_display'     => $targetRoom
                    ? $targetBooking->booking_code . ' - ' . ($targetGuest?->guest?->full_name ?: $targetRoom->room_number)
                    : $targetBooking->booking_code . ' - ' . $targetBooking->booking_name,
                'description'       => '[TRANSFER IN ' . $sourceLocation . '=>' . $targetLocation . '] ' . $payment->description,
                'amount'            => abs($payment->amount),
                'total_amount_before_split' => $payment->total_amount_before_split,
                'pack2'             => Payment::PACK2_DEPOSIT,
                'pack4'             => $payment->pack4,
                'folio_id'          => 1,
                'payment_method_id' => $payment->payment_method_id,
                'debit_account'     => $payment->debit_account,
                'department_id'     => $departmentId,
                'image_path'        => $payment->image_path,
                'reversal_ref'      => $payment->id,
                'status'            => Payment::STATUS_PENDING,
                'edit_flag'         => 0,
                'created_by'        => $payment->created_by ?? Auth::user()?->username ?? 'system',
                'created_at'        => $payment->created_at ?? now(),
                'updated_at'        => now(),
            ]);

            // Sync payment_value cả 2 booking
            foreach ([$sourceBookingId, $request->target_booking_id] as $bkId) {
                $total = Payment::where('booking_id', $bkId)
                    ->where('pack2', Payment::PACK2_DEPOSIT)
                    ->where('edit_flag', 0)
                    ->whereNull('deleted_at')
                    ->sum('amount');
                Booking::where('id', $bkId)->update(['payment_value' => max(0, $total)]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Chuyển cọc thành công!',
        ]);
    }

    /**
     * POST /bookings/{bookingId}/settle-payment
     * Xử lý lưu thanh toán Folio (Settlement)
     */
    public function settlePayment(Request $request, $bookingId)
    {
        $booking = Booking::findOrFail($bookingId);
        $request->validate([
            'payments' => 'required|array|min:1',
            'payments.*.amount' => 'required|numeric',
            'service_bill_ids' => 'nullable|array',
            'service_bill_ids.*' => 'integer',
        ]);

        $folioId = $request->input('folio_id', '1');
        $systemDate = $this->getSystemDate();
        $departmentId = $this->getDepartmentId($request);

        DB::transaction(function () use ($request, $booking, $bookingId, $folioId, $systemDate, $departmentId) {
            // Sinh mã thanh toán settlement (ví dụ numeric string ID 5 chữ số e.g. "11575")
            $maxPayment = Payment::max('id') ?? 11000;
            $settlementCode = (string)($maxPayment + rand(100, 500));
            $invoiceCode = (string)rand(7000, 9999);

            $isFolioA = strtoupper((string) $folioId) === 'A';
            $targetFolio = $isFolioA ? 3 : (is_numeric($folioId) ? (int)$folioId : 1);

            $reqRoomId = $request->input('booking_room_id') ?? $request->input('bookingRoomId') ?? $request->input('room_id') ?? $request->input('roomId');
            $reqGuestId = $request->input('guest_id') ?? $request->input('guestId');
            $selectedBillIds = collect($request->input('service_bill_ids', []))
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();
            $unpaidDepositQuery = Payment::where('booking_id', $bookingId)
                ->where('edit_flag', 0)
                ->whereNull('deleted_at')
                ->where(function ($q) {
                    $q->whereNull('payment_id')->orWhere('payment_id', '');
                });
            if ($reqRoomId) {
                $unpaidDepositQuery->where('booking_room_id', $reqRoomId);
            } else {
                $unpaidDepositQuery->whereNull('booking_room_id');
            }
            if (!$isFolioA) {
                $unpaidDepositQuery->where('folio_id', $folioId);
            }
            if ($reqGuestId) {
                $unpaidDepositQuery->where('guest_id', $reqGuestId);
            }

            $unpaidServiceQuery = \App\Models\ServiceBill::query();
            if ($selectedBillIds) {
                $unpaidServiceQuery->whereIn('Ma', $selectedBillIds);
            } else {
                $unpaidServiceQuery->where(function ($q) use ($booking, $bookingId, $reqRoomId) {
                if ($reqRoomId) {
                    $q->where(function ($q2) use ($reqRoomId) {
                        $q2->whereRaw('CAST(RentalRoomId1 AS CHAR) = ?', [(string) $reqRoomId])
                           ->orWhereRaw('CAST(RentalRoomId2 AS CHAR) = ?', [(string) $reqRoomId]);
                    });
                    return;
                }

                $q->where(function ($q2) use ($bookingId) {
                    $q2->where(function ($q3) use ($bookingId) {
                        $q3->whereRaw('CAST(RegisterID2 AS CHAR) = ?', [(string) $bookingId])
                           ->where(function ($q4) {
                               $q4->whereNull('RentalRoomId2')->orWhere('RentalRoomId2', '')->orWhere('RentalRoomId2', '0');
                           });
                    })->orWhere(function ($q3) use ($bookingId) {
                        $q3->whereRaw('CAST(RegisterId1 AS CHAR) = ?', [(string) $bookingId])
                           ->whereNull('RentalRoomId1')
                           ->where(function ($q4) {
                               $q4->whereNull('RentalRoomId2')->orWhere('RentalRoomId2', '')->orWhere('RentalRoomId2', '0');
                           });
                    });
                });
                });
            }
            if ($reqRoomId) {
                $unpaidServiceQuery->where(function ($q) use ($reqRoomId) {
                    $q->whereRaw('CAST(RentalRoomId2 AS CHAR) = ?', [(string) $reqRoomId])
                      ->orWhere(function ($fallback) use ($reqRoomId) {
                          $fallback->where(function ($owner) {
                              $owner->whereNull('RegisterID2')->orWhere('RegisterID2', '');
                          })->where(function ($room) {
                              $room->whereNull('RentalRoomId2')->orWhere('RentalRoomId2', '')->orWhere('RentalRoomId2', '0');
                          })->whereRaw('CAST(RentalRoomId1 AS CHAR) = ?', [(string) $reqRoomId]);
                      });
                });
            }
            if (!$isFolioA) {
                $unpaidServiceQuery->where(function ($q) use ($folioId) {
                    $q->where('Folio', (string) $folioId)
                      ->orWhere('Folio', (int) $folioId)
                      ->orWhereNull('Folio')
                      ->orWhere('Folio', '0')
                      ->orWhere('Folio', '');
                });
            }
            $unpaidServiceQuery
                ->where(function ($q) {
                    $q->whereNull('PaymentId')->orWhere('PaymentId', '')->orWhereNull('PaymentID')->orWhere('PaymentID', '');
                })
                ->where('Edit', 0);
            if ($reqRoomId && (bool) $booking->is_master_room_rate) {
                $unpaidServiceQuery->whereNotIn('ServiceId', ['RM', 'RMS']);
            }
            if ($reqGuestId) {
                $unpaidServiceQuery->where(function ($q) use ($reqGuestId) {
                    $q->whereRaw('CAST(CustomerId2 AS CHAR) = ?', [(string) $reqGuestId])
                      ->orWhere(function ($fallback) use ($reqGuestId) {
                          $fallback->where(function ($guest) {
                              $guest->whereNull('CustomerId2')->orWhere('CustomerId2', '');
                          })->whereRaw('CAST(CustomerId1 AS CHAR) = ?', [(string) $reqGuestId]);
                      });
                });
            }

            $outstandingAmount = round(
                (float) $unpaidServiceQuery->sum('Amount') - (float) $unpaidDepositQuery->sum('amount'),
                2
            );
            $settlementAmount = round(collect($request->input('payments', []))->sum(fn ($payment) => (float) ($payment['amount'] ?? 0)), 2);
            $difference = round($outstandingAmount - $settlementAmount, 2);
            if (abs($difference) > 0.01) {
                $message = $difference > 0
                    ? 'Còn thiếu ' . number_format($difference, 0, ',', '.') . ' VND. Vui lòng thanh toán đủ trước khi lưu.'
                    : 'Số tiền thanh toán vượt ' . number_format(abs($difference), 0, ',', '.') . ' VND. Vui lòng nhập đúng số tiền cần thanh toán.';

                throw \Illuminate\Validation\ValidationException::withMessages([
                    'payments' => [$message],
                ]);
            }

            // 1. Tạo các bản ghi Payment thanh toán từ danh sách payments trong modal
            foreach ($request->input('payments', []) as $pItem) {
                $amt = (float)($pItem['amount'] ?? 0);
                $pmId = $this->resolvePaymentMethodCode($pItem['payment_method_id'] ?? 'CA');
                $note = $pItem['note'] ?? ('Thanh toán - ' . $pmId);

                Payment::create([
                    'booking_id'        => $bookingId,
                    'booking_room_id'   => $request->input('booking_room_id'),
                    'guest_id'          => $request->input('guest_id'),
                    'company_id'        => $booking->company_id,
                    'date'              => $request->input('date', $systemDate),
                    'open_time'         => $request->input('open_time', now()->format('H:i:s')),
                    'guest_display'     => $booking->booking_code . ' - ' . $booking->booking_name,
                    'description'       => $note,
                    'amount'            => $amt,
                    'total_amount_before_split' => $amt,
                    'pack2'             => Payment::PACK2_DEPOSIT,
                    'pack4'             => 'PY', // Settlement payment
                    'folio_id'          => $targetFolio,
                    'payment_method_id' => $pmId,
                    'debit_account'     => $pItem['bank_account'] ?? null,
                    'department_id'     => $departmentId,
                    'payment_id'        => $settlementCode,
                    'status'            => Payment::STATUS_PAID, // 2
                    'edit_flag'         => 0,
                    'created_by'        => Auth::user()?->username ?? 'system',
                ]);
            }

            // 2. Cập nhật mã thanh toán payment_id trên các bản ghi cọc/tạm ứng hiện có thuộc Folio này
            $targetRoomIds = $reqRoomId ? [(string)$reqRoomId] : $booking->bookingRooms->pluck('id')->map(fn($id) => (string)$id)->toArray();
            // Một số dữ liệu legacy dùng mã phòng dạng Gxxxx, không thể so sánh
            // trực tiếp với các cột RentalRoomId kiểu số trong ServiceBill.

            $paymentQuery = Payment::where('booking_id', $bookingId)
                ->where('edit_flag', 0)
                ->whereNull('deleted_at')
                ->where(function ($q) {
                    $q->whereNull('payment_id')->orWhere('payment_id', '');
                });
            if ($reqRoomId) {
                $paymentQuery->where('booking_room_id', $reqRoomId);
            } else {
                // Thanh toán Master chỉ gán cho khoản cọc/thanh toán của Master.
                // Không được kéo các khoản đã gắn booking_room_id của phòng khác.
                $paymentQuery->whereNull('booking_room_id');
            }
            if (!$isFolioA) {
                $paymentQuery->where('folio_id', $folioId);
            }
            if ($reqGuestId) {
                $paymentQuery->where('guest_id', $reqGuestId);
            }
            $updatePaymentData = [
                'payment_id' => $settlementCode,
                'status'     => Payment::STATUS_PAID,
            ];
            if ($isFolioA) {
                $updatePaymentData['folio_id'] = 3;
            }
            $paymentQuery->update($updatePaymentData);

            // 3. Cập nhật dịch vụ ServiceBill thuộc Folio này thành Đã thanh toán (status = 2) và gán PaymentId & InvoiceId
            $serviceBillQuery = \App\Models\ServiceBill::query();
            if ($selectedBillIds) {
                $serviceBillQuery->whereIn('Ma', $selectedBillIds);
            } else {
                $serviceBillQuery->where(function ($q) use ($booking, $bookingId, $targetRoomIds, $reqRoomId) {
                if ($reqRoomId) {
                    $q->where(function ($q2) use ($reqRoomId) {
                        $q2->whereRaw('CAST(RentalRoomId1 AS CHAR) = ?', [(string) $reqRoomId])
                           ->orWhereRaw('CAST(RentalRoomId2 AS CHAR) = ?', [(string) $reqRoomId]);
                    });
                } else {
                    $q->where(function ($q2) use ($bookingId) {
                        $q2->where(function ($q3) use ($bookingId) {
                            $q3->whereRaw('CAST(RegisterID2 AS CHAR) = ?', [(string) $bookingId])
                               ->where(function ($q4) {
                                   $q4->whereNull('RentalRoomId2')->orWhere('RentalRoomId2', '')->orWhere('RentalRoomId2', '0');
                               });
                        })->orWhere(function ($q3) use ($bookingId) {
                            $q3->whereRaw('CAST(RegisterId1 AS CHAR) = ?', [(string) $bookingId])
                               ->whereNull('RentalRoomId1')
                               ->where(function ($q4) {
                                   $q4->whereNull('RentalRoomId2')->orWhere('RentalRoomId2', '')->orWhere('RentalRoomId2', '0');
                               });
                        });
                    });
                }
                });
            }
            if ($reqRoomId) {
                $serviceBillQuery->where(function ($q) use ($reqRoomId) {
                    $q->whereRaw('CAST(RentalRoomId2 AS CHAR) = ?', [(string) $reqRoomId])
                      ->orWhere(function ($fallback) use ($reqRoomId) {
                          $fallback->where(function ($owner) {
                              $owner->whereNull('RegisterID2')->orWhere('RegisterID2', '');
                          })->where(function ($room) {
                              $room->whereNull('RentalRoomId2')->orWhere('RentalRoomId2', '')->orWhere('RentalRoomId2', '0');
                          })->whereRaw('CAST(RentalRoomId1 AS CHAR) = ?', [(string) $reqRoomId]);
                      });
                });
            }
            if (!$isFolioA) {
                $serviceBillQuery->where(function ($q) use ($folioId) {
                    $q->where('Folio', (string) $folioId)
                      ->orWhere('Folio', (int) $folioId)
                      ->orWhereNull('Folio')
                      ->orWhere('Folio', '0')
                      ->orWhere('Folio', '');
                });
            }
            $serviceBillQuery->where(function ($q) {
                $q->whereNull('PaymentId')->orWhere('PaymentId', '')->orWhereNull('PaymentID')->orWhere('PaymentID', '');
            })
            ->where('Edit', 0);

            // Khi thu riêng một phòng, tiền phòng RM/RMS chưa thanh toán
            // vẫn thuộc Master nếu booking đang bật tập hợp tiền phòng.
            if ($reqRoomId && (bool) $booking->is_master_room_rate) {
                $serviceBillQuery->whereNotIn('ServiceId', ['RM', 'RMS']);
            }

            if ($reqGuestId) {
                $serviceBillQuery->where(function ($q) use ($reqGuestId) {
                    $q->whereRaw('CAST(CustomerId2 AS CHAR) = ?', [(string) $reqGuestId])
                      ->orWhere(function ($fallback) use ($reqGuestId) {
                          $fallback->where(function ($guest) {
                              $guest->whereNull('CustomerId2')->orWhere('CustomerId2', '');
                          })->whereRaw('CAST(CustomerId1 AS CHAR) = ?', [(string) $reqGuestId]);
                      });
                });
            }

            $updateServiceBillData = [
                'PaymentId' => $settlementCode,
                'InvoiceId' => $invoiceCode,
                'Status'    => 2,
            ];
            if ($isFolioA) {
                $updateServiceBillData['Folio'] = 3;
            }
            $serviceBillQuery->update($updateServiceBillData);

            // 3b. Cập nhật các bill Buồng phòng (HousekeepingServiceBill) liên quan thành Đã thanh toán (Status = 2)
            $housekeepingQuery = \App\Models\HousekeepingServiceBill::query();
            if ($reqRoomId) {
                $roomNos = \App\Models\BookingRoom::where('id', $reqRoomId)->pluck('room_number')->filter()->toArray();
                if (!empty($roomNos)) {
                    $housekeepingQuery->whereIn('RoomNo', $roomNos);
                } else {
                    $housekeepingQuery->where('BookingId', $bookingId);
                }
            } else {
                // Master settlement không thanh toán bill housekeeping thuộc từng phòng.
                $housekeepingQuery->whereRaw('1 = 0');
            }
            if ($reqGuestId) {
                $housekeepingQuery->where('GuestId', $reqGuestId);
            }
            $housekeepingQuery->where('BillEdit', 0)->update(['Status' => 2]);

            // 4. Cập nhật dịch vụ BookingRoomService thuộc Folio này thành Đã thanh toán và chuyển về Folio 3 nếu là Folio A
            if ($reqRoomId && !empty($targetRoomIds)) {
                $roomServiceQuery = \App\Models\BookingRoomService::whereIn('booking_room_id', $targetRoomIds);
                if ($selectedBillIds && \Illuminate\Support\Facades\Schema::hasColumn('booking_room_services', 'service_bill_id')) {
                    $roomServiceQuery->whereIn('service_bill_id', $selectedBillIds);
                }
                if ($reqRoomId && (bool) $booking->is_master_room_rate) {
                    $roomServiceQuery->whereNotIn('service_code', ['RM', 'RMS']);
                }
                if (!$isFolioA) {
                    $roomServiceQuery->where('folio', $folioId);
                }
                if ($reqGuestId) {
                    $roomServiceQuery->where('guest_id', $reqGuestId);
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('booking_room_services', 'payment_id')) {
                    $roomServiceQuery->where(function ($q) {
                        $q->whereNull('payment_id')->orWhere('payment_id', '');
                    });
                } elseif (\Illuminate\Support\Facades\Schema::hasColumn('booking_room_services', 'status')) {
                    $roomServiceQuery->where('status', '!=', 2);
                }

                $updateData = [];
                if (\Illuminate\Support\Facades\Schema::hasColumn('booking_room_services', 'payment_id')) {
                    $updateData['payment_id'] = $settlementCode;
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('booking_room_services', 'invoice_code')) {
                    $updateData['invoice_code'] = $invoiceCode;
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('booking_room_services', 'status')) {
                    $updateData['status'] = 2;
                }
                if ($isFolioA) {
                    $updateData['folio'] = 3;
                }
                if (!empty($updateData)) {
                    $roomServiceQuery->update($updateData);
                }
            }

            // Sync payment_value trên booking
            $totalDeposit = Payment::where('booking_id', $bookingId)
                ->where('edit_flag', 0)
                ->whereNull('deleted_at')
                ->sum('amount');
            Booking::where('id', $bookingId)->update(['payment_value' => $totalDeposit]);

            $this->completeBookingAfterMasterSettlement($booking);
        });

        return response()->json([
            'success' => true,
            'message' => 'Thanh toán Folio ' . $folioId . ' thành công!',
        ]);
    }

    /** Complete a fully settled Master after all rooms have checked out. */
    private function completeBookingAfterMasterSettlement(Booking $booking): void
    {
        $hasActiveRooms = $booking->bookingRooms()
            ->whereNotIn('status', [BookingRoom::STATUS_CANCELLED, BookingRoom::STATUS_CHECKED_OUT])
            ->exists();
        if ($hasActiveRooms) return;

        $checkedOutRoomIds = $booking->bookingRooms()
            ->where('status', BookingRoom::STATUS_CHECKED_OUT)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $hasUnpaidMasterBills = \App\Models\ServiceBill::query()
            ->where('Edit', 0)
            ->where(function ($q) { $q->whereNull('PaymentId')->orWhere('PaymentId', ''); })
            ->where('Status', '!=', 2)
            ->where(function ($q) use ($booking) {
                $q->whereRaw('CAST(RegisterID2 AS CHAR) = ?', [(string) $booking->id])
                    ->orWhereRaw('CAST(RegisterId1 AS CHAR) = ?', [(string) $booking->id]);
            })
            ->where(function ($q) use ($booking, $checkedOutRoomIds) {
                $q->whereNull('RentalRoomId2')->orWhere('RentalRoomId2', '')->orWhere('RentalRoomId2', '0');
                if ($checkedOutRoomIds) $q->orWhereIn(DB::raw('CAST(RentalRoomId2 AS CHAR)'), $checkedOutRoomIds);
                if ((bool) $booking->is_master_room_rate) $q->orWhereIn('ServiceId', ['RM', 'RMS']);
            })
            ->exists();
        if ($hasUnpaidMasterBills) return;

        $hasUnusedDeposit = Payment::where('booking_id', $booking->id)
            ->where('edit_flag', 0)
            ->whereNull('payment_id')
            ->where(function ($q) { $q->where('pack2', Payment::PACK2_DEPOSIT)->orWhere('pack4', 'AP'); })
            ->exists();
        if ($hasUnusedDeposit) return;

        $booking->update(['status' => Booking::STATUS_CHECKOUT]);
    }
}
