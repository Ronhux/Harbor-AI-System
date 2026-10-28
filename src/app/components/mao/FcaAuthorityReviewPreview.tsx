import { useEffect, useId, useRef, useState } from 'react';
import { Button } from '../ui/button';
import { Textarea } from '../ui/textarea';
import { applicationFixture, applicationIds, authorityMock, authorityScenarios, canReadApplication, evidenceContext, fcaViewers, validateApplication, type AuthorityOutcome, type AuthorityScenario, type FcaViewer } from './fcaAuthorityModel';

/** Synthetic read-only authority panel; all decisions and document operations stay disabled. */
export default function FcaAuthorityReviewPreview({initialViewer='applicant_owner'}:{initialViewer?:FcaViewer}) {
  const prefix=useId(), generation=useRef(0);
  const [viewer,setViewer]=useState(initialViewer),[selected,setSelected]=useState(301),[snapshot,setSnapshot]=useState(()=>applicationFixture());
  const [scenario,setScenario]=useState<AuthorityScenario>('pending'),[outcome,setOutcome]=useState<AuthorityOutcome|null>(null),[busy,setBusy]=useState(false);
  const [notes,setNotes]=useState<Record<string,string>>({});
  useEffect(()=>()=>{generation.current++;},[]);
  const invalidate=()=>{generation.current++;setBusy(false);setOutcome(null);};
  const select=(nextViewer:FcaViewer,nextId:number)=>{invalidate();setViewer(nextViewer);setSelected(nextId);setSnapshot(applicationFixture(nextId));};
  const read=async()=>{const key=++generation.current;setBusy(true);setOutcome(null);const result=await authorityMock(scenario,viewer,selected);if(key!==generation.current)return;setBusy(false);setOutcome(result);};
  const permitted=canReadApplication(viewer,selected)&&outcome?.kind!=='restricted'&&outcome?.kind!=='unavailable'&&outcome?.kind!=='empty';
  const record=permitted?validateApplication(snapshot,viewer,selected):null;
  const context=record?evidenceContext(record.status):null,notesKey=`${viewer}:${selected}`;
  return <section aria-label="FCA authority review workbench" className="min-w-0 space-y-4 rounded border p-4">
    <h3 className="text-lg font-semibold">FCA authority review — synthetic only</h3>
    <p>No identity, document, directory import or real review is loaded. Example viewers never authenticate or change roles.</p>
    <div className="grid gap-3 sm:grid-cols-2">
      <label htmlFor={`${prefix}-viewer`}>Synthetic FCA viewer<select id={`${prefix}-viewer`} className="block min-h-11 w-full rounded border bg-card p-2" value={viewer} onChange={e=>{const next=e.target.value as FcaViewer;select(next,applicationIds(next)[0]??301);}}>{Object.entries(fcaViewers).map(([key,label])=><option key={key} value={key}>{label}</option>)}</select></label>
      <label htmlFor={`${prefix}-application`}>Selected synthetic FCA application<select id={`${prefix}-application`} className="block min-h-11 w-full rounded border bg-card p-2" disabled={!applicationIds(viewer).length} value={selected} onChange={e=>select(viewer,Number(e.target.value))}>{applicationIds(viewer).length?applicationIds(viewer).map(id=><option key={id} value={id}>Application {id} — {applicationFixture(id).status}</option>):<option value={301}>No permitted record</option>}</select></label>
    </div>
    {!record?<p role="alert">{outcome?.message??'Restricted: active assigned reviewer or approved producer ownership is required. Application and evidence information are withheld.'}</p>:<section aria-label="Selected synthetic FCA record" className="space-y-3">
      <h4 className="font-semibold">Application {record.id} — authority {record.status}</h4>
      <dl className="grid gap-3 text-sm sm:grid-cols-2">
        <div><dt className="font-semibold">Applicant identity</dt><dd>Owner-bound synthetic record. Name and account identity are not returned by the reviewed detail response.</dd></div>
        <div><dt className="font-semibold">Association reference</dt><dd>FCA {record.fca_id}. Selecting a directory entry proves no representative authority.</dd></div>
        <div><dt className="font-semibold">Association information</dt><dd>Name/address are directory fields, not application-detail fields. Reviewer directory lookup is not defined; no official association information is invented.</dd></div>
        <div><dt className="font-semibold">Claimed representative position</dt><dd>{record.submitted_details.representative_position}</dd></div>
        <div><dt className="font-semibold">Submitted</dt><dd>{record.created_at} (fixture; timezone agreement pending)</dd></div>
        <div><dt className="font-semibold">Loaded application version</dt><dd>{record.row_version} (zero is a valid initial version)</dd></div>
        <div><dt className="font-semibold">Reviewed / expires / revoked</dt><dd>{record.reviewed_at??'Not reviewed'} / {record.expires_at??'Not supplied'} / {record.revoked_at??'Not revoked'}</dd></div>
      </dl>
      <p>Authorization statement: {record.submitted_details.authorization_statement}</p>
      <dl aria-label="FCA independent authority determinations" className="grid gap-3 rounded border p-3 text-sm sm:grid-cols-2">
        <div><dt className="font-semibold">Platform account approval</dt><dd>Separate decision; status not returned here. Access projection assumes a synthetic approved producer.</dd></div>
        <div><dt className="font-semibold">RSBSA verification</dt><dd>Not returned or inferred. HarborAI registration is not official RSBSA enrollment.</dd></div>
        <div><dt className="font-semibold">FCA authority</dt><dd>{record.status} is a demonstration review state, not current usable authority. Ownership, directory verification, expiry and evidence must be checked by the server.</dd></div>
        <div><dt className="font-semibold">Institutional procurement eligibility</dt><dd>Not established by directory membership, RSBSA status or administrative representation approval.</dd></div>
      </dl>
      <section aria-label="Synthetic FCA evidence metadata" className="space-y-2 rounded border p-3">
        <h4 className="font-semibold">Evidence metadata — local demonstration context</h4>
        <p>Not an application-show response or a real document. No filename, receipt, private path, hash, content or URL is exposed.</p>
        <p>Type: {context!.receipt.mime}; size: {context!.receipt.bytes} bytes (24 KiB); receipt example: {context!.receipt.status}; inspection: {context!.inspection.status}.</p>
        <ul>{Object.entries(context!.inspection.checks).map(([check,state])=><li key={check}>{check}: {state} (synthetic)</li>)}</ul>
        <p>Inspection metadata needs a separately reviewed read projection. A clean fixture is not a real inspection or release authorization. Quarantined, rejected, expired or stale evidence cannot be released.</p>
        <Button disabled>View evidence</Button><Button disabled>Download evidence</Button><Button disabled>Inspect again</Button>
      </section>
      <section aria-label="Synthetic FCA review history"><h4 className="font-semibold">Review history — demonstration only</h4>{record.review_history.length?<ol className="list-inside list-decimal">{record.review_history.map((row,index)=><li key={index}>{row.event_type}: {row.previous_status??'none'} to {row.new_status??'none'}; {row.created_at}</li>)}</ol>:<p role="status">No history returned; absence does not establish a complete audit.</p>}<p>Review reasons, reviewer identity and event versions are not returned by show(). No confidential audit details are inferred.</p></section>
      <section aria-label="FCA authority requirements"><h4 className="font-semibold">Requirements still requiring verification</h4><ul className="list-inside list-disc"><li>Current approved applicant account and owner-bound application.</li><li>Verified directory reference, not name matching or membership certification.</li><li>Submitted evidence bound to this applicant/application, with successful current inspection and integrity checks.</li><li>Active explicitly assigned reviewer; self-review prohibited.</li><li>Matching expected row version and original decision key; no automatic retry.</li><li>Current authorization expiry and revocation state. No procurement eligibility is granted.</li></ul></section>
      <label htmlFor={`${prefix}-notes`}>Local FCA review notes — never submitted<Textarea id={`${prefix}-notes`} maxLength={2000} value={notes[notesKey]??''} onChange={e=>{invalidate();setNotes(previous=>({...previous,[notesKey]:e.target.value}));}} /></label>
      <p>{viewer==='assigned_reviewer'?'Assigned reviewer demonstration: only authorize/reject pending applications or revoke authorized authority are defined; backend must reject self-review.':'Producer view: own application status only; administrative decisions are unavailable.'}</p>
      <div className="flex flex-wrap gap-2"><Button disabled>Approve authority</Button><Button disabled>Reject authority</Button><Button disabled>Revoke authority</Button><Button disabled>Request clarification — contract gap</Button><Button disabled>Resubmit — contract gap</Button></div>
    </section>}
    <section aria-label="FCA response laboratory" aria-busy={busy} className="space-y-3 rounded border p-3">
      <label htmlFor={`${prefix}-scenario`}>Synthetic FCA response<select id={`${prefix}-scenario`} className="block min-h-11 w-full rounded border bg-card p-2" value={scenario} onChange={e=>{invalidate();setScenario(e.target.value as AuthorityScenario);}}>{Object.entries(authorityScenarios).map(([key,label])=><option key={key} value={key}>{label}</option>)}</select></label>
      <div className="flex flex-wrap gap-2"><Button variant="outline" data-fixture-action="fca-read" disabled={busy||!canReadApplication(viewer,selected)} onClick={()=>void read()}>Load synthetic FCA comparison</Button><Button variant="outline" data-fixture-action="fca-read" onClick={()=>select(viewer,selected)}>Reset synthetic FCA snapshot</Button></div>
      {busy&&<p role="status">Loading synthetic application; no API request.</p>}
      {outcome&&<p role={outcome.kind==='record'||outcome.kind==='empty'?'status':'alert'}>{outcome.message}</p>}
      {outcome?.kind==='record'&&record&&<div aria-label="FCA version comparison"><p>Loaded version {record.row_version} to compared version {outcome.record.row_version}; authority {record.status} to {outcome.record.status}. No decision was made.</p><Button variant="outline" data-fixture-action="fca-read" onClick={()=>{const next=validateApplication(outcome.record,viewer,selected);invalidate();setSnapshot(next);}}>Use synthetic FCA comparison</Button></div>}
    </section>
  </section>;
}
