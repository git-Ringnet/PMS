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
        // 1. Payment Methods
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('account')->nullable();
            $table->string('account_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->decimal('service_charge', 8, 2)->default(0);
            $table->string('department')->nullable();
            $table->boolean('is_free')->default(false);
            $table->boolean('is_inactive')->default(false);
            $table->timestamps();
        });

        // 2. Currencies
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('country');
            $table->string('short_name')->nullable();
            $table->integer('decimals_to_round')->default(0);
            $table->boolean('is_main')->default(false);
            $table->boolean('is_active')->default(true);
            $table->decimal('exchange_rate', 18, 4)->default(1.0000);
            $table->string('image_path')->nullable();
            $table->timestamps();
        });

        // 3. Units of Measure
        Schema::create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('symbol')->nullable();
            $table->boolean('is_inactive')->default(false);
            $table->timestamps();
        });

        // 4. Room Rate Codes
        Schema::create('room_rate_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('description')->nullable();
            $table->foreignId('room_class_id')->nullable()->constrained('room_classes')->onDelete('cascade');
            $table->foreignId('room_form_id')->nullable()->constrained('room_forms')->onDelete('cascade');
            $table->integer('adults')->default(0);
            $table->integer('children')->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('price', 18, 2)->default(0);
            $table->decimal('breakfast_price', 18, 2)->default(0);
            $table->decimal('extra_bed_price', 18, 2)->default(0);
            $table->boolean('has_breakfast')->default(false);
            $table->boolean('is_allowed')->default(true);
            $table->string('rate_type')->default('FIT');
            $table->timestamps();
        });

        // 5. Registration Statuses
        Schema::create('registration_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color')->default('#ffffff');
            $table->integer('confirmation_days')->default(0);
            $table->string('description')->nullable();
            $table->string('status_value')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->boolean('is_availability')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registration_statuses');
        Schema::dropIfExists('room_rate_codes');
        Schema::dropIfExists('units_of_measure');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('payment_methods');
    }
};
