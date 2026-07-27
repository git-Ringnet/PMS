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
        Schema::create('outlets', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('department_code')->nullable();
            $table->string('service_code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('order_index')->nullable();
            $table->boolean('check_voucher')->default(false);
            $table->boolean('check_combo')->default(false);
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('payment_content')->nullable()->default('Thanh toan don hang [BillCode]');
            $table->string('connector')->nullable();
            $table->integer('vat_config_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outlets');
    }
};
