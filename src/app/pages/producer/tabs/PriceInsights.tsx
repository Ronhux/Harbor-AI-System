import { DashboardText, useDashboardCopy } from '../../../i18n/DashboardCopy';
import { useCallback, useEffect, useState } from 'react';
import { AlertCircle, RefreshCw, TrendingUp } from 'lucide-react';
import { request } from '../../../../lib/api';
import { Badge } from '../../../components/ui/badge';
import { Button } from '../../../components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../../../components/ui/card';

type Analysis = {
  analysis_date: string;
  commodity_resolution?: {
    status?: string;
    canonical_name?: string | null;
    candidates?: Array<{ canonical_name: string }>;
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
    currency?: string;
    unit?: string;
    observation_count?: number;
    minimum_price?: number | null;
    maximum_price?: number | null;
    average_price?: number | null;
    latest_available_observation?: { date?: string | null; price?: number | null } | null;
  };
  producer_price_comparison?: {
    status?: string;
    position_relative_to_range?: string | null;
    difference_percent_from_average?: number | null;
    difference_from_latest_reference?: number | null;
    difference_percent_from_latest_reference?: number | null;
    /**
     * Provenance for the compared figure. Without this a percentage such as
     * "40% above the latest reference" reads as a current local market price, when the
     * underlying evidence is a historical regional series.
     */
    reference_basis?: {
      available?: boolean;
      geography?: string | null;
      is_local_to_producer?: boolean;
      is_current?: boolean;
      is_single_month?: boolean;
      observation_count?: number;
      first_observation_date?: string | null;
      latest_observation_date?: string | null;
      months_behind_analysis?: number | null;
      unit?: string | null;
      price_type?: string | null;
      provenance_notice?: string | null;
      limitation?: string;
    } | null;
  };
  harborai_listing_reference?: {
    active_listing_count?: number;
    average_active_listing_price?: number | null;
    price_currency?: string;
    price_unit?: string;
  };
  supply_context?: { active_listing_count?: number; quantity_total?: number | null; quantity_unit?: string | null; quantity_status?: string };
  demand_context?: {
    active_demand_request_count?: number;
    requested_quantity_total?: number | null;
    quantity_unit?: string | null;
    quantity_status?: string;
    status?: string;
  };
  psa_reference_candidates?: Array<{
    commodity_label: string;
    psa_reference: {
      observation_count?: number;
      average_price?: number | null;
      unit?: string;
      currency?: string;
      latest_available_observation?: { date?: string | null; price?: number | null } | null;
    };
  }>;
  seasonality?: {
    status?: string;
    analysis_month_name?: string | null;
    interpretation?: string;
    geographic_limitation?: string;
    references?: Array<{
      commodity_label?: string | null;
      status?: string;
      periods?: string[];
      geographic_scope?: string;
      source_name?: string | null;
      organization?: string | null;
      source_reference?: string | null;
      reference_date?: string | null;
      rows?: Array<{
        month?: number | null;
        period_label?: string | null;
        season_status?: string;
        start_month?: number | null;
        end_month?: number | null;
        peak_period?: string | null;
        reference_date?: string | null;
        notes?: string | null;
      }>;
      /** Preserved verbatim from the owner-supplied reference. */
      source_label?: string | null;
      interpretation?: string | null;
      /** True when the status is a derived indicator, not a verified observation. */
      derived_indicator?: boolean;
      signal?: {
        named_planting_window?: string | null;
        possible_harvest_window?: string | null;
        seasonal_activity?: string | null;
        harvest_signal?: string | null;
      } | null;
    }>;
    validated_reference_count?: number;
  };
};

type Insight = {
  listing: {
    listing_id: number;
    product_name: string;
    category?: string | null;
    price_per_unit: number | null;
    unit?: string | null;
    quantity_available: number | null;
    location?: string | null;
  };
  analysis: Analysis;
};

type InsightsResponse = { generated_at: string; source: string; data: Insight[] };

