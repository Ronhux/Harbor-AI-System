<?php

namespace App\Http\Controllers;

use App\Models\CommodityReference;
use App\Services\AI\AzureOpenAiProviderException;
use App\Services\AI\AzureOpenAiResponsesProvider;
use App\Services\AI\ProductAssistantAiContextBuilder;
use App\Services\AI\ProductAssistantInterpretation;
use App\Services\AI\ProductAssistantSummary;
use App\Services\Analysis\DeterministicMarketAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class ProductAssistantAnalysisController extends Controller
{
    private const UNITS = ['kg', 'g', 'sack', 'piece'];

    public function analyze(
        Request $request,
        DeterministicMarketAnalysisService $analysis,
        ProductAssistantAiContextBuilder $contextBuilder,
        AzureOpenAiResponsesProvider $provider,
    ) {
        // Match the producer listing form's fields and validation bounds without
        // requiring values that the existing assistant allows producers to omit.
        $data = $request->validate([
            'product_name' => ['nullable', 'string', 'max:255'],
            'category' => [
                'nullable',
                'string',
                'max:255',
                Rule::in(array_merge(
                    CommodityReference::CATEGORIES,
                    array_map('strtolower', CommodityReference::CATEGORIES)
                )),
            ],
            'origin' => ['nullable', 'string', 'max:255'],
            'analysis_date' => ['nullable', 'date_format:Y-m-d'],
            'unit' => ['nullable', 'string', Rule::in(self::UNITS)],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'producer_price' => ['nullable', 'numeric', 'min:0'],
            'quality' => ['nullable', 'string', 'max:255'],
            'grade' => ['nullable', 'string', 'max:255'],
        ]);

        $context = [
            'product_name' => $data['product_name'] ?? null,
            'category' => $data['category'] ?? null,
            'origin' => $data['origin'] ?? null,
            'unit' => $data['unit'] ?? null,
            'quantity' => $data['quantity'] ?? null,
            'producer_price' => $data['producer_price'] ?? null,
            // Phase 3 exposes one quality-grade context; preserve either form field.
            'quality_grade' => $data['grade'] ?? $data['quality'] ?? null,
        ];

        if (!empty($data['analysis_date'])) $context['analysis_date'] = $data['analysis_date'];

        try {
            $analysisResult = $analysis->analyze($context);
            $aiInterpretation = null;
            $aiStatus = 'UNAVAILABLE';
            $aiReason = null;

            try {
                $aiInterpretation = $provider->generate($contextBuilder->build($analysisResult));
                $aiStatus = 'AVAILABLE';
            } catch (Throwable $aiException) {
                report($aiException);
                $aiReason = $aiException instanceof AzureOpenAiProviderException
                    ? $aiException->failure->value
                    : 'unexpected_error';
            }

            // The deterministic interpretation is built from the analysis that already
            // succeeded, so an unconfigured Azure provider can no longer hide it. It is
            // always produced; Azure, when present, only adds an extra narrative layer on top.
            $interpretation = app(ProductAssistantInterpretation::class)
                ->build($analysisResult, $context, $aiStatus);

            $analysisResult['ai_interpretation'] = $aiInterpretation;
            $analysisResult['ai_interpretation_status'] = $aiStatus;
            $analysisResult['ai_interpretation_unavailable'] = $aiInterpretation === null;
            $analysisResult['ai_interpretation_reason'] = $aiReason;

            $analysisResult['interpretation'] = $interpretation;
            // The composed reading is always present, so a user-facing screen can never be
            // reduced to "unavailable" while real evidence sits unused.
            $analysisResult['interpretation_available'] = true;

            // The short farmer-facing reading shown by default. The full narratives above stay
            // available in the collapsed evidence section, so nothing is lost by not repeating it.
            $analysisResult['summary'] = app(ProductAssistantSummary::class)->build($analysisResult, $context);

            return response()->json(['data' => $analysisResult]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Product market analysis is temporarily unavailable.',
            ], 500);
        }
    }
}
