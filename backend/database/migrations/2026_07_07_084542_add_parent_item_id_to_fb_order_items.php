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
        Schema::table('fb_order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_item_id')->nullable()->after('product_id');
            $table->foreign('parent_item_id')->references('id')->on('fb_order_items')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fb_order_items', function (Blueprint $table) {
            $table->dropForeign(['parent_item_id']);
            $table->dropColumn('parent_item_id');
        });
    }
};
