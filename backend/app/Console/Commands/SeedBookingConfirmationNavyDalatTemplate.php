<?php

namespace App\Console\Commands;

use App\Models\Template;
use Illuminate\Console\Command;

class SeedBookingConfirmationNavyDalatTemplate extends Command
{
    protected $signature = 'seed:booking-confirmation-navy-dalat';

    protected $description = 'Seed Navy Hotel Da Lat Booking Confirmation template';

    private const NAVY = '#1b3764';
    private const SECTION_BG = '#c6d1e8';
    private const TOTAL_BG = '#c6d1e8';

    public function handle()
    {
        $this->info('Seeding Navy Hotel Da Lat Booking Confirmation...');

        $logo = $this->imageDataUri('navy_dalat_logo.png', 'c__Users_thaih_AppData_Roaming_Cursor_User_workspaceStorage_b81753999fd2052a122d6b0867f6869a_images_image-3faa6aa7-508e-4729-9d2d-4eb84072f0e8.png');

        $headerHtml = $this->buildHeader($logo);
        $infoHtml = $this->buildInfoTable();
        $bookingDetailHtml = $this->buildBookingDetailBlock();
        $paymentHtml = $this->buildPaymentSections();
        $policyHtml = $this->buildPolicies();
        $closingHtml = $this->buildClosing();

        $css = $this->buildCss();

        $contentJson = [
            'header' => [
                ['id' => 'bc_header', 'type' => 'text', 'content' => $headerHtml, 'style' => []],
            ],
            'detail' => [
                ['id' => 'bc_info', 'type' => 'text', 'content' => $infoHtml, 'style' => []],
                ['id' => 'bc_booking_detail', 'type' => 'text', 'content' => $bookingDetailHtml, 'style' => []],
                ['id' => 'bc_payment', 'type' => 'text', 'content' => $paymentHtml, 'style' => []],
                ['id' => 'bc_policy', 'type' => 'text', 'content' => $policyHtml, 'style' => []],
                ['id' => 'bc_closing', 'type' => 'text', 'content' => $closingHtml, 'style' => []],
            ],
            'footer' => [],
        ];

        $html = '<div class="bc-document">' . $this->compileBands($contentJson) . '</div>';

        $template = Template::where('group', 'Booking Confirmation')
            ->where(function ($q) {
                $q->where('report', 'BookingConfirmationNavyDalat')
                    ->orWhere('name', 'Booking Confirmation Navy Da Lat');
            })
            ->first();

        if (!$template) {
            $template = new Template();
            $template->group = 'Booking Confirmation';
            $template->name = 'Booking Confirmation Navy Da Lat';
            $template->report = 'BookingConfirmationNavyDalat';
        }

        $template->page_size = 'A4';
        $template->page_orientation = 'portrait';
        $template->margin_top = 10;
        $template->margin_bottom = 10;
        $template->margin_left = 12;
        $template->margin_right = 12;
        $template->content_json = $contentJson;
        $template->content_html = $html;
        $template->css = $css;
        $template->is_default = false;
        $template->version = (string) ((int) ($template->version ?: '0') + 1);
        $template->save();

        $this->info('Done. Template ID: ' . $template->id);

        return Command::SUCCESS;
    }

    private function buildHeader(string $logo): string
    {
        return '<table class="bc-layout" style="width:100%;border:none;border-collapse:collapse;margin-bottom:8px;"><tr>' .
            '<td style="width:35%;border:none;padding:0;vertical-align:top;">' .
            '<img src="' . $logo . '" style="max-height:72px;display:block;" alt="Navy Hotel Da Lat">' .
            '</td>' .
            '<td style="border:none;padding:0;vertical-align:middle;text-align:center;">' .
            '<div style="font-size:22px;font-weight:bold;color:' . self::NAVY . ';font-family:Arial,sans-serif;letter-spacing:1px;">XÁC NHẬN ĐẶT PHÒNG</div>' .
            '</td></tr></table>';
    }

