<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PrintTemplateSlot;
use App\Models\Template;
use App\Services\Reports\ReportDataExecutorService;
use App\Services\TemplateRendererService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PrintTemplateSlotController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => PrintTemplateSlot::with('template.reportDataSource')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, PrintTemplateSlot $printTemplateSlot)
    {
        $validated = $request->validate([
            'template_id' => 'nullable|integer|exists:templates,id',
        ]);

        if (! empty($validated['template_id'])) {
            $template = Template::findOrFail($validated['template_id']);
            if ($template->group !== $printTemplateSlot->group) {
                throw ValidationException::withMessages([
                    'template_id' => 'Thiết kế được chọn không thuộc đúng nhóm mẫu in.',
                ]);
            }
        }

        $printTemplateSlot->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật thiết kế sử dụng cho mẫu in.',
            'data' => $printTemplateSlot->fresh()->load('template.reportDataSource'),
        ]);
    }

    /** In phiếu thu (biên nhận thu tiền) từ các dòng thanh toán thật của Hóa đơn, theo thiết kế gán cho vị trí PAYMENT_RECEIPT. */
    public function paymentReceipt(Request $request, TemplateRendererService $renderer)
    {
        $validated = $request->validate([
            'payment_ids' => 'required|array|min:1',
            'payment_ids.*' => 'integer',
        ]);
        $slot = PrintTemplateSlot::where('code', 'PAYMENT_RECEIPT')->firstOrFail();
        // Chưa gán thiết kế cho vị trí thì dùng mẫu mặc định của nhóm Receipt.
        $template = $slot->template()->first()
            ?: Template::where('group', $slot->group)->where('is_default', true)->first();
        if (! $template) {
            throw ValidationException::withMessages([
                'template_id' => 'Vị trí mẫu in Phiếu thu chưa được chọn thiết kế (Cấu hình > Mẫu in).',
            ]);
        }

        $payments = Payment::with(['booking', 'bookingRoom', 'guest', 'paymentMethod'])
            ->whereIn('id', $validated['payment_ids'])
            ->where('edit_flag', 0)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();
        if ($payments->isEmpty() || $payments->pluck('booking_id')->unique()->count() > 1) {
            throw ValidationException::withMessages([
                'payment_ids' => 'Chỉ in phiếu thu cho các dòng thanh toán còn hiệu lực của cùng một đăng ký.',
            ]);
        }

        $first = $payments->first();
        $booking = $first->booking;
        $total = (float) $payments->sum('amount');
        $mock = $renderer->getMockData($template->group, $template->name);
        $data = [
            'hotel' => $mock['hotel'] ?? [],
            'date' => Carbon::parse($first->date ?: $first->created_at)->format('d/m/Y'),
            'guest_name' => $first->guest?->full_name ?: ($first->guest_display ?: ($booking?->booking_name ?? '')),
            'phone' => $first->guest?->phone ?: ($booking?->contact_phone ?? ''),
            'amount' => number_format($total, 0, '.', ',') . ' đ',
            'amount_in_words' => SalesInvoiceController::numberToVietnameseWords($total),
            'content' => $payments->pluck('description')->filter()->unique()->implode('; '),
            'payment_method' => $payments->map(fn (Payment $payment) => $payment->paymentMethod?->name ?: $payment->payment_method_id)->filter()->unique()->implode(', '),
            // Folio phòng ảo không có booking: hiển thị tên phòng ảo thay cho mã booking.
            'booking_code' => $booking?->booking_code ?: ($booking ? 'GAL' . $booking->id : 'PHÒNG ẢO ' . ($first->bookingRoom?->room_number ?? '')),
            'room_no' => $payments->map(fn (Payment $payment) => $payment->bookingRoom?->room_number)->filter()->unique()->implode(', '),
            'phone_no' => $mock['hotel']['phone'] ?? '',
        ];

        return response()->json([
            'success' => true,
            'template' => $template->only(['id', 'name', 'version']),
            'html' => $renderer->render($template->content_html ?? '', $template->css ?? '', $data, [
                'page_size' => $template->page_size,
                'page_orientation' => $template->page_orientation,
                'margin_top' => $template->margin_top,
                'margin_bottom' => $template->margin_bottom,
                'margin_left' => $template->margin_left,
                'margin_right' => $template->margin_right,
            ]),
        ]);
    }

    public function render(
        Request $request,
        PrintTemplateSlot $printTemplateSlot,
        TemplateRendererService $renderer,
        ReportDataExecutorService $executor
    ) {
        $validated = $request->validate([
            'data' => 'nullable|array',
            'parameters' => 'nullable|array',
        ]);
        $template = $printTemplateSlot->template()->with('reportDataSource')->first();

        if (! $template) {
            throw ValidationException::withMessages([
                'template_id' => 'Vị trí mẫu in này chưa được chọn thiết kế.',
            ]);
        }

        $data = $validated['data'] ?? null;
        if ($data === null && $template->reportDataSource) {
            $data = $executor->executeSource(
                $template->reportDataSource,
                $validated['parameters'] ?? $template->parameter_defaults ?? []
            );
        }

        if ($data === null) {
            $data = $renderer->getMockData($template->group, $template->name);
        }

        return response()->json([
            'success' => true,
            'slot' => $printTemplateSlot->only(['id', 'code', 'group', 'name']),
            'template' => $template->only(['id', 'name', 'version']),
            'html' => $renderer->render(
                $template->content_html ?? '',
                $template->css ?? '',
                $data,
                [
                    'page_size' => $template->page_size,
                    'page_orientation' => $template->page_orientation,
                    'margin_top' => $template->margin_top,
                    'margin_bottom' => $template->margin_bottom,
                    'margin_left' => $template->margin_left,
                    'margin_right' => $template->margin_right,
                ]
            ),
        ]);
    }
}
