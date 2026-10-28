import { DashboardText, useDashboardCopy } from '../../../i18n/DashboardCopy';
import { useEffect, useState } from 'react';
import { CalendarPlus } from 'lucide-react';
import { KadiwaManagement } from '../../../components/mao/KadiwaIntegration';
import { createKadiwaApi, KADIWA_INTEGRATION_ENABLED } from '../../../components/mao/kadiwaApi';
import { Button } from '../../../components/ui/button';

// Stable for the lifetime of the module: rendering never changes effect dependencies.
const api = createKadiwaApi();
type Pane = 'events' | 'participation' | 'history';
type Audit = Awaited<ReturnType<typeof api.audit>>;
export default function KadiwaManagerWorkspace({section, onSectionChange}: {section?: Pane; onSectionChange?: (pane: Pane) => void}) {
  const copy = useDashboardCopy();
  const [localPane, setLocalPane] = useState<Pane>('events');
  const pane = section ?? localPane;
  const [draftKey, setDraftKey] = useState(0);
  const [caps, setCaps] = useState<Awaited<ReturnType<typeof api.capabilities>> | null>(null);
  const [events, setEvents] = useState<Awaited<ReturnType<typeof api.assigned>>>([]);
  const [selected, setSelected] = useState<number | null>(null);
  const [audit, setAudit] = useState<Audit>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const changePane = (next: Pane) => {setLocalPane(next); onSectionChange?.(next);};
  useEffect(() => {
    if (!KADIWA_INTEGRATION_ENABLED) return;
    let active = true;
    api.capabilities().then(value => {if(active)setCaps(value);}).catch(e => {if(active)setError(e.message);});
    return () => {active = false;};
  }, []);
  useEffect(() => {
    if (pane !== 'history' || !KADIWA_INTEGRATION_ENABLED) return;
    let active = true;
    setLoading(true);setError('');
    api.assigned().then(list => {if(active){setEvents(list);setSelected(id => list.some(e => e.id === id) ? id : list[0]?.id ?? null);setLoading(false);}}).catch(e => {if(active){setError(e.message);setLoading(false);}});
    return () => {active = false;};
  }, [pane]);
  useEffect(() => {
    if (pane !== 'history' || selected === null) {setAudit([]);return;}
    let active = true;
    setLoading(true);setError('');
    api.audit(selected).then(rows => {if(active)setAudit(rows);}).catch(e => {if(active){setError(e.message);setAudit([]);}}).finally(() => {if(active)setLoading(false);});
    return () => {active = false;};
  }, [pane, selected]);
  if (!KADIWA_INTEGRATION_ENABLED) return <p><DashboardText text="KADIWA integration is not enabled." /></p>;
  return <section className="space-y-4">
    <header className="flex flex-wrap items-center justify-between gap-3">
      <h1 className="text-2xl font-bold text-foreground">{pane === 'events' ? copy("Event Management") : pane === 'participation' ? copy("Participation Management") : copy("Activity History")}</h1>
      {caps?.manager && <Button type="button" className="min-h-11 w-full bg-[#0F9488] text-white sm:w-auto" onClick={() => {setDraftKey(k => k + 1);changePane('events');}}><CalendarPlus className="mr-2 h-4 w-4" /><DashboardText text="+ Create KADIWA Event" /></Button>}
    </header>
    {!section && <nav aria-label={copy("KADIWA management sections")} className="flex flex-wrap gap-2">{(['events','participation','history'] as const).map(p => <Button key={p} variant={pane === p ? 'default' : 'outline'} onClick={() => changePane(p)}>{p === 'events' ? copy("Event Management") : p === 'participation' ? copy("Participation Management") : copy("Activity History")}</Button>)}</nav>}
    {error && <p role="alert" className="text-red-700">{error}</p>}
    {pane !== 'history' && <KadiwaManagement key={pane + '-' + draftKey} api={api} mode={pane} enabled={KADIWA_INTEGRATION_ENABLED} />}
    {pane === 'history' && <div className="space-y-3 rounded-xl border bg-card p-4">
      <label><DashboardText text="Event" /><select className="block w-full" value={selected ?? ''} onChange={e => setSelected(Number(e.target.value) || null)}><option value=""><DashboardText text="Select event" /></option>{events.map(e => <option key={e.id} value={e.id}>{e.title} — {e.status}</option>)}</select></label>
      {loading ? <p role="status"><DashboardText text="Loading activity…" /></p> : !events.length ? <p><DashboardText text="No recorded events." /></p> : !audit.length ? <p><DashboardText text="No activity recorded for this event." /></p> : <ol className="space-y-2">{audit.map((row,i) => <li key={i} className="rounded border p-3"><strong>{row.action}</strong><p>{row.previous_status ?? copy("New event")} → {row.new_status ?? copy("No status")}</p>{row.reason && <p>{row.reason}</p>}<time>{new Date(row.created_at).toLocaleString('en-PH',{timeZone:'Asia/Manila'})} PHT</time></li>)}</ol>}
    </div>}
  </section>;
}