    private function buildInfoTable(): string
    {
        $r = fn(string $l1, string $v1, string $l2, string $v2) => '<tr>' .
            '<td class="bc-label">' . $l1 . '</td><td class="bc-value">' . $v1 . '</td>' .
            '<td class="bc-label">' . $l2 . '</td><td class="bc-value">' . $v2 . '</td></tr>';

        return '<table class="bc-grid">' .
            $r('Người liên hệ', '{{confirmation.contact_person}}', 'Xác nhận bởi:', '{{confirmation.confirmed_by}}') .
            $r('Số điện thoại', '{{confirmation.contact_phone}}', 'Số điện thoại', '{{hotel.phone}}') .
            $r('Email', '{{confirmation.contact_email}}', 'Email', '{{hotel.email}}') .
            $r('Tên công ty', '{{confirmation.company}}', 'Ngày xác nhận', '{{booking.date}}') .
            $r('Mã tour', '{{confirmation.tour_code}}', 'Mã đặt phòng', '{{booking.code}}') .
            '<tr><td class="bc-label">Tên khách</td><td class="bc-value" colspan="3">{{customer.name}}</td></tr>' .
            '</table>';
    }

    private function buildBookingDetailBlock(): string
    {
        $greeting =
            '<div class="bc-greeting">' .
            'Cảm ơn Quý khách đã tin tưởng lựa chọn khách sạn <strong>NAVY HOTEL ĐÀ LẠT</strong>,<br>' .
            'Chúng tôi rất vinh hạnh được xác nhận đặt phòng của Quý khách với thông tin như sau:' .
            '</div>';

        $sumRow = fn(string $label, string $value) =>
            '<tr>' .
            '<td colspan="7" class="bc-sum-spacer">&nbsp;</td>' .
            '<td class="bc-sum-label">' . $label . '</td>' .
            '<td class="bc-sum-value">' . $value . '</td>' .
            '</tr>';

        $table =
            '<table class="bc-grid bc-room-table">' .
            '<thead><tr>' .
            '<th>Nhận phòng</th><th>Trả phòng</th><th>Dịch vụ</th><th>Số phòng</th>' .
            '<th>Số lượng</th><th>Số đêm</th><th>Số lượng khách</th><th>Đơn giá</th><th>Thành tiền</th>' .
            '</tr></thead><tbody>' .
            '<tr class="pms-detail-row" data-source="booking.lines">' .
            '<td class="bc-data-cell">{{item.checkin}}</td><td class="bc-data-cell">{{item.checkout}}</td>' .
            '<td class="bc-data-cell">{{item.service}}</td><td class="bc-data-cell">{{item.room_number}}</td>' .
            '<td class="bc-data-cell">{{item.quantity}}</td><td class="bc-data-cell">{{item.nights}}</td>' .
            '<td class="bc-data-cell">{{item.guests}}</td><td class="bc-data-cell">{{item.unit_price}}</td>' .
            '<td class="bc-data-cell">{{item.amount}}</td></tr>' .
            $sumRow('Tổng cộng:', '{{payment.total}}') .
            $sumRow('Đặt cọc:', '{{payment.deposit}}') .
            $sumRow('Tổng tiền còn lại', '{{payment.balance}}') .
            '</tbody></table>';

        $notesAndPayment =
            '<p class="bc-note-line"><strong>Ghi chú :</strong> {{confirmation.notes}}</p>' .
            '<p class="bc-room-note">{{confirmation.room_note}}</p>';

        return $greeting . $table . $notesAndPayment;
    }

    private function buildPaymentSections(): string
    {
        $row = fn(string $label, string $value) =>
            '<tr><td class="bc-label" style="width:28%;">' . $label . '</td><td class="bc-value">' . $value . '</td></tr>';

        return '<table class="bc-grid bc-payment-block">' .
            '<tr><td colspan="2" class="bc-payment-main-title">Phương thức thanh toán:</td></tr>' .
            '<tr><td colspan="2" class="bc-subsection-title">Thông tin chuyển khoản</td></tr>' .
            $row('Tên tài khoản', '{{confirmation.bank_account_name}}') .
            $row('Số tài khoản', '{{confirmation.bank_account_number}}') .
            $row('Ngân hàng', '{{confirmation.bank_name}}') .
            $row('Nội dung chuyển khoản', '{{confirmation.transfer_content}}') .
            '<tr><td colspan="2" class="bc-subsection-title">Thông tin xuất hóa đơn</td></tr>' .
            $row('Tên công ty', '{{confirmation.invoice_company}}') .
            $row('Địa chỉ', '{{confirmation.invoice_address}}') .
            $row('Mã số thuế', '{{confirmation.invoice_tax_code}}') .
            $row('Email', '{{confirmation.invoice_email}}') .
            '</table>';
    }

