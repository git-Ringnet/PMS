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
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('group')->nullable();
            $table->string('name')->nullable();
            $table->string('report')->nullable();
            $table->string('page_size')->default('A4');
            $table->string('page_orientation')->default('portrait');
            $table->integer('margin_top')->default(10);
            $table->integer('margin_bottom')->default(10);
            $table->integer('margin_left')->default(10);
            $table->integer('margin_right')->default(10);
            $table->longText('content_json')->nullable();
            $table->longText('content_html')->nullable();
            $table->text('css')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('version')->default('1.0');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
