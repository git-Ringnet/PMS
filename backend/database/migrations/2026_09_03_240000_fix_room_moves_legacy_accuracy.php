<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Logic đã được gộp vào migration chính 100000.
    }

    public function down(): void
    {
        // Giữ nguyên trạng thái migration chính khi rollback migration tương thích.
    }
};
