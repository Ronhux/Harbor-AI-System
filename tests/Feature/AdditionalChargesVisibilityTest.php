<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\NegotiationEvent;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Producer;
use App\Models\ProductListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks the current behaviour of additional charges.
 *
 * These tests deliberately pin the existing split: the buyer pays the charge,
 * the producer's revenue figures are built from product subtotals only, and a
 * revision that omits the field preserves whatever was agreed before. Any
 * future decision to treat charges differently must change these tests on
 * purpose rather than by accident.
 */
class AdditionalChargesVisibilityTest extends TestCase

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

    private const UNIT_PRICE = 45.0;
    private const QUANTITY = 20.0;
    private const CHARGES = 250.0;
    private const SUBTOTAL = 900.0;   // 45 x 20
    private const TOTAL = 1150.0;     // 900 + 250

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

    private function makeBuyer(string $email): array
    {
        $user = $this->makeUser($email, 'Buyer');

        $buyer = Buyer::create([
            'user_id' => $user->user_id,
            'organization_name' => 'Aparri LGU',
            'buyer_type' => 'LGU',
        ]);

        return [$user, $buyer];
    }

    private function makeListing(int $producerId, string $name = 'Premium Rice'): ProductListing
    {
        return ProductListing::create([
            'producer_id' => $producerId,
            'product_name' => $name,
            'product_category' => 'Grains',
            'category' => 'Grains',
            'current_price_per_unit' => 50,
            'price_per_unit' => 50,
            'unit_of_measure' => 'kg',
            'unit' => 'kg',
            'quantity' => 500,
            'quantity_available' => 500,
            'status' => 'Active',
        ]);
    }

    /**
     * Opens a negotiation carrying a non zero charge and returns the ids.
     */
    private function openNegotiation(ProductListing $listing, float $charges = self::CHARGES): array
    {
        [$buyerUser] = $this->buyer;

        $created = $this->actingAs($buyerUser, 'sanctum')
            ->postJson('/api/buyer/negotiations', [
                'listing_id' => $listing->listing_id,
                'unit_price' => self::UNIT_PRICE,
                'quantity' => self::QUANTITY,
                'additional_charges' => $charges,
            ])
            ->assertCreated();

        return [$created->json('data.id'), $created->json('data.version.version')];
    }

    private array $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        [$buyerUser, $buyer] = $this->makeBuyer('charges-buyer@test.local');
        $this->buyer = [$buyerUser, $buyer];
    }

    public function test_a_proposal_keeps_the_charge_that_the_buyer_submitted(): void
    {
        [$producerUser, $producer] = $this->makeProducer('charges-producer@test.local');
        $listing = $this->makeListing($producer->producer_id);

        [$id] = $this->openNegotiation($listing);

        $data = $this->actingAs($producerUser, 'sanctum')
            ->getJson("/api/producer/negotiations/$id")
            ->assertOk()
            ->json('data');

        $this->assertEqualsWithDelta(self::CHARGES, $data['version']['additional_charges'], 0.001);
        $this->assertEqualsWithDelta(self::SUBTOTAL, $data['version']['unit_price'] * $data['version']['quantity'], 0.001);
    }

    public function test_mutual_confirmation_snapshots_the_charge_and_the_totals(): void
    {
        [$producerUser, $producer] = $this->makeProducer('confirm-producer@test.local');
        $listing = $this->makeListing($producer->producer_id);

        [$id, $version] = $this->openNegotiation($listing);

        $data = $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/producer/negotiations/$id/respond", ['action' => 'accept', 'version' => $version])
            ->assertOk()
            ->assertJsonPath('data.status', 'Agreement Confirmed')
            ->json('data');

        $snapshot = $data['agreement_snapshot'];

        $this->assertEqualsWithDelta(self::CHARGES, $snapshot['additional_charges'], 0.001);
        $this->assertEqualsWithDelta(self::SUBTOTAL, $snapshot['subtotal'], 0.001);
        $this->assertEqualsWithDelta(self::TOTAL, $snapshot['total'], 0.001);
    }

    public function test_the_order_total_includes_the_charge_while_the_product_subtotal_does_not(): void
    {
        [$producerUser, $producer] = $this->makeProducer('order-producer@test.local');
        $listing = $this->makeListing($producer->producer_id);

        [$id, $version] = $this->openNegotiation($listing);

        $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/producer/negotiations/$id/respond", ['action' => 'accept', 'version' => $version])
            ->assertOk();

        $order = Order::firstOrFail();

        // The buyer is charged the product subtotal plus the additional charges.
        $this->assertEqualsWithDelta(self::TOTAL, (float) $order->total_amount, 0.001);

        // The product subtotal stays clean, which is what producer revenue is built from.
        $detail = OrderDetail::firstOrFail();
        $this->assertEqualsWithDelta(self::SUBTOTAL, (float) $detail->subtotal, 0.001);
    }

    public function test_producer_revenue_is_unchanged_and_never_includes_the_charge(): void
    {
        [$producerUser, $producer] = $this->makeProducer('revenue-producer@test.local');
        $listing = $this->makeListing($producer->producer_id);

        [$id, $version] = $this->openNegotiation($listing);

        $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/producer/negotiations/$id/respond", ['action' => 'accept', 'version' => $version])
            ->assertOk();

        // Only delivered or completed orders count as revenue.
        $order = Order::firstOrFail();
        $this->actingAs($producerUser, 'sanctum')
            ->patchJson("/api/producer/orders/{$order->order_id}", ['status' => 'Order Confirmed'])
            ->assertOk();
        $this->actingAs($producerUser, 'sanctum')
            ->patchJson("/api/producer/orders/{$order->order_id}", ['status' => 'Preparing Order'])
            ->assertOk();
        $this->actingAs($producerUser, 'sanctum')
            ->patchJson("/api/producer/orders/{$order->order_id}", ['status' => 'Ready for Shipment'])
            ->assertOk();
        $this->actingAs($producerUser, 'sanctum')
            ->patchJson("/api/producer/orders/{$order->order_id}", ['status' => 'Shipped'])
            ->assertOk();
        $this->actingAs($producerUser, 'sanctum')
            ->patchJson("/api/producer/orders/{$order->order_id}", ['status' => 'Delivered'])
            ->assertOk();

        // Revenue requires a paid order; an unpaid delivery is not realized revenue.
        Order::where('order_id', $order->order_id)->update(['payment_status' => 'Paid']);

        $dashboard = $this->actingAs($producerUser, 'sanctum')
            ->getJson('/api/producer/dashboard')
            ->assertOk();

        // Total revenue stays on the product subtotal and excludes the charge.
        $this->assertEqualsWithDelta(self::SUBTOTAL, (float) $dashboard->json('stats.total_revenue'), 0.001);

        // The monthly trend is bucketed from the same subtotals.
        $month = collect($dashboard->json('revenue_by_month'))
            ->firstWhere('month', now()->format('Y-m'));
        $this->assertEqualsWithDelta(self::SUBTOTAL, (float) $month['revenue'], 0.001);

        // The orders page reports the producer's own subtotal, not the order total.
        $ordersResponse = $this->actingAs($producerUser, 'sanctum')
            ->getJson('/api/producer/orders')
            ->assertOk();

        $this->assertEqualsWithDelta(self::SUBTOTAL, (float) $ordersResponse->json('0.producer_subtotal'), 0.001);
        $this->assertArrayNotHasKey('total_amount', $ordersResponse->json('0'));
    }

    public function test_a_revision_that_omits_the_charge_preserves_the_agreed_amount(): void
    {
        [$producerUser, $producer] = $this->makeProducer('fallback-producer@test.local');
        $listing = $this->makeListing($producer->producer_id);

        [$id, $version] = $this->openNegotiation($listing);

        // The revision deliberately does not send additional_charges.
        $data = $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/producer/negotiations/$id/respond", [
                'action' => 'revise',
                'version' => $version,
                'unit_price' => 47,
                'quantity' => self::QUANTITY,
            ])
            ->assertOk()
            ->assertJsonPath('data.current_version', 2)
            ->json('data');

        $this->assertEqualsWithDelta(self::CHARGES, $data['version']['additional_charges'], 0.001);
        $this->assertEqualsWithDelta(47 * self::QUANTITY, $data['version']['unit_price'] * $data['version']['quantity'], 0.001);
    }

    public function test_a_revision_that_sends_the_charge_updates_it(): void
    {
        [$producerUser, $producer] = $this->makeProducer('update-producer@test.local');
        $listing = $this->makeListing($producer->producer_id);

        [$id, $version] = $this->openNegotiation($listing);

        $data = $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/producer/negotiations/$id/respond", [
                'action' => 'revise',
                'version' => $version,
                'unit_price' => 47,
                'quantity' => self::QUANTITY,
                'additional_charges' => 100,
            ])
            ->assertOk()
            ->json('data');

        $this->assertEqualsWithDelta(100, $data['version']['additional_charges'], 0.001);
    }

    public function test_the_charge_is_recorded_in_negotiation_event_terms(): void
    {
        [$producerUser, $producer] = $this->makeProducer('terms-producer@test.local');
        $listing = $this->makeListing($producer->producer_id);

        [$id, $version] = $this->openNegotiation($listing);

        // The event terms are exposed by the API so the history list can render them.
        $events = $this->actingAs($producerUser, 'sanctum')
            ->getJson("/api/producer/negotiations/$id")
            ->assertOk()
            ->json('data.events');

        $submitted = collect($events)->firstWhere('event', 'proposal_submitted');
        $this->assertNotNull($submitted);
        $this->assertEqualsWithDelta(self::CHARGES, $submitted['terms']['additional_charges'], 0.001);

        $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/producer/negotiations/$id/respond", ['action' => 'accept', 'version' => $version])
            ->assertOk();

        $confirmed = NegotiationEvent::where('negotiation_id', $id)->where('event', 'agreement_confirmed')->first();
        $this->assertNotNull($confirmed);
        $this->assertEqualsWithDelta(self::CHARGES, (float) $confirmed->terms['additional_charges'], 0.001);

        // Order status changes carry no terms, so the history list must render the
        // charge conditionally rather than showing a zero for these rows.
        $order = Order::firstOrFail();
        $this->actingAs($producerUser, 'sanctum')
            ->patchJson("/api/producer/orders/{$order->order_id}", ['status' => 'Order Confirmed'])
            ->assertOk();

        $statusEvent = NegotiationEvent::where('negotiation_id', $id)->where('event', 'order_status_changed')->first();
        $this->assertNotNull($statusEvent);
        $this->assertNull($statusEvent->terms);
    }

    public function test_the_buyer_order_payload_carries_the_charge_for_the_order_detail_view(): void
    {
        [$buyerUser] = $this->buyer;
        [$producerUser, $producer] = $this->makeProducer('payload-producer@test.local');
        $listing = $this->makeListing($producer->producer_id);

        [$id, $version] = $this->openNegotiation($listing);

        $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/producer/negotiations/$id/respond", ['action' => 'accept', 'version' => $version])
            ->assertOk();

        $order = $this->actingAs($buyerUser, 'sanctum')
            ->getJson('/api/buyer/orders')
            ->assertOk()
            ->json('0');

        $this->assertEqualsWithDelta(self::TOTAL, (float) $order['total_amount'], 0.001);
        $this->assertEqualsWithDelta(self::SUBTOTAL, (float) $order['details'][0]['subtotal'], 0.001);
        // The snapshot is present, so the order detail can itemize the charge.
        $this->assertEqualsWithDelta(self::CHARGES, (float) $order['negotiation']['agreement_snapshot']['additional_charges'], 0.001);
    }

    public function test_an_older_order_without_a_negotiation_has_no_charge_to_show(): void
    {
        [$buyerUser, $buyer] = $this->buyer;
        [, $producer] = $this->makeProducer('legacy-producer@test.local');
        $listing = $this->makeListing($producer->producer_id, 'Legacy Stock');

        $order = Order::create([
            'buyer_id' => $buyer->buyer_id,
            'order_date' => now()->toDateString(),
            'total_amount' => 1000,
            'fulfillment_status' => 'Order Submitted',
            'payment_status' => 'Pending',
        ]);
        OrderDetail::create([
            'order_id' => $order->order_id,
            'listing_id' => $listing->listing_id,
            'quantity' => 20,
            'unit_price' => 50,
            'subtotal' => 1000,
        ]);

        $payload = $this->actingAs($buyerUser, 'sanctum')
            ->getJson('/api/buyer/orders')
            ->assertOk()
            ->json('0');

        // No negotiation means no agreement snapshot. The interface must not
        // derive or display a presumed charge in this case.
        $this->assertNull($payload['negotiation']);
        $this->assertEqualsWithDelta(1000, (float) $payload['total_amount'], 0.001);
    }
}