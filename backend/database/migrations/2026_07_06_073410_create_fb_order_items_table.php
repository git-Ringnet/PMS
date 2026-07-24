<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fb_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('fb_orders')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('fb_products');
            $table->unsignedBigInteger('parent_item_id')->nullable();
            $table->string('product_name');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('surcharge', 15, 2)->default(0);
            $table->decimal('base_discount', 15, 2)->default(0);
            $table->decimal('base_surcharge', 15, 2)->default(0);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->foreign('parent_item_id')->references('id')->on('fb_order_items')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fb_order_items');
    }
};
