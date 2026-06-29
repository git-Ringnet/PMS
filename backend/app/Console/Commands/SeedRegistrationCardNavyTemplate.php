<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Template;

class SeedRegistrationCardNavyTemplate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'template:seed-registration-card-navy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed the Registration Card template with Navy Hotel Nha Trang style';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to seed Navy Hotel Registration Card template...');

        // Header section html
        $headerHtml = 
            '<table style="width: 100%; border: none; border-collapse: collapse; margin-bottom: 5px;">' .
            '  <tr>' .
            '    <td style="width: 50%; border: none; padding: 0; vertical-align: middle;">' .
            '      <img src="/uploads/templates/navy_logo.png" style="max-height: 55px; display: block;" alt="Navy Logo">' .
            '    </td>' .
            '    <td style="width: 50%; border: none; padding: 0; text-align: right; font-size: 11px; color: #1e3a8a; vertical-align: middle; line-height: 1.5; font-family: sans-serif;">' .
            '      <div style="display: inline-block; text-align: left;">' .
            '        <span style="font-weight: bold;">📞 0258.3599.088</span><br>' .
            '        <span style="font-weight: bold;">🌐 www.navynhatrang.vn</span>' .
            '      </div>' .
            '    </td>' .
            '  </tr>' .
            '</table>' .
            '<div style="text-align: center; margin-top: 15px; margin-bottom: 15px; font-family: sans-serif;">' .
            '  <div style="font-size: 18px; font-weight: bold; color: #000; letter-spacing: 0.5px; line-height: 1.2;">PHIẾU ĐĂNG KÝ LƯU TRÚ</div>' .
            '  <div style="font-size: 16px; font-weight: bold; color: #000; letter-spacing: 0.5px; line-height: 1.2; margin-top: 2px;">GUEST REGISTRATION CARD</div>' .
            '</div>';

        // Information fields html — flex layout: label + underline on same row
        $fieldRow = function($label, $value, $extraStyle = '') {
            return '<div style="display: flex; align-items: flex-end; margin-bottom: 8px;">' .
                '<span style="font-weight: bold; white-space: nowrap; padding-bottom: 2px; font-size: 11px;">' . $label . '</span>' .
                '<div style="flex-grow: 1; border-bottom: 1px solid #000; margin-left: 8px; padding-bottom: 2px; min-height: 1.2em; font-size: 11px;' . $extraStyle . '">' . $value . '</div>' .
                '</div>';
        };

        $fieldsHtml =
            '<div style="font-family: sans-serif; margin-bottom: 15px;">' .
            // Row 1: Booking Code + Company
            '<div style="display: flex; gap: 20px;">' .
            '<div style="width: 50%;">' . $fieldRow('Số Đặt Phòng/ Booking Code:', '{{registration.confirmation_no}}') . '</div>' .
            '<div style="width: 50%;">' . $fieldRow('Công ty/ TA/Com:', '{{registration.company}}') . '</div>' .
            '</div>' .
            // Row 2: Guest Name (full width)
            $fieldRow('Tên Khách / Guest Name :', '{{registration.guest_name}}', ' text-transform: uppercase;') .
            // Row 3: Contact No. + Email
            '<div style="display: flex; gap: 20px;">' .
            '<div style="width: 50%;">' . $fieldRow('Số điện thoại/ Contact No.:', '{{registration.phone}}') . '</div>' .
            '<div style="width: 50%;">' . $fieldRow('Email Address:', '{{registration.email}}') . '</div>' .
            '</div>' .
            // Row 4: Special Request (full width)
            $fieldRow('Yêu Cầu đặc biệt/ Special Request:', '{{registration.special_requests}}') .
            '</div>';

        // Table structural html
        $tableHtml =
            '<table style="width: 100%; border-collapse: collapse; border: 1px solid #000; font-size: 11px; font-family: sans-serif; text-align: center; margin-bottom: 15px;">' .
            '  <thead>' .
            '    <tr style="background-color: #f8fafc; font-weight: bold; height: 35px;">' .
            '      <th rowspan="2" style="border: 1px solid #000; padding: 6px; width: 22%;">Loại Phòng<br><span style="font-weight: normal; font-style: italic; font-size: 9.5px;">Room Type</span></th>' .
            '      <th rowspan="2" style="border: 1px solid #000; padding: 6px; width: 12%;">Số Phòng<br><span style="font-weight: normal; font-style: italic; font-size: 9.5px;">Room No.</span></th>' .
            '      <th rowspan="2" style="border: 1px solid #000; padding: 6px; width: 14%;">Ngày Đến<br><span style="font-weight: normal; font-style: italic; font-size: 9.5px;">Arrival</span></th>' .
            '      <th rowspan="2" style="border: 1px solid #000; padding: 6px; width: 14%;">Ngày Đi<br><span style="font-weight: normal; font-style: italic; font-size: 9.5px;">Departure</span></th>' .
            '      <th colspan="2" style="border: 1px solid #000; padding: 6px; width: 20%;">Số Khách<br><span style="font-weight: normal; font-style: italic; font-size: 9.5px;">No. of Guest</span></th>' .
            '      <th rowspan="2" style="border: 1px solid #000; padding: 6px; width: 18%;">Ăn sáng<br><span style="font-weight: normal; font-style: italic; font-size: 9.5px;">Breakfast</span></th>' .
            '    </tr>' .
            '    <tr style="background-color: #f8fafc; font-weight: bold; height: 25px;">' .
            '      <th style="border: 1px solid #000; padding: 4px;">Adult</th>' .
            '      <th style="border: 1px solid #000; padding: 4px;">Child</th>' .
            '    </tr>' .
            '  </thead>' .
            '  <tbody>' .
            '    <tr style="height: 35px;">' .
            '      <td style="border: 1px solid #000; padding: 6px; text-align: left;">{{registration.room_type}}</td>' .
            '      <td style="border: 1px solid #000; padding: 6px;">{{registration.room_no}}</td>' .
            '      <td style="border: 1px solid #000; padding: 6px;">{{registration.arrival_date}}</td>' .
            '      <td style="border: 1px solid #000; padding: 6px;">{{registration.departure_date}}</td>' .
            '      <td style="border: 1px solid #000; padding: 6px;">{{booking.adults}}</td>' .
            '      <td style="border: 1px solid #000; padding: 6px;">{{booking.children}}</td>' .
            '      <td style="border: 1px solid #000; padding: 6px;"></td>' .
            '    </tr>' .
            '    <tr>' .
            '      <td rowspan="2" style="border: 1px solid #000; padding: 6px; font-weight: bold; text-align: left; vertical-align: middle;">' .
            '        Hình thức thanh toán/<br><span style="font-weight: normal; font-style: italic; font-size: 9.5px;">Payment Method</span>' .
            '      </td>' .
            '      <td colspan="3" style="border: 1px solid #000; padding: 8px 6px; text-align: left; vertical-align: middle;">' .
            '        <span style="font-size: 12px; vertical-align: middle;">☐</span> Cash' .
            '      </td>' .
            '      <td colspan="3" style="border: 1px solid #000; padding: 8px 6px; text-align: left; vertical-align: middle;">' .
            '        <span style="font-size: 12px; vertical-align: middle;">☐</span> Credit Card' .
            '      </td>' .
            '    </tr>' .
            '    <tr>' .
            '      <td colspan="3" style="border: 1px solid #000; padding: 8px 6px; text-align: left; vertical-align: middle;">' .
            '        <span style="font-size: 12px; vertical-align: middle;">☐</span> Bank Transfer' .
            '      </td>' .
            '      <td colspan="3" style="border: 1px solid #000; padding: 8px 6px; text-align: left; vertical-align: middle;">' .
            '        <span style="font-size: 12px; vertical-align: middle;">☐</span> Khác/ Others:' .
            '      </td>' .
            '    </tr>' .
            '    <tr>' .
            '      <td style="border: 1px solid #000; padding: 8px 6px; font-weight: bold; text-align: left; vertical-align: middle;">' .
            '        Đặt cọc/<br><span style="font-weight: normal; font-style: italic; font-size: 9.5px;">Deposited</span>' .
            '      </td>' .
            '      <td colspan="6" style="border: 1px solid #000; padding: 8px 6px; text-align: left; vertical-align: middle;"></td>' .
            '    </tr>' .
            '    <tr>' .
            '      <td style="border: 1px solid #000; padding: 8px 6px; font-weight: bold; text-align: left; vertical-align: middle;">' .
            '        Số lượng CCCD/<br><span style="font-weight: normal; font-style: italic; font-size: 9.5px;">No.of ID/Passport</span>' .
            '      </td>' .
            '      <td colspan="6" style="border: 1px solid #000; padding: 8px 6px; text-align: left; vertical-align: middle;"></td>' .
            '    </tr>' .
            '    <tr style="height: 65px;">' .
            '      <td style="border: 1px solid #000; padding: 6px; font-weight: bold; text-align: left; vertical-align: top; width: 22%;">' .
            '        Trả CCCD/ Passport/<br><span style="font-weight: normal; font-style: italic; font-size: 9.5px;">Returned ID/ Passport</span>' .
            '      </td>' .
            '      <td colspan="2" style="border: 1px solid #000; padding: 6px; vertical-align: top;"></td>' .
            '      <td style="border: 1px solid #000; padding: 6px; font-weight: bold; text-align: center; vertical-align: top; width: 14%;">' .
            '        Chữ ký khách hàng<br><span style="font-weight: normal; font-style: italic; font-size: 9.5px;">Guest\'s signature</span>' .
            '      </td>' .
            '      <td style="border: 1px solid #000; padding: 6px; vertical-align: top;"></td>' .
            '      <td style="border: 1px solid #000; padding: 6px; font-weight: bold; text-align: center; vertical-align: top; width: 10%;">' .
            '        Nhân viên<br><span style="font-weight: normal; font-style: italic; font-size: 9.5px;">Staff</span>' .
            '      </td>' .
            '      <td style="border: 1px solid #000; padding: 6px; vertical-align: top;"></td>' .
            '    </tr>' .
            '  </tbody>' .
            '</table>';

        // Disclaimers section html
        $disclaimerHtml =
            '<div style="font-size: 9.5px; color: #000; font-family: sans-serif; line-height: 1.35; text-align: justify; margin-bottom: 8px;">' .
            '  Khách sạn Navy Nha Trang không chịu trách nhiệm về tài sản có giá trị và tiền bạc trong khu vực công cộng và trong phòng ở. Quý khách vui lòng tự bảo quản tài sản có giá trị và tiền bạc trong két sắt an toàn đã được trang bị trong phòng. Cá nhân, tổ chức ký xác xác nhận trên Mẫu Đăng Ký này đồng ý tuân theo mọi quy định của Khách sạn và tự chịu trách nhiệm nếu không tuân thủ các quy định nói trên. Khách sạn có quyền từ chối làm thủ tục nhận khách không tuân thủ các quy định.' .
            '</div>' .
            '<div style="font-size: 9.5px; color: #000; font-family: sans-serif; line-height: 1.35; text-align: justify; margin-bottom: 8px; font-style: italic;">' .
            '  The signing person, company or association of this Registration Card agrees that Navy Nha Trang Hotel will not be held responsible for any valuables or money left in public areas of the hotel, or in guest rooms. Guests are advised to utilize the in-room electronic safe, located in each guest room for the safe-keeping of such items. The signing person, company or association agrees to abide by the Hotel rules and regulations, and understands that failure to abide by said regulations will result in the voiding of this contract.' .
            '</div>' .
            '<div style="font-size: 9.5px; color: #000; font-family: sans-serif; line-height: 1.35; text-align: justify; margin-bottom: 12px; font-style: italic;">' .
            '  Лицо, компания или организация, подписавшие настоящую Регистрационную карточку, соглашаются с тем, что отель Navy Nha Trang не несёт ответственности за любые ценные вещи или денежные средства, оставленные в общественных зонах отеля либо в номерах гостей. Гостям рекомендуется использовать электронный сейф, установленный в каждом номере, для безопасного хранения таких предметов. Подписавшее лицо, компания или организация также обязуются соблюдать правила и положения отеля и понимают, что несоблюдение указанных правил может привести к аннулированию настоящего договора.' .
            '</div>';

        // Signatures block html
        $signaturesHtml =
            '<table style="width: 100%; border: none; border-collapse: collapse; margin-top: 15px; margin-bottom: 10px; font-size: 11px; font-family: sans-serif; font-style: italic;">' .
            '  <tr>' .
            '    <td style="width: 33.3%; text-align: center; border: none; padding: 0;">Staff\'s signature</td>' .
            '    <td style="width: 33.3%; text-align: center; border: none; padding: 0;">Supervisor\'s signature</td>' .
            '    <td style="width: 33.3%; text-align: center; border: none; padding: 0;">Guest\'s signature</td>' .
            '  </tr>' .
            '  <tr>' .
            '    <td style="height: 55px; border: none;"></td>' .
            '    <td style="height: 55px; border: none;"></td>' .
            '    <td style="height: 55px; border: none;"></td>' .
            '  </tr>' .
            '</table>';

        // Footer decorative html
        $footerHtml =
            '<div style="text-align: center; margin-top: 5px;">' .
            '  <img src="/uploads/templates/footer_register.png" style="width: 100%; max-height: 45px; object-fit: contain;" alt="Footer decoration">' .
            '</div>';

        // Define the content blocks JSON
        $contentJson = [
            'header' => [
                [
                    'id' => 'h_navy_header',
                    'type' => 'text',
                    'content' => $headerHtml,
                    'style' => []
                ]
            ],
            'detail' => [
                [
                    'id' => 'd_navy_fields',
                    'type' => 'text',
                    'content' => $fieldsHtml,
                    'style' => []
                ],
                [
                    'id' => 'd_navy_table',
                    'type' => 'text',
                    'content' => $tableHtml,
                    'style' => []
                ],
                [
                    'id' => 'd_navy_disclaimer',
                    'type' => 'text',
                    'content' => $disclaimerHtml,
                    'style' => []
                ],
                [
                    'id' => 'd_navy_signatures',
                    'type' => 'text',
                    'content' => $signaturesHtml,
                    'style' => []
                ]
            ],
            'footer' => [
                [
                    'id' => 'f_navy_decorative',
                    'type' => 'text',
                    'content' => $footerHtml,
                    'style' => []
                ]
            ]
        ];

        // Find or create template record
        $template = Template::where('group', 'Registration Card')
            ->where('name', 'Registration Card Navy')
            ->first();

        if (!$template) {
            $template = new Template();
            $template->group = 'Registration Card';
            $template->name = 'Registration Card Navy';
            $template->report = 'RegistrationCardNavy';
        }

        // Compile HTML representation
        $html = '';
        
        $html .= '<div class="report-header-band">' . PHP_EOL;
        foreach ($contentJson['header'] as $block) {
            $html .= $this->compileBlockToHtml($block);
        }
        $html .= '</div>' . PHP_EOL;

        $html .= '<div class="report-detail-band">' . PHP_EOL;
        foreach ($contentJson['detail'] as $block) {
            $html .= $this->compileBlockToHtml($block);
        }
        $html .= '</div>' . PHP_EOL;

        $html .= '<div class="report-footer-band">' . PHP_EOL;
        foreach ($contentJson['footer'] as $block) {
            $html .= $this->compileBlockToHtml($block);
        }
        $html .= '</div>' . PHP_EOL;

        // Strip formatting whitespace and newlines from internal content blocks to avoid pre-wrap stretching
        $html = str_replace(["\r", "\n", "  "], "", $html);

        // Update settings fields
        $template->page_size = 'A4';
        $template->page_orientation = 'portrait';
        $template->margin_top = 10;
        $template->margin_bottom = 10;
        $template->margin_left = 15;
        $template->margin_right = 15;
        $template->content_json = $contentJson;
        $template->content_html = $html;
        $template->css = '';
        $template->is_default = false; // Make sure it is not default initially to prevent overriding Galliot unless user makes it so
        $template->version = '1.0';
        
        $template->save();

        $this->info('Navy Hotel Registration Card template seeded successfully!');
    }

    /**
     * Compile block to html
     */
    private function compileBlockToHtml(array $b): string
    {
        $styles = [];
        foreach ($b['style'] ?? [] as $k => $v) {
            $kHyphen = strtolower(preg_replace('/([A-Z])/', '-$1', $k));
            $styles[] = "{$kHyphen}: {$v}";
        }
        $styleStr = implode('; ', $styles);
        
        $blockHtml = "<div id=\"{$b['id']}\" style=\"{$styleStr}\">" . PHP_EOL;
        $blockHtml .= "  " . ($b['content'] ?? '') . PHP_EOL;
        $blockHtml .= '</div>' . PHP_EOL;
        return $blockHtml;
    }
}
