<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_debt_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->date('payment_date');
            $table->time('payment_time')->nullable();
            $table->string('payment_method_id', 50);
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('VND');
            $table->string('description', 500)->nullable();
            $table->unsignedTinyInteger('edit_flag')->default(0);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index(['payment_id', 'edit_flag']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_debt_settlements');
    }
};
