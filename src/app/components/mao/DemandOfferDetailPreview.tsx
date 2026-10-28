import { useEffect, useId, useRef, useState } from 'react';
import type { DemandDetailRead, OfferDetailRead } from '../../contracts/institutionalDemandReads';
import { Button } from '../ui/button';
import { Textarea } from '../ui/textarea';
import { availableOfferIds, canViewOffer, demandFixture, formatScaled, offerFixture, projectDemand, viewerLabels, type DetailViewer } from './demandDetailFixtures';
import { type DetailOutcome, type DetailScenario } from './demandDetailMock';
import { readDetailComparison } from './institutionalReadMock';
import { validateDemandDetail, validateOfferDetail } from './institutionalReadValidation';
import PaginatedReadPreview from './PaginatedReadPreview';

export const DEMAND_DETAIL_PREVIEW_ENABLED = false;
const scenarios: Record<DetailScenario, string> = { demand_updated: 'Demand changed after opening', offer_updated: 'Offer changed after opening', quantity: 'Quantity no longer available', commodity_revised: 'Commodity removed or revised', superseded: 'Superseded-offer contract gap', missing: 'Record unavailable', permission: 'Authorization changed', duplicate: 'Duplicate or stale action', network: 'Network failure', server: 'Unexpected response', empty_history: 'History unavailable', incomplete: 'Incomplete record' };
const Status = ({ kind, value }: { kind: string; value: string }) => <span className="inline-block rounded border bg-slate-50 px-2 py-1 text-sm" aria-label={`${kind} status: ${value}`}>{kind}: {value.replaceAll('_', ' ')}</span>;
const stamp = (value: string | null) => value ? `${value} (synthetic; timezone unspecified)` : 'Not supplied';

