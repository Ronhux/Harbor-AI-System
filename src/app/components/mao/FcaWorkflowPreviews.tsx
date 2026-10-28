import FcaAuthorityReviewPreview from './FcaAuthorityReviewPreview';
import { useId, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '../ui/card';
import { Button } from '../ui/button';
import { Input } from '../ui/input';
import { Label } from '../ui/label';
import { Textarea } from '../ui/textarea';

export const FCA_APPLICANT_PREVIEW_ENABLED = false;
export const FCA_ADMIN_PREVIEW_ENABLED = false;
const fixtures = [
  { id: 'synthetic-pending', association: 'Synthetic association A', status: 'pending', inspection: 'Quarantined', pdf: 'Pending', malware: 'Error: unavailable', integrity: 'Clean' },
  { id: 'synthetic-authorized', association: 'Synthetic association B', status: 'authorized', inspection: 'Clean (fixture)', pdf: 'Clean', malware: 'Clean', integrity: 'Clean' },
  { id: 'synthetic-rejected', association: 'Synthetic association C', status: 'rejected', inspection: 'Rejected (fixture)', pdf: 'Rejected', malware: 'Clean', integrity: 'Clean' },
  { id: 'synthetic-revoked', association: 'Synthetic association D', status: 'revoked', inspection: 'Clean (fixture)', pdf: 'Clean', malware: 'Clean', integrity: 'Clean' },
] as const;
const labels = { pending: 'Pending', authorized: 'Approved representative', rejected: 'Rejected', revoked: 'Revoked' };

export function FcaApplicantPreview() {
  const id = useId();
  const [position, setPosition] = useState('');
  const [statement, setStatement] = useState('');
  const [association, setAssociation] = useState('');
  return <Card><CardHeader><CardTitle>FCA representative application preview</CardTitle></CardHeader><CardContent className="space-y-4">
    <p role="status">Non-submitting preview. Associations below are synthetic examples, not the official directory.</p>
    <p>Directory membership does not prove representative authority. Representative approval does not approve your producer account, verify RSBSA, or establish procurement eligibility.</p>
    <div><Label htmlFor={`${id}-fca`}>Association</Label><select id={`${id}-fca`} value={association} onChange={event => setAssociation(event.target.value)} className="mt-1 min-h-11 w-full rounded-lg border bg-card p-3">
      <option value="">Select a synthetic association</option>{fixtures.map(row => <option key={row.id} value={row.id}>{row.association}</option>)}
    </select></div>
    <div><Label htmlFor={`${id}-position`}>Representative position</Label><Input id={`${id}-position`} maxLength={120} value={position} onChange={event => setPosition(event.target.value)} /></div>
    <div><Label htmlFor={`${id}-statement`}>Authorization statement</Label><Textarea id={`${id}-statement`} maxLength={2000} value={statement} onChange={event => setStatement(event.target.value)} /></div>
    <div><Label htmlFor={`${id}-evidence`}>Supporting evidence</Label><Input id={`${id}-evidence`} type="file" disabled aria-describedby={`${id}-requirements`} />
      <p id={`${id}-requirements`}>File selection and upload are disabled. Proposed maximum: 5 MB. PDF inspection and malware scanning must pass; image support requires an approved decoder.</p>
    </div>
    {!association || !position.trim() || !statement.trim() ? <p role="note">Select an association and enter a position and authorization statement. Supporting evidence will also be required before real submission.</p> : <p role="note">Local draft fields are present. No evidence has been uploaded or application submitted.</p>}
    <dl aria-label="Independent verification examples" className="grid gap-3 rounded border p-3 text-sm sm:grid-cols-2">
      <div><dt className="font-semibold">Platform account approval</dt><dd>Separate administrator decision</dd></div>
      <div><dt className="font-semibold">RSBSA verification</dt><dd>Independent registry verification; platform registration is not official enrollment</dd></div>
      <div><dt className="font-semibold">FCA representative authority</dt><dd>Pending until explicitly authorized</dd></div>
      <div><dt className="font-semibold">Procurement eligibility</dt><dd>Not established by directory membership or representative approval</dd></div>
    </dl>
    <Button type="button" disabled>Submit application — unavailable</Button>
    <p>Existing synthetic applications below are independent of this local draft. Changing the association does not change a submitted record or grant authority.</p>
    <FcaAuthorityReviewPreview />
  </CardContent></Card>;
}

export function FcaAdminReviewPreview() {
  return <Card><CardHeader><CardTitle>FCA administrator review preview</CardTitle></CardHeader><CardContent className="space-y-4">
    <p role="status">Synthetic, read-only review examples. Only explicitly assigned active administrators may eventually review applications.</p>
    <p>Approval applies only to representation. Account approval, RSBSA and procurement eligibility remain separate.</p>
    <FcaAuthorityReviewPreview initialViewer="assigned_reviewer" />
  </CardContent></Card>;
}
