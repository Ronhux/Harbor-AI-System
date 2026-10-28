import { DashboardText, useDashboardCopy } from '../../../i18n/DashboardCopy';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Building2, Loader2, Search, ShieldAlert, X } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../components/ui/card';
import { Button } from '../../../components/ui/button';
import { Input } from '../../../components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../../../components/ui/table';
import FeedbackModal from '../../../components/ui/feedback-modal';
import PageHeader from '../../../components/dashboard/PageHeader';
import { request } from '../../../../lib/api';

/**
 * FCA Registry Management (administrative view).
 *
 * Uses `GET /api/fca/registry-records`, which retains authentication and policy acceptance
 * with separate read-only Admin authorization, independent of the FCA workflow gate, so an administrator sees the listed president and each real
 * verification status. That is deliberately a DIFFERENT endpoint from the public registration
 * autocomplete, which returns only identifier, name and address and never exposes a president
 * name or a status to an anonymous visitor.
 */

type Row = {
  id: number;
  source_record_key?: string | null;
  official_name: string;
  address: string;
  president_name?: string | null;
  directory_verification_status: string;
  procurement_eligibility_status: string;
  registry_source?: string | null;
  president_identity_verified?: boolean;
  is_verified_directory_entry?: boolean;
};

const PAGE_SIZE = 10;

