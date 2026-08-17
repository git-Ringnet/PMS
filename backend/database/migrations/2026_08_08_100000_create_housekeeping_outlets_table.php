<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('housekeeping_outlets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('service_code', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('show_in_add_service')->default(true);
            $table->decimal('default_service_charge_percent', 5, 2)->default(0);
            $table->decimal('default_tax_percent', 5, 2)->default(0);
            $table->decimal('default_special_tax_percent', 5, 2)->default(0);
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();
        });

        DB::table('housekeeping_outlets')->insert([
            ['code' => 'MB', 'name' => 'Minibar', 'service_code' => 'MB', 'is_active' => true, 'show_in_add_service' => true, 'order_index' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'LA', 'name' => 'Giặt ủi', 'service_code' => 'LA', 'is_active' => true, 'show_in_add_service' => true, 'order_index' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'BR', 'name' => 'Hàng đền bù', 'service_code' => 'BR', 'is_active' => true, 'show_in_add_service' => true, 'order_index' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'AM', 'name' => 'Amenity', 'service_code' => 'AM', 'is_active' => true, 'show_in_add_service' => true, 'order_index' => 4, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $legacyOutletMap = [
            'minibar' => 'MB',
            'giatui' => 'LA',
            'dengbu' => 'BR',
            'amenity' => 'AM',
        ];

        foreach ($legacyOutletMap as $legacyValue => $outletCode) {
            DB::table('product_categories')
                ->whereRaw('LOWER(outlet) = ?', [$legacyValue])
                ->update(['outlet' => $outletCode]);
        }

        DB::table('housekeeping_outlets')->get(['code', 'name'])->each(function ($outlet) {
            DB::table('product_categories')
                ->where('outlet', $outlet->name)
                ->update(['outlet' => $outlet->code]);
        });

        $outletValues = DB::table('housekeeping_outlets')
            ->get(['code', 'name'])
            ->flatMap(fn($outlet) => [$outlet->code, $outlet->name])
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (Schema::hasColumn('products', 'open_key') && $outletValues) {
            DB::table('products')
                ->whereIn('product_category_id', function ($query) use ($outletValues) {
                    $query->select('id')
                        ->from('product_categories')
                        ->whereIn('outlet', $outletValues);
                })
                ->update(['open_key' => true]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('housekeeping_outlets');
    }
};