const peso = (amount: number | null | undefined, currency = 'PHP') => {
  if (amount === null || amount === undefined || !Number.isFinite(Number(amount))) return 'Not available';
  return new Intl.NumberFormat('en-PH', { style: 'currency', currency, maximumFractionDigits: 2 }).format(amount);
};

const questionFor = (insight: Insight) => {
  const comparison = insight.analysis.producer_price_comparison;
  const reference = insight.analysis.psa_reference;
  const demand = insight.analysis.demand_context;
  const supply = insight.analysis.supply_context;

  if (
    demand?.quantity_status === 'AVAILABLE'
    && supply?.quantity_status === 'AVAILABLE'
    && demand.requested_quantity_total != null
    && supply.quantity_total != null
    && demand.requested_quantity_total > supply.quantity_total
    && demand.quantity_unit === supply.quantity_unit
  ) {
    return `Recorded active buyer requests total ${demand.requested_quantity_total} ${demand.quantity_unit || ''}, compared with ${supply.quantity_total} ${supply.quantity_unit || ''} in active listing supply. Are these recorded requests relevant when you review this listing?`;
  }

  if (comparison?.status === 'COMPARABLE' && comparison.difference_from_latest_reference != null) {
    const difference = comparison.difference_from_latest_reference;
    const position = difference > 0 ? 'above' : difference < 0 ? 'below' : 'equal to';
    const basis = comparison.reference_basis;
    // Name the observation's own month. Calling a series "latest" without its date invites the
    // reader to assume it describes the month being analysed, which it usually does not.
    const latest = basis?.latest_observation_date
      ? new Date(basis.latest_observation_date).toLocaleDateString('en-GB', { month: 'long', year: 'numeric' })
      : null;
    const where = basis?.geography || reference?.geography || 'the recorded regional';
    const staleness = basis?.months_behind_analysis
      ? ` That observation is ${basis.months_behind_analysis} month(s) older than the month being analysed.`
      : '';
    return `Your asking price is ${peso(Math.abs(difference))} per kg ${position} the most recent ${where} farmgate observation${latest ? ` (${latest})` : ''}${comparison.difference_percent_from_latest_reference == null ? '' : `, by ${Math.abs(comparison.difference_percent_from_latest_reference)}%`}. This is a regional historical figure, not an Aparri price and not a current market quote.${staleness} Would reviewing this comparison help you decide whether to keep or revise your price?`;
  }

  if (comparison?.status === 'MULTIPLE_REFERENCES') {
    const varieties = insight.analysis.psa_reference_candidates?.map(({ commodity_label }) => commodity_label).join(', ');
    return `Several PSA commodity series may apply${varieties ? ` (${varieties})` : ''}. Which variety, if any, accurately describes this listing before you consider a comparison?`;
  }

  if (comparison?.status === 'UNIT_INCOMPATIBLE') {
    return 'The listing unit cannot be reliably converted to the PSA reference unit, so no price comparison is shown. Could you confirm the listing unit before using a market reference?';
  }

  return 'No single compatible, verified price comparison is available for this listing yet. Would waiting for better-matched reference data help you evaluate the asking price?';
};

