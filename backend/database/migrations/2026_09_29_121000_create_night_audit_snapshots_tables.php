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
        // 1. SP7000: Năng suất công ty/đại lý theo ngày
        if (!Schema::hasTable('night_audit_agency_productivity_snapshots')) {
            Schema::create('night_audit_agency_productivity_snapshots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('night_audit_run_id')->index('idx_na_prod_run_id');
                $table->date('snapshot_date')->index('idx_na_prod_date');
                $table->unsignedBigInteger('company_id')->nullable()->index('idx_na_prod_comp_id');
                $table->string('company_name', 255)->nullable();
                $table->string('market_segment_id', 50)->nullable();
                $table->string('source_code', 50)->nullable();
                $table->string('user_sale', 50)->nullable();
                $table->string('area_id', 50)->nullable();
                $table->integer('num_of_rooms')->default(0);
                $table->integer('room_nights')->default(0);
                $table->integer('foc')->default(0);
                $table->integer('house_use')->default(0);
                $table->integer('guest_nights')->default(0);
                $table->integer('num_of_guests')->default(0);
                $table->decimal('room_revenue', 18, 2)->default(0); // RM
                $table->decimal('extra_bed_revenue', 18, 2)->default(0); // EB
                $table->decimal('extra_rollaway_revenue', 18, 2)->default(0); // ER
                $table->decimal('total_revenue', 18, 2)->default(0);
                $table->decimal('revenue_per_room_night', 18, 4)->default(0);
                $table->decimal('revenue_percent', 18, 4)->default(0);
                $table->decimal('average_revenue', 18, 4)->default(0);
                $table->integer('rav')->default(0);
                $table->decimal('rav3', 18, 4)->default(0);
                $table->timestamps();

                $table->index(['snapshot_date', 'company_id'], 'idx_na_ag_prod_date_company');
            });
        }

        // 2. SP7001: Chi tiết khách & phòng in-house theo ngày
        if (!Schema::hasTable('night_audit_inhouse_snapshots')) {
            Schema::create('night_audit_inhouse_snapshots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('night_audit_run_id')->index('idx_na_inh_run_id');
                $table->date('snapshot_date')->index('idx_na_inh_date');
                $table->string('rental_room_id', 50)->nullable()->index('idx_na_inh_rental_id');
                $table->string('customer_id', 50)->nullable();
                $table->smallInteger('status')->default(1);
                $table->dateTime('checkout_date')->nullable();
                $table->string('checkout_time', 20)->nullable();
                $table->integer('position_id')->nullable();
                $table->string('user_checkin', 50)->nullable();
                $table->string('user_checkout', 50)->nullable();
                $table->integer('breakfast')->default(0);
                $table->string('position', 100)->nullable();
                $table->string('guest', 150)->nullable();
                $table->text('note')->nullable();
                $table->string('country', 20)->nullable();
                $table->string('nationality', 20)->nullable();
                $table->string('nationality_name', 255)->nullable();
                $table->string('passport', 100)->nullable();
                $table->dateTime('birthday')->nullable();
                $table->string('guest_name', 255)->nullable();
                $table->text('address')->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('fax', 50)->nullable();
                $table->string('email', 100)->nullable();
                $table->dateTime('issue_date')->nullable();
                $table->string('visa', 50)->nullable();
                $table->dateTime('visa_date')->nullable();
                $table->unsignedBigInteger('booking_id')->nullable()->index('idx_na_inh_bkg_id');
                $table->dateTime('arrival_date')->nullable();
                $table->string('arrival_time', 20)->nullable();
                $table->smallInteger('num_of_days')->default(1);
                $table->string('room', 50)->nullable();
                $table->smallInteger('adult')->default(1);
                $table->smallInteger('child')->default(0);
                $table->smallInteger('extra_bed')->default(0);
                $table->decimal('rate', 18, 2)->default(0);
                $table->string('room_rate_code', 50)->nullable();
                $table->smallInteger('breakfast_room')->default(0);
                $table->smallInteger('breakfast_child')->default(0);
                $table->string('room_kind', 100)->nullable();
                $table->string('room_type', 50)->nullable();
                $table->dateTime('departure_date')->nullable();
                $table->string('booking_name', 255)->nullable();
                $table->string('contact', 255)->nullable();
                $table->string('company', 255)->nullable();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->smallInteger('group_status')->default(0);
                $table->integer('orders')->default(0);
                $table->smallInteger('house_use')->default(0);
                $table->string('pack3', 100)->nullable();
                $table->integer('guest_type')->default(6);
                $table->integer('day_use')->default(0);
                $table->integer('baby_cot')->default(0);
                $table->integer('gender')->default(0);
                $table->timestamps();

                $table->index(['snapshot_date', 'room'], 'idx_na_inhouse_date_room');
            });
        }

        // 3. SP7002: KPI đại lý (Storage schema chờ cấu hình producer có căn cứ)
        if (!Schema::hasTable('night_audit_agency_productivity_kpi_snapshots')) {
            Schema::create('night_audit_agency_productivity_kpi_snapshots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('night_audit_run_id')->index('idx_na_kpi_run_id');
                $table->date('snapshot_date')->index('idx_na_kpi_date');
                $table->string('company_id', 50)->nullable();
                $table->string('travel_agency', 255)->nullable();
                $table->integer('no_of_rooms')->default(0);
                $table->integer('room_nights')->default(0);
                $table->decimal('room_nights_p', 18, 4)->default(0);
                $table->integer('no_of_guests')->default(0);
                $table->integer('guest_nights')->default(0);
                $table->decimal('no_of_guests_p', 18, 4)->default(0);
                $table->decimal('revenue', 18, 2)->default(0);
                $table->decimal('revenue_p', 18, 4)->default(0);
                $table->decimal('avg_revenue', 18, 2)->default(0);
                $table->integer('rav')->default(0);
                $table->integer('foc')->default(0);
                $table->integer('hu')->default(0);
                $table->timestamps();
            });
        }

        // 4. SP7003: Forecast tổng hợp ngày
        if (!Schema::hasTable('night_audit_room_sales_forecast_snapshots')) {
            Schema::create('night_audit_room_sales_forecast_snapshots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('night_audit_run_id')->index('idx_na_fc_run_id');
                $table->date('snapshot_date')->index('idx_na_fc_date');
                $table->integer('dep_adult')->default(0);
                $table->integer('dep_child')->default(0);
                $table->integer('dep_rooms')->default(0);
                $table->integer('arr_adult')->default(0);
                $table->integer('arr_child')->default(0);
                $table->integer('arr_rooms')->default(0);
                $table->integer('occ_adult')->default(0);
                $table->integer('occ_child')->default(0);
                $table->integer('occ_rooms')->default(0);
                $table->integer('house_use')->default(0);
                $table->integer('foc_all')->default(0);
                $table->integer('foc')->default(0);
                $table->integer('foc_owner')->default(0);
                $table->integer('room_sales')->default(0);
                $table->integer('extra_bed')->default(0);
                $table->decimal('revenue', 18, 2)->default(0);
                $table->decimal('avg_rate', 18, 2)->default(0);
                $table->decimal('avg_rate_2', 18, 2)->default(0);
                $table->integer('room_available')->default(0);
                $table->decimal('percent_occupancy', 18, 4)->default(0);
                $table->decimal('percent_occupancy_2', 18, 4)->default(0);
                $table->integer('baby_cot')->default(0);
                $table->decimal('rm', 18, 2)->default(0);
                $table->decimal('eb', 18, 2)->default(0);
                $table->decimal('er', 18, 2)->default(0);
                $table->integer('ooo_room')->default(0);
                $table->timestamps();

                $table->unique(['snapshot_date', 'night_audit_run_id'], 'uniq_na_forecast_date_run');
            });
        }

        // 5. SP7004: Forecast detail breakdown (Storage schema chờ mapping service-code)
        if (!Schema::hasTable('night_audit_room_sales_forecast_detail_snapshots')) {
            Schema::create('night_audit_room_sales_forecast_detail_snapshots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('night_audit_run_id')->index('idx_na_fcdtl_run_id');
                $table->date('snapshot_date')->index('idx_na_fcdtl_date');
                $table->decimal('rm', 18, 2)->default(0);
                $table->decimal('eb', 18, 2)->default(0);
                $table->decimal('er', 18, 2)->default(0);
                $table->decimal('bf', 18, 2)->default(0);
                $table->decimal('ep', 18, 2)->default(0);
                $table->decimal('us', 18, 2)->default(0);
                $table->decimal('ee', 18, 2)->default(0);
                $table->decimal('el', 18, 2)->default(0);
                $table->integer('occ_rooms')->default(0);
                $table->integer('house_use')->default(0);
                $table->integer('foc_all')->default(0);
                $table->integer('foc')->default(0);
                $table->integer('foc_owner')->default(0);
                $table->integer('room_sales')->default(0);
                $table->integer('extra_bed')->default(0);
                $table->decimal('revenue', 18, 2)->default(0);
                $table->decimal('avg_rate', 18, 2)->default(0);
                $table->decimal('avg_rate_2', 18, 2)->default(0);
                $table->integer('room_available')->default(0);
                $table->decimal('percent_occupancy', 18, 4)->default(0);
                $table->decimal('percent_occupancy_2', 18, 4)->default(0);
                $table->integer('baby_cot')->default(0);
                $table->timestamps();

                $table->unique(['snapshot_date', 'night_audit_run_id'], 'uniq_na_forecast_dtl_date_run');
            });
        }

        // 6. SP7005: Thống kê số lượng phòng và loại phòng
        if (!Schema::hasTable('night_audit_room_type_snapshots')) {
            Schema::create('night_audit_room_type_snapshots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('night_audit_run_id')->index('idx_na_rt_run_id');
                $table->date('snapshot_date')->index('idx_na_rt_date');
                $table->unsignedBigInteger('room_type_id')->nullable();
                $table->string('room_type', 255)->nullable();
                $table->string('room_type_code', 100);
                $table->integer('inventory')->default(0);
                $table->integer('ooo')->default(0);
                $table->integer('room_available')->default(0);
                $table->integer('no_of_night')->default(0);
                $table->decimal('adr', 18, 2)->default(0);
                $table->decimal('revenue', 18, 2)->default(0);
                $table->decimal('revenue_percent', 18, 4)->default(0);
                $table->decimal('occupancy_percent', 18, 4)->default(0);
                $table->timestamps();

                $table->index(['snapshot_date', 'room_type_code'], 'idx_na_room_type_date_code');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('night_audit_room_type_snapshots');
        Schema::dropIfExists('night_audit_room_sales_forecast_detail_snapshots');
        Schema::dropIfExists('night_audit_room_sales_forecast_snapshots');
        Schema::dropIfExists('night_audit_agency_productivity_kpi_snapshots');
        Schema::dropIfExists('night_audit_inhouse_snapshots');
        Schema::dropIfExists('night_audit_agency_productivity_snapshots');
    }
};
