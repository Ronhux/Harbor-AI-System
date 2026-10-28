<?php

namespace Tests\Feature;

use App\Models\KadiwaApplication;
use App\Models\KadiwaApplicationStatusEvent;
use App\Models\KadiwaEvent;
use App\Models\KadiwaEventAudit;
use App\Models\KadiwaEventCoordinator;
use App\Models\KadiwaInquiry;
use App\Models\KadiwaParticipationReview;
use App\Models\Producer;
use App\Models\RegisteredProducer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * KADIWA end-to-end behaviour.
 *
 * KADIWA facilitates direct producer-to-customer selling at published events. These tests
 * assert that it never behaves as an institutional purchasing process, that participation is
 * limited to Admin-approved producer accounts, that administrative authority comes only from
 * an explicit assignment, and that every decision leaves an auditable record.
 */
class KadiwaParticipationTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------
    // Fixtures
    // -----------------------------------------------------------------

    private function enable(): void
    {
        config([
            'mao_workflows.kadiwa_enabled' => true,
            'mao_workflows.kadiwa_manager_user_ids' => [],
            'mao_workflows.kadiwa_inquiry_reviewer_user_ids' => [],
        ]);
    }

    private function makeUser(string $type = 'Farmer', bool $active = true): User
    {
        static $seq = 0;

        return User::create([
            'first_name' => 'Test',
            'last_name' => (string) ++$seq,
            'email' => sprintf('kadiwa-%s-%d@test.local', strtolower($type), $seq),
            'password_hash' => bcrypt('password'),
            'contact_number' => sprintf('0918%07d', $seq),
            'user_type' => $type,
            'is_active' => $active,
        ]);
    }

    private function makeProducer(User $user, string $status = 'Verified', ?string $rsbsa = null): Producer
    {
        return Producer::create([
            'user_id' => $user->user_id,
            'verification_status' => $status,
            'rsbsa_number' => $rsbsa,
        ]);
    }

    private function registryRow(string $rsbsaNumber): void
    {
        RegisteredProducer::create([
            'rsbsa_number' => $rsbsaNumber,
            'full_name' => 'Registry producer',
            'municipality' => 'Aparri',
            'province' => 'Cagayan',
            'status' => 'Registered',
        ]);
    }

    private function makeManager(): User
    {
        $user = $this->makeUser('Admin');
        config(['mao_workflows.kadiwa_manager_user_ids' => [$user->user_id]]);

        return $user;
    }

    private function makeCoordinator(): User
    {
        return $this->makeUser('Admin');
    }

    /** Publishes an event through the real management endpoints. */
    private function publishEvent(User $manager, array $overrides = []): KadiwaEvent
    {
        $payload = array_merge([
            'title' => 'KADIWA direct selling venue',
            'description' => 'Producers sell directly to customers at this venue.',
            'venue' => 'Municipal market hall',
            'location' => 'Aparri, Cagayan',
            'opens_at' => now()->subDay()->toDateTimeString(),
            'closes_at' => now()->addDays(5)->toDateTimeString(),
            'starts_at' => now()->addDays(10)->toDateTimeString(),
            'ends_at' => now()->addDays(10)->addHours(8)->toDateTimeString(),
            'capacity' => 3,
            'categories' => ['Vegetables'],
            'requirements' => ['Bring your own display materials'],
            'rsbsa_required' => false,
            'evidence_required' => false,
            'operation_key' => (string) Str::uuid(),
        ], $overrides);

        $created = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/kadiwa/events', $payload)->assertCreated()->json();
        $id = $created['id'];

        $open = $this->actingAs($manager, 'sanctum')
            ->postJson("/api/kadiwa/events/{$id}/status", [
                'status' => 'open', 'version' => 0, 'operation_key' => (string) Str::uuid(),
            ])->assertOk()->json();

        return KadiwaEvent::findOrFail($id);
    }

    private function applicationPayload(array $overrides = []): array
    {
        return array_merge([
            'submission_key' => (string) Str::uuid(),
            'commodities' => [
                ['commodity' => 'Cabbage', 'category' => 'Vegetables', 'quantity' => '25.000', 'unit' => 'kg'],
            ],
            'participation_notes' => 'Display and transport plan.',
        ], $overrides);
    }

    // -----------------------------------------------------------------
    // Feature gate
    // -----------------------------------------------------------------

    public function test_every_kadiwa_route_is_closed_while_the_flag_is_false(): void
    {
        config(['mao_workflows.kadiwa_enabled' => false]);
        $producer = $this->makeProducer($this->makeUser());

        foreach ([
            ['getJson', '/api/kadiwa/regular-schedule'],
            ['getJson', '/api/kadiwa/capabilities'],
            ['getJson', '/api/kadiwa/events'],
            ['getJson', '/api/kadiwa/applications'],
            ['getJson', '/api/kadiwa/assigned'],
            ['postJson', '/api/kadiwa/events'],
            ['postJson', '/api/kadiwa/inquiries'],
            ['getJson', '/api/kadiwa/inquiry-review'],
        ] as [$verb, $uri]) {
            $this->actingAs($producer->user, 'sanctum')->{$verb}($uri)->assertNotFound();
        }
    }

    public function test_the_projected_schedule_never_asserts_a_confirmed_event(): void
    {
        $this->enable();
        $response = $this->actingAs($this->makeProducer($this->makeUser())->user, 'sanctum')
            ->getJson('/api/kadiwa/regular-schedule')->assertOk()->json();

        $this->assertSame(3, $response['rule']['ordinal']);
        $this->assertSame(1, $response['rule']['iso_weekday']);
        $this->assertSame(1, $response['rule']['following_day_offset']);
        $this->assertNotEmpty($response['notice']);

        $this->assertNotEmpty($response['dates']);
        $seen = [];
        foreach ($response['dates'] as $date) {
            // Every projected date is explicitly unconfirmed and carries no event detail.
            $this->assertSame('projected', $date['kind']);
            $this->assertFalse($date['confirmed']);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $date['date']);
            $this->assertGreaterThan(now()->toDateString(), $date['date']);
            $seen[] = $date['date'];
        }
        // Third Monday of a month is never the 29th-31st, and the list stays chronological.
        foreach ($seen as $index => $date) {
            $this->assertLessThanOrEqual(7, (int) date('N', strtotime($date)));
            if ($index > 0) $this->assertGreaterThan($seen[$index - 1], $date);
        }
    }

    // -----------------------------------------------------------------
    // Authorization
    // -----------------------------------------------------------------

    public function test_unauthenticated_and_buyer_requests_are_refused(): void
    {
        $this->enable();
        $this->postJson('/api/kadiwa/events', [])->assertUnauthorized();

        $buyer = $this->makeUser('Buyer');
        $this->actingAs($buyer, 'sanctum')->getJson('/api/kadiwa/applications')->assertForbidden();
        $this->actingAs($buyer, 'sanctum')->postJson('/api/kadiwa/inquiries', [
            'locality' => 'Aparri', 'message' => 'May I sell my vegetables here?',
        ])->assertForbidden();
    }

    public function test_an_administrator_role_alone_grants_no_kadiwa_authority(): void
    {
        $this->enable();
        $admin = $this->makeUser('Admin');

        $capabilities = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/kadiwa/capabilities')->assertOk()->json();
        $this->assertFalse($capabilities['manager']);
        $this->assertFalse($capabilities['coordinator']);
        $this->assertFalse($capabilities['inquiry_reviewer']);
        $this->assertSame([], $capabilities['assigned_event_ids']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/kadiwa/events', $this->eventPayload())
            ->assertForbidden();
        $this->actingAs($admin, 'sanctum')->getJson('/api/kadiwa/assigned')->assertForbidden();
        $this->actingAs($admin, 'sanctum')->getJson('/api/kadiwa/inquiry-review')->assertForbidden();
    }

    public function test_a_producer_cannot_manage_events_or_review_participation(): void
    {
        $this->enable();
        $manager = $this->makeManager();
        $event = $this->publishEvent($manager);
        $producerUser = $this->makeUser();
        $producer = $this->makeProducer($producerUser);

        $application = $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/kadiwa/events/{$event->id}/applications", $this->applicationPayload())
            ->assertCreated()->json();

        // A producer is not an administrator and cannot be assigned as a coordinator.
        $this->actingAs($producerUser, 'sanctum')->postJson("/api/kadiwa/events/{$event->id}/status", [
            'status' => 'closed', 'version' => 1, 'operation_key' => (string) Str::uuid(),
        ])->assertForbidden();

        $this->actingAs($producerUser, 'sanctum')
            ->getJson("/api/kadiwa/events/{$event->id}/queue")->assertForbidden();

        // A producer can never approve their own participation.
        $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/kadiwa/applications/{$application['id']}/review", [
                'decision' => 'approve', 'reason' => 'Self approval',
                'version' => 0, 'operation_key' => (string) Str::uuid(),
            ])->assertForbidden();

        $this->assertSame('pending', KadiwaApplication::findOrFail($application['id'])->status);
        $this->assertSame(0, KadiwaParticipationReview::count());
    }

    public function test_a_coordinator_reaches_only_the_event_they_are_assigned_to(): void
    {
        $this->enable();
        $manager = $this->makeManager();
        $coordinator = $this->makeCoordinator();
        $other = $this->makeCoordinator();

        $assignedEvent = $this->publishEvent($manager, ['title' => 'Assigned venue']);
        $unassignedEvent = $this->publishEvent($manager, ['title' => 'Unassigned venue']);

        $this->actingAs($manager, 'sanctum')->postJson("/api/kadiwa/events/{$assignedEvent->id}/coordinators", [
            'user_id' => $coordinator->user_id, 'active' => true,
        ])->assertOk();

        $capabilities = $this->actingAs($coordinator, 'sanctum')
            ->getJson('/api/kadiwa/capabilities')->assertOk()->json();
        $this->assertTrue($capabilities['coordinator']);
        $this->assertFalse($capabilities['manager']);
        $this->assertSame([(int) $assignedEvent->id], $capabilities['assigned_event_ids']);

        $this->actingAs($coordinator, 'sanctum')->getJson("/api/kadiwa/events/{$assignedEvent->id}/queue")
            ->assertOk();
        $this->actingAs($coordinator, 'sanctum')->getJson("/api/kadiwa/events/{$unassignedEvent->id}/queue")
            ->assertForbidden();

        // A coordinator manages nothing: publication stays with the manager assignment.
        $this->actingAs($coordinator, 'sanctum')
            ->postJson("/api/kadiwa/events/{$unassignedEvent->id}/status", [
                'status' => 'closed', 'version' => 1, 'operation_key' => (string) Str::uuid(),
            ])->assertForbidden();
    }

    public function test_a_coordinator_assignment_cannot_promote_an_inactive_or_non_admin_account(): void
    {
        $this->enable();
        $manager = $this->makeManager();
        $event = $this->publishEvent($manager);
        $inactive = $this->makeUser('Admin', false);
        $producer = $this->makeUser('Farmer');

        foreach ([$inactive, $producer] as $candidate) {
            $this->actingAs($manager, 'sanctum')
                ->postJson("/api/kadiwa/events/{$event->id}/coordinators", [
                    'user_id' => $candidate->user_id, 'active' => true,
                ])->assertStatus(422);
        }

        $this->assertSame(0, KadiwaEventCoordinator::count());
    }

    // -----------------------------------------------------------------
    // Publication and management
    // -----------------------------------------------------------------

    public function test_an_event_is_saved_as_a_draft_and_publication_is_a_separate_decision(): void
    {
        $this->enable();
        $manager = $this->makeManager();

        $draft = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/kadiwa/events', $this->eventPayload())->assertCreated()->json();

        $this->assertSame('draft', $draft['status']);
        $this->assertSame(0, $draft['row_version']);
        // A draft is never a participation opportunity.
        $published = $this->actingAs($this->makeProducer($this->makeUser())->user, 'sanctum')
            ->getJson('/api/kadiwa/events')->assertOk()->json();
        $this->assertSame([], $published);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/kadiwa/events/{$draft['id']}/status", [
                'status' => 'open', 'version' => 0, 'operation_key' => (string) Str::uuid(),
            ])->assertOk()->assertJsonPath('status', 'open')->assertJsonPath('row_version', 1);

        $this->assertSame(
            ['created', 'published'],
            KadiwaEventAudit::where('event_id', $draft['id'])->orderBy('id')->pluck('action')->all()
        );
    }

    public function test_only_permitted_status_transitions_are_accepted(): void
    {
        $this->enable();
        $manager = $this->makeManager();
        $event = $this->publishEvent($manager);
        $id = $event->id;

        // draft -> open -> closed -> ongoing -> completed
        $this->actingAs($manager, 'sanctum')->postJson("/api/kadiwa/events/{$id}/status", [
            'status' => 'completed', 'version' => 1, 'operation_key' => (string) Str::uuid(),
        ])->assertStatus(422);
        $this->actingAs($manager, 'sanctum')->postJson("/api/kadiwa/events/{$id}/status", [
            'status' => 'closed', 'version' => 1, 'operation_key' => (string) Str::uuid(),
        ])->assertOk()->assertJsonPath('status', 'closed');
        $this->actingAs($manager, 'sanctum')->postJson("/api/kadiwa/events/{$id}/status", [
            'status' => 'open', 'version' => 2, 'operation_key' => (string) Str::uuid(),
        ])->assertStatus(422);
        $this->actingAs($manager, 'sanctum')->postJson("/api/kadiwa/events/{$id}/status", [
            'status' => 'ongoing', 'version' => 2, 'operation_key' => (string) Str::uuid(),
        ])->assertOk()->assertJsonPath('status', 'ongoing');

        // A stale version never overwrites a current record.
        $this->actingAs($manager, 'sanctum')->postJson("/api/kadiwa/events/{$id}/status", [
            'status' => 'completed', 'version' => 2, 'operation_key' => (string) Str::uuid(),
        ])->assertStatus(409);
    }

    public function test_rescheduling_returns_the_event_to_draft_and_records_the_reason(): void
    {
        $this->enable();
        $manager = $this->makeManager();
        $event = $this->publishEvent($manager);
        $producerUser = $this->makeUser();
        $this->makeProducer($producerUser);
        $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/kadiwa/events/{$event->id}/applications", $this->applicationPayload())
            ->assertCreated();

        $rescheduled = $this->actingAs($manager, 'sanctum')
            ->postJson("/api/kadiwa/events/{$event->id}/reschedule", [
                'venue' => 'New municipal market hall',
                'location' => 'Aparri, Cagayan',
                'opens_at' => now()->subDay()->toDateTimeString(),
                'closes_at' => now()->addDays(7)->toDateTimeString(),
                'starts_at' => now()->addDays(14)->toDateTimeString(),
                'ends_at' => now()->addDays(14)->addHours(8)->toDateTimeString(),
                'reason' => 'Venue unavailable on the original date.',
                'version' => 1,
                'operation_key' => (string) Str::uuid(),
            ])->assertOk()->json();

        // Republication is required, so producers cannot act on changed dates.
        $this->assertSame('draft', $rescheduled['status']);
        $this->assertSame(2, $rescheduled['row_version']);
        $this->assertSame(
            ['Venue unavailable on the original date.'],
            KadiwaEventAudit::where('event_id', $event->id)->where('action', 'rescheduled')
                ->pluck('reason')->all()
        );
    }

    public function test_cancelling_an_event_cancels_its_live_applications_with_a_record(): void
    {
        $this->enable();
        $manager = $this->makeManager();
        $event = $this->publishEvent($manager);
        $producerUser = $this->makeUser();
        $this->makeProducer($producerUser);
        $application = $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/kadiwa/events/{$event->id}/applications", $this->applicationPayload())
            ->assertCreated()->json();

        $this->actingAs($manager, 'sanctum')->postJson("/api/kadiwa/events/{$event->id}/status", [
            'status' => 'cancelled', 'version' => 1, 'operation_key' => (string) Str::uuid(),
        ])->assertOk()->assertJsonPath('status', 'cancelled');

        $this->assertSame('cancelled', KadiwaApplication::findOrFail($application['id'])->status);
        $this->assertTrue(
            KadiwaApplicationStatusEvent::where('application_id', $application['id'])
                ->where('action', 'event_cancelled')->exists()
        );
        // A cancelled event stays visible so producers see it, but opens no participation.
        $listed = $this->actingAs($producerUser, 'sanctum')->getJson('/api/kadiwa/events')->assertOk()->json();
        $this->assertSame(['cancelled'], array_column($listed, 'status'));
        $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/kadiwa/events/{$event->id}/applications", $this->applicationPayload())
            ->assertStatus(422);
    }

    public function test_a_draft_cannot_be_edited_through_the_schedule_editing_path(): void
    {
        $this->enable();
        $manager = $this->makeManager();
        $created = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/kadiwa/events', $this->eventPayload())->assertCreated()->json();

        $this->actingAs($manager, 'sanctum')
            ->patchJson("/api/kadiwa/events/{$created['id']}", [
                'title' => 'Renamed venue', 'description' => 'Updated description.',
                'capacity' => 5, 'categories' => ['Vegetables'], 'requirements' => [],
                'rsbsa_required' => false, 'evidence_required' => false, 'version' => 0,
                'starts_at' => now()->addYear()->toDateTimeString(),
            ])->assertStatus(422);

        $this->assertSame('KADIWA venue', KadiwaEvent::findOrFail($created['id'])->title);
    }

    // -----------------------------------------------------------------
    // Participation eligibility
    // -----------------------------------------------------------------

    public function test_participation_requires_an_admin_approved_producer_account(): void
    {
        $this->enable();
        $manager = $this->makeManager();
        $event = $this->publishEvent($manager);

        $pendingUser = $this->makeUser();
        $this->makeProducer($pendingUser, 'Pending');
        $this->actingAs($pendingUser, 'sanctum')
            ->postJson("/api/kadiwa/events/{$event->id}/applications", $this->applicationPayload())
            ->assertForbidden();

        // An approved account with no producer record cannot participate either.
        $noRecord = $this->makeUser();
        $this->actingAs($noRecord, 'sanctum')
            ->postJson("/api/kadiwa/events/{$event->id}/applications", $this->applicationPayload())
            ->assertForbidden();

        $inactive = $this->makeUser('Farmer', false);
        $this->makeProducer($inactive);
        $this->actingAs($inactive, 'sanctum')
            ->postJson("/api/kadiwa/events/{$event->id}/applications", $this->applicationPayload())
            ->assertForbidden();

        $this->assertSame(0, KadiwaApplication::count());
    }

    public function test_an_rsbsa_required_event_requires_a_registry_matched_producer(): void
    {
        $this->enable();
        $manager = $this->makeManager();
        $this->registryRow('RSBSA-0001');
        $event = $this->publishEvent($manager, ['rsbsa_required' => true]);

        $unmatched = $this->makeUser();
        $this->makeProducer($unmatched, 'Verified', 'RSBSA-9999');
        $this->actingAs($unmatched, 'sanctum')
            ->postJson("/api/kadiwa/events/{$event->id}/applications", $this->applicationPayload())
            ->assertStatus(422);

        $matched = $this->makeUser();
        $this->makeProducer($matched, 'Verified', 'RSBSA-0001');
        $this->actingAs($matched, 'sanctum')
            ->postJson("/api/kadiwa/events/{$event->id}/applications", $this->applicationPayload())
            ->assertCreated();

        $this->assertSame(1, KadiwaApplication::count());
    }

    public function test_an_evidence_required_event_stays_closed_to_applications(): void
    {
        $this->enable();
        $manager = $this->makeManager();
        $event = $this->publishEvent($manager, ['evidence_required' => true]);
        $producerUser = $this->makeUser();
        $this->makeProducer($producerUser);

        $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/kadiwa/events/{$event->id}/applications", $this->applicationPayload())
            ->assertStatus(503);

        $this->assertSame(0, KadiwaApplication::count());
    }

    public function test_application_payload_is_validated_against_the_published_event(): void
    {
        $this->enable();
        $manager = $this->makeManager();
        $event = $this->publishEvent($manager, ['categories' => ['Vegetables']]);
        $producerUser = $this->makeUser();
        $this->makeProducer($producerUser);

        // A category the event never published is refused.
        $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/kadiwa/events/{$event->id}/applications", $this->applicationPayload([
                'commodities' => [
                    ['commodity' => 'Tilapia', 'category' => 'Fish', 'quantity' => '10', 'unit' => 'kg'],
                ],
            ]))->assertStatus(422);

        // Duplicate commodity lines in one application are refused.
        $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/kadiwa/events/{$event->id}/applications", $this->applicationPayload([
                'commodities' => [
                    ['commodity' => 'Cabbage', 'category' => 'Vegetables', 'quantity' => '5', 'unit' => 'kg'],
                    ['commodity' => ' cabbage ', 'category' => 'Vegetables', 'quantity' => '6', 'unit' => 'kg'],
                ],
            ]))->assertStatus(422);

        // The producer cannot set a status or hijack the event/producer binding.
        $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/kadiwa/events/{$event->id}/applications",
                $this->applicationPayload(['status' => 'approved', 'producer_id' => 9999]))
            ->assertStatus(422);

        $this->assertSame(0, KadiwaApplication::count());
    }

    public function test_applications_outside_the_published_window_are_refused(): void
    {
        $this->enable();
        $manager = $this->makeManager();
        $event = $this->publishEvent($manager, [
            'opens_at' => now()->addDay()->toDateTimeString(),
            'closes_at' => now()->addDays(4)->toDateTimeString(),
        ]);
        $producerUser = $this->makeUser();
        $this->makeProducer($producerUser);

        $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/kadiwa/events/{$event->id}/applications", $this->applicationPayload())
            ->assertStatus(422);
    }

    // -----------------------------------------------------------------
    // Duplicate applications
    // -----------------------------------------------------------------

    public function test_a_second_live_application_for_the_same_event_is_refused(): void
    {
        $this->enable();
        $manager = $this->makeManager();
        $event = $this->publishEvent($manager);
        $producerUser = $this->makeUser();
        $this->makeProducer($producerUser);

        $first = $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/kadiwa/events/{$event->id}/applications", $this->applicationPayload())
            ->assertCreated()->json();

        $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/kadiwa/events/{$event->id}/applications", $this->applicationPayload())
            ->assertStatus(409);

        // The same submission key with the same details replays to the same record.
        $replay = $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/kadiwa/events/{$event->id}/applications", [
                'submission_key' => KadiwaApplication::findOrFail($first['id'])->submission_key,
                'commodities' => [['commodity' => 'Cabbage', 'category' => 'Vegetables', 'quantity' => '25.000', 'unit' => 'kg']],
                'participation_notes' => 'Display and transport plan.',
            ])->assertCreated()->json();
        $this->assertSame($first['id'], $replay['id']);

        // The same key with different details is a conflict, not a silent overwrite.
        $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/kadiwa/events/{$event->id}/applications", [
                'submission_key' => KadiwaApplication::findOrFail($first['id'])->submission_key,
                'commodities' => [['commodity' => 'Pechay', 'category' => 'Vegetables', 'quantity' => '5', 'unit' => 'kg']],
            ])->assertStatus(409);

        $this->assertSame(1, KadiwaApplication::count());
    }

    // -----------------------------------------------------------------
    // Review and status transitions
    // -----------------------------------------------------------------

    public function test_an_assigned_coordinator_records_a_documented_approval(): void
    {
        $this->enable();
        $manager = $this->makeManager();
        $coordinator = $this->makeCoordinator();
        $event = $this->publishEvent($manager);
        $this->actingAs($manager, 'sanctum')->postJson("/api/kadiwa/events/{$event->id}/coordinators", [
            'user_id' => $coordinator->user_id, 'active' => true,
        ])->assertOk();

        $producerUser = $this->makeUser();
        $this->makeProducer($producerUser);
        $application = $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/kadiwa/events/{$event->id}/applications", $this->applicationPayload())
            ->assertCreated()->json();

        $approved = $this->actingAs($coordinator, 'sanctum')
            ->postJson("/api/kadiwa/applications/{$application['id']}/review", [
                'decision' => 'approve', 'reason' => 'Produce displayed at the assigned stall.',
                'version' => 0, 'operation_key' => (string) Str::uuid(),
            ])->assertOk()->json();
        $this->assertSame('approved', $approved['status']);
        $this->assertSame(1, $approved['row_version']);

        // The producer reads the decision and its documented reason.
        $detail = $this->actingAs($producerUser, 'sanctum')
            ->getJson("/api/kadiwa/applications/{$application['id']}")->assertOk()->json();
        $this->assertSame(['Produce displayed at the assigned stall.'], array_column($detail['review_notes'], 'reason'));
        $this->assertSame(['submitted', 'approve'], array_column($detail['history'], 'action'));
        $this->assertNull($detail['history'][0]['previous_status']);
        $this->assertSame('pending', $detail['history'][0]['new_status']);
        $this->assertSame('pending', $detail['history'][1]['previous_status']);
        $this->assertSame('approved', $detail['history'][1]['new_status']);

        // Nothing producer-identifying is present in the coordinator queue.
        $queue = $this->actingAs($coordinator, 'sanctum')
            ->getJson("/api/kadiwa/events/{$event->id}/queue")->assertOk()->json();
        $this->assertSame(1, $queue['approved_count']);
        $this->assertSame(2, $queue['available_slots']);
        $this->assertSame(['id', 'event_id', 'status', 'row_version', 'created_at'], array_keys($queue['applications'][0]));
    }

    public function test_approval_stops_at_the_published_capacity(): void
    {
        $this->enable();
        $manager = $this->makeManager();
        $coordinator = $this->makeCoordinator();
        $event = $this->publishEvent($manager, ['capacity' => 1]);
        $this->actingAs($manager, 'sanctum')->postJson("/api/kadiwa/events/{$event->id}/coordinators", [
            'user_id' => $coordinator->user_id, 'active' => true,
        ])->assertOk();

        $applications = [];
        foreach ([1, 2] as $index) {
            $user = $this->makeUser();
            $this->makeProducer($user);
            $applications[] = $this->actingAs($user, 'sanctum')
                ->postJson("/api/kadiwa/events/{$event->id}/applications", $this->applicationPayload())
                ->assertCreated()->json();
        }

        $this->actingAs($coordinator, 'sanctum')
            ->postJson("/api/kadiwa/applications/{$applications[0]['id']}/review", [
                'decision' => 'approve', 'reason' => 'First approved producer.',
                'version' => 0, 'operation_key' => (string) Str::uuid(),
            ])->assertOk();

        // The second approval is refused; no capacity is silently oversubscribed.
        $this->actingAs($coordinator, 'sanctum')
            ->postJson("/api/kadiwa/applications/{$applications[1]['id']}/review", [
                'decision' => 'approve', 'reason' => 'Second approved producer.',
                'version' => 0, 'operation_key' => (string) Str::uuid(),
            ])->assertStatus(409);

        $queue = $this->actingAs($coordinator, 'sanctum')
            ->getJson("/api/kadiwa/events/{$event->id}/queue")->assertOk()->json();
        $this->assertSame(1, $queue['approved_count']);
        $this->assertSame(0, $queue['available_slots']);
        $this->assertSame('pending', KadiwaApplication::findOrFail($applications[1]['id'])->status);
    }

    public function test_a_stale_version_or_reused_operation_key_cannot_double_decide(): void
    {
        $this->enable();
        $manager = $this->makeManager();
        $event = $this->publishEvent($manager);
        $producerUser = $this->makeUser();
        $this->makeProducer($producerUser);
        $application = $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/kadiwa/events/{$event->id}/applications", $this->applicationPayload())
            ->assertCreated()->json();

        $key = (string) Str::uuid();
        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/kadiwa/applications/{$application['id']}/review", [
                'decision' => 'approve', 'reason' => 'Approved at the assigned stall.',
                'version' => 0, 'operation_key' => $key,
            ])->assertOk();

        // A stale version is refused rather than re-deciding the record.
        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/kadiwa/applications/{$application['id']}/review", [
                'decision' => 'reject', 'reason' => 'Changed my mind.',
                'version' => 0, 'operation_key' => (string) Str::uuid(),
            ])->assertStatus(409);

        // A replay of the same operation key does not add a second decision.
        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/kadiwa/applications/{$application['id']}/review", [
                'decision' => 'approve', 'reason' => 'Approved at the assigned stall.',
                'version' => 1, 'operation_key' => $key,
            ])->assertOk();

        $this->assertSame(1, KadiwaParticipationReview::count());
        $this->assertSame('approved', KadiwaApplication::findOrFail($application['id'])->status);
    }

    public function test_requested_information_allows_one_resubmission(): void
    {
        $this->enable();
        $manager = $this->makeManager();
        $event = $this->publishEvent($manager);
        $producerUser = $this->makeUser();
        $this->makeProducer($producerUser);
        $application = $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/kadiwa/events/{$event->id}/applications", $this->applicationPayload())
            ->assertCreated()->json();

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/kadiwa/applications/{$application['id']}/review", [
                'decision' => 'request_information', 'reason' => 'State the transport schedule.',
                'version' => 0, 'operation_key' => (string) Str::uuid(),
            ])->assertOk()->assertJsonPath('status', 'needs_information');

        // Resubmission is only possible against a documented information request.
        $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/kadiwa/applications/{$application['id']}/resubmit", [
                'commodities' => [['commodity' => 'Cabbage', 'category' => 'Vegetables', 'quantity' => '30.000', 'unit' => 'kg']],
                'participation_notes' => 'Transport arrives at 05:00.',
                'version' => 1, 'operation_key' => (string) Str::uuid(),
            ])->assertOk()->assertJsonPath('status', 'pending')->assertJsonPath('row_version', 2);

        // And only once, from the producer who owns it.
        $other = $this->makeUser();
        $this->makeProducer($other);
        $this->actingAs($other, 'sanctum')
            ->postJson("/api/kadiwa/applications/{$application['id']}/resubmit", [
                'commodities' => [['commodity' => 'Cabbage', 'category' => 'Vegetables', 'quantity' => '1', 'unit' => 'kg']],
                'version' => 2, 'operation_key' => (string) Str::uuid(),
            ])->assertForbidden();
    }

    public function test_a_producer_can_withdraw_only_while_the_application_can_still_change(): void
    {
        $this->enable();
        $manager = $this->makeManager();
        $event = $this->publishEvent($manager);
        $producerUser = $this->makeUser();
        $this->makeProducer($producerUser);
        $application = $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/kadiwa/events/{$event->id}/applications", $this->applicationPayload())
            ->assertCreated()->json();

        $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/kadiwa/applications/{$application['id']}/review", [
                'decision' => 'withdraw', 'reason' => 'Crop failed this week.',
                'version' => 0, 'operation_key' => (string) Str::uuid(),
            ])->assertOk()->assertJsonPath('status', 'withdrawn');

        // A withdrawn application cannot be resubmitted or withdrawn again.
        $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/kadiwa/applications/{$application['id']}/review", [
                'decision' => 'withdraw', 'reason' => 'Changed my mind.',
                'version' => 1, 'operation_key' => (string) Str::uuid(),
            ])->assertStatus(409);

        $detail = $this->actingAs($producerUser, 'sanctum')
            ->getJson("/api/kadiwa/applications/{$application['id']}")->assertOk()->json();
        $this->assertSame('withdrawn', $detail['application']['status']);
        $this->assertSame(['submitted', 'withdraw'], array_column($detail['history'], 'action'));
    }

    public function test_a_manager_cannot_withdraw_on_a_producer_behalf(): void
    {
        $this->enable();
        $manager = $this->makeManager();
        $event = $this->publishEvent($manager);
        $producerUser = $this->makeUser();
        $this->makeProducer($producerUser);
        $application = $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/kadiwa/events/{$event->id}/applications", $this->applicationPayload())
            ->assertCreated()->json();

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/kadiwa/applications/{$application['id']}/review", [
                'decision' => 'withdraw', 'reason' => 'Manager initiated.',
                'version' => 0, 'operation_key' => (string) Str::uuid(),
            ])->assertForbidden();

        $this->assertSame('pending', KadiwaApplication::findOrFail($application['id'])->status);
    }

    // -----------------------------------------------------------------
    // Data isolation
    // -----------------------------------------------------------------

    public function test_a_producer_never_sees_another_producers_application(): void
    {
        $this->enable();
        $manager = $this->makeManager();
        $event = $this->publishEvent($manager);
        $ownerUser = $this->makeUser();
        $this->makeProducer($ownerUser);
        $application = $this->actingAs($ownerUser, 'sanctum')
            ->postJson("/api/kadiwa/events/{$event->id}/applications", $this->applicationPayload())
            ->assertCreated()->json();

        $stranger = $this->makeUser();
        $this->makeProducer($stranger);
        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/kadiwa/applications/{$application['id']}")->assertForbidden();
        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/kadiwa/applications/{$application['id']}/review", [
                'decision' => 'approve', 'reason' => 'Not my record.',
                'version' => 0, 'operation_key' => (string) Str::uuid(),
            ])->assertForbidden();

        // Each account's list is owner-scoped.
        $this->assertSame([$application['id']], array_column(
            $this->actingAs($ownerUser, 'sanctum')->getJson('/api/kadiwa/applications')->json(), 'id'
        ));
        $this->assertSame([], $this->actingAs($stranger, 'sanctum')->getJson('/api/kadiwa/applications')->json());
    }

    public function test_empty_accounts_return_genuine_empty_states(): void
    {
        $this->enable();
        $producerUser = $this->makeUser();
        $this->makeProducer($producerUser);
        $producer = $producerUser;

        $this->assertSame([], $this->actingAs($producer, 'sanctum')->getJson('/api/kadiwa/events')->json());
        $this->assertSame([], $this->actingAs($producer, 'sanctum')->getJson('/api/kadiwa/applications')->json());

        $inquiries = $this->actingAs($producer, 'sanctum')->getJson('/api/kadiwa/inquiries')->assertOk()->json();
        $this->assertSame([], $inquiries['data']);
        $this->assertSame(1, $inquiries['current_page']);
        $this->assertSame(1, $inquiries['last_page']);
        $this->assertSame(0, $inquiries['total']);
    }

    // -----------------------------------------------------------------
    // Inquiries
    // -----------------------------------------------------------------

    public function test_an_inquiry_never_approves_participation(): void
    {
        $this->enable();
        $manager = $this->makeManager();
        config(['mao_workflows.kadiwa_inquiry_reviewer_user_ids' => [$manager->user_id]]);

        $producerUser = $this->makeUser();
        $this->makeProducer($producerUser);

        $inquiry = $this->actingAs($producerUser, 'sanctum')
            ->postJson('/api/kadiwa/inquiries', [
                'locality' => 'Allacapan',
                'message' => 'Is there a KADIWA venue in my municipality this year?',
                'submission_key' => (string) Str::uuid(),
            ])->assertCreated()->json();
        $this->assertFalse($inquiry['participation_approved']);
        $this->assertSame('pending', $inquiry['status']);

        $answered = $this->actingAs($manager, 'sanctum')
            ->postJson("/api/kadiwa/inquiry-review/{$inquiry['id']}", [
                'response' => 'No event is scheduled for that locality at present.',
                'status' => 'answered', 'version' => 0, 'operation_key' => (string) Str::uuid(),
            ])->assertOk()->json();
        $this->assertSame('answered', $answered['status']);
        $this->assertFalse($answered['participation_approved']);

        // No event and no application came into existence from the inquiry.
        $this->assertSame(0, KadiwaEvent::count());
        $this->assertSame(0, KadiwaApplication::count());
    }

    public function test_an_inquiry_is_private_to_its_owner_and_needs_a_review_assignment(): void
    {
        $this->enable();
        $owner = $this->makeUser();
        $this->makeProducer($owner);
        $stranger = $this->makeUser();
        $this->makeProducer($stranger);

        $inquiry = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/kadiwa/inquiries', [
                'locality' => 'Aparri',
                'message' => 'Please advise on the next venue schedule.',
                'submission_key' => (string) Str::uuid(),
            ])->assertCreated()->json();

        $strangerPage = $this->actingAs($stranger, 'sanctum')->getJson('/api/kadiwa/inquiries')->json();
        $this->assertSame([], $strangerPage['data']);

        $unassigned = $this->makeUser('Admin');
        $this->actingAs($unassigned, 'sanctum')->getJson('/api/kadiwa/inquiry-review')->assertForbidden();
        $this->actingAs($unassigned, 'sanctum')
            ->postJson("/api/kadiwa/inquiry-review/{$inquiry['id']}", [
                'response' => 'Trying to answer without an assignment.',
                'status' => 'answered', 'version' => 0, 'operation_key' => (string) Str::uuid(),
            ])->assertForbidden();

        $this->assertSame('pending', KadiwaInquiry::findOrFail($inquiry['id'])->status);
    }

    // -----------------------------------------------------------------
    // Business-rule guard
    // -----------------------------------------------------------------

    public function test_kadiwa_creates_no_purchase_order_award_or_payment(): void
    {
        $this->enable();
        $manager = $this->makeManager();
        $event = $this->publishEvent($manager);
        $producerUser = $this->makeUser();
        $this->makeProducer($producerUser);
        $application = $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/kadiwa/events/{$event->id}/applications", $this->applicationPayload())
            ->assertCreated()->json();
        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/kadiwa/applications/{$application['id']}/review", [
                'decision' => 'approve', 'reason' => 'Approved to sell at the venue.',
                'version' => 0, 'operation_key' => (string) Str::uuid(),
            ])->assertOk();

        foreach (['orders', 'negotiations', 'demand_requests', 'transaction_notifications'] as $table) {
            $this->assertSame(0, \Illuminate\Support\Facades\DB::table($table)->count(),
                "KADIWA must not create rows in {$table}.");
        }
        // The producer's own commercial state is untouched by an approval.
        $this->assertSame('Verified', Producer::where('user_id', $producerUser->user_id)->value('verification_status'));
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('order_details')->count());
    }

    public function test_manager_save_reload_edit_publish_and_participation_journey(): void
    {
        $this->enable();
        $manager = $this->makeUser('KADIWA Manager');
        config(['mao_workflows.kadiwa_manager_user_ids' => [$manager->user_id]]);
        $draft = $this->actingAs($manager, 'sanctum')->postJson('/api/kadiwa/events', $this->eventPayload(['capacity'=>2]))
            ->assertCreated()->assertJsonPath('row_version',0)->json();
        $id = $draft['id'];
        $this->getJson('/api/kadiwa/assigned')->assertOk()->assertJsonPath('0.id',$id)->assertJsonPath('0.status','draft');
        $edited = $this->patchJson("/api/kadiwa/events/$id", ['version'=>0,'title'=>'Updated KADIWA venue','description'=>$draft['description'],'capacity'=>2,'categories'=>$draft['categories'],'requirements'=>$draft['requirements'],'rsbsa_required'=>false,'evidence_required'=>false])
            ->assertOk()->assertJsonPath('title','Updated KADIWA venue')->json();
        $farmer = $this->makeUser();
        $this->makeProducer($farmer);
        $this->actingAs($farmer, 'sanctum')->getJson('/api/kadiwa/events?upcoming=1')->assertOk()->assertJsonCount(0);
        $this->actingAs($manager, 'sanctum')->postJson("/api/kadiwa/events/$id/status", ['status'=>'open','version'=>$edited['row_version'],'operation_key'=>(string) Str::uuid()])->assertOk();
        $this->actingAs($farmer, 'sanctum')->getJson('/api/kadiwa/events?upcoming=1')->assertOk()->assertJsonPath('0.id',$id);
        $application = $this->postJson("/api/kadiwa/events/$id/applications", $this->applicationPayload())->assertCreated()->assertJsonPath('row_version',0)->json();
        $this->actingAs($manager, 'sanctum')->getJson("/api/kadiwa/events/$id/queue")->assertOk()->assertJsonPath('available_slots',2);
        $this->postJson('/api/kadiwa/applications/'.$application['id'].'/review', ['decision'=>'approve','reason'=>'Test eligibility confirmed','version'=>0,'operation_key'=>(string) Str::uuid()])->assertOk()->assertJsonPath('status','approved');
        $this->getJson("/api/kadiwa/events/$id/queue")->assertOk()->assertJsonPath('approved_count',1)->assertJsonPath('available_slots',1);
        $this->getJson("/api/kadiwa/events/$id/audit")->assertOk()->assertJsonPath('data.0.previous_status',null)->assertJsonPath('data.0.new_status','draft')->assertJsonPath('data.2.previous_status','draft')->assertJsonPath('data.2.new_status','open');
        $this->assertDatabaseCount('orders',0);
    }

    private function eventPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'KADIWA venue',
            'description' => 'Producers sell directly to customers.',
            'venue' => 'Municipal market hall',
            'location' => 'Aparri, Cagayan',
            'opens_at' => now()->subDay()->toDateTimeString(),
            'closes_at' => now()->addDays(5)->toDateTimeString(),
            'starts_at' => now()->addDays(10)->toDateTimeString(),
            'ends_at' => now()->addDays(10)->addHours(8)->toDateTimeString(),
            'capacity' => null,
            'categories' => ['Vegetables'],
            'requirements' => [],
            'rsbsa_required' => false,
            'evidence_required' => false,
            'operation_key' => (string) Str::uuid(),
        ], $overrides);
    }
}