import { useEffect, useState } from 'react';
import { ChevronDown, ChevronUp, Package } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../../../components/ui/card';
import { Badge } from '../../../components/ui/badge';
import { LoadingState } from '../../../components/ui/loading-state';
import { Button } from '../../../components/ui/button';
import { request } from '../../../../lib/api';

type SupplyGroup = { group_key: string; product: string; category?: string; producer_count: number; total_available: number; unit?: string; records: { listing_id: number; producer: string; quantity: number; unit?: string; location?: string }[] };

export default function ProductSupplyGroups() {
  const [groups, setGroups] = useState<SupplyGroup[]>([]);
  const [expanded, setExpanded] = useState<string | null>(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [refresh, setRefresh] = useState(0);
  useEffect(() => {
    const controller = new AbortController();
    setLoading(true); setError('');
    request('/api/admin/supply', { signal: controller.signal }).then(result => {
      if (!Array.isArray(result)) throw new Error('Supply unavailable');
      if (!controller.signal.aborted) setGroups(result);
    }).catch(() => { if (!controller.signal.aborted) setError('Could not load producer supply.'); })
      .finally(() => { if (!controller.signal.aborted) setLoading(false); });
    return () => controller.abort();
  }, [refresh]);
  const filtered = groups.filter(group => [group.product, group.category, group.unit, ...group.records.map(record => record.producer)].join(' ').toLowerCase().includes(search.trim().toLowerCase()));

  return <section className="space-y-4">
    <div><h2 className="font-display text-xl text-foreground sm:text-2xl">Available Producer Supply</h2><p className="mt-1 text-sm text-muted-foreground">Active listings grouped by product and unit, with producer details.</p></div>
    <label className="block text-sm font-semibold">Search supply<input type="search" value={search} onChange={event => setSearch(event.target.value)} placeholder="Product, category, unit or producer" className="mt-1 min-h-11 w-full rounded-lg border bg-card px-3" /></label>
    {loading ? <LoadingState message="Loading producer supply..." /> : error ? <Card><CardContent className="p-6 text-center text-sm text-red-700" role="alert">{error}<Button variant="outline" className="ml-3 min-h-11" onClick={() => setRefresh(value => value + 1)}>Retry</Button></CardContent></Card>
      : filtered.length === 0 ? <Card><CardContent className="p-6 text-center text-sm text-muted-foreground">{search ? 'No supply matches this search.' : 'No active producer supply listings found.'}</CardContent></Card>
      : <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{filtered.map((group) => {
        const isExpanded = expanded === group.group_key;
        return <Card key={group.group_key} className="min-w-0 overflow-hidden border border-border">
          <CardHeader className="flex flex-col items-start gap-3 p-4 pb-3"><div className="min-w-0"><CardTitle className="flex items-start gap-2 break-words text-base"><Package className="h-5 w-5 shrink-0 text-[#0F9488]" /><span className="min-w-0 break-words">{group.product}</span></CardTitle><CardDescription className="mt-1">{group.category || 'Product'} · {group.producer_count} producer{group.producer_count === 1 ? '' : 's'}</CardDescription></div><Badge className="max-w-full whitespace-normal break-words bg-[#0F9488]/10 text-[#0B4842]">{Number(group.total_available).toLocaleString()} {group.unit || 'units'}</Badge></CardHeader>
          <CardContent className="px-4 pb-4"><button type="button" aria-expanded={isExpanded} onClick={() => setExpanded(isExpanded ? null : group.group_key)} className="flex min-h-11 w-full items-center justify-between rounded-lg border border-border px-4 text-sm font-bold text-foreground hover:bg-background">{isExpanded ? 'Hide' : 'View'} producer records{isExpanded ? <ChevronUp size={18} /> : <ChevronDown size={18} />}</button>
            {isExpanded && <div className="mt-3 space-y-2">{group.records.map((record) => <div key={record.listing_id} className="flex flex-col justify-between gap-1 rounded-lg bg-background p-3 text-sm sm:flex-row"><span className="min-w-0 break-words font-semibold text-foreground">{record.producer || 'Producer'}{record.location && <span className="font-normal text-muted-foreground"> · {record.location}</span>}</span><span className="text-muted-foreground">{Number(record.quantity || 0).toLocaleString()} {record.unit || group.unit || 'units'}</span></div>)}</div>}
          </CardContent>
        </Card>;
      })}</div>}
  </section>;
}
