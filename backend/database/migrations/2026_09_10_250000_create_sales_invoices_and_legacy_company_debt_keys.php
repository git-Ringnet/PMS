<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_invoices', function (Blueprint $table) {
            $table->id();
            // SP3003.Ma. Nullable so existing installations are not backfilled.
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->string('bill_id', 10)->nullable();
            $table->dateTime('invoice_date')->nullable();
            $table->string('room', 10)->nullable();
            // Raw SP3003 identifiers are kept without unverified foreign keys.
            $table->string('legacy_rental_room_id', 20)->nullable();
            $table->unsignedBigInteger('legacy_booking_id')->nullable();
            $table->unsignedBigInteger('legacy_payment_id')->nullable();
            $table->string('outlet', 10)->nullable();
            $table->string('username', 50)->nullable();
            $table->unsignedTinyInteger('status')->nullable();
            $table->decimal('amount', 20, 6)->nullable();
            $table->string('currency', 3)->nullable();
            $table->timestamps();

            $table->index('legacy_rental_room_id');
            $table->index('legacy_booking_id');
            $table->index('legacy_payment_id');
            $table->index('invoice_date');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            // SP3002.PaymentTotalAmount0 is the AC amount used by sp_212.
            $table->decimal('legacy_payment_total_amount0', 20, 6)->nullable();
            $table->string('legacy_payment_currency0', 3)->nullable();
            $table->string('legacy_pack5', 50)->nullable();
        });

        Schema::table('payment_debt_settlements', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            // SP3007.PaymentTableId is preserved as a raw key until payment mapping is verified.
            $table->unsignedBigInteger('legacy_payment_table_id')->nullable()->index();
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            // SP1302.ReCreditLimit is retained separately; max_debt equivalence is unresolved.
            $table->decimal('legacy_re_credit_limit', 20, 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropUnique('companies_legacy_id_unique');
            $table->dropColumn(['legacy_id', 'legacy_re_credit_limit']);
        });

        Schema::table('payment_debt_settlements', function (Blueprint $table) {
            $table->dropUnique('payment_debt_settlements_legacy_id_unique');
            $table->dropColumn(['legacy_id', 'legacy_payment_table_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_legacy_id_unique');
            $table->dropColumn([
                'legacy_id',
                'legacy_payment_total_amount0',
                'legacy_payment_currency0',
                'legacy_pack5',
            ]);
        });

        Schema::dropIfExists('sales_invoices');
    }
};
