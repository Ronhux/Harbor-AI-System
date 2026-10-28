import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {parse} from '@babel/parser';
const source=readFileSync(new URL('../src/app/pages/admin/tabs/RegistryManagement.tsx',import.meta.url),'utf8');
const ast=parse(source,{sourceType:'module',plugins:['typescript','jsx']});
const component=ast.program.body.find(n=>n.type==='ExportDefaultDeclaration').declaration;
function nodes(n, predicate, found=[]){if(!n||typeof n!=='object')return found;if(predicate(n))found.push(n);for(const [key,value] of Object.entries(n)){if(['loc','start','end'].includes(key))continue;if(Array.isArray(value))value.forEach(v=>nodes(v,predicate,found));else if(value&&typeof value==='object')nodes(value,predicate,found);}return found;}
test('registration decision and result dialogs belong to the returned React tree',()=>{
 const statements=component.body.body;
 const index=statements.findIndex(n=>n.type==='ReturnStatement');
 assert.ok(index>=0);
 assert.equal(statements.slice(index+1).filter(n=>n.type!=='EmptyStatement').length,0,'no unreachable modal expressions after return');
 const names=nodes(statements[index].argument,n=>n.type==='JSXOpeningElement').map(n=>n.name.name);
 assert.ok(names.includes('DecisionDialog'),'confirmation must be mounted by the returned UI');
 assert.ok(names.includes('FeedbackModal'),'real decision outcome must be mounted');
});
test('review confirmation is an accessible dialog and cannot silently auto-approve',()=>{
 const dialog=ast.program.body.find(n=>n.type==='FunctionDeclaration'&&n.id.name==='DecisionDialog');
 const names=nodes(dialog,n=>n.type==='JSXOpeningElement').map(n=>n.name.name);
 for(const name of ['Dialog','DialogTitle','DialogDescription','DialogFooter','textarea'])assert.ok(names.includes(name));
 const handler=nodes(dialog,n=>n.type==='JSXAttribute'&&n.name.name==='onClick').at(-1);
 const calls=nodes(handler,n=>n.type==='CallExpression'&&n.callee.name==='onConfirm');
 assert.equal(calls.length,1);assert.equal(calls[0].arguments.length,2,'confirmed decision must carry applicant reason');
});
