<?php

namespace Tests\Feature;

use App\Models\FcaRegistry;
use App\Models\FcaRepresentative;
use App\Models\TransactionNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FcaPreliminaryIntakeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mao_workflows.fca_applications_enabled' => true]);
    }

    private function payload(): array
    {
        $fca = new FcaRegistry;
        $fca->forceFill(['official_name' => 'Synthetic FCA', 'address' => 'Synthetic address', 'president_name' => 'Synthetic directory name', 'directory_verification_status' => 'pending'])->save();

        return ['fca_id' => $fca->id, 'representative_position' => 'delegated', 'authorization_statement' => 'Synthetic applicant and contact for separate review.', 'submission_key' => (string) Str::uuid()];
    }

    public function test_multiple_preliminary_applicants_for_one_association_persist_without_identity_uploads(): void
    {
        $payload = $this->payload();
        $this->postJson('/api/fca/applications/preliminary', $payload)->assertCreated()->assertJsonPath('data.status', 'pending');
        $payload['submission_key'] = (string) Str::uuid();
        $payload['authorization_statement'] = 'Another synthetic applicant';
        $this->postJson('/api/fca/applications/preliminary', $payload)->assertCreated()->assertJsonPath('data.status', 'pending');
        $this->assertDatabaseCount('fca_representatives', 2);
        $this->assertDatabaseCount('fca_evidence', 0);
        $this->assertDatabaseCount('users', 0);
        foreach (FcaRepresentative::all() as $row) {
            $this->assertNull($row->producer_id);
            $this->assertNull($row->reviewed_by);
            $this->assertSame('preliminary', $row->submitted_details['intake_type']);
        }
    }

    public function test_network_retry_replays_same_request_but_changed_payload_conflicts(): void
    {
        $payload = $this->payload();
        $id = $this->postJson('/api/fca/applications/preliminary', $payload)->assertCreated()->json('data.id');
        $this->postJson('/api/fca/applications/preliminary', $payload)->assertCreated()->assertJsonPath('data.id', $id);
        $payload['authorization_statement'] = 'Changed applicant';
        $this->postJson('/api/fca/applications/preliminary', $payload)->assertConflict();
        $this->assertDatabaseCount('fca_representatives', 1);
    }

    public function test_public_response_and_raw_storage_do_not_expose_claimed_identity(): void
    {
        $payload = $this->payload();
        $response = $this->postJson('/api/fca/applications/preliminary', $payload)->assertCreated();
        $this->assertStringNotContainsString($payload['authorization_statement'], $response->getContent());
        $row = FcaRepresentative::firstOrFail();
        $this->assertStringNotContainsString($payload['authorization_statement'], $row->getRawOriginal('submitted_details'));
        $this->getJson('/api/fca/applications/'.$row->id)->assertUnauthorized();
        $this->postJson('/api/fca/review/'.$row->id, ['decision' => 'approve'])->assertUnauthorized();
    }

    public function test_assigned_reviewers_can_reject_preliminary_intake_but_cannot_grant_unverified_authority(): void
    {
        $payload = $this->payload();
        $id = $this->postJson('/api/fca/applications/preliminary', $payload)->assertCreated()->json('data.id');
        $admin = User::create(['first_name' => 'Synthetic', 'last_name' => 'Reviewer', 'email' => 'reviewer@example.test', 'user_type' => 'Admin', 'password_hash' => bcrypt('test-only'), 'is_active' => true]);
        $decision = ['decision' => 'reject', 'decision_key' => (string) Str::uuid(), 'expected_version' => 0, 'review_reason' => 'Synthetic review decision'];
        $this->actingAs($admin, 'sanctum')->postJson('/api/fca/review/'.$id, $decision)->assertForbidden();
        config(['mao_workflows.fca_reviewer_user_ids' => [(int) $admin->user_id]]);
        $this->postJson('/api/fca/review/'.$id, array_merge($decision, ['decision' => 'authorize']))->assertUnprocessable();
        $this->assertDatabaseHas('fca_representatives', ['id' => $id, 'status' => 'pending', 'producer_id' => null]);
        $this->postJson('/api/fca/review/'.$id, $decision)->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->postJson('/api/fca/review/'.$id, $decision)->assertOk();
        $this->assertDatabaseCount('fca_review_events', 1);
        $this->assertDatabaseHas('fca_review_events', ['application_id' => $id, 'actor_user_id' => $admin->user_id, 'event_type' => 'application_rejected']);
        $this->assertDatabaseCount('producers', 0);
    }

    public function test_preliminary_intake_notifies_only_assigned_active_reviewers_once_on_retry(): void
    {
        $reviewer = User::create(['first_name' => 'Synthetic', 'last_name' => 'Reviewer', 'email' => 'assigned@example.test', 'user_type' => 'Admin', 'password_hash' => bcrypt('test-only'), 'is_active' => true]);
        User::create(['first_name' => 'Synthetic', 'last_name' => 'Unassigned', 'email' => 'unassigned@example.test', 'user_type' => 'Admin', 'password_hash' => bcrypt('test-only'), 'is_active' => true]);
        config(['mao_workflows.fca_reviewer_user_ids' => [(int) $reviewer->user_id]]);
        $payload = $this->payload();
        $this->postJson('/api/fca/applications/preliminary', $payload)->assertCreated();
        $this->postJson('/api/fca/applications/preliminary', $payload)->assertCreated();
        $this->assertDatabaseCount('transaction_notifications', 1);
        $this->assertDatabaseHas('transaction_notifications', ['recipient_user_id' => $reviewer->user_id]);
        $this->assertStringNotContainsString($payload['authorization_statement'], TransactionNotification::firstOrFail()->body);
    }
}
