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

    // =========================================
    // GET: Danh sách cọc của booking
    // =========================================
    public function index($bookingId)
    {
        $booking = Booking::findOrFail($bookingId);

        $payments = Payment::where('booking_id', $bookingId)
            ->with('paymentMethod')
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $totalDeposit = $payments
            ->where('pack2', Payment::PACK2_DEPOSIT)
            ->where('edit_flag', 0)
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

        $request->validate([
            'date'              => 'required|date',
            'amount'            => 'required|numeric|min:0.01',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'description'       => 'nullable|string|max:255',
            'debit_account'     => 'nullable|string|max:100',
            'booking_room_id'   => 'nullable|exists:booking_rooms,id',
        ]);

        // Kiểm tra payment_method — không cho Công nợ (group=4) và Miễn phí (group=5) làm cọc
        $method = PaymentMethod::findOrFail($request->payment_method_id);
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

        $payment = DB::transaction(function () use ($request, $bookingId, $booking, $description, $guestDisplay) {
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
                'payment_method_id' => $request->payment_method_id,
                'debit_account'     => $request->debit_account,
                'status'            => Payment::STATUS_PENDING,
                'edit_flag'         => 0,
                'created_by'        => Auth::user()?->username ?? 'system',
            ]);

            // Cập nhật payment_value trên booking header = tổng cọc
            $totalDeposit = Payment::where('booking_id', $bookingId)
                ->where('pack2', Payment::PACK2_DEPOSIT)
                ->where('edit_flag', 0)
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
    // PUT: Cập nhật cọc
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
            'date'              => 'sometimes|date',
            'amount'            => 'sometimes|numeric|min:0.01',
            'payment_method_id' => 'sometimes|exists:payment_methods,id',
            'description'       => 'nullable|string|max:255',
            'debit_account'     => 'nullable|string|max:100',
        ]);

        DB::transaction(function () use ($request, $payment) {
            $payment->update(array_merge(
                $request->only(['date', 'amount', 'payment_method_id', 'description', 'debit_account']),
                ['updated_by' => Auth::user()?->username ?? 'system']
            ));

            // Sync payment_value trên booking header
            $totalDeposit = Payment::where('booking_id', $payment->booking_id)
                ->where('pack2', Payment::PACK2_DEPOSIT)
                ->where('edit_flag', 0)
                ->sum('amount');

            Booking::where('id', $payment->booking_id)->update(['payment_value' => $totalDeposit]);
        });

        return response()->json([
            'success' => true,
            'data'    => $payment->fresh()->load('paymentMethod'),
            'message' => 'Cập nhật cọc thành công!',
        ]);
    }

    // =========================================
    // DELETE: Xóa cọc — tạo dòng âm đối trừ (reversal)
    // DELETE /payments/{id}
    // =========================================
    public function destroy($id)
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

        DB::transaction(function () use ($payment) {
            // Tạo dòng âm đối trừ
            $reversal = Payment::create([
                'booking_id'        => $payment->booking_id,
                'booking_room_id'   => $payment->booking_room_id,
                'company_id'        => $payment->company_id,
                'date'              => now()->toDateString(),
                'open_time'         => now()->format('H:i:s'),
                'guest_display'     => $payment->guest_display,
                'description'       => '[REVERSAL] ' . $payment->description,
                'amount'            => -abs($payment->amount), // Số âm
                'pack2'             => Payment::PACK2_DEPOSIT,
                'payment_method_id' => $payment->payment_method_id,
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

            // Sync payment_value
            $totalDeposit = Payment::where('booking_id', $payment->booking_id)
                ->where('pack2', Payment::PACK2_DEPOSIT)
                ->where('edit_flag', 0)
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
    public function split(Request $request, $id)
    {
        $payment = Payment::findOrFail($id);

        if ($payment->edit_flag !== 0 || $payment->status !== Payment::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ có thể tách cọc đang chờ thanh toán.',
            ], 422);
        }

        $request->validate([
            'amounts'   => 'required|array|min:2',
            'amounts.*' => 'required|numeric|min:0.01',
        ]);

        $sumNew = array_sum($request->amounts);
        if (abs($sumNew - $payment->amount) > 0.01) {
            return response()->json([
                'success' => false,
                'message' => 'Tổng số tiền sau khi tách (' . number_format($sumNew, 2) . ') phải bằng số tiền gốc (' . number_format($payment->amount, 2) . ').',
            ], 422);
        }

        DB::transaction(function () use ($request, $payment) {
            $originalAmount = $payment->amount;

            // Đánh dấu dòng gốc đã tách
            $payment->update([
                'edit_flag'                  => 1,
                'total_amount_before_split'  => $originalAmount,
                'updated_by'                 => Auth::user()?->username ?? 'system',
            ]);

            // Tạo các dòng tách
            foreach ($request->amounts as $amt) {
                Payment::create([
                    'booking_id'                 => $payment->booking_id,
                    'booking_room_id'            => $payment->booking_room_id,
                    'company_id'                 => $payment->company_id,
                    'date'                       => $payment->date,
                    'open_time'                  => now()->format('H:i:s'),
                    'guest_display'              => $payment->guest_display,
                    'description'                => $payment->description . ' [Tách]',
                    'amount'                     => $amt,
                    'total_amount_before_split'  => $originalAmount,
                    'pack2'                      => Payment::PACK2_DEPOSIT,
                    'payment_method_id'          => $payment->payment_method_id,
                    'reversal_ref'               => $payment->id,
                    'status'                     => Payment::STATUS_PENDING,
                    'edit_flag'                  => 0,
                    'created_by'                 => Auth::user()?->username ?? 'system',
                ]);
            }
        });

        $newPayments = Payment::where('reversal_ref', $payment->id)->get();

        return response()->json([
            'success' => true,
            'data'    => $newPayments,
            'message' => 'Tách cọc thành công! Đã tạo ' . count($request->amounts) . ' dòng cọc mới.',
        ], 201);
    }

    // =========================================
    // POST: Chuyển cọc sang booking khác
    // POST /payments/{id}/transfer
    // Body: target_booking_id (required)
    // =========================================
    public function transfer(Request $request, $id)
    {
        $payment = Payment::findOrFail($id);

        if ($payment->edit_flag !== 0 || $payment->status !== Payment::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ có thể chuyển cọc đang chờ thanh toán.',
            ], 422);
        }

        $request->validate([
            'target_booking_id' => 'required|exists:bookings,id',
        ]);

        if ($request->target_booking_id == $payment->booking_id) {
            return response()->json([
                'success' => false,
                'message' => 'Booking đích phải khác booking nguồn.',
            ], 422);
        }

        $targetBooking = Booking::findOrFail($request->target_booking_id);

        if (in_array($targetBooking->status, [Booking::STATUS_CHECKOUT, Booking::STATUS_DELETED])) {
            return response()->json([
                'success' => false,
                'message' => 'Booking đích đã checkout hoặc đã hủy, không thể chuyển cọc.',
            ], 422);
        }

        DB::transaction(function () use ($request, $payment, $targetBooking) {
            $sourceBookingId = $payment->booking_id;

            // Tạo dòng âm trên booking nguồn
            $reversal = Payment::create([
                'booking_id'        => $sourceBookingId,
                'date'              => now()->toDateString(),
                'open_time'         => now()->format('H:i:s'),
                'guest_display'     => $payment->guest_display,
                'description'       => '[TRANSFER OUT → ' . $targetBooking->booking_code . '] ' . $payment->description,
                'amount'            => -abs($payment->amount),
                'pack2'             => Payment::PACK2_DEPOSIT,
                'payment_method_id' => $payment->payment_method_id,
                'reversal_ref'      => $payment->id,
                'status'            => Payment::STATUS_DELETED,
                'edit_flag'         => 1,
                'created_by'        => Auth::user()?->username ?? 'system',
            ]);

            // Đánh dấu dòng gốc đã chuyển
            $payment->update([
                'edit_flag'    => 1,
                'reversal_ref' => $reversal->id,
                'updated_by'   => Auth::user()?->username ?? 'system',
            ]);

            // Tạo dòng mới trên booking đích
            $newPayment = Payment::create([
                'booking_id'        => $request->target_booking_id,
                'company_id'        => $targetBooking->company_id,
                'date'              => $payment->date,
                'open_time'         => now()->format('H:i:s'),
                'guest_display'     => $targetBooking->booking_code . ' - ' . $targetBooking->booking_name,
                'description'       => '[TRANSFER IN ← ' . Booking::find($sourceBookingId)?->booking_code . '] ' . $payment->description,
                'amount'            => abs($payment->amount),
                'pack2'             => Payment::PACK2_DEPOSIT,
                'payment_method_id' => $payment->payment_method_id,
                'reversal_ref'      => $payment->id,
                'status'            => Payment::STATUS_PENDING,
                'edit_flag'         => 0,
                'created_by'        => Auth::user()?->username ?? 'system',
            ]);

            // Sync payment_value cả 2 booking
            foreach ([$sourceBookingId, $request->target_booking_id] as $bkId) {
                $total = Payment::where('booking_id', $bkId)
                    ->where('pack2', Payment::PACK2_DEPOSIT)
                    ->where('edit_flag', 0)
                    ->sum('amount');
                Booking::where('id', $bkId)->update(['payment_value' => max(0, $total)]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Chuyển cọc sang booking ' . $targetBooking->booking_code . ' thành công!',
        ]);
    }
}
