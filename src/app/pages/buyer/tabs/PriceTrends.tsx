import { DashboardText, useDashboardCopy } from '../../../i18n/DashboardCopy';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { BarChart3, CalendarDays, MapPin, Package, RefreshCw } from 'lucide-react';
import { request } from '../../../../lib/api';
import { Badge } from '../../../components/ui/badge';
import { Button } from '../../../components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../../../components/ui/card';

type Listing = {
  listing_id: number;
  product_name: string;
  product_category?: string;
  category?: string;
  current_price_per_unit?: number;
  price_per_unit?: number;
  unit_of_measure?: string;
  unit?: string;
  quantity_available?: number;
  quantity?: number;
  location?: string;
  producer?: { name?: string; location?: string };
};

type ReferenceSummary = {
  status: string;
  source?: string | null;
  source_name?: string | null;
  geography?: string | null;
  price_type?: string | null;
  currency?: string | null;
  unit?: string | null;
  observation_count: number;
  minimum_price?: number | null;
  maximum_price?: number | null;
  average_price?: number | null;
  first_observation_date?: string | null;
  latest_available_observation?: { date?: string | null; period?: string | null; price?: number | null } | null;
};

type ListingComparison = {
  seasonality?: {analysis_month_name?: string; interpretation?: string; geographic_limitation?: string};
  listing_id: number;
  status: string;
  commodity_resolution: { canonical_name?: string | null; candidates?: Array<{ canonical_name: string }> };
  comparison: { status: string; normalized_listing_price?: number | null; unit?: string | null };
  reference: ReferenceSummary;
  reference_candidates: Array<{ commodity_label: string; reference: ReferenceSummary }>;
};

const categoryOf = (listing: Listing) => listing.product_category || listing.category || 'General';
const unitOf = (listing: Listing) => listing.unit_of_measure || listing.unit || 'unit';
const money = (value: number | null | undefined) => value == null || !Number.isFinite(value)
  ? 'Not available'
  : new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 2 }).format(value);

function ReferenceCard({ label, reference }: { label: string; reference: ReferenceSummary }) {
  const copy = useDashboardCopy();
  const latest = reference.latest_available_observation;
  const hasObservations = reference.observation_count > 0 && reference.average_price != null;
  const firstPeriod = reference.first_observation_date || 'Not reported';
  const latestPeriod = latest?.period || latest?.date || 'Not reported';

  return (
    <article className="min-w-0 rounded-lg border border-border bg-card p-3">
      <h4 className="break-words font-semibold text-foreground">{copy(label)}</h4>
      {hasObservations ? (
        <dl className="mt-3 grid min-w-0 gap-x-4 gap-y-3 sm:grid-cols-2">
          <div className="min-w-0">
            <dt className="text-xs font-medium text-muted-foreground"><DashboardText text="Latest available PSA Region II farmgate reference" /></dt>
            <dd className="mt-1 break-words font-bold text-foreground">
              {money(latest?.price)} / {reference.unit || 'PHP/kg'}
            </dd>
            <dd className="text-xs text-muted-foreground">{latestPeriod}</dd>
          </div>
          <div className="min-w-0">
            <dt className="text-xs font-medium text-muted-foreground"><DashboardText text="Historical range" /></dt>
            <dd className="mt-1 break-words font-semibold text-foreground">
              {money(reference.minimum_price)} – {money(reference.maximum_price)} / {reference.unit || 'PHP/kg'}
            </dd>
          </div>
          <div className="min-w-0">
            <dt className="text-xs font-medium text-muted-foreground"><DashboardText text="Historical average" /></dt>
            <dd className="mt-1 break-words font-semibold text-foreground">
              {money(reference.average_price)} / {reference.unit || 'PHP/kg'}
            </dd>
          </div>
          <div className="min-w-0">
            <dt className="text-xs font-medium text-muted-foreground"><DashboardText text="Reference period and observations" /></dt>
            <dd className="mt-1 break-words text-muted-foreground">
              {firstPeriod} – {latestPeriod}
            </dd>
            <dd className="text-xs text-muted-foreground">{reference.observation_count}{' '}<DashboardText text="monthly observations" /></dd>
          </div>
          <div className="min-w-0">
            <dt className="text-xs font-medium text-muted-foreground"><DashboardText text="Source" /></dt>
            <dd className="mt-1 break-words text-muted-foreground">{reference.source || reference.source_name || copy("Official reference")}</dd>
          </div>
          <div className="min-w-0">
            <dt className="text-xs font-medium text-muted-foreground"><DashboardText text="Geographic basis" /></dt>
            <dd className="mt-1 break-words text-muted-foreground">{reference.geography || copy("Not reported")}</dd>
          </div>
        </dl>
      ) : (
        <p className="mt-2 text-sm text-muted-foreground"><DashboardText text="No verified PSA observations are available for this series." /></p>
      )}
    </article>
  );
}

