import { Card, CardContent, CardHeader, CardTitle } from '../ui/card';
import { Button } from '../ui/button';
import { Input } from '../ui/input';
import type { InstitutionalDemandState, SupplyOfferState } from '../../contracts/institutionalDemands';
import { quantityWithinRemaining, isDraftDecimal } from './previewModel';
export const INSTITUTIONAL_BUYER_PREVIEW_ENABLED = false;
export const INSTITUTIONAL_SUPPLIER_PREVIEW_ENABLED = false;
const demandStates: readonly InstitutionalDemandState[] = ['draft', 'pending_review', 'open', 'under_review', 'partially_fulfilled', 'fulfilled', 'cancelled', 'expired'];
const offerStates: readonly SupplyOfferState[] = ['submitted', 'under_review', 'provisional', 'rejected', 'withdrawn', 'cancelled', 'expired'];
function Quantities() {
  return <div className="overflow-x-auto"><table className="w-full text-left"><caption>Synthetic commodity quantities (kg)</caption><thead><tr><th scope="col">Commodity</th><th scope="col">Requested</th><th scope="col">Provisional</th><th scope="col">Remaining</th><th scope="col">Delivered</th></tr></thead>
    <tbody><tr><td>Cabbage, green variety</td><td>10.125</td><td>6.125</td><td>4.000</td><td>0.000</td></tr><tr><td>Fish, specified grade</td><td>5.000</td><td>0.000</td><td>5.000</td><td>0.000</td></tr></tbody></table></div>;
}
export function InstitutionalBuyerPreview() {
  return <Card><CardHeader><CardTitle>My institutional demands preview</CardTitle></CardHeader><CardContent className="space-y-4"><p>Synthetic records only. Review and provisional allocations are disabled.</p>
    <p>Market inquiry: planning. Supply requirement: availability coordination. Official procurement reference: external institutional procedures apply.</p><Quantities />
    <p>Supply offer fixture: under review. Proposed price: PHP 40.10/kg. No contract, sale, inventory reservation or payment obligation exists.</p>
    <div className="flex flex-wrap gap-2"><Button disabled>Review offer</Button><Button disabled>Reject offer</Button><Button disabled>Allocate provisionally</Button><Button disabled variant="outline">Cancel demand</Button></div>
    <ul className="grid gap-2 sm:grid-cols-2">{demandStates.map(s => <li key={s}>Lifecycle example: {s}</li>)}</ul>
    <p>Partially fulfilled and fulfilled require separately validated delivery records; provisional allocation does not establish either state.</p>
    <p>History fixture: draft → pending review → open → offer reviewed → provisionally allocated.</p>
  </CardContent></Card>;
}
export function InstitutionalSupplierPreview() {
  const id = useId();
  const [item, setItem] = useState('cabbage');
  const [quantity, setQuantity] = useState('2.000');
  const [price, setPrice] = useState('');
  const [availability, setAvailability] = useState('');
  const remaining = item === 'cabbage' ? '4.000' : '5.000';
  const quantityValid = quantityWithinRemaining(quantity, remaining);
  const priceValid = !price || isDraftDecimal(price, 10, 2);
  return <Card><CardHeader><CardTitle>Institutional supply opportunities preview</CardTitle></CardHeader><CardContent className="space-y-4"><p>Synthetic market inquiry from a synthetic institution.</p>
    <p>Check this posting’s supplier type, RSBSA requirement, specifications, deadlines and evidence requirements. FCA authority does not establish procurement eligibility.</p><Quantities />
    <fieldset className="grid gap-3 rounded border p-3 sm:grid-cols-2"><legend>Local offer draft — no commitment created</legend>
      <label htmlFor={`${id}-item`}>Selected synthetic commodity<select id={`${id}-item`} className="block min-h-11 w-full rounded border bg-card p-2" value={item} onChange={e => setItem(e.target.value)}><option value="cabbage">Cabbage (kg)</option><option value="fish">Fish (kg)</option></select></label>
      <label htmlFor={`${id}-quantity`}>Proposed quantity (kg)<Input id={`${id}-quantity`} value={quantity} inputMode="decimal" aria-invalid={!quantityValid} aria-describedby={`${id}-capacity`} onChange={e => setQuantity(e.target.value)} /></label>
      <label htmlFor={`${id}-price`}>Unit price (PHP, optional)<Input id={`${id}-price`} value={price} inputMode="decimal" aria-invalid={!priceValid} onChange={e => setPrice(e.target.value)} /></label>
      <label htmlFor={`${id}-date`}>Availability date<Input id={`${id}-date`} type="date" value={availability} onChange={e => setAvailability(e.target.value)} /></label>
      {['Delivery information', 'Eligibility declaration'].map(label => <label key={label}>{label}<Input maxLength={2000} /></label>)}
      <p id={`${id}-capacity`}>Fixture remaining: {remaining} kg. Quantities must be positive and within this fixture remainder; server concurrency and eligibility checks are still required.</p>
      {!quantityValid && <p role="alert">Enter a positive quantity with up to 3 decimal places, no greater than the remaining fixture quantity.</p>}
      {!priceValid && <p role="alert">Use up to 10 whole and 2 decimal digits for price.</p>}
    </fieldset>
    <Button disabled>Submit supply offer</Button><Button disabled variant="outline">Withdraw offer</Button>
    <ul className="grid gap-2 sm:grid-cols-2">{offerStates.map(s => <li key={s}>Synthetic offer: {s}</li>)}</ul><p>Response fixture: quantities reviewed for coordination. Provisional allocation is not delivered goods or an official government award.</p>
  </CardContent></Card>;
}
import { useId, useState } from 'react';
