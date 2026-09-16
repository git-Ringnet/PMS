<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotelSetting;
use App\Models\SalesInvoice;
use App\Models\SystemDateRoll;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesInvoiceController extends Controller
{
    /**
     * Danh sách hóa đơn bán hàng với bộ lọc và tổng hợp doanh thu.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SalesInvoice::query()
            ->with([
                'booking:id,booking_name,arrival_date,departure_date',
                'company:id,name',
            ]);

        // Lọc theo khoảng ngày (mặc định ưu tiên payment_date hoặc invoice_date)
        if ($request->filled('from_date')) {
            $fromDate = Carbon::parse($request->input('from_date'))->startOfDay();
            $query->where(function ($q) use ($fromDate) {
                $q->where('payment_date', '>=', $fromDate)
                  ->orWhere('invoice_date', '>=', $fromDate);
            });
        }

        if ($request->filled('to_date')) {
            $toDate = Carbon::parse($request->input('to_date'))->endOfDay();
            $query->where(function ($q) use ($toDate) {
                $q->where('payment_date', '<=', $toDate)
                  ->orWhere('invoice_date', '<=', $toDate);
            });
        }

        // Lọc theo số phòng
        if ($request->filled('room')) {
            $room = trim((string) $request->input('room'));
            $query->where(function ($q) use ($room) {
                $q->where('room', 'like', "%{$room}%")
                  ->orWhere('room', 'like', "%R:{$room}%");
            });
        }

        // Lọc theo số hóa đơn (Bill ID)
        if ($request->filled('bill_id')) {
            $billId = trim((string) $request->input('bill_id'));
            $query->where('bill_id', 'like', "%{$billId}%");
        }

        // Lọc theo mã đặt phòng / Booking ID
        if ($request->filled('booking_id')) {
            $query->where('booking_id', (int) $request->input('booking_id'));
        }

        // Lọc theo mã thanh toán (payment_code)
        if ($request->filled('payment_code')) {
            $code = trim((string) $request->input('payment_code'));
            $query->where('payment_code', 'like', "%{$code}%");
        }

        // Lọc theo tên khách
        if ($request->filled('guest_name')) {
            $guestName = trim((string) $request->input('guest_name'));
            $query->where('guest_name', 'like', "%{$guestName}%");
        }

        // Lọc theo bộ phận
        if ($request->filled('department')) {
            $query->where('department', strtoupper(trim((string) $request->input('department'))));
        }

        // Lọc theo ca làm việc
        if ($request->filled('ca')) {
            $query->where('ca', (string) $request->input('ca'));
        }

        // Lọc theo trạng thái (1: Hiệu lực, 0: Đã hủy)
        if ($request->has('status') && $request->input('status') !== '' && $request->input('status') !== 'all') {
            $query->where('status', (int) $request->input('status'));
        }

        // Tính toán thống kê tổng hợp trước khi phân trang
        $summaryQuery = clone $query;
        $summary = [
            'total_invoices'        => (int) $summaryQuery->count(),
            'total_amount'          => (float) $summaryQuery->sum('amount'),
            'total_original_rate'   => (float) $summaryQuery->sum('original_rate'),
            'total_service_charge'  => (float) $summaryQuery->sum('service_charge_amount'),
            'total_special_tax'     => (float) $summaryQuery->sum('special_tax'),
            'total_tax'             => (float) $summaryQuery->sum('tax'),
            'total_discount'        => (float) $summaryQuery->sum('discount'),
        ];

        // Sắp xếp mặc định: mới nhất lên đầu
        $query->orderByDesc('id');

        $perPage = max(1, min(200, (int) $request->input('per_page', 20)));
        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $paginator->items(),
            'summary' => $summary,
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /**
     * Thống kê nhanh doanh thu hóa đơn bán hàng theo ngày hệ thống.
     */
    public function stats(Request $request): JsonResponse
    {
        $sysDate = SystemDateRoll::latest('id')->value('system_date') ?: now()->toDateString();
        $date = $request->input('date', $sysDate);

        $dayInvoices = SalesInvoice::whereDate('invoice_date', $date)
            ->where('status', 1)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'date'                  => $date,
                'total_count'           => $dayInvoices->count(),
                'total_amount'          => (float) $dayInvoices->sum('amount'),
                'total_original_rate'   => (float) $dayInvoices->sum('original_rate'),
                'total_service_charge'  => (float) $dayInvoices->sum('service_charge_amount'),
                'total_tax'             => (float) $dayInvoices->sum('tax'),
            ],
        ]);
    }

    /**
     * Chi tiết một hóa đơn bán hàng kèm dịch vụ và thanh toán.
     */
    public function show(string|int $id): JsonResponse
    {
        $invoice = SalesInvoice::query()
            ->with([
                'booking',
                'company',
                'payments' => function ($q) {
                    $q->with(['paymentMethod', 'bankAccount', 'user:id,name,username'])->orderBy('id');
                },
                'serviceBills' => function ($q) {
                    $q->with(['hotelService'])->orderBy('Date')->orderBy('OpenTime');
                },
            ])
            ->where('id', $id)
            ->orWhere('bill_id', $id)
            ->first();

        if (! $invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy hóa đơn bán hàng.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $invoice,
        ]);
    }

    /**
     * Danh sách hóa đơn bán hàng của một booking.
     */
    public function byBooking(string|int $bookingId): JsonResponse
    {
        $invoices = SalesInvoice::query()
            ->with([
                'payments.paymentMethod',
                'serviceBills',
            ])
            ->where('booking_id', $bookingId)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $invoices,
        ]);
    }

    /**
     * Dữ liệu mẫu in hóa đơn bán hàng / biên lai thanh toán.
     */
    public function printData(string|int $id): JsonResponse
    {
        $invoice = SalesInvoice::query()
            ->with([
                'booking',
                'company',
                'payments.paymentMethod',
                'serviceBills',
            ])
            ->where('id', $id)
            ->orWhere('bill_id', $id)
            ->first();

        if (! $invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy hóa đơn cần in.',
            ], 404);
        }

        $hotelSetting = HotelSetting::first();

        // Danh sách dòng dịch vụ
        $items = $invoice->serviceBills->map(function ($bill, $index) {
            return [
                'stt'            => $index + 1,
                'service_code'   => $bill->ServiceId,
                'description'    => $bill->DescriptionServive ?: $bill->Guest,
                'quantity'       => (float) ($bill->Quantity ?: 1),
                'amount'         => (float) $bill->Amount,
                'service_charge' => (float) ($bill->ServiceCharge ?? 0),
                'tax'            => (float) ($bill->Tax ?? 0),
                'date'           => $bill->Date ? Carbon::parse($bill->Date)->format('d/m/Y') : '',
                'time'           => $bill->OpenTime,
            ];
        });

        // Danh sách khoản thanh toán
        $payments = $invoice->payments->map(function ($p) {
            return [
                'method_name' => $p->paymentMethod?->name ?? $p->payment_method_id,
                'amount'      => (float) $p->amount,
                'is_deposit'  => $p->pack2 === 'DPR',
                'description' => $p->description,
            ];
        });

        $amountInWords = self::numberToVietnameseWords((float) $invoice->amount);

        return response()->json([
            'success' => true,
            'data'    => [
                'hotel' => [
                    'name'     => $hotelSetting?->hotel_name ?? 'Khách Sạn',
                    'address'  => $hotelSetting?->address ?? '',
                    'phone'    => $hotelSetting?->phone ?? '',
                    'tax_code' => $hotelSetting?->tax_code ?? '',
                    'logo_url' => $hotelSetting?->logo_url ?? '',
                ],
                'invoice' => [
                    'id'                    => $invoice->id,
                    'bill_id'               => $invoice->bill_id,
                    'invoice_date'          => $invoice->invoice_date?->format('d/m/Y') ?: '',
                    'payment_date'          => $invoice->payment_date?->format('d/m/Y') ?: '',
                    'open_time'             => $invoice->open_time,
                    'room'                  => $invoice->room,
                    'guest_name'            => $invoice->guest_name,
                    'username'              => $invoice->username,
                    'ca'                    => $invoice->ca,
                    'department'            => $invoice->department,
                    'payment_code'          => $invoice->payment_code,
                    'status'                => $invoice->status,
                    'original_rate'         => (float) $invoice->original_rate,
                    'service_charge_amount' => (float) $invoice->service_charge_amount,
                    'special_tax'           => (float) $invoice->special_tax,
                    'tax'                   => (float) $invoice->tax,
                    'discount'              => (float) $invoice->discount,
                    'amount'                => (float) $invoice->amount,
                    'amount_in_words'       => $amountInWords,
                    'currency'              => $invoice->currency ?: 'VND',
                ],
                'booking' => [
                    'booking_id'     => $invoice->booking_id,
                    'booking_code'   => $invoice->booking?->booking_code ?? '',
                    'arrival_date'   => $invoice->booking?->arrival_date ? Carbon::parse($invoice->booking->arrival_date)->format('d/m/Y') : '',
                    'departure_date' => $invoice->booking?->departure_date ? Carbon::parse($invoice->booking->departure_date)->format('d/m/Y') : '',
                    'company_name'   => $invoice->company?->name ?? '',
                ],
                'items'    => $items,
                'payments' => $payments,
            ],
        ]);
    }

    /**
     * Chuyển đổi số tiền thành chữ tiếng Việt chuẩn.
     */
    public static function numberToVietnameseWords(float|int $number): string
    {
        $number = round($number);
        if ($number <= 0) {
            return 'Không đồng';
        }

        $units = ['', 'nghìn', 'triệu', 'tỷ', 'nghìn tỷ', 'triệu tỷ'];
        $digits = ['không', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];

        $str = (string) $number;
        $len = strlen($str);
        $groups = [];

        while ($len > 0) {
            $take = min(3, $len);
            $groups[] = substr($str, max(0, $len - 3), $take);
            $len -= 3;
        }

        $words = [];
        foreach ($groups as $idx => $group) {
            $gNum = (int) $group;
            if ($gNum === 0) {
                continue;
            }

            $gStr = str_pad($group, 3, '0', STR_PAD_LEFT);
            $h = (int) $gStr[0];
            $t = (int) $gStr[1];
            $u = (int) $gStr[2];

            $gWords = [];
            // Trăm
            $gWords[] = $digits[$h] . ' trăm';

            // Chục
            if ($t === 0 && $u !== 0) {
                $gWords[] = 'lẻ';
            } elseif ($t === 1) {
                $gWords[] = 'mười';
            } elseif ($t > 1) {
                $gWords[] = $digits[$t] . ' mươi';
            }

            // Đơn vị
            if ($u === 1) {
                $gWords[] = ($t > 1) ? 'mốt' : 'một';
            } elseif ($u === 5 && $t > 0) {
                $gWords[] = 'lăm';
            } elseif ($u > 0) {
                $gWords[] = $digits[$u];
            }

            $part = implode(' ', $gWords);
            if (! empty($units[$idx])) {
                $part .= ' ' . $units[$idx];
            }
            array_unshift($words, $part);
        }

        $result = trim(implode(' ', $words));
        // Xóa "không trăm lẻ" nếu chỉ có nhóm hàng đơn vị
        if (str_starts_with($result, 'không trăm lẻ ')) {
            $result = substr($result, strlen('không trăm lẻ '));
        } elseif (str_starts_with($result, 'không trăm ')) {
            $result = substr($result, strlen('không trăm '));
        }

        return mb_strtoupper(mb_substr($result, 0, 1)) . mb_substr($result, 1) . ' đồng chẵn';
    }
}
