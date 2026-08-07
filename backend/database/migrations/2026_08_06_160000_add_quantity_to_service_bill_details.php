<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('service_bill_details', function (Blueprint $table) {
            $table->decimal('Quantity', 20, 6)->default(1)->after('OriginalRate');
        });

        DB::statement("UPDATE service_bill_details SET Quantity = CASE WHEN OriginalRate IS NULL OR OriginalRate = 0 THEN 1 ELSE Amount / OriginalRate END");
    }

    public function down(): void
    {
        Schema::table('service_bill_details', function (Blueprint $table) {
            $table->dropColumn('Quantity');
        });
    }
};
