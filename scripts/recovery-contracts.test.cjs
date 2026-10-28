// Bundle with the installed Rolldown CLI (platform node, format cjs), then run with node. No network or database access.
const fs = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');
const assert = require('node:assert/strict');
const {readDemandReviews} = require('../src/app/contracts/adminDemandReviews.ts');
const {notificationTarget} = require('../src/app/contracts/notificationNavigation.ts');
const {proposedSchedule,fromManilaInput,toManilaInput} = require('../src/app/contracts/kadiwaSchedule.ts');
const {createKadiwaApi,readEvent,readApplication} = require('../src/app/components/mao/kadiwaApi.ts');
const event = {id:1,title:'Test only',description:'Synthetic contract fixture',venue:'Test hall',location:'Test locality',starts_at:'2026-11-16T08:00:00+08:00',ends_at:'2026-11-17T17:00:00+08:00',opens_at:'2026-10-20T00:00:00+08:00',closes_at:'2026-11-14T23:59:00+08:00',capacity:2,categories:['Vegetables'],requirements:[],status:'draft',row_version:0,evidence_required:false,rsbsa_required:false};
test('demand institution string and optional details match the backend', () => {
  const [row] = readDemandReviews({data:[{id:24,status:'Active',buyer:'Aparri Institution',product_name:'Rice',quantity_needed:'12.5',max_price_per_unit:'40.00',special_requirements:'Dry sacks',partial_fulfillment:false,review_available:true,review_status:'Pending Review'}]});
  assert.equal(row.buyer,'Aparri Institution');assert.ok(row.buyer.toLowerCase().includes('aparri'));
  assert.equal(row.quantity_needed,12.5);assert.equal(row.special_requirements,'Dry sacks');assert.equal(row.partial_fulfillment,false);
});
test('malformed demand buyer objects fail validation', () => assert.throws(() => readDemandReviews({data:[{id:1,status:'Active',buyer:{organization_name:'Wrong contract'}}]})));
test('demand review destination uses explicit metadata', () => assert.deepEqual(notificationTarget({navigation:{type:'demand_review',record_id:24}}),{tab:'demands',recordId:24}));
test('demand negotiation notices keep negotiation navigation', () => assert.deepEqual(notificationTarget({title:'Demand offer negotiation started',negotiation_id:9,navigation:{type:'demand_review',record_id:24}}),{tab:'negotiations',recordId:9}));
test('orders take precedence and titles cannot invent destinations', () => {
  assert.deepEqual(notificationTarget({order_id:8,negotiation_id:9}),{tab:'orders',recordId:8});
  assert.equal(notificationTarget({title:'New demand request awaiting review'}),null);
  assert.equal(notificationTarget({navigation:{type:'demand_review',record_id:-1}}),null);
});
test('proposed event spans third Monday and following Tuesday in Philippine time', () => {
  const p = proposedSchedule(2026,10);
  assert.equal(p.starts_at,'2026-11-16T08:00');assert.equal(p.ends_at,'2026-11-17T17:00');
  assert.ok(Date.parse(fromManilaInput(p.closes_at)) < Date.parse(fromManilaInput(p.starts_at)));
});
test('Philippine input parsing is independent of host timezone and rejects invalid dates', () => {
  const previous = process.env.TZ;
  try { for(const zone of ['UTC','America/Los_Angeles','Asia/Manila']) {process.env.TZ=zone;assert.equal(fromManilaInput('2026-11-16T08:00'),'2026-11-16T00:00:00.000Z');assert.equal(toManilaInput(new Date('2026-11-17T09:00:00Z')),'2026-11-17T17:00');assert.equal(proposedSchedule(2026,10).ends_at,'2026-11-17T17:00');} }
  finally {if(previous===undefined)delete process.env.TZ;else process.env.TZ=previous;}
  assert.throws(()=>fromManilaInput('2026-02-30T08:00'));
});
test('new event and participation records accept backend version zero', () => {
  assert.equal(readEvent(event).row_version,0);
  assert.equal(readApplication({id:2,event_id:1,status:'pending',row_version:0,created_at:'2026-10-16T08:00:00+08:00'}).row_version,0);
});
test('draft edit sends flat fields plus version to the existing endpoint', async () => {
  let sent;
  const api = createKadiwaApi(async (url,options) => {sent={url,options};return {...event,row_version:1};});
  await api.edit(1,0,{title:'Updated',description:'Test',categories:['Vegetables'],rsbsa_required:false,evidence_required:false});
  assert.equal(sent.options.method,'PATCH');assert.equal(sent.options.body.title,'Updated');assert.equal(sent.options.body.version,0);assert.equal(sent.options.body.changes,undefined);
});
test('activity history unwraps data and retains previous status and reason', async () => {
  const api = createKadiwaApi(async () => ({data:[{action:'published',previous_status:'draft',new_status:'open',reason:null,created_at:'2026-10-16T08:00:00+08:00'}]}));
  const rows=await api.audit(1);assert.equal(rows.length,1);assert.equal(rows[0].previous_status,'draft');assert.equal(rows[0].new_status,'open');
});
test('participation queue preserves capacity and version-zero applicants', async () => {
  const api=createKadiwaApi(async()=>({event:{...event,status:'open',row_version:1},approved_count:0,available_slots:2,applications:[{id:2,event_id:1,status:'pending',row_version:0,created_at:'2026-10-16T08:00:00+08:00'}]}));
  const queue=await api.queue(1);assert.equal(queue.available_slots,2);assert.equal(queue.applications[0].row_version,0);
});
