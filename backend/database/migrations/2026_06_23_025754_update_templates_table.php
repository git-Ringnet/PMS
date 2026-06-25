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
        Schema::table('templates', function (Blueprint $table) {
            $table->string('page_size')->default('A4')->after('report');
            $table->string('page_orientation')->default('portrait')->after('page_size');
            $table->integer('margin_top')->default(10)->after('page_orientation');
            $table->integer('margin_bottom')->default(10)->after('margin_top');
            $table->integer('margin_left')->default(10)->after('margin_bottom');
            $table->integer('margin_right')->default(10)->after('margin_left');
            $table->longText('content_json')->nullable()->after('margin_right');
            $table->longText('content_html')->nullable()->after('content_json');
            $table->text('css')->nullable()->after('content_html');
            $table->boolean('is_default')->default(false)->after('css');
            $table->string('version')->default('1.0')->after('is_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn([
                'page_size',
                'page_orientation',
                'margin_top',
                'margin_bottom',
                'margin_left',
                'margin_right',
                'content_json',
                'content_html',
                'css',
                'is_default',
                'version'
            ]);
        });
    }
};
