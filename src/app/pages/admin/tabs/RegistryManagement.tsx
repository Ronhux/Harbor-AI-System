import { DashboardText, useDashboardCopy } from '../../../i18n/DashboardCopy';
import { useLanguage } from '../../../i18n/useLanguage';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { CheckCircle2, FileText, Search, XCircle, Users } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../components/ui/card';
import { Button } from '../../../components/ui/button';
import { Input } from '../../../components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../../../components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../../../components/ui/table';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '../../../components/ui/dialog';
import { request } from '../../../../lib/api';
import { aparriBarangays } from '../../../data/aparriBarangays';
import PageHeader from '../../../components/dashboard/PageHeader';
import { LoadingState } from '../../../components/ui/loading-state';
import FeedbackModal from '../../../components/ui/feedback-modal';

type RegistryEntry = {
  registry_id: number;
  rsbsa_number?: string;
  first_name?: string | null;
  middle_name?: string | null;
  last_name?: string | null;
  extension_name?: string | null;
  sex?: string | null;
  house_number?: string | null;
  zone?: string | null;
  street?: string | null;
  barangay?: string | null;
  municipality?: string | null;
  province?: string | null;
  region?: string | null;
  mobile_number?: string | null;
  farm_location?: string | null;
  area_ha?: string | number | null;
  area_display?: string | null;
  address?: string | null;
  full_name?: string | null;
  name?: string | null;
  primary_livelihood?: string | null;
  producer_type?: string | null;
  contact_number?: string | null;
  producer_accounts?: Array<{
    producer_id: number;
    name: string;
    email?: string | null;
    contact_number?: string | null;
    account_status?: string;
    user_type?: string;
    producer_type?: string;
    verification_status: string;
    link_status: string;
    registry_match: { status: string; valid_match: boolean };
  }>;
};
type PendingProducer = {
  producer_id: number;
  user_id: number;
  name: string;
  email?: string;
  producer_type?: string;
  location?: string;
  verification_status: string;
  rsbsa_number?: string;
  registry_match?: {
    status: string;
    rsbsa_match: boolean;
    valid_match: boolean;
    name_match: boolean | null;
    registry_id?: number | null;
    registry_name?: string | null;
    registry_location?: string | null;
    primary_livelihood?: string | null;
    producer_type?: string | null;
  };
  contact_number?: string | null;
  submitted_details?: Partial<RegistryEntry> & { email?: string; suffix?: string };
  matched_registry?: Partial<RegistryEntry>;
  registry_matched_at?: string | null;
  match_evidence?: 'registration_snapshot' | 'current_name_check';
};

const formatFullName = (entry: Partial<RegistryEntry>) => {
  const parts = [entry.first_name, entry.middle_name, entry.last_name, entry.extension_name]
    .filter((value): value is string => typeof value === 'string' && value.trim().length > 0 && value.trim().toLowerCase() !== 'null' && value.trim().toLowerCase() !== 'undefined');

  if (parts.length > 0) {
    return parts.map((part) => part.trim()).join(' ');
  }

  const fallback = [entry.full_name, entry.name].find((value): value is string => typeof value === 'string' && value.trim().length > 0);
  return fallback ? fallback.trim() : '—';
};

const formatArea = (value: string | number | null | undefined) => {
  const num = Number(value ?? NaN);
  if (!Number.isFinite(num)) {
    return '—';
  }

  return `${new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num)} ha`;
};

const displayText = (value: string | number | null | undefined) => {
  if (value === null || value === undefined) return '—';
  const text = String(value).trim();
  if (!text) return '—';
  const lower = text.toLowerCase();
  if (lower === 'null' || lower === 'undefined') return '—';
  return text;
};

function DetailField({ label, value, wide = false }: { label: string; value?: string | number | null; wide?: boolean }) {
  return (
    <div className={wide ? 'min-w-0 sm:col-span-2' : 'min-w-0'}>
      <p className="text-xs font-semibold uppercase tracking-[0.06em] text-muted-foreground">{label}</p>
      <p className="mt-1 break-words text-sm text-foreground">{displayText(value)}</p>
    </div>
  );
}

