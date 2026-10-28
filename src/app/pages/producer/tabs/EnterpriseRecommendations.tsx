import { useCallback, useEffect, useState } from 'react';
import { AlertCircle, Brain, RefreshCw } from 'lucide-react';
import { request } from '../../../../lib/api';
import { Badge } from '../../../components/ui/badge';
import { Button } from '../../../components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../../../components/ui/card';

type InsightItem = {
  listing: {
    listing_id: number;
    product_name: string;
    price_per_unit: number | null;
    unit?: string | null;
    quantity_available: number | null;
    location?: string | null;
  };
  analysis: {
    analysis_date: string;
    commodity_resolution?: {
      status?: string;
      canonical_name?: string | null;
      candidates?: Array<{ canonical_name: string; match_method: string }>;
      source_coverage?: {
        status?: string;
        covered_series?: string[];
        unmapped_source_siblings?: string[];
        interpretation?: string;
      };
    };
    psa_reference?: {
      status?: string;
      source?: string;
      geography?: string;
      price_type?: string;
      observation_count?: number;
      minimum_price?: number | null;
      maximum_price?: number | null;
      average_price?: number | null;
      unit?: string;
      currency?: string;
      latest_available_observation?: { date?: string | null; price?: number | null } | null;
    };
    producer_price_comparison?: {
      status?: string;
      position_relative_to_range?: string | null;
      latest_reference_price?: number | null;
      difference_from_latest_reference?: number | null;
      difference_percent_from_latest_reference?: number | null;
    };
    psa_reference_candidates?: Array<{
      commodity_label: string;
      match_method: string;
      psa_reference: {
        status?: string;
        observation_count?: number;
        average_price?: number | null;
        minimum_price?: number | null;
        maximum_price?: number | null;
        unit?: string;
        currency?: string;
        latest_available_observation?: { date?: string | null; price?: number | null } | null;
      };
      producer_price_comparison?: {
        status?: string;
        difference_from_latest_reference?: number | null;
        difference_percent_from_latest_reference?: number | null;
      };
    }>;
    supply_context?: { active_listing_count?: number; quantity_total?: number | null; quantity_unit?: string | null };
    demand_context?: {
      status?: string;
      active_demand_request_count?: number;
      requested_quantity_total?: number | null;
      quantity_unit?: string | null;
      quantity_status?: string;
    };
    data_sufficiency?: { status?: string; basis?: string };
  };
};

type InsightsResponse = { generated_at: string; source: string; data: InsightItem[] };

// Only finite numbers are formatted. A legitimate 0 is finite and still
// renders as currency; null, undefined, NaN and Infinity do not, because a
// non-finite value has no currency meaning and must not be shown as a price.
const money = (value: number | null | undefined, currency = 'PHP') => {
  if (typeof value !== 'number' || !Number.isFinite(value)) return 'Not available';

  return new Intl.NumberFormat('en-PH', { style: 'currency', currency, maximumFractionDigits: 2 }).format(value);
};

const differenceText = (difference: number | null | undefined, percent: number | null | undefined) => {
  if (difference == null || !Number.isFinite(difference)) return 'No comparable price difference is available.';
  const direction = difference > 0 ? 'above' : difference < 0 ? 'below' : 'equal to';
  return `The listing is ${money(Math.abs(difference))} per kg ${direction} the latest reference${percent == null ? '' : ` (${Math.abs(percent)}%)`}.`;
};

