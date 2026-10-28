import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { stripTypeScriptTypes } from 'node:module';

const source = stripTypeScriptTypes(readFileSync(new URL('../src/lib/authStorage.ts', import.meta.url), 'utf8'))
  .replace("import { request } from './api';", "const { request } = require('./api');")
  .replaceAll('export ', '') + '\nexports.logoutSession = logoutSession;';
function fixture(status, blocked = false) {
  const values = new Map([['authToken', 'synthetic-token'], ['userRole', 'producer'], ['harborai_theme', 'dark'], ['harborai_lang', 'fil']]);
  const exports = {};
  let calls = 0;
  vm.runInNewContext(source, {
    exports,
    require: () => ({ request: async (path, options) => {
      calls++;
      assert.equal(path, '/api/logout'); assert.equal(options.method, 'POST');
      if (status) throw Object.assign(new Error('synthetic failure'), { status });
    } }),
    localStorage: { removeItem: key => { if (blocked) throw Error('blocked'); values.delete(key); } },
    sessionStorage: { removeItem: () => { if (blocked) throw Error('blocked'); } },
  });
  return { values, logout: exports.logoutSession, calls: () => calls };
}
test('server logout removes auth while preserving independent preferences', async () => {
  const f = fixture(); await f.logout(); assert.equal(f.calls(), 1);
  assert.equal(f.values.has('authToken'), false); assert.equal(f.values.has('userRole'), false);
  assert.equal(f.values.get('harborai_theme'), 'dark'); assert.equal(f.values.get('harborai_lang'), 'fil');
});
test('expired session clears stale auth and preserves preferences', async () => {
  const f = fixture(401); await f.logout(); assert.equal(f.values.has('authToken'), false); assert.equal(f.values.get('harborai_theme'), 'dark');
});
test('server failure preserves auth for a retry instead of claiming logout', async () => {
  const f = fixture(503); await assert.rejects(f.logout(), /synthetic failure/); assert.equal(f.values.get('authToken'), 'synthetic-token');
});
test('successful server logout survives restricted local browser storage', async () => {
  const f = fixture(undefined, true); await f.logout(); assert.equal(f.calls(), 1);
});
