import {request} from '../../../lib/api';

export const FCA_MANUAL_INTEGRATION_ENABLED=true;
export type ClaimStatus='pending'|'verified'|'rejected'|'requires_further_review';
export interface ManualClaim {id:number;fca_id:number;row_version:number;status:ClaimStatus;can_resubmit:boolean;correction_fca_locked:boolean;authority_application_id:number|null;authority_status:string;phone_display:string;phone_verified:false;verification_method:'manual'}
export interface DirectoryRow {id:number;official_name:string;address:string}
export interface Page<T>{data:T[];current_page:number;last_page:number}
export interface AuthorityApplication {id:number;fca_id:number;status:'pending'|'authorized'|'rejected'|'revoked';row_version:number}
export interface ReviewClaim extends ManualClaim {manual_verification_current:boolean;claimed_name:string;directory_president:string;applicant_identity:{first_name:string;last_name:string};correction_history:{from_claim_id:number;target_claim_id:number;previous_version:number;resubmitted_at:string}[];manual_review_history:{result:string;outcome:string;reviewer_id:number;reviewed_at:string;contacted_at:string|null}[]}
export interface ClaimInput {association:string;name:string;phone:string;consent:boolean}
export interface ManualDecision {result:'verified'|'rejected'|'requires_further_review';outcome:'identity_confirmed'|'identity_mismatch'|'unreachable'|'outdated_record'|'ambiguous_record';expected_version:number;decision_key:string;contact_version?:number;contacted_at?:string;evidence:string}
export type Transport=(path:string,options?:{method?:string;body?:unknown;headers?:Record<string,string>})=>Promise<unknown>;
async function boundedRequest(path:string,options?:Parameters<Transport>[1]):Promise<unknown>{const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),30000);try{return await request(path,{...options,signal:controller.signal});}catch(e){if(controller.signal.aborted)throw new FcaApiError(408);throw e;}finally{clearTimeout(timer);}}
async function privateDocument(path:string):Promise<Blob>{
 const abort=new AbortController(),deadline=setTimeout(()=>abort.abort(),30000);
 try{
  const headers:Record<string,string>={Accept:'application/octet-stream'};const token=localStorage.getItem('authToken');if(token)headers.Authorization='Bearer '+token;
  const response=await fetch(path,{credentials:'include',cache:'no-store',redirect:'error',headers,signal:abort.signal});
  if(!response.ok)throw new FcaApiError(response.status);
  if(response.headers.get('content-type')?.split(';')[0]!=='application/octet-stream'||!response.body)throw new FcaApiError(503);
  const reader=response.body.getReader(),parts:Uint8Array[]=[];let bytes=0;
  try{for(;;){const chunk=await reader.read();if(chunk.done)break;bytes+=chunk.value.byteLength;if(bytes>5*1024*1024){await reader.cancel();throw new FcaApiError(503);}parts.push(chunk.value);}}finally{reader.releaseLock();}
  if(bytes===0)throw new FcaApiError(503);return new Blob(parts as BlobPart[],{type:'application/octet-stream'});
 }finally{clearTimeout(deadline);}
}
const fieldLabels:Record<string,string>={association:'Association',fca_id:'Association',name:'Full name',phone:'Contact number',consent:'Consent',email:'Email',password:'Password',result:'Verification result',outcome:'Contact outcome',evidence:'Verification evidence',contacted_at:'Contact time',contact_version:'Directory version',expected_version:'Record version',representative_position:'Position',authorization_statement:'Authorization statement',evidence_receipt:'Supporting document',expires_at:'Authority expiry',review_reason:'Authority review reason'};
export class FcaApiError extends Error {constructor(public status:number,fields:string[]=[]){super((({401:'Please sign in again.',403:'You are not authorized for this action.',404:'This workflow is not enabled.',408:'The request timed out. Its outcome may be unknown; refresh before retrying.',409:'The record changed or requires review. Reload before continuing.',419:'Your session expired. Please sign in again.',422:'Check the submitted fields and contact evidence.',429:'Too many requests. Please wait.',503:'Verification dependencies are unavailable.'} as Record<number,string>)[status]??'The request could not complete. No automatic retry.')+(fields.length?' Review: '+fields.filter(k=>Object.prototype.hasOwnProperty.call(fieldLabels,k)).map(k=>fieldLabels[k]).slice(0,5).join(', ')+'.':''));}}
function failure(e:unknown):FcaApiError {if(e instanceof FcaApiError)return e;const r=typeof e==='object'&&e!==null?e as Record<string,unknown>:{};const fields=typeof r.errors==='object'&&r.errors!==null?Object.keys(r.errors):[];return new FcaApiError(typeof r.status==='number'?r.status:503,fields.filter(k=>Object.prototype.hasOwnProperty.call(fieldLabels,k)));}
function obj(raw:unknown):Record<string,unknown>{if(!raw||typeof raw!=='object'||Array.isArray(raw))throw new FcaApiError(503);return raw as Record<string,unknown>;}
function int(raw:unknown,min=1):number {if(typeof raw!=='number'||!Number.isSafeInteger(raw)||raw<min)throw new FcaApiError(503);return raw;}
function str(raw:unknown,max=2000):string {if(typeof raw!=='string'||raw.length>max)throw new FcaApiError(503);return raw;}
function bool(raw:unknown):boolean {if(typeof raw!=='boolean')throw new FcaApiError(503);return raw;}
function keys(r:Record<string,unknown>,allowed:string[]):void{if(Object.keys(r).some(k=>!allowed.includes(k)))throw new FcaApiError(503);}
const claimKeys=['id','fca_id','row_version','status','can_resubmit','correction_fca_locked','authority_application_id','authority_status','phone_display','phone_verified','verification_method'];
export function readManualClaim(raw:unknown):ManualClaim {
 const r=obj(raw);keys(r,claimKeys);
 if(!['pending','verified','rejected','requires_further_review'].includes(String(r.status))||r.phone_verified!==false||r.verification_method!=='manual'||!['Confidential MAO number','***********'].includes(String(r.phone_display))||!['pending','authorized','rejected','revoked'].includes(String(r.authority_status)))throw new FcaApiError(503);
 return {id:int(r.id),fca_id:int(r.fca_id),row_version:int(r.row_version),status:r.status as ClaimStatus,can_resubmit:bool(r.can_resubmit),correction_fca_locked:bool(r.correction_fca_locked),authority_application_id:r.authority_application_id===null?null:int(r.authority_application_id),authority_status:String(r.authority_status),phone_display:String(r.phone_display),phone_verified:false,verification_method:'manual'};
}
export function readReviewClaim(raw:unknown):ReviewClaim {
 const r=obj(raw);keys(r,[...claimKeys,'manual_verification_current','claimed_name','directory_president','applicant_identity','manual_review_history','correction_history']);
 const base=Object.fromEntries(claimKeys.map(k=>[k,r[k]]));const identity=obj(r.applicant_identity);keys(identity,['first_name','last_name']);
 if(!Array.isArray(r.correction_history)||r.correction_history.length>20)throw new FcaApiError(503);
 if(!Array.isArray(r.manual_review_history)||r.manual_review_history.length>20)throw new FcaApiError(503);
 return {...readManualClaim(base),manual_verification_current:bool(r.manual_verification_current),claimed_name:str(r.claimed_name,255),directory_president:str(r.directory_president,255),applicant_identity:{first_name:str(identity.first_name,255),last_name:str(identity.last_name,255)},correction_history:r.correction_history.map(raw=>{const h=obj(raw);keys(h,['from_claim_id','target_claim_id','previous_version','resubmitted_at']);return {from_claim_id:int(h.from_claim_id),target_claim_id:int(h.target_claim_id),previous_version:int(h.previous_version),resubmitted_at:str(h.resubmitted_at,40)};}),manual_review_history:r.manual_review_history.map(raw=>{const h=obj(raw);keys(h,['result','outcome','reviewer_id','reviewed_at','contacted_at']);return {result:str(h.result,32),outcome:str(h.outcome,32),reviewer_id:int(h.reviewer_id),reviewed_at:str(h.reviewed_at,40),contacted_at:h.contacted_at===null?null:str(h.contacted_at,40)};})};
}
function page<T>(raw:unknown,read:(raw:unknown)=>T):Page<T>{const r=obj(raw);if(!Array.isArray(r.data)||r.data.length>20)throw new FcaApiError(503);return {data:r.data.map(read),current_page:int(r.current_page),last_page:int(r.last_page)};}
function authority(raw:unknown):AuthorityApplication {const r=obj(raw);if(!['pending','authorized','rejected','revoked'].includes(String(r.status)))throw new FcaApiError(503);return {id:int(r.id),fca_id:int(r.fca_id),status:r.status as AuthorityApplication['status'],row_version:int(r.row_version,0)};}
function uuid(raw:unknown):string {const s=str(raw,36);if(!/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i.test(s))throw new FcaApiError(503);return s;}
export function createManualFcaApi(transport:Transport=boundedRequest,enabled=FCA_MANUAL_INTEGRATION_ENABLED,documentTransport:(path:string)=>Promise<Blob>=privateDocument){
 async function call(path:string,body?:unknown):Promise<unknown>{if(!enabled)throw new FcaApiError(404);try{return await transport('/api/'+path,body===undefined?undefined:{method:'POST',body});}catch(e){throw failure(e);}}
 return {
  directory:async(p=1)=>page(await call('fca-president/directory?page='+int(p)),raw=>{const r=obj(raw);keys(r,['id','official_name','address']);return {id:int(r.id),official_name:str(r.official_name,255),address:str(r.address)};}),
  mine:async(p=1)=>page(await call('fca-president/claims?page='+int(p)),readManualClaim),
  queue:async(p=1)=>page(await call('fca-president-review/claims?page='+int(p)),readManualClaim),
  claim:async(fca:number,input:ClaimInput)=>readManualClaim(await call('fca-president/directory/'+int(fca)+'/claim',input)),
  show:async(id:number)=>readManualClaim(await call('fca-president/claims/'+int(id))),
  resubmit:async(id:number,input:ClaimInput&{fca_id:number;expected_version:number;submission_key:string})=>readManualClaim(await call('fca-president/claims/'+int(id)+'/resubmit',input)),
  review:async(id:number)=>readReviewClaim(await call('fca-president-review/claims/'+int(id))),
  contact:async(id:number,version:number)=>{const r=obj(await call('fca-president-review/claims/'+int(id)+'/trusted-contact',{expected_version:int(version),purpose:'manual_identity_verification'}));keys(r,['trusted_phone','contact_version']);if(typeof r.trusted_phone!=='string'||!/^09\d{9}$/.test(r.trusted_phone))throw new FcaApiError(503);return {trusted_phone:r.trusted_phone,contact_version:int(r.contact_version)};},
  decide:async(id:number,input:ManualDecision)=>readManualClaim(await call('fca-president-review/claims/'+int(id)+'/manual-verification',input)),
  upload:async(file:File)=>{if(file.size>5*1024*1024||file.size===0||!['application/pdf','image/png','image/jpeg'].includes(file.type))throw new FcaApiError(422);const form=new FormData();form.set('evidence',file);const r=obj(await call('fca/evidence',form));keys(r,['receipt','status','mime','bytes']);return {receipt:uuid(r.receipt),status:str(r.status,32)};},
  evidence:async(receipt:string)=>{const r=obj(await call('fca/evidence/'+uuid(receipt)+'/status'));keys(r,['receipt','status','inspection_ready','expired']);return {receipt:uuid(r.receipt),status:str(r.status,32),inspection_ready:bool(r.inspection_ready),expired:bool(r.expired)};},
  submit:async(id:number,input:{representative_position:string;authorization_statement:string;evidence_receipt:string;submission_key:string})=>{const r=obj(await call('fca-president/claims/'+int(id)+'/application',input));keys(r,['id','status','phone_verified','verification_method']);if(r.status!=='pending'||r.phone_verified!==false||r.verification_method!=='manual')throw new FcaApiError(503);return int(r.id);},
  application:async(id:number)=>authority(obj(await call('fca/applications/'+int(id))).data),
  downloadEvidence:async(id:number)=>{if(!enabled)throw new FcaApiError(404);try{return await documentTransport('/api/fca/applications/'+int(id)+'/evidence');}catch(e){if(e instanceof FcaApiError)throw e;throw new FcaApiError(503);}},
  applications:async(p=1)=>page(await call('fca/review/pending?page='+int(p)),authority),
  authority:async(id:number,input:{decision:'authorize'|'reject'|'revoke';expected_version:number;decision_key:string;review_reason:string;expires_at?:string})=>authority(obj(await call('fca/review/'+int(id),input)).data),
 };
}
export type ManualFcaApi=ReturnType<typeof createManualFcaApi>;

/** Restricted intent uses a volatile bearer token, never authToken/localStorage. */
export async function openManualOnboarding(email:string,password:string,enabled=FCA_MANUAL_INTEGRATION_ENABLED):Promise<ManualFcaApi>{
 if(!enabled)throw new FcaApiError(404);
 let raw:unknown;try{raw=await boundedRequest('/api/fca-president/onboarding',{method:'POST',body:{email,password}});}catch(e){throw failure(e);}
 const r=obj(raw);keys(r,['token','scope','expires_in']);if(typeof r.token!=='string'||r.token.length<40||r.token.length>512||/[\r\n]/.test(r.token)||r.scope!=='fca:onboarding'||r.expires_in!==900)throw new FcaApiError(503);
 const token=r.token,expires=Date.now()+900000;
 return createManualFcaApi(async(path,options)=>{if(Date.now()>=expires)throw new FcaApiError(401);if(!path.startsWith('/api/fca-president/')||path.endsWith('/application'))throw new FcaApiError(403);return boundedRequest(path,{...options,headers:{Authorization:'Bearer '+token}});},true,async()=>{throw new FcaApiError(403);});
}
