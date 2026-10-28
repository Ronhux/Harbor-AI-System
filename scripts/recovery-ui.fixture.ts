import '../src/styles/index.css';
// Isolated headless component regression fixture. All fetches are intercepted below.
// Never loads credentials, authenticates, or calls the application server.
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';
import Workspace from '../src/app/pages/admin/tabs/KadiwaManagerWorkspace';
import DemandApprovals from '../src/app/pages/admin/tabs/DemandApprovals';
import LanguageProvider from '../src/app/i18n/LanguageProvider';

const results: string[] = [];
const host = document.createElement('div'); document.body.append(host);
const report = document.createElement('pre'); document.body.append(report);
const root = createRoot(host);
const calls: string[] = [];
const event = {id:1,title:'Fixture draft',description:'Isolated UI test only',venue:'Fixture venue',location:'Fixture locality',starts_at:'2026-11-16T08:00:00+08:00',ends_at:'2026-11-17T17:00:00+08:00',opens_at:'2026-10-20T00:00:00+08:00',closes_at:'2026-11-14T23:59:00+08:00',capacity:2,categories:['Vegetables'],requirements:['Display plan'],status:'draft',row_version:0,evidence_required:false,rsbsa_required:false};
window.fetch = async (input, options) => {
  const url=String(input);calls.push(url);
  let data: unknown;
  if(options?.method && options.method !== 'GET') throw new Error('Unexpected write in read-only UI fixture');
  if(url==='/api/kadiwa/capabilities') data={manager:true,coordinator:false,inquiry_reviewer:false,assigned_event_ids:[1]};
  else if(url==='/api/kadiwa/assigned') data=[event];
  else if(url==='/api/kadiwa/regular-schedule') data={rule:{ordinal:3,iso_weekday:1,following_day_offset:1,timezone:'Asia/Manila'},dates:[],notice:'Unconfirmed projection'};
  else if(url==='/api/kadiwa/events/1/queue') data={event,approved_count:0,available_slots:2,applications:[{id:2,event_id:1,status:'pending',row_version:0,created_at:'2026-10-16T08:00:00+08:00'}]};
  else if(url==='/api/kadiwa/applications/2') data={application:{id:2,event_id:1,status:'pending',row_version:0,created_at:'2026-10-16T08:00:00+08:00'},details:{commodities:[{commodity:'Cabbage',category:'Vegetables',quantity:'25.000',unit:'kg'}],participation_notes:'Test only'},history:[],review_notes:[]};
  else if(url==='/api/kadiwa/events/1/audit') data={data:[{action:'created',previous_status:null,new_status:'draft',reason:null,created_at:'2026-10-16T08:00:00+08:00'}]};
  else if(url==='/api/admin/demands') data={data:[{id:24,buyer:'Aparri Institution',product_name:'Rice',quantity_needed:'10',unit:'kg',status:'Active',review_status:'Pending Review',review_id:1,review_available:true,special_requirements:'Dry sacks',partial_fulfillment:false}]};
  else throw new Error('Unexpected URL '+url);
  return new Response(JSON.stringify(data), {status:200,headers:{'Content-Type':'application/json'}});
};
const pause=(ms=30)=>new Promise(resolve=>setTimeout(resolve,ms));
async function until(check:()=>boolean){for(let i=0;i<80;i++){if(check())return;await pause();}throw new Error('Timed out: '+host.textContent?.slice(0,500));}
function assert(value: unknown,message:string){if(!value)throw new Error(message);results.push('PASS '+message);report.textContent=results.join('\n');}
function button(text:string){const b=Array.from(host.querySelectorAll('button')).find(b=>b.textContent?.trim()===text);if(!b)throw new Error('Missing button '+text);b.click();}
function field(label:string): HTMLInputElement | HTMLSelectElement {const l=Array.from(host.querySelectorAll('label')).find(l=>l.textContent?.startsWith(label));const f=l?.querySelector('input,select');if(!(f instanceof HTMLInputElement || f instanceof HTMLSelectElement))throw new Error('Missing field '+label);return f;}
function change(f:HTMLInputElement|HTMLSelectElement,value:string){const proto=f instanceof HTMLSelectElement?HTMLSelectElement.prototype:HTMLInputElement.prototype;Object.getOwnPropertyDescriptor(proto,'value')!.set!.call(f,value);f.dispatchEvent(new Event(f instanceof HTMLSelectElement?'change':'input',{bubbles:true}));}
(async()=>{
  root.render(createElement(Workspace));
  await until(()=>host.textContent?.includes('+ Create KADIWA Event')===true && !!host.querySelector('input[type=month]'));
  const firstCalls=calls.length;await pause(250);assert(calls.length===firstCalls,'workspace rendering does not repeat API loads');
  change(field('Configured event'),'1');await pause();assert(field('Event title').value==='Fixture draft','existing draft loads with its title');
  button('+ Create KADIWA Event');await until(()=>!!host.querySelector('input[type=month]') && field('Event title').value==='');
  assert(field('Configured event').value==='','Create Event resets an existing selection to a new draft');
  change(field('Schedule month'),'2026-11');await pause();assert(field('Event starts').value==='2026-11-16T08:00' && field('Event ends').value==='2026-11-17T17:00','creation form prefills Monday through Tuesday');
  assert(!host.textContent?.includes('Participation review'),'Event Management has no participation review controls');
  button('Participation Management');await until(()=>host.textContent?.includes('KADIWA participation management')===true && Array.from(host.querySelectorAll('label')).some(l=>l.textContent?.startsWith('Configured event') && l.querySelector('option[value="1"]')));
  assert(!host.querySelector('input[type=month]') && !host.textContent?.includes('Save confirmed details as draft'),'Participation Management has no event creation form');
  change(field('Configured event'),'1');await until(()=>host.textContent?.includes('Participation review')===true);
  assert(host.textContent?.includes('available slots: 2'),'participation queue displays server capacity');
  change(field('Participation review'),'2');await until(()=>host.textContent?.includes('Approve participation')===true);
  assert(host.textContent?.includes('Reject participation') && host.textContent?.includes('Request participation clarification'),'participation decisions are in the participation pane');
  button('Activity History');await until(()=>host.textContent?.includes('New event')===true);
  assert(calls.includes('/api/kadiwa/events/1/audit'),'history automatically loads first event audit');
  root.render(createElement(LanguageProvider,null,createElement(DemandApprovals)));await until(()=>host.textContent?.includes('Aparri Institution')===true);
  assert(host.textContent?.includes('Aparri Institution'),'demand institution name renders from string response');
  const search=host.querySelector('input[aria-label="Search demand requests"]');if(!(search instanceof HTMLInputElement))throw new Error('Missing search');
  change(search,'aparri');await pause();assert(host.textContent?.includes('Aparri Institution'),'institution-name search retains the matching demand');
  button('View Details');await until(()=>document.body.textContent?.includes('Dry sacks')===true);
  assert(document.body.textContent?.includes('Partial fulfillment:') && document.body.textContent?.includes('Not allowed'),'details include special requirements and partial fulfillment');
  document.documentElement.dataset.testStatus='passed';report.textContent=results.join('\n')+'\nTOTAL '+results.length+' PASSED';
})().catch(error=>{root.render(null);document.documentElement.dataset.testStatus='failed';report.textContent=results.join('\n')+'\nFAIL '+error.stack;});
