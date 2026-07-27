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
        Schema::create('fb_product_outlets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fb_product_id');
            $table->unsignedBigInteger('outlet_id');
            $table->boolean('is_active')->default(true);
            $table->decimal('original_amount', 15, 2)->nullable();
            $table->decimal('service_charge_percent', 5, 2)->default(5.00);
            $table->decimal('service_charge_amount', 15, 2)->nullable();
            $table->decimal('special_tax_percent', 5, 2)->default(0.00);
            $table->decimal('special_tax_amount', 15, 2)->nullable();
            $table->decimal('tax_percent', 5, 2)->default(8.00);
            $table->decimal('tax_amount', 15, 2)->nullable();
            $table->decimal('price', 15, 2)->default(0.00);
            $table->decimal('combo_original', 15, 2)->nullable();
            $table->decimal('combo_service', 5, 2)->default(5.00);
            $table->decimal('combo_special', 5, 2)->default(0.00);
            $table->decimal('combo_tax', 5, 2)->default(8.00);
            $table->decimal('combo_price', 15, 2)->nullable();
            $table->boolean('update_price')->default(false);
            $table->boolean('update_combo_price')->default(false);
            $table->boolean('is_expanded')->default(false);
            $table->json('selectedCounterOutlets')->nullable();
            $table->timestamps();

            $table->foreign('fb_product_id')->references('id')->on('fb_products')->onDelete('cascade');
            $table->foreign('outlet_id')->references('id')->on('outlets')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fb_product_outlets');
    }
};
