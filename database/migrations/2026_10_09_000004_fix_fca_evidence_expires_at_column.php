<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix fca_evidence.expires_at column on legacy installations.
 *
 * Background:
 * The original migration (2026_10_09_000002) defined:
 *     $table->timestamp('expires_at');
 *
 * On MariaDB/MySQL, the FIRST TIMESTAMP column in a table implicitly receives
 * DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP unless explicitly
 * overridden. Since expires_at was defined BEFORE $table->timestamps(), it was
 * the first TIMESTAMP column and received this implicit behavior. This caused
 * expiration timestamps to be reset to NOW() whenever the evidence row was
 * updated (inspection, attachment, status change).
 *
 * The corrected migration now uses:
 *     $table->timestamp('expires_at')->nullable()->default(null);
 *
 * This migration detects and corrects the legacy defect ONLY where it exists.
 * It does not alter already-correct schemas, does not extend expiration dates,
 * and preserves all existing evidence rows and their stored timestamps.
 *
 * Operational notes:
 * - MODIFY COLUMN on InnoDB may rebuild the table on MariaDB < 10.3 or MySQL < 5.6.
 *   Test on target version before production deployment.
 * - This migration is intentionally non-reversing (see down()).
 * - Already-corrupted expiration values (reset to upload time) cannot be
 *   reconstructed from schema alone; this migration fixes future updates only.
 * - SQLite does not have the implicit ON UPDATE behavior; this migration is a no-op on SQLite.
 */

