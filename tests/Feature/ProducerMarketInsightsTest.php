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

class ProducerMarketInsightsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AiReferenceDataSeeder::class);
    }

    private function makeProducer(string $email, string $status = 'Verified'): array
    {
        $user = User::create([
            'email' => $email,
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Test',
            'last_name' => 'Producer',
            'user_type' => 'Farmer',
        ]);
        $producer = Producer::create([
            'user_id' => $user->user_id,
            'verification_status' => $status,
            'producer_type' => 'Farmer',
        ]);

        return [$user, $producer];
    }

    public function test_market_insights_recompute_from_the_authenticated_producers_actual_active_listings(): void
    {
        [$user, $producer] = $this->makeProducer('market-insights@example.test');
        [, $otherProducer] = $this->makeProducer('other-market-insights@example.test');
        $listing = ProductListing::create([
            'producer_id' => $producer->producer_id,
            'product_name' => 'Cabbage',
            'category' => 'Vegetables',
            'product_category' => 'Vegetables',
            'current_price_per_unit' => 30,
            'price_per_unit' => 30,
            'quantity_available' => 12,
            'quantity' => 12,
            'unit' => 'kg',
            'unit_of_measure' => 'kg',
            'status' => 'Active',
        ]);
        ProductListing::create([
            'producer_id' => $producer->producer_id,
            'product_name' => 'Paused Cabbage',
            'current_price_per_unit' => 50,
            'quantity_available' => 4,
            'unit' => 'kg',
            'status' => 'Paused',
        ]);
        ProductListing::create([
            'producer_id' => $otherProducer->producer_id,
            'product_name' => 'Cabbage',
            'current_price_per_unit' => 90,
            'quantity_available' => 10,
            'unit' => 'kg',
            'status' => 'Active',
        ]);

        $commodity = CommodityReference::query()->where('canonical_name', 'Cabbage')->firstOrFail();
        $source = AiDataSource::query()->where('source_name', AiDataSource::SOURCE_PSA_MAJOR_CROPS)->firstOrFail();
        MarketPriceReference::create([
            'commodity_reference_id' => $commodity->id,
            'ai_data_source_id' => $source->id,
            'price' => 25,
            'currency' => 'PHP',
            'unit' => 'Peso per kilogram',
            'price_type' => 'farmgate',
            'geographic_scope' => 'Region II / Cagayan Valley',
            'source_reference' => 'TEST FIXTURE ONLY - not actual PSA data',
            'source_commodity_name' => 'Cabbage',
            'source_geographic_scope' => '..REGION II (CAGAYAN VALLEY)',
            'observation_date' => '2025-01-01',
            'observation_period' => 'January 2025',
            'period_frequency' => 'monthly',
            'observation_status' => 'preliminary',
            'verification_status' => 'verified',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/producer/market-insights')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.listing.listing_id', $listing->listing_id)
            ->assertJsonPath('data.0.listing.price_per_unit', 30)
            ->assertJsonPath('data.0.analysis.psa_reference.average_price', 25)
            ->assertJsonPath('data.0.analysis.psa_reference.source', 'Synthetic test references')
            ->assertJsonPath('data.0.analysis.psa_reference.geography', 'Region II / Cagayan Valley');

        $this->assertArrayNotHasKey('forecast', $response->json('data.0.analysis'));
        $this->assertArrayNotHasKey('profit_estimate', $response->json('data.0.analysis'));
    }

    public function test_unverified_producers_cannot_refresh_market_insights(): void
    {
        [$user] = $this->makeProducer('pending-market-insights@example.test', 'Pending');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/producer/market-insights')
            ->assertForbidden()
            ->assertJsonPath('verification_required', true);
    }

    public function test_approved_local_commodity_alias_uses_latest_verified_psa_reference(): void
    {
        [$user, $producer] = $this->makeProducer('repolyo-market-insights@example.test');
        $listing = ProductListing::create([
            'producer_id' => $producer->producer_id,
            'product_name' => 'Repolyo',
            'category' => 'Vegetables',
            'product_category' => 'Vegetables',
            'current_price_per_unit' => 35,
            'price_per_unit' => 35,
            'quantity_available' => 12,
            'quantity' => 12,
            'unit' => 'kg',
            'unit_of_measure' => 'kg',
            'status' => 'Active',
        ]);

        $commodity = CommodityReference::query()->where('canonical_name', 'Cabbage')->firstOrFail();
        $source = AiDataSource::query()->where('source_name', AiDataSource::SOURCE_PSA_MAJOR_CROPS)->firstOrFail();
        foreach ([[10, '2025-01-01', 'January 2025'], [30, '2025-02-01', 'February 2025']] as [$price, $date, $period]) {
            MarketPriceReference::create([
                'commodity_reference_id' => $commodity->id,
                'ai_data_source_id' => $source->id,
                'price' => $price,
                'currency' => 'PHP',
                'unit' => 'Peso per kilogram',
                'price_type' => 'farmgate',
                'geographic_scope' => 'Region II / Cagayan Valley',
                'source_reference' => 'TEST FIXTURE ONLY - not actual PSA data',
                'source_commodity_name' => 'Cabbage',
                'source_geographic_scope' => '..REGION II (CAGAYAN VALLEY)',
                'observation_date' => $date,
                'observation_period' => $period,
                'period_frequency' => 'monthly',
                'observation_status' => 'preliminary',
                'verification_status' => 'verified',
            ]);
        }

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/producer/market-insights')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.listing.listing_id', $listing->listing_id)
            ->assertJsonPath('data.0.analysis.commodity_resolution.canonical_name', 'Cabbage')
            ->assertJsonPath('data.0.analysis.psa_reference.average_price', 20)
            ->assertJsonPath('data.0.analysis.psa_reference.latest_available_observation.price', 30)
            ->assertJsonPath('data.0.analysis.producer_price_comparison.status', 'COMPARABLE')
            ->assertJsonPath('data.0.analysis.producer_price_comparison.difference_from_latest_reference', 5)
            ->assertJsonPath('data.0.analysis.producer_price_comparison.difference_percent_from_latest_reference', 16.67);
    }
}
