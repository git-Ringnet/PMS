<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FbPrinter;
use App\Models\FbProduct;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;

class FbPrinterSampleSeeder extends Seeder
{
    public function run(): void
    {
        // Xóa máy in cũ
        DB::table('fb_printers')->delete();

        $outlets = Outlet::all();
        if ($outlets->isEmpty()) {
            return;
        }

        $allPrinters = [];

        // Tạo máy in cho từng điểm bán
        foreach ($outlets as $outlet) {
            $kitchenPrinter = FbPrinter::create([
                'outlet_id' => $outlet->id,
                'name' => 'Máy in Bếp',
                'type' => 1,
                'num_of_prints' => 2,
                'driver_name' => 'EPSON TM-T88VI',
            ]);

            $barPrinter = FbPrinter::create([
                'outlet_id' => $outlet->id,
                'name' => 'Máy in Quầy Pha Chế',
                'type' => 2,
                'num_of_prints' => 1,
                'driver_name' => 'Xprinter XP-Q80',
            ]);

            $cashierPrinter = FbPrinter::create([
                'outlet_id' => $outlet->id,
                'name' => 'Máy in Thu Ngân',
                'type' => 3,
                'num_of_prints' => 1,
                'driver_name' => 'Citizen CT-S310II',
            ]);

            $allPrinters[$outlet->id] = [
                'KITCHEN' => $kitchenPrinter,
                'BAR' => $barPrinter,
                'CASHIER' => $cashierPrinter,
            ];
        }

        // Cập nhật fb_printer_ids cho các món ăn để tự động in
        $products = FbProduct::all();
        
        // Lấy tất cả printer ID vào một mảng phẳng để gắn ngẫu nhiên nếu cần
        // Hoặc gắn theo loại sản phẩm
        $kitchenPrinterIds = FbPrinter::where('type', 1)->pluck('id')->toArray();
        $barPrinterIds = FbPrinter::where('type', 2)->pluck('id')->toArray();

        foreach ($products as $product) {
            $printerIds = [];
            
            // Nếu là đồ uống (đặc biệt có cồn), in ra quầy pha chế
            if ($product->is_alcohol || strpos(strtolower($product->name), 'nước') !== false || strpos(strtolower($product->name), 'bia') !== false) {
                // Chọn ngẫu nhiên 1-2 máy in bar
                if (!empty($barPrinterIds)) {
                    $printerIds[] = $barPrinterIds[array_rand($barPrinterIds)];
                }
            } 
            // Nếu là đồ ăn, in ra bếp
            else if ($product->service_group === 'Ăn uống') {
                if (!empty($kitchenPrinterIds)) {
                    $printerIds[] = $kitchenPrinterIds[array_rand($kitchenPrinterIds)];
                }
            }

            if (!empty($printerIds)) {
                $product->update([
                    'fb_printer_ids' => $printerIds
                ]);
            }
        }
    }
}
