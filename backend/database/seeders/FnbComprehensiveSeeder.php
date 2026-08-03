<?php

namespace Database\Seeders;

use App\Models\FbCombo;
use App\Models\FbLocation;
use App\Models\FbOrder;
use App\Models\FbOrderItem;
use App\Models\FbParty;
use App\Models\FbPartyItem;
use App\Models\FbPartyPayment;
use App\Models\FbPrintLog;
use App\Models\FbPrinter;
use App\Models\FbProduct;
use App\Models\FbProductCategory;
use App\Models\FbProductOutlet;
use App\Models\FbPromotion;
use App\Models\FbPromotionProduct;
use App\Models\FbSubParty;
use App\Models\FbTable;
use App\Models\Outlet;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FnbComprehensiveSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->clear();
            $outlets = $this->outlets();
            $locations = $this->locations($outlets);
            $tables = $this->tables($locations);
            $products = $this->products($outlets);
            $printers = $this->printers($outlets);
            $this->assignPrinters($products, $printers);
            $promotions = $this->promotions($outlets, $products);
            $orders = $this->orders($tables, $products, $promotions);
            $this->logs($orders, $printers);
            $this->parties($outlets, $products);
        });
    }

    private function clear(): void
    {
        foreach (['fb_print_logs','fb_order_items','fb_orders','fb_party_payments','fb_party_items','fb_sub_parties','fb_parties','fb_promotion_products','fb_promotions','fb_combos','fb_product_outlets','fb_products','fb_product_categories','fb_printers','fb_tables','fb_locations'] as $table) DB::table($table)->delete();
    }

    private function outlets(): array
    {
        return [
            Outlet::updateOrCreate(['code' => 'GAL1'], ['name' => 'Nhà hàng GAL1', 'department_code' => 'FB', 'service_code' => 'FB', 'is_active' => true, 'order_index' => 1, 'check_voucher' => true, 'check_combo' => true]),
            Outlet::updateOrCreate(['code' => 'GAL2'], ['name' => 'Nhà hàng GAL2', 'department_code' => 'FB', 'service_code' => 'FB', 'is_active' => true, 'order_index' => 2, 'check_combo' => true]),
            Outlet::updateOrCreate(['code' => 'GAL3'], ['name' => 'Nhà hàng GAL3', 'department_code' => 'FB', 'service_code' => 'FB', 'is_active' => true, 'order_index' => 3, 'check_voucher' => true, 'check_combo' => true]),
            Outlet::updateOrCreate(['code' => 'GAL4'], ['name' => 'Nhà hàng GAL4', 'department_code' => 'FB', 'service_code' => 'FB', 'is_active' => true, 'order_index' => 4, 'check_combo' => true]),
        ];
    }

    private function locations(array $outlets): array
    {
        $result = [];
        foreach ($outlets as $outlet) foreach ([['A','Sảnh chính','#4F86C6'],['V','Phòng VIP','#D97706']] as [$code,$name,$color]) $result[] = FbLocation::create(['id' => $outlet->code.$code, 'name' => $name, 'outlet_code' => $outlet->code, 'color' => $color, 'letter' => $code, 'is_active' => true]);
        return $result;
    }

    private function tables(array $locations): array
    {
        $result = [];
        foreach ($locations as $location) foreach (range(1, 4) as $number) $result[] = FbTable::create(['table_code' => $location->id.$number, 'name' => 'Bàn '.$number, 'location_id' => $location->id, 'row_index' => (int)ceil($number / 2), 'col_index' => (($number - 1) % 2) + 1, 'max_seats' => $number === 4 ? 10 : 4, 'status' => 'Active', 'is_active' => true]);
        $result[3]->update(['status' => 'Inactive', 'is_active' => false]);
        return $result;
    }

    private function products(array $outlets): array
    {
        $unit = UnitOfMeasure::firstOrCreate(['code' => 'PHAN'], ['name' => 'Phần']);
        $drinkUnit = UnitOfMeasure::firstOrCreate(['code' => 'LY'], ['name' => 'Ly']);
        $food = FbProductCategory::create(['code' => 'SEED_FOOD', 'name' => 'Món ăn', 'order_index' => 1]);
        $drink = FbProductCategory::create(['code' => 'SEED_DRINK', 'name' => 'Đồ uống', 'order_index' => 2]);
        $comboCat = FbProductCategory::create(['code' => 'SEED_COMBO', 'name' => 'Combo', 'order_index' => 3]);
        $subCategories = [];
        foreach (['Khai vị','Món chính','Hải sản','Món chay','Lẩu','Tráng miệng'] as $index => $name) $subCategories[] = FbProductCategory::create(['code' => 'SEED_F'.$index, 'name' => $name, 'parent_id' => $food->id, 'order_index' => 10 + $index]);
        foreach (['Bia rượu','Nước ngọt','Nước ép','Cà phê','Trà'] as $index => $name) $subCategories[] = FbProductCategory::create(['code' => 'SEED_D'.$index, 'name' => $name, 'parent_id' => $drink->id, 'order_index' => 20 + $index]);
        $defs = [
            ['FOOD001','Phở bò đặc biệt',$food,$unit,120000,false,true,1], ['FOOD002','Cơm chiên hải sản',$food,$unit,180000,false,true,1],
            ['DRINK001','Bia Tiger',$drink,$drinkUnit,60000,true,true,1], ['DRINK002','Nước suối',$drink,$drinkUnit,30000,false,true,1],
            ['FOOD999','Món tạm ngưng bán',$food,$unit,90000,false,false,0],
        ];
        $result = [];
        foreach ($defs as [$code,$name,$category,$productUnit,$price,$alcohol,$active,$stock]) $result[$code] = FbProduct::create(['fb_product_category_id'=>$category->id,'name'=>$name,'product_code'=>$code,'unit_id'=>$productUnit->id,'service_group'=>$category->code==='SEED_DRINK'?'Đồ uống':'Ăn uống','price'=>$price,'original_amount'=>$price*.6,'tax_percent'=>8,'service_charge_percent'=>5,'is_print'=>true,'is_alcohol'=>$alcohol,'is_active'=>$active,'is_in_stock'=>$stock,'track_stock'=>true]);
        $names = ['Gà nướng mật ong','Bò lúc lắc','Cá hấp Hồng Kông','Tôm rang me','Mực chiên giòn','Lẩu Thái hải sản','Salad Đà Lạt','Chả giò rế','Cơm chiên Dương Châu','Mì xào bò','Chè khúc bạch','Bánh flan','Bia Heineken','Pepsi','Nước cam ép','Cà phê sữa','Trà đào cam sả'];
        foreach (range(1, 240) as $number) {
            $category = $subCategories[($number - 1) % count($subCategories)];
            $isDrink = str_starts_with($category->code, 'SEED_D');
            $price = ($isDrink ? 25 : 90) * 1000 + (($number % 12) * 5000);
            $code = 'AUTO'.str_pad($number, 4, '0', STR_PAD_LEFT);
            $result[$code] = FbProduct::create(['fb_product_category_id'=>$category->id,'name'=>$names[($number - 1) % count($names)].' '.$number,'product_code'=>$code,'unit_id'=>($isDrink ? $drinkUnit : $unit)->id,'service_group'=>$isDrink?'Đồ uống':'Ăn uống','price'=>$price,'original_amount'=>$price*.6,'tax_percent'=>8,'service_charge_percent'=>5,'is_print'=>true,'is_alcohol'=>$isDrink && $number % 7 === 0,'is_active'=>$number % 23 !== 0,'is_in_stock'=>$number % 29 === 0 ? 0 : 1,'track_stock'=>true,'note'=>$number % 17 === 0 ? 'Món theo mùa' : null]);
        }
        $result['COMBO001'] = FbProduct::create(['fb_product_category_id'=>$comboCat->id,'name'=>'Combo gia đình','product_code'=>'COMBO001','unit_id'=>$unit->id,'service_group'=>'Dịch vụ khác','price'=>300000,'tax_percent'=>8,'is_combo'=>true,'is_active'=>true]);
        FbCombo::create(['parent_id'=>$result['COMBO001']->id,'child_id'=>$result['FOOD001']->id,'quantity'=>1,'price'=>120000]);
        FbCombo::create(['parent_id'=>$result['COMBO001']->id,'child_id'=>$result['DRINK002']->id,'quantity'=>2,'price'=>60000]);
        foreach (range(1, 20) as $number) {
            $combo = FbProduct::create(['fb_product_category_id'=>$comboCat->id,'name'=>'Combo nhóm '.$number,'product_code'=>'COMBO'.str_pad($number + 1, 3, '0', STR_PAD_LEFT),'unit_id'=>$unit->id,'service_group'=>'Dịch vụ khác','price'=>350000 + ($number * 10000),'tax_percent'=>8,'is_combo'=>true,'is_active'=>true]);
            $result[$combo->product_code] = $combo;
            $foodProduct = $result['AUTO'.str_pad($number, 4, '0', STR_PAD_LEFT)];
            $drinkProduct = $result['AUTO'.str_pad($number + 40, 4, '0', STR_PAD_LEFT)];
            FbCombo::create(['parent_id'=>$combo->id,'child_id'=>$foodProduct->id,'quantity'=>1,'price'=>$foodProduct->price]);
            FbCombo::create(['parent_id'=>$combo->id,'child_id'=>$drinkProduct->id,'quantity'=>2,'price'=>$drinkProduct->price]);
        }
        foreach ($result as $product) foreach ($outlets as $outlet) FbProductOutlet::create(['fb_product_id'=>$product->id,'outlet_id'=>$outlet->id,'is_active'=>$product->is_active,'price'=>$product->price,'original_amount'=>$product->original_amount ?? $product->price,'tax_percent'=>$product->tax_percent ?? 0,'service_charge_percent'=>$product->service_charge_percent ?? 0,'combo_price'=>$product->is_combo?$product->price:null,'selectedCounterOutlets'=>[]]);
        return $result;
    }

    private function printers(array $outlets): array
    {
        $result = [];
        foreach ($outlets as $outlet) foreach ([1=>'Bếp',2=>'Quầy bar',3=>'Thu ngân'] as $type=>$name) $result[] = FbPrinter::create(['outlet_id'=>$outlet->id,'name'=>$name.' '.$outlet->code,'type'=>$type,'num_of_prints'=>1,'driver_name'=>'Generic POS Printer']);
        return $result;
    }

    private function assignPrinters(array $products, array $printers): void
    {
        foreach ($products as $product) $product->update(['fb_printer_ids'=>collect($printers)->where('type', $product->is_alcohol || $product->service_group==='Đồ uống' ? 2 : 1)->pluck('id')->values()->all()]);
    }

    private function promotions(array $outlets, array $products): array
    {
        $all = FbPromotion::create(['name'=>'Giảm 10% khai trương','outlet_id'=>$outlets[0]->id,'discount_percent'=>10,'is_auto_apply'=>true,'is_all_product'=>true,'start_date'=>now()->subDay(),'end_date'=>now()->addDays(30),'is_active'=>true]);
        $happy = FbPromotion::create(['name'=>'Happy Hour đồ uống','discount_percent'=>20,'is_auto_apply'=>true,'is_all_product'=>false,'apply_by_time'=>true,'start_time'=>'16:00','end_time'=>'19:00','start_date'=>now()->subDay(),'end_date'=>now()->addDays(30),'is_active'=>true]);
        foreach (['DRINK001','DRINK002'] as $code) FbPromotionProduct::create(['fb_promotion_id'=>$happy->id,'fb_product_id'=>$products[$code]->id]);
        $expired = FbPromotion::create(['name'=>'Khuyến mãi đã hết hạn','discount_amount'=>50000,'is_all_product'=>true,'start_date'=>now()->subDays(30),'end_date'=>now()->subDay(),'is_active'=>false]);
        return [$all,$happy,$expired];
    }

    private function orders(array $tables, array $products, array $promotions): array
    {
        $result = [];
        foreach ([['serving',1,null],['waiting',2,$promotions[1]->id],['paid',3,$promotions[0]->id],['cancelled',4,null]] as $index => [$status,$tableNumber,$promotionId]) {
            $table = $tables[$tableNumber-1];
            $order = FbOrder::create(['outlet_code'=>$table->location->outlet_code,'table_id'=>$table->id,'name'=>'Bill '.($index+1),'status'=>$status,'customer_name'=>'Khách F&B '.($index+1),'guest_count'=>$index+2,'promotion_id'=>$promotionId,'public_note'=>$status==='cancelled'?'Khách huỷ món':null,'total_amount'=>0]);
            $total = 0;
            foreach ([['FOOD001',1],['DRINK001',2]] as [$code,$quantity]) { $discount = $status==='paid' ? 10000 : 0; FbOrderItem::create(['order_id'=>$order->id,'product_id'=>$products[$code]->id,'product_name'=>$products[$code]->name,'quantity'=>$quantity,'price'=>$products[$code]->price,'discount'=>$discount,'note'=>$code==='DRINK001'?'Ít đá':null]); $total += $products[$code]->price*$quantity-$discount; }
            $order->update(['total_amount'=>$total]); $result[] = $order;
        }
        return $result;
    }

    private function logs(array $orders, array $printers): void
    {
        foreach ($orders as $index => $order) FbPrintLog::create(['order_id'=>$order->id,'corder_code'=>'ORD-'.str_pad($order->id,5,'0',STR_PAD_LEFT),'printer_name'=>$printers[$index % count($printers)]->name,'printer_type'=>$printers[$index % count($printers)]->type,'is_printed'=>$order->status!=='cancelled','html'=>'<div>F&B order '.$order->id.'</div>','printed_at'=>$order->status!=='cancelled'?now():null]);
    }

    private function parties(array $outlets, array $products): void
    {
        foreach (['confirmed','serving','completed','cancelled'] as $index => $status) {
            $party = FbParty::create(['party_code'=>'SEED-PTY-'.($index+1),'party_name'=>'Tiệc mẫu '.$status,'arrival_date'=>now()->addDays($status==='confirmed'?3:-1),'confirmation_type'=>'byDate','confirmation_date'=>now()->toDateString(),'company'=>'Khách lẻ','customer'=>'Khách F&B','email'=>'fnb'.($index+1).'@example.com','status'=>$status]);
            $sub = FbSubParty::create(['party_id'=>$party->id,'booking_code'=>$party->party_code.'-01','arrival_date'=>$party->arrival_date,'arrival_time'=>'18:00:00','departure_time'=>'21:00:00','adults'=>10+$index,'children'=>$index,'tables'=>2,'outlet'=>$outlets[0]->code,'location'=>'Sảnh chính','party_type'=>'Tiệc bàn','status'=>$status]);
            foreach (['FOOD001','DRINK002'] as $code) FbPartyItem::create(['sub_party_id'=>$sub->id,'product_id'=>$products[$code]->id,'name'=>$products[$code]->name,'quantity'=>2,'unit'=>$products[$code]->unit->name,'price'=>$products[$code]->price,'discount'=>$status==='completed'?5000:0]);
            if ($status==='completed') { FbPartyPayment::create(['party_id'=>$party->id,'sub_party_id'=>$sub->id,'payment_date'=>now()->toDateString(),'payment_method'=>'cash','amount'=>300000,'note'=>'Đã thu đủ','status'=>'active']); FbPartyPayment::create(['party_id'=>$party->id,'sub_party_id'=>$sub->id,'payment_date'=>now()->toDateString(),'payment_method'=>'transfer','amount'=>50000,'note'=>'Thanh toán bị huỷ mẫu','status'=>'cancelled']); }
        }
    }
}