export default function FcaRegistryManagement() {
  const copy = useDashboardCopy();
  const [rows, setRows] = useState<Row[]>([]);
  const [total, setTotal] = useState(0);
  const [verifiedCount, setVerifiedCount] = useState(0);
  const [notice, setNotice] = useState('');
  const [term, setTerm] = useState('');
  const [status, setStatus] = useState<'all' | 'pending' | 'verified'>('all');
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [detail, setDetail] = useState<Row | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const result = await request('/api/fca/registry-records?limit=50') as {
        data?: Row[]; total_in_directory?: number; verified_count?: number; notice?: string;
      };
      setRows(result?.data ?? []);
      setTotal(result?.total_in_directory ?? (result?.data?.length ?? 0));
      setVerifiedCount(result?.verified_count ?? 0);
      setNotice(result?.notice ?? '');
    } catch (e) {
      setError(e instanceof Error ? e.message : 'The FCA directory could not be loaded.');
      setRows([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { void load(); }, [load]);

  const filtered = useMemo(() => {
    const q = term.trim().toLowerCase();
    return rows.filter((r) => {
      if (status !== 'all' && r.directory_verification_status !== status) return false;
      if (!q) return true;
      return (
        r.official_name.toLowerCase().includes(q)
        || r.address.toLowerCase().includes(q)
        || (r.president_name ?? '').toLowerCase().includes(q)
      );
    });
  }, [rows, term, status]);

  const pageCount = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
  const safePage = Math.min(page, pageCount);
  const visible = filtered.slice((safePage - 1) * PAGE_SIZE, safePage * PAGE_SIZE);

  const badge = (value: string) => (
    <span className={`inline-block rounded-full px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide ${
      value === 'verified'
        ? 'bg-emerald-100 text-emerald-800'
        : 'bg-amber-100 text-amber-800'
    }`}>
      {value === 'verified' ? copy("Confirmed") : copy("Pending confirmation")}
    </span>
  );

  return (
    <div className="space-y-4">
      <PageHeader
        title={copy("FCA Registry Management")}
        icon={Building2}
        subtitle={copy("Associations and cooperatives supplied by the project owner. Listed presidents are not verified identities.")}
      />

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-foreground">
            <Building2 className="h-5 w-5 text-[#0F9488]" aria-hidden="true" /><DashboardText text="Directory records" /></CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid gap-3 sm:grid-cols-3">
            <div className="rounded-xl border-2 border-[#D8E4E8] bg-background p-3">
              <p className="text-2xl font-black text-foreground">{loading ? '—' : total}</p>
              <p className="text-xs font-semibold text-muted-foreground"><DashboardText text="Records in directory" /></p>
            </div>
            <div className="rounded-xl border-2 border-[#D8E4E8] bg-background p-3">
              <p className="text-2xl font-black text-amber-700">{loading ? '—' : total - verifiedCount}</p>
              <p className="text-xs font-semibold text-muted-foreground"><DashboardText text="Awaiting confirmation" /></p>
            </div>
            <div className="rounded-xl border-2 border-[#D8E4E8] bg-background p-3">
              <p className="text-2xl font-black text-emerald-700">{loading ? '—' : verifiedCount}</p>
              <p className="text-xs font-semibold text-muted-foreground"><DashboardText text="Independently confirmed" /></p>
            </div>
          </div>

          {notice && (
            <p className="flex items-start gap-2 rounded-xl border border-amber-300 bg-amber-50 p-3 text-xs leading-relaxed text-amber-900">
              <ShieldAlert className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
              {notice}
            </p>
          )}

          <div className="grid gap-3 sm:grid-cols-2">
            <div className="relative">
              <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
              <Input
                value={term}
                onChange={(e) => { setTerm(e.target.value); setPage(1); }}
                placeholder={copy("Search name, address or president")}
                aria-label={copy("Search the FCA directory")}
                className="pl-9"
              />
            </div>
            <div>
              <select
                value={status}
                onChange={(e) => { setStatus(e.target.value as typeof status); setPage(1); }}
                aria-label={copy("Filter by confirmation status")}
                className="h-10 w-full rounded-xl border border-[#D8E4E8] bg-card px-3 text-sm"
              >
                <option value="all"><DashboardText text="All statuses" /></option>
                <option value="pending"><DashboardText text="Pending confirmation" /></option>
                <option value="verified"><DashboardText text="Confirmed" /></option>
              </select>
            </div>
          </div>

          {error && <p role="alert" className="text-sm font-semibold text-red-700">{error}</p>}

          {loading ? (
            <p className="flex items-center justify-center gap-2 py-10 text-sm text-muted-foreground">
              <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" /><DashboardText text="Loading the FCA directory…" /></p>
          ) : filtered.length === 0 ? (
            <div className="rounded-xl border-2 border-dashed border-[#D8E4E8] bg-background p-8 text-center">
              <p className="font-semibold text-foreground"><DashboardText text="No matching records" /></p>
              <p className="mt-1 text-sm text-muted-foreground">
                {rows.length === 0
                  ? copy("The directory currently holds no records.")
                  : copy("Adjust the search term or status filter.")}
              </p>
            </div>
          ) : (
            <>
              <div className="overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead><DashboardText text="Association / Cooperative" /></TableHead>
                      <TableHead><DashboardText text="Address" /></TableHead>
                      <TableHead><DashboardText text="Listed president" /></TableHead>
                      <TableHead><DashboardText text="Source status" /></TableHead>
                      <TableHead><span className="sr-only"><DashboardText text="Actions" /></span></TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {visible.map((r) => (
                      <TableRow key={r.id}>
                        <TableCell className="font-semibold text-foreground">{r.official_name}</TableCell>
                        <TableCell className="text-sm text-muted-foreground">{r.address}</TableCell>
                        <TableCell className="text-sm text-muted-foreground">
                          {r.president_name ?? '—'}
                          <span className="block text-[11px] text-[#B45309]"><DashboardText text="listed, not verified" /></span>
                        </TableCell>
                        <TableCell>{badge(r.directory_verification_status)}</TableCell>
                        <TableCell>
                          <Button size="sm" variant="outline" onClick={() => setDetail(r)}><DashboardText text="Details" /></Button>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>

              <div className="flex items-center justify-between gap-3">
                <p className="text-xs text-muted-foreground"><DashboardText text="Showing" />{' '}{(safePage - 1) * PAGE_SIZE + 1}–{Math.min(safePage * PAGE_SIZE, filtered.length)}{' '}<DashboardText text="of" />{' '}{filtered.length}
                </p>
                <div className="flex gap-2">
                  <Button size="sm" variant="outline" disabled={safePage <= 1} onClick={() => setPage(safePage - 1)}><DashboardText text="Previous" /></Button>
                  <Button size="sm" variant="outline" disabled={safePage >= pageCount} onClick={() => setPage(safePage + 1)}><DashboardText text="Next" /></Button>
                </div>
              </div>
            </>
          )}
        </CardContent>
      </Card>

      {detail && (
        <FeedbackModal
          open
          tone="success"
          title={detail.official_name}
          description={copy("Full directory record. Selecting or reviewing this record does not verify the association or the listed president.")}
          detail={
            <dl className="mt-2 space-y-1 text-left text-sm">
              <div><dt className="inline font-semibold"><DashboardText text="Address:" /></dt> <dd className="inline">{detail.address}</dd></div>
              <div><dt className="inline font-semibold"><DashboardText text="Listed president:" /></dt> <dd className="inline">{detail.president_name ?? '—'}</dd></div>
              <div><dt className="inline font-semibold"><DashboardText text="Directory status:" /></dt> <dd className="inline">{detail.directory_verification_status}</dd></div>
              <div><dt className="inline font-semibold"><DashboardText text="Procurement eligibility:" /></dt> <dd className="inline">{detail.procurement_eligibility_status}</dd></div>
              <div><dt className="inline font-semibold"><DashboardText text="Source:" /></dt> <dd className="inline">{detail.registry_source ?? '—'}</dd></div>
              <div><dt className="inline font-semibold"><DashboardText text="Source key:" /></dt> <dd className="inline">{detail.source_record_key ?? '—'}</dd></div>
            </dl>
          }
          onClose={() => setDetail(null)}
          onCloseLabel={copy("Close")}
        />
      )}

      {rows.length === 0 && !loading && !error && (
        <p className="flex items-center gap-2 text-xs text-muted-foreground">
          <X className="h-3.5 w-3.5" aria-hidden="true" /><DashboardText text="An authorized import has not been applied yet, so this page is showing the empty database state." /></p>
      )}
    </div>
  );
}