import type { InstitutionalDemandInput, InstitutionalDemandItem } from '../../contracts/institutionalDemands';
import { isDraftDecimal } from './previewModel';

export const demandUnits = ['kg', 'g', 'litre', 'piece', 'sack', 'crate'] as const;
export const commodityExamples = [
  { id: 'synthetic-cabbage', name: 'Synthetic cabbage', category: 'Vegetables' },
  { id: 'synthetic-fish', name: 'Synthetic fish', category: 'Fishery' },
] as const;
export type DraftRow = { key: number; selection: string; item: InstitutionalDemandItem };
export type FieldErrors = Record<string, string[]>;
export const blankCommodity = (): InstitutionalDemandItem => ({ commodity: '', category: '', quantity: '', unit: 'kg', specifications: '' });
export const blankPosting = (): InstitutionalDemandInput => ({ title: '', description: '', purpose: '', program: 'other', posting_type: 'market_inquiry', items: [], budget_kind: 'none', currency: 'PHP', delivery_location: '', delivery_start: '', delivery_end: '', offer_deadline: '', partial_supply: true, required_documents: [], eligible_supplier_type: 'both', eligibility_requirements: '', rsbsa_required: false });

/** Removes empty optional numeric fields; never includes UI row keys, actors or statuses. */
export function draftPosting(metadata: InstitutionalDemandInput, rows: readonly DraftRow[]): InstitutionalDemandInput {
  const posting = { ...metadata, items: rows.map(({ item }) => {
    const clean = { ...item, commodity: item.commodity.trim(), category: item.category.trim(), variety: item.variety?.trim() };
    if (!clean.max_unit_price) delete clean.max_unit_price;
    if (!clean.variety) delete clean.variety;
    return clean;
  }) };
  if (!posting.budget_amount) delete posting.budget_amount;
  if (!posting.procurement_reference) delete posting.procurement_reference;
  return posting;
}
function localDate(value: string): number {
  const match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/.exec(value);
  if (!match) return NaN;
  const date = new Date(value), parts = match.slice(1).map(Number);
  return date.getFullYear() === parts[0] && date.getMonth() + 1 === parts[1] && date.getDate() === parts[2] && date.getHours() === parts[3] && date.getMinutes() === parts[4] ? date.getTime() : NaN;
}
/** Client checks only. Backend remains authoritative for access, dates and business rules. */
export function validatePosting(posting: InstitutionalDemandInput, rows: readonly DraftRow[], now: number): FieldErrors {
  const errors: FieldErrors = {};
  const add = (key: string, text: string) => { (errors[key] ??= []).push(text); };
  const textLimits = { title: 160, description: 4000, purpose: 2000, delivery_location: 2000, eligibility_requirements: 4000 } as const;
  for (const [key, max] of Object.entries(textLimits) as [keyof typeof textLimits, number][]) {
    if (!posting[key].trim()) add(key, 'This field is required.');
    if (posting[key].length > max) add(key, `Use no more than ${max} characters.`);
  }
  if (!['market_inquiry', 'supply_requirement', 'official_procurement_reference'].includes(posting.posting_type)) add('posting_type', 'Choose a supported posting type.');
  if (posting.program && !['sagip_saka', 'kadiwa', 'other'].includes(posting.program)) add('program', 'Choose a supported program.');
  if (!['individual', 'fca', 'both'].includes(posting.eligible_supplier_type)) add('eligible_supplier_type', 'Choose supported supplier types.');
  if (posting.currency !== 'PHP') add('currency', 'Only PHP is supported.');
  if (typeof posting.partial_supply !== 'boolean') add('partial_supply', 'Choose a partial-supply setting.');
  if (typeof posting.rsbsa_required !== 'boolean') add('rsbsa_required', 'Choose an RSBSA requirement setting.');
  if (posting.posting_type === 'official_procurement_reference' && !posting.procurement_reference?.trim()) add('procurement_reference', 'An official reference is required.');
  if ((posting.procurement_reference?.length ?? 0) > 255) add('procurement_reference', 'Use no more than 255 characters.');
  if (!['none', 'indicative', 'abc'].includes(posting.budget_kind)) add('budget_kind', 'Choose a supported budget type.');
  if (posting.budget_kind === 'none' ? Boolean(posting.budget_amount) : !isDraftDecimal(posting.budget_amount ?? '', 12, 2, true)) add('budget_amount', 'Use a positive budget with up to 12 whole and 2 decimal digits, or no amount for no budget.');
  if (posting.budget_kind === 'abc' && posting.posting_type !== 'official_procurement_reference') add('budget_kind', 'ABC is supported only for an official procurement reference.');
  const deadline = localDate(posting.offer_deadline), start = localDate(posting.delivery_start), end = localDate(posting.delivery_end);
  if (!Number.isFinite(deadline) || deadline <= now) add('offer_deadline', 'Choose a valid future offer deadline.');
  if (!Number.isFinite(start) || !Number.isFinite(deadline) || start < deadline) add('delivery_start', 'Delivery must begin at or after the offer deadline.');
  if (!Number.isFinite(end) || !Number.isFinite(start) || end < start) add('delivery_end', 'Delivery must end at or after its start.');
  if (posting.items.length < 1 || posting.items.length > 20) add('items', 'Use between 1 and 20 commodities.');
  const seen = new Set<string>();
  posting.items.forEach((item, index) => {
    const path = (key: string) => `items.${index}.${key}`;
    if (!rows[index] || (rows[index].selection !== 'custom' && !commodityExamples.some(example => example.id === rows[index].selection))) add(path('commodity'), 'Choose a supported synthetic example or enter a custom commodity.');
    for (const [key, max] of [['commodity', 160], ['category', 80], ['specifications', 2000]] as const) {
      if (!item[key].trim()) add(path(key), 'This field is required.');
      if (item[key].length > max) add(path(key), `Use no more than ${max} characters.`);
    }
    if ((item.variety?.length ?? 0) > 120) add(path('variety'), 'Use no more than 120 characters.');
    if (!isDraftDecimal(item.quantity, 9, 3, true)) add(path('quantity'), 'Quantity must be positive, with up to 9 whole and 3 decimal digits.');
    if (!demandUnits.includes(item.unit)) add(path('unit'), 'Choose a supported unit.');
    if (item.max_unit_price && !isDraftDecimal(item.max_unit_price, 10, 2)) add(path('max_unit_price'), 'Use up to 10 whole and 2 decimal digits for price.');
    const key = [item.commodity, item.category, item.variety ?? ''].map(value => value.trim().toLowerCase()).join('|');
    if (seen.has(key)) add(path('commodity'), 'This commodity, category and variety already appear.');
    seen.add(key);
  });
  if (posting.required_documents.length > 20 || posting.required_documents.some(value => !value.trim() || value.length > 160) || new Set(posting.required_documents).size !== posting.required_documents.length) add('required_documents', 'Use up to 20 distinct, non-empty document descriptions, at most 160 characters each.');
  return errors;
}
