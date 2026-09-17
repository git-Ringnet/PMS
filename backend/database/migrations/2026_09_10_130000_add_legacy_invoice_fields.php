<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_bills', function (Blueprint $table) {
            $table->string('RefId', 10)->nullable();
            $table->integer('NotPrint')->nullable();
            $table->string('VATNumber', 40)->nullable();
            $table->string('Serial', 20)->nullable();
            $table->string('InvoiceNumber', 20)->nullable();
            $table->string('DebitAccount', 20)->nullable();
            $table->string('CreditAccount', 20)->nullable();
            $table->string('RevenueAccount', 20)->nullable();
            $table->string('CostAccount', 20)->nullable();
            $table->float('BillExchangeRate')->nullable();
            $table->float('BillExchangeAmount')->nullable();
            $table->bigInteger('ParentBillId')->nullable();
            $table->string('UpdatedUser', 10)->nullable();
            $table->string('UpdatedHour', 5)->nullable();
            $table->string('OwnerUser', 10)->nullable();
            $table->string('RootOwnerUser', 10)->nullable();
            $table->decimal('ExchangeRate1', 15, 2)->nullable();
            $table->decimal('ExchangeRate2', 15, 2)->nullable();
            $table->decimal('TotalAmount1', 15, 2)->nullable();
            $table->decimal('TotalAmount2', 15, 2)->nullable();
            $table->string('Currency0', 3)->nullable();
            $table->decimal('TotalAmount0', 15, 2)->nullable();
            $table->string('Currency1', 3)->nullable();
            $table->float('ConvertRate')->nullable();
            $table->decimal('ConvertAmount', 15, 2)->nullable();
            $table->string('Currency2', 3)->nullable();
            $table->float('ConvertRate2')->nullable();
            $table->decimal('ConvertAmount2', 15, 2)->nullable();
            $table->boolean('IsSyncT')->nullable();
        });

        Schema::table('service_bill_details', function (Blueprint $table) {
            $table->decimal('ServiceChargeAmount', 15, 2)->nullable();
            $table->decimal('SpecialTaxAmount', 15, 2)->nullable();
            $table->decimal('TaxAmount', 15, 2)->nullable();
            $table->float('BillExchangeRate')->nullable();
            $table->decimal('BillExchangeAmount', 15, 2)->nullable();
            $table->decimal('DetailBillTotalAmount', 15, 2)->nullable();
            $table->decimal('DetailBillServiceChargeAmount', 15, 2)->nullable();
            $table->decimal('DetailBillSpecialTaxAmount', 15, 2)->nullable();
            $table->decimal('DetailBillTaxAmount', 15, 2)->nullable();
            $table->decimal('OriginalAmount', 15, 2)->nullable();
        });

        Schema::table('housekeeping_service_bills', function (Blueprint $table) {
            $table->string('NumOfRoom', 10)->nullable();
            $table->smallInteger('HumanNumber')->nullable();
            $table->decimal('BillServicesChargeAmount', 15, 2)->nullable();
            $table->decimal('BillSpecialTaxAmount', 15, 2)->nullable();
            $table->decimal('BillTaxAmount', 15, 2)->nullable();
            $table->decimal('BillTotalAmount', 15, 2)->nullable();
            $table->boolean('IncludeServicesCharge')->nullable();
            $table->boolean('IncludeSpecialTax')->nullable();
            $table->boolean('IncludeTax')->nullable();
            $table->integer('FOCType')->nullable();
            $table->string('BillTime', 5)->nullable();
            $table->integer('BillShift')->nullable();
            $table->string('CaptainOrder', 50)->nullable();
            $table->boolean('IsExport')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('housekeeping_service_bills', function (Blueprint $table) {
            $table->dropColumn([
                'NumOfRoom', 'HumanNumber', 'BillServicesChargeAmount', 'BillSpecialTaxAmount',
                'BillTaxAmount', 'BillTotalAmount', 'IncludeServicesCharge',
                'IncludeSpecialTax', 'IncludeTax', 'FOCType', 'BillTime',
                'BillShift', 'CaptainOrder', 'IsExport',
            ]);
        });

        Schema::table('service_bill_details', function (Blueprint $table) {
            $table->dropColumn([
                'ServiceChargeAmount', 'SpecialTaxAmount', 'TaxAmount',
                'BillExchangeRate', 'BillExchangeAmount', 'DetailBillTotalAmount',
                'DetailBillServiceChargeAmount', 'DetailBillSpecialTaxAmount',
                'DetailBillTaxAmount', 'OriginalAmount',
            ]);
        });

        Schema::table('service_bills', function (Blueprint $table) {
            $table->dropColumn([
                'RefId', 'NotPrint', 'VATNumber', 'Serial', 'InvoiceNumber',
                'DebitAccount', 'CreditAccount', 'RevenueAccount', 'CostAccount',
                'BillExchangeRate', 'BillExchangeAmount', 'ParentBillId',
                'UpdatedUser', 'UpdatedHour', 'OwnerUser', 'RootOwnerUser',
                'ExchangeRate1', 'ExchangeRate2', 'TotalAmount1', 'TotalAmount2',
                'Currency0', 'TotalAmount0', 'Currency1', 'ConvertRate',
                'ConvertAmount', 'Currency2', 'ConvertRate2', 'ConvertAmount2',
                'IsSyncT',
            ]);
        });
    }
};
