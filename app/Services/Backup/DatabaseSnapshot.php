<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;

class DatabaseSnapshot
{
    public function create(string $directory): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        if ($driver === 'sqlite' && app()->environment('testing')) {
            $file = $directory.'/database.sqlite';
            $connection->statement('VACUUM INTO '.$connection->getPdo()->quote($file));
            chmod($file, 0600);

            return ['driver' => 'sqlite', 'entry' => 'database.sqlite', 'tables' => $this->sqliteTables(new PDO('sqlite:'.$file))];
        }
        if ($driver !== 'mysql') {
            throw new RuntimeException('Only MySQL backups and isolated SQLite tests are supported.');
        }
        $engines = $connection->select("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'");
        foreach ($engines as $table) {
            if (strtolower((string) $table->ENGINE) !== 'innodb') {
                throw new RuntimeException('All database tables must use InnoDB for a consistent online snapshot.');
            }
        }
        $config = $connection->getConfig();
        $this->mysql($directory, $config, config('backup.dump_binary'), ['--single-transaction', '--quick', '--skip-lock-tables', '--hex-blob', '--routines', '--events', '--triggers', '--result-file='.$directory.'/database.sql', $config['database']]);
        chmod($directory.'/database.sql', 0600);

        return ['driver' => 'mysql', 'entry' => 'database.sql', 'tables' => array_map(fn ($row) => $row->TABLE_NAME, $engines)];
    }

    public function restoreIsolated(string $directory, array $database): array
    {
        if ($database['driver'] === 'sqlite' && app()->environment('testing')) {
            $pdo = new PDO('sqlite:'.$directory.'/database.sqlite');
            if ($pdo->query('PRAGMA integrity_check')->fetchColumn() !== 'ok' || $pdo->query('PRAGMA foreign_key_check')->fetchAll()) {
                throw new RuntimeException('Restored database integrity failed.');
            }
            $tables = $this->sqliteTables($pdo);
            $expected = $database['tables'] ?? [];
            sort($expected);
            if ($tables !== $expected) {
                throw new RuntimeException('Restored table inventory mismatch.');
            }
            $counts = [];
            foreach ($tables as $table) {
                $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM "'.str_replace('"', '""', $table).'"')->fetchColumn();
            }

            return ['tables' => $tables, 'row_counts' => $counts, 'target' => 'isolated SQLite temporary directory'];
        }
        $target = config('backup.recovery');
        if (! ($target['enabled'] ?? false) || $database['driver'] !== 'mysql') {
            throw new RuntimeException('An approved isolated recovery database is not configured.');
        }
        $live = DB::connection()->getConfig();
        if ($target['host'] !== '127.0.0.1' || (int) $target['port'] === (int) ($live['port'] ?? 3306)
            || $target['database'] !== 'harborai_recovery_drill' || $target['username'] !== 'harborai_recovery'
            || $target['username'] === ($live['username'] ?? '') || $target['database'] === ($live['database'] ?? '')
            || empty($target['password'])) {
            throw new RuntimeException('Isolated recovery target safety checks failed.');
        }
        $pdo = new PDO('mysql:host=127.0.0.1;port='.(int) $target['port'].';dbname=harborai_recovery_drill;charset=utf8mb4', $target['username'], $target['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->assertRecoveryGrants($pdo->query('SHOW GRANTS FOR CURRENT_USER')->fetchAll(PDO::FETCH_COLUMN));
        // DISABLED cannot be re-enabled at runtime by another connection.
        if (strtoupper((string) $pdo->query('SELECT @@GLOBAL.event_scheduler')->fetchColumn()) !== 'DISABLED') {
            throw new RuntimeException('Recovery server event scheduler must be DISABLED at startup.');
        }
        if ($pdo->query('SHOW FULL TABLES')->fetchAll()
            || $pdo->query('SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE()')->fetchAll()
            || $pdo->query('SELECT EVENT_NAME FROM information_schema.EVENTS WHERE EVENT_SCHEMA = DATABASE()')->fetchAll()) {
            throw new RuntimeException('Recovery target must be empty, including routines and events; existing objects will not be overwritten.');
        }
        $this->mysql($directory, $target, config('backup.mysql_binary'), ['--binary-mode', '--local-infile=0', '--skip-reconnect', '--database=harborai_recovery_drill'], $directory.'/database.sql');
        $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
        $expected = $database['tables'];
        sort($tables);
        sort($expected);
        if ($tables !== $expected) {
            throw new RuntimeException('Restored table inventory mismatch.');
        }
        $counts = [];
        foreach ($tables as $table) {
            $quoted = chr(96).str_replace(chr(96), chr(96).chr(96), $table).chr(96);
            $result = $pdo->query('CHECK TABLE '.$quoted)->fetchAll(PDO::FETCH_ASSOC);
            if (! collect($result)->contains(fn ($row) => ($row['Msg_text'] ?? '') === 'OK')) {
                throw new RuntimeException('Restored table check failed.');
            }
            $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM '.$quoted)->fetchColumn();
        }

        return ['tables' => $tables, 'row_counts' => $counts, 'target' => 'isolated harborai_recovery_drill', 'limitations' => 'Application login, role permissions, semantic relationships and external integrations require separate recovery QA.'];
    }

    /** Fail closed on global privileges, roles, wildcards and grant delegation. */
    public function assertRecoveryGrants(array $grants): void
    {
        if (! $grants) {
            throw new RuntimeException('Recovery grants could not be verified.');
        }
        foreach ($grants as $grant) {
            if (stripos($grant, 'WITH GRANT OPTION') !== false) {
                throw new RuntimeException('Recovery grant delegation is forbidden.');
            }
            if (preg_match('/^GRANT USAGE ON \*\.\* TO /i', $grant)) {
                continue;
            }
            // Database-level grants interpret unescaped underscores as wildcards.
            // Require the literal, escaped schema name returned by SHOW GRANTS.
            $schema = chr(96).'harborai'.chr(92).'_recovery'.chr(92).'_drill'.chr(96);
            if (! preg_match('/^GRANT [A-Z_, ]+ ON '.preg_quote($schema, '/').'\.\* TO /i', $grant)) {
                throw new RuntimeException('Recovery user must have grants confined to the literal isolated recovery database.');
            }
        }
    }

    private function sqliteTables(PDO $pdo): array
    {
        return $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    }

    private function mysql(string $directory, array $config, string $binary, array $arguments, ?string $input = null): void
    {
        $credentials = $directory.'/mysql-client.cnf';
        $quote = static fn ($v) => '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], (string) $v).'"';
        $content = "[client]\nuser=".$quote($config['username'])."\npassword=".$quote($config['password'] ?? '')."\nhost=".$quote($config['host'] ?? '127.0.0.1')."\nport=".(int) ($config['port'] ?? 3306)."\nprotocol=tcp\n";
        $handle = fopen($credentials, 'xb');
        if (! $handle) {
            throw new RuntimeException('Cannot prepare private database credentials.');
        }
        chmod($credentials, 0600);
        $written = fwrite($handle, $content);
        fclose($handle);
        if ($written !== strlen($content)) {
            unlink($credentials);
            throw new RuntimeException('Cannot write private database credentials.');
        }
        $stream = $input ? fopen($input, 'rb') : null;
        try {
            if ($input !== null && ! is_resource($stream)) {
                throw new RuntimeException('Recovery SQL input is unavailable.');
            }
            $process = new Process(array_merge([$binary, '--defaults-file='.$credentials], $arguments));
            $process->setTimeout(3600);
            if ($stream) {
                $process->setInput($stream);
            } $process->disableOutput();
            if ($process->run() !== 0) {
                throw new RuntimeException('Database backup/recovery tool failed. Check the binary, grants and private configuration.');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            } unlink($credentials);
        }
    }
}
