<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FbParty;
use App\Models\FbSubParty;
use App\Models\FbPartyItem;
use App\Models\FbProduct;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FbPartySampleSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('fb_party_payments')->delete();
        DB::table('fb_party_items')->delete();
        DB::table('fb_sub_parties')->delete();
        DB::table('fb_parties')->delete();

        $partyNames = [
            'Tiệc cưới', 'Sinh nhật', 'Tiệc tất niên', 'Tiệc tri ân khách hàng', 
            'Hội nghị khách hàng', 'Tiệc tân gia', 'Lễ kỷ niệm thành lập',
            'Tiệc Gala Dinner', 'Tiệc Buffet cuối năm', 'Họp mặt gia đình'
        ];

        $customerNames = [
            'Nguyễn Văn A', 'Trần Thị B', 'Lê Văn C', 'Phạm Thị D', 'Hoàng Văn E',
            'Đặng Thị F', 'Bùi Văn G', 'Đỗ Thị H', 'Ngô Văn I', 'Công ty ABC'
        ];

        $statuses = ['confirmed', 'serving', 'completed', 'cancelled'];
        
        $outlets = Outlet::all();
        if ($outlets->isEmpty()) {
            return; // No outlets to assign
        }

        $products = FbProduct::with('unit')->inRandomOrder()->limit(100)->get();
        if ($products->isEmpty()) {
            return;
        }

        for ($i = 1; $i <= 50; $i++) {
            $baseDate = Carbon::now()->addDays(rand(-10, 30));
            if ($baseDate->isFuture()) {
                $futureStatuses = ['confirmed', 'cancelled'];
                $partyStatus = $futureStatuses[array_rand($futureStatuses)];
            } else {
                $partyStatus = $statuses[array_rand($statuses)];
            }
            $party = FbParty::create([
                'party_code' => 'PTY' . date('Ym') . str_pad($i, 4, '0', STR_PAD_LEFT),
                'party_name' => $partyNames[array_rand($partyNames)] . ' ' . $customerNames[array_rand($customerNames)],
                'arrival_date' => $baseDate->format('Y-m-d'),
                'confirmation_type' => rand(1, 10) > 2 ? 'confirmed' : 'tentative',
                'confirmation_date' => $baseDate->copy()->subDays(rand(1, 30))->format('Y-m-d'),
                'sale_staff' => 'NV' . rand(100, 999),
                'customer' => $customerNames[array_rand($customerNames)],
                'email' => 'customer' . $i . '@example.com',
                'note' => 'Yêu cầu trang trí hoa tươi',
                'status' => $partyStatus,
            ]);

            $numSubParties = rand(2, 5);
            for ($j = 1; $j <= $numSubParties; $j++) {
                $arrivalTime = str_pad(rand(8, 20), 2, '0', STR_PAD_LEFT) . ':00:00';
                $departureTime = str_pad(rand(21, 23), 2, '0', STR_PAD_LEFT) . ':00:00';
                
                $subStatus = $partyStatus;
                // If party is completed, sub parties must be completed
                // If party is serving, sub party could be confirmed, serving or completed
                if ($partyStatus === 'serving') {
                    $subStatuses = ['confirmed', 'serving', 'completed'];
                    $subStatus = $subStatuses[array_rand($subStatuses)];
                }

                $outlet = $outlets->random();

                $subParty = FbSubParty::create([
                    'party_id' => $party->id,
                    'booking_code' => $party->party_code . '-' . $j,
                    'arrival_date' => $party->arrival_date,
                    'arrival_time' => $arrivalTime,
                    'departure_time' => $departureTime,
                    'adults' => rand(10, 100),
                    'children' => rand(0, 20),
                    'tables' => rand(2, 15),
                    'extra' => rand(0, 2),
                    'outlet' => (string)$outlet->id,
                    'location' => 'Sảnh ' . rand(1, 5),
                    'party_type' => 'Tiệc bàn',
                    'note' => 'Tiệc con ' . $j,
                    'status' => $subStatus,
                ]);

                // Add 3 to 8 random items to this sub party
                $numItems = rand(3, 8);
                $selectedProducts = $products->random($numItems);

                foreach ($selectedProducts as $product) {
                    FbPartyItem::create([
                        'sub_party_id' => $subParty->id,
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'quantity' => rand(1, 15),
                        'unit' => $product->unit ? $product->unit->name : 'Phần',
                        'price' => $product->price,
                        'note' => rand(1, 10) > 8 ? 'Không cay' : null,
                    ]);
                }
            }
            
            // Re-evaluate party status just in case (to keep consistency)
            if ($partyStatus === 'serving' || $partyStatus === 'confirmed') {
                $allCompleted = $party->subParties()->where('status', '!=', 'completed')->count() === 0;
                if ($allCompleted && $party->subParties()->count() > 0) {
                    $party->update(['status' => 'completed']);
                }
            }
        }
    }
}
