<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Đa ngôn ngữ dùng chung toàn hệ thống (DB pms_system).
 * Khóa của mỗi dòng dịch là chính chữ gốc tiếng Việt đang hiển thị trên giao diện / mẫu in,
 * nên không cần sửa code các module hiện có để gắn key.
 */
return new class extends Migration
{
    public function up(): void
    {
        $system = config('database_domains.system_connection', 'mysql_system');
        $schema = Schema::connection($system);

        if (! $schema->hasTable('languages')) {
            $schema->create('languages', function (Blueprint $table) {
                $table->id();
                $table->string('code', 10)->unique()->comment('vi, en, ja, km...');
                $table->string('name', 60)->comment('Tên hiển thị: Tiếng Việt, English...');
                $table->string('flag', 16)->nullable()->comment('Emoji cờ hoặc ký hiệu ngắn');
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false)->comment('Ngôn ngữ gốc của giao diện (vi)');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });

            $now = now();
            DB::connection($system)->table('languages')->insert([
                ['code' => 'vi', 'name' => 'Tiếng Việt', 'flag' => '🇻🇳', 'is_active' => true, 'is_default' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['code' => 'en', 'name' => 'English', 'flag' => '🇬🇧', 'is_active' => true, 'is_default' => false, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        if (! $schema->hasTable('translation_entries')) {
            $schema->create('translation_entries', function (Blueprint $table) {
                $table->id();
                $table->char('source_hash', 40)->unique()->comment('sha1 của chữ gốc đã chuẩn hóa khoảng trắng');
                $table->text('source')->comment('Chữ gốc tiếng Việt, có thể chứa {biến} nếu là mẫu câu');
                $table->boolean('is_pattern')->default(false)->comment('Mẫu câu có {biến}, vd: Còn {n} phòng trống');
                $table->string('note', 255)->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('translation_values')) {
            $schema->create('translation_values', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('entry_id');
                $table->string('lang_code', 10);
                $table->text('default_value')->nullable()->comment('Bản dịch có sẵn trong code (i18n.js)');
                $table->text('value')->nullable()->comment('Bản dịch do khách sạn nhập; null = dùng mặc định');
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->unique(['entry_id', 'lang_code']);
                $table->index('lang_code');
                $table->foreign('entry_id')->references('id')->on('translation_entries')->cascadeOnDelete();
            });
        }

        if (! $schema->hasTable('translation_locations')) {
            $schema->create('translation_locations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('entry_id');
                $table->string('area', 255)->comment('Đường dẫn vị trí, vd: Menu › Lễ tân › Giao Phòng');
                $table->string('route', 255)->nullable()->comment('URL màn hình để mở "Xem vị trí"');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamp('last_seen_at')->nullable();

                $table->unique(['entry_id', 'area']);
                $table->index('area');
                $table->foreign('entry_id')->references('id')->on('translation_entries')->cascadeOnDelete();
            });
        }

        $this->seedPermissions($system);
    }

    public function down(): void
    {
        $system = config('database_domains.system_connection', 'mysql_system');
        $schema = Schema::connection($system);
        $schema->dropIfExists('translation_locations');
        $schema->dropIfExists('translation_values');
        $schema->dropIfExists('translation_entries');
        $schema->dropIfExists('languages');

        if ($schema->hasTable('permissions')) {
            DB::connection($system)->table('permissions')->where('screen_code', 'system.language')->delete();
        }
    }

    /** Thêm quyền màn hình Ngôn ngữ (đồng bộ với RbacMatrixSeeder) cho hệ thống đang chạy. */
    private function seedPermissions(string $system): void
    {
        $schema = Schema::connection($system);
        if (! $schema->hasTable('permissions') || ! $schema->hasColumn('permissions', 'screen_code')) {
            return;
        }

        $actions = [
            'view' => ['system.language.view', 'Xem bản dịch ngôn ngữ'],
            'add' => ['system.language.create', 'Thêm ngôn ngữ / dòng dịch'],
            'edit' => ['system.language.edit', 'Sửa bản dịch ngôn ngữ'],
            'delete' => ['system.language.delete', 'Xóa ngôn ngữ / dòng dịch'],
        ];
        $sortOrder = (int) DB::connection($system)->table('permissions')->max('sort_order');
        $now = now();

        foreach ($actions as $action => [$code, $name]) {
            DB::connection($system)->table('permissions')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => $name,
                    'module' => 'SYSTEM',
                    'application_code' => 'PMS',
                    'screen_code' => 'system.language',
                    'screen_name' => 'Ngôn ngữ & Bản dịch',
                    'action' => $action,
                    'screen_type' => 'screen',
                    'sort_order' => ++$sortOrder,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
};
