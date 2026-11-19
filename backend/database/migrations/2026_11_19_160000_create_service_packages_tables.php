<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_packages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('description', 255)->nullable();
            $table->string('currency', 10)->default('VND');
            $table->unsignedSmallInteger('nights')->default(1);
            $table->decimal('display_price', 15, 2)->default(0);
            $table->date('begin_date')->nullable();
            $table->date('end_date')->nullable();
            $table->json('days_of_week')->nullable();
            $table->boolean('is_inactive')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('service_package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_package_id')->constrained('service_packages')->cascadeOnDelete();
            $table->unsignedSmallInteger('detail_no');
            $table->string('service_code', 50);
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('rate', 15, 2)->default(0);
            $table->decimal('service_charge', 8, 4)->default(0);
            $table->decimal('special_tax', 8, 4)->default(0);
            $table->decimal('tax', 8, 4)->default(0);
            $table->unsignedSmallInteger('order_day')->default(1);
            $table->string('outlet', 250)->nullable();
            $table->string('note', 200)->nullable();
            $table->timestamps();

            $table->unique(['service_package_id', 'detail_no']);
            $table->index('service_code');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('service_package_id')->nullable()->after('breakfast_included')
                ->constrained('service_packages')->nullOnDelete();
            $table->json('service_package_snapshot')->nullable()->after('service_package_id');
        });

        Schema::table('booking_room_services', function (Blueprint $table) {
            $table->unsignedSmallInteger('service_package_detail_no')->nullable()->after('service_code');
            $table->index(['booking_room_id', 'service_package_detail_no'], 'brs_package_detail_index');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_package_id');
            $table->dropColumn('service_package_snapshot');
        });

        Schema::table('booking_room_services', function (Blueprint $table) {
            $table->dropIndex('brs_package_detail_index');
            $table->dropColumn('service_package_detail_no');
        });

        Schema::dropIfExists('service_package_items');
        Schema::dropIfExists('service_packages');
    }
};
