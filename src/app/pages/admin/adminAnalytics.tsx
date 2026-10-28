import { useEffect, useState } from 'react';
import { request } from '../../../lib/api';
import { Button } from '../../components/ui/button';

export type RangeMeta = { start_date: string; end_date: string; timezone: string };
export type Monitoring = {
  meta: RangeMeta;
  current_snapshot_as_of: string;
  metrics: Record<string, { created_in_period?: number; current_status_counts?: { status: string; count: number }[] }>;
  monthly: { month: string; label: string; producer_accounts: number; orders_created: number; demand_requests_created: number }[];
  recent_activity: { available: boolean; events: { id: number; action: string; subject: string; status: string; created_at: string }[] };
};
export type SupplyDemand = {
  meta: RangeMeta;
  current_snapshot_as_of: string;
  months: { month: string; label: string }[];
  monthly_activity: { month: string; product_key: string; unit_key: string; product_name: string; unit: string; listings_created: number; demand_requests_created: number }[];
  current_snapshot: { product_key: string; unit_key: string; product_name: string; unit: string; available_supply_quantity: number; requested_demand_quantity: number; available_listing_count: number; active_demand_request_count: number }[];
};

// Contracts reviewed against AdminAnalyticsController. No sample fallbacks.
export function useAdminAnalytics<T extends Monitoring | SupplyDemand>(kind: 'monitoring' | 'supply-demand') {
  const [range, setRange] = useState('');
  const [refresh, setRefresh] = useState(0);
  const [data, setData] = useState<T | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    setError('');
    setData(null);
    request(`/api/admin/analytics/${kind}${range ? `?${range}` : ''}`, { signal: controller.signal })
      .then(result => {
        if (!result?.meta || (kind === 'monitoring'
          ? !result.metrics || !Array.isArray(result.monthly) || !Array.isArray(result.recent_activity?.events)
          : !Array.isArray(result.months) || !Array.isArray(result.monthly_activity) || !Array.isArray(result.current_snapshot))) {
          throw new Error('Unexpected analytics response');
        }
        if (!controller.signal.aborted) setData(result);
      })
      .catch(() => { if (!controller.signal.aborted) setError('Analytics are unavailable. Try again when the reporting service is available.'); })
      .finally(() => { if (!controller.signal.aborted) setLoading(false); });
    return () => controller.abort();
  }, [kind, range, refresh]);
  return { data, loading, error, setRange, retry: () => setRefresh(value => value + 1) };
}

export function AnalyticsPeriod({ onApply, loading, meta }: { onApply: (range: string) => void; loading: boolean; meta?: RangeMeta }) {
  const [start, setStart] = useState('');
  const [end, setEnd] = useState('');
  return <form className="rounded-xl border border-border bg-card p-4" onSubmit={event => { event.preventDefault(); onApply(new URLSearchParams({ start_date: start, end_date: end }).toString()); }}>
    <div className="grid min-w-0 gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_auto_auto] lg:items-end">
      <label className="min-w-0 text-sm font-semibold">Start date<input required type="date" value={start} max={end || undefined} onChange={event => setStart(event.target.value)} className="mt-1 block min-h-11 w-full min-w-0 rounded-lg border bg-card px-3" /></label>
      <label className="min-w-0 text-sm font-semibold">End date<input required type="date" value={end} min={start || undefined} onChange={event => setEnd(event.target.value)} className="mt-1 block min-h-11 w-full min-w-0 rounded-lg border bg-card px-3" /></label>
      <Button type="submit" className="min-h-11" disabled={loading || !start || !end || start > end}>Apply period</Button>
      <Button type="button" variant="outline" className="min-h-11" disabled={loading} onClick={() => { setStart(''); setEnd(''); onApply(''); }}>Default period</Button>
    </div>
    <p className="mt-3 text-sm text-muted-foreground">{meta ? `Showing ${meta.start_date} to ${meta.end_date} (${meta.timezone}).` : 'Default: the reporting service’s current six-month period.'} Up to 24 calendar months; future dates are not supported.</p>
  </form>;
}

export function AnalyticsError({ message, retry }: { message: string; retry: () => void }) {
  return <div role="alert" className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><p>{message}</p><Button variant="outline" className="min-h-11" onClick={retry}>Retry</Button></div>;
}
