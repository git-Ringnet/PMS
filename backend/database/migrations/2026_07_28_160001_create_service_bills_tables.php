<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_bills', function (Blueprint $table) {
            $table->id('Ma');
            $table->dateTime('Date');
            $table->string('OpenTime', 5);
            $table->string('Guest', 100);
            $table->string('DepartmentId', 2);
            $table->string('ServiceId', 2);
            $table->text('DescriptionServive')->nullable();
            $table->decimal('Quantity', 15, 2)->default(1);
            $table->decimal('Amount', 20, 6)->default(0);
            $table->float('ServiceCharge')->default(0);
            $table->float('SpecialTax')->default(0);
            $table->float('Tax')->default(0);
            $table->string('Currency', 3)->default('VND');
            $table->float('Exchange')->default(1);
            $table->boolean('Edit')->default(false);
            $table->string('Folio', 1)->default('1');
            $table->unsignedBigInteger('PaymentId')->nullable();
            $table->unsignedBigInteger('VatId')->nullable();
            $table->unsignedBigInteger('RegisterId1')->nullable();
            $table->string('RentalRoomId1', 50)->nullable();
            $table->string('CustomerId1', 50)->nullable();
            $table->unsignedBigInteger('CompanyId1')->nullable();
            $table->unsignedBigInteger('RegisterID2')->nullable();
            $table->string('RentalRoomId2', 50)->nullable();
            $table->string('CustomerId2', 50)->nullable();
            $table->unsignedBigInteger('CompanyId2')->nullable();
            $table->string('Username', 50);
            $table->string('Ca', 1)->nullable();
            $table->unsignedSmallInteger('Status')->default(1);
            $table->unsignedBigInteger('InvoiceId')->nullable();
            $table->string('Outlet', 10)->nullable();
            $table->string('Pack1', 50)->nullable();
            $table->string('Pack2', 50)->nullable();
            $table->string('Pack3', 500)->nullable();
            $table->unsignedInteger('Year')->nullable();
            $table->unsignedTinyInteger('Month')->nullable();
            $table->unsignedTinyInteger('Day')->nullable();
            $table->string('CreatedUser', 50)->nullable();
            $table->dateTime('CreatedDate')->nullable();
            $table->string('CreatedHour', 5)->nullable();
            $table->dateTime('UpdatedDate')->nullable();
            $table->unsignedBigInteger('AdjustmentBillId')->nullable();
            $table->string('MisaRefId', 50)->nullable();
            $table->timestamps();
            $table->index(['RentalRoomId2', 'CustomerId2']);
            $table->index('PaymentId');
            $table->index('VatId');
        });

        Schema::create('service_bill_details', function (Blueprint $table) {
            $table->unsignedBigInteger('BillServiceId');
            $table->unsignedBigInteger('Ma');
            $table->string('DepartmentId', 2);
            $table->string('ServiceId', 2);
            $table->string('DescriptionServive', 400)->nullable();
            $table->decimal('OriginalRate', 15, 2)->nullable();
            $table->float('ServiceCharge')->default(0);
            $table->float('SpecialTax')->default(0);
            $table->float('Tax')->default(0);
            $table->decimal('Amount', 20, 6)->default(0);
            $table->string('Currency', 3)->default('VND');
            $table->float('Exchange')->default(1);
            $table->string('Pack1', 50)->nullable();
            $table->string('Pack2', 50)->nullable();
            $table->string('Pack3', 500)->nullable();
            $table->decimal('DetailBillOriginalAmount', 20, 6)->nullable();
            $table->decimal('DiscountAmount', 20, 6)->default(0);
            $table->decimal('IncreaseAmount', 20, 6)->default(0);
            $table->unsignedBigInteger('VatId')->nullable();
            $table->string('VatNumber', 100)->nullable();
            $table->primary(['BillServiceId', 'Ma']);
        });

        Schema::create('housekeeping_service_bills', function (Blueprint $table) {
            $table->id('Ma');
            $table->unsignedBigInteger('BookingId')->nullable();
            $table->string('GuestId', 50)->nullable();
            $table->decimal('BillOriginalAmount', 20, 6)->default(0);
            $table->decimal('BillDiscountAmount', 20, 6)->default(0);
            $table->decimal('BillAmount', 20, 6)->default(0);
            $table->float('BillDiscount')->default(0);
            $table->float('BillServicesCharge')->default(0);
            $table->float('BillSpecialTax')->default(0);
            $table->float('BillTax')->default(0);
            $table->string('BillNote', 255)->nullable();
            $table->unsignedTinyInteger('Status')->default(1);
            $table->string('Outlet', 10);
            $table->dateTime('Date')->nullable();
            $table->string('Department', 10);
            $table->string('RoomNo', 20)->nullable();
            $table->unsignedBigInteger('BillServiceId')->nullable();
            $table->string('Currency', 3)->default('VND');
            $table->float('ExchangeRate')->default(1);
            $table->string('BillUsername', 50)->nullable();
            $table->unsignedTinyInteger('BillEdit')->default(0);
            $table->timestamps();
            $table->index('BillServiceId');
            $table->index('GuestId');
        });

        Schema::create('housekeeping_service_bill_details', function (Blueprint $table) {
            $table->unsignedBigInteger('BillId');
            $table->unsignedBigInteger('DetailId');
            $table->unsignedBigInteger('MaProduct')->nullable();
            $table->unsignedBigInteger('ProductGroupId')->nullable();
            $table->string('Product', 100);
            $table->decimal('Rate', 15, 2)->default(0);
            $table->decimal('Quantity', 12, 6)->default(1);
            $table->float('Discount')->default(0);
            $table->decimal('DiscountAmount', 20, 6)->default(0);
            $table->float('Increase')->default(0);
            $table->decimal('IncreaseAmount', 20, 6)->default(0);
            $table->decimal('TotalAmount', 20, 6)->default(0);
            $table->string('Note', 255)->nullable();
            $table->unsignedTinyInteger('Deleted')->default(0);
            $table->primary(['BillId', 'DetailId']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('housekeeping_service_bill_details');
        Schema::dropIfExists('housekeeping_service_bills');
        Schema::dropIfExists('service_bill_details');
        Schema::dropIfExists('service_bills');
    }
};