export default function EnterpriseRecommendations() {
  const [result, setResult] = useState<InsightsResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [expanded, setExpanded] = useState<number | null>(null);

  const refresh = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      setResult(await request('/api/producer/market-insights') as InsightsResponse);
      setExpanded(null);
    } catch (failure) {
      setError(failure instanceof Error ? failure.message : 'Could not refresh market insights.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { void refresh(); }, [refresh]);

  return (
    <div className="space-y-6 p-4 sm:p-6">
      <header className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="flex items-center gap-3 font-display text-2xl text-[#123C5C] sm:text-3xl">
            <Brain className="h-8 w-8 text-[#0F9488]" />Market Insights
          </h1>
          <p className="mt-1 text-sm text-[#45586B]">Recomputed from your current active listings, persisted HarborAI market activity, and available verified references.</p>
        </div>
        <Button type="button" variant="outline" onClick={() => void refresh()} disabled={loading} className="min-h-11 shrink-0">
          <RefreshCw className={`mr-2 h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
          {loading ? 'Refreshing…' : 'Refresh Analysis'}
        </Button>
      </header>

      {result && <p className="text-xs text-[#617184]">Recomputed {new Date(result.generated_at).toLocaleString()}. {result.source}</p>}
      {error && <div role="alert" className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><AlertCircle className="mr-2 inline h-4 w-4" />{error}</div>}

      {loading && !result ? <p className="py-10 text-center text-[#45586B]">Loading supported market evidence…</p>
        : !error && result?.data.length === 0 ? <Card><CardContent className="p-8 text-center text-[#45586B]">No active in-stock listings are available for analysis. No opportunities or forecasts are inferred.</CardContent></Card>
          : <div className="space-y-4">{result?.data.map(({ listing, analysis }) => {
            const reference = analysis.psa_reference;
            const demand = analysis.demand_context;
            const hasComparableReference = analysis.producer_price_comparison?.status === 'COMPARABLE' && reference?.average_price != null;
            const unmappedSiblings = analysis.commodity_resolution?.source_coverage?.unmapped_source_siblings ?? [];
            return (
              <Card key={listing.listing_id} className="border-2 border-[#E7E1D0]">
                <CardHeader>
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <CardTitle className="text-xl text-[#123C5C]">{listing.product_name}</CardTitle>
                      <CardDescription>Listing #{listing.listing_id} · {listing.quantity_available ?? 'Quantity not reported'} {listing.unit || ''} available · {listing.location || 'Location not listed'}</CardDescription>
                    </div>
                    <Badge variant="secondary">{money(listing.price_per_unit)} / {listing.unit || 'unit'}</Badge>
                  </div>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="grid gap-3 sm:grid-cols-3">
                    <div className="rounded-lg bg-[#F5F1E5] p-3"><p className="text-xs font-bold uppercase text-[#617184]">PSA Region II farmgate reference</p><p className="mt-1 text-sm font-bold text-[#123C5C]">{hasComparableReference ? `${money(reference?.average_price, reference?.currency)} / ${reference?.unit || 'unit'}` : 'No comparable reference'}</p><p className="text-xs text-[#617184]">{reference?.source || 'No source available'} · {reference?.geography || 'Geography not specified'}</p></div>
                    <div className="rounded-lg bg-[#F5F1E5] p-3"><p className="text-xs font-bold uppercase text-[#617184]">Recorded active demand</p><p className="mt-1 text-sm font-bold text-[#123C5C]">{demand?.active_demand_request_count ?? 0} request(s)</p><p className="text-xs text-[#617184]">{demand?.requested_quantity_total == null ? 'No matching request quantity available' : `${demand.requested_quantity_total} ${demand.quantity_unit || ''} requested`}</p></div>
                    <div className="rounded-lg bg-[#F5F1E5] p-3"><p className="text-xs font-bold uppercase text-[#617184]">Your price vs reference range</p><p className="mt-1 text-sm font-bold capitalize text-[#123C5C]">{analysis.producer_price_comparison?.position_relative_to_range?.replaceAll('_', ' ').toLowerCase() || 'Not comparable'}</p><p className="text-xs text-[#617184]">No forecast or price change is applied.</p></div>
                  </div>

                  <p className="rounded-lg border border-[#D8E8F0] bg-[#F3FAFD] p-4 text-sm leading-6 text-[#45586B]">
                    {hasComparableReference
                      ? `Would comparing your asking price with the latest ${reference?.geography || 'regional'} reference help you decide whether to keep or revise it?`
                      : 'Would waiting for a comparable verified reference help you evaluate this listing? The available data does not support a conclusion yet.'}
                  </p>

                  {unmappedSiblings.length > 0 && (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm leading-6 text-amber-950">
                      <p className="font-semibold">Partial source coverage</p>
                      <p className="mt-1 text-xs">
                        PSA publishes further series for this product that HarborAI has no registered commodity reference for, so the
                        figures above cover the matched series only and are not combined with an unmapped series.
                      </p>
                      <ul className="ml-5 mt-1 list-disc text-xs">
                        {unmappedSiblings.map((sibling) => <li key={sibling}>{sibling}</li>)}
                      </ul>
                    </div>
                  )}

                  <div className="flex flex-wrap items-center justify-between gap-3 border-t border-[#E7E1D0] pt-3">
                    <p className="text-xs text-[#617184]">Analysis date: {analysis.analysis_date}. Final pricing decisions remain yours.</p>
                    <Button type="button" variant="outline" aria-expanded={expanded === listing.listing_id} onClick={() => setExpanded((current) => current === listing.listing_id ? null : listing.listing_id)}>
                      {expanded === listing.listing_id ? 'Hide Analysis' : 'View Analysis'}
                    </Button>
                  </div>
                  {expanded === listing.listing_id && (
                    <section className="space-y-4 rounded-lg border border-[#D8E8F0] bg-[#F8FBFC] p-4" aria-label={`Market analysis for ${listing.product_name}`}>
                      <div>
                        <h3 className="font-bold text-[#123C5C]">Market analysis</h3>
                        <p className="mt-1 text-sm text-[#617184]">Calculated {analysis.analysis_date}. Reference prices are regional farmgate observations, not a forecast or a recommended asking price.</p>
                      </div>

                      <div className="grid gap-3 sm:grid-cols-2">
                        <div className="rounded-lg bg-white p-3">
                          <h4 className="text-xs font-bold uppercase text-[#617184]">PSA reference</h4>
                          <p className="mt-1 text-sm text-[#123C5C]">{reference?.source || 'No source available'} · {reference?.geography || 'Geography not specified'}</p>
                          {reference?.status === 'MULTIPLE_REFERENCES' ? (
                            <p className="mt-1 text-sm text-[#45586B]">Multiple PSA commodity series matched. They are shown separately below and are not combined.</p>
                          ) : reference?.status === 'AVAILABLE' ? (
                            <>
                              <p className="mt-1 text-sm text-[#45586B]">{reference.price_type || 'Farmgate'} · {reference.observation_count ?? 0} verified monthly observation(s)</p>
                              <p className="mt-1 text-sm text-[#45586B]">Historical range: {money(reference.minimum_price, reference.currency)}–{money(reference.maximum_price, reference.currency)} / {reference.unit || 'unit'}</p>
                              <p className="mt-1 text-sm text-[#45586B]">Latest observation: {reference.latest_available_observation?.date || 'Not available'} · {money(reference.latest_available_observation?.price, reference.currency)} / {reference.unit || 'unit'}</p>
                            </>
                          ) : (
                            <p className="mt-1 text-sm text-[#45586B]">No verified monthly observation is available for the matched series.</p>
                          )}
                        </div>
                        <div className="rounded-lg bg-white p-3">
                          <h4 className="text-xs font-bold uppercase text-[#617184]">Asking price comparison</h4>
                          <p className="mt-1 text-sm text-[#123C5C]">{analysis.producer_price_comparison?.status?.replaceAll('_', ' ') || 'Not comparable'}</p>
                          <p className="mt-1 text-sm text-[#45586B]">{differenceText(
                            analysis.producer_price_comparison?.difference_from_latest_reference,
                            analysis.producer_price_comparison?.difference_percent_from_latest_reference,
                          )}</p>
                          <p className="mt-1 text-xs text-[#617184]">Per-kilogram equivalent; comparisons are shown only when the commodity, currency, and unit are compatible.</p>
                        </div>
                      </div>

                      {analysis.psa_reference_candidates?.length ? (
                        <div className="rounded-lg bg-white p-3">
                          <h4 className="text-xs font-bold uppercase text-[#617184]">Matched PSA commodity series</h4>
                          <div className="mt-2 divide-y divide-[#E7E1D0]">
                            {analysis.psa_reference_candidates.map((candidate) => (
                              <div key={candidate.commodity_label} className="py-2 text-sm text-[#45586B]">
                                <p className="font-semibold text-[#123C5C]">{candidate.commodity_label}</p>
                                <p>{candidate.psa_reference.observation_count ?? 0} observation(s) · latest {candidate.psa_reference.latest_available_observation?.date || 'not available'} at {money(candidate.psa_reference.latest_available_observation?.price, candidate.psa_reference.currency)} / {candidate.psa_reference.unit || 'unit'}</p>
                                <p>Listing comparison: {candidate.producer_price_comparison?.status?.replaceAll('_', ' ') || 'not comparable'}. {candidate.producer_price_comparison?.status === 'COMPARABLE' ? differenceText(candidate.producer_price_comparison.difference_from_latest_reference, candidate.producer_price_comparison.difference_percent_from_latest_reference) : ''}</p>
                              </div>
                            ))}
                          </div>
                        </div>
                      ) : (
                        <p className="rounded-lg bg-white p-3 text-sm text-[#45586B]">No verified PSA commodity series was matched. No reference price has been inferred.</p>
                      )}

                      <div className="grid gap-3 sm:grid-cols-2">
                        <div className="rounded-lg bg-white p-3">
                          <h4 className="text-xs font-bold uppercase text-[#617184]">HarborAI supply</h4>
                          <p className="mt-1 text-sm text-[#45586B]">{analysis.supply_context?.active_listing_count ?? 0} active in-stock listing(s); {analysis.supply_context?.quantity_total == null ? 'quantity total unavailable' : `${analysis.supply_context.quantity_total} ${analysis.supply_context.quantity_unit || ''} recorded`}</p>
                        </div>
                        <div className="rounded-lg bg-white p-3">
                          <h4 className="text-xs font-bold uppercase text-[#617184]">Institutional buyer demand</h4>
                          <p className="mt-1 text-sm text-[#45586B]">{demand?.active_demand_request_count ?? 0} active request(s); {demand?.requested_quantity_total == null ? 'compatible quantity total unavailable' : `${demand.requested_quantity_total} ${demand.quantity_unit || ''} requested`}</p>
                        </div>
                      </div>

                      <p className="text-xs leading-5 text-[#617184]">Data sufficiency: {analysis.data_sufficiency?.status || 'INSUFFICIENT_DATA'} ({analysis.data_sufficiency?.basis?.replaceAll('_', ' ').toLowerCase() || 'no further detail'}). This analysis does not apply a price change, infer profit, or forecast future prices.</p>
                    </section>
                  )}
                </CardContent>
              </Card>
            );
          })}</div>}
    </div>
  );
}
