import type { InstitutionalDemandInput, InstitutionalDemandState, InstitutionalSupplyInput, SupplyOfferState } from './institutionalDemands';

/** Explicit PreparedMaoResponse read projections. Routes and real API execution remain gated. */
export interface DemandDetailRead {
  demand: { request_id: number; mao_state: InstitutionalDemandState; mao_version: number; created_at: string | null; updated_at: string | null };
  posting: InstitutionalDemandInput & { requesting_institution: string };
  items: { id: number; commodity: string; category: string; variety: string | null; unit: string; specifications: string; requested: string; provisional: string; remaining: string; delivered: string }[];
  history: { action: string; previous_state: string | null; new_state: string | null; revision: number; created_at: string | null }[];
}
export interface OfferDetailRead {
  offer: { id: number; demand_request_id: number; mao_state: SupplyOfferState; mao_version: number; created_at: string | null; updated_at: string | null };
  submission: InstitutionalSupplyInput;
  items: { id: number; demand_item_id: number; offered_milli: number | string; unit_price_cents: number | string | null }[];
  history: { action: string; previous_state: string | null; new_state: string | null; created_at: string | null; reason: string | null }[];
}
export interface OfferListRead { data: { id: number; state: SupplyOfferState; version: number }[]; next_cursor: number | null }
/** Inactive create/revise/state and offer/review/allocation responses use these ordinary records, including replay. */
export type DemandRecordRead = DemandDetailRead['demand'];
export type OfferRecordRead = OfferDetailRead['offer'];
/** Prepared list(): ascending id, after >= 0, fixed maximum 50 rows. */
export interface DemandListRead { data: { id: number; title: string; requesting_institution: string; posting_type: InstitutionalDemandInput['posting_type']; state: InstitutionalDemandState; version: number; offer_deadline: string }[]; next_cursor: number | null }
