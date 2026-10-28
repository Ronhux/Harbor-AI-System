<?php

use App\Support\PhilippineMobile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Add the canonical (normalized) login mobile number to accounts and enforce
 * database-level uniqueness on it.
 *
 * Why this exists: `users.contact_number` holds whatever representation the
 * account was created with (09XXXXXXXXX, +639XXXXXXXXX, spaced/dashed text).
 * A PHP-level "is this number already registered?" check is inherently racy:
 * two simultaneous registrations can both pass the check before either row is
 * written. A UNIQUE constraint on the canonical value closes that window.
 *
 * What this migration changes:
 *  - ADDS the nullable `login_number` column (CHAR(11)).
 *  - ADDS the `users_login_number_unique` index.
 *  - POPULATES `login_number` for existing rows whose stored contact number is
 *    a valid Philippine mobile number.
 *
 * What this migration deliberately does NOT do:
 *  - It does not drop, rename, truncate, or rewrite `contact_number`. Every
 *    existing stored contact number is preserved byte-for-byte.
 *  - It does not merge, delete, or reassign accounts.
 *  - It does not modify or drop any existing column, index, or constraint.
 *
 * Rows whose stored contact number is not a valid Philippine mobile number
 * (landlines, blank values, free text, test placeholders) keep a NULL
 * `login_number`. MySQL and SQLite both allow any number of NULLs in a UNIQUE
 * index, so those rows never block the constraint and are never claimed by it.
 *
 * Rows whose canonical number is already claimed by a lower `user_id` also keep
 * NULL `login_number` and are reported as conflicts. That is the honest outcome:
 * the data is ambiguous and only an operator can decide which account is
 * correct. No conflicting number is changed here.
 */
return new class extends Migration
{
    public function up(): void
    {
        $columnAdded = false;

        if (!Schema::hasColumn('users', 'login_number')) {
            Schema::table('users', function (Blueprint $table) {
                $table->char('login_number', 11)->nullable()->after('contact_number');
            });
            $columnAdded = true;
        }

        $conflicts = $columnAdded ? $this->backfillCanonicalLoginNumbers() : [];

        if (!$this->hasLoginNumberUniqueIndex()) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('login_number', 'users_login_number_unique');
            });
        }

        if ($conflicts !== []) {
            Log::warning('HarborAI mobile login numbers: existing conflicts left unresolved.', [
                'conflicts' => $conflicts,
                'required_cleanup' => 'Resolve each duplicate number manually (merge is NOT performed automatically), then re-run: php artisan migrate:refresh is NOT required. After cleanup, populate with: UPDATE users SET login_number = <canonical> WHERE user_id = ...',
            ]);
        }
    }

    public function down(): void
    {
        if ($this->hasLoginNumberUniqueIndex()) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_login_number_unique');
            });
        }

        if (Schema::hasColumn('users', 'login_number')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('login_number');
            });
        }
    }

    /**
     * Fill `login_number` for every account whose stored contact number is a
     * valid Philippine mobile number, keeping the lowest `user_id` for any
     * canonical number that is claimed more than once.
     *
     * @return array<string, list<int>> canonical number => every user_id that stores it
     */
    private function backfillCanonicalLoginNumbers(): array
    {
        $claimedBy = [];
        $conflicts = [];

        DB::table('users')
            ->select('user_id', 'contact_number')
            ->whereNotNull('contact_number')
            ->orderBy('user_id')
            ->chunkById(500, function ($rows) use (&$claimedBy, &$conflicts): void {
                foreach ($rows as $row) {
                    $canonical = PhilippineMobile::normalize($row->contact_number);

                    if ($canonical === null) {
                        continue;
                    }

                    if (!isset($claimedBy[$canonical])) {
                        $claimedBy[$canonical] = (int) $row->user_id;
                        $conflicts[$canonical] ??= [(int) $row->user_id];

                        DB::table('users')
                            ->where('user_id', $row->user_id)
                            ->update(['login_number' => $canonical]);

                        continue;
                    }

                    // A second account already claims this number. Leave it
                    // unenforced (NULL) and report it instead of rewriting it.
                    $conflicts[$canonical][] = (int) $row->user_id;
                }
            }, 'user_id');

        // Keep only genuinely contested numbers in the report.
        $conflicts = array_filter($conflicts, fn (array $userIds): bool => count($userIds) > 1);

        return $conflicts;
    }

    private function hasLoginNumberUniqueIndex(): bool
    {
        foreach (['mysql', 'mariadb', 'pgsql', 'sqlite'] as $driver) {
            if (DB::connection()->getDriverName() !== $driver) {
                continue;
            }

            return match ($driver) {
                'mysql', 'mariadb' => DB::selectOne(
                    'SELECT 1 AS found FROM information_schema.statistics
                     WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
                    ['users', 'users_login_number_unique']
                ) !== null,
                'sqlite' => DB::selectOne(
                    "SELECT 1 AS found FROM sqlite_master WHERE type = 'index' AND tbl_name = 'users' AND name = ? LIMIT 1",
                    ['users_login_number_unique']
                ) !== null,
                default => DB::selectOne(
                    'SELECT 1 AS found FROM pg_indexes WHERE tablename = ? AND indexname = ? LIMIT 1',
                    ['users', 'users_login_number_unique']
                ) !== null,
            };
        }

        return false;
    }
};