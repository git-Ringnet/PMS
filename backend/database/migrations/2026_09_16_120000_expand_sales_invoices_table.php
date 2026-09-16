<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Mở rộng bảng vật lý sales_invoices với đầy đủ các cột nghiệp vụ
        if (Schema::hasTable('sales_invoices')) {
            Schema::table('sales_invoices', function (Blueprint $table) {
                if (!Schema::hasColumn('sales_invoices', 'guest_room_id')) {
                    $table->string('guest_room_id', 50)->nullable()->after('room');
                }
                if (!Schema::hasColumn('sales_invoices', 'original_rate')) {
                    $table->decimal('original_rate', 20, 6)->default(0)->after('currency');
                }
                if (!Schema::hasColumn('sales_invoices', 'service_charge_amount')) {
                    $table->decimal('service_charge_amount', 20, 6)->default(0)->after('original_rate');
                }
                if (!Schema::hasColumn('sales_invoices', 'special_tax')) {
                    $table->decimal('special_tax', 20, 6)->default(0)->after('service_charge_amount');
                }
                if (!Schema::hasColumn('sales_invoices', 'tax')) {
                    $table->decimal('tax', 20, 6)->default(0)->after('special_tax');
                }
                if (!Schema::hasColumn('sales_invoices', 'discount')) {
                    $table->decimal('discount', 20, 6)->default(0)->after('tax');
                }
                if (!Schema::hasColumn('sales_invoices', 'payment_date')) {
                    $table->dateTime('payment_date')->nullable()->after('invoice_date');
                }
                if (!Schema::hasColumn('sales_invoices', 'open_time')) {
                    $table->string('open_time', 20)->nullable()->after('payment_date');
                }
                if (!Schema::hasColumn('sales_invoices', 'department')) {
                    $table->string('department', 20)->default('FO')->after('outlet');
                }
                if (!Schema::hasColumn('sales_invoices', 'booking_id')) {
                    $table->unsignedBigInteger('booking_id')->nullable()->after('department')->index();
                }
                if (!Schema::hasColumn('sales_invoices', 'booking_room_id')) {
                    $table->string('booking_room_id', 50)->nullable()->after('booking_id')->index();
                }
                if (!Schema::hasColumn('sales_invoices', 'guest_id')) {
                    $table->string('guest_id', 50)->nullable()->after('booking_room_id')->index();
                }
                if (!Schema::hasColumn('sales_invoices', 'company_id')) {
                    $table->unsignedBigInteger('company_id')->nullable()->after('guest_id')->index();
                }
                if (!Schema::hasColumn('sales_invoices', 'payment_code')) {
                    $table->string('payment_code', 50)->nullable()->after('company_id')->index();
                }
                if (!Schema::hasColumn('sales_invoices', 'guest_name')) {
                    $table->string('guest_name', 255)->nullable()->after('payment_code');
                }
                if (!Schema::hasColumn('sales_invoices', 'ca')) {
                    $table->string('ca', 20)->nullable()->after('username');
                }
                if (!Schema::hasColumn('sales_invoices', 'exchange_rate')) {
                    $table->decimal('exchange_rate', 20, 6)->default(1)->after('amount');
                }
                if (!Schema::hasColumn('sales_invoices', 'note')) {
                    $table->string('note', 500)->nullable()->after('status');
                }
            });
        }

        // 2. Thêm cột invoice_id vào bảng payments nếu chưa có
        if (Schema::hasTable('payments') && !Schema::hasColumn('payments', 'invoice_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->unsignedBigInteger('invoice_id')->nullable()->after('payment_id')->index();
            });
        }

        // 3. Dọn dẹp triệt để bất kỳ view hoặc bảng legacy sp3000, sp3002, sp3003 nếu tồn tại
        try {
            DB::statement('DROP VIEW IF EXISTS sp3003');
            DB::statement('DROP VIEW IF EXISTS sp3002');
            DB::statement('DROP VIEW IF EXISTS sp3000');
            DB::statement('DROP TABLE IF EXISTS sp3003');
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'invoice_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('invoice_id');
            });
        }
    }
};
