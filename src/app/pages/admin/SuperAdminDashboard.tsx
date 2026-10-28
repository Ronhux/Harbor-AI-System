import { DashboardText, useDashboardCopy } from '../../i18n/DashboardCopy';
import { useLanguage } from '../../i18n/useLanguage';
import { toast } from 'sonner';
import BackupRecovery from './tabs/BackupRecovery';
import { logoutSession } from '../../../lib/authStorage';
import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Activity,
  Building2,
  ChevronLeft,
  ChevronRight,
  ClipboardList,
  Package,
  Search,
  Shield,
  Users,
  UserRoundCheck,
  UserRoundX,
  X,
} from 'lucide-react';
import PageHeader from '../../components/dashboard/PageHeader';
import DashboardShell from '../../components/dashboard/DashboardShell';
import { PolicyAcceptanceGate } from '../../components/PolicyAcceptanceGate';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Button } from '../../components/ui/button';
import { Badge } from '../../components/ui/badge';
import { request } from '../../../lib/api';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '../../components/ui/alert-dialog';
import { LoadingState } from '../../components/ui/loading-state';

type Admin = {
  user_id: number;
  first_name: string | null;
  last_name: string | null;
  email: string;
  is_active: boolean;
  joined_at: string | null;
};
type ActivityItem = { id: number; email?: string; first_name?: string; last_name?: string; action: string; activity_label?: string; target_label?: string | null; subject?: string; status: string; created_at: string };
type Overview = { stats: Record<string, number | null>; activity: ActivityItem[]; warnings?: string[] };
type AdministratorList = {
  data: Admin[];
  pagination: { page: number; per_page: number; total: number; last_page: number };
  summary: { total: number; active: number; inactive: number };
};
type ErrorWithStatus = Error & { status?: number };

const cards = [
  ['admins', 'Total Admins', Users], ['active_admins', 'Active Admins', UserRoundCheck], ['inactive_admins', 'Inactive Admins', UserRoundX],
  ['producers', 'Producers', Building2], ['buyers', 'Institutional Buyers', Shield], ['listings', 'Product Listings', Package], ['demands', 'Demand Requests', ClipboardList], ['orders', 'Orders', Activity],
] as const;
const administratorSummaryCards = [
  { label: 'Total Administrators', key: 'total', icon: Users },
  { label: 'Active', key: 'active', icon: UserRoundCheck },
  { label: 'Inactive', key: 'inactive', icon: UserRoundX },
] as const;

function safeError(error: unknown, fallback: string): string {
  const responseError = error as ErrorWithStatus | null;

  return responseError?.status && responseError.status < 500 && responseError.message
    ? responseError.message
    : fallback;
}

function adminName(admin: Pick<Admin, 'first_name' | 'last_name'>): string {
  return [admin.first_name, admin.last_name].filter(Boolean).join(' ') || 'Unnamed administrator';
}

function joinedDate(value: string | null): string {
  if (!value) return '—';

  const date = new Date(`${value.slice(0, 10)}T00:00:00`);
  return Number.isNaN(date.getTime()) ? '—' : date.toLocaleDateString();
}

