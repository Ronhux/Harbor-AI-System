<?php

namespace Tests\Feature;

use App\Models\TransactionNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_uses_record_identifiers_not_titles(): void
    {
        $negotiation = new TransactionNotification(['title' => 'Demand offer negotiation started', 'negotiation_id' => 17]);
        $this->assertSame(['type' => 'negotiation', 'record_id' => 17], $negotiation->navigation);
        $order = new TransactionNotification(['title' => 'Demand order update', 'order_id' => 8, 'negotiation_id' => 17]);
        $this->assertSame(['type' => 'order', 'record_id' => 8], $order->navigation);
        $unrelated = new TransactionNotification(['title' => 'Demand request awaiting review', 'body' => 'No related record']);
        $this->assertNull($unrelated->navigation);
    }


    public function test_unread_count_includes_records_beyond_the_notification_list_and_is_user_scoped(): void
    {
        $producer = User::create(['email'=>'notifications-producer@example.test','password_hash'=>bcrypt('secret123'),'first_name'=>'Producer','user_type'=>'Farmer']);
        $buyer = User::create(['email'=>'notifications-buyer@example.test','password_hash'=>bcrypt('secret123'),'first_name'=>'Buyer','user_type'=>'Buyer']);
        $admin = User::create(['email'=>'notifications-admin@example.test','password_hash'=>bcrypt('secret123'),'first_name'=>'Admin','user_type'=>'Admin']);
        $superAdmin = User::create(['email'=>'notifications-super@example.test','password_hash'=>bcrypt('secret123'),'first_name'=>'Super','user_type'=>'Super Admin']);

        for ($i = 0; $i < 31; $i++) {
            TransactionNotification::create(['recipient_user_id'=>$producer->user_id,'title'=>'Order update','body'=>'Update '.$i]);
        }
        $other = TransactionNotification::create(['recipient_user_id'=>$buyer->user_id,'title'=>'Private buyer update','body'=>'Buyer only']);
        TransactionNotification::create(['recipient_user_id'=>$admin->user_id,'title'=>'Admin review','body'=>'Admin only']);
        TransactionNotification::create(['recipient_user_id'=>$superAdmin->user_id,'title'=>'System review','body'=>'Super admin only']);

        $response = $this->actingAs($producer, 'sanctum')->getJson('/api/notifications')->assertOk();
        $response->assertJsonCount(30, 'data')->assertJsonPath('unread_count', 31);
        $this->assertFalse(collect($response->json('data'))->contains(fn ($item) => $item['id'] === $other->id));

        $notificationId = $response->json('data.0.id');
        $readResponse = $this->actingAs($producer, 'sanctum')->patchJson("/api/notifications/$notificationId/read")
            ->assertOk()->assertJsonPath('unread_count', 30);
        $persistedReadAt=$readResponse->json('read_at');
        $this->assertNotEmpty($persistedReadAt);
        $this->travel(5)->minutes();
        $readResponse = $this->actingAs($producer, 'sanctum')->patchJson("/api/notifications/$notificationId/read")
            ->assertOk()->assertJsonPath('unread_count', 30);
        $this->assertSame($persistedReadAt,$readResponse->json('read_at'));
        $this->actingAs($producer, 'sanctum')->patchJson("/api/notifications/{$other->id}/read")->assertNotFound();
        $this->actingAs($admin, 'sanctum')->getJson('/api/notifications')->assertOk()->assertJsonPath('unread_count', 1)->assertJsonPath('data.0.title', 'Admin review');
        $this->actingAs($superAdmin, 'sanctum')->getJson('/api/notifications')->assertOk()->assertJsonPath('unread_count', 1)->assertJsonPath('data.0.title', 'System review');
    }
}
