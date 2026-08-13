<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Danh sách kho (map SP6006, thêm outlet_id cho Get Bill)
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            // outlet_id liên kết SP5409 (department=HK) — dùng cho Get Bill
            $table->string('outlet_id', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Phiếu kiểm kê định kỳ (map SP6009)
        Schema::create('inventory_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_id');
            $table->char('month', 7); // YYYY-MM
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['warehouse_id', 'month']); // mỗi kho chỉ 1 phiếu/tháng
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
        });

        // Chi tiết phiếu kiểm kê (map SP6010)
        Schema::create('inventory_check_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('check_id');
            $table->unsignedBigInteger('product_id'); // FK -> products (SP6005)
            $table->float('well_balance')->default(0);    // Tồn đầu kỳ
            $table->float('stoke_take')->default(0);      // Số lượng thực tế (StockTake)
            $table->float('different_qty')->default(0);   // = stoke_take - well_balance
            $table->float('final_balance')->default(0);   // Tồn cuối kỳ (= well_balance ban đầu, cập nhật theo tháng)
            $table->string('unit', 50)->nullable();
            $table->string('note', 200)->nullable();
            $table->timestamps();

            $table->unique(['check_id', 'product_id']);
            $table->foreign('check_id')->references('id')->on('inventory_checks')->onDelete('cascade');
        });

        // Nhật ký nhập/xuất/chuyển kho từng ngày (map SP6007)
        Schema::create('inventory_daily_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('warehouse_id');
            $table->date('date');
            $table->unsignedBigInteger('product_id');
            $table->float('receive')->default(0);   // Nhập
            $table->float('export')->default(0);    // Xuất
            $table->float('transfer')->default(0);  // Chuyển
            $table->timestamps();

            $table->primary(['warehouse_id', 'date', 'product_id']);
        });

        // Chi tiết phiếu chuyển kho (map SP6008)
        Schema::create('inventory_transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_id');        // Kho nguồn
            $table->unsignedBigInteger('product_id');
            $table->date('date');
            $table->float('quantity');
            $table->unsignedBigInteger('transfer_to_warehouse_id'); // Kho đích
            $table->char('hour', 5)->nullable(); // HH:mm
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transfers');
        Schema::dropIfExists('inventory_daily_logs');
        Schema::dropIfExists('inventory_check_items');
        Schema::dropIfExists('inventory_checks');
        Schema::dropIfExists('warehouses');
    }
};
