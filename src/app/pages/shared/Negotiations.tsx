import { DashboardText, useDashboardCopy } from '../../i18n/DashboardCopy';
import { AlertDialog, AlertDialogContent, AlertDialogTitle, AlertDialogDescription, AlertDialogCancel } from '../../components/ui/alert-dialog';
import { useNotificationRecord } from '../../contracts/useNotificationRecord';
import { useEffect, useRef, useState } from 'react';
import { Dialog, DialogContent, DialogTitle } from '../../components/ui/dialog';
import { CheckCircle2, Clock3, GitCompareArrows, Handshake, type LucideIcon } from 'lucide-react';
import { Badge } from '../../components/ui/badge';
import { Button } from '../../components/ui/button';
import { Card, CardContent } from '../../components/ui/card';
import { Input } from '../../components/ui/input';
import PageHeader from '../../components/dashboard/PageHeader';
import { request } from '../../../lib/api';

type Props = { side: 'buyer' | 'producer'; title?: string; icon?: LucideIcon };
type Confirmation = {
  version?: number|null; buyer_confirmed_at?: string|null; producer_confirmed_at?: string|null;
  buyer_confirmed?: boolean; producer_confirmed?: boolean; mutually_approved?: boolean;
  awaiting?: 'buyer'|'producer'|null; you_confirmed?: boolean; other_party_confirmed?: boolean; your_action_required?: boolean;
};
type Negotiation = {
  id:number; reference:string; status:string; status_label?:string; awaiting?:'buyer'|'producer'|null; confirmation?:Confirmation;
  product:{name:string;category?:string;image_url?:string;images?:{url:string}[]};
  buyer:{name:string;organization?:string}; producer:{name:string};
  rejected_by?:'buyer'|'producer'|null; rejected_version_id?:number|null;
  order?:{order_id:number;fulfillment_status:string;payment_status?:string}|null;
  version:any; versions:any[]; events:any[]; agreement_snapshot?:any; updated_at:string;
};

const colors: Record<string, string> = {
  'Awaiting Producer Review': 'bg-amber-100 text-amber-900',
  'Awaiting Buyer Confirmation': 'bg-amber-100 text-amber-900',
  'Revised Proposal': 'bg-blue-100 text-blue-900',
  'Revised Terms Submitted': 'bg-blue-100 text-blue-900',
  'Agreement Confirmed': 'bg-emerald-100 text-emerald-900',
  Rejected: 'bg-red-100 text-red-900', Cancelled: 'bg-slate-100 text-slate-700',
};

/**
 * Final negotiation states. This mirrors
 * NegotiationController::CLOSED_STATUSES on the server, plus the confirmed
 * 'Agreement Confirmed' state, which has left the negotiation workflow and now
 * lives on as an order. No status is renamed and none is invented: an existing
 * record simply appears under the other view.
 */
const HISTORY_STATUSES = ['Rejected', 'Cancelled', 'Expired', 'Agreement Confirmed'];

const isHistoryNegotiation = (n: Negotiation) => HISTORY_STATUSES.includes(n.status);

/**
 * Renders the status from the confirmations that are actually stored for the
 * current proposal version, so a saved confirmation can never be shown as still
 * pending. `status_label` from the API is the source of truth; the raw workflow
 * status is only used as a fallback for older payloads.
 */
function NegotiationStatus({ status, label }: { status:string; label?:string }) {
  const text = label || status;
  if (text === 'Approved' || status === 'Agreement Confirmed') return <Badge className="inline-flex items-center gap-1 bg-emerald-100 text-emerald-900"><CheckCircle2 className="h-3.5 w-3.5" aria-hidden="true"/><DashboardText text="Approved" /></Badge>;
  if (text === 'Buyer Confirmed') return <Badge className="inline-flex items-center gap-1 bg-amber-100 text-amber-900"><CheckCircle2 className="h-3.5 w-3.5" aria-hidden="true"/><DashboardText text="Buyer Confirmed" /></Badge>;
  if (text === 'Revision Pending' || status === 'Revised Proposal' || status === 'Revised Terms Submitted') return <Badge className="inline-flex items-center gap-1 bg-blue-100 text-blue-900"><GitCompareArrows className="h-3.5 w-3.5" aria-hidden="true"/><DashboardText text="Revision Pending" /></Badge>;
  if (text === 'Awaiting Producer Confirmation' || status === 'Awaiting Producer Review') return <Badge className="inline-flex items-center gap-1 bg-amber-100 text-amber-900"><Clock3 className="h-3.5 w-3.5" aria-hidden="true"/><DashboardText text="Awaiting Producer" /></Badge>;
  if (text === 'Awaiting Buyer Confirmation') return <Badge className="inline-flex items-center gap-1 bg-amber-100 text-amber-900"><Clock3 className="h-3.5 w-3.5" aria-hidden="true"/><DashboardText text="Awaiting Buyer" /></Badge>;
  return <Badge className={colors[status] || 'bg-slate-100 text-slate-800'}>{text}</Badge>;
}

