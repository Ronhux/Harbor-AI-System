import { DashboardText, useDashboardCopy } from '../../i18n/DashboardCopy';
import { logoutSession } from '../../../lib/authStorage';
import { toast } from 'sonner';
import { Button } from '../../components/ui/button';
import KadiwaManagerWorkspace from '../admin/tabs/KadiwaManagerWorkspace';
import {useEffect,useState} from 'react';
import {Navigate,useNavigate} from 'react-router-dom';
import {CalendarDays,ClipboardList,History,Inbox,LayoutDashboard,MapPin} from 'lucide-react';
import DashboardShell from '../../components/dashboard/DashboardShell';
import {PolicyAcceptanceGate} from '../../components/PolicyAcceptanceGate';
import {InquiryPanel} from '../../components/mao/KadiwaIntegration';
import {createKadiwaApi,KADIWA_INTEGRATION_ENABLED} from '../../components/mao/kadiwaApi';
import {useLanguage} from '../../i18n/useLanguage';
const api=createKadiwaApi();
type Section='overview'|'events'|'applications'|'coordination'|'inquiries'|'history';
function Overview({onCreate}:{onCreate:()=>void}){
 const [canCreate,setCanCreate]=useState(false);
 const {lang}=useLanguage();const tr=(en:string,fil:string)=>lang==='fil'?fil:en;
 const [counts,setCounts]=useState<{published:number;upcoming:number;pending:number;approved:number;inquiries:number|null}|null>(null),[error,setError]=useState('');
 useEffect(()=>{let active=true;(async()=>{try{
  const caps=await api.capabilities();if(active)setCanCreate(caps.manager);const events=caps.manager||caps.coordinator?await api.assigned():[];
  const queues=await Promise.all(events.filter(e=>caps.assigned_event_ids.includes(e.id)).map(e=>api.queue(e.id)));
  let inquiries:number|null=null;if(caps.inquiry_reviewer){inquiries=0;let page=1,last=1;do{const result=await api.inquiries(true,page);inquiries+=result.data.length;last=result.last_page;page++;}while(page<=last);}
  if(active)setCounts({published:events.filter(e=>e.status!=='draft'&&e.status!=='cancelled').length,upcoming:events.filter(e=>['open','closed'].includes(e.status)&&new Date(e.starts_at).getTime()>Date.now()).length,pending:queues.reduce((n,q)=>n+q.applications.filter(a=>a.status==='pending').length,0),approved:queues.reduce((n,q)=>n+q.approved_count,0),inquiries});
 }catch{if(active)setError(tr('Could not load coordination records. Refresh to try again.','Hindi ma-load ang mga tala. I-refresh upang subukang muli.'));}})();return()=>{active=false;};},[]);
 if(error)return <p role="alert">{error}</p>;
 const cards=[[tr('Published events','Mga nailathalang event'),counts?.published],[tr('Upcoming schedules','Mga paparating na iskedyul'),counts?.upcoming],[tr('Pending requests in loaded queues','Naghihintay sa mga naka-load na pila'),counts?.pending],[tr('Approved participants','Mga aprubadong kalahok'),counts?.approved],[tr('Authorized inquiries','Mga saklaw na tanong'),counts?.inquiries]];
 return <div className="space-y-4">{canCreate&&<Button className="min-h-11 bg-[#0F9488] text-white" onClick={onCreate}><DashboardText text="+ Create KADIWA Event" /></Button>}<div className="workflow-grid">{cards.map(([label,value])=><article key={String(label)}><p>{label}</p><strong className="text-3xl">{value??'—'}</strong></article>)}</div><p>{tr('Counts reflect records within your assigned authority. An em dash means unavailable or not assigned. Participation queues show up to 100 applications per event.','Mga tala lamang na saklaw ng iyong awtoridad. Ang gitling ay hindi available o hindi nakatalaga. Hanggang 100 aplikasyon bawat event ang nasa pila.')}</p><p>{tr('Local demonstration workspace. Official event details must be confirmed before publication.','Lokal na demonstrasyon. Kumpirmahin ang opisyal na detalye bago ilathala.')}</p></div>;
}
function Inquiries(){
  const copy = useDashboardCopy();const [allowed,setAllowed]=useState<boolean|null>(null);useEffect(()=>{api.capabilities().then(c=>setAllowed(c.inquiry_reviewer)).catch(()=>setAllowed(false));},[]);return allowed?<InquiryPanel api={api} review/>:<p>{allowed===null?copy("Loading permissions…"):copy("Inquiry review is not assigned to this account.")}</p>;}
export default function KadiwaDashboard(){
  const copy = useDashboardCopy();
 const [section,setSection]=useState<Section>('overview');const navigate=useNavigate();const {lang}=useLanguage();const tr=(en:string,fil:string)=>lang==='fil'?fil:en;
 if(localStorage.getItem('userRole')!=='kadiwa')return <Navigate to="/login" replace/>;
 const sections=[{id:'overview',label:tr('Overview','Pangkalahatan'),icon:LayoutDashboard},{id:'events',label:tr('Event Management','Pamamahala ng event'),icon:CalendarDays},{id:'applications',label:tr('Participation Management','Pamamahala ng paglahok'),icon:ClipboardList},{id:'coordination',label:tr('Venue & Accommodation','Lugar at akomodasyon'),icon:MapPin},{id:'inquiries',label:tr('Inquiries','Mga tanong'),icon:Inbox},{id:'history',label:tr('Activity History','Kasaysayan'),icon:History}];
 return <PolicyAcceptanceGate areaLabel={copy("KADIWA workspace")}><DashboardShell title={copy("KADIWA Management")} subtitle={tr('Events and producer coordination','Mga event at koordinasyon')} tabs={sections} navSections={[{label:'KADIWA',items:sections}]} activeTab={section} onTabChange={v=>setSection(v as Section)} onLogout={async()=>{try{await logoutSession();navigate('/login');}catch{toast.error('Logout could not be completed. Please try again.');}}} userName={localStorage.getItem('userName')||'KADIWA Manager'} userRole="KADIWA Manager" workspaceLabel={copy("KADIWA Workspace")}><div className="harbor-workflow space-y-4">{section==='overview'&&<Overview onCreate={()=>setSection('events')}/>}{['events','applications','history'].includes(section)&&<KadiwaManagerWorkspace section={section==='applications'?'participation':section==='history'?'history':'events'} onSectionChange={p=>setSection(p==='participation'?'applications':p)}/>}{section==='coordination'&&<><section><h2>{tr('Venue and accommodation coordination','Koordinasyon sa lugar at akomodasyon')}</h2><p>{tr('Event Management contains published venue, capacity and requirements. Use authorized inquiries below for accommodation and coordination details.','Nasa pamamahala ng event ang lugar, kapasidad at mga kailangan. Gamitin ang mga tanong sa ibaba para sa akomodasyon at koordinasyon.')}</p></section><Inquiries/></>}{section==='inquiries'&&<Inquiries/>}</div></DashboardShell></PolicyAcceptanceGate>;
}