    private function buildPolicies(): string
    {
        $section = fn(string $title, string $body) =>
            '<div class="bc-section-title">' . $title . '</div><div class="bc-section-body bc-text">' . $body . '</div>';

        return $section('Thời gian mùa cao điểm, mùa thấp điểm và ngày lễ tết',
            'Mùa cao điểm: Tháng 6, 7, 8, 9, 10, 11, 12, tháng 1, tháng 2, tháng 3, tháng 4<br>' .
            'Mùa thấp điểm: Tháng 5<br>' .
            'Giá phòng trên là giá Net (đã bao gồm thuế, phí, phụ phí). Phụ thu các ngày lễ tết theo quy định khách sạn.') .
            $section('1. Quy định về thời gian nhận/ trả phòng',
            'Thời gian nhận phòng tiêu chuẩn: 14:00. Thời gian trả phòng tiêu chuẩn: 12:00.<br>' .
            'Nếu Quý khách có nhu cầu nhận phòng sớm hoặc trả phòng muộn, vui lòng thông báo trước để khách sạn sắp xếp (tùy tình trạng phòng trống).<br>' .
            'Trả phòng muộn đến 18:00: phụ thu 50% giá phòng; sau 18:00: phụ thu 100% giá phòng.<br>' .
            'Nhận phòng sớm trước 06:00: phụ thu 100% giá phòng; từ 06:00–14:00: phụ thu 50% giá phòng.') .
            $section('2. Chính sách dành cho trẻ em',
            'Trẻ em dưới 6 tuổi: miễn phí (tối đa 2 trẻ/phòng, ngủ chung giường với bố mẹ).<br>' .
            'Trẻ em từ 6 - 11 tuổi: phụ thu 90,000 VNĐ/trẻ/đêm (bao gồm ăn sáng).<br>' .
            'Trẻ em từ 12 tuổi trở lên: tính như người lớn.') .
            $section('3. Chính sách hủy',
            '<strong>- Mùa thấp điểm:</strong><br>' .
            '&nbsp;&nbsp;Trước 07 ngày: phí phạt 0% &nbsp;|&nbsp; Từ 03 - 07 ngày: 50% tổng tiền phòng đã đặt<br>' .
            '&nbsp;&nbsp;Trong vòng 03 ngày hoặc không đến: 100% tổng tiền phòng đã đặt<br>' .
            '<strong>- Mùa cao điểm:</strong><br>' .
            '&nbsp;&nbsp;Trước 15 ngày: phí phạt 0% &nbsp;|&nbsp; Từ 05 - 15 ngày: 50% tổng tiền phòng đã đặt<br>' .
            '&nbsp;&nbsp;Trong vòng 05 ngày hoặc không đến: 100% tổng tiền phòng đã đặt<br>' .
            '<strong>- Các ngày lễ tết:</strong><br>' .
            '&nbsp;&nbsp;Trước 30 ngày: phí phạt 0% &nbsp;|&nbsp; Từ 10 - 30 ngày: 50% tổng tiền phòng đã đặt<br>' .
            '&nbsp;&nbsp;Trong vòng 10 ngày hoặc không đến: 100% tổng tiền phòng đã đặt');
    }

    private function buildClosing(): string
    {
        return '<div class="bc-section-title">Lời cảm ơn</div>' .
            '<div class="bc-section-body bc-text">' .
            'Chúng tôi hân hạnh được chào đón Quý khách đến với khách sạn Navy Đà Lạt.<br>' .
            'Nếu Quý khách cần thêm bất kỳ thông tin nào, xin vui lòng liên hệ với chúng tôi để được hỗ trợ.<br><br>' .
            'Trân trọng cảm ơn,<br>' .
            '<strong>NAVY HOTEL ĐÀ LẠT</strong><br>' .
            '{{hotel.address}}<br>' .
            'P. {{hotel.phone}} &nbsp;&nbsp; E. {{hotel.email}} &nbsp;&nbsp; W. {{hotel.website}}' .
            '</div>' .
            '<table class="bc-layout" style="width:100%;border:none;border-collapse:collapse;margin-top:36px;">' .
            '<tr><td style="width:50%;border:none;text-align:center;font-size:11px;font-weight:bold;">Chữ ký lễ tân</td>' .
            '<td style="width:50%;border:none;text-align:center;font-size:11px;font-weight:bold;">Chữ ký khách hàng</td></tr>' .
            '<tr><td style="height:50px;border:none;"></td><td style="height:50px;border:none;"></td></tr></table>';
    }

