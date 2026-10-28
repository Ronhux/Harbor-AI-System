import { useEffect, useId, useRef, useState } from 'react';
import type { DemandListRead, OfferListRead } from '../../contracts/institutionalDemandReads';
import type { DetailViewer } from './demandDetailFixtures';
import { LatestRead, readDemandPage, readOfferPage, readScenarios, type ReadScenario } from './institutionalReadMock';
import { Button } from '../ui/button';

/** Embedded only in the existing unrouted workbench. Previous is a local cursor stack. */
export default function PaginatedReadPreview({viewer,demandId,onSelect}: {viewer:DetailViewer;demandId:number;onSelect:(id:number)=>void}) {
  const id=useId(), gate=useRef(new LatestRead());
  const [kind,setKind]=useState<'demands'|'offers'>('demands'), [scenario,setScenario]=useState<ReadScenario>('valid');
  const [page,setPage]=useState<DemandListRead|OfferListRead|null>(null), [cursors,setCursors]=useState([0]);
  const [busy,setBusy]=useState(false), [error,setError]=useState('');
  const restricted=viewer === 'administrator' || (kind === 'offers' && viewer !== 'buyer_owner');
  const clear=() => { gate.current.cancel(); setPage(null);setCursors([0]);setBusy(false);setError(''); };
  useEffect(() => { clear(); return () => gate.current.cancel(); },[viewer,demandId]);
  const load=async (next:number[]) => {
    const request=gate.current.begin();setBusy(true);setError('');setPage(null);
    try {
      const after=next[next.length-1];
      const result=kind === 'demands' ? await readDemandPage(viewer,after,scenario,request.signal) : await readOfferPage(viewer,demandId,after,scenario,request.signal);
      if (!gate.current.current(request.generation)) return;
      setPage(result);setCursors(next);setBusy(false);
    } catch (failure) {
      if (!gate.current.current(request.generation)) return;
      setBusy(false);setError(failure instanceof Error ? failure.message : 'Synthetic response unavailable.');
    }
  };
  return <section aria-label="Paginated synthetic reads" aria-busy={busy} className="min-w-0 space-y-3 rounded border p-3">
    <h3 className="font-semibold">Paginated reads — synthetic only</h3>
    <p>Ascending ID cursor; maximum 50 rows. Previous uses local cursor history. Only demand 101 and 102 have detail fixtures. No real records or requests.</p>
    <div className="grid gap-3 sm:grid-cols-2">
      <label htmlFor={`${id}-kind`}>Synthetic read collection<select id={`${id}-kind`} className="block min-h-11 w-full rounded border p-2" value={kind} onChange={e=>{clear();setKind(e.target.value as typeof kind);}}><option value="demands">{viewer==='buyer_owner'?'Owned demands':'Open demand opportunities'}</option><option value="offers">Buyer-owned received offers</option></select></label>
      <label htmlFor={`${id}-scenario`}>Synthetic read response<select id={`${id}-scenario`} className="block min-h-11 w-full rounded border p-2" value={scenario} onChange={e=>{clear();setScenario(e.target.value as ReadScenario);}}>{Object.entries(readScenarios).map(([key,label])=><option key={key} value={key}>{label}</option>)}</select></label>
    </div>
    {restricted ? <p role="alert">Restricted: this collection has no reviewed read permission for this viewer. Suppliers use their selected own-offer detail, not the received-offer list.</p> : <>
      <div className="flex flex-wrap gap-2">
        <Button data-fixture-action="read-page" variant="outline" disabled={busy} onClick={()=>void load([0])}>Refresh synthetic reads</Button>
        <Button data-fixture-action="read-page" variant="outline" disabled={busy||cursors.length<2} onClick={()=>void load(cursors.slice(0,-1))}>Previous synthetic page</Button>
        <Button data-fixture-action="read-page" variant="outline" disabled={busy||!page?.next_cursor} onClick={()=>void load([...cursors,page!.next_cursor!])}>Next synthetic page</Button>
        <Button data-fixture-action="read-page" variant="outline" disabled={!busy} onClick={()=>{gate.current.cancel();setBusy(false);setError('Synthetic read cancelled; notes and drafts retained.');}}>Cancel synthetic read</Button>
      </div>
      {busy&&<p role="status">Loading validated synthetic page.</p>}{error&&<p role="alert">{error}</p>}
      {page&&<><p role="status">{page.data.length} synthetic rows; cursor after {cursors[cursors.length-1]}. {page.next_cursor===null?'End of results.':'More results available.'}</p>
        {page.data.length===0?<p role="status">No synthetic results.</p>:<ul className="max-h-64 space-y-2 overflow-y-auto">{page.data.map(row=><li key={row.id} className="flex flex-wrap items-center gap-2">{'title' in row?row.title:`Synthetic offer ${row.id}`} — {row.state}; version {row.version}{kind==='demands'&&[101,102].includes(row.id)&&<Button variant="outline" data-fixture-action="select-read" onClick={()=>onSelect(row.id)}>Inspect synthetic demand {row.id}</Button>}</li>)}</ul>}</>}
      {!page&&!busy&&!error&&<p role="status">No page loaded. Use the local refresh control.</p>}
    </>}
    <p>Histories are validated with details, capped at 100; no history cursor is defined. FCA references confer no authority or procurement eligibility.</p>
  </section>;
}