function DetailSection({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section className="rounded-xl border border-border bg-card p-4 sm:p-5">
      <h3 className="mb-3 text-xs font-bold uppercase tracking-[0.1em] text-[#0F9488]">{title}</h3>
      <div className="grid gap-x-6 gap-y-3 sm:grid-cols-2">{children}</div>
    </section>
  );
}

type ProducerDecision = { producer: PendingProducer; status: 'Verified' | 'Rejected' };

/**
 * Confirmation dialog for an approve/reject decision.
 *
 * Shows who the decision is about before anything is sent, blocks a second click while the
 * request is in flight, and reports the real backend outcome. Kept as its own component so the
 * decision is never read through a nullable value inside a callback.
 */
function DecisionDialog({
  decision,
  busy,
  onCancel,
  onConfirm,
}: {
  decision: ProducerDecision;
  busy: boolean;
  onCancel: () => void;
  onConfirm: (status: 'Verified' | 'Rejected', reason: string) => void | Promise<void>;
}) {
  const { producer, status } = decision;
  const {lang}=useLanguage(); const tr=(en:string,fil:string)=>lang==='fil'?fil:en;
  const [reason,setReason]=useState('');
  return <Dialog open onOpenChange={open=>{if(!open&&!busy)onCancel()}}><DialogContent closeDisabled={busy} className="bg-card text-foreground"><DialogHeader><DialogTitle>{status==='Verified'?tr('Approve Registration','Aprubahan ang Rehistrasyon'):tr('Reject Registration','Tanggihan ang Rehistrasyon')}</DialogTitle><DialogDescription>{tr('Review the applicant before confirming.','Suriin ang aplikante bago kumpirmahin.')}</DialogDescription></DialogHeader>
  <dl className="space-y-2 text-sm"><div><dt>{tr('Applicant','Aplikante')}</dt><dd>{producer.name}</dd></div><div><dt>RSBSA</dt><dd>{producer.rsbsa_number}</dd></div><div><dt>{tr('Location','Lokasyon')}</dt><dd>{producer.location}</dd></div><div><dt>{tr('Registry match','Pagtutugma sa rehistro')}</dt><dd>{producer.registry_match?.status}</dd></div></dl>
  {status==='Rejected'&&<label htmlFor="registration-reason" className="space-y-2 text-sm font-semibold">{tr('Applicant-facing reason (required)','Dahilang ipapakita sa aplikante (kinakailangan)')}<textarea id="registration-reason" minLength={10} maxLength={2000} disabled={busy} value={reason} onChange={e=>setReason(e.target.value)} className="block min-h-24 w-full rounded-lg border border-border bg-background p-3 text-foreground"/><span className="text-xs text-muted-foreground">{tr('At least 10 characters. Exclude internal notes and sensitive registry details.','Hindi bababa sa 10 karakter. Huwag isama ang panloob na tala at sensitibong detalye sa rehistro.')}</span></label>}
  <DialogFooter><Button type="button" variant="outline" disabled={busy} onClick={onCancel}>{tr('Cancel','Kanselahin')}</Button><Button type="button" disabled={busy||(status==='Rejected'&&(reason.trim().length<10||!/[\p{L}\p{N}]/u.test(reason)))} onClick={()=>void onConfirm(status,reason.trim())}>{busy?tr('Saving...','Sine-save...'):status==='Verified'?tr('Confirm approval','Kumpirmahin ang pag-apruba'):tr('Confirm rejection','Kumpirmahin ang pagtanggi')}</Button></DialogFooter>
  </DialogContent></Dialog>;
}

