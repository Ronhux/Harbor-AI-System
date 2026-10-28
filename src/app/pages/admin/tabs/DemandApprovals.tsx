import { DashboardText, useDashboardCopy } from '../../../i18n/DashboardCopy';
import { useLanguage } from '../../../i18n/useLanguage';
import { useNotificationRecord } from '../../../contracts/useNotificationRecord';
import { readDemandReviews, type DemandReview as Review } from '../../../contracts/adminDemandReviews';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { CheckCircle2, ClipboardCheck, FileText, Loader2, Search, XCircle } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../components/ui/card';
import { Button } from '../../../components/ui/button';
import { Input } from '../../../components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../../../components/ui/table';
import FeedbackModal from '../../../components/ui/feedback-modal';
import PageHeader from '../../../components/dashboard/PageHeader';
import { request } from '../../../../lib/api';

/**
 * Demand Approvals.
 *
 * Institutional buyers post demand requests and those requests enter an administrative
 * publication review. This page lists them and records the decision.
 *
 * It calls the EXISTING infrastructure: GET /api/admin/demands and POST
 * /api/admin/demands/{id}/review, both behind EnsureUserType:Admin. Nothing about the review
 * workflow is reimplemented here.
 *
 * SAFETY OF THE DECISION
 * ----------------------
 * - A decision is only recorded after the backend accepts it. The success dialog is shown from
 *   the server's response, never optimistically.
 * - The backend guards against a stale decision with expected_review_id, so two administrators
 *   cannot silently overwrite each other; a 409 is reported as "reload", not as success.
 * - Approving publishes the request for producers. It creates NO order, NO payment and NO
 *   negotiation. That is deliberately not done from this screen.
 * - Rejecting requires a reason and preserves the request with that reason attached.
 */

type Filter = 'pending' | 'approved' | 'rejected' | 'all';

const FILTERS: { id: Filter; label: string }[] = [
  { id: 'pending', label: 'Pending Review' },
  { id: 'approved', label: 'Approved' },
  { id: 'rejected', label: 'Rejected' },
  { id: 'all', label: 'All Requests' },
];

const pendingOf = (r: Review) => ['Pending Review', 'Legacy Unreviewed'].includes(r.review_status ?? '') && ['Active', 'Offers Received', 'open'].includes(r.status);
const approvedOf = (r: Review) => (r.review_status ?? '').toLowerCase() === 'approved';
const rejectedOf = (r: Review) => (r.review_status ?? '').toLowerCase() === 'rejected';

