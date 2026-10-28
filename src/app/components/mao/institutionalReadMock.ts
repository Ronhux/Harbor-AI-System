import type { DemandListRead, OfferListRead } from '../../contracts/institutionalDemandReads';
import { demandFixture, offerFixture, projectDemand, type DetailViewer } from './demandDetailFixtures';
import { detailMock, type DetailScenario } from './demandDetailMock';
import { READ_PAGE_LIMIT, validateDemandDetail, validateDemandList, validateOfferDetail, validateOfferList } from './institutionalReadValidation';

export type ReadScenario = 'valid' | 'empty' | 'invalid' | 'missing_cursor' | 'duplicate' | 'status' | 'quantity' | 'missing' | 'permission' | 'version' | 'network' | 'server' | 'slow';
export const readScenarios: Record<ReadScenario,string> = { valid:'Valid page', empty:'Empty page', invalid:'Invalid structure', missing_cursor:'Missing cursor', duplicate:'Duplicate identities', status:'Unsupported status', quantity:'Inconsistent detail quantities', missing:'Unavailable record', permission:'Permission changed', version:'Version conflict', network:'Network interruption', server:'Unexpected server error', slow:'Delayed response' };
export class MockReadError extends Error {
  constructor(public readonly kind: 'unavailable' | 'permission' | 'conflict' | 'network' | 'server' | 'cancelled', message: string) { super(message); }
}
/** Local-only asynchronous boundary. No HTTP or persistence implementation exists. */
async function wait(scenario: ReadScenario, signal?: AbortSignal) {
  if (signal?.aborted) throw new MockReadError('cancelled','Synthetic read cancelled.');
  await new Promise<void>((resolve,reject) => {
    const cancel = () => { clearTimeout(timer); signal?.removeEventListener('abort',cancel); reject(new MockReadError('cancelled','Synthetic read cancelled.')); };
    const timer = setTimeout(() => { signal?.removeEventListener('abort',cancel); resolve(); },scenario === 'slow' ? 400 : 100);
    signal?.addEventListener('abort',cancel,{once:true});
  });
  const failures = { missing:'unavailable',permission:'permission',version:'conflict',network:'network',server:'server' } as const;
  if (scenario in failures) throw new MockReadError(failures[scenario as keyof typeof failures],`Synthetic ${scenario} response; local drafts and notes retained.`);
  if (!(scenario in readScenarios)) throw new MockReadError('server','Unsupported synthetic response scenario.');
}
function corruptPage(value: DemandListRead | OfferListRead, scenario: ReadScenario): unknown {
  if (scenario === 'quantity') return {...value,data:[{...value.data[0],remaining:'99.000'}]}; // No quantities belong in the prepared list contract.
  if (scenario === 'invalid') return {data:'not an array',next_cursor:null};
  if (scenario === 'missing_cursor') return {data:value.data};
  if (scenario === 'duplicate') return {...value,data:[value.data[0],value.data[0]]};
  if (scenario === 'status') return {...value,data:[{...value.data[0],state:'superseded'}],next_cursor:null};
  return value;
}
export async function readDemandPage(viewer: DetailViewer, after = 0, scenario: ReadScenario = 'valid', signal?: AbortSignal): Promise<DemandListRead> {
  await wait(scenario,signal);
  // 52 entirely synthetic owned/browsable records exercise the actual fixed 50-row boundary.
  const all = Array.from({length:52},(_,i) => ({id:101+i,title:`Synthetic requirement ${101+i}`,requesting_institution:'Synthetic institution',posting_type:'market_inquiry' as const,state:'open' as const,version:3,offer_deadline:'2030-01-10T09:00'}));
  const remaining = scenario === 'empty' ? [] : all.filter(row => row.id > after), data = remaining.slice(0,READ_PAGE_LIMIT);
  return validateDemandList(corruptPage({data,next_cursor:remaining.length > READ_PAGE_LIMIT ? data[data.length-1].id : null},scenario),viewer,after);
}
export async function readOfferPage(viewer: DetailViewer, demandId: number, after = 0, scenario: ReadScenario = 'valid', signal?: AbortSignal): Promise<OfferListRead> {
  await wait(scenario,signal);
  if (![101,102].includes(demandId)) throw new MockReadError('unavailable','No selected demand fixture.');
  const all = demandId === 101 ? [201,202].map(id => { const o=offerFixture(id)!; return {id,state:o.offer.mao_state,version:o.offer.mao_version}; }) : [];
  const data = scenario === 'empty' ? [] : all.filter(row => row.id > after);
  return validateOfferList(corruptPage({data,next_cursor:null},scenario),viewer,after);
}
export async function readDemandDetail(viewer: DetailViewer, id: number, scenario: ReadScenario = 'valid', signal?: AbortSignal) {
  await wait(scenario,signal);
  if (![101,102].includes(id)) throw new MockReadError('unavailable','List-only synthetic row; no detail fixture prepared.');
  const value = projectDemand(viewer,demandFixture(id));
  if (!value) throw new MockReadError('permission','No permitted demand detail.');
  if (scenario === 'quantity') value.items[0].remaining = '99.000';
  return validateDemandDetail(scenario === 'invalid' ? {} : value,viewer,id);
}
export async function readOfferDetail(viewer: DetailViewer, id: number, demandId: number, signal?: AbortSignal) {
  await wait('valid',signal); return validateOfferDetail(offerFixture(id),viewer,id,demandId);
}
/** Existing comparison fixtures pass through the same runtime boundary and role projection. */
export async function readDetailComparison(scenario: DetailScenario, id: number, demandId: number, viewer: DetailViewer) {
  const outcome = await detailMock(scenario,id,demandId,viewer);
  if (outcome.kind !== 'comparison') return outcome;
  try {
    const projected = projectDemand(viewer,outcome.demand);
    return {...outcome,demand:validateDemandDetail(projected,viewer,demandId),offer:outcome.offer ? validateOfferDetail(outcome.offer,viewer,id,demandId) : null};
  } catch { return {kind:'error' as const,message:'Synthetic detail response failed runtime validation; loaded snapshot and notes retained.'}; }
}
/** Used in React and mocked tests: every new selection/read invalidates prior completion. */
export class LatestRead {
  private generation = 0;
  private controller: AbortController | null = null;
  begin() { this.cancel(); this.controller = new AbortController(); return {generation:this.generation,signal:this.controller.signal}; }
  current(generation: number) { return generation === this.generation; }
  cancel() { this.generation++; this.controller?.abort(); this.controller = null; }
}
