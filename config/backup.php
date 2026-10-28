<?php

return [
    'enabled' => (bool) env('BACKUP_ENABLED', false),
    'root' => storage_path('app/private/harbor-backups'),
    // A separately managed 32-byte base64 key; never reuse APP_KEY.
    'key' => env('BACKUP_ENCRYPTION_KEY'),
    'storage_permissions_approved' => (bool) env('BACKUP_STORAGE_PERMISSIONS_APPROVED', false),
    'secondary_root' => env('BACKUP_SECONDARY_ROOT'),
    'dump_binary' => env('BACKUP_MYSQLDUMP_BINARY', 'mysqldump'),
    'mysql_binary' => env('BACKUP_MYSQL_BINARY', 'mysql'),
    'time' => env('BACKUP_TIME', '02:00'),
    'timezone' => 'Asia/Manila',
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),
    'rpo_hours' => 24,
    'min_free_bytes' => 512 * 1024 * 1024,
    'max_archive_bytes' => 2 * 1024 * 1024 * 1024,
    // Explicit application-owned roots only. Never archive .env or source checkpoints.
    'file_roots' => [
        'uploads' => public_path('storage'),
        'private-uploads' => storage_path('app/private/uploads'),
        'reports' => storage_path('app/private/reports'),
        'fca-evidence' => env('BACKUP_FCA_EVIDENCE_ROOT'),
    ],
    'recovery' => [
        'enabled' => (bool) env('BACKUP_RECOVERY_DRILL_ENABLED', false),
        'host' => '127.0.0.1',
        'port' => (int) env('BACKUP_RECOVERY_PORT', 3307),
        'database' => 'harborai_recovery_drill',
        'username' => 'harborai_recovery',
        'password' => env('BACKUP_RECOVERY_PASSWORD'),
    ],
];
