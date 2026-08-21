<?php

return [
    /*
    | Tables verified as shared by every hotel branch.
    | Everything not listed here remains branch-owned until verified.
    */
    'system_connection' => env('SYSTEM_DB_CONNECTION', 'mysql_system'),

    'default_branch_code' => env('DEFAULT_BRANCH_CODE', 'HKT1'),

    'branch_connections' => [
        'HKT1' => 'mysql_hkt1',
        'HKT2' => 'mysql_hkt2',
        'HKT3' => 'mysql_hkt3',
        'HKT4' => 'mysql_hkt4',
    ],

    'system_tables' => [
        'users',
        'password_reset_tokens',
        'sessions',
        'personal_access_tokens',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'system_branches',
        'info_businesses',
        'roles',
        'permissions',
        'role_permissions',
        'user_branches',
        'user_roles',
        'user_settings',
    ],

    'system_migrations' => [
        '0001_01_01_000000_create_users_table.php',
        '0001_01_01_000001_create_cache_table.php',
        '0001_01_01_000002_create_jobs_table.php',
        '2026_06_10_103433_create_personal_access_tokens_table.php',
        '2026_06_16_160000_create_system_branches_table.php',
        '2026_06_16_160500_create_info_businesses_table.php',
        '2026_07_15_105629_create_user_settings_table.php',
        '2026_08_19_210000_create_roles_and_permissions_tables.php',
        '2026_08_21_120001_restore_system_user_foreign_keys.php',
    ],

    'system_seeders' => [
        Database\Seeders\SystemBranchSeeder::class,
        Database\Seeders\InfoBusinessSeeder::class,
        Database\Seeders\EmployeeSeeder::class,
        Database\Seeders\RolePermissionSeeder::class,
    ],

    // Tables not yet verified against the complete legacy database stay in Branch DB.
    'unresolved_table_policy' => 'branch',
];
