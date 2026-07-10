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
        // 1. fb_parties
        Schema::create('fb_parties', function (Blueprint $table) {
            $table->id();
            $table->string('party_code', 50)->unique();
            $table->string('party_name');
            $table->date('arrival_date');
            $table->string('confirmation_type', 50)->default('byDate');
            $table->date('confirmation_date')->nullable();
            $table->string('sale_staff')->nullable();
            $table->string('company')->default('KHÁCH LẺ');
            $table->string('customer')->nullable();
            $table->string('email')->nullable();
            $table->text('note')->nullable();
            $table->text('vat_note')->nullable();
            $table->string('status', 50)->default('confirmed'); // confirmed, serving, completed, cancelled
            $table->timestamps();
        });

        // 2. fb_sub_parties
        Schema::create('fb_sub_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained('fb_parties')->cascadeOnDelete();
            $table->string('booking_code', 50)->nullable();
            $table->date('arrival_date');
            $table->time('arrival_time')->nullable();
            $table->time('departure_time')->nullable();
            $table->integer('adults')->default(1);
            $table->integer('children')->default(0);
            $table->integer('tables')->default(1);
            $table->integer('extra')->default(0);
            $table->string('outlet')->nullable();
            $table->string('location')->nullable();
            $table->string('party_type')->nullable();
            $table->string('group_code')->nullable();
            $table->text('note')->nullable();
            $table->string('status', 50)->default('confirmed'); // confirmed, serving, completed, cancelled
            $table->timestamps();
        });

        // 3. fb_party_items
        Schema::create('fb_party_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_party_id')->constrained('fb_sub_parties')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('fb_products')->nullOnDelete();
            $table->string('name');
            $table->integer('quantity')->default(1);
            $table->string('unit')->default('Phần');
            $table->decimal('price', 15, 2)->default(0);
            $table->string('note')->nullable();
            $table->timestamps();
        });

        // 4. fb_party_payments
        Schema::create('fb_party_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained('fb_parties')->cascadeOnDelete();
            $table->foreignId('sub_party_id')->nullable()->constrained('fb_sub_parties')->cascadeOnDelete();
            $table->date('payment_date');
            $table->string('payment_method');
            $table->decimal('amount', 15, 2);
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fb_party_payments');
        Schema::dropIfExists('fb_party_items');
        Schema::dropIfExists('fb_sub_parties');
        Schema::dropIfExists('fb_parties');
    }
};
