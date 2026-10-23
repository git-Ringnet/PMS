<?php

namespace App\Services;

use App\Models\Company;
use App\Models\HotelConfig;
use App\Models\SystemBranch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Đồng bộ chuỗi (thông số SyncCompanyAllBranch = 1): khi thêm / sửa / xóa công ty, thị trường,
 * nguồn khách ở chi nhánh đang thao tác thì đẩy thay đổi sang DB của các chi nhánh còn lại.
 *
 * - Khớp dữ liệu giữa các chi nhánh bằng mã (code), không dùng id vì id mỗi DB khác nhau.
 * - Không đồng bộ người bán (sales person), người đặt phòng, chi nhánh: các trường gắn với từng chi nhánh.
 * - Lỗi ở chi nhánh khác không chặn thao tác hiện tại, chỉ trả về cảnh báo.
 */
class ChainCatalogSyncService
{
    public const CONFIG_NAME = 'SyncCompanyAllBranch';

    /** Trường công ty được đồng bộ (ngoài nguồn khách / thị trường được ánh xạ theo mã). */
    private const COMPANY_FIELDS = [
        'code', 'name', 'trading_name', 'address', 'tax_code', 'phone', 'email',
        'sync_acc', 'max_debt', 'bank_account', 'rate_code', 'is_active',
    ];

    private const CATALOG_FOREIGN_KEYS = [
        'markets' => 'market_id',
        'customer_sources' => 'customer_source_id',
    ];

    public function enabled(): bool
    {
        return trim((string) HotelConfig::where('name', self::CONFIG_NAME)->value('value')) === '1';
    }

    /** @return array<string, string> mã chi nhánh => tên connection của các chi nhánh khác */
    public function targetConnections(): array
    {
        $currentCode = strtoupper((string) request()->attributes->get('_branch_code', config('database_domains.default_branch_code')));
        $currentConnection = DB::getDefaultConnection();
        $staticConnections = config('database_domains.branch_connections', []);

        $targets = [];
        foreach (SystemBranch::where('is_active', true)->orderBy('id')->get(['code']) as $branch) {
            $code = (string) $branch->code;
            if (strtoupper($code) === $currentCode) {
                continue;
            }

            $connection = $staticConnections[strtoupper($code)] ?? $staticConnections[$code] ?? null;
            if (!$connection) {
                $connection = TenantDatabaseService::getConnectionName($code);
                if (!config("database.connections.{$connection}")) {
                    TenantDatabaseService::registerDynamicConnection($connection, TenantDatabaseService::getDatabaseName($code));
                }
            }
            if ($connection !== $currentConnection) {
                $targets[$code] = $connection;
            }
        }

        return $targets;
    }

    /**
     * Thông số SyncCompanyAllBranch áp dụng cho cả chuỗi: đổi ở một chi nhánh thì
     * ghi cùng giá trị sang các chi nhánh khác để đồng bộ chạy được cả hai chiều.
     * @return string[] cảnh báo các chi nhánh không cập nhật được
     */
    public function propagateSetting(?string $value): array
    {
        $warnings = [];
        foreach ($this->targetConnections() as $branch => $connection) {
            try {
                DB::connection($connection)->table('hotel_configs')->updateOrInsert(
                    ['name' => self::CONFIG_NAME],
                    ['value' => $value, 'updated_at' => now()]
                );
            } catch (\Throwable $e) {
                Log::error("ChainCatalogSync: không cập nhật được thông số ở chi nhánh {$branch}: " . $e->getMessage());
                $warnings[] = "Chi nhánh {$branch}: không cập nhật được thông số SyncCompanyAllBranch, vui lòng kiểm tra log hệ thống.";
            }
        }

        return $warnings;
    }

