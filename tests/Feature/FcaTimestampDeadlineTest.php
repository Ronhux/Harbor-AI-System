<?php

namespace Tests\Feature;

use App\Models\{FcaDirectoryContact, FcaOtpChallenge, FcaPresidentClaim, FcaRegistry, User};
use App\Services\Mao\President\{PresidentIdentity, PresidentVerification};
use App\Support\Deadline;
use Illuminate\Support\Facades\{DB, Http};
use Illuminate\Support\Str;

/**
 * Verification deadlines and the consent record must not move on an unrelated update.
 *
 * Background. On this server explicit_defaults_for_timestamp is OFF, so MariaDB gives a
 * non-first NOT NULL timestamp column an implicit
 * DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP. Three FCA columns landed that
 * way and were proven on MariaDB 10.4 to slide forward whenever any other column on the
 * same row was updated:
 *
 *   fca_directory_contacts.valid_until  -> gates whether a contact may match at all
 *   fca_otp_challenges.expires_at       -> gates whether a challenge may still be used
 *   fca_president_claims.consented_at    -> the original consent event
 *
 * Migration 2026_10_17_000001 removes the ON UPDATE behaviour. These tests pin the
 * application half of the fix: the application must never write those columns on an
 * unrelated update, and a missing or expired deadline must authorise nobody.
 *
 * SQLite has no implicit ON UPDATE behaviour, so the schema half of the defect cannot be
 * reproduced here. It is proven against MariaDB 10.4 in an isolated schema; these tests
 * guard the application contract and the fail-closed behaviour.
 *
 * Fixtures are built locally rather than inherited, because the existing FCA suites keep
 * their helpers private and are not modified by this file.
 */
class FcaTimestampDeadlineTest extends FcaApplicationPersistenceTest
{
    private PresidentVerification $verification;

    private string $phone;