export default function PriceTrends() {
  const copy = useDashboardCopy();
  const [listings, setListings] = useState<Listing[]>([]);
  const [comparisons, setComparisons] = useState<Record<number, ListingComparison>>({});
  const [category, setCategory] = useState('All categories');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [referenceError, setReferenceError] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    setReferenceError('');
    setComparisons({});
    let productsLoaded = false;
    try {
      const result = await request('/api/buyer/products');
      const currentListings = Array.isArray(result?.data) ? result.data as Listing[] : [];
      productsLoaded = true;
      setListings(currentListings);

      const nextComparisons: Record<number, ListingComparison> = {};
      for (let index = 0; index < currentListings.length; index += 25) {
        const listingIds = currentListings.slice(index, index + 25).map((listing) => listing.listing_id);
        const response = await request('/api/buyer/price-trends/references', {
          method: 'POST',
          body: { listing_ids: listingIds },
        });
        for (const comparison of (response?.data ?? []) as ListingComparison[]) {
          nextComparisons[comparison.listing_id] = comparison;
        }
        setComparisons({ ...nextComparisons });
      }
    } catch (failure: any) {
      if (!productsLoaded) {
        setError(failure?.message || 'Could not load current listing prices.');
        setListings([]);
      } else {
        setReferenceError(failure?.message || 'Reference comparisons could not be loaded. Current listing prices remain available.');
      }
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { void load(); }, [load]);

  const categories = useMemo(() => ['All categories', ...Array.from(new Set(listings.map(categoryOf))).sort((a, b) => a.localeCompare(b))], [listings]);
  const visibleListings = useMemo(
    () => listings.filter((listing) => category === 'All categories' || categoryOf(listing) === category),
    [listings, category],
  );

  return (
    <div className="min-h-full bg-[#F8F7F1] p-4 sm:p-6 lg:p-8">
      <div className="mx-auto max-w-[1500px] space-y-5">
        <header className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-start gap-4">
            <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-[#E7F7EC] text-[#16A05A] sm:h-16 sm:w-16">
              <BarChart3 className="h-8 w-8 sm:h-9 sm:w-9" strokeWidth={2.5} />
            </div>
            <div>
              <h1 className="font-display text-3xl font-bold tracking-tight text-foreground sm:text-4xl"><DashboardText text="Listing Prices & PSA Reference" /></h1>
              <p className="mt-1 max-w-3xl text-sm text-muted-foreground sm:text-base"><DashboardText text="Asking prices and availability from verified producers’ active listings, shown alongside verified PSA Region II / Cagayan Valley farmgate reference data where available." /></p>
            </div>
          </div>
          <label className="flex min-h-12 w-full items-center gap-3 rounded-xl border border-[#D7DEE7] bg-card px-4 shadow-sm sm:w-auto">
            <CalendarDays className="h-5 w-5 shrink-0 text-foreground" />
            <span className="sr-only"><DashboardText text="Filter listing prices by category" /></span>
            <select aria-label={copy("Filter listing prices by category")} value={category} onChange={(event) => setCategory(event.target.value)} className="min-h-12 min-w-0 flex-1 bg-transparent font-semibold text-foreground outline-none sm:min-w-48">
              {categories.map((item) => <option key={item} value={item}>{item}</option>)}
            </select>
          </label>
        </header>

        <Card className="border-[#DDE3E8] bg-card shadow-sm">
          <CardHeader className="border-b border-border/70">
            <CardTitle className="text-xl text-foreground sm:text-2xl"><DashboardText text="Available market listings" /></CardTitle>
            <CardDescription><DashboardText text="Each listing shows its own producer, price and unit. PSA OpenSTAT references, when available, are shown separately as historical regional farmgate observations." /></CardDescription>
          </CardHeader>
          <div className="mx-4 mb-1 rounded-lg border border-[#D8E8F0] bg-[#F3FAFD] p-3 text-xs leading-5 text-muted-foreground sm:mx-6"><DashboardText text="PSA OpenSTAT references are historical farmgate observations for Region II / Cagayan Valley, not live market quotations or Aparri-specific selling prices. Invalid and nonpositive prices are excluded from comparisons; missing PSA “..” cells are not imported as zero." /></div>
          <CardContent className="p-4 sm:p-6">
            {loading ? <p className="py-10 text-center text-sm text-muted-foreground"><DashboardText text="Loading current prices…" /></p> : error ? (
              <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-red-50 p-4 text-sm text-red-800">
                <span role="alert">{error}</span>
                <Button type="button" variant="outline" onClick={() => void load()}><RefreshCw className="mr-2 h-4 w-4"/><DashboardText text="Retry" /></Button>
              </div>
            ) : visibleListings.length === 0 ? <p className="py-10 text-center text-sm text-muted-foreground"><DashboardText text="No available listings match this category." /></p> : (
              <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {visibleListings.map((listing) => {
                  const rawPrice = listing.current_price_per_unit ?? listing.price_per_unit;
                  const price = typeof rawPrice === 'number' && Number.isFinite(rawPrice) ? rawPrice : null;
                  const rawQuantity = listing.quantity_available ?? listing.quantity;
                  const quantity = typeof rawQuantity === 'number' && Number.isFinite(rawQuantity) ? rawQuantity : null;
                  return (
                    <article key={listing.listing_id} className="min-w-0 rounded-xl border border-border bg-card p-4 shadow-sm">
                      <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                          <h2 className="truncate font-bold text-foreground">{listing.product_name}</h2>
                          <p className="mt-1 text-sm text-muted-foreground">{categoryOf(listing)}</p>
                        </div>
                        <Badge className="shrink-0 bg-[#EAF8EF] text-[#16713E]"><DashboardText text="Available" /></Badge>
                      </div>
                      <p className="mt-5 break-words text-2xl font-black text-foreground">{money(price)} <span className="text-sm font-semibold text-muted-foreground">/ {unitOf(listing)}</span></p>
                      <div className="mt-4 space-y-2 border-t border-border pt-3 text-sm text-muted-foreground">
                        <p className="flex items-center gap-2"><Package className="h-4 w-4 shrink-0 text-[#0F9488]" />{quantity == null ? copy("Quantity not reported") : quantity.toLocaleString()} {unitOf(listing)}{' '}<DashboardText text="available" /></p>
                        <p className="flex items-center gap-2"><MapPin className="h-4 w-4 shrink-0 text-[#0F9488]" /><span className="truncate">{listing.producer?.name || copy("Verified Producer")} · {listing.location || listing.producer?.location || copy("Location not listed")}</span></p>
                      </div>
                      <details className="mt-4 min-w-0 rounded-lg border border-[#DDE3E8] bg-[#F8FAFB] p-3 text-sm text-muted-foreground">
                        <summary className="cursor-pointer font-bold text-foreground"><DashboardText text="PSA market references and comparison" /></summary>
                        <div className="mt-3 space-y-3">
                          {referenceError ? <p role="status" className="rounded-md bg-red-50 p-3 text-sm text-red-800">{referenceError}</p>
                            : !comparisons[listing.listing_id] ? <p className="text-sm"><DashboardText text="Reference comparison is being checked." /></p>
                              : (() => {
                                const comparison = comparisons[listing.listing_id];
                                const reference = comparison.reference;
                                const multipleMatches = comparison.status === 'MULTIPLE_MATCHES';
                                const references = multipleMatches
                                  ? comparison.reference_candidates
                                  : [{
                                    commodity_label: comparison.commodity_resolution.canonical_name || listing.product_name,
                                    reference,
                                  }];
                                const explanation = comparison.status === 'UNIT_INCOMPATIBLE'
                                  ? `Not directly comparable: the listing is priced per ${unitOf(listing)}, while PSA is reported per ${reference.unit || 'kg'}. No verified conversion is available.`
                                  : comparison.status === 'NO_REFERENCE_AVAILABLE'
                                    ? 'No verified PSA reference observations are available for a supported commodity match.'
                                    : comparison.status === 'UNRESOLVED'
                                      ? 'The product name could not be matched confidently to a supported PSA commodity.'
                                      : multipleMatches
                                        ? 'This generic product name matches multiple PSA series. Each variety is shown separately; no combined average or single variety is selected.'
                                        : null;

                                return (
                                  <>
                                    {multipleMatches && <p className="rounded-md bg-amber-50 p-3 text-sm text-amber-950">{explanation}</p>}
                                    {comparison.seasonality&&<p className="rounded-md bg-teal-50 p-3 text-sm"><strong><DashboardText text="Seasonal reference ·" />{' '}{comparison.seasonality.analysis_month_name}</strong><br/>{comparison.seasonality.interpretation}<br/><span className="text-xs">{comparison.seasonality.geographic_limitation}</span></p>}
                                    {comparison.status === 'COMPARABLE' && comparison.comparison.normalized_listing_price != null && (
                                      <div className="rounded-md bg-[#EAF8EF] p-3 text-sm text-foreground">
                                        <span className="font-semibold"><DashboardText text="Comparable listing equivalent:" /></span> {money(comparison.comparison.normalized_listing_price)} / {comparison.comparison.unit || 'PHP/kg'}
                                      </div>
                                    )}
                                    {comparison.status === 'UNIT_INCOMPATIBLE' && (
                                      <div className="rounded-md bg-amber-50 p-3 text-sm text-amber-950">
                                        <p className="font-semibold"><DashboardText text="Not directly comparable" /></p>
                                        <p className="mt-1"><DashboardText text="Producer listing:" />{' '}{money(price)} / {unitOf(listing)}{' '}<DashboardText text=". PSA reference:" />{' '}{reference.observation_count ? `${money(reference.average_price)} / ${reference.unit || 'PHP/kg'}` : copy("No verified observations")}.</p>
                                        <p className="mt-1">{explanation}</p>
                                      </div>
                                    )}
                                    {comparison.status === 'UNRESOLVED' && <p className="rounded-md bg-slate-100 p-3 text-sm">{explanation}</p>}
                                    {references.length > 0 ? (
                                      <div className="grid min-w-0 gap-3">
                                        {references.map((candidate) => (
                                          <ReferenceCard
                                            key={candidate.commodity_label}
                                            label={candidate.commodity_label}
                                            reference={candidate.reference}
                                          />
                                        ))}
                                      </div>
                                    ) : comparison.status === 'NO_REFERENCE_AVAILABLE' ? (
                                      <ReferenceCard label={comparison.commodity_resolution.canonical_name || listing.product_name} reference={reference} />
                                    ) : null}
                                    <p className="text-xs leading-5 text-muted-foreground"><DashboardText text="PSA OpenSTAT historical Region II / Cagayan Valley farmgate references are not live quotations or Aparri-specific selling prices. No procurement price is recommended." /></p>
                                  </>
                                );
                              })()}
                        </div>
                      </details>
                    </article>
                  );
                })}
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