export default function SuperAdminDashboard() {
  const copy = useDashboardCopy();
  const { lang } = useLanguage();
  const backupLabel = lang === 'fil' ? 'Backup at Pagbawi mula sa Sakuna' : 'Backup & Disaster Recovery';
  const navigate = useNavigate();
  const [activeTab, setActiveTab] = useState('overview');
  const [activeNavigationId, setActiveNavigationId] = useState('overview');
  const [overview, setOverview] = useState<Overview | null>(null);
  const [overviewLoading, setOverviewLoading] = useState(true);
  const [overviewError, setOverviewError] = useState('');
  const [overviewRefresh, setOverviewRefresh] = useState(0);
  const [administratorList, setAdministratorList] = useState<AdministratorList | null>(null);
  const [administratorsLoading, setAdministratorsLoading] = useState(true);
  const [administratorsError, setAdministratorsError] = useState('');
  const [administratorRefresh, setAdministratorRefresh] = useState(0);
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState<'all' | 'active' | 'inactive'>('all');
  const [page, setPage] = useState(1);
  const [targetAdmin, setTargetAdmin] = useState<Admin | null>(null);
  const [saving, setSaving] = useState(false);
  const [actionError, setActionError] = useState('');

  useEffect(() => {
    if (localStorage.getItem('userRole') !== 'superadmin') {
      navigate('/login', { replace: true });
      return;
    }

    let cancelled = false;
    setOverviewLoading(true);
    setOverviewError('');
    request('/api/superadmin/overview')
      .then((result) => { if (!result?.stats || !Array.isArray(result.activity)) throw new Error('Invalid overview response'); if (!cancelled) setOverview(result as Overview); })
      .catch((error: unknown) => { if (!cancelled) setOverviewError(safeError(error, 'Could not load system overview.')); })
      .finally(() => { if (!cancelled) setOverviewLoading(false); });

    return () => { cancelled = true; };
  }, [navigate, overviewRefresh]);

  useEffect(() => {
    const timer = window.setTimeout(() => setSearch(searchInput.trim()), 250);
    return () => window.clearTimeout(timer);
  }, [searchInput]);

  useEffect(() => {
    if (localStorage.getItem('userRole') !== 'superadmin') return;

    let cancelled = false;
    const params = new URLSearchParams({
      search,
      status,
      page: String(page),
      per_page: '15',
    });
    setAdministratorsLoading(true);
    setAdministratorsError('');
    request(`/api/superadmin/admins?${params.toString()}`)
      .then((result) => { if (!Array.isArray(result?.data) || !result.pagination || !result.summary) throw new Error('Invalid administrator response'); if (!cancelled) setAdministratorList(result as AdministratorList); })
      .catch((error: unknown) => { if (!cancelled) setAdministratorsError(safeError(error, 'Could not load administrator accounts.')); })
      .finally(() => { if (!cancelled) setAdministratorsLoading(false); });

    return () => { cancelled = true; };
  }, [search, status, page, administratorRefresh]);

  const logout = async () => {
    try { await logoutSession(); navigate('/login'); }
    catch { toast.error('Logout could not be completed. Please try again.'); }
  };
  const handleNavigation = (id: string) => {
    setActiveNavigationId(id);
    if (id === 'administrator-management') {
      setActiveTab('overview');
      window.setTimeout(() => document.getElementById('administrator-management-title')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 100);
      return;
    }
    setActiveTab(id);
  };
  const changeStatus = async () => {
    if (!targetAdmin) return;
    setSaving(true);
    setActionError('');
    try {
      await request(`/api/superadmin/admins/${targetAdmin.user_id}/status`, {
        method: 'PATCH',
        body: { is_active: !targetAdmin.is_active },
      });
      setTargetAdmin(null);
      setAdministratorRefresh((current) => current + 1);
      setOverviewRefresh((current) => current + 1);
    } catch (error) {
      setActionError(safeError(error, 'Could not update administrator access. Please try again.'));
    } finally {
      setSaving(false);
    }
  };

  const tabs = [{ id: 'overview', label: 'System Overview', icon: Shield }, { id: 'backups', label: backupLabel, icon: Shield }];
  const navSections = [
    { label: 'Overview', items: [{ id: 'overview', label: 'System Overview', icon: Shield }] },
    { label: lang === 'fil' ? 'Pagbawi' : 'Recovery', items: [{ id: 'backups', label: backupLabel, icon: Shield }] },
    { label: 'Administration', items: [{ id: 'administrator-management', label: 'Administrator Management', icon: Users, tabId: 'overview', anchorId: 'administrator-management-title' }] },
  ];
  const pagination = administratorList?.pagination;
  const summary = administratorList?.summary;
  const currentAdminName = targetAdmin ? adminName(targetAdmin) : 'this administrator';
  const actionName = targetAdmin?.is_active ? 'Deactivate' : 'Activate';

  return <PolicyAcceptanceGate areaLabel={copy("super admin workspace")}><DashboardShell title="Super Admin" subtitle={copy("System-level administration and oversight")} tabs={tabs} navSections={navSections} activeTab={activeTab} activeNavigationId={activeNavigationId} onTabChange={handleNavigation} userName={localStorage.getItem('userName') || 'Super Admin'} userRole="Super Admin" workspaceLabel="Super Admin" onLogout={logout}>
    {activeTab === 'backups' ? <BackupRecovery /> : <><div className="min-w-0 space-y-5 [&_button]:min-h-11">
      <PageHeader title={copy("System Overview")} icon={Shield} subtitle={copy("Monitor HarborAI and manage administrator access.")} />

      {overview?.warnings?.map((warning) => <p key={warning} role="status" className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">{warning}</p>)}
      {overviewLoading ? <LoadingState message={copy("Loading system information...")} /> : overviewError ? <div role="alert" className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><span>{overviewError}</span><Button variant="outline" onClick={() => setOverviewRefresh((current) => current + 1)}><DashboardText text="Retry" /></Button></div> : overview && <>
        <section aria-label={copy("System summary")} className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">{cards.map(([key, label, Icon]) => <Card key={key} className="border-border"><CardContent className="min-w-0 p-3 sm:p-4"><div className="flex flex-col items-start gap-2"><Icon className="h-5 w-5 shrink-0 text-[#0F9488]"/><span className="break-words text-sm font-semibold text-muted-foreground sm:text-sm">{copy(label)}</span></div><p className="mt-3 break-words text-xl sm:text-2xl font-bold text-foreground">{overview.stats[key] == null ? copy("Unavailable") : overview.stats[key]}</p></CardContent></Card>)}</section>

        <Card className="border-border">
          <CardHeader><CardTitle className="flex items-center gap-2"><Activity className="h-5 w-5 text-[#0F9488]"/><DashboardText text="Administrator Activity" /></CardTitle></CardHeader>
          <CardContent>{overview.activity.length ? <div className="space-y-2">{overview.activity.map((item) => <div key={item.id} className="min-w-0 break-words flex flex-col gap-1 border-b border-border py-3 last:border-0 sm:flex-row sm:items-center sm:justify-between"><div className="min-w-0 [overflow-wrap:anywhere]"><p className="font-semibold text-foreground">{item.activity_label || item.action || copy("Administrator activity")}</p><p className="text-sm text-muted-foreground">{item.first_name ? `${item.first_name} ${item.last_name}` : item.email || copy("Unknown admin")}{item.target_label ?? item.subject ? ` - ${item.target_label ?? item.subject}` : ''}</p></div><div className="shrink-0 text-xs text-muted-foreground"><p><DashboardText text="Outcome:" />{' '}{item.status === 'success' ? copy("Succeeded") : item.status === 'failure' ? copy("Failed") : copy("Not recorded")}</p><time>{new Date(item.created_at).toLocaleString()}</time></div></div>)}</div> : <p className="text-sm text-muted-foreground"><DashboardText text="No administrator activity recorded yet." /></p>}</CardContent>
        </Card>
      </>}

      <section aria-labelledby="administrator-management-title" className="min-w-0 scroll-mt-24 space-y-4">
        <div>
          <h2 id="administrator-management-title" className="text-xl font-bold text-foreground"><DashboardText text="Administrator Management" /></h2>
          <p className="mt-1 text-sm text-muted-foreground"><DashboardText text="Search, review, and manage administrator access. Only Admin accounts appear here." /></p>
        </div>

        {administratorsError && <div role="alert" className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><span>{administratorsError}</span><Button variant="outline" onClick={() => setAdministratorRefresh((current) => current + 1)}><DashboardText text="Retry" /></Button></div>}

        {summary && <div aria-label={copy("Administrator status summary")} className="grid grid-cols-1 gap-3 sm:grid-cols-3">
          {administratorSummaryCards.map(({ label, key, icon: StatusIcon }) => <Card key={key} className="border-border"><CardContent className="flex items-center justify-between gap-3 p-4"><span className="text-sm font-medium text-muted-foreground">{copy(label)}</span><span className="flex items-center gap-2 text-lg font-bold text-foreground"><StatusIcon className="h-4 w-4 text-[#0F9488]" />{administratorsLoading ? '…' : summary[key]}</span></CardContent></Card>)}
        </div>}

        <Card className="border-border">
          <CardContent className="flex flex-col gap-3 p-4 sm:flex-row sm:items-end sm:justify-between">
            <div className="grid min-w-0 flex-1 gap-3 sm:grid-cols-[minmax(240px,1fr)_190px]">
              <div className="min-w-0">
                <label htmlFor="administrator-search" className="mb-1 block text-xs font-semibold text-muted-foreground"><DashboardText text="Search administrators" /></label>
                <div className="relative">
                  <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                  <input id="administrator-search" type="search" value={searchInput} onChange={(event) => { setSearchInput(event.target.value); setPage(1); }} placeholder={copy("Name or email")} className="min-h-11 w-full rounded-md border border-[#D6D3C8] bg-card py-2 pl-9 pr-12 text-sm text-foreground outline-none focus:border-[#0F9488] focus:ring-2 focus:ring-[#0F9488]/20" />
                  {searchInput && <button type="button" aria-label={copy("Clear administrator search")} onClick={() => { setSearchInput(''); setPage(1); }} className="absolute right-0 top-1/2 min-h-11 min-w-11 -translate-y-1/2 rounded p-1 text-muted-foreground hover:bg-[#F1F5F4]"><X className="h-4 w-4" /></button>}
                </div>
              </div>
              <div>
                <label htmlFor="administrator-status" className="mb-1 block text-xs font-semibold text-muted-foreground"><DashboardText text="Status" /></label>
                <select id="administrator-status" value={status} onChange={(event) => { setStatus(event.target.value as 'all' | 'active' | 'inactive'); setPage(1); }} className="min-h-11 w-full rounded-md border border-[#D6D3C8] bg-card px-3 py-2 text-sm text-foreground outline-none focus:border-[#0F9488] focus:ring-2 focus:ring-[#0F9488]/20">
                  <option value="all"><DashboardText text="All Administrators" /></option>
                  <option value="active"><DashboardText text="Active" /></option>
                  <option value="inactive"><DashboardText text="Inactive" /></option>
                </select>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card className="min-w-0 border-border">
          <CardHeader className="flex flex-row items-center justify-between gap-3 space-y-0 px-4 py-3 sm:px-5">
            <CardTitle className="text-base"><DashboardText text="Administrator List" /></CardTitle>
            {administratorsLoading && <span className="text-xs text-muted-foreground"><DashboardText text="Loading…" /></span>}
          </CardHeader>
          <CardContent className="px-0 pb-0 pt-0">
            <div className="space-y-3 px-4 pb-4 md:hidden" aria-busy={administratorsLoading}>
              {administratorsLoading ? <LoadingState message={copy("Loading administrator accounts...")} /> : administratorsError ? <p className="text-sm text-muted-foreground"><DashboardText text="Administrator records could not be refreshed." /></p> : administratorList?.data.length ? administratorList.data.map(admin => <article key={admin.user_id} className="rounded-xl border border-border p-4 [overflow-wrap:anywhere]"><div className="flex flex-wrap items-start justify-between gap-2"><h3 className="min-w-0 font-bold">{adminName(admin)}</h3><Badge className={admin.is_active ? 'bg-[#DCFCE7] text-[#166534]' : 'bg-[#FEE2E2] text-[#991B1B]'}>{admin.is_active ? copy("Active") : copy("Inactive")}</Badge></div><p className="mt-1 text-sm text-muted-foreground">{admin.email}</p><dl className="mt-3 text-sm"><div className="flex flex-wrap justify-between gap-2"><dt><DashboardText text="Role" /></dt><dd>Admin</dd></div><div className="mt-2 flex flex-wrap justify-between gap-2"><dt><DashboardText text="Date added" /></dt><dd>{joinedDate(admin.joined_at)}</dd></div></dl><Button className="mt-4 min-h-11 w-full" variant="outline" onClick={() => { setActionError(''); setTargetAdmin(admin); }}>{admin.is_active ? copy("Deactivate") : copy("Activate")}{' '}<DashboardText text="administrator" /></Button></article>) : <p className="py-5 text-center text-sm text-muted-foreground">{search || status !== 'all' ? copy("No administrators match your current search or filter.") : copy("No administrator accounts found.")}</p>}
            </div>
            <div tabIndex={0} role="region" aria-label={copy("Administrator records table")} className="hidden max-w-full overflow-x-auto md:block">
              <table className="w-full min-w-[700px] border-collapse text-left text-sm">
                <thead className="bg-[#F4F6F4] text-xs uppercase tracking-wide text-muted-foreground">
                  <tr><th scope="col" className="px-4 py-3 font-semibold sm:px-5">Administrator</th><th scope="col" className="px-4 py-3 font-semibold"><DashboardText text="Role" /></th><th scope="col" className="px-4 py-3 font-semibold"><DashboardText text="Status" /></th><th scope="col" className="px-4 py-3 font-semibold"><DashboardText text="Date Added" /></th><th scope="col" className="px-4 py-3 text-right font-semibold sm:px-5"><DashboardText text="Action" /></th></tr>
                </thead>
                <tbody className="divide-y divide-[#E7E1D0]">
                  {administratorsLoading ? <tr><td colSpan={5} className="px-5 py-10"><LoadingState message={copy("Loading administrator accounts...")} /></td></tr>
                    : administratorsError ? <tr><td colSpan={5} className="px-5 py-8 text-sm"><DashboardText text="Administrator records could not be refreshed." /></td></tr> : administratorList?.data.length ? administratorList.data.map((admin) => <tr key={admin.user_id} className="hover:bg-[#F8FAF8]">
                      <td className="max-w-[280px] px-4 py-3 sm:px-5"><p className="break-words font-semibold text-foreground">{adminName(admin)}</p><p className="break-all text-xs text-muted-foreground">{admin.email}</p></td>
                      <td className="px-4 py-3 text-muted-foreground">Admin</td>
                      <td className="px-4 py-3"><Badge className={admin.is_active ? 'bg-[#DCFCE7] text-[#166534]' : 'bg-[#FEE2E2] text-[#991B1B]'}>{admin.is_active ? copy("Active") : copy("Inactive")}</Badge></td>
                      <td className="whitespace-nowrap px-4 py-3 text-muted-foreground">{joinedDate(admin.joined_at)}</td>
                      <td className="px-4 py-3 text-right sm:px-5"><Button variant="outline" size="sm" className="min-h-9" onClick={() => { setActionError(''); setTargetAdmin(admin); }}>{admin.is_active ? copy("Deactivate") : copy("Activate")}</Button></td>
                    </tr>) : <tr><td colSpan={5} className="px-5 py-10 text-center text-sm text-muted-foreground">{search || status !== 'all' ? copy("No administrators match your current search or filter.") : copy("No administrator accounts found.")}</td></tr>}
                </tbody>
              </table>
            </div>
            {pagination && <div className="flex flex-col gap-3 border-t border-border px-4 py-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between sm:px-5">
              <p>{pagination.total === 0 ? copy("No administrators to display") : copy("Showing {0}–{1} of {2} administrators", [(pagination.page - 1) * pagination.per_page + 1, Math.min(pagination.page * pagination.per_page, pagination.total), pagination.total])}</p>
              <div className="flex items-center gap-2">
                <Button variant="outline" size="sm" disabled={administratorsLoading || pagination.page <= 1} onClick={() => setPage((current) => Math.max(1, current - 1))}><ChevronLeft className="mr-1 h-4 w-4" /><DashboardText text="Previous" /></Button>
                <span aria-live="polite" className="min-w-[90px] text-center"><DashboardText text="Page" />{' '}{pagination.page}{' '}<DashboardText text="of" />{' '}{pagination.last_page}</span>
                <Button variant="outline" size="sm" disabled={administratorsLoading || pagination.page >= pagination.last_page} onClick={() => setPage((current) => Math.min(pagination.last_page, current + 1))}><DashboardText text="Next" /><ChevronRight className="ml-1 h-4 w-4" /></Button>
              </div>
            </div>}
          </CardContent>
        </Card>
      </section>
    </div>

    <AlertDialog open={!!targetAdmin} onOpenChange={(open) => { if (!open && !saving) { setTargetAdmin(null); setActionError(''); } }}>
      <AlertDialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto [overflow-wrap:anywhere] [&_button]:min-h-11">
        <AlertDialogHeader>
          <AlertDialogTitle>{actionName}{' '}<DashboardText text="administrator?" /></AlertDialogTitle>
          <AlertDialogDescription>{actionName} <strong>{currentAdminName}</strong> ({targetAdmin?.email})? {targetAdmin?.is_active ? copy("This will prevent the account from signing in.") : copy("This will allow the account to sign in again.")}</AlertDialogDescription>
        </AlertDialogHeader>
        {actionError && <p role="alert" className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">{actionError}</p>}
        <AlertDialogFooter>
          <AlertDialogCancel disabled={saving}><DashboardText text="Cancel" /></AlertDialogCancel>
          <AlertDialogAction disabled={saving} onClick={(event) => { event.preventDefault(); void changeStatus(); }} className={targetAdmin?.is_active ? 'bg-red-700 hover:bg-red-800' : 'bg-[#0F9488]'}>
            {saving ? copy("Saving...") : copy("{0} administrator", [actionName])}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </>}</DashboardShell></PolicyAcceptanceGate>;
}
