<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->string('group')->default('Report');
            $table->text('description')->nullable();
            $table->foreignId('report_data_source_id')
                ->constrained('report_data_sources')
                ->restrictOnDelete();
            $table->json('parameter_ui_schema')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('show_in_menu')->default(true);
            $table->json('menu_locations')->nullable();
            $table->unsignedInteger('menu_top_order')->default(20);
            $table->unsignedInteger('menu_group_order')->default(0);
            $table->unsignedInteger('menu_item_order')->default(0);
            $table->timestamps();
        });

        Schema::create('report_definition_template', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_definition_id')
                ->constrained('report_definitions')
                ->cascadeOnDelete();
            $table->foreignId('template_id')
                ->constrained('templates')
                ->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['report_definition_id', 'template_id'], 'report_definition_template_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_definition_template');
        Schema::dropIfExists('report_definitions');
    }
};
