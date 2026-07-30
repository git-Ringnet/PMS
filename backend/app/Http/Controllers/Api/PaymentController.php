<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Payment;
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
        if ($user->username === 'admin' || !empty($user->is_admin)) return true;

        $settings = $user->setting?->settings ?? [];
        if (isset($settings['RuleUserCorrectOrPostBillPaymentOldDay'])) {
            $val = $settings['RuleUserCorrectOrPostBillPaymentOldDay'];
            return $val === true || $val === 1 || $val === '1' || $val === 'true';
        }
        return false;
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

        $request->validate([
            'date'              => 'required|date',
            'amount'            => 'required|numeric|min:0.01',
            'payment_method_id' => 'required',
            'description'       => 'nullable|string|max:255',
            'debit_account'     => 'nullable|string|max:100',
            'booking_room_id'   => 'nullable|exists:booking_rooms,id',
            'folio_id'          => 'nullable|integer|between:1,3',
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
                'message' => 'Hình thức "' . $method->name . '" không được dùng cho đặt cọc. Chỉ chấp nhận Tiền mặt, Thẻ/CK hoặc Voucher.',
            ], 422);
        }

        // Tạo mô tả mặc định nếu chưa có
        $description = $request->description
            ?? ('Đặt cọc - ' . $method->name . ' - ' . $booking->booking_code);

        // Tạo hiển thị guest: mã booking + tên
        $guestDisplay = $booking->booking_code . ' - ' . $booking->booking_name;

        $payment = DB::transaction(function () use ($request, $bookingId, $booking, $description, $guestDisplay, $imagePath, $pmCode, $departmentId) {
            $payment = Payment::create([
                'booking_id'        => $bookingId,
                'booking_room_id'   => $request->booking_room_id,
                'company_id'        => $booking->company_id,
                'date'              => $request->date,
                'open_time'         => now()->format('H:i:s'),
                'guest_display'     => $guestDisplay,
                'description'       => $description,
                'amount'            => $request->amount,
                'pack2'             => Payment::PACK2_DEPOSIT,
                // Cọc chung của booking/Master mặc định Folio 1; cọc phòng có thể chỉ định Folio sau này.
                'folio_id'          => $request->booking_room_id ? ($request->folio_id ?? 1) : 1,
                'payment_method_id' => $pmCode,
                'debit_account'     => $request->debit_account,
                'department_id'     => $departmentId,
                'status'            => Payment::STATUS_PENDING,
                'edit_flag'         => 0,
                'created_by'        => Auth::user()?->username ?? 'system',
                'image_path'        => $imagePath,
            ]);

            // Cập nhật payment_value trên booking header = tổng cọc
            $totalDeposit = Payment::where('booking_id', $bookingId)
                ->where('pack2', Payment::PACK2_DEPOSIT)
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
        $validated = $request->validate(['folio_id' => 'required|integer|between:1,3']);
        $payment = Payment::findOrFail($id);

        if ($payment->edit_flag !== 0 || $payment->status !== Payment::STATUS_PENDING || !empty($payment->payment_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ được chuyển Folio cho cọc chưa dùng để thanh toán.',
            ], 422);
        }

        $payment->update([
            'folio_id' => $validated['folio_id'],
            'updated_by' => Auth::user()?->username ?? 'system',
        ]);

        return response()->json([
            'success' => true,
            'data' => $payment->fresh(),
            'message' => 'Đã chuyển cọc sang Folio mới.',
        ]);
    }

    // =========================================
    // DELETE: Xóa cọc — tạo dòng âm đối trừ (reversal)
    // DELETE /payments/{id}
    // =========================================
    public function destroy(Request $request, $id)
    {
        $payment = Payment::findOrFail($id);

        if ($payment->edit_flag !== 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cọc này đã bị hủy hoặc đã được xử lý trước đó.',
            ], 422);
        }

        if ($payment->status === Payment::STATUS_PAID) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xóa cọc đã thanh toán. Vui lòng chuyển sang hoàn cọc.',
            ], 422);
        }

        $systemDate = $this->getSystemDate();
        $paymentDate = Carbon::parse($payment->date)->toDateString();
        if ($paymentDate < $systemDate && !$this->canOperateOldDay()) {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản không được phân quyền xóa đặt cọc cho ngày cũ (RuleUserCorrectOrPostBillPaymentOldDay). Chỉ được xóa cọc có ngày = ngày hệ thống.',
            ], 403);
        }

        $departmentId = $this->getDepartmentId($request);

        DB::transaction(function () use ($payment, $systemDate, $departmentId) {
            // Tạo dòng âm đối trừ với ngày hệ thống hiện tại
            $reversal = Payment::create([
                'booking_id'        => $payment->booking_id,
                'booking_room_id'   => $payment->booking_room_id,
                'company_id'        => $payment->company_id,
                'date'              => $systemDate,
                'open_time'         => now()->format('H:i:s'),
                'guest_display'     => $payment->guest_display,
                'description'       => '[REVERSAL] ' . $payment->description,
                'amount'            => -abs($payment->amount), // Số âm
                'pack2'             => Payment::PACK2_DEPOSIT,
                'payment_method_id' => $payment->payment_method_id,
                'department_id'     => $departmentId,
                'image_path'        => $payment->image_path,
                'reversal_ref'      => $payment->id,
                'status'            => Payment::STATUS_DELETED,
                'edit_flag'         => 1,
                'created_by'        => Auth::user()?->username ?? 'system',
            ]);

            // Đánh dấu dòng gốc đã hủy, lưu ref sang dòng âm
            $payment->update([
                'edit_flag'    => 1,
                'reversal_ref' => $reversal->id,
                'updated_by'   => Auth::user()?->username ?? 'system',
            ]);
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
            'message' => 'Đã xóa cọc thành công (tạo dòng đối trừ).',
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
        if (!$targetRoom && $request->target_guest_id) {
            abort(422, 'Chỉ được chọn khách khi nơi nhận là một phòng cụ thể.');
        }

        DB::transaction(function () use ($request, $targetBooking, $targetRoom) {
            $payments = Payment::whereIn('id', $request->payment_ids)->lockForUpdate()->get();
            if ($payments->count() !== count($request->payment_ids)) abort(422, 'Không tìm thấy đủ các dòng cọc cần chuyển.');

            foreach ($payments as $payment) {
                if ($payment->edit_flag !== 0 || $payment->status !== Payment::STATUS_PENDING || !empty($payment->payment_id)) {
                    abort(422, 'Chỉ có thể chuyển các dòng cọc chưa được thanh toán.');
                }
                $isSameLocation = (int) $payment->booking_id === (int) $targetBooking->id
                    && (string) ($payment->booking_room_id ?? '') === (string) ($targetRoom?->id ?? '');
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

        $isSameLocation = (int) $payment->booking_id === (int) $targetBooking->id
            && (string) ($payment->booking_room_id ?? '') === (string) ($targetRoom?->id ?? '');
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
}