    /**
     * Mã công ty tiếp theo, tính trên tất cả chi nhánh để không trùng mã khi đồng bộ.
     */
    public function nextCompanyCode(): string
    {
        $max = $this->maxCompanyNumber(DB::getDefaultConnection());
        foreach ($this->targetConnections() as $connection) {
            try {
                $max = max($max, $this->maxCompanyNumber($connection));
            } catch (\Throwable $e) {
                Log::warning("ChainCatalogSync: không đọc được mã công ty ở {$connection}: " . $e->getMessage());
            }
        }

        return 'CTY' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    private function maxCompanyNumber(string $connection): int
    {
        return (int) DB::connection($connection)->table('companies')->where('code', 'like', 'CTY%')->pluck('code')
            ->map(fn ($code) => (int) substr((string) $code, 3))
            ->max();
    }

    // ==================== Thị trường / Nguồn khách ====================

    /** @param 'markets'|'customer_sources' $table */
    public function catalogCreated(string $table, string $code, string $name): array
    {
        return $this->eachTarget(function (string $branch, string $connection) use ($table, $code, $name) {
            $db = DB::connection($connection)->table($table);
            if ($db->clone()->where('code', $code)->exists()) {
                $db->where('code', $code)->update(['name' => $name, 'updated_at' => now()]);
            } else {
                $db->insert(['code' => $code, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);
            }

            return null;
        });
    }

    public function catalogUpdated(string $table, string $originalCode, string $code, string $name): array
    {
        return $this->eachTarget(function (string $branch, string $connection) use ($table, $originalCode, $code, $name) {
            $db = DB::connection($connection);
            if ($code !== $originalCode && $db->table($table)->where('code', $code)->exists()) {
                return "Chi nhánh {$branch}: đã có mã {$code}, không đổi được mã {$originalCode}.";
            }

            $updated = $db->table($table)->where('code', $originalCode)
                ->update(['code' => $code, 'name' => $name, 'updated_at' => now()]);
            if (!$updated) {
                $db->table($table)->insert(['code' => $code, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);
            }

            return null;
        });
    }

    public function catalogDeleted(string $table, string $code): array
    {
        $foreignKey = self::CATALOG_FOREIGN_KEYS[$table];

        return $this->eachTarget(function (string $branch, string $connection) use ($table, $code, $foreignKey) {
            $db = DB::connection($connection);
            $id = $db->table($table)->where('code', $code)->value('id');
            if (!$id) {
                return null;
            }

            $used = $db->table('companies')->where($foreignKey, $id)->exists()
                || (Schema::connection($connection)->hasTable('bookings') && $db->table('bookings')->where($foreignKey, $id)->exists());
            if ($used) {
                return "Chi nhánh {$branch}: mã {$code} đang được sử dụng nên không xóa.";
            }

            $db->table($table)->where('id', $id)->delete();

            return null;
        });
    }

    // ==================== Công ty ====================

    public function companyCreated(Company $company): array
    {
        // Nạp lại từ DB để có giá trị mặc định của cột (sync_acc, max_debt, is_active...)
        $company = $company->fresh(['market', 'customerSource']) ?? $company;

        return $this->eachTarget(function (string $branch, string $connection) use ($company) {
            $db = DB::connection($connection);
            $existing = $db->table('companies')->where('code', $company->code)->first();
            if ($existing) {
                return "Chi nhánh {$branch}: đã có công ty mã {$company->code} ({$existing->name}), không đồng bộ.";
            }

            $db->table('companies')->insert($this->companyPayload($company, $connection) + [
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return null;
        });
    }

    /** @param array $original thuộc tính công ty trước khi sửa */
    public function companyUpdated(Company $company, array $original): array
    {
        // Nạp lại từ DB để có giá trị mặc định của cột (sync_acc, max_debt, is_active...)
        $company = $company->fresh(['market', 'customerSource']) ?? $company;

        return $this->eachTarget(function (string $branch, string $connection) use ($company, $original) {
            $db = DB::connection($connection);
            $existing = $db->table('companies')->where('code', $company->code)->first();

            if (!$existing) {
                $db->table('companies')->insert($this->companyPayload($company, $connection) + [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return null;
            }
            if (!$this->isSameCompany($existing, $original)) {
                return "Chi nhánh {$branch}: công ty mã {$company->code} ({$existing->name}) khác dữ liệu, không đồng bộ.";
            }

            $db->table('companies')->where('id', $existing->id)
                ->update($this->companyPayload($company, $connection) + ['updated_at' => now()]);

            return null;
        });
    }

    /** @param array $original thuộc tính công ty trước khi xóa */
    public function companyDeleted(array $original): array
    {
        return $this->eachTarget(function (string $branch, string $connection) use ($original) {
            $db = DB::connection($connection);
            $existing = $db->table('companies')->where('code', $original['code'])->first();
            if (!$existing) {
                return null;
            }
            if (!$this->isSameCompany($existing, $original)) {
                return "Chi nhánh {$branch}: công ty mã {$original['code']} ({$existing->name}) khác dữ liệu, không xóa.";
            }

            $schema = Schema::connection($connection);
            $used = ($schema->hasTable('bookings') && $db->table('bookings')->where('company_id', $existing->id)->exists())
                || ($schema->hasTable('payments') && $db->table('payments')->where('company_id', $existing->id)->exists())
                || ($schema->hasTable('fb_promotions') && $db->table('fb_promotions')->where('company_id', $existing->id)->exists())
                || ($schema->hasTable('service_bills') && $db->table('service_bills')
                    ->where(fn ($q) => $q->where('CompanyId1', $existing->id)->orWhere('CompanyId2', $existing->id))->exists());
            if ($used) {
                return "Chi nhánh {$branch}: công ty {$existing->name} đang được sử dụng nên không xóa.";
            }

            $db->table('companies')->where('id', $existing->id)->delete();

            return null;
        });
    }

    private function companyPayload(Company $company, string $connection): array
    {
        $payload = [];
        foreach (self::COMPANY_FIELDS as $field) {
            $value = $company->getAttribute($field);
            $payload[$field] = is_bool($value) ? (int) $value : $value;
        }

        $payload['market_id'] = $this->resolveCatalogId($connection, 'markets', $company->market?->code, $company->market?->name);
        $payload['customer_source_id'] = $this->resolveCatalogId($connection, 'customer_sources', $company->customerSource?->code, $company->customerSource?->name);

        return $payload;
    }

    /** Tìm id danh mục ở chi nhánh đích theo mã, chưa có thì tạo mới. */
    private function resolveCatalogId(string $connection, string $table, ?string $code, ?string $name): ?int
    {
        if (!$code) {
            return null;
        }

        $db = DB::connection($connection)->table($table);
        $id = $db->clone()->where('code', $code)->value('id');

        return $id ? (int) $id : (int) $db->insertGetId([
            'code' => $code,
            'name' => $name ?: $code,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Cùng một công ty: trùng tên hoặc trùng mã số thuế với dữ liệu trước khi sửa. */
    private function isSameCompany(object $existing, array $original): bool
    {
        $norm = fn ($value) => mb_strtolower(trim((string) $value));

        return $norm($existing->name) === $norm($original['name'] ?? '')
            || ($norm($original['tax_code'] ?? '') !== '' && $norm($existing->tax_code) === $norm($original['tax_code']));
    }

    /**
     * Chạy thao tác cho từng chi nhánh khác khi đồng bộ đang bật.
     * @param callable(string $branch, string $connection): ?string $action trả về cảnh báo hoặc null
     * @return string[] danh sách cảnh báo
     */
    private function eachTarget(callable $action): array
    {
        if (!$this->enabled()) {
            return [];
        }

        $warnings = [];
        foreach ($this->targetConnections() as $branch => $connection) {
            try {
                $warning = DB::connection($connection)->transaction(fn () => $action($branch, $connection));
                if ($warning) {
                    $warnings[] = $warning;
                }
            } catch (\Throwable $e) {
                Log::error("ChainCatalogSync: lỗi đồng bộ sang chi nhánh {$branch}: " . $e->getMessage());
                $warnings[] = "Chi nhánh {$branch}: lỗi đồng bộ, vui lòng kiểm tra log hệ thống.";
            }
        }

        return $warnings;
    }
}
