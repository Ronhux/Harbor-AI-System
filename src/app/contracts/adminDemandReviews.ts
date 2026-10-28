export type DemandReview = {
  id: number; buyer: string; product_name: string | null; product: string | null;
  category: string | null; quantity_needed: number | null; quantity: number | null;
  unit: string | null; max_price_per_unit: number | null; target_price: string | null;
  location: string | null; deadline: string | null; preferred_delivery_date: string | null;
  quality_requirements: string | null; special_requirements: string | null;
  partial_fulfillment: boolean | null; status: string; review_status: string | null;
  review_id: number; review_reason: string | null; review_available: boolean;
  review_history: {id: number; decision: string; reason: string | null; created_at: string | null}[];
  offer_count: number; created_at: string | null;
};
function record(value: unknown): Record<string, unknown> {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) throw new Error('Invalid demand response');
  return Object.fromEntries(Object.entries(value));
}
function text(value: unknown): string | null {
  if (value == null) return null;
  if (typeof value !== 'string') throw new Error('Invalid demand text');
  return value;
}
function number(value: unknown): number | null {
  if (value == null) return null;
  if ((typeof value !== 'number' && typeof value !== 'string') || value === '' || !Number.isFinite(Number(value))) throw new Error('Invalid demand number');
  return Number(value);
}
function flag(value: unknown): boolean | null {
  if (value == null) return null;
  if (value === true || value === 1 || value === '1') return true;
  if (value === false || value === 0 || value === '0') return false;
  throw new Error('Invalid demand flag');
}
export function readDemandReviews(value: unknown): DemandReview[] {
  const response = record(value);
  if (!Array.isArray(response.data)) throw new Error('Invalid demand list');
  return response.data.map((raw: unknown) => {
    const r = record(raw), id = number(r.id), status = text(r.status);
    if (!id || !Number.isSafeInteger(id) || !status) throw new Error('Invalid demand identity');
    if (r.review_history != null && !Array.isArray(r.review_history)) throw new Error('Invalid review history');
    const history: unknown[] = Array.isArray(r.review_history) ? r.review_history : [];
    return {
      id, status, buyer: text(r.buyer) ?? '', product_name: text(r.product_name), product: text(r.product),
      category: text(r.category), quantity_needed: number(r.quantity_needed), quantity: number(r.quantity),
      unit: text(r.unit), max_price_per_unit: number(r.max_price_per_unit), target_price: r.target_price == null ? null : String(number(r.target_price)),
      location: text(r.location), deadline: text(r.deadline), preferred_delivery_date: text(r.preferred_delivery_date),
      quality_requirements: text(r.quality_requirements), special_requirements: text(r.special_requirements),
      partial_fulfillment: flag(r.partial_fulfillment), review_status: text(r.review_status),
      review_id: number(r.review_id) ?? 0, review_reason: text(r.review_reason), review_available: flag(r.review_available) === true,
      review_history: history.map(raw => {const h = record(raw); return {id: number(h.id) ?? 0, decision: text(h.decision) ?? '', reason: text(h.reason), created_at: text(h.created_at)};}),
      offer_count: number(r.offer_count) ?? 0, created_at: text(r.created_at),
    };
  });
}
