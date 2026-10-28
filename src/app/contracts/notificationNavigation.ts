export type NotificationTarget = {tab: 'orders' | 'negotiations' | 'demands' | 'demand' | 'listings' | 'products'; recordId: number};
export function notificationTarget(item: {order_id?: unknown; negotiation_id?: unknown; listing_id?: unknown; navigation?: {type?: unknown; record_id?: unknown} | null}, producer = false): NotificationTarget | null {
  const id = (value: unknown) => typeof value === 'number' && Number.isSafeInteger(value) && value > 0;
  // Transaction relationships always take priority over a descriptive demand notice.
  if (id(item.order_id)) return {tab: 'orders', recordId: Number(item.order_id)};
  if (id(item.negotiation_id)) return {tab: 'negotiations', recordId: Number(item.negotiation_id)};
  const nav = item.navigation;
  if (nav && id(nav.record_id)) {
    if (nav.type === 'order') return {tab: 'orders', recordId: Number(nav.record_id)};
    if (nav.type === 'negotiation') return {tab: 'negotiations', recordId: Number(nav.record_id)};
    if (nav.type === 'demand_review') return {tab: 'demands', recordId: Number(nav.record_id)};
    if (nav.type === 'buyer_demand') return {tab: 'demand', recordId: Number(nav.record_id)};
  }
  if (id(item.listing_id)) return {tab: producer ? 'listings' : 'products', recordId: Number(item.listing_id)};
  return null;
}