    private function buildCss(): string
    {
        return '.bc-document { border: 1px solid #000; padding: 6px 8px; box-sizing: border-box; }' .
            '.bc-layout table { margin: 0 !important; }' .
            '.bc-grid { width: 100%; border-collapse: collapse; margin: 8px 0; font-family: Arial, sans-serif; font-size: 10px; }' .
            '.bc-grid th, .bc-grid td { border: 1px solid #000 !important; padding: 5px 6px; vertical-align: middle; background-clip: padding-box; }' .
            '.bc-grid th { background: ' . self::SECTION_BG . '; font-weight: bold; text-align: center; }' .
            '.bc-label { background: #fff; font-weight: bold; width: 18%; white-space: nowrap; }' .
            '.bc-value { background: #fff; }' .
            '.bc-total { background: ' . self::TOTAL_BG . '; text-align: right; font-weight: bold; }' .
            '.bc-section-title { background: ' . self::SECTION_BG . '; border: 1px solid #000; border-bottom: none; padding: 6px 8px; font-weight: bold; font-size: 10px; font-family: Arial, sans-serif; margin-top: 8px; }' .
            '.bc-section-body { border: 1px solid #000; padding: 8px; font-family: Arial, sans-serif; font-size: 10px; line-height: 1.45; }' .
            '.bc-greeting { font-family: Arial, sans-serif; font-size: 10px; line-height: 1.55; color: #000; margin: 4px 0 8px; }' .
            '.bc-room-table { margin-top: 0 !important; margin-bottom: 0 !important; }' .
            '.bc-room-table th, .bc-room-table td.bc-data-cell { text-align: center; font-size: 10px; }' .
            '.bc-sum-spacer { background: #fff !important; border: 1px solid #000 !important; }' .
            '.bc-sum-label { background: #fff !important; font-weight: bold; text-align: right; white-space: nowrap; font-size: 10px; padding-right: 8px !important; }' .
            '.bc-sum-value { background: ' . self::TOTAL_BG . ' !important; text-align: center; font-size: 10px; min-width: 70px; }' .
            '.bc-note-line { font-family: Arial, sans-serif; font-size: 10px; margin: 10px 0 4px; color: #000; }' .
            '.bc-room-note { font-family: Arial, sans-serif; font-size: 10px; margin: 0 0 6px; color: #000; }' .
            '.bc-payment-block { margin-top: 0 !important; margin-bottom: 8px !important; }' .
            '.bc-payment-main-title { background: #fff !important; font-weight: bold; text-align: left; font-size: 10px; padding: 6px 8px !important; }' .
            '.bc-subsection-title { background: ' . self::SECTION_BG . ' !important; font-weight: bold; text-align: left; font-size: 10px; padding: 6px 8px !important; }' .
            '.bc-text { font-family: Arial, sans-serif; font-size: 10px; line-height: 1.5; color: #000; margin: 0 0 8px; }' .
            '.bc-room-table th, .bc-room-table td { font-size: 10px; padding: 5px 4px; }';
    }

    private function compileBands(array $contentJson): string
    {
        $html = '';
        foreach (['header', 'detail', 'footer'] as $band) {
            if (empty($contentJson[$band])) {
                continue;
            }
            $html .= '<div class="report-' . $band . '-band">';
            foreach ($contentJson[$band] as $block) {
                $html .= '<div id="' . $block['id'] . '">' . ($block['content'] ?? '') . '</div>';
            }
            $html .= '</div>';
        }
        return $html;
    }

    private function imageDataUri(string $filename, string $assetName): string
    {
        $path = public_path('uploads/templates/' . $filename);
        if (!is_file($path)) {
            $assetPath = base_path('../.cursor/projects/c-xampp-htdocs-PMS/assets/' . $assetName);
            if (is_file($assetPath)) {
                @copy($assetPath, $path);
            }
        }
        if (!is_file($path)) {
            return '/uploads/templates/' . $filename;
        }
        return 'data:image/png;base64,' . base64_encode(file_get_contents($path));
    }
}
