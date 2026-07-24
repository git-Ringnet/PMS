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
            $table->string('product_code')->nullable();
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
            $table->boolean('is_print_one_ticket')->default(false);
            $table->string('ticket_type')->nullable();
            $table->string('entrance_ip')->nullable();
            $table->integer('entrance_gate_ticket_type')->default(0);
            $table->integer('exchange_limit_hours')->default(0);
            $table->boolean('is_fixed_price')->default(false);
            $table->boolean('no_reinvest')->default(false);
            $table->boolean('is_contra')->default(false);
            $table->integer('processing_time')->default(0);
            $table->integer('serving_time')->default(0);
            $table->boolean('is_combo')->default(false);
            $table->boolean('is_get_price_from_items')->default(false);
            $table->boolean('is_check_combo')->default(false);
            $table->integer('combo_max_items')->nullable();
            $table->decimal('price', 15, 2)->default(0.00);
            $table->boolean('flexible_price')->default(false);
            $table->boolean('change_table')->default(false);
            $table->boolean('open_key')->default(false);
            $table->boolean('is_alcohol')->default(false);
            $table->boolean('track_stock')->default(false);
            $table->integer('is_in_stock')->default(1);
            $table->string('fb_printer_ids')->nullable();
            $table->decimal('original_amount', 15, 2)->nullable();
            $table->decimal('service_charge_percent', 5, 2)->default(0);
            $table->decimal('service_charge_amount', 15, 2)->nullable();
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->nullable();
            $table->decimal('special_tax_percent', 5, 2)->default(0);
            $table->decimal('special_tax_amount', 15, 2)->nullable();
            $table->string('image')->nullable();
            $table->text('note')->nullable();
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
