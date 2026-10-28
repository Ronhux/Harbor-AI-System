import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
const source = readFileSync(new URL('../public/theme-init.js', import.meta.url), 'utf8');
function browser({ saved = null, dark = false, blocked = false } = {}) {
  const values = new Map(saved ? [['harborai_theme', saved]] : []);
  const mediaEvents = new Map();
  const resolveEvents = new Map();
  const media = { matches: dark, addEventListener: (name, listener) => mediaEvents.set(name, listener) };
  const window = { matchMedia: () => media, addEventListener: (name, listener) => resolveEvents.set(name, listener) };
  const localStorage = { getItem: key => { if (blocked) throw Error('Storage unavailable'); return values.get(key) ?? null; }, setItem: (key, value) => { if (blocked) throw Error('Storage unavailable'); values.set(key, value); } };
  vm.runInNewContext(source, { window, document: { documentElement: { style: {} } }, localStorage, Set });
  return {
    store: window.harborAppearance,
    values,
    system: (isDark) => { media.matches = isDark; mediaEvents.get('change')(); },
    storage: (key, newValue) => resolveEvents.get('storage')({ key, newValue }),
  };
}
test('defaults to System and resolves dark when OS is dark', () => {
  const b = browser({ dark: true });
  assert.equal(b.store.getPreference(), 'system');
  assert.equal(b.store.getResolvedTheme(), 'dark');
  assert.ok(b.store.isDark());
});
test('Light overrides OS dark and persists across reload', () => {
  const b = browser({ dark: true });
  b.store.setPreference('light');
  assert.equal(b.store.getResolvedTheme(), 'light');
  assert.ok(!b.store.isDark());
  b.system(false);
  b.system(true);
  assert.equal(b.store.getResolvedTheme(), 'light');
  assert.equal(b.values.get('harborai_theme'), 'light');
  b.store.setPreference('dark');
  assert.equal(b.store.getResolvedTheme(), 'dark');
  assert.ok(browser({ saved: b.values.get('harborai_theme') }).store.isDark());
});
test('System responds to OS changes after explicit preference', () => {
  const b = browser({ saved: 'light' });
  b.store.setPreference('system');
  b.system(true);
  assert.equal(b.store.getResolvedTheme(), 'dark');
  b.system(false);
  assert.equal(b.store.getResolvedTheme(), 'light');
});
test('cross-tab changes update the subscribed React store and can unsubscribe', () => {
  const b = browser(); let notifications = 0; let resolvedNotifications = 0;
  const unsubscribe = b.store.subscribe(() => notifications++);
  const unsubscribeResolved = b.store.subscribeResolved(() => resolvedNotifications++);
  b.storage('harborai_theme', 'dark');
  assert.equal(b.store.getPreference(), 'dark');
  assert.equal(b.store.getResolvedTheme(), 'dark');
  assert.ok(b.store.isDark());
  assert.equal(notifications, 1);
  assert.equal(resolvedNotifications, 1);
  unsubscribe(); unsubscribeResolved();
  b.storage('harborai_theme', 'light');
  assert.equal(notifications, 1);
  assert.equal(resolvedNotifications, 1);
  assert.equal(b.store.getResolvedTheme(), 'light');
});
test('invalid and unavailable storage falls back safely without blocking controls', () => {
  assert.equal(browser({ saved: 'invalid' }).store.getResolvedTheme(), 'light');
  const b = browser({ blocked: true });
  b.store.setPreference('dark');
  assert.equal(b.store.getResolvedTheme(), 'dark');
  b.store.setPreference('invalid');
  assert.equal(b.store.getPreference(), 'dark');
  assert.equal(b.store.getResolvedTheme(), 'dark');
  b.storage(null, null);
  assert.equal(b.store.getResolvedTheme(), 'light');
});
