<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_template_slots', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('group');
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('template_id')->nullable()->constrained('templates')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $slots = [
            ['BOOKING_CONFIRMATION', 'Booking Confirmation', 'Xác nhận đặt phòng'],
            ['REGISTRATION_CARD', 'Registration Card', 'Phiếu đăng ký khách sạn'],
            ['DEPOSIT_RECEIPT', 'Deposit', 'Phiếu đặt cọc'],
            ['PAYMENT_RECEIPT', 'Receipt', 'Phiếu thu'],
            ['ROOM_MORNING_WORKSHEET', 'Room Morning Worksheet', 'Phiếu công việc buồng phòng'],
            ['INVOICE', 'Invoice', 'Hóa đơn khách sạn'],
            ['BREAKFAST_TICKET', 'Breakfast Ticket', 'Phiếu ăn sáng'],
        ];

        foreach ($slots as $index => [$code, $group, $name]) {
            $defaultTemplateId = DB::table('templates')
                ->where('group', $group)
                ->where('is_default', true)
                ->value('id');

            DB::table('print_template_slots')->insert([
                'code' => $code,
                'group' => $group,
                'name' => $name,
                'description' => "Vị trí mẫu in cho {$name}",
                'template_id' => $defaultTemplateId,
                'sort_order' => $index,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('print_template_slots');
    }
};
