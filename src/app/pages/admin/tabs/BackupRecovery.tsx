import { useEffect, useRef, useState } from 'react';
import { DatabaseBackup, RefreshCw } from 'lucide-react';
import { request } from '../../../../lib/api';
import { Button } from '../../../components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '../../../components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '../../../components/ui/dialog';
import PageHeader from '../../../components/dashboard/PageHeader';

import { useLanguage } from '../../../i18n/useLanguage';
import { backupFil } from '../../../i18n/backupMessages';

type Backup = { id: string; started_at: string; completed_at?: string; status: string; verification_status: string; verified_at?: string; bytes?: number; secondary_status: string; deleted_at?: string; error?: string; drill?: { status: string; seconds: number; at: string; application_qa?: string } };
type AuditEvent = { at: string; action: string; backup_id: string; actor_id: number | null };
type Health = { audit: AuditEvent[]; isolated_recovery_configured: boolean; enabled: boolean; storage_permissions_approved: boolean; key_configured: boolean; latest_success: string | null; latest_attempt: Backup | null; next_scheduled: string | null; schedule: string; scheduler_status: string; scheduler_heartbeat: string | null; verified_points: number; rpo_met: boolean; storage_bytes: number; free_bytes: number; local_status: string; secondary_status: string; retention_days: number; latest_drill: Backup['drill']; history: Backup[] };
export default function BackupRecovery() {
  const { lang } = useLanguage();
  const tr = (text: string) => lang === 'fil' ? backupFil[text] ?? text : text;
  const date = (value?: string | null) => value ? new Date(value).toLocaleString(lang === 'fil' ? 'fil-PH' : 'en-PH', { timeZone: 'Asia/Manila' }) + ' (Manila)' : tr('Not recorded');
  const size = (value?: number) => value == null ? tr('Unknown') : (value / 1024 / 1024).toFixed(1) + ' MB';
  const operationLock = useRef(false);
  const [loading, setLoading] = useState(true);

  const [health, setHealth] = useState<Health | null>(null); const [error, setError] = useState(''); const [message, setMessage] = useState('');
  const [busy, setBusy] = useState(false); const [secureAction, setSecureAction] = useState<{ id: string; action: 'download' | 'drill' } | null>(null); const [password, setPassword] = useState('');
  const [instructions, setInstructions] = useState<string[] | null>(null);
  const load = async () => { setLoading(true); try { setHealth(await request('/api/superadmin/backups')); setError(''); } catch { setError('Backup health could not be loaded. Try again.'); } finally { setLoading(false); } };
  useEffect(() => { void load(); }, []);
  async function action(path: string, body?: object) {
    if (operationLock.current) return false;
    operationLock.current = true;
    setBusy(true); setError(''); setMessage('');
    try { await request(path, { method: 'POST', body }); setMessage('Operation completed. Review the recorded outcome below.'); await load(); return true; }
    catch (e) { setError(e instanceof Error && e.message === 'Reauthentication failed.' ? e.message : 'Operation failed. Refresh history before retrying; a timed-out request may still be running.'); return false; }
    finally { operationLock.current = false; setBusy(false); }
  }
  async function runSecure() {
    if (!secureAction || operationLock.current) return;
    if (secureAction.action === 'drill') { const ok = await action('/api/superadmin/backups/' + secureAction.id + '/drill', { password }); setPassword(''); if (ok) setSecureAction(null); return; }
    operationLock.current = true;
    setBusy(true); setError(''); setMessage('');
    try {
      await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
      const headers: Record<string, string> = { 'Content-Type': 'application/json', Accept: 'application/octet-stream' };
      try { const token = localStorage.getItem('authToken'); if (token) headers.Authorization = 'Bearer ' + token; } catch { /* Cookie authentication remains available. */ }
      const xsrf = document.cookie.split('; ').find(cookie => cookie.startsWith('XSRF-TOKEN=')); if (xsrf) headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrf.slice('XSRF-TOKEN='.length));
      const response = await fetch('/api/superadmin/backups/' + secureAction.id + '/download', { method: 'POST', credentials: 'include', headers, body: JSON.stringify({ password }) });
      if (!response.ok) { const data = response.headers.get('content-type')?.includes('application/json') ? await response.json() : null; throw new Error(data?.message || 'Encrypted download failed.'); }
      const url = URL.createObjectURL(await response.blob()); const a = document.createElement('a'); a.href = url; a.download = 'harborai-' + secureAction.id + '.hbk'; a.click(); setTimeout(() => URL.revokeObjectURL(url), 1000); setSecureAction(null); setMessage('Encrypted archive downloaded. Keep its encryption key separately.');
    } catch (e) { setError(e instanceof Error && e.message === 'Reauthentication failed.' ? e.message : 'Encrypted download failed.'); }
    finally { operationLock.current = false; setPassword(''); setBusy(false); }
  }
  return <section className="space-y-5 min-w-0" aria-busy={busy || loading}>
    <PageHeader title={tr("Backup & Disaster Recovery")} icon={DatabaseBackup} subtitle={tr("Encrypted recovery points, integrity checks and isolated recovery drills.")} />
    <p className="rounded-lg border border-border bg-card p-4 text-sm">{tr("Proposed targets: RPO 24 hours · RTO 8 hours. An integrity check confirms archive authenticity. A database/file drill still requires separate application and permission checks. These targets are not proven operational guarantees.")}</p>
    {error && <p role="alert" className="rounded-lg border border-red-400 bg-card p-3 text-red-600 dark:text-red-300">{tr(error)}</p>}{message && <p role="status" className="rounded-lg border border-border bg-card p-3">{tr(message)}</p>}
    <div className="flex flex-wrap gap-3"><Button disabled={busy || !health?.enabled || !health?.key_configured || !health?.storage_permissions_approved} onClick={() => void action('/api/superadmin/backups')}>{tr("Create Manual Backup")}</Button><Button variant="outline" disabled={busy || loading} onClick={() => void load()}><RefreshCw className="mr-2 h-4 w-4"/>{tr("Refresh health")}</Button><Button variant="outline" disabled={busy || loading} onClick={async () => { try { const data = await request('/api/superadmin/backups/instructions'); setInstructions(data.steps); } catch { setError('Recovery instructions could not be loaded.'); } }}>{tr("Review Recovery Instructions")}</Button></div>
    {loading && !health ? <p role="status">{tr("Loading backup health…")}</p> : health ? <>
      {!health.enabled && <p className="font-semibold">{tr("Not operational: backups are disabled until an operator configures the dedicated encryption key, database tool, storage permissions and scheduler.")}</p>}
      <dl className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">{[
        ['Latest successful database backup', date(health.latest_success)], ['Latest backup attempt', health.latest_attempt ? date(health.latest_attempt.started_at) + ' · ' + tr(health.latest_attempt.status) : 'None'], ['Next scheduled backup', date(health.next_scheduled)], ['Scheduling health', health.scheduler_status], ['Integrity-verified recovery points', String(health.verified_points)], ['24-hour recovery point window', health.rpo_met ? 'Recent integrity-verified point exists; application recovery unproven' : 'No recent verified point'], ['Local archive usage', size(health.storage_bytes)], ['Available local space', size(health.free_bytes)], ['Local storage', health.local_status], ['Secondary storage', health.secondary_status], ['Private permissions approval', health.storage_permissions_approved ? 'Operator approval configured' : 'Not verified'], ['Encryption key', health.key_configured ? 'Configured; not displayed' : 'Not Configured'], ['Isolated recovery target', health.isolated_recovery_configured ? 'Configured; an empty isolated target is still required' : 'Not Configured'], ['Latest isolated drill', health.latest_drill ? tr(health.latest_drill.status) + ' · ' + health.latest_drill.seconds + ' ' + tr('seconds') : 'Not performed'],
      ].map(([label, value]) => <Card key={label}><CardContent className="p-4"><dt className="text-sm text-muted-foreground">{tr(label)}</dt><dd className="mt-2 break-words font-semibold">{tr(value)}</dd></CardContent></Card>)}</dl>
      <Card><CardHeader><CardTitle>{tr("Backup History")}</CardTitle><p className="text-sm text-muted-foreground">{tr("Daily schedule")}: {health.schedule.replace(' daily', lang === 'fil' ? ' araw-araw' : ' daily')}. {tr('Retention')}: {health.retention_days} {tr('days; latest verified point is protected. Latest 100 attempts shown. Actions are audited in the private server journal.')}</p></CardHeader><CardContent className="space-y-4">{!health.history.length ? <p>{tr("No backup attempts recorded.")}</p> : health.history.map(row => <article key={row.id} className="space-y-2 rounded-lg border border-border p-4"><p className="break-all text-xs">{row.id}</p><p>{date(row.started_at)} · {tr(row.status)}{row.deleted_at ? ' · ' + tr('retained history, archive deleted') : ''} · {size(row.bytes)}</p><p className="text-sm">{tr("Integrity")}: {tr(row.verification_status)} · {tr("Secondary")}: {tr(row.secondary_status)}</p>{row.error && <p role="status">{tr(row.error)}</p>}{row.drill && <p>{tr("Drill")}: {tr(row.drill.status)} · {row.drill.seconds} {tr('seconds')} · {tr("Application QA")}: {tr(row.drill.application_qa || 'not performed')}</p>}{row.status === 'completed' && !row.deleted_at && <div className="flex flex-wrap gap-2"><Button variant="outline" disabled={busy} onClick={() => void action('/api/superadmin/backups/' + row.id + '/verify')}>{tr("Verify Integrity")}</Button><Button variant="outline" disabled={busy} onClick={() => { setError(''); setPassword(''); setSecureAction({ id: row.id, action: 'download' }); }}>{tr("Download encrypted backup")}</Button><Button variant="outline" disabled={busy || !health.isolated_recovery_configured} onClick={() => { setError(''); setPassword(''); setSecureAction({ id: row.id, action: 'drill' }); }}>{tr("Initiate isolated recovery test")}</Button></div>}</article>)}</CardContent></Card>
      <Card><CardHeader><CardTitle>{tr("Backup Audit Records")}</CardTitle></CardHeader><CardContent>{!health.audit.length ? <p>{tr("No backup operations recorded.")}</p> : <ol className="space-y-2">{health.audit.map((event, index) => <li key={index} className="break-words rounded-lg border border-border p-3 text-sm"><p>{date(event.at)} · {tr(event.action)}</p><p className="break-all text-xs">{tr('Backup')} {event.backup_id} · {tr('Actor')} {event.actor_id ?? tr('scheduler / CLI')}</p></li>)}</ol>}</CardContent></Card>
    </> : null}
    <Dialog open={!!secureAction} onOpenChange={open => { if (!open && !busy) { setSecureAction(null); setPassword(''); } }}><DialogContent className="max-h-[85dvh] overflow-y-auto"><DialogHeader><DialogTitle>{tr(secureAction?.action === 'download' ? 'Download encrypted backup' : 'Isolated recovery test')}</DialogTitle><DialogDescription>{tr("Re-enter your password. Recovery tests require a separately configured empty database and cannot overwrite the active application database.")}</DialogDescription></DialogHeader><form className="space-y-4" onSubmit={e => { e.preventDefault(); void runSecure(); }}><label htmlFor="backup-password">{tr("Current password")}</label><input id="backup-password" type="password" autoComplete="current-password" required disabled={busy} maxLength={1024} value={password} onChange={e => setPassword(e.target.value)} className="min-h-11 w-full rounded-lg border border-border bg-background px-3"/>{error && <p role="alert">{tr(error)}</p>}<Button type="submit" disabled={busy}>{tr(busy ? 'Working…' : 'Continue')}</Button></form></DialogContent></Dialog>
    <Dialog open={!!instructions} onOpenChange={open => { if (!open) setInstructions(null); }}><DialogContent className="max-h-[85dvh] overflow-y-auto"><DialogHeader><DialogTitle>{tr("Recovery Instructions")}</DialogTitle><DialogDescription>{tr('Full operator runbook')}: docs/operations/BACKUP_RECOVERY.md</DialogDescription></DialogHeader><ol className="list-decimal space-y-2 pl-5">{instructions?.map(step => <li key={step}>{tr(step)}</li>)}</ol></DialogContent></Dialog>
  </section>;
}
