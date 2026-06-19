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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('currency', 20)->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->string('note')->nullable();
            $table->boolean('change_table')->default(false);
            $table->boolean('open_key')->default(false);
            $table->boolean('is_alcohol')->default(false);
            $table->string('goods', 50)->nullable();
            $table->string('image')->nullable();
            $table->integer('is_in_stock')->nullable();
            $table->float('service_charge_percent')->default(0);
            $table->float('tax_percent')->default(0);
            $table->float('special_tax_percent')->default(0);
            $table->decimal('original_amount', 15, 2)->nullable();
            $table->decimal('service_charge_amount', 15, 2)->nullable();
            $table->decimal('tax_amount', 15, 2)->nullable();
            $table->decimal('special_tax_amount', 15, 2)->nullable();
            $table->string('inventory_id', 50)->nullable();
            $table->boolean('flexible_price')->default(false);
            $table->uuid('misa_id')->nullable();
            $table->string('product_code')->nullable();
            $table->string('debit_account', 20)->nullable();
            $table->string('credit_account', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('track_stock')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
