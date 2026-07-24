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
        Schema::table('booking_children', function (Blueprint $table) {
            // Giấy tờ cá nhân (SP2400)
            $table->string('id_type', 50)->nullable()->after('nationality_code');
            $table->string('id_number', 50)->nullable()->after('id_type');
            $table->date('id_issue_date')->nullable()->after('id_number');
            $table->string('passport_number', 50)->nullable()->after('id_issue_date');
            $table->date('passport_expiry')->nullable()->after('passport_number');
            $table->unsignedTinyInteger('gender')->nullable()->after('passport_expiry'); // 0 = Nam, 1 = Nữ, 2 = Khác

            // Địa chỉ & Liên hệ
            $table->string('phone', 20)->nullable()->after('gender');
            $table->string('email', 150)->nullable()->after('phone');
            $table->string('address', 500)->nullable()->after('email');
            $table->string('province', 100)->nullable()->after('address');
            $table->string('district', 100)->nullable()->after('province');
            $table->string('ward', 100)->nullable()->after('district');

            // Khai báo lưu trú & Xuất nhập cảnh
            $table->string('residence_type', 20)->nullable()->after('ward');
            $table->date('temp_residence_to')->nullable()->after('residence_type');
            $table->string('visa_no', 50)->nullable()->after('temp_residence_to');
            $table->date('entry_date')->nullable()->after('visa_no');
            $table->date('visa_expiry_date')->nullable()->after('entry_date');
            $table->string('entry_purpose', 200)->nullable()->after('visa_expiry_date');
            $table->string('border_gate', 100)->nullable()->after('entry_purpose');
            $table->text('note')->nullable()->after('border_gate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_children', function (Blueprint $table) {
            $table->dropColumn([
                'id_type', 'id_number', 'id_issue_date', 'passport_number', 'passport_expiry', 'gender',
                'phone', 'email', 'address', 'province', 'district', 'ward',
                'residence_type', 'temp_residence_to', 'visa_no', 'entry_date', 'visa_expiry_date',
                'entry_purpose', 'border_gate', 'note'
            ]);
        });
    }
};
