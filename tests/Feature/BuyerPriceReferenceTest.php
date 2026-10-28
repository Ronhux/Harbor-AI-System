<?php

namespace Tests\Feature;

use App\Models\AiDataSource;
use App\Models\CommodityReference;
use App\Models\MarketPriceReference;
use App\Models\Producer;
use App\Models\ProductListing;
use App\Models\User;
use Database\Seeders\AiReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerPriceReferenceTest extends TestCase
{
    use RefreshDatabase;

    private AiDataSource $psaSource;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AiReferenceDataSeeder::class);
        $this->psaSource = AiDataSource::query()
            ->where('source_name', AiDataSource::SOURCE_PSA_MAJOR_CROPS)
            ->firstOrFail();
    }

    private function user(string $email, string $type): User
    {
        return User::create([
            'email' => $email,
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Synthetic',
            'last_name' => 'Test',
            'user_type' => $type,
        ]);
    }

    private function listing(string $email, array $values): ProductListing
    {
        $user = $this->user($email, 'Farmer');
        $producer = Producer::create([
            'user_id' => $user->user_id,
            'verification_status' => 'Verified',
            'producer_type' => 'Farmer',
        ]);

        return ProductListing::create(array_merge([
            'producer_id' => $producer->producer_id,
            'product_name' => 'Cabbage',
            'product_category' => 'Vegetables',
            'current_price_per_unit' => 0.03,
            'price_per_unit' => 0.03,
            'unit_of_measure' => 'g',
            'quantity_available' => 1000,
            'status' => 'Active',
        ], $values));
    }

    private function observation(CommodityReference $commodity, float $price, string $date, string $period): void
    {
        MarketPriceReference::create([
            'commodity_reference_id' => $commodity->id,
            'ai_data_source_id' => $this->psaSource->id,
            'price' => $price,
            'currency' => 'PHP',
            'unit' => 'Peso per kilogram',
            'price_type' => 'farmgate',
            'geographic_scope' => 'Region II / Cagayan Valley',
            'source_reference' => 'TEST FIXTURE ONLY - not actual PSA data',
            'source_commodity_name' => $commodity->canonical_name,
            'source_geographic_scope' => '..REGION II (CAGAYAN VALLEY)',
            'observation_date' => $date,
            'observation_period' => $period,
            'period_frequency' => 'monthly',
            'observation_status' => 'preliminary',
            'verification_status' => 'verified',
        ]);
    }

    public function test_buyer_comparison_uses_verified_commodity_reference_units_and_source_period(): void
    {
        $buyer = $this->user('buyer-price-reference@example.test', 'Buyer');
        $listing = $this->listing('cabbage-price-reference@example.test', [
            'location' => 'Aparri, Cagayan',
        ]);
        $cabbage = CommodityReference::query()->where('canonical_name', 'Cabbage')->firstOrFail();
        $this->observation($cabbage, 20, '2025-01-01', 'January 2025');
        $this->observation($cabbage, 30, '2025-02-01', 'February 2025');

        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/buyer/price-trends/references', ['listing_ids' => [$listing->listing_id]])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'COMPARABLE')
            ->assertJsonPath('data.0.commodity_resolution.canonical_name', 'Cabbage')
            ->assertJsonPath('data.0.listing_price.unit', 'g')
            ->assertJsonPath('data.0.comparison.normalized_listing_price', 30)
            ->assertJsonPath('data.0.comparison.unit', 'PHP/kg')
            ->assertJsonPath('data.0.reference.average_price', 25)
            ->assertJsonPath('data.0.reference.latest_available_observation.period', 'February 2025')
            ->assertJsonPath('data.0.reference.geography', 'Region II / Cagayan Valley')
            ->assertJsonMissing(['geography' => 'Aparri, Cagayan']);
    }

    public function test_ambiguous_commodity_matches_keep_each_reference_series_separate(): void
    {
        $buyer = $this->user('buyer-eggplant-reference@example.test', 'Buyer');
        $listing = $this->listing('eggplant-price-reference@example.test', [
            'product_name' => 'Eggplant',
            'current_price_per_unit' => 50,
            'price_per_unit' => 50,
            'unit_of_measure' => 'kg',
        ]);

        foreach ([
            'Eggplant, long, purple' => 11,
            'Eggplant, native, long' => 21,
            'Eggplant, native, round' => 31,
        ] as $name => $price) {
            $commodity = CommodityReference::query()->where('canonical_name', $name)->firstOrFail();
            $this->observation($commodity, $price, '2025-01-01', 'January 2025');
        }

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/buyer/price-trends/references', ['listing_ids' => [$listing->listing_id]])
            ->assertOk()
            ->assertJsonPath('data.0.status', 'MULTIPLE_MATCHES')
            ->assertJsonPath('data.0.comparison.status', 'MULTIPLE_REFERENCES')
            ->assertJsonCount(3, 'data.0.reference_candidates');

        $this->assertNull($response->json('data.0.comparison.normalized_listing_price'));
    }

    public function test_generic_banana_listing_keeps_psa_variety_series_separate(): void
    {
        $buyer = $this->user('buyer-banana-reference@example.test', 'Buyer');
        $listing = $this->listing('banana-reference@example.test', [
            'product_name' => 'Banana',
            'current_price_per_unit' => 60,
            'price_per_unit' => 60,
            'unit_of_measure' => 'kg',
        ]);

        foreach ([
            'Banana Bungulan' => 10,
            'Banana Cavendish' => 20,
            'Banana Lakatan' => 30,
            'Banana Latundan' => 40,
            'Banana Others' => 50,
            'Banana Saba' => 60,
        ] as $name => $price) {
            $commodity = CommodityReference::query()->where('canonical_name', $name)->firstOrFail();
            $this->observation($commodity, $price, '2025-01-01', 'January 2025');
        }

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/buyer/price-trends/references', ['listing_ids' => [$listing->listing_id]])
            ->assertOk()
            ->assertJsonPath('data.0.status', 'MULTIPLE_MATCHES')
            ->assertJsonCount(6, 'data.0.reference_candidates');

        $candidates = collect($response->json('data.0.reference_candidates'))
            ->keyBy('commodity_label');
        $this->assertSame(10, $candidates->get('Banana Bungulan')['reference']['average_price']);
        $this->assertSame(60, $candidates->get('Banana Saba')['reference']['average_price']);
        $this->assertNull($response->json('data.0.comparison.normalized_listing_price'));
    }

    public function test_explicit_zero_psa_observation_is_excluded_from_comparable_statistics(): void
    {
        $buyer = $this->user('buyer-zero-reference@example.test', 'Buyer');
        $listing = $this->listing('zero-reference@example.test', [
            'product_name' => 'Cabbage',
            'current_price_per_unit' => 25,
            'price_per_unit' => 25,
            'unit_of_measure' => 'kg',
        ]);
        $cabbage = CommodityReference::query()->where('canonical_name', 'Cabbage')->firstOrFail();
        $this->observation($cabbage, 0, '2025-01-01', 'January 2025');
        $this->observation($cabbage, 10, '2025-02-01', 'February 2025');

        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/buyer/price-trends/references', ['listing_ids' => [$listing->listing_id]])
            ->assertOk()
            ->assertJsonPath('data.0.status', 'COMPARABLE')
            ->assertJsonPath('data.0.reference.minimum_price', 10)
            ->assertJsonPath('data.0.reference.average_price', 10)
            ->assertJsonPath('data.0.reference.latest_available_observation.price', 10);
    }

    public function test_unsupported_units_are_not_compared_and_unavailable_listings_are_excluded(): void
    {
        $buyer = $this->user('buyer-unit-reference@example.test', 'Buyer');
        $listing = $this->listing('sack-price-reference@example.test', [
            'current_price_per_unit' => 500,
            'price_per_unit' => 500,
            'unit_of_measure' => 'sack',
        ]);
        $inactiveListing = $this->listing('inactive-price-reference@example.test', [
            'status' => 'Paused',
        ]);
        $cabbage = CommodityReference::query()->where('canonical_name', 'Cabbage')->firstOrFail();
        $this->observation($cabbage, 25, '2025-01-01', 'January 2025');

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/buyer/price-trends/references', [
                'listing_ids' => [$listing->listing_id, $inactiveListing->listing_id],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.listing_id', $listing->listing_id)
            ->assertJsonPath('data.0.status', 'UNIT_INCOMPATIBLE');

        $this->assertNull($response->json('data.0.comparison.normalized_listing_price'));
    }

    public function test_price_reference_comparisons_are_limited_to_buyer_accounts(): void
    {
        $producer = $this->user('producer-price-reference-access@example.test', 'Farmer');

        $this->actingAs($producer, 'sanctum')
            ->postJson('/api/buyer/price-trends/references', ['listing_ids' => [1]])
            ->assertForbidden();
    }
}