export default function RegistryManagement() {
  const copy = useDashboardCopy();
  const {lang}=useLanguage();const tr=(en:string,fil:string)=>lang==='fil'?fil:en;
  const submitting=useRef(false);
  const [registries, setRegistries] = useState<RegistryEntry[]>([]);
  const [pending, setPending] = useState<PendingProducer[]>([]);
  const [searchTerm, setSearchTerm] = useState('');
  const [barangay, setBarangay] = useState('all');
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalCount, setTotalCount] = useState(0);
  const [loading, setLoading] = useState(true);
  const [pendingLoading, setPendingLoading] = useState(true);
  const [error, setError] = useState('');
  const [reviewError, setReviewError] = useState('');
  const [actioning, setActioning] = useState<Record<number, string>>({});
  const [reviewSearch, setReviewSearch] = useState('');
  const [reviewStatus, setReviewStatus] = useState('all');
  const [registryRefresh, setRegistryRefresh] = useState(0);
  const [actionMessage, setActionMessage] = useState('');
  const [accountFilter, setAccountFilter] = useState('all');
  const [selectedEntry, setSelectedEntry] = useState<RegistryEntry | null>(null);

  useEffect(() => {
    setPage(1);
  }, [searchTerm, barangay]);

  useEffect(() => {
    let mounted = true;
    const timer = window.setTimeout(async () => {
      setLoading(true);
      setError('');
      try {
        const params = new URLSearchParams({ page: String(page) });
        if (searchTerm.trim()) params.set('name', searchTerm.trim());
        if (barangay !== 'all') params.set('barangay', barangay);

        const result = await request(`/api/admin/registry/search?${params.toString()}`);
        if (!mounted) return;

        if (!Array.isArray(result?.data)) throw new Error('Registry records are unavailable.');
        const list = result.data;
        setRegistries(list);
        setTotalPages(Number(result?.last_page || 1));
        setTotalCount(Number(result?.total || list.length || 0));
      } catch (err) {
        if (mounted) setError(err instanceof Error ? err.message : 'Could not load registry records.');
      } finally {
        if (mounted) setLoading(false);
      }
    }, searchTerm ? 250 : 0);

    return () => {
      mounted = false;
      window.clearTimeout(timer);
    };
  }, [searchTerm, barangay, page, registryRefresh]);

  const loadPending = async () => {
    setPendingLoading(true);
    setReviewError('');
    try {
      const result = await request('/api/admin/producers/pending');
      if (!Array.isArray(result?.data)) throw new Error('Producer review records are unavailable.');
      setPending(result.data);
    } catch (err) {
      setReviewError(err instanceof Error ? err.message : 'Could not load producer applications.');
    } finally {
      setPendingLoading(false);
    }
  };

  useEffect(() => {
    void loadPending();
  }, []);

  /** Which decision the administrator is being asked to confirm, and who it is for. */
const [pendingDecision, setPendingDecision] = useState<{ producer: PendingProducer; status: 'Verified' | 'Rejected' } | null>(null);
/** Result of the decision, shown only after the backend actually responded. */
const [decisionResult, setDecisionResult] = useState<{ tone: 'success' | 'error'; title: string; description: string } | null>(null);