return new class extends Migration
{
    /**
     * Supported TIMESTAMP precision variants for MariaDB/MySQL.
     */
    private const SUPPORTED_TIMESTAMP_TYPES = [
        'timestamp',
        'timestamp(0)',
        'timestamp(1)',
        'timestamp(2)',
        'timestamp(3)',
        'timestamp(4)',
        'timestamp(5)',
        'timestamp(6)',
    ];

    // Schema classification constants
    private const CLASS_CORRECT = 'correct';
    private const CLASS_LEGACY_DEFECT = 'legacy_defect';
    private const CLASS_UNEXPECTED = 'unexpected';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        // SQLite: no implicit ON UPDATE behavior; nothing to fix.
        if ($driver === 'sqlite') {
            $this->verifySqliteSchema();
            return;
        }

        // MariaDB/MySQL: inspect information_schema for legacy defect
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->fixMysqlColumn();
            return;
        }

        // Unsupported driver
        throw new \RuntimeException(
            "Unsupported database driver: {$driver}. " .
            "This migration supports mysql, mariadb, and sqlite only."
        );
    }

    /**
     * SQLite schema verification - no ALTER needed.
     * SQLite never had the ON UPDATE CURRENT_TIMESTAMP defect.
     */
    private function verifySqliteSchema(): void
    {
        if (!Schema::hasTable('fca_evidence')) {
            // No FCA evidence table: either FCA feature not installed, or schema
            // created from corrected original migration. Nothing to fix.
            return;
        }

        if (!Schema::hasColumn('fca_evidence', 'expires_at')) {
            // Table exists but column missing: unexpected incomplete FCA schema.
            throw new \RuntimeException(
                'fca_evidence table exists but expires_at column is missing. ' .
                'This indicates an incomplete or manually altered schema. ' .
                'Investigate before proceeding.'
            );
        }

        // Column exists on SQLite: already correct (no implicit ON UPDATE behavior).
        // No action needed.
    }

    /**
     * MariaDB/MySQL column fix using information_schema inspection.
     */
    private function fixMysqlColumn(): void
    {
        if (!Schema::hasTable('fca_evidence')) {
            // No FCA evidence table: either FCA feature not installed, or schema
            // created from corrected original migration. Nothing to fix.
            return;
        }

        if (!Schema::hasColumn('fca_evidence', 'expires_at')) {
            // Table exists but column missing: unexpected incomplete FCA schema.
            throw new \RuntimeException(
                'fca_evidence table exists but expires_at column is missing. ' .
                'This indicates an incomplete or manually altered schema. ' .
                'Investigate before proceeding.'
            );
        }

        $definition = $this->getColumnDefinition('fca_evidence', 'expires_at');

        if (empty($definition)) {
            // Column exists in Schema facade but not in information_schema: unexpected.
            throw new \RuntimeException(
                'Unable to read expires_at column definition from information_schema. ' .
                'Database connection or permissions issue.'
            );
        }

        $classification = $this->classifySchema($definition);

        match ($classification) {
            self::CLASS_CORRECT => null, // No-op: already correct
            self::CLASS_LEGACY_DEFECT => $this->applyCorrection($definition),
            self::CLASS_UNEXPECTED => throw new \RuntimeException(
                'Unexpected expires_at column definition detected. ' .
                'Manual investigation required before automated correction. ' .
                'Definition: ' . json_encode($definition)
            ),
        };
    }

    /**
     * Classify the column definition state.
     */
    private function classifySchema(array $definition): string
    {
        $columnType = $definition['COLUMN_TYPE'] ?? '';
        $isNullable = ($definition['IS_NULLABLE'] ?? '') === 'YES';
        $default = $definition['COLUMN_DEFAULT'] ?? null;
        $extra = $definition['EXTRA'] ?? '';

        // Validate column type is a supported TIMESTAMP variant
        if (!in_array(strtolower($columnType), self::SUPPORTED_TIMESTAMP_TYPES, true)) {
            return self::CLASS_UNEXPECTED;
        }

        // Must be nullable with NULL default for correct schema
        // information_schema returns the string 'NULL' for explicit DEFAULT NULL
        $isNullDefault = $default === null || strtolower((string)$default) === 'null';
        $isCorrectlyNullable = $isNullable && $isNullDefault;

        // Definitive legacy defect marker: ON UPDATE CURRENT_TIMESTAMP
        $hasOnUpdate = stripos($extra, 'on update current_timestamp') !== false;

        if ($hasOnUpdate) {
            return self::CLASS_LEGACY_DEFECT;
        }

        if ($isCorrectlyNullable) {
            return self::CLASS_CORRECT;
        }

        // Any other combination is unexpected (e.g., NOT NULL, non-NULL default, etc.)
        return self::CLASS_UNEXPECTED;
    }

    /**
     * Apply the correction: remove ON UPDATE behavior, preserve precision.
     */
    private function applyCorrection(array $definition): void
    {
        $columnType = $definition['COLUMN_TYPE'] ?? 'timestamp';

        // Validate precision is in our allowlist before using in SQL
        if (!in_array(strtolower($columnType), self::SUPPORTED_TIMESTAMP_TYPES, true)) {
            throw new \RuntimeException(
                "Unsupported TIMESTAMP precision in column definition: {$columnType}. " .
                "Cannot safely construct ALTER TABLE statement."
            );
        }

        $sql = "ALTER TABLE `fca_evidence` MODIFY `expires_at` {$columnType} NULL DEFAULT NULL";

        // Log the action for audit trail (Laravel log)
        \Log::info('FCA migration: fixing legacy expires_at column', [
            'sql' => $sql,
            'previous_definition' => $definition,
            'driver' => DB::getDriverName(),
        ]);

        DB::statement($sql);
    }

    /**
     * Fetch the raw column definition for expires_at from information_schema.
     * Normalizes stdClass result to associative array.
     */
    private function getColumnDefinition(string $table, string $column): array
    {
        $results = DB::select(
            'SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?',
            [$table, $column]
        );

        if (empty($results)) {
            return [];
        }

        // Normalize stdClass to associative array
        $row = $results[0];
        return [
            'COLUMN_TYPE' => $row->COLUMN_TYPE ?? '',
            'IS_NULLABLE' => $row->IS_NULLABLE ?? '',
            'COLUMN_DEFAULT' => $row->COLUMN_DEFAULT ?? null,
            'EXTRA' => $row->EXTRA ?? '',
        ];
    }

    /**
     * Reverse the migrations.
     *
     * Intentionally non-reversing: restoring the defective ON UPDATE CURRENT_TIMESTAMP
     * behavior would re-introduce the data-integrity bug. Operational rollback requires
     * a database snapshot restore, not a schema migration.
     *
     * If rollback is needed:
     * 1. Restore database from pre-migration backup/snapshot.
     * 2. Do NOT run migrate:rollback for this migration.
     */
    public function down(): void
    {
        // No-op by design. See class docblock for rollback procedure.
        throw new \BadMethodCallException(
            'This migration cannot be reversed. ' .
            'It corrects a data-integrity bug (implicit ON UPDATE CURRENT_TIMESTAMP). ' .
            'To rollback, restore database from pre-migration backup.'
        );
    }
};