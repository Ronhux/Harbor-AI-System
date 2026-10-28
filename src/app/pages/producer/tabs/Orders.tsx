import { DashboardText, useDashboardCopy } from '../../../i18n/DashboardCopy';
import { useNotificationRecord } from '../../../contracts/useNotificationRecord';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Package, Clock, Truck, CheckCircle2 } from 'lucide-react';
import { Card, CardContent } from '../../../components/ui/card';
import { Badge } from '../../../components/ui/badge';
import { Button } from '../../../components/ui/button';
import FeedbackModal from '../../../components/ui/feedback-modal';
import PageHeader from '../../../components/dashboard/PageHeader';
import { request } from '../../../../lib/api';

type OrderDetail = {
  order_detail_id: number; listing_id: number; product_name: string;
  quantity: number; unit?: string | null; unit_price: number; subtotal: number;
};

type Order = {
  order_id: number; producer_subtotal: number; order_date: string; delivery_date?: string | null;
  fulfillment_status: string; payment_status: string;
  buyer?: { name?: string; organization_name?: string | null };
  /** Only the lines belonging to the logged in producer's own listings. */
  details?: OrderDetail[];
  negotiation?: { reference: string; status?: string; agreement_snapshot: any } | null;
};

type StatusFeedback = { tone: 'loading' | 'success'; title: string; description: string };

const nextStatus: Record<string, string> = {
  'Order Submitted': 'Order Confirmed', 'Order Confirmed': 'Preparing Order',
  'Preparing Order': 'Ready for Shipment', 'Ready for Shipment': 'Shipped', Shipped: 'Delivered',
};

/**
 * Summarizes the producer's own lines on one order.
 *
 * A shared order can hold several of this producer's products, so the card
 * never presents one arbitrary line as if it were the whole order.
 */
const summarizeOwnDetails = (details: OrderDetail[]) => {
  const own = Array.isArray(details) ? details : [];

  if (own.length === 0) {
    return {
      title: 'Product',
      quantity: '—',
      unitPrice: null as number | null,
      products: 0,
    };
  }

  const units = Array.from(new Set(own.map((d) => d.unit).filter(Boolean)));
  const prices = Array.from(new Set(own.map((d) => Number(d.unit_price))));
  const totalQuantity = own.reduce((sum, d) => sum + Number(d.quantity || 0), 0);

  return {
    title: own.length === 1 ? own[0].product_name : `${own.length} products`,
    quantity:
      units.length === 1
        ? `${Number(totalQuantity).toLocaleString()} ${units[0]}`
        : own.length === 1
          ? Number(totalQuantity).toLocaleString()
          : 'Mixed units',
    unitPrice: prices.length === 1 ? Number(own[0].unit_price) : null,
    products: own.length,
  };
};

