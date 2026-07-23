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
            $table->integer('payment_group')->nullable()->comment('1: TIỀN MẶT, 2: THẺ CK, 3: VOUCHER, 4: CÔNG NỢ, 5: MIỄN PHÍ');
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

        // 4. Room Rate Codes (Mapped from SP1340)
        Schema::create('room_rate_codes', function (Blueprint $table) {
            $table->string('Ma', 20)->primary();
            $table->string('Description', 150)->nullable();
            $table->dateTime('CreateDate')->nullable();
            $table->date('BeginDate')->nullable();
            $table->date('EndDate')->nullable();
            $table->boolean('IncludeBF')->default(false);
            $table->string('Currency', 5)->nullable();
            $table->string('PromotionCode', 20)->nullable();
            $table->integer('SourceCode')->nullable();
            $table->integer('MarketSegment')->nullable();
            $table->string('Type', 10)->nullable();
            $table->text('Value')->nullable();
            $table->boolean('Disable')->default(false);
            $table->boolean('AllowChangeRate')->default(false);
            $table->boolean('IsChannelManager')->default(false);
            $table->boolean('IsDaily')->default(false);
        });

        // 4.1 Room Rate Plans (Mapped from SP1341)
        Schema::create('room_rate_plans', function (Blueprint $table) {
            $table->string('RateCode', 20);
            $table->string('Code', 20);
            $table->string('Description', 150)->nullable();
            $table->date('BeginDate')->nullable();
            $table->date('EndDate')->nullable();
            $table->longText('Period')->nullable(); // Store JSON matrix here

            $table->primary(['RateCode', 'Code']);
            $table->foreign('RateCode')->references('Ma')->on('room_rate_codes')->onDelete('cascade');
        });

        // 4.2 Room Rate Daily Mappings (Mapped from SP1342)
        Schema::create('room_rate_daily_mappings', function (Blueprint $table) {
            $table->string('RateCode', 20);
            $table->date('Date');
            $table->string('Code', 20);

            $table->primary(['RateCode', 'Date']);
            $table->foreign('RateCode')->references('Ma')->on('room_rate_codes')->onDelete('cascade');
        });

        // 5. Registration Statuses (Bảng SP1311 - TinhTrangDangKy)
        Schema::create('registration_statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('booking_status_id')->nullable()->comment('Mã ID trạng thái SP1311: 1, 20, 24, 25, 26, 27, 28, 29');
            $table->string('name');
            $table->string('color')->default('#ffffff');
            $table->integer('cut_off_day')->default(0);
            $table->string('description')->nullable();
            $table->string('status_value')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->boolean('is_availability')->default(true);
            $table->unsignedTinyInteger('bk_definite')->nullable()->comment('4 = trạng thái tự chuyển khi hủy booking. null = không tự chuyển.');
            $table->string('vietnamese')->nullable();
            $table->string('english')->nullable();
            $table->integer('order_index')->nullable();
            $table->timestamps();
        });

        // Update existing rate codes that already have daily mappings to IsDaily = true
        DB::table('room_rate_codes')
            ->whereIn('Ma', function ($query) {
                $query->select('RateCode')
                    ->from('room_rate_daily_mappings')
                    ->distinct();
            })
            ->update(['IsDaily' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registration_statuses');
        Schema::dropIfExists('room_rate_daily_mappings');
        Schema::dropIfExists('room_rate_plans');
        Schema::dropIfExists('room_rate_codes');
        Schema::dropIfExists('units_of_measure');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('payment_methods');
    }
};
