<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hotel-service codes may be longer than the legacy two-character codes
     * (for example RM/BF). Keep the billing tables aligned with the catalog.
     */
    public function up(): void
    {
        Schema::table('service_bills', function (Blueprint $table) {
            $table->string('ServiceId', 50)->change();
        });

        Schema::table('service_bill_details', function (Blueprint $table) {
            $table->string('ServiceId', 50)->change();
        });
    }

    public function down(): void
    {
        Schema::table('service_bills', function (Blueprint $table) {
            $table->string('ServiceId', 2)->change();
        });

        Schema::table('service_bill_details', function (Blueprint $table) {
            $table->string('ServiceId', 2)->change();
        });
    }
};
