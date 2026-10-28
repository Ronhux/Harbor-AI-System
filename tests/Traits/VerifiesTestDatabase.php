<?php

namespace Tests\Traits;

use Illuminate\Support\Facades\Config;
use LogicException;

/**
 * Fail-closed database target verification for FCA integration tests.
 *
 * This trait enforces strict database targeting before any migration or
 * destructive test setup runs. It must be used in setUp() BEFORE
 * parent::setUp() to prevent RefreshDatabase from connecting to an
 * unauthorized database.
 *
 * Allowed targets:
 *   - SQLite :memory: (default, safe for CI/unit tests)
 *   - Explicitly approved MariaDB test database (harborai_fca_regression_disposable)
 *
 * Rejected targets:
 *   - harbor_ai (production database)
 *   - Any other database name not explicitly approved
 *   - Any connection falling back to .env without explicit test override
 */
trait VerifiesTestDatabase
{
    /**
     * The exact approved MariaDB test database name.
     * Must be set via DB_DATABASE env var when running MariaDB integration tests.
     */
    protected const APPROVED_MARIADB_TEST_DATABASE = 'harborai_fca_regression_disposable';

    /**
     * The approved MariaDB test username.
     * Must be set via DB_USERNAME env var when running MariaDB integration tests.
     */
    protected const APPROVED_MARIADB_TEST_USERNAME = 'harborai_fca_regression';

    /**
     * The approved MariaDB test port.
     */
    protected const APPROVED_MARIADB_TEST_PORT = 3307;

    /**
     * Pre-boot environment variable guard.
     *
     * Runs BEFORE parent::setUp() boots the Laravel application.
     * Uses getenv() directly to avoid facade dependency.
     *
     * @throws LogicException if the database target is not authorized
     */
    protected function verifyTestDatabaseTargetPreBoot(): void
    {
        if (getenv('DB_CONNECTION') === 'mysql') {
            \Tests\Support\FcaDatabaseGuard::verify(getenv('FCA_DB_VALIDATION_MODE') ?: '');
        }
        if (getenv('DB_URL') || getenv('DB_SOCKET')) throw new LogicException('Database URL/socket overrides forbidden.');
        // Check for config caching which would bypass env var overrides
        $cachedConfigPath = dirname(__DIR__, 2) . '/bootstrap/cache/config.php';
        if (is_file($cachedConfigPath)) {
            throw new LogicException('Config cache detected (bootstrap/cache/config.php). Tests must run with uncached configuration.');
        }

        // Read raw environment variables (phpunit.xml overrides apply here)
        $dbConnection = getenv('DB_CONNECTION') ?: 'sqlite';
        $dbDatabase = getenv('DB_DATABASE') ?: ':memory:';
        $dbUsername = getenv('DB_USERNAME') ?: '';
        $dbHost = getenv('DB_HOST') ?: '127.0.0.1';
        $dbPort = getenv('DB_PORT') ?: '3306';

        // Mode 1: SQLite in-memory (default, safe)
        $isSqliteMemory = ($dbConnection === 'sqlite' && $dbDatabase === ':memory:');

        // Mode 2: Explicit MariaDB integration test (opt-in via exact match)
        $isApprovedMariaDb = (
            $dbConnection === 'mysql' &&
            $dbDatabase === self::APPROVED_MARIADB_TEST_DATABASE &&
            $dbUsername === self::APPROVED_MARIADB_TEST_USERNAME &&
            $dbHost === '127.0.0.1' &&
            (string)$dbPort === (string)self::APPROVED_MARIADB_TEST_PORT
        );

        // Explicitly reject production database
        if ($dbDatabase === 'harbor_ai') {
            throw new LogicException('Production database harbor_ai targeted. Refusing to run tests.');
        }

        // Reject any other database
        if (! $isSqliteMemory && ! $isApprovedMariaDb) {
            throw new LogicException(
                "Unauthorized database target: connection={$dbConnection}, database={$dbDatabase}, username={$dbUsername}, host={$dbHost}, port={$dbPort}. " .
                "Allowed: sqlite :memory: OR exact MariaDB test database (harborai_fca_regression_disposable with user harborai_fca_regression on port 3307)."
            );
        }

        // Additional safety: verify the test database name pattern for MariaDB
        if ($isApprovedMariaDb) {
            if (! preg_match('/^harborai_fca_regression_disposable$/', $dbDatabase)) {
                throw new LogicException("MariaDB test database name must be 'harborai_fca_regression_disposable'");
            }
        }
    }

    /**
     * Post-boot configuration verification.
     *
     * Runs AFTER parent::setUp() when Laravel facades are available.
     * Verifies the effective resolved configuration matches expectations.
     *
     * @throws LogicException if the resolved configuration doesn't match
     */
    protected function verifyTestDatabaseTargetPostBoot(): void
    {
        $defaultConn = Config::get('database.default');
        $connConfig = Config::get("database.connections.{$defaultConn}");

        if (! $connConfig) {
            throw new LogicException("Database connection '{$defaultConn}' not configured.");
        }

        $driver = $connConfig['driver'] ?? null;
        $database = $connConfig['database'] ?? null;
        $username = $connConfig['username'] ?? null;
        $host = $connConfig['host'] ?? null;
        $port = $connConfig['port'] ?? null;

        // Mode 1: SQLite in-memory (default, safe)
        $isSqliteMemory = ($driver === 'sqlite' && $database === ':memory:');

        // Mode 2: Explicit MariaDB integration test (opt-in via exact match)
        $isApprovedMariaDb = (
            $driver === 'mysql' &&
            $database === self::APPROVED_MARIADB_TEST_DATABASE &&
            $username === self::APPROVED_MARIADB_TEST_USERNAME &&
            $host === '127.0.0.1' &&
            (string)$port === (string)self::APPROVED_MARIADB_TEST_PORT
        );

        // Explicitly reject production database
        if ($database === 'harbor_ai') {
            throw new LogicException('Production database harbor_ai targeted. Refusing to run tests.');
        }

        // Reject any other database
        if (! $isSqliteMemory && ! $isApprovedMariaDb) {
            throw new LogicException(
                "Unauthorized database target (post-boot): driver={$driver}, database={$database}, username={$username}, host={$host}, port={$port}. " .
                "Allowed: sqlite :memory: OR exact MariaDB test database (harborai_fca_regression_disposable with user harborai_fca_regression on port 3307)."
            );
        }
    }
}