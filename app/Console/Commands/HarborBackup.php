<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupManager;
use Illuminate\Console\Command;
use Throwable;

class HarborBackup extends Command
{
    protected $signature = 'harbor:backup {action=create : create, health, verify, drill, retain, monitor, import} {id?}';

    protected $description = 'Manage encrypted Harbor AI backups and isolated recovery drills';

    public function handle(BackupManager $backups): int
    {
        try {
            $result = match ($this->argument('action')) {
                'create' => $backups->create(null, 'scheduled-or-cli'),
                'import' => $backups->import((string) $this->argument('id')),
                'verify' => $backups->verify((string) $this->argument('id')),
                'drill' => $backups->drill((string) $this->argument('id')),
                'retain' => ['deleted' => $backups->retain()],
                'monitor' => $this->monitor($backups),
                'health' => $backups->health(),
                default => throw new \RuntimeException('Unknown backup action.'),
            };
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Backup operation failed. Review the private backup journal and operational configuration.');

            return self::FAILURE;
        }
    }

    private function monitor(BackupManager $backups): array
    {
        $backups->heartbeat();

        return ['scheduler_heartbeat' => 'recorded'];
    }
}
