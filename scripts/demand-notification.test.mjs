import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { stripTypeScriptTypes } from 'node:module';
const src=readFileSync(new URL('../src/app/contracts/notificationNavigation.ts',import.meta.url),'utf8');
const {notificationTarget}=await import('data:text/javascript;base64,'+Buffer.from(stripTypeScriptTypes(src)).toString('base64'));
test('demand decision opens buyer management with the actual record identity',()=>{
 assert.deepEqual(notificationTarget({navigation:{type:'buyer_demand',record_id:42}}),{tab:'demand',recordId:42});
 assert.deepEqual(notificationTarget({navigation:{type:'demand_review',record_id:42}}),{tab:'demands',recordId:42});
});
test('invalid demand references cannot navigate and transaction relationships retain priority',()=>{
 for(const record_id of [0,-1,NaN,1.5,'42',null]) assert.equal(notificationTarget({navigation:{type:'buyer_demand',record_id}}),null);
 assert.deepEqual(notificationTarget({order_id:7,navigation:{type:'buyer_demand',record_id:42}}),{tab:'orders',recordId:7});
});
