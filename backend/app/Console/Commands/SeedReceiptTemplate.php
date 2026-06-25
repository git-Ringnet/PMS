<?php

namespace App\Console\Commands;

use App\Models\Template;
use Illuminate\Console\Command;

class SeedReceiptTemplate extends Command
{
    protected $signature = 'seed:receipt-template';

    protected $description = 'Seed Navy Hotel Receipt Template (Phiếu Thu)';

    private const CONTACT_BROWN = '#8B5A4A';

    public function handle()
    {
        $this->info('Seeding Navy Hotel Receipt Template...');

        $phoneIcon = $this->iconDataUri('navy_icon_phone.png');
        $webIcon = $this->iconDataUri('navy_icon_web.png');

        if ($phoneIcon === '' || $webIcon === '') {
            $this->warn('Contact icons missing in public/uploads/templates — copying from assets if available.');
            $this->ensureContactIcons();
            $phoneIcon = $this->iconDataUri('navy_icon_phone.png');
            $webIcon = $this->iconDataUri('navy_icon_web.png');
        }

        $labelFont = 'Times New Roman, Georgia, serif';
        $valueGap = '<span style="display:inline-block;width:32px;line-height:1;font-size:11px;">&nbsp;</span>';
        $fieldValueStyle = 'flex:1 1 auto;border-bottom:1px dotted #444;padding-bottom:2px;font-size:11px;font-family:' . $labelFont . ';color:#000;min-width:0;';

        $inlineField = function (string $label, string $value) use ($labelFont, $valueGap, $fieldValueStyle): string {
            return '<div style="display:flex;align-items:flex-end;width:100%;margin-bottom:10px;">' .
                '<span style="white-space:nowrap;padding-bottom:2px;font-size:11px;font-family:' . $labelFont . ';color:#1a1a1a;">' . $label . '</span>' .
                $valueGap .
                '<span style="' . $fieldValueStyle . '">' . $value . '</span>' .
                '</div>';
        };

        $fieldLine = function (string $label, string $value) use ($inlineField): string {
            return $inlineField($label, $value);
        };

        $pairedRow = function (
            string $leftLabel,
            string $leftValue,
            string $rightLabel,
            string $rightValue
        ) use ($inlineField): string {
            return '<table style="width:100%;border:none;border-collapse:collapse;margin-bottom:0;"><tr>' .
                '<td style="width:50%;border:none;padding:0 14px 0 0;vertical-align:bottom;">' . $inlineField($leftLabel, $leftValue) . '</td>' .
                '<td style="width:50%;border:none;padding:0 0 0 2px;vertical-align:bottom;">' . $inlineField($rightLabel, $rightValue) . '</td>' .
                '</tr></table>';
        };

        $contactLine = function (string $iconDataUri, string $text): string {
            return '<table class="receipt-contact-row" style="border: none; border-collapse: collapse; margin-bottom: 4px;"><tr>' .
                '<td style="border: none; padding: 0 7px 0 0; vertical-align: middle; width: 18px;">' .
                '<img src="' . $iconDataUri . '" class="receipt-contact-icon" alt="">' .
                '</td>' .
                '<td style="border: none; padding: 0; vertical-align: middle;">' .
                '<span style="font-size:11px;font-family:Arial,sans-serif;font-weight:bold;color:' . self::CONTACT_BROWN . ';">' . $text . '</span>' .
                '</td></tr></table>';
        };

        $headerHtml =
            '<table style="width: 100%; border: none; border-collapse: collapse; margin-bottom: 2px;">' .
            '  <tr>' .
            '    <td style="width: 55%; border: none; padding: 0; vertical-align: middle;">' .
            '      <img src="/uploads/templates/navy_logo.png" style="max-height: 62px; display: block;" alt="Navy Logo">' .
            '    </td>' .
            '    <td style="width: 45%; border: none; padding: 0; text-align: right; vertical-align: middle;">' .
            '      <div style="display: inline-block; text-align: left;">' .
            $contactLine($phoneIcon, '0988246336') .
            $contactLine($webIcon, 'www.navynhatrang.vn') .
            '      </div>' .
            '    </td>' .
            '  </tr>' .
            '</table>' .
            '<div style="text-align: center; margin: 20px 0 22px 0;">' .
            '  <div style="font-size: 20px; font-weight: bold; color: #000; font-family: Arial, sans-serif; letter-spacing: 0.5px; line-height: 1.25;">PHIẾU THU</div>' .
            '  <div style="font-size: 18px; font-weight: bold; color: #000; font-family: Arial, sans-serif; letter-spacing: 0.5px; line-height: 1.25; margin-top: 2px;">RECEIPT</div>' .
            '</div>';

        $fieldsHtml =
            '<div class="receipt-form">' .
            $pairedRow('Ngày/ Date:', '{{date}}', 'Mã đặt phòng/ Booking code:', '{{booking_code}}') .
            $pairedRow('Tên Khách/ Guest Name:', '{{guest_name}}', 'Số phòng/ Room No.:', '{{room_no}}') .
            $pairedRow('Số điện thoại/ Contact No.:', '{{phone}}', 'Phone no.:', '{{phone_no}}') .
            $fieldLine('Tổng tiền/ Amount:', '{{amount}}') .
            $fieldLine('Bằng chữ/ In word:', '{{amount_in_words}}') .
            $fieldLine('Nội dung/ Content:', '{{content}}') .
            $fieldLine('Hình thức thanh toán/ Payment:', '{{payment_method}}') .
            '</div>';

        $signaturesHtml =
            '<table style="width: 100%; border: none; border-collapse: collapse; margin-top: 48px;">' .
            '  <tr>' .
            '    <td style="width: 50%; text-align: center; border: none; padding: 0 10px;">' .
            '      <div style="font-weight: bold; font-style: italic; font-size: 11px; color: #000; font-family: ' . $labelFont . '; margin-bottom: 58px;">Chữ ký nhân viên</div>' .
            '      <div style="font-style: italic; font-size: 10px; color: #555; font-family: ' . $labelFont . ';">Staff\'s signature</div>' .
            '    </td>' .
            '    <td style="width: 50%; text-align: center; border: none; padding: 0 10px;">' .
            '      <div style="font-weight: bold; font-style: italic; font-size: 11px; color: #000; font-family: ' . $labelFont . '; margin-bottom: 58px;">Chữ ký khách hàng</div>' .
            '      <div style="font-style: italic; font-size: 10px; color: #555; font-family: ' . $labelFont . ';">Guest\'s signature</div>' .
            '    </td>' .
            '  </tr>' .
            '</table>';

        $footerHtml =
            '<div style="text-align: center; margin-top: 22px;">' .
            '  <img src="/uploads/templates/footer_register.png" style="width: 100%; max-height: 40px; object-fit: contain;" alt="Footer decoration">' .
            '</div>';

        $css =
            '.receipt-form table { margin-top: 0 !important; margin-bottom: 0 !important; }' .
            '.receipt-form td { padding: 0 !important; border: none !important; background: transparent !important; border-bottom: none !important; }' .
            '.receipt-contact-row td { padding: 0 !important; border: none !important; background: transparent !important; }' .
            '.receipt-contact-icon { width: 15px; height: 15px; display: block; }' .
            '.report-header-band, .report-detail-band, .report-footer-band { width: 100%; }';

        $contentJson = [
            'header' => [
                ['id' => 'h_receipt_header', 'type' => 'text', 'content' => $headerHtml, 'style' => []],
            ],
            'detail' => [
                ['id' => 'd_receipt_fields', 'type' => 'text', 'content' => $fieldsHtml, 'style' => []],
                ['id' => 'd_receipt_signatures', 'type' => 'text', 'content' => $signaturesHtml, 'style' => []],
            ],
            'footer' => [
                ['id' => 'f_receipt_decorative', 'type' => 'text', 'content' => $footerHtml, 'style' => []],
            ],
        ];

        $html = '';
        foreach (['header', 'detail', 'footer'] as $band) {
            $html .= '<div class="report-' . $band . '-band">' . PHP_EOL;
            foreach ($contentJson[$band] as $block) {
                $html .= $this->compileBlockToHtml($block);
            }
            $html .= '</div>' . PHP_EOL;
        }
        $html = str_replace(["\r", "\n", '  '], '', $html);

        $template = Template::where('group', 'Receipt')
            ->where(function ($query) {
                $query->where('report', 'ReceiptNavy')
                    ->orWhere('name', 'like', 'Navy Hotel Receipt%');
            })
            ->first();

        if (!$template) {
            $template = new Template();
            $template->group = 'Receipt';
            $template->name = 'Navy Hotel Receipt';
            $template->report = 'ReceiptNavy';
        }

        $template->page_size = 'A5';
        $template->page_orientation = 'portrait';
        $template->margin_top = 15;
        $template->margin_bottom = 15;
        $template->margin_left = 20;
        $template->margin_right = 20;
        $template->content_json = $contentJson;
        $template->content_html = $html;
        $template->css = $css;
        $template->is_default = true;
        $template->version = (string) ((int) ($template->version ?: '1') + 1);
        $template->save();

        $this->info('Navy Hotel Receipt template seeded successfully!');
        $this->info("Template ID: {$template->id}");
        $this->info('Icons embedded: ' . ($phoneIcon !== '' && $webIcon !== '' ? 'yes' : 'no'));

        return Command::SUCCESS;
    }

