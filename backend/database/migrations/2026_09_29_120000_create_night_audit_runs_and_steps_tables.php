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
        if (!Schema::hasTable('night_audit_runs')) {
            Schema::create('night_audit_runs', function (Blueprint $table) {
                $table->id();
                $table->date('source_system_date');
                $table->date('target_system_date');
                $table->dateTime('actual_started_at');
                $table->dateTime('actual_finished_at')->nullable();
                $table->string('shift', 20)->default('1');
                $table->string('username', 50)->default('admin');
                $table->string('status', 30)->default('running'); // running, succeeded, failed, recovery_required
                $table->string('idempotency_key', 100)->unique();
                $table->string('error_code', 50)->nullable();
                $table->text('error_message')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['source_system_date', 'status'], 'idx_na_runs_date_status');
                $table->index('actual_started_at', 'idx_na_runs_started_at');
            });
        }

        if (!Schema::hasTable('night_audit_run_steps')) {
            Schema::create('night_audit_run_steps', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('run_id');
                $table->string('step_code', 50);
                $table->string('step_name', 255);
                $table->unsignedSmallInteger('step_order');
                $table->string('status', 30)->default('pending'); // pending, running, succeeded, failed, skipped_unconfigured
                $table->dateTime('started_at')->nullable();
                $table->dateTime('finished_at')->nullable();
                $table->integer('affected_rows')->default(0);
                $table->json('summary')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->foreign('run_id')->references('id')->on('night_audit_runs')->onDelete('cascade');
                $table->index(['run_id', 'step_order'], 'idx_na_steps_run_order');
                $table->index(['run_id', 'status'], 'idx_na_steps_run_status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('night_audit_run_steps');
        Schema::dropIfExists('night_audit_runs');
    }
};
