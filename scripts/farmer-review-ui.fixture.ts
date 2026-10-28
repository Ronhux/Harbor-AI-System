import '../src/styles/index.css';
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';
import { MemoryRouter } from 'react-router-dom';
import LanguageProvider from '../src/app/i18n/LanguageProvider';
import Registry from '../src/app/pages/admin/tabs/RegistryManagement';
import Login from '../src/app/pages/LoginPage';
const host=document.createElement('div');document.body.append(host);const report=document.createElement('pre');document.body.append(report);const root=createRoot(host);
const results:string[]=[];let rows:any[]=[];let writes:any[]=[];let mode='ok';let release:(()=>void)|null=null;let seq=0;
const applicant={producer_id:71,user_id:81,name:'Synthetic Review Farmer',verification_status:'Pending',rsbsa_number:'SYNTHETIC-ONLY',location:'Synthetic locality',registry_match:{valid_match:true,status:'REGISTRY_MATCH_NAME_MATCH'}};
window.fetch=async(input,options)=>{
 const url=String(input);let data:any={};let status=200;
 if(url==='/sanctum/csrf-cookie')return new Response(null,{status:204});
 if(url.startsWith('/api/admin/registry/search'))data={data:[],last_page:1,total:0};
 else if(url==='/api/admin/producers/pending')data={data:rows};
 else if(url==='/api/admin/producers/71/verification'){
  const body=JSON.parse(String(options?.body));writes.push(body);
  if(mode==='delay')await new Promise<void>(resolve=>{release=resolve});
  if(mode==='network')throw new TypeError('Synthetic network failure');
  if(['403','409','500'].includes(mode)){status=Number(mode);data={message:status===500?'SQL SECRET diagnostic':'Review request denied'};}
  else {rows=body.verification_status==='Verified'?[]:[{...applicant,verification_status:'Rejected'}];data={producer:{...applicant,verification_status:body.verification_status}};}
 }
 else if(url==='/api/login'){status=403;data={registration_status:mode==='pending'?'Pending':'Rejected',applicant_reason:mode==='pending'?null:'Synthetic applicant-facing explanation'};}
 else throw new Error('Fixture blocked unexpected request '+url);
 return new Response(JSON.stringify(data),{status,headers:{'Content-Type':'application/json'}});
};
const pause=()=>new Promise(resolve=>setTimeout(resolve,20));
async function until(fn:()=>boolean){for(let i=0;i<180;i++){if(fn())return;await pause()}throw new Error('Timeout '+document.body.textContent?.slice(-1800));}
function assert(condition:unknown,message:string){if(!condition)throw new Error(message);results.push('PASS '+message);report.textContent=results.join('\n');}
function find(text:string){return Array.from(document.querySelectorAll('button')).find(b=>b.textContent?.trim()===text)}
function click(text:string){const b=find(text);if(!b)throw new Error('Missing '+text);b.click();}
function change(field:HTMLInputElement|HTMLTextAreaElement,value:string){const proto=field instanceof HTMLTextAreaElement?HTMLTextAreaElement.prototype:HTMLInputElement.prototype;Object.getOwnPropertyDescriptor(proto,'value')!.set!.call(field,value);field.dispatchEvent(new Event('input',{bubbles:true}));field.dispatchEvent(new Event('change',{bubbles:true}));}
async function mount(status='Pending'){root.render(null);await until(()=>host.textContent==='');rows=[{...applicant,verification_status:status}];writes=[];root.render(createElement(LanguageProvider,{key:++seq},createElement(Registry)));await until(()=>!!find('Approve'));}
(async()=>{
 if(new URLSearchParams(location.search).has('manual')){await mount();document.documentElement.dataset.testStatus='manual';report.textContent='Manual synthetic component inspection. All API calls are mocked.';return;}

 await mount();click('Approve');await until(()=>!!find('Confirm approval'));assert(writes.length===0,'Approve opens confirmation without submitting');click('Cancel');await until(()=>!find('Confirm approval'));assert(writes.length===0,'Cancel performs no write');
 click('Approve');await until(()=>!!find('Confirm approval'));mode='delay';const confirmButton=find('Confirm approval')!;confirmButton.click();confirmButton.click();await until(()=>writes.length===1&&release!==null&&find('Saving...')?.disabled===true);assert(find('Saving...')?.disabled,'Saving disables confirmation');release!();await until(()=>document.body.textContent?.includes('Farmer registration approved successfully.')===true);assert(writes.length===1,'Repeated clicks cause one request');assert(!find('Approve'),'Approved applicant leaves queue');
 mode='ok';await mount();click('Reject');await until(()=>!!find('Confirm rejection'));assert(find('Confirm rejection')?.disabled,'Reject requires a reason');change(document.querySelector('#registration-reason')!,'Synthetic explanation for the applicant');await until(()=>!find('Confirm rejection')?.disabled);click('Confirm rejection');await until(()=>document.body.textContent?.includes("Farmer registration rejected. The applicant's status has been updated.")===true);assert(writes[0]?.reason==='Synthetic explanation for the applicant','Rejection sends applicant reason');
 for(const failure of ['403','409','500','network']){mode=failure;await mount();click('Approve');await until(()=>!!find('Confirm approval'));click('Confirm approval');await until(()=>document.body.textContent?.includes('Approval Failed')===true);assert(!document.body.textContent?.includes('SQL SECRET'),'Failure '+failure+' is visible and sanitized');click('Done');await until(()=>!find('Done'));assert(find('Approve')&&!find('Approve')!.disabled,'Failure '+failure+' releases controls');}

 mode='ok';await mount('Rejected');assert(find('Approve')?.disabled&&find('Reject')?.disabled,'Decided applications cannot be reviewed again');
 for(const state of ['rejected','pending']){mode=state;root.render(null);await until(()=>host.textContent==='');root.render(createElement(MemoryRouter,{key:++seq},createElement(LanguageProvider,null,createElement(Login))));await until(()=>!!document.querySelector('#identifier'));change(document.querySelector('#identifier')!,'09179990000');change(document.querySelector('#password')!,'test-only');await pause();host.querySelector('form')!.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));await until(()=>!!find('Return to Login'));assert(document.body.textContent?.includes(state==='rejected'?'Registration Not Approved':'Pending Review'),'Login renders secure '+state+' outcome');assert(localStorage.getItem('authToken')===null,'Status outcome does not store auth token');click('Return to Login');}
 document.documentElement.dataset.testStatus='passed';report.textContent=results.join('\n')+'\nTOTAL '+results.length+' PASSED';
})().catch(error=>{root.render(null);document.documentElement.dataset.testStatus='failed';report.textContent=results.join('\n')+'\nFAIL '+error.stack});
