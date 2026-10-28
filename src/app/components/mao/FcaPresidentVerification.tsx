import {useEffect,useRef,useState} from 'react';
import {Button} from '../ui/button';
import {Input} from '../ui/input';
export const FCA_PRESIDENT_VERIFICATION_UI_ENABLED=true;
export interface PresidentClaimRead {id:number;fca_id:number;row_version:number;status:'manual_review'|'phone_required'|'phone_verified'|'rejected'|'revoked';phone_verified:boolean;phone_display:string;authority_status:'pending'|'authorized'|'rejected'|'revoked'}
/** No transport is installed by default. Future client must use restricted authenticated endpoints. */
export interface PresidentVerificationApi {
 claim(fca:number,association:string,name:string,phone:string,consent:boolean):Promise<unknown>;
 send(claim:number):Promise<unknown>;verify(challenge:string,code:string):Promise<unknown>;
}
export function presidentClaimRead(raw:unknown):PresidentClaimRead {
 if(!raw||typeof raw!=='object'||Array.isArray(raw))throw new Error('Invalid verification response.');
 const r=raw as Record<string,unknown>,keys=['id','fca_id','row_version','status','phone_verified','phone_display','authority_status'];
 if(Object.keys(r).length!==keys.length||Object.keys(r).some(k=>!keys.includes(k))||!Number.isSafeInteger(r.id)||Number(r.id)<1||!Number.isSafeInteger(r.fca_id)||Number(r.fca_id)<1||!Number.isSafeInteger(r.row_version)||Number(r.row_version)<1||!['manual_review','phone_required','phone_verified','rejected','revoked'].includes(String(r.status))||typeof r.phone_verified!=='boolean'||!['Confidential MAO number','***********'].includes(String(r.phone_display))||!['pending','authorized','rejected','revoked'].includes(String(r.authority_status))||r.phone_verified&&r.status!=='phone_verified')throw new Error('Invalid verification response.');
 return r as unknown as PresidentClaimRead;
}
export function otpResult(raw:unknown,kind:'send'|'verify'):Record<string,unknown>{
 if(!raw||typeof raw!=='object'||Array.isArray(raw))throw new Error('Invalid OTP result.');
 const r=raw as Record<string,unknown>,keys=kind==='send'?['challenge_id','status','phone_verified']:['status','phone_verified','authority_status'];
 if(Object.keys(r).some(key=>!keys.includes(key))||typeof r.phone_verified!=='boolean')throw new Error('Invalid OTP result.');
 if(kind==='send'){
  if(r.phone_verified!==false||!['code_required','unavailable'].includes(String(r.status))||typeof r.challenge_id!=='string'||!(/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/).test(r.challenge_id))throw new Error('Invalid OTP result.');
 }else if(!['phone_verified','code_rejected','verification_unavailable'].includes(String(r.status))||r.phone_verified!==(r.status==='phone_verified')||(r.status!=='verification_unavailable'&&r.authority_status!=='pending')||(r.authority_status!==undefined&&r.authority_status!=='pending'))throw new Error('Invalid OTP result.');
 return r;
}
export default function FcaPresidentVerification({fcaId,association,api}:{fcaId:number;association:string;api?:PresidentVerificationApi}){
 const [consent,setConsent]=useState(false);const [name,setName]=useState(''),[phone,setPhone]=useState(''),[code,setCode]=useState(''),[claim,setClaim]=useState<PresidentClaimRead|null>(null),[challenge,setChallenge]=useState<string|null>(null),[notice,setNotice]=useState('Real SMS and this UI are disabled pending approved configuration.'),[busy,setBusy]=useState(false);const ticket=useRef(0);
 const enabled=FCA_PRESIDENT_VERIFICATION_UI_ENABLED&&Boolean(api);
 useEffect(()=>{ticket.current++;setClaim(null);setChallenge(null);setCode('');return ()=>{ticket.current++;};},[fcaId,association]);
 const run=async(kind:'claim'|'send'|'verify')=>{
  if(!enabled||!api)return;const current=++ticket.current;setBusy(true);
  try{
   if(kind==='claim'){const value=presidentClaimRead(await api.claim(fcaId,association,name,phone,consent));if(current!==ticket.current)return;setClaim(value);setChallenge(null);setNotice(value.status.replaceAll('_',' '));}
   else if(kind==='send'&&claim){const raw=otpResult(await api.send(claim.id),'send');if(current!==ticket.current)return;setChallenge(raw.status==='code_required'?raw.challenge_id as string:null);setNotice(raw.status==='code_required'?'Provider accepted the request. Phone is not verified yet.':'SMS unavailable; no verification completed.');}
   else if(kind==='verify'&&challenge){const raw=otpResult(await api.verify(challenge,code),'verify');if(current!==ticket.current)return;setCode('');setNotice(raw.phone_verified?'Phone verified by the server; FCA authority remains pending Admin review.':'Verification was not completed.');if(raw.phone_verified)setChallenge(null);}
  }catch{if(current===ticket.current)setNotice('Verification unavailable; no success assumed.');}finally{if(current===ticket.current)setBusy(false);}
 };
 return <section aria-label="FCA president verification" aria-busy={busy} className="space-y-3 rounded border p-4"><h3 className="font-semibold">FCA president phone verification</h3><p>Phone possession does not approve identity, account, RSBSA, FCA authority or procurement eligibility. The server uses the confidential MAO destination, never a submitted destination override.</p>
 <label>Claimed president identity<Input maxLength={255} disabled={!enabled||busy} value={name} onChange={e=>{ticket.current++;setClaim(null);setChallenge(null);setName(e.target.value);}}/></label>
 <label>Applicant contact number<Input type="tel" maxLength={40} disabled={!enabled||busy} value={phone} onChange={e=>{ticket.current++;setClaim(null);setChallenge(null);setPhone(e.target.value);}}/></label>
 <label><Input type="checkbox" disabled={!enabled||busy} checked={consent} onChange={e=>setConsent(e.target.checked)}/>I request phone verification under the approved processing notice.</label><Button disabled={!enabled||busy||!consent} onClick={()=>void run('claim')}>Request directory verification</Button><Button disabled={!enabled||busy||claim?.status!=='phone_required'} onClick={()=>void run('send')}>Request SMS code</Button>
 <label>SMS verification code<Input inputMode="numeric" maxLength={6} autoComplete="one-time-code" disabled={!enabled||busy||!challenge} value={code} onChange={e=>setCode(e.target.value)}/></label><Button disabled={!enabled||busy||!challenge} onClick={()=>void run('verify')}>Verify phone</Button>
 <p role="status">{notice}</p>{claim&&<p>Phone: {claim.phone_display}; claim: {claim.status}; FCA authority record: {claim.authority_status}. No authority granted by this screen.</p>}<Button disabled>Approve FCA authority</Button></section>;
}
