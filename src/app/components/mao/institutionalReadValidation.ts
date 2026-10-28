import type { DemandDetailRead, DemandListRead, OfferDetailRead, OfferListRead } from '../../contracts/institutionalDemandReads';
import { canViewOffer, type DetailViewer } from './demandDetailFixtures';
import { responseTimestamp, comparableSchedule } from '../../contracts/responseTimestamp';

export const READ_PAGE_LIMIT = 50;
const demandStates = ['draft', 'pending_review', 'open', 'under_review', 'partially_fulfilled', 'fulfilled', 'cancelled', 'expired'];
const offerStates = ['submitted', 'under_review', 'provisional', 'rejected', 'withdrawn', 'cancelled', 'expired'];
const postingTypes = ['market_inquiry', 'supply_requirement', 'official_procurement_reference'];
const units = ['kg', 'g', 'litre', 'piece', 'sack', 'crate'];
/** Errors name only fields, never payload values. Strict mock subsets, not final HTTP DTOs. */
export class ReadValidationError extends Error { constructor(field: string) { super(`Rejected synthetic response: ${field}`); } }
function requireValue(ok: unknown, field: string): asserts ok { if (!ok) throw new ReadValidationError(field); }
function object(value: unknown, keys: string[], field: string): Record<string, unknown> {
  requireValue(value !== null && typeof value === 'object' && !Array.isArray(value), field);
  const result = value as Record<string, unknown>;
  requireValue(Object.keys(result).every(key => keys.includes(key)), `${field}.unsupported_field`);
  return result;
}
function text(value: unknown, max: number, field: string, empty = false): asserts value is string { requireValue(typeof value === 'string' && value.length <= max && (empty || value.trim().length > 0), field); }
function integer(value: unknown, field: string, min = 1): asserts value is number { requireValue(typeof value === 'number' && Number.isSafeInteger(value) && value >= min, field); }
function choice(value: unknown, choices: string[], field: string) { requireValue(typeof value === 'string' && choices.includes(value), field); }
function array(value: unknown, field: string, max: number, min = 0): asserts value is unknown[] { requireValue(Array.isArray(value) && value.length >= min && value.length <= max, field); }
function unique(values: unknown[], field: string) { requireValue(new Set(values).size === values.length, `${field}.duplicate`); }
function date(value: unknown, field: string, nullable = false): number {
  if (nullable && value === null) return 0;
  text(value, 40, field);
  const stamp = responseTimestamp(value);
  requireValue(Number.isFinite(stamp), field);
  return stamp;
}
function decimal(value: unknown, whole: number, scale: number, field: string, positive = false): bigint {
  text(value, whole + scale + 1, field);
  requireValue(!/[\r\n]/.test(value) && new RegExp(`^\\d{1,${whole}}(?:\\.\\d{1,${scale}})?$`).test(value), field);
  const [a, b = ''] = value.split('.'); const result = BigInt(a + b.padEnd(scale, '0'));
  requireValue(!positive || result > 0n, field); return result;
}
function scaled(value: unknown, field: string, nullable = false): bigint | null {
  if (nullable && value === null) return null;
  requireValue((typeof value === 'number' && Number.isSafeInteger(value) && value >= 0) || (typeof value === 'string' && !/[\r\n]/.test(value) && /^\d{1,15}$/.test(value)), field);
  return BigInt(value as number | string);
}
function viewerAllowed(viewer: DetailViewer) { requireValue(['buyer_owner', 'producer_owner', 'fca_owner', 'unrelated_producer'].includes(viewer), 'viewer.permission'); }
function page(raw: unknown, after: number, offer: boolean, viewer: DetailViewer) {
  integer(after, 'after', 0); viewerAllowed(viewer);
  if (offer) requireValue(viewer === 'buyer_owner', 'offer_list.owner_only');
  const value = object(raw, ['data', 'next_cursor'], 'page'); array(value.data, 'page.data', READ_PAGE_LIMIT);
  let previous = after;
  value.data.forEach((entry, i) => {
    const row = object(entry, offer ? ['id', 'state', 'version'] : ['id', 'title', 'requesting_institution', 'posting_type', 'state', 'version', 'offer_deadline'], `page.data.${i}`);
    integer(row.id, 'row.id'); requireValue(row.id > previous, 'page.id_order'); previous = row.id;
    integer(row.version, 'row.version'); choice(row.state, offer ? offerStates : demandStates, 'row.state');
    if (!offer) {
      if (viewer !== 'buyer_owner') requireValue(row.state === 'open', 'browse.open_only');
      text(row.title, 160, 'row.title'); text(row.requesting_institution, 255, 'row.institution'); choice(row.posting_type, postingTypes, 'row.posting_type'); date(row.offer_deadline, 'row.deadline');
    }
  });
  requireValue(value.next_cursor === null || (value.data.length === READ_PAGE_LIMIT && value.next_cursor === previous), 'page.next_cursor');
}
export function validateDemandList(raw: unknown, viewer: DetailViewer, after = 0): DemandListRead { page(raw, after, false, viewer); return structuredClone(raw) as DemandListRead; }
export function validateOfferList(raw: unknown, viewer: DetailViewer, after = 0): OfferListRead { page(raw, after, true, viewer); return structuredClone(raw) as OfferListRead; }
function history(raw: unknown, offer: boolean, version?: number) {
  array(raw, 'history', 100);
  raw.forEach(entry => {
    const row = object(entry, offer ? ['action', 'previous_state', 'new_state', 'created_at', 'reason'] : ['action', 'previous_state', 'new_state', 'revision', 'created_at'], 'history.row');
    choice(row.action, offer ? ['offer_submitted', 'review', 'reject', 'withdraw', 'provisional_allocation', 'cancelled', 'expired'] : ['created', 'revised', 'state_changed'], 'history.action');
    for (const key of ['previous_state', 'new_state']) if (row[key] !== null) choice(row[key], offer ? offerStates : demandStates, `history.${key}`);
    date(row.created_at, 'history.date', true);
    if (offer) { if (row.reason !== null) text(row.reason, 1000, 'history.reason', true); }
    else { integer(row.revision, 'history.revision'); requireValue(row.revision <= version!, 'history.future_revision'); }
  });
}
export function validateDemandDetail(raw: unknown, viewer: DetailViewer, id: number): DemandDetailRead {
  viewerAllowed(viewer); integer(id, 'selected.id');
  const value = object(raw, ['demand', 'posting', 'items', 'history'], 'detail');
  const d = object(value.demand, ['request_id', 'mao_state', 'mao_version', 'created_at', 'updated_at'], 'demand');
  requireValue(d.request_id === id, 'demand.identity'); choice(d.mao_state, demandStates, 'demand.state'); integer(d.mao_version, 'demand.version');
  if (viewer !== 'buyer_owner') requireValue(['open', 'under_review'].includes(d.mao_state as string), 'demand.visibility');
  date(d.created_at, 'demand.created', true); date(d.updated_at, 'demand.updated', true);
  const p = object(value.posting, ['requesting_institution','title','description','purpose','program','posting_type','procurement_reference','items','budget_kind','budget_amount','currency','delivery_location','delivery_start','delivery_end','offer_deadline','partial_supply','required_documents','eligible_supplier_type','eligibility_requirements','rsbsa_required'], 'posting');
  for (const [key, max] of [['requesting_institution',255],['title',160],['description',4000],['purpose',2000],['delivery_location',2000],['eligibility_requirements',4000]] as const) text(p[key],max,`posting.${key}`);
  choice(p.posting_type, postingTypes, 'posting.type'); if (p.program != null) choice(p.program,['sagip_saka','kadiwa','other'],'posting.program');
  if (p.procurement_reference != null) text(p.procurement_reference,255,'posting.reference');
  if (p.posting_type === 'official_procurement_reference') text(p.procurement_reference,255,'posting.reference');
  choice(p.budget_kind,['none','indicative','abc'],'posting.budget_kind'); requireValue(p.currency === 'PHP','posting.currency');
  if (p.budget_kind === 'none') requireValue(p.budget_amount == null,'posting.no_budget'); else decimal(p.budget_amount,12,2,'posting.budget',true);
  if (p.budget_kind === 'abc') requireValue(p.posting_type === 'official_procurement_reference','posting.abc');
  const deadline = date(p.offer_deadline,'posting.deadline'), start = date(p.delivery_start,'posting.start'), end = date(p.delivery_end,'posting.end'); requireValue(comparableSchedule([p.offer_deadline,p.delivery_start,p.delivery_end]) && start >= deadline && end >= start,'posting.schedule');
  requireValue(typeof p.partial_supply === 'boolean' && typeof p.rsbsa_required === 'boolean','posting.booleans'); choice(p.eligible_supplier_type,['individual','fca','both'],'posting.suppliers');
  array(p.required_documents,'posting.documents',20); p.required_documents.forEach(v => text(v,160,'posting.document')); unique(p.required_documents,'posting.documents');
  array(p.items,'posting.items',20,1); array(value.items,'detail.items',20,1); requireValue(p.items.length === value.items.length,'detail.item_count');
  const ids: unknown[] = [], commodities: string[] = [];
  value.items.forEach(entry => {
    const row = object(entry,['id','commodity','category','variety','unit','specifications','requested','provisional','remaining','delivered'],'item'); integer(row.id,'item.id'); ids.push(row.id);
    const matched = (p.items as unknown[]).find(input => input !== null && typeof input === 'object' && !Array.isArray(input) && (input as Record<string,unknown>).commodity === row.commodity && (input as Record<string,unknown>).category === row.category && ((input as Record<string,unknown>).variety ?? null) === row.variety);
    const input = object(matched,['commodity','category','variety','quantity','unit','specifications','max_unit_price'],'posting.item');
    for (const [key,max] of [['commodity',160],['category',80],['specifications',2000]] as const) { text(row[key],max,`item.${key}`); requireValue(input[key] === row[key],`item.${key}.mismatch`); }
    if (row.variety !== null) text(row.variety,120,'item.variety',true); requireValue((input.variety ?? null) === row.variety,'item.variety.mismatch');
    choice(row.unit,units,'item.unit'); requireValue(input.unit === row.unit,'item.unit.mismatch');
    const requested = decimal(row.requested,9,3,'item.requested',true), provisional = decimal(row.provisional,9,3,'item.provisional'), remaining = decimal(row.remaining,9,3,'item.remaining');
    requireValue(requested === decimal(input.quantity,9,3,'item.quantity',true) && requested === provisional + remaining,'item.quantity_contradiction');
    requireValue(decimal(row.delivered,9,3,'item.delivered') === 0n,'item.delivered_placeholder');
    if (input.max_unit_price != null) decimal(input.max_unit_price,10,2,'item.price');
    commodities.push([row.commodity,row.category,row.variety ?? ''].map(v => String(v).trim().toLowerCase()).join('|'));
  }); unique(ids,'items'); unique(commodities,'commodities');
  history(value.history,false,d.mao_version); if (viewer !== 'buyer_owner') requireValue((value.history as unknown[]).length === 0,'history.owner_only');
  return structuredClone(raw) as DemandDetailRead;
}
export function validateOfferDetail(raw: unknown, viewer: DetailViewer, id: number, demandId: number): OfferDetailRead {
  requireValue(canViewOffer(viewer,id),'offer.permission');
  return validatePreparedOfferDetail(raw,id,demandId);
}
/** Data validation only; actual access is enforced by Laravel, not fixture identities. */
export function validatePreparedOfferDetail(raw:unknown,id:number,demandId:number):OfferDetailRead {
  const value = object(raw,['offer','submission','items','history'],'offer_detail');
  const o = object(value.offer,['id','demand_request_id','mao_state','mao_version','created_at','updated_at'],'offer');
  requireValue(o.id === id && o.demand_request_id === demandId,'offer.identity'); integer(o.mao_version,'offer.version'); choice(o.mao_state,offerStates,'offer.state'); date(o.created_at,'offer.created',true); date(o.updated_at,'offer.updated',true);
  const s = object(value.submission,['demand_version','supplier_type','representation_id','items','availability_date','delivery_information','eligibility_declaration'],'submission'); integer(s.demand_version,'submission.version'); choice(s.supplier_type,['individual','fca'],'submission.type');
  if (s.supplier_type === 'fca') integer(s.representation_id,'submission.representation'); else requireValue(s.representation_id == null,'submission.individual');
  date(s.availability_date,'submission.date'); text(s.delivery_information,2000,'submission.delivery'); text(s.eligibility_declaration,4000,'submission.declaration');
  array(s.items,'submission.items',20,1); array(value.items,'offer.items',20,1); requireValue(s.items.length === value.items.length,'offer.item_count');
  const ids: unknown[] = [], refs: unknown[] = [];
  value.items.forEach(entry => {
    const row = object(entry,['id','demand_item_id','offered_milli','unit_price_cents'],'offer.item'); integer(row.id,'offer.item.id'); integer(row.demand_item_id,'offer.item.reference'); ids.push(row.id); refs.push(row.demand_item_id);
    const matched = (s.items as unknown[]).find(input => input !== null && typeof input === 'object' && !Array.isArray(input) && (input as Record<string,unknown>).demand_item_id === row.demand_item_id);
    const input = object(matched,['demand_item_id','quantity','unit_price'],'submission.item'); requireValue(input.demand_item_id === row.demand_item_id,'offer.item.reference_mismatch');
    requireValue(scaled(row.offered_milli,'offer.quantity') === decimal(input.quantity,9,3,'submission.quantity',true),'offer.quantity_mismatch');
    const price = scaled(row.unit_price_cents,'offer.price',true); requireValue(price === (input.unit_price == null ? null : decimal(input.unit_price,10,2,'submission.price')),'offer.price_mismatch');
  }); unique(ids,'offer.items'); unique(refs,'offer.references'); history(value.history,true);
  return structuredClone(raw) as OfferDetailRead;
}
