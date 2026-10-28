import { DashboardText, useDashboardCopy } from '../../../i18n/DashboardCopy';
import { useEffect, useState } from 'react';
import { ChevronDown, ChevronUp, ShoppingCart } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../components/ui/card';
import { Badge } from '../../../components/ui/badge';
import { request } from '../../../../lib/api';
import { LoadingState } from '../../../components/ui/loading-state';
import PageHeader from '../../../components/dashboard/PageHeader';
import { Button } from '../../../components/ui/button';
import ProductSupplyGroups from './ProductSupplyGroups';

type Demand = { id: number; buyer: string; product: string; category?: string; quantity: number | string; unit?: string; target_price?: number | string | null; location?: string | null; description?: string | null; deadline?: string | null; status: string; review_available: boolean; review_status: string; review_id: number; review_reason?: string; review_history: {id:number; actor_user_id:number; decision:string; reason?:string; created_at:string}[]; quality_requirements?:string; special_requirements?:string; preferred_delivery_date?:string; };

export default function ProcurementNeeds() {
  const copy = useDashboardCopy();
  const [demands, setDemands] = useState<Demand[]>([]);
  const [expanded, setExpanded] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const [busy, setBusy] = useState(false);
  const [reasons, setReasons] = useState<Record<number,string>>({});
  const [reviewError, setReviewError] = useState('');
  const decide = async (demand: Demand, decision: 'Approved' | 'Rejected') => {
    setBusy(true); setReviewError('');
    try { await request(`/api/admin/demands/${demand.id}/review`, {method:'POST', body:{decision, reason:reasons[demand.id] || null, expected_review_id:demand.review_id}}); setRefresh(value=>value+1); }
    catch(error:any) {setReviewError(error.message || 'Review failed.');} finally {setBusy(false);}
  };
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('all');
  const [refresh, setRefresh] = useState(0);
  useEffect(() => {
    setLoading(true); setError('');
    let alive = true;
    request('/api/admin/demands').then((result) => { if (!Array.isArray(result?.data)) throw new Error('Demand records are unavailable.'); if (alive) setDemands(result.data); })
      .catch((err) => { if (alive) setError(err instanceof Error ? err.message : 'Could not load buyer demand requests.'); })
      .finally(() => { if (alive) setLoading(false); });
    return () => { alive = false; };
  }, [refresh]);
  const filtered = demands.filter(demand => [demand.product, demand.buyer, demand.location].join(' ').toLowerCase().includes(search.trim().toLowerCase()) && (status === 'all' || demand.status === status));

  return <div className="min-w-0 space-y-6">
    <PageHeader title={copy("Supply & Demand Monitoring")} icon={ShoppingCart} subtitle={copy("Producer supply and institutional buyer demand from stored HarborAI records.")} />
    <ProductSupplyGroups />
    {reviewError && <p role="alert" className="text-red-700">{reviewError}</p>}
    <section className="space-y-4"><div><h2 className="font-display text-xl text-foreground sm:text-2xl"><DashboardText text="Buyer Demand Requests" /></h2><p className="mt-1 text-sm text-muted-foreground"><DashboardText text="Review platform publication eligibility. This does not certify government procurement compliance or purchasing authority." /></p></div>
    <div className="grid gap-3 rounded-xl border border-border bg-card p-4 sm:grid-cols-2"><label className="min-w-0 text-sm font-semibold"><DashboardText text="Search demand" /><input type="search" value={search} onChange={event => setSearch(event.target.value)} placeholder={copy("Product, buyer or location")} className="mt-1 min-h-11 w-full rounded-lg border px-3" /></label><label className="min-w-0 text-sm font-semibold"><DashboardText text="Request status" /><select value={status} onChange={event => setStatus(event.target.value)} className="mt-1 min-h-11 w-full rounded-lg border bg-card px-3"><option value="all"><DashboardText text="All statuses" /></option>{Array.from(new Set(demands.map(demand => demand.status))).map(value => <option key={value} value={value}>{value}</option>)}</select></label></div>
    {loading ? <LoadingState message={copy("Loading demand requests...")} />
      : error ? <Card><CardContent className="p-8 text-center text-sm text-red-700" role="alert">{error}<Button variant="outline" className="ml-3 min-h-11" onClick={() => setRefresh(value => value + 1)}><DashboardText text="Retry" /></Button></CardContent></Card>
      : filtered.length === 0 ? <Card><CardContent className="p-8 text-center text-sm text-muted-foreground">{search || status !== 'all' ? copy("No requests match these filters.") : copy("No buyer demand requests found.")}</CardContent></Card>
      : <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{filtered.map((demand) => {
        const isOpen = expanded === demand.id;
        return <Card key={demand.id} className={`min-w-0 overflow-hidden border transition-shadow ${isOpen ? 'border-[#0F9488] shadow-md' : 'border-border'}`}>
          <button type="button" aria-expanded={isOpen} onClick={() => setExpanded(isOpen ? null : demand.id)} className="block min-h-11 w-full text-left focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#0F9488]"><CardHeader><div className="flex flex-col items-start gap-3"><div className="min-w-0"><CardTitle className="break-words text-lg text-foreground">{demand.product}</CardTitle><p className="mt-1 break-words text-sm text-muted-foreground">{demand.buyer}</p></div><div className="flex shrink-0 items-center gap-2"><Badge className={demand.status.toLowerCase() === 'open' || demand.status.toLowerCase() === 'active' ? 'bg-[#22C55E] text-white' : 'bg-[#123C5C] text-white'}>{demand.status}</Badge>{isOpen ? <ChevronUp className="h-4 w-4"/> : <ChevronDown className="h-4 w-4"/>}</div></div></CardHeader></button>
          <CardContent className="space-y-2 pt-0 text-sm [overflow-wrap:anywhere]"><div className="flex flex-wrap justify-between gap-3"><span className="text-muted-foreground"><DashboardText text="Quantity" /></span><span className="font-semibold text-foreground">{demand.quantity} {demand.unit}</span></div><div className="flex flex-wrap justify-between gap-3"><span className="text-muted-foreground"><DashboardText text="Deadline" /></span><span className="font-semibold text-foreground">{demand.deadline || '—'}</span></div><div className="flex flex-wrap justify-between gap-3"><span className="text-muted-foreground"><DashboardText text="Target price" /></span><span className="font-semibold text-foreground">{demand.target_price ? `₱${Number(demand.target_price).toLocaleString()} / ${demand.unit}` : copy("Not specified")}</span></div>
            <Badge>{demand.review_status}</Badge>{!demand.review_available && <p><DashboardText text="Demand review is temporarily unavailable. Contact the administrator." /></p>}
            {isOpen && <div className="mt-4 space-y-2 border-t border-border pt-4"><div className="flex flex-wrap justify-between gap-3"><span className="text-muted-foreground"><DashboardText text="Category" /></span><span className="min-w-0 break-words font-medium text-foreground">{demand.category || '—'}</span></div><div className="flex flex-wrap justify-between gap-3"><span className="text-muted-foreground"><DashboardText text="Location" /></span><span className="min-w-0 break-words font-medium text-foreground">{demand.location || '—'}</span></div>{demand.description && <p className="whitespace-pre-wrap break-words leading-6 text-muted-foreground">{demand.description}</p>}</div>}
            {isOpen && <div className="space-y-3 border-t pt-3">
              <p><DashboardText text="Quality:" />{' '}{demand.quality_requirements || copy("Not specified")}</p><p><DashboardText text="Requirements:" />{' '}{demand.special_requirements || copy("Not specified")}</p><p><DashboardText text="Delivery:" />{' '}{demand.preferred_delivery_date || copy("Not specified")}</p>
              {demand.review_available && ['Pending Review','Legacy Unreviewed'].includes(demand.review_status) && ['Active','Offers Received','open'].includes(demand.status) && <><label className="block"><DashboardText text="Review reason (required to reject)" /><textarea className="mt-1 w-full rounded border p-2" maxLength={2000} value={reasons[demand.id] || ''} onChange={e=>setReasons({...reasons,[demand.id]:e.target.value})}/></label><div className="flex flex-wrap gap-2"><Button disabled={busy} onClick={()=>void decide(demand,'Approved')}><DashboardText text="Approve publication" /></Button><Button variant="outline" disabled={busy || !reasons[demand.id]?.trim()} onClick={()=>void decide(demand,'Rejected')}><DashboardText text="Reject" /></Button></div></>}
              <h4 className="font-semibold"><DashboardText text="Review history" /></h4>{demand.review_history?.length ? demand.review_history.map(review=><p key={review.id}>{review.decision}{' '}<DashboardText text="· User #" />{' '}{review.actor_user_id} · {new Date(review.created_at).toLocaleString()} {review.reason}</p>) : <p><DashboardText text="No review decisions recorded." /></p>}
            </div>}
          </CardContent>
        </Card>;
      })}</div>}
    </section>
  </div>;
}