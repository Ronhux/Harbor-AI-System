<?php

namespace Tests\Feature;

use App\Models\AiDataSource;
use App\Models\Buyer;
use App\Models\CommodityReference;
use App\Models\DemandRequest;
use App\Models\Negotiation;
use App\Models\Producer;
use App\Models\ProductListing;
use App\Models\SeasonalityReference;
use App\Models\User;
use App\Services\Analysis\DeterministicMarketAnalysisService;
use Carbon\CarbonImmutable;
use Database\Seeders\AiReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductAssistantAnalysisApiTest extends TestCase
{
    use RefreshDatabase;

    private CommodityReference $cabbage;
    private AiDataSource $psaSource;
    private array $aiResponse = [
        'status' => 'completed',
        'output' => [[
            'type' => 'message',
            'content' => [['type' => 'output_text', 'text' => 'Analysis is based only on the supplied reference data.']],
        ]],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.azure_openai.enabled' => true]);

        $this->seed(AiReferenceDataSeeder::class);
        $this->cabbage = CommodityReference::query()->where('canonical_name', 'Cabbage')->firstOrFail();
        $this->psaSource = AiDataSource::query()
            ->where('source_name', AiDataSource::SOURCE_PSA_MAJOR_CROPS)
            ->firstOrFail();
        Http::fake(fn () => Http::response($this->aiResponse, 200));
    }

    public function test_selected_month_returns_seasonality_without_azure_or_price_evidence(): void
    {
        config(['services.azure_openai.enabled'=>false]);
        [$user]=$this->createProducer();
        $this->actingAs($user)->postJson('/api/producer/product-assistant/analyze',[
            'product_name'=>'Sitaw','analysis_date'=>'2026-10-01','origin'=>'Isabela','unit'=>'kg','producer_price'=>40,
        ])->assertOk()->assertJsonPath('data.analysis_date','2026-10-01')
          ->assertJsonPath('data.seasonality.analysis_month',10)
          ->assertJsonPath('data.seasonality.references.0.signal.harvest_signal','undetermined')
          ->assertJsonPath('data.psa_reference.observation_count',0)
          ->assertJsonPath('data.ai_interpretation_status','UNAVAILABLE');
        Http::assertNothingSent();
        $this->postJson('/api/producer/product-assistant/analyze',['analysis_date'=>'not-a-date'])->assertUnprocessable();
    }

    public function test_endpoint_requires_authentication_and_producer_role(): void
    {
        $this->postJson('/api/producer/product-assistant/analyze', [])
            ->assertUnauthorized();

        $buyer = $this->createUser('Buyer');
        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/producer/product-assistant/analyze', [])
            ->assertForbidden()
            ->assertJsonPath('message', 'You are not authorized to access this resource.');
    }

    public function test_valid_context_returns_deterministic_facts_with_sources_kept_separate(): void
    {
        [$producerUser, $producer] = $this->createProducer();
        $this->createPsaObservation(10, '2024-01-01', 'January 2024');
        $this->createPsaObservation(20, '2024-02-01', 'February 2024');
        $this->createListing($producer, [
            'current_price_per_unit' => 40,
            'price_per_unit' => 40,
            'quantity_available' => 25,
            'quantity' => 25,
        ]);
        $this->createDemand();

        $response = $this->actingAs($producerUser, 'sanctum')
            ->postJson('/api/producer/product-assistant/analyze', [
                'product_name' => 'Cabbage',
                'category' => 'vegetables',
                'origin' => 'Brgy Centro',
                'unit' => 'kg',
                'quantity' => 5,
                'producer_price' => 25,
                'grade' => 'Grade A',
            ])
            ->assertOk();

        $response->assertJsonPath('data.commodity_resolution.status', 'RESOLVED');
        $response->assertJsonPath('data.input_context.origin', 'Brgy Centro');
        $response->assertJsonPath('data.input_context.quality_grade', 'Grade A');
        $response->assertJsonPath('data.psa_reference.source', 'Synthetic test references');
        $response->assertJsonPath('data.psa_reference.geography', 'Region II / Cagayan Valley');
        $response->assertJsonPath('data.psa_reference.price_type', 'farmgate');
        $response->assertJsonPath('data.psa_reference.currency', 'PHP');
        $response->assertJsonPath('data.psa_reference.unit', 'PHP/kg');
        $response->assertJsonPath('data.psa_reference.observation_count', 2);
        $response->assertJsonPath('data.psa_reference.minimum_price', 10);
        $response->assertJsonPath('data.psa_reference.maximum_price', 20);
        $response->assertJsonPath('data.psa_reference.average_price', 15);
        $response->assertJsonPath('data.psa_reference.median_price', 15);
        $response->assertJsonPath('data.psa_reference.latest_available_observation.date', '2024-02-01');
        $response->assertJsonPath('data.producer_price_comparison.difference_from_average', 10);
        $response->assertJsonPath('data.producer_price_comparison.difference_percent_from_average', 66.67);
        $response->assertJsonPath('data.producer_price_comparison.position_relative_to_range', 'ABOVE_REFERENCE_RANGE');
        $response->assertJsonPath('data.harborai_listing_reference.active_listing_count', 1);
        $response->assertJsonPath('data.harborai_listing_reference.average_active_listing_price', 40);
        $response->assertJsonPath('data.psa_reference.average_price', 15);
        $response->assertJsonPath('data.demand_context.active_demand_request_count', 1);
        $response->assertJsonPath('data.demand_context.requested_quantity_total', 2);
        $response->assertJsonPath('data.gross_listing_value.amount', 125);
        $response->assertJsonPath('data.data_sufficiency.status', 'HIGH');
        $response->assertJsonMissingPath('data.psa_reference.records');
        $response->assertJsonMissingPath('data.user');

        $json = $response->getContent();
        $this->assertStringNotContainsString('password_hash', $json);
        $this->assertStringNotContainsString('APP_KEY', $json);
        $this->assertStringNotContainsString('C:\\xampp', $json);
        $this->assertStringNotContainsString('Aparri farmgate', $json);
    }

    public function test_generic_eggplant_api_response_keeps_variety_series_separate(): void
    {
        [$producerUser] = $this->createProducer();
        $varieties = [
            'Eggplant, long, purple' => [11, 21],
            'Eggplant, native, long' => [101, 201],
            'Eggplant, native, round' => [1001, 2001],
        ];
        foreach ($varieties as $label => $prices) {
            $reference = CommodityReference::query()->where('canonical_name', $label)->firstOrFail();
            $this->createPsaObservation($prices[0], '2024-01-01', 'January 2024', [
                'commodity_reference_id' => $reference->id,
                'source_commodity_name' => $label,
            ]);
            $this->createPsaObservation($prices[1], '2024-02-01', 'February 2024', [
                'commodity_reference_id' => $reference->id,
                'source_commodity_name' => $label,
            ]);
        }

        $response = $this->actingAs($producerUser, 'sanctum')
            ->postJson('/api/producer/product-assistant/analyze', [
                'product_name' => 'Eggplant',
                'unit' => 'kg',
                'producer_price' => 50,
            ])
            ->assertOk();

        $response->assertJsonPath('data.input_context.product_name', 'Eggplant');
        $response->assertJsonPath('data.commodity_resolution.status', 'MULTIPLE_MATCHES');
        $response->assertJsonPath('data.psa_reference.status', 'MULTIPLE_REFERENCES');
        $response->assertJsonPath('data.producer_price_comparison.status', 'MULTIPLE_REFERENCES');
        $response->assertJsonCount(3, 'data.psa_reference_candidates');
        $response->assertJsonPath('data.psa_reference_candidates.0.psa_reference.observation_count', 2);
        $this->assertSame(
            ['Eggplant, long, purple', 'Eggplant, native, long', 'Eggplant, native, round'],
            array_column($response->json('data.psa_reference_candidates'), 'commodity_label')
        );
        $this->assertSame(
            [16, 151, 1501],
            array_column(array_column($response->json('data.psa_reference_candidates'), 'psa_reference'), 'average_price')
        );
    }

    public function test_unavailable_ai_keeps_deterministic_product_analysis_response(): void
    {
        [$producerUser] = $this->createProducer();
        config([
            'services.azure_openai.enabled' => true,
            'services.azure_openai.endpoint' => '',
            'services.azure_openai.api_key' => '',
        ]);

        $response = $this->actingAs($producerUser, 'sanctum')
            ->postJson('/api/producer/product-assistant/analyze', ['product_name' => 'Rice'])
            ->assertOk();

        $response->assertJsonPath('data.input_context.product_name', 'Rice');
        $response->assertJsonPath('data.psa_reference.status', 'NO_REFERENCE_AVAILABLE');
        $response->assertJsonPath('data.ai_interpretation_status', 'UNAVAILABLE');
        $response->assertJsonPath('data.ai_interpretation', null);
        $response->assertJsonPath('data.ai_interpretation_unavailable', true);
    }

    public function test_empty_provider_output_reports_ai_fallback_without_losing_deterministic_analysis(): void
    {
        [$producerUser] = $this->createProducer();
        config([
            'services.azure_openai.endpoint' => 'https://harborai-test.openai.azure.com/openai/v1',
            'services.azure_openai.api_key' => 'test-only-key',
        ]);
        $this->aiResponse = ['status' => 'completed', 'output' => []];

        $response = $this->actingAs($producerUser, 'sanctum')
            ->postJson('/api/producer/product-assistant/analyze', [
                'product_name' => 'Cabbage',
                'unit' => 'kg',
                'producer_price' => 32,
            ])
            ->assertOk();

        $response->assertJsonPath('data.input_context.product_name', 'Cabbage');
        $response->assertJsonPath('data.producer_price_comparison.input_price', 32);
        $response->assertJsonPath('data.ai_interpretation_status', 'UNAVAILABLE');
        $response->assertJsonPath('data.ai_interpretation_unavailable', true);
        $response->assertJsonPath('data.ai_interpretation_reason', 'empty_output');
        $response->assertJsonPath('data.ai_interpretation', null);
    }

    public function test_endpoint_invokes_the_deterministic_service_with_normalized_context(): void
    {
        [$producerUser] = $this->createProducer();
        $expectedContext = [
            'product_name' => 'Cabbage',
            'category' => 'vegetables',
            'origin' => 'Aparri, Cagayan',
            'unit' => 'kg',
            'quantity' => 12.5,
            'producer_price' => 31.25,
            'quality_grade' => 'Premium',
        ];
        $service = \Mockery::mock(DeterministicMarketAnalysisService::class);
        $service->shouldReceive('analyze')
            ->once()
            ->with($expectedContext)
            ->andReturn(['data_sufficiency' => ['status' => 'LIMITED']]);
        $this->app->instance(DeterministicMarketAnalysisService::class, $service);

        $this->actingAs($producerUser, 'sanctum')
            ->postJson('/api/producer/product-assistant/analyze', [
                'product_name' => 'Cabbage',
                'category' => 'vegetables',
                'origin' => 'Aparri, Cagayan',
                'unit' => 'kg',
                'quantity' => 12.5,
                'producer_price' => 31.25,
                'quality' => 'Standard',
                'grade' => 'Premium',
            ])
            ->assertOk()
            ->assertJsonPath('data.data_sufficiency.status', 'LIMITED');
    }

    public function test_missing_optional_fields_return_insufficient_data_without_guessing(): void
    {
        [$producerUser] = $this->createProducer();
        $response = $this->actingAs($producerUser, 'sanctum')
            ->postJson('/api/producer/product-assistant/analyze', [])
            ->assertOk();

        $response->assertJsonPath('data.commodity_resolution.status', 'UNRESOLVED');
        $response->assertJsonPath('data.psa_reference.status', 'NO_REFERENCE_AVAILABLE');
        $response->assertJsonPath('data.producer_price_comparison.status', 'NO_REFERENCE_AVAILABLE');
        $response->assertJsonPath('data.harborai_listing_reference.active_listing_count', 0);
        $response->assertJsonPath('data.demand_context.active_demand_request_count', 0);
        $response->assertJsonPath('data.data_sufficiency.status', 'INSUFFICIENT_DATA');
        $response->assertJsonPath('data.seasonality.status', 'INSUFFICIENT_DATA');
        $response->assertJsonPath('data.seasonality.references', []);
        $this->assertStringContainsString(
            'will not infer a season',
            $response->json('data.seasonality.interpretation')
        );

        $unmatched = $this->actingAs($producerUser, 'sanctum')
            ->postJson('/api/producer/product-assistant/analyze', [
                'product_name' => 'Cabbage-like vegetable',
                'category' => 'vegetables',
                'unit' => 'kg',
                'producer_price' => 50,
            ])
            ->assertOk();
        $unmatched->assertJsonPath('data.commodity_resolution.status', 'UNRESOLVED');
        $unmatched->assertJsonPath('data.psa_reference.observation_count', 0);
        $unmatched->assertJsonPath('data.psa_reference.average_price', null);
        $unmatched->assertJsonPath('data.data_sufficiency.status', 'INSUFFICIENT_DATA');
        $unmatched->assertJsonPath('data.seasonality.status', 'INSUFFICIENT_DATA');
    }

    public function test_verified_seasonality_is_deterministic_and_only_supplied_to_ai_context(): void
    {
        [$producerUser] = $this->createProducer();
        config([
            'services.azure_openai.endpoint' => 'https://harborai-test.openai.azure.com/openai/v1',
            'services.azure_openai.api_key' => 'test-only-key',
        ]);
        $source = AiDataSource::create([
            'source_name' => 'TEST Official Seasonality Source',
            'source_type' => AiDataSource::TYPE_OFFICIAL_REFERENCE,
            'organization' => 'TEST fixture organization - not a real source',
            'source_url' => 'https://example.test/test-seasonality',
            'is_official' => true,
            'is_active' => true,
        ]);
        SeasonalityReference::create([
            'commodity_reference_id' => $this->cabbage->id,
            'ai_data_source_id' => $source->id,
            'season_status' => 'harvest',
            'start_month' => 9,
            'end_month' => 12,
            'period_label' => 'September–December',
            'geographic_scope' => 'Region II / Cagayan Valley',
            'source_reference' => 'TEST FIXTURE ONLY - no real harvest data',
            'reference_date' => '2026-01-01',
            'verification_status' => 'verified',
        ]);

        CarbonImmutable::setTestNow('2026-10-15 12:00:00');
        try {
            $response = $this->actingAs($producerUser, 'sanctum')
                ->postJson('/api/producer/product-assistant/analyze', [
                    'product_name' => 'Repolyo',
                    'unit' => 'kg',
                    'producer_price' => 25,
                ])
                ->assertOk();

            $response->assertJsonPath('data.commodity_resolution.canonical_name', 'Cabbage');
            $response->assertJsonPath('data.seasonality.analysis_month_name', 'October');
            $response->assertJsonPath('data.seasonality.status', 'IN SEASON');
            $response->assertJsonPath('data.seasonality.references.0.periods.0', 'September–December');
            $response->assertJsonPath('data.seasonality.references.0.source_name', 'TEST Official Seasonality Source');
            $response->assertJsonPath('data.seasonality.references.0.geographic_scope', 'Region II / Cagayan Valley');
            $this->assertStringContainsString('documented harvest period', $response->json('data.seasonality.interpretation'));
            $response->assertJsonPath('data.ai_interpretation_status', 'AVAILABLE');

            Http::assertSent(function (ClientRequest $request): bool {
                $payload = $request->data();
                $context = json_decode((string) ($payload['input'] ?? ''), true);
                $seasonality = $context['seasonality'] ?? [];
                return is_array($seasonality)
                    && ($seasonality['status'] ?? null) === 'IN SEASON'
                    && ($seasonality['references'][0]['periods'][0] ?? null) === 'September–December'
                    && ($seasonality['references'][0]['source_name'] ?? null) === 'TEST Official Seasonality Source'
                    && !str_contains(json_encode($seasonality), 'January');
            });
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_missing_producer_price_returns_no_comparison_instead_of_an_invalid_value(): void
    {
        [$producerUser] = $this->createProducer();
        $this->createPsaObservation(15, '2024-01-01', 'January 2024');

        $this->actingAs($producerUser, 'sanctum')
            ->postJson('/api/producer/product-assistant/analyze', [
                'product_name' => 'Cabbage',
                'unit' => 'kg',
            ])
            ->assertOk()
            ->assertJsonPath('data.producer_price_comparison.status', 'PRICE_NOT_PROVIDED')
            ->assertJsonPath('data.producer_price_comparison.position_relative_to_range', null)
            ->assertJsonPath('data.data_sufficiency.status', 'MODERATE');
    }

    public function test_invalid_listing_values_and_unsupported_form_options_are_rejected(): void
    {
        [$producerUser] = $this->createProducer();

        $this->actingAs($producerUser, 'sanctum')
            ->postJson('/api/producer/product-assistant/analyze', [
                'quantity' => -1,
                'producer_price' => -0.01,
                'unit' => 'pound',
                'category' => 'Agriculture',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity', 'producer_price', 'unit', 'category']);
    }

    public function test_unexpected_analysis_error_returns_a_generic_json_error(): void
    {
        [$producerUser] = $this->createProducer();
        $service = \Mockery::mock(DeterministicMarketAnalysisService::class);
        $service->shouldReceive('analyze')->once()->andThrow(new \RuntimeException('internal test exception detail'));
        $this->app->instance(DeterministicMarketAnalysisService::class, $service);

        $response = $this->actingAs($producerUser, 'sanctum')
            ->postJson('/api/producer/product-assistant/analyze', [])
            ->assertInternalServerError()
            ->assertJsonPath('message', 'Product market analysis is temporarily unavailable.');

        $this->assertStringNotContainsString('internal test exception detail', $response->getContent());
        $this->assertStringNotContainsString('RuntimeException', $response->getContent());
    }

    public function test_endpoint_does_not_mutate_listings_prices_psa_observations_orders_or_negotiations(): void
    {
        [$producerUser, $producer] = $this->createProducer();
        $buyerUser = $this->createUser('Buyer');
        $buyer = Buyer::create(['user_id' => $buyerUser->user_id, 'organization_name' => 'Test Buyer']);
        $listing = $this->createListing($producer);
        $price = $this->createPsaObservation(18.5, '2024-01-01', 'January 2024');
        $negotiation = Negotiation::create([
            'reference' => 'READONLY-TEST-001',
            'buyer_id' => $buyer->buyer_id,
            'producer_id' => $producer->producer_id,
            'listing_id' => $listing->listing_id,
            'status' => 'Awaiting Producer Review',
        ]);
        DB::table('orders')->insert([
            'buyer_id' => $buyer->buyer_id,
            'product_listing_id' => $listing->listing_id,
            'negotiation_id' => $negotiation->id,
            'quantity' => 2,
            'total_price' => 40,
            'total_amount' => 40,
            'shipping_address' => 'Test only',
            'fulfillment_status' => 'Pending',
            'payment_status' => 'Pending',
            'status' => 'pending',
            'order_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $before = [
            'listings' => $this->tableRows('product_listings', 'listing_id'),
            'prices' => $this->tableRows('market_price_references', 'id'),
            'orders' => $this->tableRows('orders', 'order_id'),
            'negotiations' => $this->tableRows('negotiations', 'id'),
        ];

        $this->actingAs($producerUser, 'sanctum')
            ->postJson('/api/producer/product-assistant/analyze', [
                'product_name' => 'Cabbage',
                'unit' => 'kg',
                'quantity' => 2,
                'producer_price' => 20,
            ])
            ->assertOk();

        $this->assertSame($before['listings'], $this->tableRows('product_listings', 'listing_id'));
        $this->assertSame($before['prices'], $this->tableRows('market_price_references', 'id'));
        $this->assertSame($before['orders'], $this->tableRows('orders', 'order_id'));
        $this->assertSame($before['negotiations'], $this->tableRows('negotiations', 'id'));
    }

    private function createUser(string $type): User
    {
        return User::create([
            'email' => $type . '-' . uniqid('', true) . '@example.test',
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Test',
            'last_name' => $type,
            'user_type' => $type,
        ]);
    }

    /** @return array{0: User, 1: Producer} */
    private function createProducer(): array
    {
        $user = $this->createUser('Farmer');
        $producer = Producer::create([
            'user_id' => $user->user_id,
            'verification_status' => 'Verified',
            'producer_type' => 'Farmer',
            'location' => 'Cagayan Valley',
        ]);

        return [$user, $producer];
    }

    private function createPsaObservation(float $price, string $date, string $period, array $overrides = [])
    {
        return \App\Models\MarketPriceReference::create(array_merge([
            'commodity_reference_id' => $this->cabbage->id,
            'ai_data_source_id' => $this->psaSource->id,
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
        ], $overrides));
    }

    private function createListing(Producer $producer, array $overrides = []): ProductListing
    {
        return ProductListing::create(array_merge([
            'producer_id' => $producer->producer_id,
            'product_name' => 'Cabbage',
            'product_category' => 'Vegetables',
            'category' => 'Vegetables',
            'current_price_per_unit' => 40,
            'price_per_unit' => 40,
            'unit_of_measure' => 'kg',
            'unit' => 'kg',
            'quantity_available' => 25,
            'quantity' => 25,
            'status' => 'Active',
        ], $overrides));
    }

    private function createDemand(): DemandRequest
    {
        $buyerUser = $this->createUser('Buyer');
        $buyer = Buyer::create(['user_id' => $buyerUser->user_id, 'organization_name' => 'Test Buyer']);
        $demand = DemandRequest::create([
            'buyer_id' => $buyer->buyer_id,
            'product_name' => 'Cabbage',
            'category' => 'Vegetables',
            'quantity_needed' => 2,
            'unit' => 'kg',
            'status' => 'Active',
            'deadline' => today()->toDateString(),
        ]);
        $reviewer = User::create(['email'=>'reviewer-'.uniqid().'@example.test','first_name'=>'Synthetic','last_name'=>'Reviewer','user_type'=>'Admin','password_hash'=>'test-only']);
        $demand->publicationReviews()->create(['actor_user_id'=>$reviewer->user_id,'decision'=>'Approved']);
        return $demand;
    }

    private function tableRows(string $table, string $primaryKey): array
    {
        return DB::table($table)
            ->orderBy($primaryKey)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }
}
