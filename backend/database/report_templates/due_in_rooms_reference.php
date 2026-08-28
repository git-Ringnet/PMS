<?php

use Illuminate\Support\Facades\DB;

return new class
{
    public function apply(): void
    {
        DB::table('templates')->where('report', 'DUE_IN_ROOMS_STANDARD')->update([
            'content_json' => json_encode($this->blocks(), JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    private function blocks(): array
    {
        return [
            'header' => [
                ['id'=>'due_in_hotel','type'=>'text','content'=>'<div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Người dùng:</b> {{report.generated_by}} <b>Ngày:</b> {{report.generated_at}}</div></div></div>','style'=>['fontSize'=>'9px','marginBottom'=>'4px']],
                ['id'=>'due_in_divider','type'=>'divider','content'=>'<hr>','style'=>['marginBottom'=>'4px']],
                ['id'=>'due_in_title','type'=>'text','content'=>'<h1>DANH SÁCH PHÒNG DUE IN</h1>','style'=>['textAlign'=>'center','fontWeight'=>'bold','marginBottom'=>'6px']],
                ['id'=>'due_in_period','type'=>'text','content'=>'<p>Ngày: {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p>','style'=>['textAlign'=>'center','marginBottom'=>'10px']],
            ],
            'detail' => [[
                'id'=>'due_in_table','type'=>'table','dataSource'=>'rows','tableType'=>'dynamic','tableStyle'=>'grid','isNew'=>false,
                'groupBy'=>'ArrivalDateGroup','groupHeader'=>'<td colspan="12" class="date-row">Ngày: {{row.ArrivalDateGroup}}</td>',
                'columns'=>[
                    ['header'=>'STT','value'=>'row._index','width'=>'4%','align'=>'center'],['header'=>'Mã ĐK','value'=>'row.BookingId','width'=>'7%','align'=>'center'],['header'=>'Tên Khách','value'=>'row.GuestName','width'=>'14%','align'=>'left'],['header'=>'Công Ty','value'=>'row.Company','width'=>'12%','align'=>'left'],['header'=>'Phòng','value'=>'row.Room','width'=>'6%','align'=>'center'],['header'=>'Loại Phòng','value'=>'row.RoomType','width'=>'8%','align'=>'center'],['header'=>'Ngày Đến','value'=>'row.ArrivalDate','width'=>'11%','align'=>'center'],['header'=>'Ngày Đi','value'=>'row.DepartureDate','width'=>'11%','align'=>'center'],['header'=>'Đêm','value'=>'row.RoomNight','width'=>'5%','align'=>'center'],['header'=>'Thêm Giường','value'=>'row.ExtraBed','width'=>'8%','align'=>'center'],['header'=>'N.Lớn / T.Em','value'=>'row.AdultChild','width'=>'8%','align'=>'center'],['header'=>'Ghi Chú','value'=>'row.Note','width'=>'12%','align'=>'left'],
                ],
                'style'=>['marginTop'=>'4px','marginBottom'=>'4px'],
            ]],
            'footer' => [['id'=>'due_in_total','type'=>'text','content'=>'<div class="grand-total"><b>Tổng số dòng:</b> {{summary.row_count}}</div>','style'=>['textAlign'=>'right','fontWeight'=>'bold','marginTop'=>'5px']]],
        ];
    }
};