export default function PriceInsights() {
  const copy = useDashboardCopy();
  const [result, setResult] = useState<InsightsResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const refresh = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const response = await request('/api/producer/market-insights') as InsightsResponse;
      setResult(response);
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
          <h1 className="flex items-center gap-3 font-display text-2xl text-foreground sm:text-3xl">
            <TrendingUp className="h-8 w-8 text-[#0F9488]" /><DashboardText text="Market & Pricing Insights" /></h1>
          <p className="mt-1 text-sm text-muted-foreground"><DashboardText text="Evidence from your active listings, HarborAI supply and demand records, and available verified references. Pricing decisions remain yours." /></p>
        </div>
        <Button type="button" variant="outline" onClick={() => void refresh()} disabled={loading} className="min-h-11 shrink-0">
          <RefreshCw className={`mr-2 h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
          {loading ? copy("Refreshing…") : copy("Refresh Analysis")}
        </Button>
      </header>

      {result && <p className="text-xs text-muted-foreground"><DashboardText text="Recomputed" />{' '}{new Date(result.generated_at).toLocaleString()}. {result.source}</p>}
      {error && <div role="alert" className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><AlertCircle className="mr-2 inline h-4 w-4" />{error}</div>}

      {loading && !result ? <p className="py-10 text-center text-muted-foreground"><DashboardText text="Loading supported market evidence…" /></p>
        : !error && result?.data.length === 0 ? <Card><CardContent className="p-8 text-center text-muted-foreground"><DashboardText text="No active in-stock listings are available for analysis. Add an active listing to see evidence-based pricing context." /></CardContent></Card>
          : <div className="space-y-4">{result?.data.map((item) => {
            const { listing, analysis } = item;
            const reference = analysis.psa_reference;
            const comparison = analysis.producer_price_comparison;
            const demand = analysis.demand_context;
            const supply = analysis.supply_context;
            const latestReference = reference?.latest_available_observation;
            const pricePosition = comparison?.position_relative_to_range?.replaceAll('_', ' ').toLowerCase();
            const unmappedSiblings = analysis.commodity_resolution?.source_coverage?.unmapped_source_siblings ?? [];

            return (
              <Card key={listing.listing_id} className="border-2 border-border">
                <CardHeader className="border-b border-border/70">
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <CardTitle className="text-xl text-foreground">{listing.product_name}</CardTitle>
                      <CardDescription>{listing.category || copy("Category not listed")}{' '}<DashboardText text="· Listing #" />{' '}{listing.listing_id} · {listing.location || copy("Location not listed")}</CardDescription>
                    </div>
                    <Badge variant="secondary"><DashboardText text="Current asking price:" />{' '}{peso(listing.price_per_unit)} / {listing.unit || copy("unit")}</Badge>
                  </div>
                </CardHeader>
                <CardContent className="space-y-4 p-4 sm:p-6">
                  <h3 className="font-bold text-foreground"><DashboardText text="Market Information" /></h3>
                  <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="rounded-lg bg-background p-3"><p className="text-xs font-bold uppercase text-muted-foreground"><DashboardText text="Available quantity" /></p><p className="mt-1 font-bold text-foreground">{listing.quantity_available ?? copy("Not reported")} {listing.unit || ''}</p></div>
                    <div className="rounded-lg bg-background p-3"><p className="text-xs font-bold uppercase text-muted-foreground"><DashboardText text="Latest available PSA Region II reference" /></p><p className="mt-1 font-bold text-foreground">{latestReference?.price == null ? copy("Not available") : `${peso(latestReference.price, reference?.currency)} / ${reference?.unit || 'unit'}`}</p><p className="mt-1 text-xs text-muted-foreground">{latestReference?.date || copy("No observation date")}</p></div>
                    <div className="rounded-lg bg-background p-3"><p className="text-xs font-bold uppercase text-muted-foreground"><DashboardText text="Reference range position" /></p><p className="mt-1 font-bold capitalize text-foreground">{pricePosition || comparison?.status?.replaceAll('_', ' ').toLowerCase() || copy("Not available")}</p></div>
                    <div className="rounded-lg bg-background p-3"><p className="text-xs font-bold uppercase text-muted-foreground"><DashboardText text="Active demand requests" /></p><p className="mt-1 font-bold text-foreground">{demand?.active_demand_request_count ?? 0}{demand?.requested_quantity_total != null ? ` · ${demand.requested_quantity_total} ${demand.quantity_unit || ''}` : ''}</p></div>
                  </div>

                  <section className="rounded-lg border border-[#D8E8F0] bg-[#F3FAFD] p-4">
                    <h3 className="font-bold text-foreground"><DashboardText text="Pricing Guidance" /></h3>
                    <p className="mt-1 text-sm leading-6 text-muted-foreground">{questionFor(item)}</p>
                    <p className="mt-2 text-xs text-muted-foreground"><DashboardText text="This is decision support only. HarborAI does not change listing prices or claim a forecast." /></p>
                  </section>

                  {unmappedSiblings.length > 0 && (
                    <section className="rounded-lg border border-amber-200 bg-amber-50 p-4">
                      <h3 className="font-bold text-amber-950"><DashboardText text="Partial source coverage" /></h3>
                      <p className="mt-1 text-sm leading-6 text-amber-950"><DashboardText text="PSA publishes additional series for this product that HarborAI has no registered commodity reference for. Only the matched series shown above are represented. Statistics are not combined with, or replaced by, an unmapped series." /></p>
                      <ul className="ml-5 mt-1 list-disc text-xs leading-5 text-amber-950">
                        {unmappedSiblings.map((sibling) => <li key={sibling}>{sibling}</li>)}
                      </ul>
                    </section>
                  )}

                  <details className="rounded-lg border border-border p-4">
                    <summary className="cursor-pointer font-bold text-foreground"><DashboardText text="Reference details and limitations" /></summary>
                    <div className="mt-3 space-y-2 text-sm text-muted-foreground">
                      {reference?.status === 'AVAILABLE' ? <>
                        <p><DashboardText text="Source:" />{' '}{reference.source || copy("Verified reference data")}{' '}<DashboardText text="· Geography:" />{' '}{reference.geography || copy("Not specified")}{' '}<DashboardText text="· Type:" />{' '}{reference.price_type || copy("Not specified")}</p>
                        <p><DashboardText text="Historical source reference," />{' '}{reference.observation_count ?? 0}{' '}<DashboardText text="observation(s); historical range" />{' '}{peso(reference.minimum_price, reference.currency)}–{peso(reference.maximum_price, reference.currency)}{' '}<DashboardText text="per" />{' '}{reference.unit || copy("unit")}{' '}<DashboardText text="; historical average" />{' '}{peso(reference.average_price, reference.currency)}{' '}<DashboardText text="per" />{' '}{reference.unit || copy("unit")}.</p>
                        <p><DashboardText text="Latest available observation:" />{' '}{reference.latest_available_observation?.date || copy("Date not supplied")}{reference.latest_available_observation?.price != null ? copy(" at {0}", [peso(reference.latest_available_observation.price, reference.currency)]) : ''}{' '}<DashboardText text=". This is the latest available reference observation, not a current or Aparri-specific price." /></p>
                      </> : <p><DashboardText text="No single compatible verified reference is available for comparison." /></p>}
                      {analysis.psa_reference_candidates && analysis.psa_reference_candidates.length > 1 && (
                        <div>
                          <p><DashboardText text="Possible PSA series (not combined):" /></p>
                          <ul className="ml-5 list-disc">
                            {analysis.psa_reference_candidates.map((candidate) => (
                              <li key={candidate.commodity_label}>{candidate.commodity_label}: {candidate.psa_reference.observation_count ?? 0}{' '}<DashboardText text="observations; latest" />{' '}{candidate.psa_reference.latest_available_observation?.date || copy("not available")}{' '}<DashboardText text="at" />{' '}{peso(candidate.psa_reference.latest_available_observation?.price, candidate.psa_reference.currency)} / {candidate.psa_reference.unit || copy("unit")}.</li>
                            ))}
                          </ul>
                        </div>
                      )}
                      {comparison?.status === 'COMPARABLE' && comparison.difference_from_latest_reference != null && (
                        <p>{questionFor(item)}</p>
                      )}
                      <p><DashboardText text="HarborAI supply:" />{' '}{supply?.active_listing_count ?? 0}{' '}<DashboardText text="active in-stock listing(s)" />{' '}{analysis.harborai_listing_reference?.average_active_listing_price != null ? copy("; current listing average {0} / {1}", [peso(analysis.harborai_listing_reference.average_active_listing_price, analysis.harborai_listing_reference.price_currency), analysis.harborai_listing_reference.price_unit || 'unit']) : ''}.</p>
                      {demand?.active_demand_request_count ? <p><DashboardText text="Recorded active demand:" />{' '}{demand.active_demand_request_count}{' '}<DashboardText text="request(s)" />{' '}{demand.requested_quantity_total != null ? copy(" totaling {0} {1}", [demand.requested_quantity_total, demand.quantity_unit || '']) : ''}.</p> : <p><DashboardText text="No matching active demand requests are recorded." /></p>}
                      {/* Provenance for the price comparison. Rendered before any percentage so a
                          regional historical figure is never read as a current local price. */}
                      {comparison?.reference_basis?.available && (
                        <div className="rounded-lg border border-border bg-[#FFFBF2] p-3">
                          <p className="text-xs font-bold uppercase text-muted-foreground"><DashboardText text="What this price comparison is based on" /></p>
                          <p className="mt-1 text-sm text-muted-foreground">{comparison.reference_basis.limitation}</p>
                          <p className="mt-1 text-xs text-muted-foreground"><DashboardText text="Geography:" />{' '}{comparison.reference_basis.geography || copy("not specified")}
                            {' · '}{' '}<DashboardText text="Observations:" />{' '}{comparison.reference_basis.observation_count ?? 0}
                            {' · '}{' '}<DashboardText text="Span:" />{' '}{comparison.reference_basis.first_observation_date || 'n/a'}{' '}<DashboardText text="to" />{' '}{comparison.reference_basis.latest_observation_date || 'n/a'}
                            {' · '}{' '}<DashboardText text="Local to you:" />{' '}{comparison.reference_basis.is_local_to_producer ? copy("yes") : copy("no")}
                            {' · '}{' '}<DashboardText text="Current:" />{' '}{comparison.reference_basis.is_current ? copy("yes") : copy("no")}
                          </p>
                        </div>
                      )}
                      {analysis.seasonality && (
                        <div className="rounded-lg border border-border bg-card p-3">
                          <p className="text-xs font-bold uppercase text-muted-foreground"><DashboardText text="Seasonality context for" />{' '}{analysis.seasonality.analysis_month_name || copy("this month")}
                          </p>
                          <p className="mt-1 text-sm text-foreground"><DashboardText text="Status:" />{' '}{analysis.seasonality.status || copy("not stated")}
                          </p>
                          {analysis.seasonality.references?.length ? analysis.seasonality.references.map((season) => (
                            <div key={season.source_reference || season.commodity_label || 'ref'} className="mt-2 text-sm text-muted-foreground">
                              <p><strong className="text-foreground">{season.commodity_label}</strong> — {season.status}{season.geographic_scope ? ` · ${season.geographic_scope}` : ''}</p>
                              {season.interpretation && <p className="mt-1">{season.interpretation}</p>}
                              {season.source_label && (
                                <p className="mt-1 text-xs text-muted-foreground"><DashboardText text="Evidence label:" />{' '}{season.source_label}
                                  {season.derived_indicator ? copy(" · derived indicator, not a MAO-verified harvest record") : ''}
                                  {season.source_name ? copy(" · source: {0}", [season.source_name]) : ''}
                                </p>
                              )}
                              {season.signal?.seasonal_activity && (
                                <p className="mt-1 text-xs text-muted-foreground"><DashboardText text="Derived signal:" />{' '}{season.signal.seasonal_activity}
                                  {season.signal.harvest_signal ? copy(" · harvest: {0}", [season.signal.harvest_signal]) : ''}
                                </p>
                              )}
                              {season.rows?.length ? (
                                <ul className="ml-4 mt-1 list-disc text-xs text-muted-foreground">
                                  {season.rows.map((row) => (
                                    <li key={`${row.month}-${row.season_status}`}>
                                      {row.period_label}: {row.season_status}{row.notes ? ` — ${row.notes}` : ''}
                                    </li>
                                  ))}
                                </ul>
                              ) : null}
                            </div>
                          )) : (
                            <p className="mt-1 text-sm text-muted-foreground">
                              {analysis.seasonality.interpretation
                                || copy("No verified seasonality reference is available for this product; no seasonal forecast is shown.")}
                            </p>
                          )}
                          {analysis.seasonality.geographic_limitation && (
                            <p className="mt-2 text-xs italic text-muted-foreground">{analysis.seasonality.geographic_limitation}</p>
                          )}
                        </div>
                      )}
                    </div>
                  </details>
                </CardContent>
              </Card>
            );
          })}</div>}
    </div>
  );
}
