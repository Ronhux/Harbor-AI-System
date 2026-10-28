<?php

namespace Tests\Feature;

use App\Services\AI\AzureOpenAiFailure;
use App\Services\AI\AzureOpenAiProviderException;
use App\Services\AI\AzureOpenAiResponsesProvider;
use App\Services\AI\ProductAssistantAiContextBuilder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AzureOpenAiResponsesProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.azure_openai.enabled' => true,
            'services.azure_openai.endpoint' => 'https://harborai-test.openai.azure.com',
            'services.azure_openai.api_key' => 'test-only-secret-key',
            'services.azure_openai.deployment' => 'gpt-5-mini',
        ]);
    }

    public function test_disabled_provider_never_sends_a_request_even_with_credentials(): void
    {
        config(['services.azure_openai.enabled'=>false]);
        Http::fake();
        try { app(AzureOpenAiResponsesProvider::class)->generate(['product'=>'Rice']); $this->fail('Expected disabled provider.'); }
        catch (AzureOpenAiProviderException $e) { $this->assertSame(AzureOpenAiFailure::CONFIGURATION, $e->failure); }
        Http::assertNothingSent();
    }

    public function test_documented_responses_api_shape_returns_nested_output_text(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => 'response-test-id',
                'status' => 'completed',
                'output' => [[
                    'type' => 'message',
                    'status' => 'completed',
                    'role' => 'assistant',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => '  Compare the provided references carefully.  ',
                    ]],
                ]],
            ], 200),
        ]);

        $result = app(AzureOpenAiResponsesProvider::class)->generate([
            'producer_input' => ['product_name' => 'Cabbage', 'producer_price' => 25],
        ]);

        $this->assertSame('Compare the provided references carefully.', $result);
        Http::assertSent(function (Request $request): bool {
            $body = json_decode($request->body(), true);

            return $request->method() === 'POST'
                && $request->url() === 'https://harborai-test.openai.azure.com/openai/v1/responses'
                && $request->hasHeader('api-key', 'test-only-secret-key')
                && ($body['model'] ?? null) === 'gpt-5-mini'
                && ($body['max_output_tokens'] ?? null) === 1500
                && ($body['reasoning']['effort'] ?? null) === 'low'
                && str_contains((string) ($body['instructions'] ?? ''), 'Region II / Cagayan Valley')
                && str_contains((string) ($body['instructions'] ?? ''), 'concise Markdown')
                && str_contains((string) ($body['instructions'] ?? ''), 'Price Assessment')
                && str_contains((string) ($body['instructions'] ?? ''), 'do not restate every statistic')
                && is_string($body['input'] ?? null)
                && str_contains($body['input'], 'Cabbage');
        });
        Http::assertSentCount(1);
    }

    public function test_ai_context_and_instructions_preserve_explicit_missing_psa_data(): void
    {
        Http::fake(['*' => Http::response([
            'status' => 'completed',
            'output' => [[
                'type' => 'message',
                'content' => [['type' => 'output_text', 'text' => 'No matching verified PSA reference is available.']],
            ]],
        ], 200)]);

        $context = app(ProductAssistantAiContextBuilder::class)->build([
            'input_context' => ['product_name' => 'Rice'],
            'commodity_resolution' => ['status' => 'UNRESOLVED', 'candidate_count' => 0],
            'psa_reference' => ['status' => 'NO_REFERENCE_AVAILABLE', 'observation_count' => 0],
            'psa_reference_candidates' => [],
        ]);

        $this->assertSame([], $context['psa_region_ii_series']);
        $this->assertSame('NO_REFERENCE_AVAILABLE', $context['psa_region_ii_reference']['status']);
        $this->assertSame('No matching verified PSA reference is available.', app(AzureOpenAiResponsesProvider::class)->generate($context));
        Http::assertSent(function (Request $request): bool {
            $body = json_decode($request->body(), true);
            $sentContext = json_decode((string) ($body['input'] ?? ''), true);

            return str_contains((string) ($body['instructions'] ?? ''), 'clearly say that no matching verified PSA reference is available')
                && is_array($sentContext)
                && ($sentContext['commodity_resolution']['status'] ?? null) === 'UNRESOLVED'
                && ($sentContext['psa_region_ii_reference']['status'] ?? null) === 'NO_REFERENCE_AVAILABLE'
                && ($sentContext['psa_region_ii_series'] ?? null) === [];
        });
    }

    public function test_incomplete_response_logs_only_bounded_shape_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => 'response-test-id',
                'status' => 'incomplete',
                'incomplete_details' => [
                    'reason' => 'max_output_tokens',
                    'private_detail' => 'must not appear in diagnostics',
                ],
                'max_output_tokens' => 1500,
                'usage' => [
                    'output_tokens' => 1500,
                    'output_tokens_details' => ['reasoning_tokens' => 1500],
                    'total_tokens' => 1620,
                ],
                'output' => [],
            ], 200),
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->with('Azure OpenAI Responses request failed.', \Mockery::on(function (array $context): bool {
                $diagnostics = $context['response_diagnostics'] ?? [];
                $diagnosticsJson = json_encode($diagnostics, JSON_THROW_ON_ERROR);

                $this->assertSame('empty_output', $context['failure']);
                $this->assertSame(200, $context['http_status']);
                $this->assertSame('incomplete', $diagnostics['response_status']);
                $this->assertSame('max_output_tokens', $diagnostics['incomplete_reason']);
                $this->assertSame(1500, $diagnostics['request_max_output_tokens']);
                $this->assertSame(1500, $diagnostics['response_max_output_tokens']);
                $this->assertSame(1500, $diagnostics['usage_output_tokens']);
                $this->assertSame(1500, $diagnostics['usage_reasoning_tokens']);
                $this->assertSame(1620, $diagnostics['usage_total_tokens']);
                $this->assertSame([], $diagnostics['output_item_types']);
                $this->assertSame([], $diagnostics['content_item_types']);
                $this->assertFalse($diagnostics['has_output_text_field']);
                $this->assertSame(0, $diagnostics['generated_text_length']);
                $this->assertFalse($diagnostics['is_completed']);
                $this->assertTrue($diagnostics['is_incomplete']);
                $this->assertFalse($diagnostics['is_refusal']);
                $this->assertStringNotContainsString('private_detail', $diagnosticsJson);
                $this->assertStringNotContainsString('must not appear in diagnostics', $diagnosticsJson);

                return true;
            }));

        try {
            app(AzureOpenAiResponsesProvider::class)->generate([
                'data_sufficiency' => ['status' => 'LIMITED'],
            ]);
            $this->fail('Expected an incomplete response with no text to fail safely.');
        } catch (AzureOpenAiProviderException $exception) {
            $this->assertSame(AzureOpenAiFailure::EMPTY_OUTPUT, $exception->failure);
        }
    }

    public function test_refusal_diagnostics_do_not_log_refusal_text(): void
    {
        $privateRefusal = 'private refusal detail that must not be logged';
        Http::fake([
            '*' => Http::response([
                'id' => 'response-test-id',
                'status' => 'completed',
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'refusal',
                        'refusal' => $privateRefusal,
                    ]],
                ]],
            ], 200),
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->with('Azure OpenAI Responses request failed.', \Mockery::on(function (array $context) use ($privateRefusal): bool {
                $diagnostics = $context['response_diagnostics'] ?? [];
                $diagnosticsJson = json_encode($diagnostics, JSON_THROW_ON_ERROR);

                $this->assertSame('refusal', $context['failure']);
                $this->assertTrue($diagnostics['is_refusal']);
                $this->assertSame(['message'], $diagnostics['output_item_types']);
                $this->assertSame(['refusal'], $diagnostics['content_item_types']);
                $this->assertStringNotContainsString($privateRefusal, $diagnosticsJson);

                return true;
            }));

        try {
            app(AzureOpenAiResponsesProvider::class)->generate([
                'data_sufficiency' => ['status' => 'LIMITED'],
            ]);
            $this->fail('Expected a refusal response to fail safely.');
        } catch (AzureOpenAiProviderException $exception) {
            $this->assertSame(AzureOpenAiFailure::REFUSAL, $exception->failure);
            $this->assertStringNotContainsString($privateRefusal, $exception->getMessage());
        }
    }

    public function test_endpoint_with_existing_v1_suffix_is_not_duplicated(): void
    {
        config(['services.azure_openai.endpoint' => 'https://harborai-test.openai.azure.com/openai/v1/']);
        Http::fake(['*' => $this->textResponse('Ready.')]);

        app(AzureOpenAiResponsesProvider::class)->generate(['data_sufficiency' => ['status' => 'LIMITED']]);

        Http::assertSent(fn (Request $request): bool => $request->url()
            === 'https://harborai-test.openai.azure.com/openai/v1/responses');
    }

    public function test_empty_deployment_uses_the_gpt_5_mini_default(): void
    {
        config(['services.azure_openai.deployment' => '']);
        Http::fake(['*' => Http::response($this->textResponse('Ready.'), 200)]);

        app(AzureOpenAiResponsesProvider::class)->generate(['data_sufficiency' => ['status' => 'LIMITED']]);

        Http::assertSent(function (Request $request): bool {
            $body = json_decode($request->body(), true);

            return ($body['model'] ?? null) === 'gpt-5-mini';
        });
    }

    public function test_missing_configuration_fails_without_sending_a_request(): void
    {
        config(['services.azure_openai.api_key' => '']);
        Http::fake();

        try {
            app(AzureOpenAiResponsesProvider::class)->generate(['data_sufficiency' => ['status' => 'LIMITED']]);
            $this->fail('Expected a typed configuration exception.');
        } catch (AzureOpenAiProviderException $exception) {
            $this->assertSame(AzureOpenAiFailure::CONFIGURATION, $exception->failure);
            $this->assertStringNotContainsString('test-only-secret-key', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    #[DataProvider('failedHttpResponses')]
    public function test_http_failures_are_typed_and_do_not_expose_provider_body(
        int $status,
        AzureOpenAiFailure $failure,
    ): void {
        Http::fake(['*' => Http::response(['error' => ['message' => 'test-only-secret-key private detail']], $status)]);

        try {
            app(AzureOpenAiResponsesProvider::class)->generate(['data_sufficiency' => ['status' => 'LIMITED']]);
            $this->fail('Expected a typed provider exception.');
        } catch (AzureOpenAiProviderException $exception) {
            $this->assertSame($failure, $exception->failure);
            $this->assertSame($status, $exception->httpStatus);
            $this->assertStringNotContainsString('test-only-secret-key', $exception->getMessage());
            $this->assertStringNotContainsString('private detail', $exception->getMessage());
        }

        Http::assertSentCount(1);
    }

    public static function failedHttpResponses(): array
    {
        return [
            'unauthorized' => [401, AzureOpenAiFailure::AUTHENTICATION],
            'forbidden' => [403, AzureOpenAiFailure::AUTHENTICATION],
            'rate limited' => [429, AzureOpenAiFailure::RATE_LIMIT],
            'other client error' => [422, AzureOpenAiFailure::REQUEST_REJECTED],
            'upstream error' => [503, AzureOpenAiFailure::UPSTREAM],
        ];
    }

    public function test_connection_timeout_is_reported_without_transport_details(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        try {
            app(AzureOpenAiResponsesProvider::class)->generate(['data_sufficiency' => ['status' => 'LIMITED']]);
            $this->fail('Expected a timeout exception.');
        } catch (AzureOpenAiProviderException $exception) {
            $this->assertSame(AzureOpenAiFailure::TIMEOUT, $exception->failure);
            $this->assertStringNotContainsString('cURL', $exception->getMessage());
        }
    }

    public function test_network_failure_is_reported_without_transport_details(): void
    {
        Http::fake(fn () => throw new ConnectionException('Could not connect to endpoint'));
        try {
            app(AzureOpenAiResponsesProvider::class)->generate(['data_sufficiency' => ['status' => 'LIMITED']]);
            $this->fail('Expected a network exception.');
        } catch (AzureOpenAiProviderException $exception) {
            $this->assertSame(AzureOpenAiFailure::NETWORK, $exception->failure);
            $this->assertStringNotContainsString('endpoint', $exception->getMessage());
        }
    }

    #[DataProvider('invalidSuccessfulBodies')]
    public function test_malformed_empty_and_refused_output_are_typed_safely(
        mixed $body,
        int $status,
        AzureOpenAiFailure $failure,
    ): void {
        Http::fake(['*' => Http::response($body, $status)]);

        try {
            app(AzureOpenAiResponsesProvider::class)->generate(['data_sufficiency' => ['status' => 'LIMITED']]);
            $this->fail('Expected an output exception.');
        } catch (AzureOpenAiProviderException $exception) {
            $this->assertSame($failure, $exception->failure);
            $this->assertStringNotContainsString('test-only-secret-key', $exception->getMessage());
        }
    }

    public static function invalidSuccessfulBodies(): array
    {
        return [
            'malformed response' => ['not-json', 200, AzureOpenAiFailure::MALFORMED_RESPONSE],
            'missing output list' => [['id' => 'test'], 200, AzureOpenAiFailure::MALFORMED_RESPONSE],
            'no output text' => [['output' => []], 200, AzureOpenAiFailure::EMPTY_OUTPUT],
            'empty output text' => [[
                'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => '  ']]]],
            ], 200, AzureOpenAiFailure::EMPTY_OUTPUT],
            'refusal' => [[
                'output' => [['type' => 'message', 'content' => [['type' => 'refusal', 'refusal' => 'Private details']]]],
            ], 200, AzureOpenAiFailure::REFUSAL],
        ];
    }

    public function test_context_builder_omits_identity_origin_ids_and_unlisted_fields(): void
    {
        $analysis = [
            'analysis_date' => '2026-10-06',
            'input_context' => [
                'product_name' => 'Cabbage',
                'category' => 'Vegetables',
                'origin' => 'Brgy Centro, Aparri',
                'unit' => 'kg',
                'quantity' => 5,
                'producer_price' => 25,
                'currency' => 'PHP',
                'quality_grade' => 'Grade A',
                'producer_email' => 'producer@example.test',
            ],
            'commodity_resolution' => [
                'status' => 'RESOLVED',
                'canonical_name' => 'Cabbage',
                'commodity_reference_id' => 42,
            ],
            'psa_reference' => [
                'geography' => 'Aparri-specific test value',
                'average_price' => 18,
                'observation_count' => 3,
                'records' => [['email' => 'private@example.test']],
            ],
            'psa_reference_candidates' => [[
                'commodity_label' => 'Cabbage, test variety',
                'psa_reference' => [
                    'observation_count' => 2,
                    'minimum_price' => 10,
                    'maximum_price' => 20,
                    'average_price' => 15,
                    'median_price' => 15,
                    'latest_available_observation' => ['date' => '2024-02-01', 'price' => 20],
                    'raw_record' => 'must not be sent',
                ],
            ]],
            'harborai_listing_reference' => ['active_listing_count' => 4],
            'demand_context' => ['active_demand_request_count' => 2],
            'database_dump' => ['secret' => 'never send'],
        ];

        $context = app(ProductAssistantAiContextBuilder::class)->build($analysis);
        $json = json_encode($context, JSON_THROW_ON_ERROR);

        $this->assertSame('Cabbage', $context['producer_input']['product_name']);
        $this->assertSame('Region II / Cagayan Valley', $context['psa_region_ii_reference']['geography']);
        $this->assertSame(18, $context['psa_region_ii_reference']['average_price']);
        $this->assertSame('Cabbage, test variety', $context['psa_region_ii_series'][0]['commodity_label']);
        $this->assertSame(2, $context['psa_region_ii_series'][0]['observation_count']);
        $this->assertSame(4, $context['harborai_listing_reference']['active_listing_count']);
        $this->assertSame(2, $context['harborai_buyer_requests']['active_demand_request_count']);
        $this->assertStringNotContainsString('Aparri', $json);
        $this->assertStringNotContainsString('producer@example.test', $json);
        $this->assertStringNotContainsString('private@example.test', $json);
        $this->assertStringNotContainsString('commodity_reference_id', $json);
        $this->assertStringNotContainsString('raw_record', $json);
        $this->assertStringNotContainsString('must not be sent', $json);
        $this->assertStringNotContainsString('database_dump', $json);
        $this->assertStringNotContainsString('never send', $json);
    }

    private function textResponse(string $text): array
    {
        return [
            'output' => [[
                'type' => 'message',
                'content' => [['type' => 'output_text', 'text' => $text]],
            ]],
        ];
    }
}
