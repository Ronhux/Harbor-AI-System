import test from 'node:test';
import assert from 'node:assert/strict';

const enModule = await import('../src/app/i18n/en.ts');
const filModule = await import('../src/app/i18n/fil.ts');
const en = enModule.en;
const fil = filModule.fil;

function collectKeys(obj, prefix = '') {
  const keys = [];
  for (const [k, v] of Object.entries(obj)) {
    const path = prefix ? `${prefix}.${k}` : k;
    if (typeof v === 'string') {
      keys.push(path);
    } else if (v && typeof v === 'object' && !Array.isArray(v)) {
      keys.push(...collectKeys(v, path));
    }
  }
  return keys.sort();
}

function getNested(obj, path) {
  return path.split('.').reduce((acc, k) => (acc && acc[k] !== undefined ? acc[k] : undefined), obj);
}

const enKeys = collectKeys(en);
const filKeys = collectKeys(fil);

test('en and fil have identical translation key sets', () => {
  const enSet = new Set(enKeys);
  const filSet = new Set(filKeys);

  const missingInFil = enKeys.filter(k => !filSet.has(k));
  const missingInEn = filKeys.filter(k => !enSet.has(k));

  assert.equal(missingInFil.length, 0, `Keys missing in fil: ${missingInFil.join(', ')}`);
  assert.equal(missingInEn.length, 0, `Keys missing in en: ${missingInEn.join(', ')}`);
});

test('every translation value is a non-empty string', () => {
  for (const key of enKeys) {
    const enValue = getNested(en, key);
    const filValue = getNested(fil, key);
    assert.ok(typeof enValue === 'string' && enValue.length > 0, `en.${key} is not a non-empty string`);
    assert.ok(typeof filValue === 'string' && filValue.length > 0, `fil.${key} is not a non-empty string`);
  }
});

const backupModule = await import('../src/app/i18n/backupMessages.ts');
const { backupFil } = backupModule;

test('backupMessages Filipino values are all non-empty strings', () => {
  const backupKeys = Object.keys(backupFil);
  assert.ok(backupKeys.length > 0, 'backupMessages should have keys');
  for (const key of backupKeys) {
    assert.ok(typeof backupFil[key] === 'string' && backupFil[key].length > 0, `backupFil.${key} is not a non-empty string`);
  }
});
