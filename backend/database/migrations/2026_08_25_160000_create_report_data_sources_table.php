<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_data_sources', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('source_type', 30)->default('procedure');
            $table->string('schema_name', 128);
            $table->string('object_name', 128);
            $table->json('parameter_schema')->nullable();
            $table->json('field_schema')->nullable();
            $table->json('sample_parameters')->nullable();
            $table->unsignedInteger('max_rows')->default(1000);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_discovered_at')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'schema_name', 'object_name'], 'report_sources_object_unique');
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->foreignId('report_data_source_id')
                ->nullable()
                ->after('report')
                ->constrained('report_data_sources')
                ->nullOnDelete();
            $table->json('parameter_defaults')->nullable()->after('report_data_source_id');
        });

        Schema::table('template_versions', function (Blueprint $table) {
            $table->unsignedBigInteger('report_data_source_id')->nullable()->after('template_id');
            $table->json('parameter_defaults')->nullable()->after('report_data_source_id');
        });
    }

    public function down(): void
    {
        Schema::table('template_versions', function (Blueprint $table) {
            $table->dropColumn(['report_data_source_id', 'parameter_defaults']);
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('report_data_source_id');
            $table->dropColumn('parameter_defaults');
        });

        Schema::dropIfExists('report_data_sources');
    }
};
