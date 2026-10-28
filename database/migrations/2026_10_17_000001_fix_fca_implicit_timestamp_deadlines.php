<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrective migration: remove implicit ON UPDATE behaviour from three FCA deadline columns.
 *
 * WHY THIS EXISTS
 * ---------------
 * This server runs with explicit_defaults_for_timestamp = OFF. MariaDB then gives the
 * FIRST timestamp column in a table an implicit DEFAULT CURRENT_TIMESTAMP ON UPDATE
 * CURRENT_TIMESTAMP, and gives every SUBSEQUENT timestamp column the invalid default
 * '0000-00-00 00:00:00' unless it is declared NULL.
 *
 * Three columns were declared as a NOT NULL timestamp with no default and landed as:
 *
 *   fca_directory_contacts.valid_until  DEFAULT current_timestamp() ON UPDATE current_timestamp()
 *   fca_otp_challenges.expires_at       DEFAULT current_timestamp() ON UPDATE current_timestamp()
 *   fca_president_claims.consented_at    DEFAULT current_timestamp() ON UPDATE current_timestamp()
 *
 * Any UPDATE that touches an unrelated column on those rows silently rewrites the
 * deadline forward. That is a real authorization defect, not cosmetic:
 *
 *   - valid_until gates whether a directory contact may match at all
 *     (PresidentVerification::directoryMatch / ::contact / ::trustedContact)
 *   - expires_at gates whether an OTP challenge may still be verified
 *   - consented_at is the original consent event and must never move during review
 *
 * It is the same defect class that 2026_10_09_000004 repairs for fca_evidence.expires_at.
 *
 * TARGET SEMANTICS
 * ----------------
 *   valid_until  -> timestamp NULL DEFAULT NULL
 *                  Always written explicitly at provisioning. A NULL or past value
 *                  authorises nobody; App\Support\Deadline fails closed on NULL.
 *   expires_at   -> timestamp NULL DEFAULT NULL
 *                  Always written explicitly when a challenge is created. A NULL or
 *                  past value makes the challenge permanently unusable.
 *   consented_at -> timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
 *                  Records the moment consent was captured at INSERT and is never
 *                  rewritten by an UPDATE. No ON UPDATE clause.
 *
 * All three keep the existing column precision.
 *
 * PROPERTIES
 * ----------
 * - Idempotent: a column that is already correct is left untouched.
 * - Fails loudly on an unrecognised definition instead of guessing.
 * - Never rewrites stored values. An already-drifted deadline is NOT reconstructed;
 *   only future updates are protected. Operator reconciliation is a separate decision.
 * - Non-reversing by design (see down()): restoring ON UPDATE behaviour would
 *   reintroduce the defect, so a database restore is the only rollback path.
 * - SQLite has no implicit ON UPDATE behaviour: verified and left unchanged.
 */
