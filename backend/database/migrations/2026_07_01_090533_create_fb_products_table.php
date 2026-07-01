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
        Schema::create('fb_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fb_product_category_id');
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('short_name')->nullable();
            $table->string('service_group')->nullable();
            $table->string('vat_billing_name')->nullable();
            $table->unsignedBigInteger('unit_id');
            $table->string('barcode')->nullable();
            $table->boolean('is_print')->default(false);
            $table->boolean('is_gate_ticket')->default(false);
            $table->boolean('is_dish_exchange')->default(false);
            $table->boolean('is_pre_printed')->default(false);
            $table->boolean('no_reinvest')->default(false);
            $table->boolean('is_contra')->default(false);
            $table->integer('processing_time')->default(0);
            $table->integer('serving_time')->default(0);
            $table->boolean('is_combo')->default(false);
            $table->decimal('price', 15, 2)->default(0.00);
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('fb_product_category_id')->references('id')->on('fb_product_categories')->onDelete('cascade');
            $table->foreign('unit_id')->references('id')->on('units_of_measure')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fb_products');
    }
};
