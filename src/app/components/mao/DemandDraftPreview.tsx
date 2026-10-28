import { useEffect, useId, useRef, useState } from 'react';
import type { InstitutionalDemandInput, InstitutionalDemandItem } from '../../contracts/institutionalDemands';
import { Card, CardContent, CardHeader, CardTitle } from '../ui/card';
import { Button } from '../ui/button';
import { Input } from '../ui/input';
import { Textarea } from '../ui/textarea';
import { blankCommodity, blankPosting, commodityExamples, demandUnits, draftPosting, validatePosting, type DraftRow, type FieldErrors } from './institutionalDraftModel';
import { mockPostingCheck, type MockOutcome, type MockScenario } from './institutionalMockAdapter';
const scenarios: Record<MockScenario, string> = {
  local_valid: 'Local validation only', validation: '422 field validation', unauthorized: '401 unauthenticated',
  forbidden: '403 forbidden', missing: '404 missing or disabled', stale: '409 stale revision',
  quantity: '409 allocation quantity conflict', duplicate_conflict: '409 duplicate key conflict',
  duplicate_replay: 'Matching duplicate replay example', unavailable: '503 official workflow unavailable',
  network: 'Network failure', server: 'Unexpected server failure',
};
/** Synthetic controlled draft. No API adapter, storage, upload, posting or account mutation. */
export default function DemandDraftPreview() {
  const id = useId(), nextRow = useRef(1), generation = useRef(0);
  const [metadata, setMetadata] = useState(blankPosting);
  const [rows, setRows] = useState<DraftRow[]>([{ key: 0, selection: 'custom', item: blankCommodity() }]);
  const [checked, setChecked] = useState(false), [remoteFields, setRemoteFields] = useState<FieldErrors>({});
  const [scenario, setScenario] = useState<MockScenario>('local_valid');
  const [outcome, setOutcome] = useState<MockOutcome | null>(null), [busy, setBusy] = useState(false);
  const summary = useRef<HTMLDivElement>(null);
  useEffect(() => () => { generation.current++; }, []);
  const posting = draftPosting(metadata, rows);
  const errors = checked ? { ...validatePosting(posting, rows, Date.now()), ...remoteFields } : {};
  const invalidate = () => { generation.current++; setOutcome(null); setRemoteFields({}); setBusy(false); };
  const update = <K extends keyof InstitutionalDemandInput>(key: K, value: InstitutionalDemandInput[K]) => { invalidate(); setMetadata(current => ({ ...current, [key]: value })); };
  const itemChange = (index: number, key: keyof InstitutionalDemandItem, value: string) => { invalidate(); setRows(current => current.map((row, i) => i === index ? { ...row, selection: key === 'commodity' || key === 'category' ? 'custom' : row.selection, item: { ...row.item, [key]: value } } : row)); };
  const selectCommodity = (index: number, selection: string) => {
    invalidate(); const example = commodityExamples.find(row => row.id === selection);
    setRows(current => current.map((row, i) => i === index ? { ...row, selection, item: { ...row.item, commodity: example?.name ?? '', category: example?.category ?? '' } } : row));
  };
  const fieldId = (key: string) => id + '-' + key.replaceAll('.', '-');
  const attrs = (key: string) => ({ id: fieldId(key), 'aria-invalid': Boolean(errors[key]), 'aria-describedby': errors[key] ? fieldId(key) + '-error' : undefined });
  const error = (key: string) => errors[key] ? <p id={fieldId(key) + '-error'} className="text-sm text-red-800">{errors[key].join(' ')}</p> : null;
  const validateLocal = () => { invalidate(); setChecked(true); if (Object.keys(validatePosting(posting, rows, Date.now())).length) setTimeout(() => summary.current?.focus(), 0); };
  const simulate = async () => {
    if (scenario === 'local_valid' && Object.keys(validatePosting(posting, rows, Date.now())).length) { validateLocal(); return; }
    const ticket = ++generation.current; setBusy(true); setOutcome(null); setRemoteFields({});
    const result = await mockPostingCheck(scenario); if (ticket !== generation.current) return;
    setBusy(false); setOutcome(result);
    if (result.kind === 'http_fixture' && result.body.errors) {
      const fields: FieldErrors = {}; for (const [key, value] of Object.entries(result.body.errors)) fields[key.replace(/^posting\./, '')] = value;
      setRemoteFields(fields); setChecked(true); setTimeout(() => summary.current?.focus(), 0);
    }
  };
  return <Card><CardHeader><CardTitle>Institutional demand draft preview</CardTitle></CardHeader><CardContent className="space-y-5">
    <p role="status">Local draft only. Nothing is submitted, uploaded, saved or published.</p>
    <p>Synthetic institution: a real institution and authorization must be resolved by the server, not selected by this form.</p>
    <p className="text-sm">Custom commodity names are supported by the prepared contract. Examples below are synthetic, not an official commodity or eligibility directory.</p>
    {Object.keys(errors).length > 0 && <div ref={summary} tabIndex={-1} role="alert" className="rounded border border-red-300 bg-red-50 p-3"><h3 className="font-semibold">Review the draft fields</h3><ul className="list-inside list-disc">{Object.entries(errors).map(([key, messages]) => <li key={key}>{key}: {messages.join(' ')}</li>)}</ul></div>}
    <fieldset className="grid min-w-0 gap-3 rounded border p-3 sm:grid-cols-2"><legend>Posting identification and purpose</legend>
      <label htmlFor={fieldId('title')}>Demand title<Input {...attrs('title')} maxLength={160} value={metadata.title} onChange={e => update('title', e.target.value)} />{error('title')}</label>
      {(['description', 'purpose'] as const).map(key => <label key={key} htmlFor={fieldId(key)}>{key === 'description' ? 'Description' : 'Purpose'}<Textarea {...attrs(key)} maxLength={key === 'purpose' ? 2000 : 4000} value={metadata[key]} onChange={e => update(key, e.target.value)} />{error(key)}</label>)}
      <label htmlFor={fieldId('program')}>Related program<select {...attrs('program')} className="block min-h-11 w-full rounded border bg-card p-2" value={metadata.program ?? ''} onChange={e => update('program', e.target.value as InstitutionalDemandInput['program'])}><option value="other">Other</option><option value="sagip_saka">Sagip Saka</option><option value="kadiwa">KADIWA</option></select>{error('program')}</label>
      <label htmlFor={fieldId('posting_type')}>Posting type<select {...attrs('posting_type')} className="block min-h-11 w-full rounded border bg-card p-2" value={metadata.posting_type} onChange={e => update('posting_type', e.target.value as InstitutionalDemandInput['posting_type'])}><option value="market_inquiry">Market inquiry — planning</option><option value="supply_requirement">Supply requirement — coordination</option><option value="official_procurement_reference">Official procurement reference</option></select>{error('posting_type')}</label>
      <label htmlFor={fieldId('procurement_reference')}>Official reference (required for official postings)<Input {...attrs('procurement_reference')} maxLength={255} value={metadata.procurement_reference ?? ''} onChange={e => update('procurement_reference', e.target.value)} />{error('procurement_reference')}</label>
    </fieldset>
    <p>Sagip Saka and official posting publication require separate validated procedures. This preview cannot purchase goods, award a contract or process payment.</p>
    {rows.map((row, index) => <fieldset key={row.key} className="grid min-w-0 gap-3 rounded border p-3 sm:grid-cols-2" data-commodity-row><legend>Commodity {index + 1}</legend>
      <label htmlFor={id + '-selection-' + row.key}>Commodity selection<select id={id + '-selection-' + row.key} className="block min-h-11 w-full rounded border bg-card p-2" value={row.selection} onChange={e => selectCommodity(index, e.target.value)}><option value="custom">Custom commodity</option>{commodityExamples.map(example => <option key={example.id} value={example.id}>{example.name}</option>)}</select></label>
      {(['commodity', 'category', 'variety', 'quantity', 'specifications', 'max_unit_price'] as const).map(key => {
        const path = 'items.' + index + '.' + key, limits = { commodity: 160, category: 80, variety: 120, quantity: 13, specifications: 2000, max_unit_price: 13 };
        return <label key={key} htmlFor={fieldId(path)}>{key === 'max_unit_price' ? 'Maximum unit price (PHP, optional)' : key.replaceAll('_', ' ')}<Input {...attrs(path)} maxLength={limits[key]} inputMode={key === 'quantity' || key === 'max_unit_price' ? 'decimal' : 'text'} value={row.item[key] ?? ''} onChange={e => itemChange(index, key, e.target.value)} />{error(path)}</label>;
      })}
      <label htmlFor={fieldId('items.' + index + '.unit')}>Unit<select {...attrs('items.' + index + '.unit')} className="block min-h-11 w-full rounded border bg-card p-2" value={row.item.unit} onChange={e => itemChange(index, 'unit', e.target.value)}>{demandUnits.map(unit => <option key={unit}>{unit}</option>)}</select>{error('items.' + index + '.unit')}</label>
      <Button type="button" data-fixture-action="remove" variant="outline" disabled={rows.length === 1} onClick={() => { invalidate(); setRows(current => current.filter(record => record.key !== row.key)); }}>Remove commodity {index + 1}</Button>
    </fieldset>)}
    {error('items')}
    <Button type="button" data-fixture-action="add" variant="outline" disabled={rows.length >= 20} onClick={() => { invalidate(); setRows(current => [...current, { key: nextRow.current++, selection: 'custom', item: blankCommodity() }]); }}>Add commodity</Button>
    <fieldset className="grid min-w-0 gap-3 rounded border p-3 sm:grid-cols-2"><legend>Budget and schedule</legend>
      <label htmlFor={fieldId('budget_kind')}>Budget type<select {...attrs('budget_kind')} className="block min-h-11 w-full rounded border bg-card p-2" value={metadata.budget_kind} onChange={e => { const kind = e.target.value as InstitutionalDemandInput['budget_kind']; invalidate(); setMetadata(current => ({ ...current, budget_kind: kind, budget_amount: kind === 'none' ? undefined : current.budget_amount })); }}><option value="none">No budget</option><option value="indicative">Indicative budget</option><option value="abc">ABC — official reference only</option></select>{error('budget_kind')}</label>
      <label htmlFor={fieldId('budget_amount')}>Budget amount (PHP)<Input {...attrs('budget_amount')} disabled={metadata.budget_kind === 'none'} inputMode="decimal" maxLength={15} value={metadata.budget_amount ?? ''} onChange={e => update('budget_amount', e.target.value || undefined)} />{error('budget_amount')}</label>
      <label htmlFor={fieldId('delivery_location')}>Delivery location<Input {...attrs('delivery_location')} maxLength={2000} value={metadata.delivery_location} onChange={e => update('delivery_location', e.target.value)} />{error('delivery_location')}</label>
      {(['offer_deadline', 'delivery_start', 'delivery_end'] as const).map(key => <label key={key} htmlFor={fieldId(key)}>{key.replaceAll('_', ' ')}<Input {...attrs(key)} type="datetime-local" value={metadata[key]} onChange={e => update(key, e.target.value)} />{error(key)}</label>)}
      <p className="text-sm sm:col-span-2">Times use this browser's local timezone for the preview. A live timezone contract still requires review.</p>
    </fieldset>
    <fieldset className="space-y-3 rounded border p-3"><legend>Supplier eligibility and document descriptions</legend>
      <label htmlFor={fieldId('eligible_supplier_type')}>Eligible suppliers<select {...attrs('eligible_supplier_type')} className="block min-h-11 w-full rounded border bg-card p-2" value={metadata.eligible_supplier_type} onChange={e => update('eligible_supplier_type', e.target.value as InstitutionalDemandInput['eligible_supplier_type'])}><option value="individual">Individual producer</option><option value="fca">FCA</option><option value="both">Both</option></select>{error('eligible_supplier_type')}</label>
      <label htmlFor={fieldId('eligibility_requirements')}>Eligibility requirements<Textarea {...attrs('eligibility_requirements')} maxLength={4000} value={metadata.eligibility_requirements} onChange={e => update('eligibility_requirements', e.target.value)} />{error('eligibility_requirements')}</label>
      <label htmlFor={fieldId('required_documents')}>Required document descriptions (one per line)<Textarea {...attrs('required_documents')} maxLength={3220} value={metadata.required_documents.join('\n')} onChange={e => update('required_documents', e.target.value ? e.target.value.split('\n') : [])} />{error('required_documents')}<span className="text-sm">Descriptions only. No file selection or upload.</span></label>
      <label className="flex items-start gap-2"><input type="checkbox" checked={metadata.partial_supply} onChange={e => update('partial_supply', e.target.checked)} />Accept partial supply from multiple suppliers</label>
      <label className="flex items-start gap-2"><input type="checkbox" checked={metadata.rsbsa_required} onChange={e => update('rsbsa_required', e.target.checked)} />Require independently verified RSBSA eligibility</label>
    </fieldset>
    <Button type="button" data-fixture-action="validate" variant="outline" onClick={validateLocal}>Check synthetic draft locally</Button>
    {checked && Object.keys(errors).length === 0 && <p role="status">Local draft checks passed. This is not server authorization, persistence or a published demand.</p>}
    <section className="space-y-2 rounded border p-3" aria-label="Local demand summary"><h3 className="font-semibold">Local demand summary — not submitted</h3><p>{metadata.title || 'Untitled local draft'} · {metadata.posting_type} · {metadata.program}</p>
      <ul>{posting.items.map((item, index) => <li key={rows[index].key}>{item.commodity || 'Commodity ' + (index + 1) + ' incomplete'}: {item.quantity || 'quantity missing'} {item.unit}, {item.specifications || 'specifications missing'}</li>)}</ul>
      <p>Budget: {metadata.budget_kind === 'none' ? 'none' : (metadata.budget_amount || 'amount missing') + ' PHP (' + metadata.budget_kind + ')'}. Partial supply: {metadata.partial_supply ? 'allowed' : 'not allowed'}.</p>
      <p>Offer deadline: {metadata.offer_deadline || 'not set'}. Delivery: {metadata.delivery_start || 'not set'} to {metadata.delivery_end || 'not set'}.</p><p>No quantities are added across incompatible units or reserved for delivery.</p>
    </section>
    <section aria-label="Mock response laboratory" aria-busy={busy} className="space-y-3 rounded border p-3">
      <label htmlFor={id + '-scenario'}>Synthetic response<select id={id + '-scenario'} className="block min-h-11 w-full rounded border bg-card p-2" value={scenario} onChange={e => { invalidate(); setScenario(e.target.value as MockScenario); }}>{Object.entries(scenarios).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</select></label>
      <Button type="button" data-fixture-action="simulate" variant="outline" disabled={busy} onClick={() => void simulate()}>Simulate response locally</Button>
      {busy && <p role="status">Loading synthetic response; no network request is running.</p>}
      {outcome && <p role={outcome.kind === 'local' ? 'status' : 'alert'}>{outcome.kind === 'http_fixture' ? 'Synthetic HTTP ' + outcome.status + ': ' + outcome.body.message : outcome.message} Local draft retained.</p>}
      <p className="text-sm">Scenarios demonstrate handling, not authorization or actual server validation. Quantity conflicts relate to allocation contracts, not a new posting endpoint.</p>
    </section>
    <p>Account approval, RSBSA verification, FCA authority and procurement eligibility remain independent server checks.</p>
    <div className="flex flex-wrap gap-2"><Button disabled>Save demand</Button><Button disabled>Submit for review</Button><Button disabled>Publish demand</Button></div>
  </CardContent></Card>;
}
