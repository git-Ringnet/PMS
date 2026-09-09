<?php

use Illuminate\Support\Facades\DB;

return new class
{
    public function apply(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;

        DB::table('templates')->where('report', 'EARLY_CHECKOUT_ROOMS_STANDARD')->update([
            'content_json' => json_encode($this->blocks(), JSON_UNESCAPED_UNICODE),
            'content_html' => $this->html(),
            'css' => $this->css(),
            'updated_at' => now(),
        ]);
    }

    private function blocks(): array
    {
        return [
            'header' => [
                ['id' => 'early_checkout_header', 'type' => 'text', 'content' => '<div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Người dùng:</b> {{report.generated_by}} <b class="generated-date">Ngày:</b> {{report.generated_at}}</div></div></div><hr class="header-divider"><h1>BÁO CÁO PHÒNG CHECKOUT SỚM</h1><p class="report-period"><b>Ngày checkout:</b><span>{{parameters.p_from_date}}</span><b>~</b><span>{{parameters.p_to_date}}</span></p>', 'style' => ['fontSize' => '9px', 'marginBottom' => '4px']],
            ],
            'detail' => [
                [
                    'id' => 'early_checkout_table',
                    'type' => 'table',
                    'dataSource' => 'rows',
                    'tableType' => 'dynamic',
                    'tableStyle' => 'grid',
                    'isNew' => false,
                    'groupBy' => 'CheckoutDateGroup',
                    'groupHeader' => '<td colspan="11" class="date-row">Ngày checkout: {{row.CheckoutDateGroup}}</td>',
                    'columns' => $this->columns(),
                    'style' => ['fontSize' => '9px', 'marginTop' => '4px', 'marginBottom' => '0px'],
                ],
            ],
            'footer' => [],
        ];
    }

    private function columns(): array
    {
        return [
            ['header' => 'STT', 'value' => 'row.STT', 'width' => '4%', 'align' => 'center'],
            ['header' => 'Mã ĐK', 'value' => 'row.BookingId', 'width' => '8%', 'align' => 'center'],
            ['header' => 'Phòng', 'value' => 'row.Room', 'width' => '7%', 'align' => 'center'],
            ['header' => 'Loại phòng', 'value' => 'row.RoomType', 'width' => '9%', 'align' => 'center'],
            ['header' => 'Tên khách', 'value' => 'row.GuestName', 'width' => '15%', 'align' => 'left'],
            ['header' => 'Ngày đến', 'value' => 'row.ArrivalDate', 'width' => '9%', 'align' => 'center'],
            ['header' => 'Ngày đi dự kiến', 'value' => 'row.PlannedDepartureDate', 'width' => '11%', 'align' => 'center'],
            ['header' => 'Ngày checkout', 'value' => 'row.ActualCheckoutDate', 'width' => '11%', 'align' => 'center'],
            ['header' => 'Số ngày sớm', 'value' => 'row.EarlyCheckoutDays', 'width' => '8%', 'align' => 'center'],
            ['header' => 'Người checkout', 'value' => 'row.CheckoutUser', 'width' => '10%', 'align' => 'center'],
            ['header' => 'Ghi chú', 'value' => 'row.Note', 'width' => '8%', 'align' => 'left'],
        ];
    }

    private function html(): string
    {
        return <<<'HTML'
<div class="report-header-band">
  <div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Người dùng:</b> {{report.generated_by}} <b class="generated-date">Ngày:</b> {{report.generated_at}}</div></div></div>
  <hr class="header-divider">
  <h1>BÁO CÁO PHÒNG CHECKOUT SỚM</h1>
  <p class="report-period"><b>Ngày checkout:</b><span>{{parameters.p_from_date}}</span><b>~</b><span>{{parameters.p_to_date}}</span></p>
</div>
<div class="report-detail-band">
  <table class="early-checkout-table">
    <thead><tr><th>STT</th><th>Mã ĐK</th><th>Phòng</th><th>Loại phòng</th><th>Tên khách</th><th>Ngày đến</th><th>Ngày đi dự kiến</th><th>Ngày checkout</th><th>Số ngày sớm</th><th>Người checkout</th><th>Ghi chú</th></tr></thead>
    <tbody class="pms-grouped-rows" data-source="rows" data-group-by="CheckoutDateGroup">
      <tr class="pms-group-header"><td colspan="11" class="date-row">Ngày checkout: {{row.CheckoutDateGroup}}</td></tr>
      <tr class="pms-detail-row"><td>{{row.STT}}</td><td>{{row.BookingId}}</td><td>{{row.Room}}</td><td>{{row.RoomType}}</td><td>{{row.GuestName}}</td><td>{{row.ArrivalDate}}</td><td>{{row.PlannedDepartureDate}}</td><td>{{row.ActualCheckoutDate}}</td><td>{{row.EarlyCheckoutDays}}</td><td>{{row.CheckoutUser}}</td><td>{{row.Note}}</td></tr>
    </tbody>
  </table>
</div>
HTML;
    }

    private function css(): string
    {
        return <<<'CSS'
body { color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 9px; }
.hotel-header { display: grid; grid-template-columns: 180px 1fr; align-items: center; min-height: 66px; }
.hotel-logo { display: flex; align-items: center; min-height: 58px; }
.hotel-logo-image { display: block; max-width: 120px; max-height: 58px; object-fit: contain; }
.hotel-information { line-height: 1.8; }
.hotel-information .generated-date { margin-left: 36px; }
.header-divider { margin: 0 0 5px; border: 0; border-top: 1.5px solid #000; }
h1 { margin: 0; text-align: center; font-size: 20px; line-height: 1.25; }
.report-period { display: flex; justify-content: center; gap: 24px; margin: 7px 0 12px; }
.early-checkout-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.early-checkout-table th, .early-checkout-table td { border: 1px solid #b9c3d0; padding: 4px; line-height: 1.15; vertical-align: middle; overflow-wrap: anywhere; }
.early-checkout-table th { background: #d9e1ec; text-align: center; font-weight: 700; }
.early-checkout-table td:nth-child(1), .early-checkout-table td:nth-child(2), .early-checkout-table td:nth-child(3), .early-checkout-table td:nth-child(4), .early-checkout-table td:nth-child(6), .early-checkout-table td:nth-child(7), .early-checkout-table td:nth-child(8), .early-checkout-table td:nth-child(9) { text-align: center; }
.date-row { color: #b91c1c; background: #fff; font-weight: 700; text-align: left !important; }
@media print { thead { display: table-header-group; } tr { break-inside: avoid; } }
CSS;
    }
};
