import {useEffect,useId,useRef,useState} from 'react';
import {Button} from '../ui/button';
import {Textarea} from '../ui/textarea';
import {publicationMock,publicationScenarios,type PublicationOutcome,type PublicationScenario} from './publicationReadMock';
/** Inactive owner/status contract view. Reviewer context never receives private draft data. */
export default function PublicationReadPreview({reviewerContext=false}:{reviewerContext?:boolean}){
 const id=useId(),ticket=useRef(0);const [scenario,setScenario]=useState<PublicationScenario>(reviewerContext?'unknown_reviewer':'owner_draft');
 const [outcome,setOutcome]=useState<PublicationOutcome|null>(null),[busy,setBusy]=useState(false),[notes,setNotes]=useState('');
 useEffect(()=>()=>{ticket.current++;},[]);
 const invalidate=()=>{ticket.current++;setBusy(false);setOutcome(null);};
 const load=async()=>{const key=++ticket.current;setBusy(true);setOutcome(null);const next=await publicationMock(scenario,reviewerContext);if(key!==ticket.current)return;setBusy(false);setOutcome(next);};
 return <section aria-label="Publication read and capability preview" aria-busy={busy} className="min-w-0 space-y-3 rounded border bg-card p-4">
   <h2 className="text-lg font-semibold">Publication status and capabilities — synthetic only</h2>
   <p>Draft ownership, review authority and publication authority are different. These read-only examples execute no operation. Configured reviewer status supplies no established private-draft read entitlement.</p>
   <label htmlFor={`${id}-scenario`}>Synthetic publication read<select id={`${id}-scenario`} className="block min-h-11 w-full rounded border p-2" value={scenario} onChange={e=>{invalidate();setScenario(e.target.value as PublicationScenario);}}>{Object.entries(publicationScenarios).map(([key,label])=><option key={key} value={key}>{label}</option>)}</select></label>
   <Button variant="outline" data-fixture-action="publication-read" disabled={busy} onClick={()=>void load()}>Load synthetic publication read</Button>
   {busy&&<p role="status">Loading synthetic owner/status projection; no API request.</p>}
   {outcome?.kind==='error'&&<p role="alert">{outcome.message}</p>}
   {outcome?.kind==='record'&&(outcome.record.scope==='restricted'?<p role="alert">Restricted: no private draft, version or history returned. Reviewer read/publication rules remain unestablished.</p>:<section aria-label="Owned publication status" className="space-y-2">
     <h3 className="font-semibold">Own demand {outcome.record.demand!.demand.request_id}: {outcome.record.demand!.demand.mao_state}</h3>
     <p>Loaded version {outcome.record.version.current}; expected {outcome.record.version.expected??'not supplied'}; matches: {outcome.record.version.matches===null?'not established':String(outcome.record.version.matches)}.</p>
     <p>Institution: {outcome.record.demand!.posting.requesting_institution}. {outcome.record.demand!.posting.title}</p>
     <ul>{Object.entries(outcome.record.capabilities).map(([key,value])=><li key={key}>{key.replaceAll('_',' ')}: {value?'supported by prepared owner preconditions':'unavailable'}</li>)}</ul>
     <p>Execution enabled: false. Capabilities describe existing prepared checks, not permission to submit or publish. Future operations must recheck identity, ownership, state/version, audit key and publication requirements.</p>
     <h4 className="font-semibold">Unverified publication requirements</h4><ul>{outcome.record.blockers.map(code=><li key={code}>{code.replaceAll('_',' ')}</li>)}</ul>
     <h4 className="font-semibold">Permitted owner history — demonstration only</h4><ul>{outcome.record.demand!.history.map((row,i)=><li key={i}>{row.action}: {row.previous_state??'none'} to {row.new_state??'none'}; version {row.revision}; {row.created_at??'not supplied'}</li>)}</ul>
     <p>No confidential review reasons, documents, registry data or reviewer identity returned. Pending review is not approval. No rejection/clarification decision is invented.</p>
   </section>)}
   <label htmlFor={`${id}-notes`}>Local publication comparison notes<Textarea id={`${id}-notes`} maxLength={1000} value={notes} onChange={e=>{invalidate();setNotes(e.target.value);}}/></label>
   <div className="flex flex-wrap gap-2"><Button disabled>Request publication review</Button><Button disabled>Review publication request</Button><Button disabled>Publish demand</Button><Button disabled>Cancel publication request</Button><Button disabled>Allocate supply</Button></div>
 </section>;
}