return new class extends Migration
{
    private const SUPPORTED_TIMESTAMP_TYPES = [
        'timestamp', 'timestamp(0)', 'timestamp(1)', 'timestamp(2)',
        'timestamp(3)', 'timestamp(4)', 'timestamp(5)', 'timestamp(6)',
    ];

    private const CLASS_CORRECT = 'correct';
    private const CLASS_LEGACY_DEFECT = 'legacy_defect';
    private const CLASS_UNEXPECTED = 'unexpected';

    /**
     * table => [column, desired nullability, desired default expression or null]
     */
    private const TARGETS = [
        'fca_directory_contacts' => ['valid_until', true, null],
        'fca_otp_challenges' => ['expires_at', true, null],
        'fca_president_claims' => ['consented_at', false, 'CURRENT_TIMESTAMP'],
    ];

    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $this->verifySqliteSchema();

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->correctMysqlColumns();

            return;
        }

        throw new RuntimeException("Unsupported database driver: {$driver}. Supports mysql, mariadb, sqlite only.");
    }

    /** SQLite never had the implicit ON UPDATE behaviour, so this only checks presence. */
    private function verifySqliteSchema(): void
    {
        foreach (self::TARGETS as $table => [$column]) {
            if (!Schema::hasTable($table)) continue; // Table not installed: nothing to correct.
            if (!Schema::hasColumn($table, $column)) {
                throw new RuntimeException(
                    "{$table} exists but {$column} is missing. Incomplete or manually altered schema; investigate."
                );
            }
        }
    }

    private function correctMysqlColumns(): void
    {
        foreach (self::TARGETS as $table => [$column, $nullable, $default]) {
            if (!Schema::hasTable($table)) {
                continue; // Table not installed: nothing to correct.
            }
            if (!Schema::hasColumn($table, $column)) {
                throw new RuntimeException(
                    "{$table} exists but {$column} is missing. Incomplete or manually altered schema; investigate."
                );
            }

            $definition = $this->columnDefinition($table, $column);
            if ($definition === null) {
                throw new RuntimeException(
                    "Unable to read {$table}.{$column} from information_schema. Connection or permission issue."
                );
            }

            $classification = $this->classify($definition, $nullable, $default);

            match ($classification) {
                self::CLASS_CORRECT => null,
                self::CLASS_LEGACY_DEFECT => $this->applyCorrection($table, $column, $definition, $nullable, $default),
                self::CLASS_UNEXPECTED => throw new RuntimeException(
                    "Unexpected definition for {$table}.{$column}. Manual investigation required. "
                    .'Definition: ' . json_encode($definition)
                ),
            };
        }
    }

    private function columnDefinition(string $table, string $column): ?array
    {
        $row = DB::selectOne(
            'SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
               FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$table, $column]
        );

        return $row === null ? null : [
            'COLUMN_TYPE' => $row->COLUMN_TYPE,
            'IS_NULLABLE' => $row->IS_NULLABLE,
            'COLUMN_DEFAULT' => $row->COLUMN_DEFAULT,
            'EXTRA' => $row->EXTRA,
        ];
    }

    private function classify(array $definition, bool $wantNullable, ?string $wantDefault): string
    {
        $columnType = strtolower((string) ($definition['COLUMN_TYPE'] ?? ''));
        if (!in_array($columnType, self::SUPPORTED_TIMESTAMP_TYPES, true)) {
            return self::CLASS_UNEXPECTED;
        }

        $isNullable = ($definition['IS_NULLABLE'] ?? '') === 'YES';
        $default = $definition['COLUMN_DEFAULT'] ?? null;
        $extra = (string) ($definition['EXTRA'] ?? '');

        // The defect itself is an implicit ON UPDATE clause on a verification deadline.
        if (stripos($extra, 'on update') !== false) {
            return self::CLASS_LEGACY_DEFECT;
        }

        // No ON UPDATE: compare the rest against the intended definition.
        if ($wantNullable) {
            $isNullDefault = $default === null || strtolower((string) $default) === 'null';
            return $isNullable && $isNullDefault ? self::CLASS_CORRECT : self::CLASS_UNEXPECTED;
        }

        $isCurrentTimestamp = strtolower((string) $default) === 'current_timestamp';
        return (!$isNullable && $isCurrentTimestamp) ? self::CLASS_CORRECT : self::CLASS_UNEXPECTED;
    }

    private function applyCorrection(string $table, string $column, array $definition, bool $nullable, ?string $default): void
    {
        $columnType = strtolower((string) ($definition['COLUMN_TYPE'] ?? 'timestamp'));
        if (!in_array($columnType, self::SUPPORTED_TIMESTAMP_TYPES, true)) {
            throw new RuntimeException("Unsupported TIMESTAMP precision for {$table}.{$column}: {$columnType}.");
        }

        // Only these exact fragments are ever interpolated into SQL; both come from a fixed map.
        $nullability = $nullable ? 'NULL' : 'NOT NULL';
        $defaultSql = $default === null ? 'DEFAULT NULL' : 'DEFAULT ' . $default;
        $sql = "ALTER TABLE `{$table}` MODIFY `{$column}` {$columnType} {$nullability} {$defaultSql}";

        DB::statement($sql);

        Log::info('FCA corrective migration: removed implicit ON UPDATE from a verification deadline', [
            'table' => $table,
            'column' => $column,
            'sql' => $sql,
            'previous_definition' => $definition,
        ]);
    }

    /**
     * Intentionally non-reversing. Reinstating ON UPDATE CURRENT_TIMESTAMP would
     * restore the authorization defect this migration exists to remove.
     */
    public function down(): void
    {
        throw new RuntimeException(
            '2026_10_17_000001 is not reversible by design. Restoring ON UPDATE behaviour on a verification '
            .'deadline would reinstate the authorization defect. Roll back from a verified database backup instead.'
        );
    }
};