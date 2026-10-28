<?php

namespace Tests\Feature;

use App\Models\{Buyer, Producer, TransactionNotification, User};
use App\Services\Notifications\AdminNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Administrative notifications.
 *
 * Administrators previously had no queue at all: every existing notification addressed a buyer
 * or a producer. These tests prove the administrative queue is real (created only from genuine
 * records), correctly scoped, and that it never leaks another account's notices.
 */
class AdminNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $superAdmin;

    private User $producer;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser('admin@test.local', 'Admin');
        $this->superAdmin = $this->makeUser('root@test.local', 'Super Admin');
        $this->producer = $this->makeUser('grower@test.local', 'Farmer');
        $this->buyer = $this->makeUser('purchaser@test.local', 'Buyer');
    }

    private function makeUser(string $email, string $type): User
    {
        return User::create([
            'first_name' => 'Test', 'last_name' => $type,
            'email' => $email, 'password_hash' => bcrypt('test-only'),
            'contact_number' => '0918' . random_int(1000000, 9999999),
            'user_type' => $type, 'is_active' => true,
        ]);
    }

    public function test_recipients_are_only_active_administrator_accounts(): void
    {
        $inactiveAdmin = $this->makeUser('suspended@test.local', 'Admin');
        $inactiveAdmin->forceFill(['is_active' => false])->save();

        $recipients = app(AdminNotificationService::class)->recipients();

        $this->assertContains((int) $this->admin->user_id, $recipients);
        $this->assertContains((int) $this->superAdmin->user_id, $recipients);
        $this->assertNotContains((int) $this->producer->user_id, $recipients);
        $this->assertNotContains((int) $this->buyer->user_id, $recipients);
        $this->assertNotContains((int) $inactiveAdmin->user_id, $recipients, 'a suspended admin must not be notified');
    }

    public function test_a_real_demand_request_creates_a_notification_for_administrators(): void
    {
        $buyerRecord = Buyer::create(['user_id' => $this->buyer->user_id, 'verification_status' => 'Verified']);
        $demand = $buyerRecord->demandRequests()->create([
            'product_name' => 'Ampalaya', 'quantity_needed' => 500, 'unit' => 'kg',
            'location' => 'Aparri', 'status' => 'Active',
        ]);

        $created = app(AdminNotificationService::class)->demandRequestSubmitted($demand);

        $this->assertSame(2, $created, 'both administrators should be notified');
        $this->assertSame(2, TransactionNotification::whereIn(
            'recipient_user_id', [$this->admin->user_id, $this->superAdmin->user_id]
        )->count());
        $this->assertSame(0, TransactionNotification::where('recipient_user_id', $this->buyer->user_id)->count());
        $this->assertSame(0, TransactionNotification::where('recipient_user_id', $this->producer->user_id)->count());
    }

    public function test_the_body_names_the_record_but_not_the_requesting_organisation(): void
    {
        $buyerRecord = Buyer::create([
            'user_id' => $this->buyer->user_id,
            'verification_status' => 'Verified',
            'organization_name' => 'Secret Institution Name',
        ]);
        $demand = $buyerRecord->demandRequests()->create([
            'product_name' => 'Ampalaya', 'quantity_needed' => 500, 'unit' => 'kg',
            'status' => 'Active',
        ]);

        app(AdminNotificationService::class)->demandRequestSubmitted($demand);

        $body = TransactionNotification::where('recipient_user_id', $this->admin->user_id)
            ->latest('id')->value('body');

        $this->assertStringContainsString("#{$demand->request_id}", $body, 'the record must be traceable');
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/notifications')
            ->assertOk()->assertJsonPath('data.0.navigation.type', 'demand_review')
            ->assertJsonPath('data.0.navigation.record_id', $demand->request_id);
        $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/notifications')
            ->assertOk()->assertJsonPath('data.0.navigation', null);
        $this->assertStringNotContainsString('Secret Institution Name', $body,
            'the requesting organisation must not be reproduced in the notice');
    }

    public function test_a_real_new_producer_notifies_administrators(): void
    {
        $producer = Producer::create(['user_id' => $this->producer->user_id, 'verification_status' => 'Pending']);

        $created = app(AdminNotificationService::class)->producerAwaitingVerification($producer);

        $this->assertSame(2, $created);
        $title = TransactionNotification::where('recipient_user_id', $this->admin->user_id)
            ->latest('id')->value('title');
        $this->assertSame('New producer awaiting verification', $title);
    }

    /** Unread counts are per recipient, never aggregated across accounts. */
    public function test_unread_counts_are_scoped_to_the_recipient(): void
    {
        $service = app(AdminNotificationService::class);
        $service->notifyAdministrators('Operational notice', 'Something real happened.');

        $this->assertSame(1, $service->unreadCount((int) $this->admin->user_id));
        $this->assertSame(1, $service->unreadCount((int) $this->superAdmin->user_id));
        $this->assertSame(0, $service->unreadCount((int) $this->producer->user_id));
    }

    public function test_the_notification_endpoint_returns_only_my_own_notifications(): void
    {
        app(AdminNotificationService::class)->notifyAdministrators('For administrators only', 'Administrative queue item.');

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/notifications')->assertOk()->json();

        $this->assertSame(1, $response['unread_count']);
        $this->assertSame('For administrators only', $response['data'][0]['title']);

        $producerResponse = $this->actingAs($this->producer, 'sanctum')
            ->getJson('/api/notifications')->assertOk()->json();
        $this->assertSame(0, $producerResponse['unread_count']);
        $this->assertSame([], $producerResponse['data']);
    }

    public function test_marking_as_read_updates_only_my_own_unread_count(): void
    {
        app(AdminNotificationService::class)->notifyAdministrators('Review me', 'Administrative queue item.');
        $mine = TransactionNotification::where('recipient_user_id', $this->admin->user_id)->firstOrFail();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/notifications/{$mine->id}/read")->assertOk()->json();

        $this->assertSame(0, $response['unread_count']);
        $this->assertNotNull($mine->fresh()->read_at);

        // The other administrator's copy must remain unread.
        $theirs = TransactionNotification::where('recipient_user_id', $this->superAdmin->user_id)->firstOrFail();
        $this->assertNull($theirs->fresh()->read_at);
    }

    /** A recipient must not be able to mark or read someone else's notification. */
    public function test_a_recipient_cannot_mark_another_accounts_notification_as_read(): void
    {
        app(AdminNotificationService::class)->notifyAdministrators('Admin only', 'Administrative queue item.');
        $theirs = TransactionNotification::where('recipient_user_id', $this->superAdmin->user_id)->firstOrFail();

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/notifications/{$theirs->id}/read")
            ->assertNotFound();

        $this->assertNull($theirs->fresh()->read_at, 'the other account must stay unread');
    }

    public function test_a_non_administrator_never_receives_an_administrative_notice(): void
    {
        $service = app(AdminNotificationService::class);
        $service->notifyAdministrators('Admin only', 'Administrative queue item.');

        $nonAdmins = TransactionNotification::query()
            ->whereIn('recipient_user_id', [$this->producer->user_id, $this->buyer->user_id])
            ->count();

        $this->assertSame(0, $nonAdmins);
    }

    public function test_with_no_active_administrators_nothing_is_written(): void
    {
        $this->admin->forceFill(['is_active' => false])->save();
        $this->superAdmin->forceFill(['is_active' => false])->save();

        $created = app(AdminNotificationService::class)
            ->notifyAdministrators('Nobody home', 'Should not be stored anywhere.');

        $this->assertSame(0, $created);
        $this->assertSame(0, TransactionNotification::count());
    }
}