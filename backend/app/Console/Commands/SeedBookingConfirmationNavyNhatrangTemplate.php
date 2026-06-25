<?php

namespace App\Console\Commands;

use App\Models\Template;
use Illuminate\Console\Command;

class SeedBookingConfirmationNavyNhatrangTemplate extends Command
{
    protected $signature = 'seed:booking-confirmation-navy-nhatrang';

    protected $description = 'Seed Navy Hotel Nha Trang Booking Confirmation template';

    private const NAVY = '#1b3764';
    private const HEADER_BG = '#d9e1f2';

    public function handle()
    {
        $this->info('Seeding Navy Hotel Nha Trang Booking Confirmation...');

        $logo = $this->resolveLogo();
        $css = $this->buildCss();

        $contentJson = [
            'header' => [
                ['id' => 'bcnt_header', 'type' => 'text', 'content' => $this->buildHeader($logo), 'style' => []],
            ],
            'detail' => [
                ['id' => 'bcnt_contact', 'type' => 'text', 'content' => $this->buildContactBlock(), 'style' => []],
                ['id' => 'bcnt_greeting', 'type' => 'text', 'content' => $this->buildGreeting(), 'style' => []],
                ['id' => 'bcnt_booking', 'type' => 'text', 'content' => $this->buildBookingTable(), 'style' => []],
                ['id' => 'bcnt_options', 'type' => 'text', 'content' => $this->buildOptionsBlock(), 'style' => []],
                ['id' => 'bcnt_note', 'type' => 'text', 'content' => $this->buildNoteBlock(), 'style' => []],
                ['id' => 'bcnt_signatures', 'type' => 'text', 'content' => $this->buildSignatures(), 'style' => []],
            ],
            'footer' => [],
        ];

        $html = '<div class="bcnt-document">' . $this->compileBands($contentJson) . '</div>';

        $template = Template::where('group', 'Booking Confirmation')
            ->where(function ($q) {
                $q->where('report', 'BookingConfirmationNavyNhatrang')
                    ->orWhere('name', 'Booking Confirmation Navy Nha Trang');
            })
            ->first();

        if (!$template) {
            $template = new Template();
            $template->group = 'Booking Confirmation';
            $template->name = 'Booking Confirmation Navy Nha Trang';
            $template->report = 'BookingConfirmationNavyNhatrang';
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
        return '<table class="bcnt-layout bcnt-header-top">' .
            '<tr>' .
            '<td class="bcnt-logo-cell">' . $logo . '</td>' .
            '<td class="bcnt-datetime-cell">{{confirmation.printed_at}}</td>' .
            '</tr></table>' .
            '<div class="bcnt-main-title">BOOKING CONFIRMATION</div>';
    }

    private function buildContactBlock(): string
    {
        $row = fn(string $l1, string $v1, string $l2, string $v2) =>
            '<tr><td class="bcnt-contact-label">' . $l1 . '</td><td class="bcnt-contact-value">' . $v1 . '</td>' .
            '<td class="bcnt-contact-label">' . $l2 . '</td><td class="bcnt-contact-value">' . $v2 . '</td></tr>';

        return '<table class="bcnt-contact-table">' .
            $row('<strong>From:</strong>', '{{confirmation.from}}', '<strong>To:</strong>', '{{customer.name}}') .
            $row('<strong>Tel:</strong>', '{{hotel.phone}}', '<strong>Company:</strong>', '{{confirmation.company}}') .
            $row('<strong>Fax:</strong>', '{{hotel.fax}}', '<strong>Phone:</strong>', '{{customer.phone}}') .
            $row('', '', '<strong>Fax:</strong>', '{{customer.fax}}') .
            $row('', '', '<strong>Email:</strong>', '{{customer.email}}') .
            $row('', '', '<strong>Confirmation number:</strong>', '{{booking.code}}') .
            '</table>';
    }

    private function buildGreeting(): string
    {
        return '<p class="bcnt-recipient"><strong>{{customer.name}} - {{confirmation.company}}</strong></p>' .
            '<p class="bcnt-intro">Further to your request, we are please to confirm your reservation as follows:</p>';
    }

    private function buildBookingTable(): string
    {
        return '<table class="bcnt-grid bcnt-booking-table">' .
            '<thead><tr>' .
            '<th>Check in</th><th>Check out</th><th>Service</th><th>Quantity</th>' .
            '<th>No. of guest</th><th>Price</th><th>Total</th>' .
            '</tr></thead><tbody>' .
            '<tr class="pms-detail-row" data-source="booking.lines">' .
            '<td class="bcnt-data-cell">{{item.checkin}}</td>' .
            '<td class="bcnt-data-cell">{{item.checkout}}</td>' .
            '<td class="bcnt-service-cell">{{item.service}}</td>' .
            '<td class="bcnt-data-cell">{{item.quantity}}</td>' .
            '<td class="bcnt-data-cell">{{item.guests}}</td>' .
            '<td class="bcnt-money-cell">{{item.unit_price}}</td>' .
            '<td class="bcnt-money-cell">{{item.amount}}</td>' .
            '</tr>' .
            '<tr>' .
            '<td colspan="6" class="bcnt-total-label">Total:</td>' .
            '<td class="bcnt-total-value">{{payment.total}}</td>' .
            '</tr></tbody></table>';
    }

    private function buildOptionsBlock(): string
    {
        $check = fn(string $yes, string $no) =>
            'Yes <span class="bcnt-check">' . $yes . '</span> &nbsp; No <span class="bcnt-check">' . $no . '</span>';

        return '<table class="bcnt-options-table">' .
            '<tr>' .
            '<td class="bcnt-option-cell">Buffet breakfast included: ' .
            $check('{{confirmation.breakfast_yes_box}}', '{{confirmation.breakfast_no_box}}') . '</td>' .
            '<td class="bcnt-option-cell">5% Service charge and 8% V.A.T include: ' .
            $check('{{confirmation.vat_yes_box}}', '{{confirmation.vat_no_box}}') . '</td>' .
            '</tr>' .
            '<tr>' .
            '<td class="bcnt-option-cell"><strong>Payment method:</strong> {{confirmation.payment_method}}</td>' .
            '<td class="bcnt-option-cell"><strong>Reservation guaranteed:</strong> {{confirmation.guarantee_status}}</td>' .
            '</tr></table>';
    }

    private function buildNoteBlock(): string
    {
        return '<table class="bcnt-note-box">' .
            '<tr><td class="bcnt-note-title"><strong>Note</strong></td></tr>' .
            '<tr><td class="bcnt-note-body">{{confirmation.notes}}</td></tr>' .
            '</table>';
    }

    private function buildSignatures(): string
    {
        $sig = fn(string $vi, string $en) =>
            '<td class="bcnt-sig-cell">' .
            '<div class="bcnt-sig-vi"><strong>' . $vi . '</strong></div>' .
            '<div class="bcnt-sig-en"><em>' . $en . '</em></div>' .
            '<div class="bcnt-sig-space">&nbsp;</div></td>';

        return '<table class="bcnt-sig-table">' .
            '<tr>' .
            $sig('Chữ ký lễ tân', "Receptionist's Signature") .
            $sig('Chữ kí FOM/GM', "Receptionist's Signature") .
            '<td class="bcnt-sig-cell"><div class="bcnt-sig-vi"><strong>Chữ ký của khách</strong></div><div class="bcnt-sig-en"><em>Guest\'s Signature</em></div><div class="bcnt-sig-space">&nbsp;</div></td>' .
            $sig('Chữ ký', 'Sale&amp;Reservation Department') .
            '</tr></table>';
    }

    private function buildCss(): string
    {
        return '.bcnt-document { font-family: Arial, Helvetica, sans-serif; font-size: 10px; color: #000; }' .
            '.bcnt-layout { width: 100%; border: none; border-collapse: collapse; }' .
            '.bcnt-layout td { border: none; padding: 0; vertical-align: top; }' .
            '.bcnt-header-top { margin-bottom: 6px; }' .
            '.bcnt-logo-cell { width: 40%; }' .
            '.bcnt-datetime-cell { width: 60%; text-align: right; font-size: 9px; padding-top: 4px !important; }' .
            '.bcnt-logo-navy { font-family: "Times New Roman", Times, serif; font-size: 32px; font-weight: bold; color: ' . self::NAVY . '; letter-spacing: 1px; line-height: 1; }' .
            '.bcnt-logo-hotel { font-family: "Times New Roman", Times, serif; font-size: 13px; color: #5a7a9a; letter-spacing: 3px; margin-top: 2px; }' .
            '.bcnt-logo-city { font-family: Arial, sans-serif; font-size: 10px; color: #333; margin-top: 2px; }' .
            '.bcnt-logo-img { max-height: 72px; display: block; }' .
            '.bcnt-main-title { text-align: center; font-size: 20px; font-weight: bold; letter-spacing: 1px; margin: 10px 0 14px; }' .
            '.bcnt-contact-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 10px; }' .
            '.bcnt-contact-table td { border: none !important; padding: 2px 4px 2px 0; vertical-align: top; }' .
            '.bcnt-contact-label { width: 12%; white-space: nowrap; }' .
            '.bcnt-contact-value { width: 38%; }' .
            '.bcnt-recipient { margin: 0 0 6px; font-size: 10px; }' .
            '.bcnt-intro { margin: 0 0 10px; font-size: 10px; }' .
            '.bcnt-grid { width: 100%; border-collapse: collapse; margin: 0 0 10px; font-size: 10px; }' .
            '.bcnt-grid th, .bcnt-grid td { border: 1px solid #000 !important; padding: 5px 6px; vertical-align: middle; }' .
            '.bcnt-grid th { background: ' . self::HEADER_BG . '; font-weight: bold; text-align: center; }' .
            '.bcnt-booking-table { margin-bottom: 8px !important; }' .
            '.bcnt-data-cell { text-align: center; }' .
            '.bcnt-service-cell { text-align: left; }' .
            '.bcnt-money-cell { text-align: right; }' .
            '.bcnt-total-label { text-align: right; font-weight: bold; background: #fff !important; padding-right: 10px !important; }' .
            '.bcnt-total-value { text-align: right; font-weight: bold; background: ' . self::HEADER_BG . ' !important; }' .
            '.bcnt-options-table { width: 100%; border-collapse: collapse; margin: 0 0 10px; font-size: 10px; }' .
            '.bcnt-options-table td { border: none !important; padding: 4px 8px 4px 0; vertical-align: top; width: 50%; }' .
            '.bcnt-check { display: inline-block; min-width: 12px; text-align: center; font-size: 11px; }' .
            '.bcnt-note-box { width: 100%; border-collapse: collapse; margin: 0 0 24px; }' .
            '.bcnt-note-box td { border: 1px solid #000 !important; padding: 6px 8px; vertical-align: top; }' .
            '.bcnt-note-title { font-size: 10px; padding-bottom: 4px !important; }' .
            '.bcnt-note-body { min-height: 48px; font-size: 10px; }' .
            '.bcnt-sig-table { width: 100%; border-collapse: collapse; margin-top: 8px; }' .
            '.bcnt-sig-table td { border: none !important; width: 25%; text-align: center; vertical-align: top; padding: 0 4px; font-size: 9px; }' .
            '.bcnt-sig-vi { font-size: 10px; margin-bottom: 2px; }' .
            '.bcnt-sig-en, .bcnt-sig-en-only { font-size: 9px; color: #333; }' .
            '.bcnt-sig-space { height: 48px; }';
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

    private function resolveLogo(): string
    {
        $path = public_path('uploads/templates/navy_nhatrang_logo.png');
        if (is_file($path)) {
            $uri = 'data:image/png;base64,' . base64_encode(file_get_contents($path));
            return '<img src="' . $uri . '" class="bcnt-logo-img" alt="Navy Hotel Nha Trang">';
        }

        return '<div class="bcnt-logo">' .
            '<div class="bcnt-logo-navy">NAVY</div>' .
            '<div class="bcnt-logo-hotel">HOTEL</div>' .
            '<div class="bcnt-logo-city">NHA TRANG</div>' .
            '</div>';
    }
}
