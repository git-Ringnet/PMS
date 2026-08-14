<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_bills', function (Blueprint $table) {
            $table->string('employee_code', 50)->nullable()->after('Username')->index();
        });

        Schema::table('booking_room_services', function (Blueprint $table) {
            $table->string('posted_by_employee_code', 50)->nullable()->after('posted_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('booking_room_services', function (Blueprint $table) {
            $table->dropIndex(['posted_by_employee_code']);
            $table->dropColumn('posted_by_employee_code');
        });

        Schema::table('service_bills', function (Blueprint $table) {
            $table->dropIndex(['employee_code']);
            $table->dropColumn('employee_code');
        });
    }
};
