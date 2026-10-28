<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Producer;
use App\Models\ProductListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProducerOrderScopingTest extends TestCase

{

    /**
     * Deterministic, unique synthetic Philippine mobile number.
     *
     * Mirrors the helper already used by DebugExactTest,
     * DebugServiceExceptionTest and FcaApplicationPersistenceTest. Each call
     * yields a distinct 11-digit number so the unique users.login_number
     * index is satisfied without weakening the constraint.
     */
    private static function uniqueMobile(): string
    {
        static $seq = 0;
        return sprintf('0918%07d', ++$seq);
    }

    use RefreshDatabase;

    private function makeUser(string $email, string $type = 'Farmer'): User
    {
        return User::create([
            'email' => $email,
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Ana',
            'last_name' => 'Dela Cruz',
            'contact_number' => self::uniqueMobile(),
            'user_type' => $type,
        ]);
    }

    private function makeProducer(string $email): array
    {
        $user = $this->makeUser($email);

        $producer = Producer::create([
            'user_id' => $user->user_id,
            'verification_status' => 'Verified',
            'producer_type' => 'Farmer',
        ]);

        return [$user, $producer];
    }

    private function makeListing(int $producerId, string $name, string $price = '100.00'): ProductListing
    {
        return ProductListing::create([
            'producer_id' => $producerId,
            'product_name' => $name,
            'product_category' => 'Grains',
            'current_price_per_unit' => $price,
            'price_per_unit' => $price,
            'unit_of_measure' => 'kg',
            'quantity_available' => 100,
            'status' => 'Active',
        ]);
    }

    private function addDetail(Order $order, ProductListing $listing, float $subtotal): void
    {
        OrderDetail::create([
            'order_id' => $order->order_id,
            'listing_id' => $listing->listing_id,
            'quantity' => 10,
            'unit_price' => $subtotal / 10,
            'subtotal' => $subtotal,
        ]);
    }

    public function test_a_producer_cannot_see_another_producers_order_lines(): void
    {
        [$mineUser, $mine] = $this->makeProducer('scoped-mine@test.local');
        [, $theirs] = $this->makeProducer('scoped-theirs@test.local');

        $buyerId = Buyer::create([
            'user_id' => $this->makeUser('scoped-buyer@test.local', 'Buyer')->user_id,
            'organization_name' => 'Aparri LGU',
        ])->buyer_id;

        $myListing = $this->makeListing($mine->producer_id, 'My Premium Rice', '40.00');
        $theirListing = $this->makeListing($theirs->producer_id, 'Their Secret Tilapia', '900.00');

        // A single order holding products from both producers.
        $order = Order::create([
            'buyer_id' => $buyerId,
            'order_date' => now()->toDateString(),
            'total_amount' => 15000,
            'fulfillment_status' => 'Order Submitted',
            'payment_status' => 'Pending',
        ]);

        $this->addDetail($order, $myListing, 4000);
        $this->addDetail($order, $theirListing, 11000);

        $response = $this->actingAs($mineUser, 'sanctum')->getJson('/api/producer/orders')
            ->assertOk()
            ->assertJsonCount(1);

        $payload = $response->json()[0];

        // Only this producer's own line is returned.
        $this->assertCount(1, $payload['details']);
        $this->assertSame('My Premium Rice', $payload['details'][0]['product_name']);
        $this->assertEqualsWithDelta(4000, $payload['details'][0]['subtotal'], 0.001);

        // The whole order total is never exposed on this endpoint.
        $this->assertArrayNotHasKey('total_amount', $payload);

        // Nothing about the other producer's product leaks anywhere in the body.
        $body = $response->getContent();
        $this->assertStringNotContainsString('Their Secret Tilapia', $body);
        $this->assertStringNotContainsString('11000', $body);
        // Matched as a JSON key so the check cannot collide with unrelated digits.
        $this->assertStringNotContainsString('"listing_id":' . $theirListing->listing_id, $body);
        $this->assertStringContainsString('"listing_id":' . $myListing->listing_id, $body);
    }

    public function test_the_reported_subtotal_uses_only_the_logged_in_producers_own_lines(): void
    {
        [$mineUser, $mine] = $this->makeProducer('subtotal-mine@test.local');
        [, $theirs] = $this->makeProducer('subtotal-theirs@test.local');

        $buyerId = Buyer::create([
            'user_id' => $this->makeUser('subtotal-buyer@test.local', 'Buyer')->user_id,
        ])->buyer_id;

        $order = Order::create([
            'buyer_id' => $buyerId,
            'order_date' => now()->toDateString(),
            'total_amount' => 20000,
            'fulfillment_status' => 'Order Submitted',
            'payment_status' => 'Pending',
        ]);

        // Two of this producer's own lines plus one belonging to somebody else.
        $this->addDetail($order, $this->makeListing($mine->producer_id, 'Rice A', '40.00'), 2500);
        $this->addDetail($order, $this->makeListing($mine->producer_id, 'Rice B', '60.00'), 1500);
        $this->addDetail($order, $this->makeListing($theirs->producer_id, 'Other Corn', '500.00'), 16000);

        $this->actingAs($mineUser, 'sanctum')->getJson('/api/producer/orders')
            ->assertOk()
            // 2500 + 1500, never the 20000 order total.
            ->assertJsonPath('0.producer_subtotal', 4000)
            ->assertJsonCount(2, '0.details')
            ->assertJsonPath('0.details.0.product_name', 'Rice A')
            ->assertJsonPath('0.details.1.product_name', 'Rice B');
    }

    public function test_each_producer_sees_a_different_scoped_view_of_the_same_order(): void
    {
        [$mineUser, $mine] = $this->makeProducer('both-mine@test.local');
        [$theirUser, $theirs] = $this->makeProducer('both-theirs@test.local');

        $buyerId = Buyer::create([
            'user_id' => $this->makeUser('both-buyer@test.local', 'Buyer')->user_id,
        ])->buyer_id;

        $order = Order::create([
            'buyer_id' => $buyerId,
            'order_date' => now()->toDateString(),
            'total_amount' => 9000,
            'fulfillment_status' => 'Shipped',
            'payment_status' => 'Pending',
        ]);

        $this->addDetail($order, $this->makeListing($mine->producer_id, 'Mine', '30.00'), 3000);
        $this->addDetail($order, $this->makeListing($theirs->producer_id, 'Theirs', '60.00'), 6000);

        $mineView = $this->actingAs($mineUser, 'sanctum')->getJson('/api/producer/orders')->assertOk()->json('0');
        $theirView = $this->actingAs($theirUser, 'sanctum')->getJson('/api/producer/orders')->assertOk()->json('0');

        $this->assertSame(3000, $mineView['producer_subtotal']);
        $this->assertSame(6000, $theirView['producer_subtotal']);

        $this->assertSame('Mine', $mineView['details'][0]['product_name']);
        $this->assertSame('Theirs', $theirView['details'][0]['product_name']);

        // Both sides still see the same shared order id and status.
        $this->assertSame($order->order_id, $mineView['order_id']);
        $this->assertSame($order->order_id, $theirView['order_id']);
        $this->assertSame('Shipped', $mineView['fulfillment_status']);
        $this->assertSame('Shipped', $theirView['fulfillment_status']);
    }

    public function test_orders_without_the_producers_lines_are_not_listed_at_all(): void
    {
        [$mineUser, $mine] = $this->makeProducer('isolated-mine@test.local');
        [, $theirs] = $this->makeProducer('isolated-theirs@test.local');

        $buyerId = Buyer::create([
            'user_id' => $this->makeUser('isolated-buyer@test.local', 'Buyer')->user_id,
        ])->buyer_id;

        $otherOrder = Order::create([
            'buyer_id' => $buyerId,
            'order_date' => now()->toDateString(),
            'fulfillment_status' => 'Order Submitted',
            'payment_status' => 'Pending',
        ]);
        $this->addDetail($otherOrder, $this->makeListing($theirs->producer_id, 'Not Mine'), 500);

        $this->actingAs($mineUser, 'sanctum')->getJson('/api/producer/orders')
            ->assertOk()
            ->assertExactJson([]);

        $this->assertSame($mine->producer_id, $mine->producer_id);
        $this->assertNotNull($theirs->producer_id);
    }

    public function test_the_orders_endpoint_agrees_with_the_dashboard_recent_orders(): void
    {
        [$mineUser, $mine] = $this->makeProducer('parity-mine@test.local');
        [, $theirs] = $this->makeProducer('parity-theirs@test.local');

        $buyerId = Buyer::create([
            'user_id' => $this->makeUser('parity-buyer@test.local', 'Buyer')->user_id,
        ])->buyer_id;

        $order = Order::create([
            'buyer_id' => $buyerId,
            'order_date' => now()->toDateString(),
            'total_amount' => 7500,
            'fulfillment_status' => 'Order Confirmed',
            'payment_status' => 'Pending',
        ]);

        $this->addDetail($order, $this->makeListing($mine->producer_id, 'Parity Rice', '50.00'), 2500);
        $this->addDetail($order, $this->makeListing($theirs->producer_id, 'Parity Fish', '250.00'), 5000);

        $fromOrdersPage = $this->actingAs($mineUser, 'sanctum')->getJson('/api/producer/orders')->assertOk()->json('0');
        $fromDashboard = $this->actingAs($mineUser, 'sanctum')->getJson('/api/producer/dashboard')->assertOk()->json('recent_orders.0');

        // Both surfaces must report the same producer scoped subtotal and the
        // same single line for this producer.
        $this->assertEqualsWithDelta($fromOrdersPage['producer_subtotal'], $fromDashboard['producer_subtotal'], 0.001);
        $this->assertCount(1, $fromOrdersPage['details']);
        $this->assertCount(1, $fromDashboard['details']);
        $this->assertSame(
            $fromDashboard['details'][0]['product_name'],
            $fromOrdersPage['details'][0]['product_name']
        );
        $this->assertSame($fromDashboard['order_id'], $fromOrdersPage['order_id']);
    }

    public function test_a_single_producer_order_still_reports_its_line_and_subtotal(): void
    {
        [$mineUser, $mine] = $this->makeProducer('single@test.local');

        $buyerId = Buyer::create([
            'user_id' => $this->makeUser('single-buyer@test.local', 'Buyer')->user_id,
            'organization_name' => 'Kadiwa Outlet',
        ])->buyer_id;

        $listing = $this->makeListing($mine->producer_id, 'Solo Tilapia', '180.00');
        $order = Order::create([
            'buyer_id' => $buyerId,
            'order_date' => now()->toDateString(),
            'total_amount' => 18000,
            'fulfillment_status' => 'Delivered',
            'payment_status' => 'Paid',
        ]);
        $this->addDetail($order, $listing, 18000);

        $this->actingAs($mineUser, 'sanctum')->getJson('/api/producer/orders')
            ->assertOk()
            ->assertJsonPath('0.producer_subtotal', 18000)
            ->assertJsonPath('0.details.0.product_name', 'Solo Tilapia')
            ->assertJsonPath('0.details.0.unit', 'kg')
            ->assertJsonPath('0.details.0.unit_price', 1800)
            ->assertJsonPath('0.buyer.organization_name', 'Kadiwa Outlet')
            ->assertJsonPath('0.fulfillment_status', 'Delivered');
    }
}