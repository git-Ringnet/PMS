<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm cột name_according_police: tên loại giấy tờ theo danh mục mẫu khai báo lưu trú của Công an
     * (dùng khi xuất Excel mẫu khách Việt Nam)
     */
    public function up(): void
    {
        if (!Schema::hasColumn('id_types', 'name_according_police')) {
            Schema::table('id_types', function (Blueprint $table) {
                $table->string('name_according_police', 100)->nullable()->after('name');
            });
        }

        $mapping = [
            'CCCD' => '1 - Thẻ CCCD',
            'GPLX' => '3 - Giấy phép lái xe',
            'Passport' => '4 - Hộ chiếu',
            'Other' => '9 - Giấy Tờ Khác',
        ];
        foreach ($mapping as $code => $policeName) {
            DB::table('id_types')->where('code', $code)->update(['name_according_police' => $policeName]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('id_types', 'name_according_police')) {
            Schema::table('id_types', function (Blueprint $table) {
                $table->dropColumn('name_according_police');
            });
        }
    }
};