export default function Negotiations({ side, title, icon }: Props) {
  const copy = useDashboardCopy();
  const [items, setItems] = useState<Negotiation[]>([]);
  const [view, setView] = useState<'active' | 'history'>('active');
  const activeItems = items.filter((n) => !isHistoryNegotiation(n));
  const historyItems = items.filter(isHistoryNegotiation);
  const shownItems = view === 'active' ? activeItems : historyItems;
  const [selected, setSelected] = useState<Negotiation | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [statusMessage, setStatusMessage] = useState('');
  const [busy, setBusy] = useState(false);
  const [remarks, setRemarks] = useState('');
  const [showRevise, setShowRevise] = useState(false);
  const [confirmAction, setConfirmAction] = useState<string | null>(null);
  const [price, setPrice] = useState('');
  const [quantity, setQuantity] = useState('');
  const [deliveryDate, setDeliveryDate] = useState('');
  const [arrangement, setArrangement] = useState('');
  const [orderAddress, setOrderAddress] = useState('');
  const root = side === 'buyer' ? '/api/buyer/negotiations' : '/api/producer/negotiations';

  const load = async () => {
    setLoading(true); setError('');
    try { const r = await request(root); setItems(r?.data || []); }
    catch (e: any) { setError(e?.message || 'Could not load negotiations.'); }
    finally { setLoading(false); }
  };
  useEffect(() => { void load(); }, [side]);

  useNotificationRecord('negotiations', !loading && !error, id => {
    const item = items.find(n => n.id === id);
    if (item) {setView(isHistoryNegotiation(item) ? 'history' : 'active');void detail(item);}
    else setError('This negotiation is not available to your account.');
  });

  const detail = async (n: Negotiation) => {
    setError(''); setStatusMessage('');
    try { const r = await request(`${root}/${n.id}`); setSelected(r.data); }
    catch (e: any) { setError(e?.message || 'Could not open this negotiation.'); }
  };

  /** Reloads the negotiation from the API so the modal always shows persisted state. */
  const refreshSelected = async (id: number) => {
    try { const latest = await request(`${root}/${id}`); setSelected(latest.data); return latest.data as Negotiation; }
    catch { return null; }
  };

  const operationLock=useRef(false);
  const act = async (action: string) => {
    if (!selected || operationLock.current) return;
    operationLock.current=true;
    setBusy(true); setError('');
    try {
      if (action === 'order') {
        const r = await request(`${root}/${selected.id}/order`, { method: 'POST', body: { shipping_address: orderAddress } });
        setStatusMessage(r?.message || 'Official order submitted.');
      } else {
        const r = await request(`${root}/${selected.id}/respond`, {
          method: 'POST',
          body: {
            action, version: Number(selected.version?.version), remarks,
            ...(action === 'revise' ? { unit_price: Number(price), quantity: Number(quantity), delivery_date: deliveryDate || null, delivery_arrangement: arrangement } : {}),
          },
        });
        if (r.order) {
          window.dispatchEvent(new CustomEvent('orderPlaced', { detail: { orderId: r.order.order_id } }));
          setStatusMessage(`Agreement confirmed. Order #${r.order.order_id} is ${r.order.fulfillment_status || 'Order Submitted'}.`);
        } else if (action === 'accept' || action === 'confirm') {
          setStatusMessage('');
        } else if (r.message) setStatusMessage(r.message);
      }
      setConfirmAction(null);
      setShowRevise(false);
      // Always re-read the record so the modal reflects what was actually saved.
      await refreshSelected(selected.id);
      await load();
    } catch (e: any) {
      setConfirmAction(null);
      if (e?.status === 409) {
        await refreshSelected(selected.id); await load();
        setError(`${e.message} The latest proposal has been loaded; review it before confirming.`);
      } else {
        setError(e?.message || 'The action could not be completed. Please check availability and try again.');
      }
    } finally { operationLock.current=false;setBusy(false); }
  };

  const activeVersion = selected?.version || {};

  /**
   * The proposed terms are shown as separate amounts so a producer can see
   * exactly what the additional charges are before confirming a version.
   * These mirror agreementSnapshot() on the server and change nothing about
   * how any total is stored or reported.
   */
  const activeCharges = Number(activeVersion.additional_charges || 0);
  const activeSubtotal = Number(activeVersion.unit_price || 0) * Number(activeVersion.quantity || 0);
  const activeTotal = activeSubtotal + activeCharges;

  const snapshot = selected?.agreement_snapshot;
  const snapshotCharges = Number(snapshot?.additional_charges || 0);
  const snapshotSubtotal = Number(snapshot?.subtotal || 0);
  const snapshotTotal = Number(snapshot?.total || 0);

  const confirmation = selected?.confirmation || {};
  const closed = ['Rejected', 'Cancelled', 'Expired'].includes(selected?.status || '');
  const myConfirmed = !closed && Boolean(confirmation.you_confirmed ?? (side === 'buyer' ? activeVersion.buyer_confirmed_at : activeVersion.producer_confirmed_at));
  const mutuallyApproved = selected?.status === 'Agreement Confirmed'
    && Boolean(confirmation.mutually_approved ?? (activeVersion.buyer_confirmed_at && activeVersion.producer_confirmed_at));
  const myConfirmedAt = side === 'buyer' ? confirmation.buyer_confirmed_at : confirmation.producer_confirmed_at;
  const waitingForOther = side === 'buyer' ? 'producer' : 'buyer';

  return <div className="space-y-5">
    <PageHeader title={title ?? (side === 'buyer' ? copy("My Negotiations") : copy("Negotiation Requests"))} icon={icon ?? Handshake} subtitle={copy("Review proposed terms, responses, agreement confirmations, and linked orders.")} />
    {error && <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-800">{copy(error)}</p>}
    {loading ? <p className="p-6 text-muted-foreground"><DashboardText text="Loading negotiation records…" /></p> : items.length === 0 ? <Card><CardContent className="p-8 text-center text-muted-foreground"><DashboardText text="No negotiation requests yet. Start from an available product listing." /></CardContent></Card> : <>
    <div role="tablist" aria-label={copy("Negotiation view")} className="inline-flex w-full max-w-md rounded-full border border-border bg-card p-1">
      {([['active', 'Active Requests', activeItems.length], ['history', 'Negotiation History', historyItems.length]] as const).map(([value, label, count]) => (
        <button
          key={value}
          type="button"
          role="tab"
          aria-selected={view === value}
          onClick={() => setView(value)}
          className={`min-h-11 flex-1 rounded-full px-4 text-sm font-bold transition-colors ${view === value ? 'bg-[#123C5C] text-white' : 'text-muted-foreground hover:bg-background'}`}
        >
          {copy(label)}<span className="ml-2 opacity-80">{count}</span>
        </button>
      ))}
    </div>
    <p className="text-xs text-muted-foreground">
      {view === 'active'
        ? copy("Requests still under negotiation and awaiting a response or confirmation.")
        : copy("Rejected, cancelled, expired, and confirmed requests. Negotiation terms, versions, and timestamps are retained here.")}
    </p>
    {shownItems.length === 0
      ? <Card><CardContent className="p-8 text-center text-muted-foreground">{view === 'active' ? copy("No active negotiation requests. Previous outcomes are available in Negotiation History.") : copy("No negotiation history yet.")}</CardContent></Card>
      : <div className="grid gap-3">{shownItems.map(n => <Card key={n.id} className="border-border"><CardContent className="flex flex-col gap-4 p-4 sm:flex-row sm:items-center"><div className="min-w-0 flex-1"><p className="font-bold text-foreground">{n.reference} · {n.product?.name}</p><p className="mt-1 text-sm text-muted-foreground">{side === 'buyer' ? n.producer?.name : (n.buyer?.organization || n.buyer?.name)} · ₱{Number(n.version?.unit_price || 0).toLocaleString()} / {n.version?.unit} · {Number(n.version?.quantity || 0).toLocaleString()} {n.version?.unit}</p><p className="mt-1 text-xs text-muted-foreground"><DashboardText text="Updated" />{' '}{new Date(n.updated_at).toLocaleString()}</p></div><div className="flex flex-wrap items-center gap-2"><NegotiationStatus status={n.status} label={n.status_label}/>{n.order && <Badge className="bg-blue-50 text-blue-900"><DashboardText text="Order:" />{' '}{n.order.fulfillment_status}</Badge>}</div><Button variant="outline" onClick={() => void detail(n)}><DashboardText text="View details" /></Button></CardContent></Card>)}</div>}
  </>}

    <Dialog open={Boolean(selected)} onOpenChange={open=>{if(!open&&!busy)setSelected(null)}}>{selected && <DialogContent closeDisabled={busy} showCloseButton={false} aria-describedby={undefined} onEscapeKeyDown={event=>{if(busy)event.preventDefault()}} onInteractOutside={event=>{if(busy)event.preventDefault()}} className="flex max-h-[90dvh] w-full flex-col overflow-y-auto rounded-2xl bg-card p-5 shadow-xl sm:max-w-3xl">
        <div className="sticky -top-5 z-10 -mx-5 -mt-5 mb-4 flex shrink-0 items-start justify-between gap-3 border-b border-border bg-card px-5 pb-3 pt-5">
          <div className="min-w-0"><DialogTitle className="break-words text-xl font-black text-foreground">{selected.reference}</DialogTitle><p className="break-words text-sm text-muted-foreground">{selected.product?.name} · {side === 'buyer' ? selected.producer?.name : (selected.buyer?.organization || selected.buyer?.name)}</p></div>
          <Button variant="ghost" disabled={busy} className="min-h-11 shrink-0" onClick={() => setSelected(null)}><DashboardText text="Close" /></Button>
        </div>
        {error&&<p role="alert" className="rounded-lg bg-destructive/10 p-3 text-sm text-destructive">{copy(error)}</p>}
        <div className="min-w-0 flex-1">
          <div className="rounded-xl bg-background p-4"><NegotiationStatus status={selected.status} label={selected.status_label}/><dl className="mt-3 grid grid-cols-1 gap-x-4 gap-y-2 text-sm sm:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]"><dt><DashboardText text="Original listed price" /></dt><dd className="break-words">₱{Number(activeVersion.listed_price || 0).toLocaleString()} / {activeVersion.unit}</dd><dt><DashboardText text="Proposed price" /></dt><dd className="break-words">₱{Number(activeVersion.unit_price || 0).toLocaleString()} / {activeVersion.unit}</dd><dt><DashboardText text="Quantity" /></dt><dd className="break-words">{activeVersion.quantity} {activeVersion.unit}</dd><dt><DashboardText text="Product subtotal" /></dt><dd className="break-words">₱{activeSubtotal.toLocaleString()}</dd><dt><DashboardText text="Additional charges" /></dt><dd className="break-words">₱{activeCharges.toLocaleString()}</dd><dt><DashboardText text="Proposed total" /></dt><dd className="break-words font-semibold">₱{activeTotal.toLocaleString()}</dd><dt><DashboardText text="Delivery date" /></dt><dd className="break-words">{activeVersion.delivery_date || copy("Not set")}</dd><dt><DashboardText text="Delivery arrangement" /></dt><dd className="break-words">{activeVersion.delivery_arrangement || copy("Not specified")}</dd></dl>{activeVersion.notes && <p className="mt-3 whitespace-pre-wrap break-words text-sm">{activeVersion.notes}</p>}</div>
          <section className="mt-5"><h4 className="font-bold text-foreground"><DashboardText text="Proposal versions" /></h4><div className="mt-2 space-y-2">{selected.versions?.map(v => {
            const rejectedVersion = selected.status === 'Rejected' && Number(selected.rejected_version_id) === Number(v.id);
            const buyerDecision = rejectedVersion && selected.rejected_by === 'buyer' ? 'Rejected this proposal' : (v.buyer_confirmed_at || 'Pending');
            const producerDecision = rejectedVersion && selected.rejected_by === 'producer' ? 'Rejected this proposal' : (v.producer_confirmed_at || 'Pending');
            return <div key={v.id} className="rounded-lg border border-border p-3 text-sm"><b><DashboardText text="Version" />{' '}{v.version}</b> · {v.proposed_by_role} · ₱{Number(v.unit_price).toLocaleString()} × {v.quantity} {v.unit}<p className="mt-1 break-words text-xs text-muted-foreground"><DashboardText text="Buyer confirmation:" />{' '}{buyerDecision}{' '}<DashboardText text="· Producer confirmation:" />{' '}{producerDecision}</p>{v.remarks && <p className="mt-1 whitespace-pre-wrap">{v.remarks}</p>}</div>;
          })}</div></section>
          <section className="mt-4"><h4 className="font-bold text-foreground"><DashboardText text="History" /></h4><ol className="mt-2 space-y-2">{selected.events?.map((ev: any) => <li key={ev.id} className="border-l-2 border-[#0F9488] pl-3 text-sm"><b>{ev.event.replaceAll('_', ' ')}</b> · {ev.status}<p className="break-words text-xs text-muted-foreground">{ev.created_at ? new Date(ev.created_at).toLocaleString() : ''} {ev.remarks || ''}</p>{ev.terms?.additional_charges != null && <p className="break-words text-xs text-muted-foreground"><DashboardText text="Additional charges at this step: ₱" />{' '}{Number(ev.terms.additional_charges).toLocaleString()}</p>}</li>)}</ol></section>
          {showRevise && <div className="mt-4 grid gap-3 rounded-xl border p-4 sm:grid-cols-2"><label className="text-sm"><DashboardText text="Proposed price" /><Input type="number" min="0.01" step="0.01" value={price} onChange={e => setPrice(e.target.value)} /></label><label className="text-sm"><DashboardText text="Quantity" /><Input type="number" min="0.001" step="0.001" value={quantity} onChange={e => setQuantity(e.target.value)} /></label><label className="text-sm"><DashboardText text="Delivery date" /><Input type="date" value={deliveryDate} onChange={e => setDeliveryDate(e.target.value)} /></label><label className="text-sm"><DashboardText text="Delivery arrangement" /><Input value={arrangement} onChange={e => setArrangement(e.target.value)} /></label><label className="text-sm sm:col-span-2"><DashboardText text="Remarks" /><Input value={remarks} onChange={e => setRemarks(e.target.value)} /></label><Button disabled={busy || !price || !quantity} onClick={() => void act('revise')}><DashboardText text="Submit revised terms" /></Button></div>}
          {statusMessage && <p role="status" className="mt-3 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-900">{statusMessage}</p>}
          {closed && <div role="status" className={`mt-4 rounded-xl border p-4 ${selected.status === 'Rejected' ? 'border-red-200 bg-red-50 text-red-900' : 'border-slate-200 bg-slate-50 text-slate-800'}`}>
            <p className="font-bold">{selected.status === 'Rejected' ? (selected.rejected_by === side ? copy("You Rejected This Proposal") : copy("Negotiation Rejected")) : selected.status === 'Cancelled' ? copy("Negotiation Cancelled") : copy("Negotiation Expired")}</p>
            <p className="mt-1 text-sm">{selected.status === 'Rejected'
              ? selected.rejected_by === side ? copy("You rejected this proposal. No further confirmation is required from you.") : selected.rejected_by ? copy("The {0} rejected this proposal. No further confirmation is required.", [selected.rejected_by]) : copy("This proposal was rejected. No further confirmation is required.")
              : selected.status === 'Cancelled' ? copy("This negotiation was cancelled. No further confirmation is required.") : copy("This negotiation has expired. No further confirmation is required.")}</p>
          </div>}
          {myConfirmed && <div role="status" className="mt-4 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4"><CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"/><div className="min-w-0"><p className="font-bold text-emerald-900"><DashboardText text="You Confirmed This Proposal" /></p><p className="mt-1 text-sm text-emerald-900">{mutuallyApproved ? copy("You and the {0} confirmed version {1}. The agreement is approved.", [waitingForOther, confirmation.version ?? activeVersion.version]) : copy("Version {0} was confirmed on {1}. No further action is needed from you; this is waiting for the {2}.", [confirmation.version ?? activeVersion.version, myConfirmedAt ? new Date(myConfirmedAt).toLocaleString() : 'an unavailable date', waitingForOther])}</p></div></div>}
          {!myConfirmed && !mutuallyApproved && !closed && <div className="mt-4 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4"><Clock3 className="mt-0.5 h-5 w-5 shrink-0 text-amber-600" aria-hidden="true"/><div className="min-w-0"><p className="font-bold text-amber-900"><DashboardText text="Your confirmation is required" /></p><p className="mt-1 text-sm text-amber-900"><DashboardText text="The" />{' '}{side === 'buyer' ? copy("producer") : copy("buyer")}{' '}<DashboardText text="submitted version" />{' '}{confirmation.version ?? activeVersion.version}{' '}<DashboardText text=". Confirm the terms below once you have reviewed them." /></p></div></div>}
          {mutuallyApproved && <div className="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4"><p className="font-bold text-emerald-900"><DashboardText text="Both sides confirmed version" />{' '}{selected.agreement_snapshot?.version ?? confirmation.version}{' '}<DashboardText text=". Total ₱" />{' '}{snapshotTotal.toLocaleString()}.</p><p className="mt-1 text-sm text-emerald-900"><DashboardText text="Product subtotal ₱" />{' '}{snapshotSubtotal.toLocaleString()}{' '}<DashboardText text="· Additional charges ₱" />{' '}{snapshotCharges.toLocaleString()}</p>{selected.order ? <p className="mt-1 text-sm text-emerald-900"><DashboardText text="Order #" />{' '}{selected.order.order_id}{' '}<DashboardText text="status:" /><b>{selected.order.fulfillment_status}</b><DashboardText text=". Track fulfillment in Orders." /></p> : <p className="mt-1 text-sm text-emerald-900"><DashboardText text="The agreement is approved. Its official order status will appear here when submitted." /></p>}</div>}
          {!mutuallyApproved && !closed && <div className="mt-5 flex flex-wrap gap-2">{!myConfirmed && <Button disabled={busy} onClick={() => setConfirmAction('accept')}>{busy ? copy("Confirming…") : copy("Confirm these terms")}</Button>}<Button variant="outline" disabled={busy} onClick={() => { setShowRevise(v => !v); setPrice(String(activeVersion.unit_price || '')); setQuantity(String(activeVersion.quantity || '')); setDeliveryDate(activeVersion.delivery_date || ''); setArrangement(activeVersion.delivery_arrangement || ''); }}><DashboardText text="Request changes" /></Button><Button variant="destructive" disabled={busy} onClick={() => setConfirmAction('reject')}><DashboardText text="Reject" /></Button>{side === 'buyer' && <Button variant="ghost" disabled={busy} onClick={() => setConfirmAction('cancel')}><DashboardText text="Cancel negotiation" /></Button>}</div>}
        </div>
        {confirmAction && <AlertDialog open onOpenChange={open=>{if(!open&&!busy)setConfirmAction(null)}}><AlertDialogContent role="alertdialog" aria-modal="true" className="w-full max-w-md rounded-2xl bg-card p-5 shadow-xl"><AlertDialogTitle className="text-lg font-bold text-foreground"><DashboardText text="Confirm" />{' '}{copy(confirmAction)}?</AlertDialogTitle><AlertDialogDescription className="mt-2 text-sm text-muted-foreground">{confirmAction === 'accept' ? copy("You confirm version {0} at {1} per {2}, for {3} {4}. The total is ₱{5}, which includes additional charges of ₱{6}. The agreement requires confirmation from both parties.", [activeVersion.version, activeVersion.unit_price, activeVersion.unit, activeVersion.quantity, activeVersion.unit, activeTotal.toLocaleString(), activeCharges.toLocaleString()]) : copy("This decision will be recorded in negotiation history and the other party will be notified.")}</AlertDialogDescription><div className="mt-4 flex justify-end gap-2"><AlertDialogCancel asChild><Button variant="outline" disabled={busy} onClick={() => setConfirmAction(null)}><DashboardText text="Back" /></Button></AlertDialogCancel><Button variant={confirmAction === 'reject' ? 'destructive' : 'default'} disabled={busy} onClick={() => void act(confirmAction)}>{busy && <span aria-label={copy("Processing")} className="mr-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-current border-r-transparent" />}{busy ? copy("Processing…") : copy("Confirm")}</Button></div></AlertDialogContent></AlertDialog>}
    </DialogContent>}</Dialog>
  </div>;
}