    private ?string $code = null;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->phone = '09' . str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT);
        config([
            'app.key' => 'base64:' . base64_encode(random_bytes(32)),
            'fca_president.lookup_key' => str_repeat('L', 32),
            'fca_president.verifier_key' => str_repeat('V', 32),
            'fca_president.workflow_enabled' => true,
        ]);
        $this->verification = new PresidentVerification();
    }

    private function enable(): void
    {
        config([
            'fca_president.verification_method' => 'otp_future',
            'fca_president.workflow_enabled' => true,
            'fca_president.notice_version' => 'synthetic.notice',
            'fca_president.sms_enabled' => true,
            'fca_president.semaphore_key' => 'not-a-real-key',
            'fca_president.sender' => 'Synthetic',
        ]);
    }

    private function owner(): User
    {
        return User::where('user_type', 'Farmer')->firstOrFail();
    }

    private function reviewer(): User
    {
        return User::where('user_type', 'Admin')->firstOrFail();
    }

    private function directory(): FcaRegistry
    {
        return FcaRegistry::firstOrFail();
    }

    private function addContact(?string $validUntil = 'month'): FcaDirectoryContact
    {
        $phone = $this->phone;
        $row = (new FcaDirectoryContact())->forceFill([
            'fca_id' => $this->directory()->id,
            'phone' => $phone,
            'phone_lookup' => PresidentIdentity::digest($phone),
            'source_reference' => 'Synthetic approved source',
            'status' => 'active',
            'version' => 1,
            'valid_until' => match ($validUntil) {
                'month' => now()->addMonth(),
                'past' => now()->subDay(),
                default => now()->addMonth(),
            },
        ]);
        $row->saveOrFail();

        return $row;
    }

    private function makeClaim(): array
    {
        return $this->verification->match(
            $this->owner(),
            $this->directory()->id,
            $this->directory()->official_name,
            'Synthetic president',
            $this->phone,
            true
        );
    }

    private function fakeSms(string $status = 'Pending'): void
    {
        Http::fake(['api.semaphore.co/*' => function ($request) use ($status) {
            $this->code = $request['code'];

            return Http::response([[
                'message_id' => 1, 'recipient' => $request['number'], 'code' => $request['code'],
                'message' => 'private code body', 'status' => $status,
            ]], 200);
        }]);
    }

    private function rawContact(int $id): ?string
    {
        return DB::table('fca_directory_contacts')->where('id', $id)->value('valid_until');
    }

    private function rawChallenge(string $id): ?string
    {
        return DB::table('fca_otp_challenges')->where('id', $id)->value('expires_at');
    }

    private function rawConsent(int $claimId): ?string
    {
        return DB::table('fca_president_claims')->where('id', $claimId)->value('consented_at');
    }

    // -----------------------------------------------------------------
    // 1. Updating a directory contact does not extend its validity
    // -----------------------------------------------------------------

    public function test_updating_a_directory_contact_does_not_extend_its_validity(): void
    {
        $this->enable();
        $contact = $this->addContact();
        $original = $this->rawContact($contact->id);
        $this->assertNotNull($original);

        // Unrelated updates, separated in time so an implicit ON UPDATE would be visible.
        $contact->version = 2;
        $contact->status = 'disputed';
        $contact->saveOrFail();
        $this->assertSame($original, $this->rawContact($contact->id), 'version/status update moved valid_until');

        $this->travel(120)->seconds();
        $contact = $contact->fresh();
        $contact->version = 3;
        $contact->saveOrFail();
        $this->assertSame($original, $this->rawContact($contact->id), 'a later update moved valid_until');

        $this->assertTrue($contact->valid_until->isFuture(), 'the provisioned deadline is still in force');
    }

    // -----------------------------------------------------------------
    // 2. Updating an OTP challenge does not change its expiration
    // -----------------------------------------------------------------

    public function test_updating_an_otp_challenge_does_not_change_its_expiration(): void
    {
        $this->enable();
        $this->addContact();
        $claim = $this->makeClaim();
        $this->fakeSms();
        $sent = $this->verification->send($this->owner(), $claim['id'], 'synthetic-address');
        $this->assertSame('code_required', $sent['status']);

        $challengeId = $sent['challenge_id'];
        $original = $this->rawChallenge($challengeId);
        $this->assertNotNull($original);

        // A wrong code increments attempts and saves the row without touching expires_at.
        $this->travel(61)->seconds();
        $this->assertFalse($this->verification->verify($this->owner(), $challengeId, '000000')['phone_verified']);
        $this->assertSame($original, $this->rawChallenge($challengeId), 'a failed attempt moved expires_at');

        // Superseding challenges is a bulk update that never mentions expires_at.
        $this->travel(61)->seconds();
        DB::table('fca_otp_challenges')->where('claim_id', $claim['id'])
            ->whereIn('status', ['sending', 'pending'])->update(['status' => 'superseded']);
        $this->assertSame($original, $this->rawChallenge($challengeId), 'a bulk supersede moved expires_at');

        $this->assertTrue(FcaOtpChallenge::findOrFail($challengeId)->expires_at->isFuture());
    }

    // -----------------------------------------------------------------
    // 3. Updating a president claim does not change its consent timestamp
    // -----------------------------------------------------------------

    public function test_updating_a_president_claim_does_not_change_its_consent_timestamp(): void
    {
        $this->enable();
        $this->addContact();
        $claim = $this->makeClaim();
        $original = $this->rawConsent($claim['id']);
        $this->assertNotNull($original);

        $this->fakeSms();
        $sent = $this->verification->send($this->owner(), $claim['id'], 'synthetic-address');
        $this->travel(61)->seconds();
        $done = $this->verification->verify($this->owner(), $sent['challenge_id'], $this->code);
        $this->assertTrue($done['phone_verified']);

        // verify() moves the claim to phone_verified and increments row_version.
        $row = FcaPresidentClaim::findOrFail($claim['id']);
        $this->assertSame('phone_verified', $row->status);
        $this->assertGreaterThan(1, $row->row_version);
        $this->assertSame($original, $this->rawConsent($claim['id']), 'verification moved the consent record');

        // A later review decision must not move it either.
        $this->travel(120)->seconds();
        $this->verification->reviewClaim($this->reviewer(), $claim['id'], 'dispute', $row->row_version);
        $this->assertSame($original, $this->rawConsent($claim['id']), 'a review decision moved the consent record');
    }

    // -----------------------------------------------------------------
    // 4. Missing or expired deadlines cannot authorise anyone
    // -----------------------------------------------------------------

    public function test_a_deadline_helper_fails_closed_on_missing_or_unusable_values(): void
    {
        $this->assertFalse(Deadline::active(null), 'an unset deadline must not authorise');
        $this->assertTrue(Deadline::expired(null));
        $this->assertFalse(Deadline::active(''));
        $this->assertFalse(Deadline::active('   '));
        $this->assertFalse(Deadline::active('not-a-date'));
        $this->assertFalse(Deadline::active(now()->subDay()));
        $this->assertFalse(Deadline::active(now()->subSecond()));
        $this->assertTrue(Deadline::expired(now()->subDay()));

        $this->assertTrue(Deadline::active(now()->addDay()));
        $this->assertTrue(Deadline::active(now()->addMinute()));
        $this->assertFalse(Deadline::expired(now()->addDay()));
    }

    public function test_an_expired_directory_contact_cannot_authorise_a_claim(): void
    {
        $this->enable();
        $this->addContact('past');

        $claim = $this->makeClaim();
        $row = FcaPresidentClaim::findOrFail($claim['id']);

        // No phone_required: an expired deadline never produces a matchable claim.
        $this->assertNotSame('phone_required', $row->status);
        $this->assertFalse($this->verification->usable($row));
        $this->assertFalse($this->verification->currentProof($row));
    }

    public function test_an_expired_otp_challenge_cannot_be_verified(): void
    {
        $this->enable();
        $this->addContact();
        $claim = $this->makeClaim();
        $this->fakeSms();
        $sent = $this->verification->send($this->owner(), $claim['id'], 'synthetic-address');
        $this->assertSame('code_required', $sent['status']);

        // Past the configured challenge lifetime.
        $this->travel(config('fca_president.expiry_seconds') + 30)->seconds();
        $result = $this->verification->verify($this->owner(), $sent['challenge_id'], $this->code);

        $this->assertFalse($result['phone_verified'], 'an expired challenge must never verify');
        $this->assertSame('verification_unavailable', $result['status']);
        $this->assertSame('phone_required', FcaPresidentClaim::findOrFail($claim['id'])->status);
    }

    public function test_a_proof_without_a_deadline_is_never_usable(): void
    {
        $this->enable();
        $this->addContact();
        $claim = $this->makeClaim();
        $this->fakeSms();
        $sent = $this->verification->send($this->owner(), $claim['id'], 'synthetic-address');
        $this->travel(61)->seconds();
        $this->verification->verify($this->owner(), $sent['challenge_id'], $this->code);

        $row = FcaPresidentClaim::findOrFail($claim['id']);
        $this->assertSame('phone_verified', $row->status);
        $this->assertTrue($this->verification->usable($row), 'a live proof with a future deadline is usable');

        // Removing the proof deadline must revoke usability rather than grant it.
        $row->proof_expires_at = null;
        $row->saveOrFail();
        $this->assertFalse($this->verification->usable($row->fresh()));
    }

    public function test_an_expired_proof_deadline_is_not_usable(): void
    {
        $this->enable();
        $this->addContact();
        $claim = $this->makeClaim();
        $this->fakeSms();
        $sent = $this->verification->send($this->owner(), $claim['id'], 'synthetic-address');
        $this->travel(61)->seconds();
        $this->verification->verify($this->owner(), $sent['challenge_id'], $this->code);

        $row = FcaPresidentClaim::findOrFail($claim['id']);
        $this->assertTrue($this->verification->usable($row));

        $row->proof_expires_at = now()->subDay();
        $row->saveOrFail();
        $this->assertFalse($this->verification->usable($row->fresh()));
    }

    // -----------------------------------------------------------------
    // 5. The corrective migration is idempotent and refuses to reverse
    // -----------------------------------------------------------------

    public function test_the_corrective_migration_is_idempotent_and_refuses_to_reverse(): void
    {
        $migration = require base_path('database/migrations/2026_10_17_000001_fix_fca_implicit_timestamp_deadlines.php');

        // On SQLite up() verifies presence only and changes nothing.
        $migration->up();
        $this->assertTrue(SchemaHas('fca_directory_contacts', 'valid_until'));
        $this->assertTrue(SchemaHas('fca_otp_challenges', 'expires_at'));
        $this->assertTrue(SchemaHas('fca_president_claims', 'consented_at'));

        // down() must refuse rather than restore the ON UPDATE behaviour.
        try {
            $migration->down();
            $this->fail('down() must refuse to restore the defect');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('not reversible', $e->getMessage());
        }
    }
}

if (!function_exists('Tests\Feature\SchemaHas')) {
    function SchemaHas(string $table, string $column): bool
    {
        return \Illuminate\Support\Facades\Schema::hasTable($table)
            && \Illuminate\Support\Facades\Schema::hasColumn($table, $column);
    }
}