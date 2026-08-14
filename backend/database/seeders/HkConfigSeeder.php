<?php

namespace Database\Seeders;

use App\Models\HkPrintCol;
use App\Models\HkSymbol;
use Illuminate\Database\Seeder;

class HkConfigSeeder extends Seeder
{
    public function run(): void
    {
        $symbols = [
            ['group' => 'hk', 'status_key' => 'occupied_dirty',  'code' => 'OD',  'label' => 'Có khách ở chưa làm vệ sinh',    'color' => '#ef4444', 'sort_order' => 1],
            ['group' => 'hk', 'status_key' => 'occupied_clean',  'code' => 'OC',  'label' => 'Có khách ở đã được làm vệ sinh',  'color' => '#10b981', 'sort_order' => 2],
            ['group' => 'hk', 'status_key' => 'occupied_ready',  'code' => 'OR',  'label' => 'Có khách ở - phòng đã sẵn sàng',        'color' => '#059669', 'sort_order' => 3],
            ['group' => 'hk', 'status_key' => 'vacant_dirty',    'code' => 'VD',  'label' => 'Phòng trống dơ',           'color' => '#f59e0b', 'sort_order' => 4],
            ['group' => 'hk', 'status_key' => 'vacant_clean',    'code' => 'VC',  'label' => 'Phòng trống sạch',         'color' => '#6ee7b7', 'sort_order' => 5],
            ['group' => 'hk', 'status_key' => 'vacant_ready',    'code' => 'VR',  'label' => 'Phòng sẵn sàng đón khách', 'color' => '#34d399', 'sort_order' => 6],
            ['group' => 'hk', 'status_key' => 'ooo',             'code' => 'OOO', 'label' => 'Phòng đang sửa chữa, không bán được', 'color' => '#94a3b8', 'sort_order' => 7],
            ['group' => 'hk', 'status_key' => 'oos',             'code' => 'OOS', 'label' => 'Out of Service',           'color' => '#7dd3fc', 'sort_order' => 8],
            ['group' => 'hk', 'status_key' => 'dnd',             'code' => 'DND', 'label' => 'Không làm phiền khách',     'color' => '#a78bfa', 'sort_order' => 9],
            ['group' => 'hk', 'status_key' => 'turndown',        'code' => 'TD',  'label' => 'Turndown',                 'color' => '#fb923c', 'sort_order' => 10],
            ['group' => 'hk', 'status_key' => 'housekeeping',    'code' => 'HK',  'label' => 'Đang dọn phòng',           'color' => '#60a5fa', 'sort_order' => 11],
            ['group' => 'hk', 'status_key' => 'si',              'code' => 'SI',  'label' => 'Phòng để khách tham quan',  'color' => '#f9a8d4', 'sort_order' => 12],
            ['group' => 'hk', 'status_key' => 'vacant_priority', 'code' => 'VP',  'label' => 'Ưu tiên dọn',              'color' => '#fbbf24', 'sort_order' => 13],
            ['group' => 'booking', 'status_key' => 'reserved', 'code' => 'CI',  'label' => 'Phòng chuẩn bị nhận trong ngày', 'color' => '#3b82f6', 'sort_order' => 1],
            ['group' => 'booking', 'status_key' => 'checkout', 'code' => 'CO',  'label' => 'Phòng khách trả trong ngày',    'color' => '#f43f5e', 'sort_order' => 2],
            ['group' => 'booking', 'status_key' => 'lco',      'code' => 'LCO', 'label' => 'Phòng trả trễ',                 'color' => '#f97316', 'sort_order' => 3],
            ['group' => 'booking', 'status_key' => 'occupied', 'code' => '',    'label' => 'Đang lưu trú',                  'color' => '#64748b', 'sort_order' => 4],
            ['group' => 'extra', 'status_key' => 'ep',      'code' => 'EP',       'label' => 'Bổ sung thêm người', 'color' => null, 'sort_order' => 1],
            ['group' => 'extra', 'status_key' => 'eb',      'code' => 'EB',       'label' => 'Giường phụ',         'color' => null, 'sort_order' => 2],
            ['group' => 'extra', 'status_key' => 'sofabed', 'code' => 'Sofa Bed', 'label' => 'Giường sofa',        'color' => null, 'sort_order' => 3],
        ];
        
        HkSymbol::query()->delete();
        foreach ($symbols as $sym) {
            HkSymbol::create($sym);
        }

        HkPrintCol::where('template', 'worksheet')->delete();
        $ws = [
            ['label'=>'STT','width'=>'24px','is_fixed'=>true,'sort_order'=>1,'parent_label'=>null],
            ['label'=>'PHÒNG','width'=>'36px','is_fixed'=>true,'sort_order'=>2,'parent_label'=>null],
            ['label'=>'LOẠI','width'=>'40px','is_fixed'=>true,'sort_order'=>3,'parent_label'=>null],
            ['label'=>'TÌNH TRẠNG','width'=>'42px','is_fixed'=>true,'sort_order'=>4,'parent_label'=>null],
            ['label'=>'VÀO','width'=>'38px','is_fixed'=>false,'sort_order'=>5,'parent_label'=>'GIỜ'],
            ['label'=>'RA','width'=>'38px','is_fixed'=>false,'sort_order'=>6,'parent_label'=>'GIỜ'],
            ['label'=>'LỚN','width'=>'30px','is_fixed'=>false,'sort_order'=>7,'parent_label'=>'DRAP'],
            ['label'=>'NHỎ','width'=>'30px','is_fixed'=>false,'sort_order'=>8,'parent_label'=>'DRAP'],
            ['label'=>'LỚN','width'=>'30px','is_fixed'=>false,'sort_order'=>9,'parent_label'=>'BỌC'],
            ['label'=>'NHỎ','width'=>'30px','is_fixed'=>false,'sort_order'=>10,'parent_label'=>'BỌC'],
            ['label'=>'ÁO GỐI','width'=>'30px','is_fixed'=>false,'sort_order'=>11,'parent_label'=>null],
            ['label'=>'TẮM','width'=>'30px','is_fixed'=>false,'sort_order'=>12,'parent_label'=>'KHĂN CÁC LOẠI'],
            ['label'=>'MẶT','width'=>'30px','is_fixed'=>false,'sort_order'=>13,'parent_label'=>'KHĂN CÁC LOẠI'],
            ['label'=>'TAY','width'=>'30px','is_fixed'=>false,'sort_order'=>14,'parent_label'=>'KHĂN CÁC LOẠI'],
            ['label'=>'THẢM','width'=>'30px','is_fixed'=>false,'sort_order'=>15,'parent_label'=>null],
            ['label'=>'KEM BỘT','width'=>'30px','is_fixed'=>false,'sort_order'=>16,'parent_label'=>null],
            ['label'=>'LƯỢC','width'=>'30px','is_fixed'=>false,'sort_order'=>17,'parent_label'=>null],
            ['label'=>'DAO CẠO RÂU','width'=>'32px','is_fixed'=>false,'sort_order'=>18,'parent_label'=>null],
            ['label'=>'TĂM BÔNG','width'=>'30px','is_fixed'=>false,'sort_order'=>19,'parent_label'=>null],
            ['label'=>'CHỤP TÓC','width'=>'30px','is_fixed'=>false,'sort_order'=>20,'parent_label'=>null],
            ['label'=>'XÀ PHÒNG','width'=>'30px','is_fixed'=>false,'sort_order'=>21,'parent_label'=>null],
            ['label'=>'DẦU GỘI','width'=>'30px','is_fixed'=>false,'sort_order'=>22,'parent_label'=>null],
            ['label'=>'SỮA TẮM','width'=>'30px','is_fixed'=>false,'sort_order'=>23,'parent_label'=>null],
            ['label'=>'DẦU XẢ','width'=>'30px','is_fixed'=>false,'sort_order'=>24,'parent_label'=>null],
            ['label'=>'GIẤY VS','width'=>'30px','is_fixed'=>false,'sort_order'=>25,'parent_label'=>null],
            ['label'=>'TRÀ','width'=>'28px','is_fixed'=>false,'sort_order'=>26,'parent_label'=>null],
            ['label'=>'CAFE','width'=>'28px','is_fixed'=>false,'sort_order'=>27,'parent_label'=>null],
            ['label'=>'SUỐI FREE','width'=>'30px','is_fixed'=>false,'sort_order'=>28,'parent_label'=>null],
            ['label'=>'KIÊNG','width'=>'34px','is_fixed'=>false,'sort_order'=>29,'parent_label'=>'ĐƯỜNG'],
            ['label'=>'TRẮNG','width'=>'34px','is_fixed'=>false,'sort_order'=>30,'parent_label'=>'ĐƯỜNG'],
            ['label'=>'GHI CHÚ','width'=>'60px','is_fixed'=>false,'sort_order'=>31,'parent_label'=>null],
        ];
        foreach ($ws as $col) { HkPrintCol::create(array_merge(['template'=>'worksheet'], $col)); }

        HkPrintCol::where('template', 'supervisor')->delete();
        $sv = [
            ['label'=>'PHÒNG','width'=>'','is_fixed'=>true,'sort_order'=>1],
            ['label'=>'LOẠI PHÒNG','width'=>'','is_fixed'=>true,'sort_order'=>2],
            ['label'=>'TÌNH TRẠNG','width'=>'','is_fixed'=>true,'sort_order'=>3],
            ['label'=>'GIỜ VÀO','width'=>'','is_fixed'=>false,'sort_order'=>4],
            ['label'=>'GIỜ RA','width'=>'','is_fixed'=>false,'sort_order'=>5],
            ['label'=>'KÝ TÊN','width'=>'','is_fixed'=>false,'sort_order'=>6],
            ['label'=>'GHI CHÚ','width'=>'160px','is_fixed'=>false,'sort_order'=>7],
            ['label'=>'MÙI PHÒNG','width'=>'','is_fixed'=>false,'sort_order'=>8],
            ['label'=>'ĐỔI TÌNH TRẠNG','width'=>'','is_fixed'=>false,'sort_order'=>9],
        ];
        foreach ($sv as $col) { HkPrintCol::create(array_merge(['template'=>'supervisor'], $col)); }
    }
}
