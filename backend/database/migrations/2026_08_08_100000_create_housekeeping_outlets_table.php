<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('housekeeping_outlets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('group_key', 30)->unique();
            $table->string('service_code', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();
        });

        DB::table('housekeeping_outlets')->insert([
            ['code' => 'MB', 'name' => 'Minibar', 'group_key' => 'minibar', 'service_code' => 'MB', 'is_active' => true, 'order_index' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'LA', 'name' => 'Giặt ủi', 'group_key' => 'giatui', 'service_code' => 'LA', 'is_active' => true, 'order_index' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'BR', 'name' => 'Hàng đền bù', 'group_key' => 'dengbu', 'service_code' => 'BR', 'is_active' => true, 'order_index' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'AM', 'name' => 'Amenity', 'group_key' => 'amenity', 'service_code' => 'AM', 'is_active' => true, 'order_index' => 4, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('housekeeping_outlets');
    }
};
