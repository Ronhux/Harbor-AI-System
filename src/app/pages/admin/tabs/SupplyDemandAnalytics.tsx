import { DashboardText, useDashboardCopy } from '../../../i18n/DashboardCopy';
import { useMemo, useState } from 'react';
import { BarChart3 } from 'lucide-react';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend } from 'recharts';
import PageHeader from '../../../components/dashboard/PageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '../../../components/ui/card';
import { LoadingState } from '../../../components/ui/loading-state';
import { AnalyticsError, AnalyticsPeriod, useAdminAnalytics, type SupplyDemand } from '../adminAnalytics';

type ProductUnitGroup = {
  product_key: string;
  unit_key: string;
  product_name: string;
  unit: string;
};

export default function SupplyDemandAnalytics() {
  const copy = useDashboardCopy();
  const { data, loading, error, setRange, retry } = useAdminAnalytics<SupplyDemand>('supply-demand');
  const [search, setSearch] = useState('');
  const [scope, setScope] = useState('all');

  const productOptions = useMemo<ProductUnitGroup[]>(() => {
    if (!data) return [];
    const options = new Map<string, ProductUnitGroup>();
    [...data.monthly_activity, ...data.current_snapshot].forEach((row) => {
      const key = `${row.product_key}|${row.unit_key}`;
      if (!options.has(key)) {
        options.set(key, { product_key: row.product_key, unit_key: row.unit_key, product_name: row.product_name, unit: row.unit });
      }
    });
    return [...options.values()].sort((left, right) => `${left.product_name}|${left.unit}`.localeCompare(`${right.product_name}|${right.unit}`));
  }, [data]);

  const term = search.trim().toLowerCase();
  const matches = (row: { product_name: string; unit: string }) => (row.product_name + ' ' + row.unit).toLowerCase().includes(term);
  const inScope = (row: ProductUnitGroup) => scope === 'all' || `${row.product_key}|${row.unit_key}` === scope;

  const groups = data?.current_snapshot.filter(row => matches(row) && inScope(row)) ?? [];
  const monthly = data?.months.map(month => {
    const rows = data.monthly_activity.filter(row => row.month === month.month && matches(row) && inScope(row));
    return {
      ...month,
      listings: rows.reduce((sum, row) => sum + row.listings_created, 0),
      demands: rows.reduce((sum, row) => sum + row.demand_requests_created, 0),
    };
  }) ?? [];
  const hasActivity = (data?.monthly_activity ?? []).some(row => matches(row) && inScope(row));

  return (
    <div className="min-w-0 space-y-5">
      <PageHeader title={copy("Supply-Demand Analytics")} icon={BarChart3} subtitle={copy("Stored supply and demand records, grouped by product and unit.")} />
      <AnalyticsPeriod onApply={setRange} loading={loading} meta={data?.meta} />

      {loading ? <LoadingState message={copy("Loading supply and demand analytics...")} /> : error ? <AnalyticsError message={error} retry={retry} /> : data && <>
        <div className="grid gap-3 sm:grid-cols-2">
          <label className="min-w-0 text-sm font-semibold"><DashboardText text="Filter product or unit" /><input type="search" value={search} onChange={event => setSearch(event.target.value)} placeholder={copy("Search product or unit")} className="mt-1 block min-h-11 w-full min-w-0 rounded-lg border bg-card px-3" />
          </label>
          <label className="min-w-0 text-sm font-semibold"><DashboardText text="Product and compatible unit" /><select value={scope} onChange={event => setScope(event.target.value)} className="mt-1 block min-h-11 w-full min-w-0 rounded-lg border bg-card px-3">
              <option value="all"><DashboardText text="All products and units (aggregated)" /></option>
              {productOptions.map(option => <option key={`${option.product_key}|${option.unit_key}`} value={`${option.product_key}|${option.unit_key}`}>{option.product_name} · {option.unit}</option>)}
            </select>
          </label>
        </div>

        <Card className="min-w-0">
          <CardHeader>
            <CardTitle className="text-lg"><DashboardText text="Monthly listing and demand activity" /></CardTitle>
            <p className="text-sm text-muted-foreground"><DashboardText text="Number of records created in the selected period. These are not historical quantities or completed purchases." /></p>
          </CardHeader>
          <CardContent className="min-w-0 px-2 sm:px-5">
            {!hasActivity ? <p className="p-4 text-sm text-muted-foreground"><DashboardText text="No matching activity recorded in this period." /></p> : <>
              <div className="h-64 min-w-0 w-full" role="img" aria-label={copy("Monthly listing and demand record counts; exact counts below.")}>
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={monthly} margin={{ top: 10, right: 12, left: -20, bottom: 8 }}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis dataKey="label" minTickGap={24} tick={{ fontSize: 11 }} />
                    <YAxis allowDecimals={false} tick={{ fontSize: 11 }} />
                    <Tooltip />
                    <Legend wrapperStyle={{ fontSize: 12 }} />
                    <Bar dataKey="listings" name="Listings created" fill="#0F9488" />
                    <Bar dataKey="demands" name="Demand requests" fill="#123C5C" />
                  </BarChart>
                </ResponsiveContainer>
              </div>
              <details className="px-2">
                <summary className="min-h-11 cursor-pointer py-3 text-sm font-semibold"><DashboardText text="View monthly counts" /></summary>
                <dl className="space-y-2">{monthly.map(row => <div key={row.month} className="flex flex-wrap justify-between gap-2 border-t py-2 text-sm"><dt>{row.label}</dt><dd>{row.listings}{' '}<DashboardText text="listings ·" />{' '}{row.demands}{' '}<DashboardText text="demands" /></dd></div>)}</dl>
              </details>
            </>}
          </CardContent>
        </Card>

        <section className="space-y-3">
          <div>
            <h2 className="text-lg font-bold"><DashboardText text="Current quantity snapshot" /></h2>
            <p className="mt-1 text-sm text-muted-foreground"><DashboardText text="As of" />{' '}{data.current_snapshot_as_of}{' '}<DashboardText text=". Independent of the selected period. Different units remain separate; quantities do not imply fulfillment or purchase commitments." /></p>
          </div>
          {!groups.length ? <Card><CardContent className="p-5 text-sm text-muted-foreground"><DashboardText text="No matching supply or demand groups available." /></CardContent></Card> : (
            <div className="grid min-w-0 gap-3 md:grid-cols-2 xl:grid-cols-3">
              {groups.map(row => (
                <Card key={`${row.product_key}|${row.unit_key}`} className="min-w-0">
                  <CardContent className="p-4">
                    <h3 className="break-words font-bold">{row.product_name}</h3>
                    <p className="text-sm text-muted-foreground"><DashboardText text="Unit:" />{' '}{row.unit}</p>
                    <dl className="mt-4 space-y-3 text-sm">
                      <div className="flex flex-wrap justify-between gap-2"><dt><DashboardText text="Available supply" /></dt><dd className="break-all font-bold text-[#0F766E]">{row.available_supply_quantity.toLocaleString()} {row.unit}</dd></div>
                      <div className="flex flex-wrap justify-between gap-2"><dt><DashboardText text="Requested demand" /></dt><dd className="break-all font-bold">{row.requested_demand_quantity.toLocaleString()} {row.unit}</dd></div>
                    </dl>
                    <p className="mt-3 border-t pt-3 text-xs text-muted-foreground">{row.available_listing_count}{' '}<DashboardText text="available listings ·" />{' '}{row.active_demand_request_count}{' '}<DashboardText text="active demand requests" /></p>
                  </CardContent>
                </Card>
              ))}
            </div>
          )}
        </section>
      </>}
    </div>
  );
}
