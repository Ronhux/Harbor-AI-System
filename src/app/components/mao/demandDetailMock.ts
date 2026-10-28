import type { DemandDetailRead, OfferDetailRead } from '../../contracts/institutionalDemandReads';
import { canViewOffer, demandFixture, offerFixture, type DetailViewer } from './demandDetailFixtures';

export type DetailScenario = 'demand_updated' | 'offer_updated' | 'quantity' | 'commodity_revised' | 'superseded' | 'missing' | 'permission' | 'duplicate' | 'network' | 'server' | 'empty_history' | 'incomplete';
export type DetailOutcome = { kind: 'comparison'; demand: DemandDetailRead; offer: OfferDetailRead | null; message: string } | { kind: 'unavailable'; status: 403 | 404; message: string } | { kind: 'conflict'; status: 409; message: string } | { kind: 'error'; message: string } | { kind: 'gap'; message: string };
/** Local read/response fixtures; never calls or mutates an API. */
export async function detailMock(scenario: DetailScenario, id: number, demandId = 101, viewer: DetailViewer = 'buyer_owner'): Promise<DetailOutcome> {
  await new Promise(resolve => setTimeout(resolve, 200));
  const demand = demandFixture(demandId), offer = demandId === 101 ? offerFixture(id) : null;
  if (!['buyer_owner', 'producer_owner', 'fca_owner', 'administrator', 'unrelated_producer'].includes(viewer)) return { kind: 'unavailable', status: 403, message: 'Unrecognized synthetic viewer context.' };
  if (viewer === 'administrator') return { kind: 'unavailable', status: 403, message: 'No general administrator detail-read permission is established by these backend methods.' };
  if (![101, 102].includes(demandId)) return { kind: 'unavailable', status: 404, message: 'No matching synthetic demand fixture.' };
  if (offer && !canViewOffer(viewer, id)) return { kind: 'unavailable', status: 403, message: 'The selected synthetic offer is not owned by this supplier fixture.' };
  if (!['demand_updated', 'offer_updated', 'quantity', 'commodity_revised', 'superseded', 'missing', 'permission', 'duplicate', 'network', 'server', 'empty_history', 'incomplete'].includes(scenario)) return { kind: 'error', message: 'Unrecognized synthetic detail scenario; no current record replaced.' };
  if (scenario === 'missing') return { kind: 'unavailable', status: 404, message: 'Synthetic record is unavailable. No record was actually deleted.' };
  if (scenario === 'permission') return { kind: 'unavailable', status: 403, message: 'Synthetic authorization changed. Record content is hidden; no real permission changed.' };
  if (scenario === 'duplicate') return { kind: 'conflict', status: 409, message: 'Synthetic stale/duplicate action conflict. No action was attempted or automatically retried.' };
  if (scenario === 'quantity') return { kind: 'conflict', status: 409, message: 'Synthetic remaining quantity conflict. The loaded snapshot and local notes are retained.' };
  if (scenario === 'network' || scenario === 'server') return { kind: 'error', message: scenario === 'network' ? 'Simulated network failure; loaded snapshot retained.' : 'Simulated unexpected server response; no automatic replacement.' };
  if (scenario === 'superseded') return { kind: 'gap', message: 'Superseded-offer linkage/status is not in the reviewed read contract. No replacement offer or new state is fabricated.' };
  if (scenario === 'incomplete') return { kind: 'gap', message: 'Incomplete record example: required detail fields are missing. No partial record is treated as current authoritative data.' };
  if (scenario === 'offer_updated' && !offer) return { kind: 'gap', message: 'No selected offer is available for a version comparison.' };
  if (scenario === 'offer_updated' && offer) {
    if (offer.offer.mao_state === 'provisional') return { kind: 'conflict', status: 409, message: 'Synthetic provisional-offer revision conflict; no unreviewed replacement state is fabricated.' };
    offer.offer.mao_version = 3; offer.offer.updated_at = '2030-01-03 13:00';
    offer.history.unshift({ action: 'review', previous_state: 'under_review', new_state: 'under_review', created_at: '2030-01-03 13:00', reason: 'Synthetic subsequent review example' });
  }
  else if (scenario === 'empty_history') { demand.history = []; if (offer) offer.history = []; }
  else if (scenario === 'demand_updated' || scenario === 'commodity_revised') {
    demand.demand.mao_version = 4; demand.demand.updated_at = '2030-01-03 13:00';
    if (scenario === 'commodity_revised') {
      if (demandId === 101) return { kind: 'conflict', status: 409, message: 'Offers exist: preserve this revision and create a separately reviewed replacement' };
      if (viewer !== 'buyer_owner') return { kind: 'unavailable', status: 403, message: 'A revised draft is not readable by a supplier under the reviewed demand-detail contract.' };
      demand.demand.mao_state = 'draft'; demand.items = [{ ...demand.items[0], id: 303, requested: '12.125', provisional: '0.000', remaining: '12.125' }]; demand.posting.items = [{ ...demand.posting.items[0], quantity: '12.125' }];
      demand.history.unshift({ action: 'revised', previous_state: 'open', new_state: 'draft', revision: 4, created_at: '2030-01-03 13:00' });
    } else { demand.demand.mao_state = 'under_review'; demand.history.unshift({ action: 'state_changed', previous_state: 'open', new_state: 'under_review', revision: 4, created_at: '2030-01-03 13:00' }); }
  }
  return { kind: 'comparison', demand, offer, message: 'Synthetic newer/read fixture available. Compare before explicitly replacing the loaded snapshot.' };
}