export default function Orders() {
  const copy = useDashboardCopy();
  const [orders, setOrders] = useState<Order[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState<number | null>(null);
  const [statusFeedback, setStatusFeedback] = useState<StatusFeedback | null>(null);
  const [view, setView] = useState<'active' | 'history'>('active');
  const updatingOrder = useRef(false);

  /*
   | Transaction history split.
   |
   | The terminal set mirrors NegotiationController's order transition table and
   | ProducerController::TERMINAL_FULFILFILLMENT_STATUSES exactly: Delivered,
   | Completed, Cancelled and Rejected close an order. Anything else - including
   | the legacy 'Pending' column default - is still in progress and stays on the
   | Active view. Nothing is deleted and no stored status is rewritten; the two
   | views are the same records seen through different filters, so reports and
   | transaction history keep full access to completed orders.
   */
  const HISTORY_STATUSES = ['Delivered', 'Completed', 'Cancelled', 'Rejected'];

  const isHistoryOrder = (order: Order) =>
    HISTORY_STATUSES.includes(order.fulfillment_status);

  const activeOrders = useMemo(
    () => orders.filter((order) => !isHistoryOrder(order)),
    [orders],
  );
  const historyOrders = useMemo(
    () => orders.filter(isHistoryOrder),
    [orders],
  );
  const [focusedOrder, setFocusedOrder] = useState<number | null>(null);
  const shownOrders = (view === 'active' ? activeOrders : historyOrders).filter(o => focusedOrder === null || o.order_id === focusedOrder);
  useNotificationRecord('orders', !loading && !error, id => {
    const order = orders.find(o => o.order_id === id);
    if (order) {setView(isHistoryOrder(order) ? 'history' : 'active');setFocusedOrder(id);}
    else setError('This order is not available to your account.');
  });

  const load = async () => {
    setLoading(true); setError('');
    try { const data = await request('/api/producer/orders'); setOrders(Array.isArray(data) ? data : []); }
    catch (e: any) { console.error('Unable to load producer transaction records', e); setError('Unable to load transaction records. Check your connection and try again.'); }
    finally { setLoading(false); }
  };
  useEffect(() => { void load(); }, []);

  const counts = useMemo(() => ({
    all: orders.length,
    pending: activeOrders.filter(o => o.fulfillment_status === 'Order Submitted').length,
    processing: activeOrders.filter(o => ['Order Confirmed', 'Preparing Order', 'Ready for Shipment'].includes(o.fulfillment_status)).length,
    transit: activeOrders.filter(o => o.fulfillment_status === 'Shipped').length,
    delivered: historyOrders.filter(o => ['Delivered', 'Completed'].includes(o.fulfillment_status)).length,
  }), [activeOrders, historyOrders]);

  const update = async (order: Order, status: string) => {
    if (updatingOrder.current) return;
    updatingOrder.current = true;
    setBusy(order.order_id); setError('');
    setStatusFeedback({
      tone: 'loading',
      title: 'Updating Order Status...',
      description: 'Please wait while we update this order.',
    });
    try {
      const result = await request(`/api/producer/orders/${order.order_id}`, {
        method: 'PATCH',
        body: { status },
      });
      const savedStatus = result?.order?.fulfillment_status;
      if (!savedStatus) throw new Error('The server did not confirm the updated order status.');

      setOrders((current) => current.map((item) =>
        item.order_id === order.order_id ? { ...item, fulfillment_status: savedStatus } : item,
      ));
      const descriptions: Record<string, string> = {
        'Order Confirmed': 'The order has been accepted and is now confirmed.',
        'Preparing Order': 'The order is now being prepared.',
        'Ready for Shipment': 'The order is now ready for shipment.',
        Shipped: 'The order has been marked as shipped.',
        Delivered: 'The order has been marked as delivered.',
        Completed: 'The order has been completed.',
      };
      setStatusFeedback({
        tone: 'success',
        title: savedStatus === 'Completed' ? 'Order Completed Successfully!' : 'Order Updated Successfully!',
        description: descriptions[savedStatus] || `The order status is now ${savedStatus}.`,
      });
    } catch (e: any) {
      setStatusFeedback(null);
      console.error('Unable to update producer order', e);
      setError(e?.message || 'This order could not be updated. Check the current status and try again.');
    } finally {
      updatingOrder.current = false;
      setBusy(null);
    }
  };

  const stats = [
    ['Kabuuang Orders', counts.all, Package], ['Pending', counts.pending, Clock],
    ['Pinoproseso', counts.processing, Package], ['In Transit', counts.transit, Truck],
    ['Nai-deliver', counts.delivered, CheckCircle2],
  ] as const;

  return (
    <div className="space-y-6 p-4 sm:p-6">
      <FeedbackModal
        open={statusFeedback !== null}
        tone={statusFeedback?.tone ?? 'loading'}
        title={statusFeedback?.title ?? ''}
        description={statusFeedback?.description ?? ''}
        busy={statusFeedback?.tone === 'loading'}
        onClose={() => setStatusFeedback(null)}
        onCloseLabel={copy("Done")}
      />
      <PageHeader title={copy("Transaction Management")} icon={Package} subtitle={copy("Subaybayan ang confirmed institutional orders at fulfillment.")} />
      {error && <div role="alert" className="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-red-50 p-3 text-sm text-red-800"><p>{error}</p><Button variant="outline" size="sm" onClick={() => void load()}><DashboardText text="Reload records" /></Button></div>}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">{stats.map(([label, count, Icon]) => <Card key={label} className="min-w-0"><CardContent className="p-4"><Icon className="mb-2 h-5 w-5 text-[#0F9488]"/><div className="text-2xl font-bold text-foreground">{count}</div><div className="text-sm text-muted-foreground">{label}</div></CardContent></Card>)}</div>
      <div role="tablist" aria-label={copy("Transaction view")} className="inline-flex w-full max-w-md rounded-full border border-border bg-card p-1">
        {([['active', 'Active Transactions', activeOrders.length], ['history', 'Transaction History', historyOrders.length]] as const).map(([value, label, count]) => (
          <button
            key={value}
            type="button"
            role="tab"
            aria-selected={view === value}
            onClick={() => setView(value)}
            className={`min-h-11 flex-1 rounded-full px-4 text-sm font-bold transition-colors ${view === value ? 'bg-[#123C5C] text-white' : 'text-muted-foreground hover:bg-background'}`}
          >
            {label}<span className="ml-2 opacity-80">{count}</span>
          </button>
        ))}
      </div>
      <p className="text-xs text-muted-foreground">
        {view === 'active'
          ? copy("Orders that still need monitoring or action: submitted, confirmed, preparing, ready to ship, or shipped.")
          : copy("Completed and closed transactions: delivered, completed, cancelled, or rejected. These records are retained for reports and transaction history.")}
      </p>
      {focusedOrder !== null && <Button variant="outline" onClick={() => setFocusedOrder(null)}><DashboardText text="Show all transactions" /></Button>}
      {loading ? <p className="p-6 text-center text-muted-foreground"><DashboardText text="Loading orders…" /></p> : orders.length === 0 ? <Card><CardContent className="p-8 text-center text-muted-foreground"><DashboardText text="No official orders are linked to your listings yet. Orders appear here after both sides confirm a negotiation." /></CardContent></Card> : shownOrders.length === 0 ? <Card><CardContent className="p-8 text-center text-muted-foreground">{view === 'active' ? copy("No active transactions. Completed transactions are available in Transaction History.") : copy("No completed transactions yet.")}</CardContent></Card> : <div className="grid gap-4 lg:grid-cols-2">{shownOrders.map(order => {
        const summary = summarizeOwnDetails(order.details ?? []);
        const next = nextStatus[order.fulfillment_status];
        return (
          <Card key={order.order_id} className="border-border">
            <CardContent className="space-y-4 p-4">
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0">
                  <h2 className="break-words font-bold text-foreground"><DashboardText text="Order #" />{' '}{order.order_id} · {summary.title}</h2>
                  <p className="text-sm text-muted-foreground">{order.buyer?.organization_name || order.buyer?.name || copy("Institutional buyer")}</p>
                </div>
                <div className="flex flex-wrap gap-2">
                  <Badge className="bg-[#0F9488]/10 text-[#0B4842]">{order.fulfillment_status === 'Ready for Shipment' ? <><Truck className="mr-1 inline h-3.5 w-3.5" aria-hidden="true"/><DashboardText text="Ready to Ship" /></> : order.fulfillment_status}</Badge>
                  {order.negotiation?.status === 'Agreement Confirmed' && <Badge className="gap-1 bg-emerald-50 text-emerald-800"><CheckCircle2 className="h-3.5 w-3.5" aria-hidden="true"/><DashboardText text="Both Parties Approved" /></Badge>}
                </div>
              </div>
              <dl className="grid min-w-0 grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)] gap-2 break-words text-sm">
                <dt>{summary.products > 1 ? copy("Total quantity") : copy("Agreed quantity")}</dt><dd>{summary.quantity}</dd>
                <dt><DashboardText text="Agreed unit price" /></dt><dd>{summary.unitPrice === null ? (summary.products > 1 ? copy("Varies per product") : '—') : `₱${summary.unitPrice.toLocaleString()}`}</dd>
                <dt><DashboardText text="Total" /></dt><dd className="font-bold">₱{Number(order.producer_subtotal || 0).toLocaleString()}</dd>
                <dt><DashboardText text="Delivery date" /></dt><dd>{order.delivery_date || copy("Not specified")}</dd>
                <dt><DashboardText text="Order date" /></dt><dd>{order.order_date}</dd>
                <dt><DashboardText text="Negotiation" /></dt><dd>{order.negotiation?.reference || '—'}</dd>
              </dl>
              {order.negotiation?.agreement_snapshot?.notes && <p className="whitespace-pre-wrap rounded-lg bg-card p-3 text-sm">{order.negotiation.agreement_snapshot.notes}</p>}
              {next && <Button disabled={busy === order.order_id} onClick={() => void update(order, next)}>{busy === order.order_id ? copy("Saving…") : order.fulfillment_status === 'Order Submitted' ? copy("Accept Order") : copy("Mark {0}", [next])}</Button>}
            </CardContent>
          </Card>
        );
      })}</div>}
    </div>
  );
}
