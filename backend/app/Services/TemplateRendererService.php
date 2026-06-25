<?php

namespace App\Services;

use App\Models\HotelSetting;

class TemplateRendererService
{
    /**
     * Render the template by replacing placeholders in HTML and injecting CSS.
     *
     * @param string $html
     * @param string $css
     * @param array $data
     * @param array $options
     * @return string
     */
    public function render(string $html, ?string $css, array $data, array $options = []): string
    {
        // 1. Flatten the structured data array to key-value pairs (e.g., customer.name => 'John')
        $flatData = $this->flattenData($data);

        // 2. Handle detail rows in bands (dynamic table repeating)
        // Find tags with class="pms-detail-row" and data-source="..."
        $html = $this->renderDetailRows($html, $data);

        // 3. Replace single placeholders {{category.field}}
        foreach ($flatData as $key => $value) {
            $html = str_replace('{{' . $key . '}}', (string)$value, $html);
        }

        // Clean up any remaining unresolved placeholders
        $html = preg_replace('/\{\{[a-zA-Z0-9_\.]+\}\}/', '', $html);

        // 4. Inject CSS styles
        $compiledCss = $css ?? '';
        
        // Return completed HTML document
        return $this->buildFullHtmlDocument($html, $compiledCss, $options);
    }

    /**
     * Flatten a multidimensional array into dot-notation keys.
     */
    private function flattenData(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                // If it's a numeric array (list of items), we don't flatten it for direct replacement
                if (array_keys($value) === range(0, count($value) - 1)) {
                    continue;
                }
                $result = array_merge($result, $this->flattenData($value, $prefix . $key . '.'));
            } else {
                $result[$prefix . $key] = $value;
            }
        }
        return $result;
    }

    /**
     * Parse and render dynamic loops for rows inside Detail Band tables.
     * Searches for elements with class "pms-detail-row" and data-source attribute.
     */
    private function renderDetailRows(string $html, array $data): string
    {
        // Match elements like <tr class="pms-detail-row" data-source="booking.services">...</tr>
        // Regex handles attributes in different orders and whitespace
        $pattern = '/<tr\s+[^>]*class="[^"]*pms-detail-row[^"]*"[^>]*data-source="([^"]+)"[^>]*>(.*?)<\/tr>/is';

        return preg_replace_callback($pattern, function ($matches) use ($data) {
            $dataSourcePath = $matches[1]; // e.g., 'booking.services' or 'booking.rooms'
            $rowTemplate = $matches[2];     // The HTML content of the row
            
            // Extract items from data path
            $items = $this->getValueByPath($data, $dataSourcePath);

            if (empty($items) || !is_array($items)) {
                return ''; // No items to render
            }

            $renderedRows = '';
            
            // Determine variable prefix from data source path (e.g. booking.services -> 'service')
            $parts = explode('.', $dataSourcePath);
            $itemPrefix = end($parts);
            // Singularize common prefixes: services -> service, rooms -> room, payments -> payment / item
            if ($itemPrefix === 'services') {
                $itemPrefix = 'service';
            } elseif ($itemPrefix === 'rooms') {
                $itemPrefix = 'room';
            } elseif ($itemPrefix === 'payments') {
                $itemPrefix = 'payment';
            } else {
                $itemPrefix = 'item';
            }

            foreach ($items as $item) {
                $currentRow = $rowTemplate;
                // Replace placeholders for this item: e.g. {{service.name}}
                foreach ($item as $key => $value) {
                    // Match both {{item.key}} and specific prefix like {{service.key}}
                    $currentRow = str_replace('{{' . $itemPrefix . '.' . $key . '}}', (string)$value, $currentRow);
                    $currentRow = str_replace('{{item.' . $key . '}}', (string)$value, $currentRow);
                }
                // Wrap back in a table row tag, but remove class/data-source to make it standard
                $renderedRows .= "<tr>" . $currentRow . "</tr>\n";
            }

            return $renderedRows;
        }, $html);
    }

    /**
     * Helper to retrieve value from nested array using dot notation.
     */
    private function getValueByPath(array $array, string $path)
    {
        $keys = explode('.', $path);
        foreach ($keys as $key) {
            if (isset($array[$key])) {
                $array = $array[$key];
            } else {
                return null;
            }
        }
        return $array;
    }

    /**
     * Generate structured mock data for previewing templates.
     */
    public function getMockData(string $group, ?string $templateName = null): array
    {
        $hotelSetting = HotelSetting::first();
        
        $hotelMock = [
            'name' => $hotelSetting ? $hotelSetting->hotel_name : 'GALLIOT HOTEL NHA TRANG',
            'address' => $hotelSetting ? ($hotelSetting->address ?? '61A Nguyen Thien Thuat, Loc Tho, Nha Trang') : '61A Nguyen Thien Thuat, Loc Tho, Nha Trang',
            'phone' => $hotelSetting ? ($hotelSetting->phone ?? '+84 258 3528 555') : '+84 258 3528 555',
            'email' => $hotelSetting ? ($hotelSetting->email ?? 'info@galliothotel.com') : 'info@galliothotel.com',
            'logo' => $hotelSetting && $hotelSetting->logo_url 
                ? '<img src="' . $hotelSetting->logo_url . '" style="height: 60px;" alt="Logo">' 
                : '<div style="font-weight: bold; font-size: 24px; color: #0284c7;">GALLIOT HOTEL</div>',
        ];

        $customerMock = [
            'name' => 'MURZAGALIYEVA MARZHAN/2553403',
            'phone' => '0901234567',
            'email' => 'marzhan@gmail.com',
            'id_card' => '20260328-503282-1247143939/MRS',
        ];

        $isNavy = $templateName && str_contains(strtolower($templateName), 'navy');
        $isNavyDalat = $isNavy && $templateName && (
            str_contains(strtolower($templateName), 'dalat') ||
            str_contains(strtolower($templateName), 'da lat') ||
            str_contains(strtolower($templateName), 'đà lạt')
        );
        $isNavyNhatrang = $isNavy && $templateName && (
            str_contains(strtolower($templateName), 'nha trang') ||
            str_contains(strtolower($templateName), 'nhatrang')
        );

        if ($group === 'Booking Confirmation' && $isNavyNhatrang) {
            $hotelMock = [
                'name' => 'NAVY HOTEL NHA TRANG',
                'address' => '18-20 Tran Hung Dao, Loc Tho, Nha Trang',
                'phone' => '0258.3599.088',
                'fax' => '',
                'email' => 'info@navyhotel.vn',
                'website' => 'www.navyhotel.vn',
                'logo' => '<img src="/uploads/templates/navy_nhatrang_logo.png" style="height: 70px;" alt="Logo">',
            ];

            $customerMock = [
                'name' => 'MR VIACHASLAU FILIPCHUK',
                'phone' => '',
                'fax' => '',
                'email' => '',
                'id_card' => '',
            ];

            $bookingMock = [
                'code' => 'NV1836',
                'date' => '23/06/2026',
                'checkin_date' => '23/06/2026',
                'checkout_date' => '07/07/2026',
                'adults' => 2,
                'children' => 0,
                'nights' => 14,
                'lines' => [
                    [
                        'checkin' => '23/06/2026',
                        'checkout' => '06/07/2026',
                        'service' => 'Deluxe Triple City View',
                        'quantity' => '1',
                        'guests' => '2',
                        'unit_price' => '800,000',
                        'amount' => '10,400,000',
                    ],
                    [
                        'checkin' => '06/07/2026',
                        'checkout' => '07/07/2026',
                        'service' => 'Deluxe Triple City View',
                        'quantity' => '1',
                        'guests' => '2',
                        'unit_price' => '0',
                        'amount' => '0',
                    ],
                ],
            ];

            $paymentMock = [
                'total' => '10,400,000',
                'deposit' => '0',
                'balance' => '10,400,000',
                'method' => 'Bank transfer',
            ];

            $confirmationMock = [
                'from' => '',
                'company' => 'AMEGA TRAVEL',
                'printed_at' => 'Tuesday, 23/06/2026 09:29:27 AM',
                'payment_method' => 'Bank transfer',
                'guarantee_status' => 'EXPECTED CONFIRM',
                'notes' => 'Reservation No.900027592/Amega',
                'breakfast_yes_box' => '☐',
                'breakfast_no_box' => '☐',
                'vat_yes_box' => '☑',
                'vat_no_box' => '☐',
            ];

            return [
                'hotel' => $hotelMock,
                'customer' => $customerMock,
                'booking' => $bookingMock,
                'payment' => $paymentMock,
                'confirmation' => $confirmationMock,
            ];
        }

        if ($group === 'Booking Confirmation' && $isNavyDalat) {
            $hotelMock = [
                'name' => 'NAVY HOTEL ĐÀ LẠT',
                'address' => '25 Phù Đổng Thiên Vương, Phường Lâm Viên- Đà Lạt, Tỉnh Lâm Đồng',
                'phone' => '02633 553 105',
                'email' => 'info@navydalat.vn',
                'website' => 'www.navydalat.vn',
                'logo' => '<img src="/uploads/templates/navy_dalat_logo.png" style="height: 70px;" alt="Logo">',
            ];

            $customerMock = [
                'name' => 'Thái Văn Hiền',
                'phone' => '0901234567',
                'email' => 'hien@gmail.com',
                'id_card' => '',
            ];

            $bookingMock = [
                'code' => 'NVD271',
                'date' => '22/06/2026',
                'checkin_date' => '22/06/2026',
                'checkout_date' => '23/06/2026',
                'adults' => 2,
                'children' => 0,
                'nights' => 1,
                'lines' => [
                    [
                        'checkin' => '22/06/2026',
                        'checkout' => '23/06/2026',
                        'service' => 'Deluxe Twin',
                        'room_number' => '107',
                        'quantity' => '1',
                        'nights' => '1',
                        'guests' => '2',
                        'unit_price' => '780,000',
                        'amount' => '780,000',
                    ],
                ],
            ];

            $paymentMock = [
                'total' => '780,000',
                'deposit' => '780,000',
                'balance' => '0',
                'method' => 'Bank transfer thanh toán 100% 10 ngày trước ngày nhận phòng',
            ];

            $confirmationMock = [
                'contact_person' => '',
                'confirmed_by' => 'Hoàng Thị Thu Trang',
                'contact_phone' => '',
                'contact_email' => '',
                'company' => 'TONG CONG TY TAN CANG SAI GON',
                'tour_code' => '',
                'notes' => 'TCL',
                'room_note' => '1 Twin',
                'payment_method' => 'Bank transfer thanh toán 100% 10 ngày trước ngày nhận phòng',
                'bank_account_name' => 'CHI NHÁNH CÔNG TY CỔ PHẦN DỊCH VỤ BAY VÀ DU LỊCH BIỂN TÂN CẢNG TẠI ĐÀ LẠT',
                'bank_account_number' => '7451100001168',
                'bank_name' => 'MB Bank - chi nhánh Lâm Đồng',
                'transfer_content' => 'Thanh toán tiền phòng cho booking NVD271',
                'invoice_company' => '',
                'invoice_address' => '',
                'invoice_tax_code' => '',
                'invoice_email' => '',
            ];

            return [
                'hotel' => $hotelMock,
                'customer' => $customerMock,
                'booking' => $bookingMock,
                'payment' => $paymentMock,
                'confirmation' => $confirmationMock,
            ];
        }

        if ($group === 'Invoice' && !$isNavy) {
            // Galliot Invoice Mock Data
            $customerMock = [
                'name' => 'PEGAS/9703607',
                'phone' => '0901234567',
                'email' => 'pegas@gmail.com',
                'id_card' => '20260328-503282-1247143939/MRS',
            ];

            $bookingMock = [
                'code' => 'GAL271',
                'date' => '07/06/2025',
                'payment_code' => 'PT-GAL271',
                'ta_com' => 'PEGAS',
                'room' => '202',
                'checkin_date' => '07/06/2025',
                'checkout_date' => '20/06/2025',
                'adults' => 1,
                'children' => 0,
                'nights' => 12,
                'services' => [
                    ['date' => '07/06/2025', 'name' => 'Dịch vụ phòng nghỉ', 'room' => '202', 'price' => '580,000', 'quantity' => 1, 'amount' => '580,000'],
                    ['date' => '08/06/2025', 'name' => 'Dịch vụ phòng nghỉ', 'room' => '202', 'price' => '580,000', 'quantity' => 1, 'amount' => '580,000'],
                    ['date' => '09/06/2025', 'name' => 'Dịch vụ phòng nghỉ', 'room' => '202', 'price' => '580,000', 'quantity' => 1, 'amount' => '580,000'],
                    ['date' => '10/06/2025', 'name' => 'Dịch vụ phòng nghỉ', 'room' => '202', 'price' => '580,000', 'quantity' => 1, 'amount' => '580,000'],
                    ['date' => '11/06/2025', 'name' => 'Dịch vụ phòng nghỉ', 'room' => '202', 'price' => '580,000', 'quantity' => 1, 'amount' => '580,000'],
                    ['date' => '12/06/2025', 'name' => 'Dịch vụ phòng nghỉ', 'room' => '202', 'price' => '580,000', 'quantity' => 1, 'amount' => '580,000'],
                    ['date' => '13/06/2025', 'name' => 'Dịch vụ phòng nghỉ', 'room' => '202', 'price' => '580,000', 'quantity' => 1, 'amount' => '580,000'],
                    ['date' => '14/06/2025', 'name' => 'Dịch vụ phòng nghỉ', 'room' => '202', 'price' => '580,000', 'quantity' => 1, 'amount' => '580,000'],
                    ['date' => '15/06/2025', 'name' => 'Dịch vụ phòng nghỉ', 'room' => '202', 'price' => '580,000', 'quantity' => 1, 'amount' => '580,000'],
                    ['date' => '16/06/2025', 'name' => 'Dịch vụ phòng nghỉ', 'room' => '202', 'price' => '580,000', 'quantity' => 1, 'amount' => '580,000'],
                    ['date' => '17/06/2025', 'name' => 'Dịch vụ phòng nghỉ', 'room' => '202', 'price' => '580,000', 'quantity' => 1, 'amount' => '580,000'],
                    ['date' => '18/06/2025', 'name' => 'Dịch vụ phòng nghỉ', 'room' => '202', 'price' => '580,000', 'quantity' => 1, 'amount' => '580,000'],
                ],
                'rooms' => [
                    ['room_number' => '202', 'room_class' => 'Delux Room', 'price' => '580,000'],
                ],
                'payments' => [
                    ['date' => '23/08/2025', 'time' => '14:36', 'ref' => 'Thu 17/07/25', 'method' => 'BT (thu hồi công nợ)', 'amount' => '6,960,000'],
                ]
            ];

            $roomMock = [
                'number' => '202',
                'class' => 'Delux Room',
                'price' => '580,000',
            ];

            $paymentMock = [
                'deposit' => '0',
                'total' => '6,960,000',
                'paid' => '6,960,000',
                'balance' => '0',
                'amount_in_words' => 'Sáu triệu chín trăm sáu mươi ngàn đồng chẵn',
                'method' => 'BT (thu hồi công nợ)',
            ];
        } else {
            // Navy Invoice (or default Invoice) Mock Data
            $bookingMock = [
                'code' => 'NV271',
                'date' => '28/03/2026',
                'payment_code' => 'PT-NV271',
                'ta_com' => 'Travel Concierge',
                'room' => '408',
                'checkin_date' => '28/03/2026',
                'checkout_date' => '04/04/2026',
                'adults' => 1,
                'children' => 0,
                'nights' => 7,
                'services' => [
                    ['date' => '28/03/2026', 'name' => 'Dịch vụ phòng nghỉ', 'room' => '408', 'price' => '380,000', 'quantity' => 1, 'amount' => '380,000'],
                    ['date' => '29/03/2026', 'name' => 'Dịch vụ phòng nghỉ', 'room' => '408', 'price' => '380,000', 'quantity' => 1, 'amount' => '380,000'],
                    ['date' => '30/03/2026', 'name' => 'Dịch vụ phòng nghỉ', 'room' => '408', 'price' => '380,000', 'quantity' => 1, 'amount' => '380,000'],
                    ['date' => '31/03/2026', 'name' => 'Dịch vụ phòng nghỉ', 'room' => '408', 'price' => '380,000', 'quantity' => 1, 'amount' => '380,000'],
                    ['date' => '01/04/2026', 'name' => 'Dịch vụ phòng nghỉ', 'room' => '408', 'price' => '380,000', 'quantity' => 1, 'amount' => '380,000'],
                    ['date' => '02/04/2026', 'name' => 'Dịch vụ phòng nghỉ', 'room' => '408', 'price' => '380,000', 'quantity' => 1, 'amount' => '380,000'],
                    ['date' => '03/04/2026', 'name' => 'Dịch vụ phòng nghỉ', 'room' => '408', 'price' => '0', 'quantity' => 1, 'amount' => '0'],
                ],
                'rooms' => [
                    ['room_number' => '408', 'room_class' => 'Superior Twin', 'price' => '380,000'],
                ],
                'payments' => [
                    ['date' => '26/03/2026', 'time' => '09:18', 'method' => 'Deposit (Bank transfer)', 'ref' => 'BT', 'amount' => '2,280,000'],
                ]
            ];

            $roomMock = [
                'number' => '408',
                'class' => 'Superior Twin',
                'price' => '380,000',
            ];

            $paymentMock = [
                'deposit' => '2,280,000',
                'total' => '2,280,000',
                'paid' => '0',
                'balance' => '0',
                'amount_in_words' => 'Không đồng',
                'method' => 'Deposit (Bank transfer)',
            ];
        }

        $registrationMock = [
            'confirmation_no' => 'GAL-2026-9703',
            'company' => 'CÔNG TY TNHH LỮ HÀNH ANH DƯƠNG / PEGAS TOURIST',
            'guest_name' => 'MURZAGALIYEVA MARZHAN',
            'id_passport' => 'N12345678',
            'nationality' => 'KAZAKHSTAN',
            'email' => 'marzhan@gmail.com',
            'phone' => '+7 701 123 4567',
            'arrival_date' => '24/06/2026',
            'departure_date' => '05/07/2026',
            'room_type' => 'Deluxe Sea View (DLX)',
            'room_no' => '408',
            'no_rooms' => '1',
            'no_guests' => '2 Adults, 0 Children',
            'room_rate' => '1,200,000',
            'no_nights' => '11',
            'deposit_method' => 'Credit Card / Visa',
            'payment_method' => 'Bank Transfer / Chuyển khoản',
        ];

        return [
            'hotel' => $hotelMock,
            'customer' => $customerMock,
            'booking' => $bookingMock,
            'room' => $roomMock,
            'payment' => $paymentMock,
            'registration' => $registrationMock,
            // Receipt fields at top level for easy access
            'date' => '20260328-503282-',
            'guest_name' => 'NGUYEN VAN A',
            'phone' => '0901234567',
            'amount' => '2,500,000 đ',
            'amount_in_words' => 'Hai triệu năm trăm ngàn đồng chẵn',
            'content' => 'Thanh toán tiền đặt cọc phòng',
            'payment_method' => 'Tiền mặt / Cash',
            'booking_code' => '20260328-503282-',
            'room_no' => '408',
            'phone_no' => '0988246336',
        ];
    }

    /**
     * Combine the HTML body and CSS code into a standalone document suitable for browser previewing/printing.
     */
    private function buildFullHtmlDocument(string $bodyHtml, string $css, array $options = []): string
    {
        $pageSize = $options['page_size'] ?? 'A4';
        $pageOrientation = $options['page_orientation'] ?? 'portrait';
        $marginTop = $options['margin_top'] ?? 10;
        $marginBottom = $options['margin_bottom'] ?? 10;
        $marginLeft = $options['margin_left'] ?? 10;
        $marginRight = $options['margin_right'] ?? 10;

        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Print Template Document</title>
    <style>
        /* General Web Reset */
        body {
            font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding-top: ' . $marginTop . 'mm;
            padding-bottom: ' . $marginBottom . 'mm;
            padding-left: ' . $marginLeft . 'mm;
            padding-right: ' . $marginRight . 'mm;
            box-sizing: border-box;
            color: #1e293b;
            font-size: 13px;
            line-height: 1.5;
            background-color: #fff;
            width: 100%;
        }
        
        /* Table Styles */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 10px;
        }
        th, td {
            padding: 8px 10px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background-color: #f8fafc;
            font-weight: bold;
            color: #475569;
        }
        
        /* Printed styles */
        @media print {
            body {
                background: none;
                color: #000;
                padding: 0 !important;
            }
            @page {
                size: ' . $pageSize . ' ' . $pageOrientation . ';
                margin: ' . $marginTop . 'mm ' . $marginRight . 'mm ' . $marginBottom . 'mm ' . $marginLeft . 'mm;
            }
            .no-print {
                display: none !important;
            }
            tr {
                page-break-inside: avoid;
            }
        }
        
        /* Dynamic User Injected CSS */
        ' . $css . '
    </style>
</head>
<body>
    ' . $bodyHtml . '
</body>
</html>';
    }
}