const updateVerification = async (producerId: number, status: 'Verified' | 'Rejected', reason: string) => {
    if(submitting.current)return;submitting.current=true;
    setActionMessage(''); setReviewError('');
    setActioning((current) => ({ ...current, [producerId]: status }));
    try {
      await request(`/api/admin/producers/${producerId}/verification`, { method: 'PATCH', body: { verification_status: status, reason: status === 'Rejected' ? reason : null } });
      setRegistryRefresh(value=>value+1);
      await loadPending();
      // The registry was refreshed from the server, so the decision genuinely took effect.
      return true;
    } catch (err) {
      const statusCode=(err as {status?:number}).status;
      const message = statusCode===409?tr('This registration has already changed. Refresh before deciding.','Nagbago na ang rehistrasyong ito. I-refresh bago magdesisyon.'):statusCode===403?tr('You are not authorized to review this registration.','Wala kang pahintulot na suriin ang rehistrasyong ito.'):statusCode===422?tr('Review the registry match and provide a meaningful rejection reason when rejecting.','Suriin ang pagtutugma sa rehistro at magbigay ng malinaw na dahilan sa pagtanggi.'):tr('Could not save this decision. Please try again.','Hindi na-save ang desisyon. Subukan muli.');
      setReviewError(message);
      return { error: message };
    } finally {
      submitting.current=false;
      setActioning((current) => {
        const next = { ...current };
        delete next[producerId];
        return next;
      });
    }
  };

  const updateAccountStatus = async (account: NonNullable<RegistryEntry['producer_accounts']>[number]) => {
    setActionMessage(''); setReviewError('');
    const isActive = account.account_status !== 'Active';
    setActioning((current) => ({ ...current, [account.producer_id]: isActive ? 'Activating' : 'Suspending' }));
    try {
      const result = await request(`/api/admin/producers/${account.producer_id}/account-status`, {
        method: 'PATCH',
        body: { is_active: isActive },
      });
      const updateEntry = (entry: RegistryEntry): RegistryEntry => ({
        ...entry,
        producer_accounts: entry.producer_accounts?.map((item) => item.producer_id === account.producer_id
          ? { ...item, account_status: result.account_status }
          : item),
      });
      setRegistries((current) => current.map((entry) => entry.registry_id === selectedEntry?.registry_id ? updateEntry(entry) : entry));
      setSelectedEntry((current) => current ? updateEntry(current) : current);
      setActionMessage('Account status updated.');
    } catch (err) {
      setReviewError(err instanceof Error ? err.message : 'Could not update the producer account status.');
    } finally {
      setActioning((current) => {
        const next = { ...current };
        delete next[account.producer_id];
        return next;
      });
    }
  };

  const filteredPending = pending.filter(producer => [producer.name, producer.rsbsa_number, producer.email, producer.contact_number].join(' ').toLowerCase().includes(reviewSearch.trim().toLowerCase()) && (reviewStatus === 'all' || producer.verification_status.toLowerCase() === reviewStatus));
  const visibleAccounts = selectedEntry?.producer_accounts?.filter(account => accountFilter === 'all' || account.account_status?.toLowerCase() === accountFilter) ?? [];
  return (
    <div className="min-w-0 space-y-5 [&_button]:min-h-11">
      <PageHeader title={copy("Registry Management")} icon={Users} subtitle={copy("Review producer applications, search registry identities and manage linked accounts.")} />

      {actionMessage && <p role="status" className="rounded-lg border border-teal-200 bg-teal-50 p-3 text-sm text-teal-900">{actionMessage}</p>}
      <Card>
        <CardHeader><CardTitle className="flex items-center gap-2"><CheckCircle2 className="h-5 w-5 text-[#0F9488]" /><DashboardText text="Pending Producer Reviews" /></CardTitle></CardHeader>
        <CardContent>
          {reviewError && <p role="alert" className="mb-3 rounded-lg bg-red-50 p-3 text-sm text-red-800">{reviewError}</p>}
          <p className="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900"><DashboardText text="A registry match confirms matching details only; it does not verify the applicant’s identity or mobile ownership. Before approval, confirm the applicant through an established contact or an appropriate verification process. No completed contact or identity check is recorded by this screen." /></p>
          <div className="mb-4 grid gap-3 sm:grid-cols-2"><label className="min-w-0 text-sm font-semibold"><DashboardText text="Search applications" /><Input className="mt-1 min-h-11" type="search" value={reviewSearch} onChange={event => setReviewSearch(event.target.value)} placeholder={copy("Name, RSBSA or contact")} /></label><label className="min-w-0 text-sm font-semibold"><DashboardText text="Approval status" /><select value={reviewStatus} onChange={event => setReviewStatus(event.target.value)} className="mt-1 min-h-11 w-full rounded-lg border bg-card px-3"><option value="all"><DashboardText text="All applications" /></option><option value="pending"><DashboardText text="Pending" /></option><option value="rejected"><DashboardText text="Rejected" /></option></select></label></div>
          {pendingLoading ? <LoadingState message={copy("Loading pending review queue...")} /> : reviewError && pending.length === 0 ? <Button variant="outline" onClick={() => void loadPending()}><DashboardText text="Retry loading reviews" /></Button> : filteredPending.length === 0 ? (
            <p className="py-4 text-sm text-muted-foreground">{reviewSearch || reviewStatus !== 'all' ? copy("No applications match these filters.") : copy("No pending or rejected producer applications.")}</p>
          ) : <div className="space-y-4">{filteredPending.map((producer) => (
            <article key={producer.producer_id} className="space-y-3 rounded-xl border border-border bg-background p-4 [overflow-wrap:anywhere]">
              <div>
                <p className="font-bold text-foreground">{producer.name}</p>
                <p className="text-sm text-muted-foreground"><DashboardText text="Approval status:" />{' '}{producer.verification_status.toLowerCase() === 'pending' ? copy("Pending Admin Review") : producer.verification_status}</p>
                <p className="text-sm text-muted-foreground"><DashboardText text="Match outcome:" />{' '}{producer.registry_match?.status?.replaceAll('_', ' ') || copy("Not available")}</p>
                <p className="text-xs text-muted-foreground">{producer.match_evidence === 'registration_snapshot'
                  ? copy("Registry details recorded at registration: {0}", [producer.registry_matched_at || 'time unavailable'])
                  : copy("Legacy application: current name check only; original submission and exact-match evidence are unavailable.")}</p>
              </div>
              <div className="grid gap-3 lg:grid-cols-2">
                <DetailSection title={copy("Applicant’s submitted details")}>
                  {producer.submitted_details ? <>
                    <DetailField label={copy("First name")} value={producer.submitted_details.first_name} />
                    <DetailField label={copy("Middle name")} value={producer.submitted_details.middle_name} />
                    <DetailField label={copy("Last name")} value={producer.submitted_details.last_name} />
                    <DetailField label={copy("Extension / suffix")} value={producer.submitted_details.extension_name || producer.submitted_details.suffix} />
                    <DetailField label={copy("Sex")} value={producer.submitted_details.sex} />
                    <DetailField label={copy("Barangay")} value={producer.submitted_details.barangay} />
                    <DetailField label="RSBSA number" value={producer.submitted_details.rsbsa_number} />
                    <DetailField label={copy("Producer type")} value={producer.submitted_details.producer_type} />
                    <DetailField label={copy("Mobile (ownership not verified)")} value={producer.submitted_details.contact_number} />
                    <DetailField label={copy("Email (optional)")} value={producer.submitted_details.email} />
                  </> : <>
                    <p className="text-sm sm:col-span-2"><DashboardText text="Original submission not recorded. Current account contact details:" /></p>
                    <DetailField label="Mobile" value={producer.contact_number} />
                    <DetailField label={copy("Email")} value={producer.email} />
                  </>}
                </DetailSection>
                <DetailSection title={copy("Matched registry information")}>
                  {producer.matched_registry ? <>
                    <DetailField label={copy("Registry name")} value={formatFullName(producer.matched_registry)} wide />
                    <DetailField label="RSBSA number" value={producer.matched_registry.rsbsa_number} />
                    <DetailField label={copy("Sex")} value={producer.matched_registry.sex} />
                    <DetailField label={copy("Barangay")} value={producer.matched_registry.barangay} />
                    <DetailField label={copy("Municipality")} value={producer.matched_registry.municipality} />
                    <DetailField label={copy("Province")} value={producer.matched_registry.province} />
                    <DetailField label={copy("Livelihood")} value={producer.matched_registry.primary_livelihood} />
                    <DetailField label={copy("Producer type")} value={producer.matched_registry.producer_type} />
                    <DetailField label={copy("Registry contact (not independently confirmed)")} value={producer.matched_registry.mobile_number || producer.matched_registry.contact_number} />
                  </> : <>
                    <DetailField label={copy("Current registry name")} value={producer.registry_match?.registry_name} />
                    <DetailField label={copy("Current registry location")} value={producer.registry_match?.registry_location} />
                  </>}
                </DetailSection>
              </div>
              <div className="flex flex-wrap gap-2">
                <Button size="sm" variant="outline" disabled={Boolean(actioning[producer.producer_id]) || producer.verification_status.toLowerCase() !== 'pending' || producer.registry_match?.valid_match !== true} onClick={() => setPendingDecision({ producer, status: 'Verified' })}>{actioning[producer.producer_id] === 'Verified' ? tr('Updating...','Ina-update...') : tr('Approve','Aprubahan')}</Button>
                <Button size="sm" variant="outline" className="border-red-200 text-red-700 hover:bg-red-50" disabled={Boolean(actioning[producer.producer_id]) || producer.verification_status.toLowerCase() !== 'pending'} onClick={() => setPendingDecision({ producer, status: 'Rejected' })}><XCircle className="mr-1 h-4 w-4" />{actioning[producer.producer_id] === 'Rejected' ? tr('Updating...','Ina-update...') : tr('Reject','Tanggihan')}</Button>
              </div>
            </article>
          ))}</div>}
        </CardContent>
      </Card>

      <Card>
        <CardContent className="grid gap-3 p-4 md:grid-cols-[minmax(0,1fr)_280px]">
          <div className="relative">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input value={searchTerm} onChange={(event) => setSearchTerm(event.target.value)} placeholder={copy("Search by RSBSA, name, barangay, or mobile number")} className="min-h-11 pl-9" aria-label={copy("Search registry")} />
          </div>
          <Select value={barangay} onValueChange={setBarangay}>
            <SelectTrigger className="min-h-11 w-full" aria-label={copy("Filter by barangay")}>
              <SelectValue placeholder={copy("All barangays")} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all"><DashboardText text="All barangays" /></SelectItem>
              {aparriBarangays.map((name) => <SelectItem key={name} value={name}>{name}</SelectItem>)}
            </SelectContent>
          </Select>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2"><FileText className="h-5 w-5" /><DashboardText text="Registered Producers" />{' '}{!loading && !error && ` (${totalCount})`}</CardTitle>
        </CardHeader>
        <CardContent>
          {loading ? (
            <LoadingState message={copy("Loading registry records...")} />
          ) : error ? (
            <div className="py-8 text-center text-sm text-red-700" role="alert">{error}<Button variant="outline" className="ml-3" onClick={() => setRegistryRefresh(value => value + 1)}><DashboardText text="Retry" /></Button></div>
          ) : registries.length === 0 ? (
            <div className="py-8 text-center text-sm text-muted-foreground"><DashboardText text="No registry records found." /></div>
          ) : (
            <>
              <div className="space-y-3 md:hidden">{registries.map(entry => <article key={entry.registry_id} className="min-w-0 rounded-xl border border-border p-4"><h3 className="break-words font-bold">{formatFullName(entry)}</h3><dl className="mt-3 space-y-2 text-sm"><div><dt className="text-muted-foreground">RSBSA number</dt><dd className="break-words">{entry.rsbsa_number || '—'}</dd></div><div><dt className="text-muted-foreground">Barangay</dt><dd className="break-words">{entry.barangay || '—'}</dd></div><div><dt className="text-muted-foreground"><DashboardText text="Farm location" /></dt><dd className="break-words">{entry.farm_location || '—'}</dd></div></dl><Button className="mt-4 w-full" variant="outline" onClick={() => setSelectedEntry(entry)}><DashboardText text="View details & accounts" /></Button></article>)}</div>
              <div tabIndex={0} role="region" aria-label={copy("Registry records table")} className="hidden max-w-full overflow-x-auto md:block">
                <Table className="min-w-[800px]">
                  <TableHeader>
                    <TableRow>
                      <TableHead>RSBSA Number</TableHead>
                      <TableHead><DashboardText text="Full Name" /></TableHead>
                      <TableHead><DashboardText text="Sex" /></TableHead>
                      <TableHead>Barangay</TableHead>
                      <TableHead><DashboardText text="Mobile Number" /></TableHead>
                      <TableHead><DashboardText text="Farm Location" /></TableHead>
                      <TableHead><DashboardText text="Area" /></TableHead>
                      <TableHead className="text-right"><DashboardText text="Actions" /></TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {registries.map((entry) => (
                      <TableRow key={entry.registry_id}>
                        <TableCell>{entry.rsbsa_number || '—'}</TableCell>
                        <TableCell>{formatFullName(entry)}</TableCell>
                        <TableCell>{entry.sex || '—'}</TableCell>
                        <TableCell>{entry.barangay || '—'}</TableCell>
                        <TableCell>{entry.mobile_number || '—'}</TableCell>
                        <TableCell>{entry.farm_location || '—'}</TableCell>
                        <TableCell>{formatArea(entry.area_ha ?? entry.area_display)}</TableCell>
                        <TableCell className="text-right">
                          <Button size="sm" variant="outline" onClick={() => setSelectedEntry(entry)}><DashboardText text="View Details" /></Button>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>

              {totalPages > 1 && (
                <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4">
                  <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((current) => Math.max(1, current - 1))}><DashboardText text="Previous" /></Button>
                  <p className="text-sm text-muted-foreground"><DashboardText text="Page" />{' '}{page}{' '}<DashboardText text="of" />{' '}{totalPages}</p>
                  <Button variant="outline" size="sm" disabled={page >= totalPages} onClick={() => setPage((current) => Math.min(totalPages, current + 1))}><DashboardText text="Next" /></Button>
                </div>
              )}
            </>
          )}
        </CardContent>
      </Card>

      <Dialog open={Boolean(selectedEntry)} onOpenChange={(open) => { if (!open) { setSelectedEntry(null); setAccountFilter('all'); } }}>
        <DialogContent className="flex max-h-[min(90dvh,calc(100dvh-2rem))] w-[min(900px,calc(100vw-24px))] max-w-[calc(100vw-32px)] flex-col gap-0 overflow-hidden rounded-2xl border border-border bg-background p-0 shadow-2xl sm:max-w-[min(900px,calc(100vw-32px))] sm:p-0">
          <DialogHeader className="shrink-0 border-b border-border px-5 py-4 pr-16 text-left sm:px-7 sm:py-5">
            <DialogTitle className="font-display break-words text-xl leading-tight text-foreground sm:text-2xl">
              {selectedEntry ? formatFullName(selectedEntry) : copy("Registry Details")}
            </DialogTitle>
            <DialogDescription className="break-words text-sm text-muted-foreground"><DashboardText text="Registry Profile" />{' '}{selectedEntry?.rsbsa_number ? ` · RSBSA: ${selectedEntry.rsbsa_number}` : ''}
            </DialogDescription>
          </DialogHeader>

          {selectedEntry && (
            <div className="min-h-0 flex-1 overflow-y-auto px-5 py-5 sm:px-7 sm:py-6">
              {reviewError && <p role="alert" className="mb-3 rounded-lg bg-red-50 p-3 text-sm text-red-800">{reviewError}</p>}
              <div className="grid gap-4 lg:grid-cols-2">
                <DetailSection title={copy("Registry Information")}>
                  <DetailField label={copy("RSBSA Number")} value={selectedEntry.rsbsa_number} />
                  <DetailField label={copy("Sex")} value={selectedEntry.sex} />
                </DetailSection>

                <DetailSection title={copy("Personal Information")}>
                  <DetailField label={copy("First Name")} value={selectedEntry.first_name} />
                  <DetailField label={copy("Middle Name")} value={selectedEntry.middle_name} />
                  <DetailField label={copy("Last Name")} value={selectedEntry.last_name} />
                  <DetailField label={copy("Extension")} value={selectedEntry.extension_name} />
                </DetailSection>

                <DetailSection title={copy("Residential Address")}>
                  <DetailField label={copy("House Number")} value={selectedEntry.house_number} />
                  <DetailField label={copy("Zone")} value={selectedEntry.zone} />
                  <DetailField label={copy("Street")} value={selectedEntry.street} />
                  <DetailField label={copy("Barangay")} value={selectedEntry.barangay} />
                  <DetailField label={copy("Municipality")} value={selectedEntry.municipality} />
                  <DetailField label={copy("Province")} value={selectedEntry.province} />
                  <DetailField label={copy("Region")} value={selectedEntry.region} wide />
                </DetailSection>

                <DetailSection title={copy("Contact & Farm Information")}>
                  <DetailField label={copy("Mobile Number")} value={selectedEntry.mobile_number} />
                  <DetailField label={copy("Area")} value={formatArea(selectedEntry.area_ha ?? selectedEntry.area_display)} />
                  <DetailField label={copy("Farm Location")} value={selectedEntry.farm_location} wide />
                </DetailSection>

                <section className="rounded-xl border border-border bg-card p-4 lg:col-span-2 sm:p-5">
                  <h3 className="mb-3 text-xs font-bold uppercase tracking-[0.1em] text-[#0F9488]"><DashboardText text="HarborAI Account Associations" /></h3>
                  {actionMessage && <p role="status" className="mb-3 text-sm text-teal-800">{actionMessage}</p>}
                  <label className="mb-3 block text-sm font-semibold"><DashboardText text="Account status" /><select value={accountFilter} onChange={event => setAccountFilter(event.target.value)} className="mt-1 min-h-11 w-full rounded-lg border bg-card px-3"><option value="all"><DashboardText text="All linked accounts" /></option>{Array.from(new Set((selectedEntry.producer_accounts ?? []).map(account => account.account_status).filter((value): value is string => typeof value === 'string' && value.length > 0))).map(value => <option key={value} value={value.toLowerCase()}>{value}</option>)}</select></label>
                  {visibleAccounts.length ? (
                    <div className="grid gap-3 lg:grid-cols-2">
                      {visibleAccounts.map((account) => (
                        <div key={account.producer_id} className="rounded-lg border border-border bg-[#F8FAF7] p-3 [overflow-wrap:anywhere]">
                          <p className="font-semibold text-foreground">{account.name}</p>
                          <p className="mt-1 text-sm text-muted-foreground">{account.email || copy("No email")} · {account.contact_number || copy("No account mobile")}</p>
                          <p className="mt-1 text-xs text-muted-foreground">
                            {account.producer_type || account.user_type || copy("Producer")}{' '}<DashboardText text="· Approval:" />{' '}{account.verification_status === 'Verified' ? copy("Approved") : account.verification_status}{' '}<DashboardText text="· Account:" />{' '}{account.account_status || copy("Unknown")}
                          </p>
                          <Button
                            size="sm"
                            variant="outline"
                            className="mt-3 min-h-11"
                            disabled={Boolean(actioning[account.producer_id])}
                            onClick={() => { if (window.confirm((account.account_status === 'Active' ? 'Suspend' : 'Activate') + ' the account for ' + account.name + '?')) void updateAccountStatus(account); }}
                          >
                            {actioning[account.producer_id] || (account.account_status === 'Active' ? copy("Suspend account") : copy("Activate account"))}
                          </Button>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <p className="text-sm text-muted-foreground">{accountFilter !== 'all' ? copy("No linked accounts match this status.") : copy("No HarborAI producer account is linked to this registry identity.")}</p>
                  )}
                </section>
              </div>
            </div>
          )}

          <DialogFooter className="shrink-0 border-t border-border bg-background px-5 py-4 sm:px-7">
            <DialogClose asChild>
              <Button variant="outline"><DashboardText text="Close" /></Button>
            </DialogClose>
          </DialogFooter>
        </DialogContent>
      </Dialog>
  {pendingDecision ? (() => {
    // Non-null throughout this branch; TypeScript cannot narrow a nullable state value
    // inside the nested callback, so the assertion is stated once, explicitly.
    const decision = pendingDecision as ProducerDecision;
    return (
      <DecisionDialog
        decision={decision}
        busy={Boolean(actioning[decision.producer.producer_id])}
        onCancel={() => {if(!submitting.current)setPendingDecision(null)}}
        onConfirm={async (status, reason) => {
          if(submitting.current)return;
          const producer = decision.producer;
          const outcome = await updateVerification(producer.producer_id, status, reason);
          setPendingDecision(null);
          if (outcome === true) {
            setDecisionResult({
              tone: 'success',
              title: status === 'Verified' ? tr('Registration Approved','Naaprubahan ang Rehistrasyon') : tr('Registration Rejected','Tinanggihan ang Rehistrasyon'),
              description: status==='Verified'?tr('Farmer registration approved successfully.','Matagumpay na naaprubahan ang rehistrasyon ng magsasaka.'):tr("Farmer registration rejected. The applicant's status has been updated.",'Tinanggihan ang rehistrasyon ng magsasaka. Na-update ang katayuan ng aplikante.'),
            });
          } else if (outcome && typeof outcome === 'object' && 'error' in outcome) {
            // A backend failure is reported as a failure, never as a success.
            setDecisionResult({
              tone: 'error',
              title: status === 'Verified' ? tr('Approval Failed','Nabigo ang Pag-apruba') : tr('Rejection Failed','Nabigo ang Pagtanggi'),
              description: String(outcome.error),
            });
          }
        }}
      />
    );
  })() : null}

  {decisionResult ? (
    (() => {
      const outcome = decisionResult as { tone: 'success' | 'error'; title: string; description: string };
      return (
        <FeedbackModal
          open
          tone={outcome.tone}
          title={outcome.title}
          description={outcome.description}
          onClose={() => setDecisionResult(null)}
          onCloseLabel={copy("Done")}
        />
      );
    })()
  ) : null}
    </div>
  );


}