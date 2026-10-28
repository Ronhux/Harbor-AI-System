import { responseTimestamp } from '../../contracts/responseTimestamp';
import type { FcaApplicationSummary, FcaApplicationDraft, FcaEvidenceReceipt, FcaInspectionSummary } from '../../contracts/maoWorkflows';

/** Consumed fields from FcaApplicationController::show. No extra endpoint or identity DTO. */
export type FcaApplicationDetailRead = FcaApplicationSummary & {
  submitted_details: Omit<FcaApplicationDraft,'evidence_receipt'>;
  review_history: {event_type:string;previous_status:FcaApplicationSummary['status']|null;new_status:FcaApplicationSummary['status']|null;created_at:string}[];
};
export interface FcaApplicationDetailResponse { data:FcaApplicationDetailRead }
export function validateFcaDetailResponse(raw:unknown,viewer:FcaViewer,id:number):FcaApplicationDetailRead {
  if(!raw||typeof raw!=='object'||Array.isArray(raw)||Object.keys(raw).some(key=>key!=='data'))throw new Error('Unsupported FCA detail envelope.');
  return validateApplication((raw as Record<string,unknown>).data,viewer,id);
}
export type FcaViewer = 'applicant_owner'|'other_producer'|'assigned_reviewer'|'unassigned_admin'|'buyer'|'inactive_reviewer';
export const fcaViewers: Record<FcaViewer,string> = {applicant_owner:'Approved producer owner example',other_producer:'Other producer example',assigned_reviewer:'Active assigned administrator example',unassigned_admin:'Administrator without assignment example',buyer:'Buyer example',inactive_reviewer:'Inactive assigned reviewer example'};
export type AuthorityScenario = 'pending'|'incomplete'|'missing_evidence'|'clarification'|'authorized'|'rejected'|'revoked'|'superseded'|'stale'|'permission'|'missing'|'network'|'server'|'empty';
export const authorityScenarios: Record<AuthorityScenario,string> = {pending:'Pending review',incomplete:'Incomplete local application',missing_evidence:'Current evidence unavailable',clarification:'Clarification contract gap',authorized:'Authorized authority example',rejected:'Rejected authority example',revoked:'Revoked authority example',superseded:'Superseded evidence contract gap',stale:'Stale application version',permission:'Reviewer permission changed',missing:'Application unavailable',network:'Network interruption',server:'Unexpected server error',empty:'Empty pending queue'};
export const authorityStatuses = ['pending','authorized','rejected','revoked'] as const;
export function canReadApplication(viewer:FcaViewer,id:number) { return [301,302,303,304].includes(id) && (viewer==='assigned_reviewer'||(viewer==='applicant_owner'&&id!==302)); }
export function applicationIds(viewer:FcaViewer) { return [301,302,303,304].filter(id=>canReadApplication(viewer,id)); }
export function applicationFixture(id=301,status:FcaApplicationSummary['status']=id===302?'authorized':id===303?'rejected':id===304?'revoked':'pending'):FcaApplicationDetailRead {
  return {id,fca_id:701+(id-301),status,row_version:status==='pending'?0:status==='revoked'?2:1,created_at:'2030-01-01T09:00:00',reviewed_at:status==='pending'?null:'2030-01-02T09:00:00',expires_at:status==='authorized'||status==='revoked'?'2030-12-31T09:00:00':null,revoked_at:status==='revoked'?'2030-01-03T09:00:00':null,
    submitted_details:{representative_position:'Synthetic designated representative',authorization_statement:'Synthetic claim only; no official membership or authority established'},
    review_history:[{event_type:'application_submitted',previous_status:null,new_status:'pending',created_at:'2030-01-01T09:00:00'},...(status==='pending'?[]:[{event_type:'application_'+(status==='revoked'?'authorized':status),previous_status:'pending' as const,new_status:status==='revoked'?'authorized' as const:status,created_at:'2030-01-02T09:00:00'}]),...(status==='revoked'?[{event_type:'application_revoked',previous_status:'authorized' as const,new_status:'revoked' as const,created_at:'2030-01-03T09:00:00'}]:[])]};
}
/** Frontend-only non-sensitive context, NOT fields returned by application show(). */
export function evidenceContext(status:FcaApplicationSummary['status']): {receipt:Pick<FcaEvidenceReceipt,'mime'|'bytes'|'status'>;inspection:FcaInspectionSummary} {
  const clean=status==='authorized'||status==='revoked';
  return {receipt:{mime:'application/pdf',bytes:24576,status:clean?'clean':'quarantined'},inspection:{status:clean?'clean':'quarantined',checks:{pdf_policy:clean?'clean':'pending',malware:clean?'clean':'error',integrity:'clean'},usable:clean}};
}
export function validateApplication(raw:unknown,viewer:FcaViewer,id:number):FcaApplicationDetailRead {
  const fail=()=>{throw new Error('Synthetic application response rejected; no record or notes replaced.');};
  if (!canReadApplication(viewer,id)||!raw||typeof raw!=='object'||Array.isArray(raw)) return fail();
  const value=raw as FcaApplicationDetailRead;
  const keys=['id','fca_id','status','row_version','created_at','reviewed_at','expires_at','revoked_at','submitted_details','review_history'];
  if(Object.keys(value).some(key=>!keys.includes(key))||value.id!==id||!Number.isSafeInteger(value.fca_id)||value.fca_id<1||!authorityStatuses.includes(value.status)||!Number.isSafeInteger(value.row_version)||value.row_version<0) return fail();
  const stamp=(v:unknown,nullable=false)=>nullable&&v===null||Number.isFinite(responseTimestamp(v));
  if(!stamp(value.created_at)||![value.reviewed_at,value.expires_at,value.revoked_at].every(v=>stamp(v,true))) return fail();
  if(value.status==='pending'&&(value.reviewed_at!==null||value.revoked_at!==null)||value.status!=='pending'&&value.reviewed_at===null||value.status==='revoked'&&value.revoked_at===null) return fail();
  const details=value.submitted_details;
  if(!details||typeof details!=='object'||Array.isArray(details)||Object.keys(details).some(key=>!['representative_position','authorization_statement'].includes(key))) return fail();
  if(typeof details.representative_position!=='string'||!details.representative_position.trim()||details.representative_position.length>120||typeof details.authorization_statement!=='string'||!details.authorization_statement.trim()||details.authorization_statement.length>2000) return fail();
  if(!Array.isArray(value.review_history)||value.review_history.length>100) return fail();
  for(const row of value.review_history) {
    if(!row||typeof row!=='object'||Object.keys(row).some(key=>!['event_type','previous_status','new_status','created_at'].includes(key))||!['application_submitted','application_authorized','application_rejected','application_revoked','evidence_downloaded','inspection_started','inspection_completed','inspection_interrupted','evidence_access_allowed','evidence_access_denied','evidence_release_failed'].includes(row.event_type)||![row.previous_status,row.new_status].every(v=>v===null||authorityStatuses.includes(v))||!stamp(row.created_at)) return fail();
  }
  return structuredClone(value);
}
export type AuthorityOutcome = {kind:'record';record:FcaApplicationDetailRead;message:string}|{kind:'gap'|'conflict'|'error'|'restricted'|'unavailable'|'empty';message:string};
/** Never submits, changes a fixture account, or accesses documents. */
export async function authorityMock(scenario:AuthorityScenario,viewer:FcaViewer,id:number):Promise<AuthorityOutcome> {
  await new Promise(resolve=>setTimeout(resolve,180));
  if(!canReadApplication(viewer,id)) return {kind:'restricted',message:'Synthetic access denied: only the approved producer owner or active explicitly assigned administrator may view this application.'};
  if(!(scenario in authorityScenarios)) return {kind:'error',message:'Unsupported synthetic response.'};
  if(scenario==='permission') return {kind:'restricted',message:'Synthetic permission changed. Application and evidence context withheld; no real permission changed.'};
  if(scenario==='missing') return {kind:'unavailable',message:'Synthetic application unavailable. No record was deleted.'};
  if(scenario==='empty') return {kind:'empty',message:'Synthetic pending queue is empty; no selected application returned.'};
  if(scenario==='stale') return {kind:'conflict',message:'Synthetic 409: expected row version differs. Loaded snapshot and notes retained; no decision, retry or overwrite.'};
  if(scenario==='network'||scenario==='server') return {kind:'error',message:`Synthetic ${scenario} failure. Snapshot and notes retained; no automatic retry.`};
  if(['clarification','superseded','incomplete'].includes(scenario)) return {kind:'gap',message:scenario==='incomplete'?'Incomplete is a local draft condition, not a persisted authority status. Position, statement and owned clean unexpired evidence are required.':scenario==='clarification'?'Clarification/resubmission decisions and states are not defined. No production status or resubmission operation is invented.':'Superseded evidence linkage/replacement is not exposed by the application contract. Receipt changes cannot bypass current inspection or version checks.'};
  if(scenario==='missing_evidence') return {kind:'gap',message:'Current evidence metadata is unavailable. Do not infer absence at submission or usable evidence now. Authorization/release must remain blocked until server checks are established.'};
  return {kind:'record',record:validateFcaDetailResponse({data:applicationFixture(id,scenario as FcaApplicationSummary['status'])},viewer,id),message:'Synthetic comparison available; explicitly select it to replace the local snapshot. No review decision occurred.'};
}
