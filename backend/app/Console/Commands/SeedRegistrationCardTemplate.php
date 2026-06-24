<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Template;

class SeedRegistrationCardTemplate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'template:seed-registration-card';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed the Registration Card template with Galliot Hotel styled blocks';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to seed Registration Card template...');

        // Common cell styles for sub-tables to ensure perfect alignment and spacing
        $subTableLabelStyle = 'width: 35%; padding: 4px 6px 4px 0; font-size: 10px; line-height: 1.25; color: #000; font-weight: normal; vertical-align: top; border: none;';
        $subTableValueStyle = 'width: 65%; padding: 4px 6px; font-size: 11px; color: #000; font-weight: normal; vertical-align: middle; border: none;';
        $subLabelStyle = 'font-size: 8.5px; color: #475569;';

        $fullNameLabelStyle = 'width: 17.5%; padding: 4px 6px 4px 0; font-size: 10px; line-height: 1.25; color: #000; font-weight: normal; vertical-align: top; border: none;';
        $fullNameValueStyle = 'width: 82.5%; padding: 4px 6px; font-size: 11px; color: #000; font-weight: normal; vertical-align: middle; border: none;';

        // Outer borders for layout table (black lines, no outer left/right border)
        $tdLeft = 'width: 50%; border-right: 1px solid #000; border-bottom: 1px solid #000; padding: 0; vertical-align: top;';
        $tdRight = 'width: 50%; border-bottom: 1px solid #000; padding: 0; vertical-align: top;';
        $tdFullWidth = 'border-bottom: 1px solid #000; padding: 0; vertical-align: top;';

        // Build the main form table structure with no outer left/right borders
        $formTable = '<table style="width: 100%; border-collapse: collapse; border-top: 1px solid #000; border-bottom: 1px solid #000; border-left: none; border-right: none; margin: 0; padding: 0; table-layout: fixed;">' .
            '  <colgroup>' .
            '    <col style="width: 50%;">' .
            '    <col style="width: 50%;">' .
            '  </colgroup>' .

            // Row 1: Confirmation No & Company
            '  <tr>' .
            '    <td style="' . $tdLeft . '">' .
            '      <table style="width: 100%; border-collapse: collapse; border: none; margin: 0; padding: 0;">' .
            '        <tr>' .
            '          <td style="' . $subTableLabelStyle . '">Confirmation No:<br><span style="' . $subLabelStyle . '">Số đặt phòng:</span></td>' .
            '          <td style="' . $subTableValueStyle . '">{{registration.confirmation_no}}</td>' .
            '        </tr>' .
            '      </table>' .
            '    </td>' .
            '    <td style="' . $tdRight . '">' .
            '      <table style="width: 100%; border-collapse: collapse; border: none; margin: 0; padding: 0;">' .
            '        <tr>' .
            '          <td style="' . $subTableLabelStyle . '">Company:<br><span style="' . $subLabelStyle . '">Tên Công Ty:</span></td>' .
            '          <td style="' . $subTableValueStyle . '">{{registration.company}}</td>' .
            '        </tr>' .
            '      </table>' .
            '    </td>' .
            '  </tr>' .

            // Row 2: Full Name
            '  <tr>' .
            '    <td colspan="2" style="' . $tdFullWidth . '">' .
            '      <table style="width: 100%; border-collapse: collapse; border: none; margin: 0; padding: 0;">' .
            '        <tr>' .
            '          <td style="' . $fullNameLabelStyle . '">Full Name:<br><span style="' . $subLabelStyle . '">Họ Tên Khách:</span></td>' .
            '          <td style="' . $fullNameValueStyle . ' text-transform: uppercase;">{{registration.guest_name}}</td>' .
            '        </tr>' .
            '      </table>' .
            '    </td>' .
            '  </tr>' .

            // Row 3: ID/Passport No & Nationality
            '  <tr>' .
            '    <td style="' . $tdLeft . '">' .
            '      <table style="width: 100%; border-collapse: collapse; border: none; margin: 0; padding: 0;">' .
            '        <tr>' .
            '          <td style="' . $subTableLabelStyle . '">ID/Passport No.:<br><span style="' . $subLabelStyle . '">Số CCCD/Hộ Chiếu:</span></td>' .
            '          <td style="' . $subTableValueStyle . '">{{registration.id_passport}}</td>' .
            '        </tr>' .
            '      </table>' .
            '    </td>' .
            '    <td style="' . $tdRight . '">' .
            '      <table style="width: 100%; border-collapse: collapse; border: none; margin: 0; padding: 0;">' .
            '        <tr>' .
            '          <td style="' . $subTableLabelStyle . '">Nationality:<br><span style="' . $subLabelStyle . '">Quốc tịch:</span></td>' .
            '          <td style="' . $subTableValueStyle . '">{{registration.nationality}}</td>' .
            '        </tr>' .
            '      </table>' .
            '    </td>' .
            '  </tr>' .

            // Row 4: Email Address & Phone No.
            '  <tr>' .
            '    <td style="' . $tdLeft . '">' .
            '      <table style="width: 100%; border-collapse: collapse; border: none; margin: 0; padding: 0;">' .
            '        <tr>' .
            '          <td style="' . $subTableLabelStyle . '">Email Address:<br><span style="' . $subLabelStyle . '">Thư Điện Tử:</span></td>' .
            '          <td style="' . $subTableValueStyle . '">{{registration.email}}</td>' .
            '        </tr>' .
            '      </table>' .
            '    </td>' .
            '    <td style="' . $tdRight . '">' .
            '      <table style="width: 100%; border-collapse: collapse; border: none; margin: 0; padding: 0;">' .
            '        <tr>' .
            '          <td style="' . $subTableLabelStyle . '">Phone No.:<br><span style="' . $subLabelStyle . '">Số Điện Thoại:</span></td>' .
            '          <td style="' . $subTableValueStyle . '">{{registration.phone}}</td>' .
            '        </tr>' .
            '      </table>' .
            '    </td>' .
            '  </tr>' .

            // Row 5: Arrival Date & Departure Date
            '  <tr>' .
            '    <td style="' . $tdLeft . '">' .
            '      <table style="width: 100%; border-collapse: collapse; border: none; margin: 0; padding: 0;">' .
            '        <tr>' .
            '          <td style="' . $subTableLabelStyle . '">ArrivalDate:<br><span style="' . $subLabelStyle . '">Ngày Nhận Phòng:</span></td>' .
            '          <td style="' . $subTableValueStyle . '">{{registration.arrival_date}}</td>' .
            '        </tr>' .
            '      </table>' .
            '    </td>' .
            '    <td style="' . $tdRight . '">' .
            '      <table style="width: 100%; border-collapse: collapse; border: none; margin: 0; padding: 0;">' .
            '        <tr>' .
            '          <td style="' . $subTableLabelStyle . '">Departure Date:<br><span style="' . $subLabelStyle . '">Ngày Trả Phòng:</span></td>' .
            '          <td style="' . $subTableValueStyle . '">{{registration.departure_date}}</td>' .
            '        </tr>' .
            '      </table>' .
            '    </td>' .
            '  </tr>' .

            // Row 6: Room Type & Room No.
            '  <tr>' .
            '    <td style="' . $tdLeft . '">' .
            '      <table style="width: 100%; border-collapse: collapse; border: none; margin: 0; padding: 0;">' .
            '        <tr>' .
            '          <td style="' . $subTableLabelStyle . '">Room Type:<br><span style="' . $subLabelStyle . '">Loại Phòng:</span></td>' .
            '          <td style="' . $subTableValueStyle . '">{{registration.room_type}}</td>' .
            '        </tr>' .
            '      </table>' .
            '    </td>' .
            '    <td style="' . $tdRight . '">' .
            '      <table style="width: 100%; border-collapse: collapse; border: none; margin: 0; padding: 0;">' .
            '        <tr>' .
            '          <td style="' . $subTableLabelStyle . '">Room No.:<br><span style="' . $subLabelStyle . '">Số Phòng:</span></td>' .
            '          <td style="' . $subTableValueStyle . '">{{registration.room_no}}</td>' .
            '        </tr>' .
            '      </table>' .
            '    </td>' .
            '  </tr>' .

            // Row 7: No. of Room(s) & No. of Guest(s)
            '  <tr>' .
            '    <td style="' . $tdLeft . '">' .
            '      <table style="width: 100%; border-collapse: collapse; border: none; margin: 0; padding: 0;">' .
            '        <tr>' .
            '          <td style="' . $subTableLabelStyle . '">No. of Room(s):<br><span style="' . $subLabelStyle . '">Số Lượng Phòng:</span></td>' .
            '          <td style="' . $subTableValueStyle . '">{{registration.no_rooms}}</td>' .
            '        </tr>' .
            '      </table>' .
            '    </td>' .
            '    <td style="' . $tdRight . '">' .
            '      <table style="width: 100%; border-collapse: collapse; border: none; margin: 0; padding: 0;">' .
            '        <tr>' .
            '          <td style="' . $subTableLabelStyle . '">No. of Guest(s):<br><span style="' . $subLabelStyle . '">Số Lượng Khách:</span></td>' .
            '          <td style="' . $subTableValueStyle . '">{{registration.no_guests}}</td>' .
            '        </tr>' .
            '      </table>' .
            '    </td>' .
            '  </tr>' .

            // Row 8: Room Rate per night (VND) & No. of Night(s)
            '  <tr>' .
            '    <td style="' . $tdLeft . '">' .
            '      <table style="width: 100%; border-collapse: collapse; border: none; margin: 0; padding: 0;">' .
            '        <tr>' .
            '          <td style="' . $subTableLabelStyle . '">Room Rate per night (VND):<br><span style="' . $subLabelStyle . '">Giá Phòng/đêm (VNĐ):</span></td>' .
            '          <td style="' . $subTableValueStyle . '">{{registration.room_rate}}</td>' .
            '        </tr>' .
            '      </table>' .
            '    </td>' .
            '    <td style="' . $tdRight . '">' .
            '      <table style="width: 100%; border-collapse: collapse; border: none; margin: 0; padding: 0;">' .
            '        <tr>' .
            '          <td style="' . $subTableLabelStyle . '">No. of Night(s):<br><span style="' . $subLabelStyle . '">Số Đêm:</span></td>' .
            '          <td style="' . $subTableValueStyle . '">{{registration.no_nights}}</td>' .
            '        </tr>' .
            '      </table>' .
            '    </td>' .
            '  </tr>' .

            // Row 9: Deposit
            '  <tr>' .
            '    <td style="' . $tdLeft . '">' .
            '      <table style="width: 100%; border-collapse: collapse; border: none; margin: 0; padding: 0;">' .
            '        <tr>' .
            '          <td style="' . $subTableLabelStyle . '">Deposit<br><span style="' . $subLabelStyle . '">Đặt cọc:</span></td>' .
            '          <td style="' . $subTableValueStyle . ' line-height: 1.4;">' .
            '            Cash/ Tiền Mặt<br>' .
            '            Credit Card/ Thẻ Tín Dụng' .
            '          </td>' .
            '        </tr>' .
            '      </table>' .
            '    </td>' .
            '    <td style="' . $tdRight . '">' .
            '      <table style="width: 100%; border-collapse: collapse; border: none; margin: 0; padding: 0;">' .
            '        <tr>' .
            '          <td style="' . $subTableLabelStyle . '"></td>' .
            '          <td style="' . $subTableValueStyle . '">Bank Transfer/ Chuyển Khoản</td>' .
            '        </tr>' .
            '      </table>' .
            '    </td>' .
            '  </tr>' .

            // Row 10: Payment Method
            '  <tr>' .
            '    <td style="' . $tdLeft . '">' .
            '      <table style="width: 100%; border-collapse: collapse; border: none; margin: 0; padding: 0;">' .
            '        <tr>' .
            '          <td style="' . $subTableLabelStyle . '">Payment Method:<br><span style="' . $subLabelStyle . '">Thanh Toán:</span></td>' .
            '          <td style="' . $subTableValueStyle . ' line-height: 1.4;">' .
            '            Cash/ Tiền Mặt<br>' .
            '            Credit Card/ Thẻ Tín Dụng' .
            '          </td>' .
            '        </tr>' .
            '      </table>' .
            '    </td>' .
            '    <td style="' . $tdRight . '">' .
            '      <table style="width: 100%; border-collapse: collapse; border: none; margin: 0; padding: 0;">' .
            '        <tr>' .
            '          <td style="' . $subTableLabelStyle . '"></td>' .
            '          <td style="' . $subTableValueStyle . '">Bank Transfer/ Chuyển Khoản</td>' .
            '        </tr>' .
            '      </table>' .
            '    </td>' .
            '  </tr>' .
            '</table>';

        // Strip formatting whitespace and newlines from the table to prevent height stretching under pre-wrap
        $formTable = str_replace(["\r", "\n", "  "], "", $formTable);

        // 1. Define the block content JSON matching the visual editor structure
        $contentJson = [
            'header' => [
                [
                    'id' => 'h_logo_info',
                    'type' => 'text',
                    'content' => '<div style="text-align: center; margin-bottom: 5px;">' .
                        '<img src="/uploads/templates/galliot_logo_register.png" style="max-height: 70px; margin: 0 auto; display: block;" alt="Galliot Logo">' .
                        '</div>',
                    'style' => []
                ],
                [
                    'id' => 'h_form_title',
                    'type' => 'text',
                    'content' => '<div style="text-align: center; margin-top: 15px; margin-bottom: 12px;">' .
                        '<div style="font-size: 18px; font-weight: bold; color: #000; letter-spacing: 0.5px;">REGISTRATION FORM/ PHIẾU ĐĂNG KÝ</div>' .
                        '</div>',
                    'style' => []
                ]
            ],
            'detail' => [
                [
                    'id' => 'd_form_table',
                    'type' => 'text',
                    'content' => $formTable,
                    'style' => []
                ],
                [
                    'id' => 'd_refund_note',
                    'type' => 'text',
                    'content' => '<div style="font-size: 10px; color: #000; font-weight: normal; margin-top: 8px; line-height: 1.3;">' .
                        '*Credit card deposit refund will be processed within 30 - 45 working days and depends on card issuer bank process.' .
                        '</div>',
                    'style' => []
                ],
                [
                    'id' => 'd_signatures',
                    'type' => 'text',
                    'content' => '<table style="width: 100%; border: none; border-collapse: collapse; margin-top: 30px; margin-bottom: 25px;">' .
                        '  <tr>' .
                        '    <td style="width: 50%; font-size: 11.5px; color: #000; font-weight: bold; padding: 0; border: none !important;">' .
                        '      Guest Signature/Khách Ký Tên:' .
                        '    </td>' .
                        '    <td style="width: 50%; font-size: 11.5px; color: #000; font-weight: bold; padding: 0; border: none !important;">' .
                        '      Check In By/Lễ Tân:' .
                        '    </td>' .
                        '  </tr>' .
                        '  <tr>' .
                        '    <td style="height: 65px; border: none !important;"></td>' .
                        '    <td style="height: 65px; border: none !important;"></td>' .
                        '  </tr>' .
                        '</table>',
                    'style' => []
                ]
            ],
            'footer' => [
                [
                    'id' => 'f_key_reminder',
                    'type' => 'text',
                    'content' => '<div style="font-size: 11.5px; font-weight: normal; color: #000; margin-bottom: 12px; line-height: 1.4;">' .
                        'Please leave the key at the front desk / Vui lòng để lại chìa khóa tại quầy Lễ Tân khi ra ngoài.' .
                        '</div>',
                    'style' => []
                ],
                [
                    'id' => 'f_policy_en',
                    'type' => 'text',
                    'content' => '<div style="font-size: 9.5px; color: #000; line-height: 1.35; margin-bottom: 10px; text-align: justify;">' .
                        'By signing this registration card, I agree to be bound with Galliot Hotel\'s policies, rules and regulations. I hereby acknowledge that I am responsible for the payment of the cost and charges payable or incurred in connection with all services provided by the Hotel under this registration, including cancellation charges for any services agreed and confirmed at time of reservation. I herewith authorize the Hotel to debit any outstanding charges to my credit card. Money and all other valuable items must be placed in the Front Desk Safety Deposit Box, otherwise the management will not be responsible for any loss.' .
                        '</div>',
                    'style' => []
                ],
                [
                    'id' => 'f_policy_vn',
                    'type' => 'text',
                    'content' => '<div style="font-size: 9.5px; color: #000; line-height: 1.35; margin-bottom: 15px; text-align: justify;">' .
                        'Tôi đồng ý tuân thủ các chính sách, quy định khách sạn Galliot khi ký vào phiếu này. Tôi xác nhận rằng tôi chịu trách nhiệm về việc thanh toán chi phí và các khoản phí phải trả hoặc phát sinh liên quan đến tất cả các dịch vụ do Khách sạn cung cấp theo đăng ký này, bao gồm cả phí hủy cho bất kỳ dịch vụ nào đã thỏa thuận và xác nhận tại thời điểm đặt phòng. Tôi ủy quyền cho Khách sạn ghi nợ bất kỳ khoản phí chưa thanh toán nào vào thẻ tín dụng của tôi. Tiền và tất cả các vật dụng có giá trị khác phải được đặt trong Hộp ký gửi an toàn của Bộ Phận Lễ Tân, nếu không Ban Quản Lý sẽ không chịu trách nhiệm về bất kỳ tổn thất nào.' .
                        '</div>',
                    'style' => []
                ],
                [
                    'id' => 'f_decorative',
                    'type' => 'text',
                    'content' => '<div style="text-align: center; margin-top: 5px;">' .
                        '<img src="/uploads/templates/footer_register.png" style="width: 100%; max-height: 50px; object-fit: contain;" alt="Footer decoration">' .
                        '</div>',
                    'style' => []
                ]
            ]
        ];

        // 2. Find and update the template record
        $template = Template::where('group', 'Registration Card')
            ->where('name', 'Registration Card Main')
            ->first();

        if (!$template) {
            $this->warn('Template "Registration Card Main" not found. Creating a new one...');
            $template = new Template();
            $template->group = 'Registration Card';
            $template->name = 'Registration Card Main';
            $template->report = 'RegistrationCardGalliot';
        }

        // 3. Compile the content_html from content_json blocks
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

        // 4. Update the template fields
        $template->page_size = 'A4';
        $template->page_orientation = 'portrait';
        $template->margin_top = 10;
        $template->margin_bottom = 10;
        $template->margin_left = 15;
        $template->margin_right = 15;
        $template->content_json = $contentJson;
        $template->content_html = $html;
        $template->css = '';
        $template->is_default = true;
        
        $template->save();

        $this->info('Registration Card template seeded successfully!');
    }

    /**
     * Compile a single block object to its HTML string representation.
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
        
        if ($b['type'] === 'text' || $b['type'] === 'divider') {
            $blockHtml .= "  " . ($b['content'] ?? '') . PHP_EOL;
        } elseif ($b['type'] === 'spacer') {
            $height = $b['height'] ?? 20;
            $blockHtml .= "  <div style=\"height: {$height}px;\"></div>" . PHP_EOL;
        } elseif ($b['type'] === 'image') {
            if (!empty($b['imageUrl'])) {
                $blockHtml .= "  <img src=\"{$b['imageUrl']}\" style=\"max-height: 80px; max-width: 100%;\" alt=\"Image\">" . PHP_EOL;
            } else {
                $blockHtml .= "  {{" . ($b['content'] ?? 'hotel.logo') . "}}" . PHP_EOL;
            }
        } elseif ($b['type'] === 'table') {
            $blockHtml .= '  <table style="width: 100%; border-collapse: collapse;">' . PHP_EOL;
            $blockHtml .= '    <thead>' . PHP_EOL . '      <tr>' . PHP_EOL;
            foreach ($b['columns'] as $col) {
                $width = $col['width'] ?? 'auto';
                $blockHtml .= "        <th style=\"border-bottom: 2px solid #cbd5e1; padding: 6px 8px; font-weight: bold; width: {$width};\">{$col['header']}</th>" . PHP_EOL;
            }
            $blockHtml .= '      </tr>' . PHP_EOL . '    </thead>' . PHP_EOL;
            $blockHtml .= '    <tbody>' . PHP_EOL;
            $blockHtml .= "      <tr class=\"pms-detail-row\" data-source=\"{$b['dataSource']}\">" . PHP_EOL;
            foreach ($b['columns'] as $col) {
                $blockHtml .= "        <td style=\"border-bottom: 1px solid #e2e8f0; padding: 6px 8px;\">{{$col['value']}}</td>" . PHP_EOL;
            }
            $blockHtml .= '      </tr>' . PHP_EOL;
            $blockHtml .= '    </tbody>' . PHP_EOL;
            $blockHtml .= '  </table>' . PHP_EOL;
        } elseif ($b['type'] === 'columns') {
            $blockHtml .= '  <table style="width: 100%; border: none; border-collapse: collapse; margin: 0; padding: 0;">' . PHP_EOL;
            $blockHtml .= '    <tr style="border: none;">' . PHP_EOL;
            foreach ($b['columns'] as $col) {
                $width = $col['width'] ?? '50%';
                $blockHtml .= "      <td style=\"width: {$width}; border: none; padding: 0; vertical-align: top;\">" . PHP_EOL;
                if (!empty($col['blocks'])) {
                    foreach ($col['blocks'] as $subBlock) {
                        $blockHtml .= $this->compileBlockToHtml($subBlock);
                    }
                }
                $blockHtml .= '      </td>' . PHP_EOL;
            }
            $blockHtml .= '    </tr>' . PHP_EOL;
            $blockHtml .= '  </table>' . PHP_EOL;
        }
        
        $blockHtml .= '</div>' . PHP_EOL;
        return $blockHtml;
    }
}