    private function iconDataUri(string $filename): string
    {
        $path = public_path('uploads/templates/' . $filename);
        if (!is_file($path)) {
            return '';
        }

        return 'data:image/png;base64,' . base64_encode(file_get_contents($path));
    }

    private function ensureContactIcons(): void
    {
        $targetDir = public_path('uploads/templates');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $assetDir = base_path('../.cursor/projects/c-xampp-htdocs-PMS/assets');
        $sources = [
            'navy_icon_phone.png' => 'c__Users_thaih_AppData_Roaming_Cursor_User_workspaceStorage_empty-window_images_image-190c7e6c-fe7e-4455-8506-e4a57fe52f2c.png',
            'navy_icon_web.png' => 'c__Users_thaih_AppData_Roaming_Cursor_User_workspaceStorage_empty-window_images_image-05743ab2-aae0-4a50-aae0-32a6e670cf0a.png',
        ];

        foreach ($sources as $dest => $src) {
            $srcPath = $assetDir . DIRECTORY_SEPARATOR . $src;
            $destPath = $targetDir . DIRECTORY_SEPARATOR . $dest;
            if (is_file($srcPath) && !is_file($destPath)) {
                copy($srcPath, $destPath);
            }
        }
    }

    private function compileBlockToHtml(array $block): string
    {
        $styles = [];
        foreach ($block['style'] ?? [] as $key => $value) {
            $keyHyphen = strtolower(preg_replace('/([A-Z])/', '-$1', $key));
            $styles[] = "{$keyHyphen}: {$value}";
        }
        $styleStr = implode('; ', $styles);

        return "<div id=\"{$block['id']}\" style=\"{$styleStr}\">" . PHP_EOL .
            '  ' . ($block['content'] ?? '') . PHP_EOL .
            '</div>' . PHP_EOL;
    }
}
