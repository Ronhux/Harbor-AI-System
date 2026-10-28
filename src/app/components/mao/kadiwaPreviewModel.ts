import { responseTimestamp, comparableSchedule } from '../../contracts/responseTimestamp';
import type { KadiwaApplicationInput, KadiwaApplicationStatus, KadiwaEventStatus } from '../../contracts/kadiwa';
import { isDraftDecimal } from './previewModel';

/** Consumed synthetic subsets of KadiwaService events/queue/view; not final live DTOs. */
export interface KadiwaEventRead {
  id:number;title:string;description:string;venue:string;location:string;starts_at:string;ends_at:string;opens_at:string;closes_at:string;
  capacity:number|null;categories:string[];requirements:string[];status:KadiwaEventStatus;row_version:number;evidence_required:boolean;rsbsa_required:boolean;
}
export interface KadiwaApplicationRead {
  application:{id:number;event_id:number;status:KadiwaApplicationStatus;row_version:number;created_at:string};
  details:{commodities:KadiwaApplicationInput['commodities'];participation_notes:string|null};
  history:{action:string;previous_status:KadiwaApplicationStatus|null;new_status:KadiwaApplicationStatus;created_at:string}[];
}
export interface KadiwaQueueRead {event:KadiwaEventRead;approved_count:number;available_slots:number|null;applications:KadiwaApplicationRead['application'][]}
export type KadiwaViewer='producer_owner'|'other_producer'|'assigned_coordinator'|'manager_unassigned'|'unassigned_admin'|'buyer'|'inactive';
export const kadiwaViewers:Record<KadiwaViewer,string>={producer_owner:'Approved producer owner example',other_producer:'Other approved producer example',assigned_coordinator:'Active event-assigned coordinator example',manager_unassigned:'Explicit manager without event assignment',unassigned_admin:'Administrator without assignment',buyer:'Buyer without coordinator assignment',inactive:'Inactive account example'};
const eventStates=['draft','open','closed','ongoing','completed','cancelled'];
const appStates=['pending','approved','rejected','needs_information','withdrawn','cancelled'];
export function eventFixture(id=401):KadiwaEventRead {
  return {id,title:`Synthetic KADIWA market ${id}`,description:'Synthetic direct-selling venue; customers buy directly from participating producers.',venue:'Synthetic market hall',location:'Synthetic locality',starts_at:'2030-11-03T09:00',ends_at:'2030-11-03T17:00',opens_at:'2030-10-31T09:00',closes_at:'2030-11-02T17:00',capacity:12,categories:['Vegetables','Fish'],requirements:['Bring display materials','Follow the venue schedule'],status:id===402?'closed':id===403?'draft':id===404?'cancelled':'open',row_version:id===403?1:2,evidence_required:false,rsbsa_required:false};
}
export function canViewEvent(viewer:KadiwaViewer,id:number) {return [401,402,403,404].includes(id)&&((['producer_owner','other_producer'].includes(viewer)&&id!==403)||(viewer==='assigned_coordinator'&&[401,403].includes(id)));}
export function canReadQueue(viewer:KadiwaViewer,id:number) {return viewer==='assigned_coordinator'&&[401,403].includes(id);}
export function canReadParticipation(viewer:KadiwaViewer,id:number,eventId:number) {return canReadQueue(viewer,eventId)||(viewer==='producer_owner'&&id===501&&eventId===401);}
export function applicationFixture(id=501,status:KadiwaApplicationStatus=id===502?'approved':'pending',eventId=401):KadiwaApplicationRead {
  return {application:{id,event_id:eventId,status,row_version:status==='pending'?1:2,created_at:'2030-11-01T09:00'},details:{commodities:[{commodity:'Synthetic cabbage',category:'Vegetables',quantity:'2.500',unit:'kg'}],participation_notes:'Synthetic display and transport plan'},history:[...(status==='pending'?[]:[{action:status==='needs_information'?'request_information':status==='approved'?'approve':status==='withdrawn'?'withdraw':status==='cancelled'?'event_cancelled':'reject',previous_status:'pending' as const,new_status:status,created_at:'2030-11-01T10:00'}]),{action:'submitted',previous_status:null,new_status:'pending',created_at:'2030-11-01T09:00'}]};
}
export function queueFixture(event=eventFixture()):KadiwaQueueRead {const apps=event.status==='draft'?[]:Array.from({length:5},(_,i)=>applicationFixture(501+i,i===0?'pending':'approved',event.id).application);return {event,approved_count:event.status==='draft'?0:4,available_slots:event.capacity===null?null:event.capacity-(event.status==='draft'?0:4),applications:apps};}
export class KadiwaValidationError extends Error {constructor(field:string){super(`Rejected synthetic KADIWA response: ${field}`);}}
function need(ok:unknown,field:string):asserts ok {if(!ok)throw new KadiwaValidationError(field);}
function object(raw:unknown,keys:string[],field:string):Record<string,unknown>{need(raw!==null&&typeof raw==='object'&&!Array.isArray(raw),field);const v=raw as Record<string,unknown>;need(Object.keys(v).every(k=>keys.includes(k)),`${field}.unsupported`);return v;}
function text(v:unknown,max:number,field:string):asserts v is string{need(typeof v==='string'&&Boolean(v.trim())&&v.length<=max,field);}
function positive(v:unknown,field:string):asserts v is number{need(typeof v==='number'&&Number.isSafeInteger(v)&&v>0,field);}
function collection(v:unknown,max:number,field:string,min=0):asserts v is unknown[]{need(Array.isArray(v)&&v.length>=min&&v.length<=max,field);}
export const kadiwaDate=responseTimestamp;
export function validateKadiwaEvent(raw:unknown,viewer:KadiwaViewer):KadiwaEventRead {
  const v=object(raw,['id','title','description','venue','location','starts_at','ends_at','opens_at','closes_at','capacity','categories','requirements','status','row_version','evidence_required','rsbsa_required'],'event');positive(v.id,'event.id');need(canViewEvent(viewer,v.id),'event.permission');positive(v.row_version,'event.version');
  for(const [key,max] of [['title',160],['description',4000],['venue',160],['location',200]] as const)text(v[key],max,`event.${key}`);
  need(eventStates.includes(v.status as string),'event.status');if(['producer_owner','other_producer'].includes(viewer))need(v.status!=='draft','event.draft_visibility');
  const start=kadiwaDate(v.starts_at),end=kadiwaDate(v.ends_at),opens=kadiwaDate(v.opens_at),closes=kadiwaDate(v.closes_at);need(comparableSchedule([v.starts_at,v.ends_at,v.opens_at,v.closes_at])&&end>start&&opens<=closes&&closes<=start,'event.schedule');
  if(v.capacity!==null){positive(v.capacity,'event.capacity');need(v.capacity<=10000,'event.capacity');}
  collection(v.categories,30,'event.categories',1);collection(v.requirements,30,'event.requirements');for(const [key,max] of [['categories',80],['requirements',500]] as const){(v[key] as unknown[]).forEach(row=>text(row,max,`event.${key}`));if(key==='categories')need(new Set(v[key] as unknown[]).size===(v[key] as unknown[]).length,`event.${key}.duplicate`);}
  need(typeof v.evidence_required==='boolean'&&typeof v.rsbsa_required==='boolean','event.requirement_flags');return structuredClone(v) as unknown as KadiwaEventRead;
}
export function validateKadiwaEvents(raw:unknown,viewer:KadiwaViewer):KadiwaEventRead[]{collection(raw,100,'events');const rows=raw.map(v=>validateKadiwaEvent(v,viewer));need(new Set(rows.map(v=>v.id)).size===rows.length,'events.duplicate');return rows;}
export function validateKadiwaQueue(raw:unknown,viewer:KadiwaViewer,eventId:number):KadiwaQueueRead {
  need(canReadQueue(viewer,eventId),'queue.permission');const v=object(raw,['event','approved_count','available_slots','applications'],'queue');const event=validateKadiwaEvent(v.event,viewer);need(event.id===eventId,'queue.identity');need(typeof v.approved_count==='number'&&Number.isSafeInteger(v.approved_count)&&v.approved_count>=0,'queue.count');
  need(event.capacity===null?v.available_slots===null:v.approved_count<=event.capacity&&v.available_slots===Math.max(0,event.capacity-v.approved_count),'queue.capacity_consistency');
  collection(v.applications,100,'queue.applications');const ids:unknown[]=[];for(const entry of v.applications){const row=object(entry,['id','event_id','status','row_version','created_at'],'queue.application');positive(row.id,'application.id');positive(row.row_version,'application.version');need(row.event_id===eventId&&appStates.includes(row.status as string)&&Number.isFinite(kadiwaDate(row.created_at)),'application.fields');ids.push(row.id);}need(new Set(ids).size===ids.length,'queue.duplicate');
  // Queue is capped; approved_count may exceed visible approved rows, but never be lower.
  const visibleApproved=(v.applications as Record<string,unknown>[]).filter(a=>a.status==='approved').length;
  need(v.applications.length===100?visibleApproved<=v.approved_count:visibleApproved===v.approved_count,'queue.approved_count');return structuredClone(v) as unknown as KadiwaQueueRead;
}
function commodities(raw:unknown,event:KadiwaEventRead){collection(raw,20,'commodities',1);const keys:string[]=[];for(const entry of raw){const row=object(entry,['commodity','category','quantity','unit'],'commodity');text(row.commodity,120,'commodity.name');text(row.category,80,'commodity.category');need(event.categories.includes(row.category),'commodity.category');need(typeof row.quantity==='string'&&isDraftDecimal(row.quantity,9,3,true),'commodity.quantity');text(row.unit,30,'commodity.unit');need(!/[\r\n\x00-\x1f]/.test(row.unit),'commodity.unit');keys.push([row.commodity,row.category,row.unit].map(v=>String(v).trim().toLowerCase()).join('|'));}}
export function validateKadiwaApplication(raw:unknown,viewer:KadiwaViewer,id:number,event:KadiwaEventRead):KadiwaApplicationRead {
  need(canReadParticipation(viewer,id,event.id),'application.permission');const v=object(raw,['application','details','history'],'participation');const a=object(v.application,['id','event_id','status','row_version','created_at'],'application');need(a.id===id&&a.event_id===event.id&&appStates.includes(a.status as string),'application.identity');positive(a.row_version,'application.version');need(Number.isFinite(kadiwaDate(a.created_at)),'application.date');const details=object(v.details,['commodities','participation_notes'],'details');commodities(details.commodities,event);if(details.participation_notes!==null)need(typeof details.participation_notes==='string'&&details.participation_notes.length<=1000,'details.notes');
  collection(v.history,100,'history');for(const entry of v.history){const row=object(entry,['action','previous_status','new_status','created_at'],'history.row');need(['submitted','approve','reject','request_information','withdraw','event_cancelled'].includes(row.action as string)&&(row.previous_status===null||appStates.includes(row.previous_status as string))&&appStates.includes(row.new_status as string)&&Number.isFinite(kadiwaDate(row.created_at)),'history.fields');}
  return structuredClone(v) as unknown as KadiwaApplicationRead;
}
export interface ParticipationDraft {commodities:KadiwaApplicationInput['commodities'];participation_notes:string}
export const blankParticipation=():ParticipationDraft=>({commodities:[{commodity:'Synthetic cabbage',category:'Vegetables',quantity:'2.500',unit:'kg'}],participation_notes:''});
export function participationIssues(draft:ParticipationDraft,event:KadiwaEventRead|null):Record<string,string> {
  const errors:Record<string,string>={};if(!event){errors.event='Select a permitted event.';return errors;}
  if(event.status!=='open')errors.event='This synthetic event is not open for applications.';
  if(event.evidence_required)errors.evidence='Private participation evidence is not integrated; application remains blocked.';
  if(draft.commodities.length<1||draft.commodities.length>20)errors.commodities='Use 1–20 products.';
  const seen=new Set<string>();draft.commodities.forEach((row,i)=>{
    if(!row.commodity.trim()||row.commodity.length>120)errors[`commodities.${i}.commodity`]='Enter a product of at most 120 characters.';
    if(!event.categories.includes(row.category))errors[`commodities.${i}.category`]='Choose a category permitted by this fixture event.';
    if(!isDraftDecimal(row.quantity,9,3,true))errors[`commodities.${i}.quantity`]='Use a positive quantity with at most 9 whole and 3 decimal digits.';
    if(!row.unit.trim()||row.unit.length>30||/[\r\n\x00-\x1f]/.test(row.unit))errors[`commodities.${i}.unit`]='Enter a nonempty unit of at most 30 characters; control characters are unsupported.';
    const key=[row.commodity,row.category,row.unit].map(v=>v.trim().toLowerCase()).join('|');if(seen.has(key))errors[`commodities.${i}.commodity`]='Duplicate product/category/unit in the local draft.';seen.add(key);
  });if(draft.participation_notes.length>1000)errors.participation_notes='Use at most 1000 characters.';return errors;
}
export type KadiwaScenario='valid'|'full'|'stale_capacity'|'duplicate'|'approved'|'schedule'|'cancelled'|'missing'|'revised'|'permission'|'requirements'|'network'|'server'|'empty'|'invalid';
export const kadiwaScenarios:Record<KadiwaScenario,string>={valid:'Current synthetic snapshot',full:'Event at capacity',stale_capacity:'Concurrent capacity conflict',duplicate:'Duplicate application',approved:'Participation already approved',schedule:'Schedule changed',cancelled:'Event cancelled',missing:'Event unavailable',revised:'Revised/superseded application gap',permission:'Authorization changed',requirements:'Private evidence requirements',network:'Network interruption',server:'Server error',empty:'Empty results',invalid:'Malformed response'};
export type KadiwaOutcome={kind:'comparison';event:KadiwaEventRead;queue:KadiwaQueueRead|null;application:KadiwaApplicationRead|null;message:string}|{kind:'restricted'|'unavailable'|'conflict'|'gap'|'error'|'empty';message:string};
export async function kadiwaMock(scenario:KadiwaScenario,viewer:KadiwaViewer,eventId:number,applicationId=501):Promise<KadiwaOutcome>{
  await new Promise(resolve=>setTimeout(resolve,160));if(!canViewEvent(viewer,eventId))return {kind:'restricted',message:'Synthetic event access denied. No assignment or account permission granted.'};
  if(!(scenario in kadiwaScenarios))return {kind:'error',message:'Unsupported synthetic response.'};
  if(scenario==='permission')return {kind:'restricted',message:'Synthetic authorization changed; event/applicant data withheld.'};
  if(scenario==='missing')return {kind:'unavailable',message:'Synthetic event unavailable; no record deleted.'};
  if(scenario==='empty')return {kind:'empty',message:'No synthetic events or applications returned.'};
  if(['stale_capacity','duplicate'].includes(scenario))return {kind:'conflict',message:`Synthetic ${scenario} conflict; no capacity reserved or automatic retry. Draft and notes retained.`};
  if(scenario==='revised')return {kind:'gap',message:'Application revision/supersession is not defined; needs_information is supported but no resubmission operation exists.'};
  if(['network','server'].includes(scenario))return {kind:'error',message:`Synthetic ${scenario} failure; snapshot, draft and notes retained.`};
  try {
    let event=eventFixture(eventId);if(scenario==='invalid')event={...event,capacity:-1};if(scenario==='schedule'){return {kind:'gap',message:'Schedule revision is not accepted by editDraft(). A changed schedule requires a separately reviewed contract; no new dates are fabricated.'};}
    if(scenario==='cancelled'){event.status='cancelled';event.row_version++;}if(scenario==='requirements')event.evidence_required=true;
    event=validateKadiwaEvent(event,viewer);let queue=canReadQueue(viewer,eventId)?queueFixture(event):null;
    if(queue&&scenario==='full'&&event.capacity!==null){queue.applications=[applicationFixture(501,'pending',eventId).application,...Array.from({length:event.capacity},(_,i)=>applicationFixture(502+i,'approved',eventId).application)];queue.approved_count=event.capacity;queue.available_slots=0;}
    if(queue&&scenario==='approved'&&queue.applications.some(a=>a.id===applicationId&&a.status==='pending')){queue.applications=queue.applications.map(a=>a.id===applicationId?applicationFixture(a.id,'approved',eventId).application:a);queue.approved_count++;queue.available_slots=event.capacity===null?null:event.capacity-queue.approved_count;}
    if(queue&&scenario==='cancelled'){queue.applications=queue.applications.map(a=>({...a,status:'cancelled',row_version:a.row_version+1}));queue.approved_count=0;queue.available_slots=event.capacity;}
    if(queue)queue=validateKadiwaQueue(queue,viewer,eventId);
    const reference=queue?.applications.find(a=>a.id===applicationId);
    let application=canReadParticipation(viewer,applicationId,eventId)&&event.status!=='draft'?applicationFixture(applicationId,reference?.status??(scenario==='approved'?'approved':scenario==='cancelled'?'cancelled':undefined),eventId):null;
    if(application&&reference)application.application=structuredClone(reference);
    if(application)application=validateKadiwaApplication(application,viewer,applicationId,event);
    return {kind:'comparison',event,queue,application,message:scenario==='full'&&!queue?'Producer event list does not expose approved counts or remaining slots. Capacity availability cannot be inferred.':'Synthetic comparison only. Explicit replacement changes no event, approval, sale, payment or selling-space reservation.'};
  }catch{return {kind:'error',message:'Malformed or unauthorized synthetic response rejected; no current data replaced.'};}
}
