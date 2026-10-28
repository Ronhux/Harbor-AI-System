import type { DemandDetailRead, OfferDetailRead } from '../../contracts/institutionalDemandReads';
import { blankPosting } from './institutionalDraftModel';

export type DetailViewer = 'buyer_owner' | 'producer_owner' | 'fca_owner' | 'administrator' | 'unrelated_producer';
export const viewerLabels: Record<DetailViewer, string> = { buyer_owner: 'Institutional buyer owner example', producer_owner: 'Producer own-offer example', fca_owner: 'FCA producer own-offer example', administrator: 'Administrator monitoring example', unrelated_producer: 'Producer with no ownership example' };
export function canViewOffer(viewer: DetailViewer, id: number): boolean { return [201, 202].includes(id) && (viewer === 'buyer_owner' || (viewer === 'producer_owner' && id === 201) || (viewer === 'fca_owner' && id === 202)); }
export function availableOfferIds(viewer: DetailViewer, demandId = 101): number[] { return demandId !== 101 ? [] : viewer === 'buyer_owner' ? [201, 202] : viewer === 'producer_owner' ? [201] : viewer === 'fca_owner' ? [202] : []; }
export function projectDemand(viewer: DetailViewer, record: DemandDetailRead): DemandDetailRead | null {
  if (!['buyer_owner', 'producer_owner', 'fca_owner', 'unrelated_producer'].includes(viewer)) return null;
  if (viewer !== 'buyer_owner' && !['open', 'under_review'].includes(record.demand.mao_state)) return null;
  return { ...record, history: viewer === 'buyer_owner' ? record.history : [] };
}
export function formatScaled(value: number | string | null, scale: number): string {
  if (![2, 3].includes(scale)) return 'Unavailable';
  if (value === null) return 'Not quoted';
  if (typeof value === 'number' && (!Number.isSafeInteger(value) || value < 0)) return 'Unavailable';
  const text = String(value);
  if (!/^\d{1,15}$/.test(text)) return 'Unavailable';
  const padded = BigInt(text).toString().padStart(scale + 1, '0');
  return `${padded.slice(0, -scale)}.${padded.slice(-scale)}`;
}
export function demandFixture(id = 101): DemandDetailRead {
  const record: DemandDetailRead = {
    demand: { request_id: id, mao_state: 'open', mao_version: 3, created_at: '2030-01-02 09:00', updated_at: '2030-01-03 10:00' },
    posting: { ...blankPosting(), requesting_institution: 'Synthetic institution', title: 'Synthetic multi-commodity requirement', description: 'Synthetic coordination only', purpose: 'Synthetic planning', program: 'other', delivery_location: 'Synthetic hall', offer_deadline: '2030-01-10T09:00', delivery_start: '2030-01-11T09:00', delivery_end: '2030-01-12T17:00', eligibility_requirements: 'Server must recheck supplier eligibility', items: [
      { commodity: 'Synthetic cabbage', category: 'Vegetables', variety: 'Green', quantity: '10.125', unit: 'kg', specifications: 'Synthetic grade', max_unit_price: '40.10' },
      { commodity: 'Synthetic fish', category: 'Fishery', quantity: '5.000', unit: 'kg', specifications: 'Synthetic freshness requirement' },
    ] },
    items: [
      { id: 301, commodity: 'Synthetic cabbage', category: 'Vegetables', variety: 'Green', unit: 'kg', specifications: 'Synthetic grade', requested: '10.125', provisional: '6.125', remaining: '4.000', delivered: '0.000' },
      { id: 302, commodity: 'Synthetic fish', category: 'Fishery', variety: null, unit: 'kg', specifications: 'Synthetic freshness requirement', requested: '5.000', provisional: '0.000', remaining: '5.000', delivered: '0.000' },
    ],
    history: [
      { action: 'state_changed', previous_state: 'pending_review', new_state: 'open', revision: 3, created_at: '2030-01-03 10:00' },
      { action: 'state_changed', previous_state: 'draft', new_state: 'pending_review', revision: 2, created_at: '2030-01-02 11:00' },
      { action: 'created', previous_state: null, new_state: 'draft', revision: 1, created_at: '2030-01-02 09:00' },
    ],
  };
  if (id === 102) record.items = record.items.map(item => ({ ...item, provisional: '0.000', remaining: item.requested }));
  return record;
}
export function offerFixture(id: number): OfferDetailRead | null {
  if (![201, 202].includes(id)) return null;
  return {
    offer: { id, demand_request_id: 101, mao_state: id === 202 ? 'provisional' : 'under_review', mao_version: id === 202 ? 3 : 2, created_at: '2030-01-03 11:00', updated_at: '2030-01-03 12:00' },
    submission: { demand_version: 3, supplier_type: id === 202 ? 'fca' : 'individual', ...(id === 202 ? { representation_id: 501 } : {}), items: [{ demand_item_id: 301, quantity: id === 202 ? '6.125' : '2.500', unit_price: '40.10' }], availability_date: '2030-01-11', delivery_information: 'Synthetic delivery arrangement', eligibility_declaration: 'Synthetic declaration; not verified eligibility' },
    items: [{ id: id + 400, demand_item_id: 301, offered_milli: id === 202 ? 6125 : 2500, unit_price_cents: 4010 }],
    history: [...(id === 202 ? [{ action: 'provisional_allocation', previous_state: 'under_review', new_state: 'provisional', created_at: '2030-01-03 12:00', reason: 'Synthetic allocation example; not an award' }] : []), { action: 'review', previous_state: 'submitted', new_state: 'under_review', created_at: '2030-01-03 12:00', reason: 'Synthetic review reason' }, { action: 'offer_submitted', previous_state: null, new_state: 'submitted', created_at: '2030-01-03 11:00', reason: null }],
  };
}