/** Unrouted read-only fixture view. Persona choices never authenticate or grant permissions. */
export default function DemandOfferDetailPreview({ initialViewer = 'buyer_owner' }: { initialViewer?: DetailViewer }) {
  const id = useId(), ticket = useRef(0);
  const [viewer, setViewer] = useState(initialViewer), [demandId, setDemandId] = useState(101);
  const [offerId, setOfferId] = useState(availableOfferIds(initialViewer)[0] ?? 0);
  const [snapshot, setSnapshot] = useState<{ demand: DemandDetailRead; offer: OfferDetailRead | null }>(() => ({ demand: demandFixture(), offer: offerFixture(availableOfferIds(initialViewer)[0] ?? 0) }));
  const [scenario, setScenario] = useState<DetailScenario>('demand_updated');
  const [outcome, setOutcome] = useState<DetailOutcome | null>(null), [busy, setBusy] = useState(false);
  const [notes, setNotes] = useState<Record<string, string>>({});
  useEffect(() => () => { ticket.current++; }, []);
  const reset = (nextViewer: DetailViewer, nextDemand: number, nextOffer = availableOfferIds(nextViewer, nextDemand)[0] ?? 0) => {
    ticket.current++; setBusy(false); setOutcome(null); setViewer(nextViewer); setDemandId(nextDemand); setOfferId(nextOffer);
    setSnapshot({ demand: demandFixture(nextDemand), offer: canViewOffer(nextViewer, nextOffer) && nextDemand === 101 ? offerFixture(nextOffer) : null });
  };
  const run = async () => {
    const key = ++ticket.current; setBusy(true); setOutcome(null);
    const result = await readDetailComparison(scenario, offerId, demandId, viewer);
    if (key !== ticket.current) return;
    setBusy(false); setOutcome(result);
  };
  const applyComparison = () => {
    if (outcome?.kind !== 'comparison') return;
    const projected = projectDemand(viewer, outcome.demand);
    if (!projected) return;
    ticket.current++; setSnapshot({ demand: projected, offer: canViewOffer(viewer, offerId) ? outcome.offer : null }); setOutcome(null);
  };
  const restricted = viewer === 'administrator' || outcome?.kind === 'unavailable';
  const projected = restricted ? null : projectDemand(viewer, snapshot.demand);
  const demand = projected ? validateDemandDetail(projected, viewer, demandId) : null;
  const offer = demand && canViewOffer(viewer, offerId) && snapshot.offer ? validateOfferDetail(snapshot.offer, viewer, offerId, demandId) : null;
  const notesKey = `${viewer}:${demandId}`;
  return <section aria-label="Demand and offer detail workbench" className="min-w-0 space-y-4 rounded-lg border bg-card p-4">
    <h2 className="text-xl font-semibold">Selected demand and offer — synthetic details</h2>
    <p>No live record, identity, audit trail or permission is loaded. All actions below are local fixture controls or disabled server actions.</p>
    <PaginatedReadPreview viewer={viewer} demandId={demandId} onSelect={next => reset(viewer, next)} />
    <div className="grid gap-3 sm:grid-cols-2">
      <label htmlFor={`${id}-viewer`}>Synthetic detail viewer<select id={`${id}-viewer`} className="block min-h-11 w-full rounded border bg-card p-2" value={viewer} onChange={e => reset(e.target.value as DetailViewer, demandId)}>{Object.entries(viewerLabels).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</select></label>
      <label htmlFor={`${id}-demand`}>Selected synthetic demand<select id={`${id}-demand`} className="block min-h-11 w-full rounded border bg-card p-2" value={demandId} onChange={e => reset(viewer, Number(e.target.value))}><option value={101}>Demand 101 — offers retained</option><option value={102}>Demand 102 — no offers yet</option></select></label>
    </div>
    {restricted || !demand ? <p role="alert">{outcome?.kind === 'unavailable' ? `Synthetic ${outcome.status}: ${outcome.message}` : 'Restricted: the reviewed demand/offer read methods do not grant general administrator monitoring access. No detail data is shown.'}</p> : <>
      <section aria-label="Selected demand details" className="space-y-3">
        <h3 className="font-semibold">Loaded demand {demand.demand.request_id}: {demand.posting.title}</h3><Status kind="Demand" value={demand.demand.mao_state} />
        <dl className="grid gap-3 text-sm sm:grid-cols-2">
          <div><dt className="font-semibold">Institution</dt><dd>{demand.posting.requesting_institution}</dd></div><div><dt className="font-semibold">Loaded demand version</dt><dd>{demand.demand.mao_version}</dd></div>
          <div><dt className="font-semibold">Posted</dt><dd>{stamp(demand.demand.created_at)}</dd></div><div><dt className="font-semibold">Last update</dt><dd>{stamp(demand.demand.updated_at)}</dd></div>
          <div><dt className="font-semibold">Purpose / program</dt><dd>{demand.posting.purpose} / {demand.posting.program}</dd></div><div><dt className="font-semibold">Posting / official reference</dt><dd>{demand.posting.posting_type} / {demand.posting.procurement_reference ?? 'No official reference supplied'}</dd></div>
          <div><dt className="font-semibold">Budget</dt><dd>{demand.posting.budget_kind}: {demand.posting.budget_amount ?? 'No amount supplied'} {demand.posting.currency}</dd></div>
          <div><dt className="font-semibold">Delivery</dt><dd>{demand.posting.delivery_location}; {demand.posting.delivery_start} to {demand.posting.delivery_end} (fixture timezone unspecified)</dd></div>
          <div><dt className="font-semibold">Offer deadline</dt><dd>{demand.posting.offer_deadline}</dd></div><div><dt className="font-semibold">Supplier requirements</dt><dd>{demand.posting.eligible_supplier_type}; RSBSA required: {demand.posting.rsbsa_required ? 'yes' : 'no'}; partial supply: {demand.posting.partial_supply ? 'yes' : 'no'}</dd></div>
        </dl>
        <p>{demand.posting.description}</p><p>{demand.posting.eligibility_requirements}</p>
        <p>Required document descriptions: {demand.posting.required_documents.join('; ') || 'None specified'}. No documents are displayed or downloadable.</p>
        <div className="overflow-x-auto"><table className="w-full text-left text-sm"><caption>Commodity requirements and posting-wide provisional quantities</caption><thead><tr>{['Commodity / specification', 'Requested', 'Provisional', 'Remaining', 'Delivered'].map(label => <th key={label} scope="col" className="p-2">{label}</th>)}</tr></thead><tbody>{demand.items.map(item => <tr key={item.id}><th scope="row" className="p-2 font-normal">{item.commodity} ({item.variety ?? item.category}); {item.specifications}<br />Unit: {item.unit}</th>{[item.requested, item.provisional, item.remaining, item.delivered].map((value, index) => <td key={index} className="p-2">{value}</td>)}</tr>)}</tbody></table></div>
        <ul>{demand.items.map(item => { const source = demand.posting.items.find(row => row.commodity === item.commodity && row.category === item.category && (row.variety ?? '') === (item.variety ?? '')); return <li key={item.id}>Maximum unit price for {item.commodity}: {source?.max_unit_price ?? 'not supplied'} PHP per {item.unit}</li>; })}</ul>
        <p>Provisional is a posting-wide aggregate, not a selected-offer commitment, delivery, order or government award. Delivered is a prepared placeholder, not verified receipt data.</p>
        <section aria-label="Synthetic demand history"><h4 className="font-semibold">Demand history — demonstration only</h4>{viewer !== 'buyer_owner' ? <p>Owner-only demand history is withheld for supplier viewers.</p> : demand.history.length ? <ul className="list-inside list-disc">{demand.history.map((row, index) => <li key={index}>{row.action}: {row.previous_state ?? 'none'} → {row.new_state ?? 'none'}; revision {row.revision}; {stamp(row.created_at)}</li>)}</ul> : <p role="status">No history supplied. This does not prove that no events occurred.</p>}</section>
      </section>
      <section aria-label="Selected offer details" className="space-y-3 rounded border p-3">
        <label htmlFor={`${id}-offer`}>{viewer === 'buyer_owner' ? 'Received synthetic offers' : 'Own synthetic offer'}<select id={`${id}-offer`} className="block min-h-11 w-full rounded border bg-card p-2" value={offerId} disabled={!availableOfferIds(viewer, demandId).length} onChange={e => reset(viewer, demandId, Number(e.target.value))}>{availableOfferIds(viewer, demandId).length ? availableOfferIds(viewer, demandId).map(value => <option key={value} value={value}>Offer {value}</option>) : <option value={0}>No permitted offer fixture</option>}</select></label>
        {offer ? <><h3 className="font-semibold">Offer {offer.offer.id} for demand {offer.offer.demand_request_id}</h3><Status kind="Offer" value={offer.offer.mao_state} />
          <p>Loaded offer version: {offer.offer.mao_version}. Submitted demand version: {offer.submission.demand_version}. Last update: {stamp(offer.offer.updated_at)}.</p>
          {offer.submission.demand_version !== demand.demand.mao_version && <p role="alert">This offer records a different demand version. Compare the posting before any future action; a version difference alone does not establish supersession or invalidity.</p>}
          <p>Supplier type: {offer.submission.supplier_type}. {offer.submission.representation_id ? `Representation reference ${offer.submission.representation_id} (synthetic).` : 'Individual submission.'} Supplier name and current RSBSA/FCA/procurement status are not in this read contract and are not inferred.</p>
          <ul>{offer.items.map(line => { const item = demand.items.find(row => row.id === line.demand_item_id); return <li key={line.id}>{item?.commodity ?? 'Commodity from a different revision; comparison required'}: {formatScaled(line.offered_milli, 3)} {item?.unit ?? '(unit unavailable)'}; price {formatScaled(line.unit_price_cents, 2)} PHP per unit</li>; })}</ul>
          <p>Availability: {offer.submission.availability_date}. {offer.submission.delivery_information}</p><p>Eligibility declaration: {offer.submission.eligibility_declaration}</p>
          <h4 className="font-semibold">Offer history — demonstration only</h4>{offer.history.length ? <ul className="list-inside list-disc">{offer.history.map((row, index) => <li key={index}>{row.action}: {row.previous_state ?? 'none'} → {row.new_state ?? 'none'}; {stamp(row.created_at)}; {row.reason ?? 'No reason supplied'}</li>)}</ul> : <p role="status">Offer history unavailable; absence is not a verified audit result.</p>}
          <p>Per-offer allocation records and superseding links are not exposed by the reviewed detail contract.</p>
        </> : <p role="status">No own/permitted offer is available. Other producers' offers and private evidence are withheld.</p>}
      </section>
      <label htmlFor={`${id}-notes`}>Local comparison notes — not an API field<Textarea id={`${id}-notes`} maxLength={1000} value={notes[notesKey] ?? ''} onChange={e => { ticket.current++; setBusy(false); setOutcome(null); setNotes(current => ({ ...current, [notesKey]: e.target.value })); }} /></label>
    </>}
    <section aria-label="Detail conflict laboratory" aria-busy={busy} className="space-y-3 rounded border p-3">
      <label htmlFor={`${id}-scenario`}>Synthetic detail response<select id={`${id}-scenario`} className="block min-h-11 w-full rounded border bg-card p-2" value={scenario} onChange={e => { ticket.current++; setBusy(false); setOutcome(null); setScenario(e.target.value as DetailScenario); }}>{Object.entries(scenarios).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</select></label>
      <Button type="button" data-fixture-action="simulate-detail" disabled={busy || viewer === 'administrator'} variant="outline" onClick={() => void run()}>Load synthetic comparison</Button>
      <Button type="button" data-fixture-action="refresh" variant="outline" onClick={() => reset(viewer, demandId, offerId)}>Reset local snapshot</Button>
      {busy && <p role="status">Loading synthetic details; no request is being sent.</p>}
      {outcome && <p role={outcome.kind === 'comparison' ? 'status' : 'alert'}>{outcome.message}</p>}
      {outcome?.kind === 'comparison' && !restricted && <div className="space-y-2" aria-label="Version comparison">
        <p>Demand version {snapshot.demand.demand.mao_version} → {outcome.demand.demand.mao_version}; offer version {snapshot.offer?.offer.mao_version ?? 'none'} → {outcome.offer?.offer.mao_version ?? 'none'}. No loaded record or local notes were automatically replaced.</p>
        <p>Requested quantities: {snapshot.demand.items.map(item => item.requested + ' ' + item.unit).join(', ')} → {outcome.demand.items.map(item => item.requested + ' ' + item.unit).join(', ')}.</p>
        <Button type="button" data-fixture-action="compare" variant="outline" onClick={applyComparison}>Use compared synthetic snapshot</Button>
      </div>}
    </section>
    <div className="flex flex-wrap gap-2"><Button disabled>Submit offer</Button><Button disabled>Accept offer</Button><Button disabled>Reject offer</Button><Button disabled>Withdraw offer</Button><Button disabled>Allocate quantity</Button></div>
  </section>;
}