export default function DemandApprovals() {
  const copy = useDashboardCopy();
  const { t } = useLanguage();
  const [rows, setRows] = useState<Review[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [filter, setFilter] = useState<Filter>('pending');
  const [term, setTerm] = useState('');
  const [detail, setDetail] = useState<Review | null>(null);
  const [decision, setDecision] = useState<{ review: Review; action: 'approve' | 'reject' } | null>(null);
  const [reason, setReason] = useState('');
  const [busy, setBusy] = useState(false);
  const [outcome, setOutcome] = useState<{ tone: 'success' | 'error'; title: string; description: string } | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const result: unknown = await request('/api/admin/demands');
      setRows(readDemandReviews(result));
    } catch (e) {
      // Never surface a framework message such as "Unauthenticated." or an exception dump.
      const message = e instanceof Error ? e.message : '';
      setError(
        /unauthenticated|unauthorized|401/i.test(message)
          ? 'Sign in as an administrator to review demand requests.'
          : /419/i.test(message)
            ? 'Your session expired. Sign in again to continue.'
            : 'Demand requests could not be loaded. Please try again.',
      );
      setRows([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { void load(); const refresh = () => { if (document.visibilityState === 'visible') void load(); }; const timer = window.setInterval(refresh, 45000); window.addEventListener('focus', refresh); return () => { window.clearInterval(timer); window.removeEventListener('focus', refresh); }; }, [load]);

  useNotificationRecord('demands', !loading && !error, id => {
    const row = rows.find(r => r.id === id);
    if (row) {setFilter('all');setTerm('');setDetail(row);}
    else setError('This demand is not available in your review queue.');
  });

  const counts = useMemo(() => ({
    total: rows.length,
    pending: rows.filter(pendingOf).length,
    approved: rows.filter(approvedOf).length,
    rejected: rows.filter(rejectedOf).length,
  }), [rows]);

  const visible = useMemo(() => {
    const q = term.trim().toLowerCase();
    return rows.filter((r) => {
      if (filter === 'pending' && !pendingOf(r)) return false;
      if (filter === 'approved' && !approvedOf(r)) return false;
      if (filter === 'rejected' && !rejectedOf(r)) return false;
      if (!q) return true;
      const haystack = [
        r.product_name, r.product, r.category, r.location,
        r.buyer,
      ].filter(Boolean).join(' ').toLowerCase();
      return haystack.includes(q);
    });
  }, [rows, filter, term]);

  const submit = async () => {
    if (!decision || busy) return;
    if (decision.action === 'reject' && reason.trim().length < 3) return;
    setBusy(true);
    try {
      await request(`/api/admin/demands/${decision.review.id}/review`, {
        method: 'POST',
        body: {
          decision: decision.action === 'approve' ? 'Approved' : 'Rejected',
          reason: decision.action === 'reject' ? reason.trim() : null,
          // Guards against a stale decision: the server rejects a mismatch with 409.
          expected_review_id: Number(decision.review.review_id ?? 0),
        },
      });

      const approved = decision.action === 'approve';
      setDecision(null);
      setReason('');
      await load();
      setOutcome({
        tone: 'success',
        title: approved ? 'Demand Approved' : 'Demand Rejected',
        description: approved
          ? 'The demand request has been approved and is now published for producers.'
          : 'The demand request has been rejected. It stays on record with your reason attached.',
      });
    } catch (e) {
      const message = e instanceof Error ? e.message : '';
      const stale = /409|changed|reload/i.test(message);
      setDecision(null);
      setOutcome({
        tone: 'error',
        title: stale ? 'Demand Changed' : 'Decision Not Recorded',
        description: stale
          ? 'Another administrator changed this request. Nothing was decided here — reload to see its current state.'
          : 'The decision was not recorded. Please try again.',
      });
      if (stale) await load();
    } finally {
      setBusy(false);
    }
  };

  const statusBadge = (r: Review) => {
    const s = (r.review_status ?? 'unknown').toLowerCase();
    if (approvedOf(r)) return <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-bold uppercase text-emerald-800">{t('demandWorkflow.approved')}</span>;
    if (rejectedOf(r)) return <span className="rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-bold uppercase text-red-800">{t('demandWorkflow.rejected')}</span>;
    if (pendingOf(r)) return <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold uppercase text-amber-800">{t('demandWorkflow.pending')}</span>;
    return <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold uppercase text-slate-700">{r.review_status ?? copy("Unknown")}</span>;
  };

  return (
    <div className="space-y-4">
      <PageHeader
        title={t('admin.demandApprovals')}
        icon={ClipboardCheck}
        subtitle={copy("Institutional demand requests awaiting an administrative publication decision.")}
      />

      <div className="grid gap-3 sm:grid-cols-4">
        {[
          ['Total Requests', counts.total, 'bg-background text-foreground'],
          ['Pending Review', counts.pending, 'bg-amber-50 text-amber-800'],
          ['Approved', counts.approved, 'bg-emerald-50 text-emerald-800'],
          ['Rejected', counts.rejected, 'bg-red-50 text-red-800'],
        ].map(([label, value, cls]) => (
          <div key={String(label)} className={`rounded-xl border-2 border-[#D8E4E8] p-3 ${cls}`}>
            <p className="text-2xl font-black">{loading ? '—' : value}</p>
            <p className="text-xs font-semibold opacity-80">{label}</p>
          </div>
        ))}
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-foreground"><DashboardText text="Institutional demand requests" /></CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex flex-wrap gap-2">
            {FILTERS.map((f) => (
              <button
                key={f.id}
                type="button"
                onClick={() => setFilter(f.id)}
                aria-pressed={filter === f.id}
                className={`rounded-full px-3 py-1.5 text-xs font-bold transition ${
                  filter === f.id
                    ? 'bg-[#0F9488] text-white'
                    : 'border border-[#D8E4E8] bg-card text-foreground hover:bg-[#F0FDFA]'
                }`}
              >
                {t('demandWorkflow.' + ({pending:'pending',approved:'approved',rejected:'rejected',all:'all'}[f.id]))}
              </button>
            ))}
            <div className="relative ml-auto w-full sm:w-64">
              <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
              <Input value={term} onChange={(e) => setTerm(e.target.value)} placeholder={copy("Search commodity or institution")} aria-label={copy("Search demand requests")} className="pl-9" />
            </div>
          </div>

          {error && <p role="alert" className="rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-semibold text-red-800">{error}</p>}

          {loading ? (
            <p className="flex items-center justify-center gap-2 py-10 text-sm text-muted-foreground">
              <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" /><DashboardText text="Loading demand requests…" /></p>
          ) : visible.length === 0 ? (
            <div className="rounded-xl border-2 border-dashed border-[#D8E4E8] bg-background p-8 text-center">
              <p className="font-semibold text-foreground"><DashboardText text="Nothing to show" /></p>
              <p className="mt-1 text-sm text-muted-foreground">
                {rows.length === 0
                  ? copy("No demand requests have been submitted yet.")
                  : copy("No requests match this filter.")}
              </p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead><DashboardText text="Institution" /></TableHead>
                    <TableHead><DashboardText text="Commodity" /></TableHead>
                    <TableHead><DashboardText text="Quantity" /></TableHead>
                    <TableHead><DashboardText text="Budget" /></TableHead>
                    <TableHead><DashboardText text="Submitted" /></TableHead>
                    <TableHead><DashboardText text="Status" /></TableHead>
                    <TableHead><span className="sr-only"><DashboardText text="Actions" /></span></TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {visible.map((r) => (
                    <TableRow key={r.id}>
                      <TableCell className="font-semibold text-foreground">
                        {r.buyer || copy("Institution not recorded")}
                      </TableCell>
                      <TableCell>{r.product_name || r.product || '—'}</TableCell>
                      <TableCell>
                        {r.quantity_needed ?? r.quantity ?? '—'} {r.unit || ''}
                      </TableCell>
                      <TableCell>
                        {r.max_price_per_unit != null
                          ? `₱${Number(r.max_price_per_unit).toFixed(2)} / ${r.unit || 'unit'}`
                          : r.target_price || copy("Not stated")}
                      </TableCell>
                      <TableCell className="text-sm text-muted-foreground">
                        {r.created_at ? new Date(r.created_at).toLocaleDateString() : '—'}
                      </TableCell>
                      <TableCell>{statusBadge(r)}</TableCell>
                      <TableCell>
                        <div className="flex gap-2">
                          <Button size="sm" variant="outline" onClick={() => setDetail(r)}>{t('demandWorkflow.details')}</Button>
                          {pendingOf(r) && r.review_available !== false && (
                            <>
                              <Button size="sm" onClick={() => { setDecision({ review: r, action: 'approve' }); setReason(''); }}>{t('demandWorkflow.approve')}</Button>
                              <Button size="sm" variant="outline" className="border-red-200 text-red-700 hover:bg-red-50"
                                onClick={() => { setDecision({ review: r, action: 'reject' }); setReason(''); }}><DashboardText text="Reject" /></Button>
                            </>
                          )}
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Details */}
      {detail && (
        <FeedbackModal
          open
          tone="success"
          title={detail.product_name || detail.product || copy("Demand request")}
          description={copy("Full record. Approval publishes this request for producers; it creates no order, payment or negotiation.")}
          detail={
            <dl className="mt-2 space-y-1 text-left text-sm">
              <div><dt className="inline font-semibold"><DashboardText text="Institution:" /></dt> <dd className="inline">{detail.buyer || copy("Not recorded")}</dd></div>
              <div><dt className="inline font-semibold"><DashboardText text="Quantity:" /></dt> <dd className="inline">{detail.quantity_needed ?? detail.quantity ?? '—'} {detail.unit || ''}</dd></div>
              <div><dt className="inline font-semibold"><DashboardText text="Budget:" /></dt> <dd className="inline">{detail.max_price_per_unit != null ? `₱${Number(detail.max_price_per_unit).toFixed(2)} / ${detail.unit || 'unit'}` : detail.target_price || copy("Not stated")}</dd></div>
              <div><dt className="inline font-semibold"><DashboardText text="Delivery by:" /></dt> <dd className="inline">{detail.preferred_delivery_date || detail.deadline || copy("Not stated")}</dd></div>
              <div><dt className="inline font-semibold"><DashboardText text="Location:" /></dt> <dd className="inline">{detail.location || copy("Not stated")}</dd></div>
              <div><dt className="inline font-semibold"><DashboardText text="Quality:" /></dt> <dd className="inline">{detail.quality_requirements || copy("Not stated")}</dd></div>
              <div><dt className="inline font-semibold"><DashboardText text="Special requirements:" /></dt> <dd className="inline">{detail.special_requirements || copy("Not stated")}</dd></div>
              <div><dt className="inline font-semibold"><DashboardText text="Partial fulfillment:" /></dt> <dd className="inline">{detail.partial_fulfillment === null ? copy("Not stated") : detail.partial_fulfillment ? copy("Allowed") : copy("Not allowed")}</dd></div>
              <div><dt className="inline font-semibold"><DashboardText text="Offers:" /></dt> <dd className="inline">{detail.offer_count ?? 0}</dd></div>
              <div><dt className="inline font-semibold"><DashboardText text="Review status:" /></dt> <dd className="inline">{detail.review_status || copy("Unknown")}</dd></div>
              {detail.review_reason && (
                <div><dt className="inline font-semibold"><DashboardText text="Last reason:" /></dt> <dd className="inline">{detail.review_reason}</dd></div>
              )}
              {detail.review_history && detail.review_history.length > 0 && (
                <div className="mt-2">
                  <dt className="font-semibold"><DashboardText text="Review history" /></dt>
                  <dd className="mt-1 space-y-1">
                    {detail.review_history.map((h, i) => (
                      <p key={h.id ?? i} className="text-xs text-muted-foreground">
                        {h.decision}{h.reason ? ` — ${h.reason}` : ''}{h.created_at ? ` (${new Date(h.created_at).toLocaleString()})` : ''}
                      </p>
                    ))}
                  </dd>
                </div>
              )}
            </dl>
          }
          onClose={() => setDetail(null)}
          onCloseLabel={copy("Close")}
        />
      )}

      {/* Approve / Reject confirmation — never a browser alert */}
      {decision && (
        <FeedbackModal
          open
          tone={decision.action === 'approve' ? 'success' : 'error'}
          title={decision.action === 'approve' ? copy("Approve Demand") : copy("Reject Demand")}
          description={
            decision.action === 'approve'
              ? copy("Approve the demand request for {0}? It will be published for producers.", [decision.review.product_name || decision.review.product || 'this commodity'])
              : copy("Reject the demand request for {0}?", [decision.review.product_name || decision.review.product || 'this commodity'])
          }
          detail={
            <div className="mt-2 text-left">
              <dl className="space-y-1 text-sm">
                <div><dt className="inline font-semibold"><DashboardText text="Institution:" /></dt> <dd className="inline">{decision.review.buyer || copy("Not recorded")}</dd></div>
                <div><dt className="inline font-semibold"><DashboardText text="Quantity:" /></dt> <dd className="inline">{decision.review.quantity_needed ?? decision.review.quantity ?? '—'} {decision.review.unit || ''}</dd></div>
              </dl>
              {decision.action === 'reject' && (
                <label className="mt-3 block text-left">
                  <span className="text-xs font-bold uppercase tracking-wide text-muted-foreground"><DashboardText text="Reason (required)" /></span>
                  <textarea
                    value={reason}
                    onChange={(e) => setReason(e.target.value)}
                    maxLength={2000}
                    rows={3}
                    placeholder={copy("Explain why this request is being rejected.")}
                    className="mt-1 w-full rounded-lg border-2 border-[#D8E4E8] px-3 py-2 text-sm outline-none focus:border-[#0F9488]"
                  />
                  {reason.trim().length > 0 && reason.trim().length < 3 && (
                    <span className="mt-1 block text-xs font-semibold text-red-700"><DashboardText text="Please give a short reason." /></span>
                  )}
                </label>
              )}
            </div>
          }
          busy={busy}
          onClose={() => !busy && setDecision(null)}
          onCloseLabel={copy("Cancel")}
          {...(decision.action === 'approve' || reason.trim().length >= 3
            ? {
                action: {
                  label: busy ? 'Saving…' : decision.action === 'approve' ? 'Approve Demand' : 'Reject Demand',
                  onClick: () => void submit(),
                },
              }
            : {})}
        />
      )}

      {/* Outcome */}
      {outcome && (
        <FeedbackModal
          open
          tone={outcome.tone}
          title={outcome.title}
          description={outcome.description}
          onClose={() => setOutcome(null)}
          onCloseLabel={copy("Done")}
        />
      )}
    </div>
  );
}