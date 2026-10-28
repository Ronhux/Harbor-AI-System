import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const appSource = readFileSync(new URL('../src/app/App.tsx', import.meta.url), 'utf8');
const dashboardShellSource = readFileSync(new URL('../src/app/components/dashboard/DashboardShell.tsx', import.meta.url), 'utf8');
const themeSelectorSource = readFileSync(new URL('../src/app/theme/ThemeSelector.tsx', import.meta.url), 'utf8');
const themeInitSource = readFileSync(new URL('../public/theme-init.js', import.meta.url), 'utf8');

test('App.tsx does not include ThemeSelector or PublicAppearance (public pages unaffected)', () => {
  assert.ok(!appSource.includes('ThemeSelector'), 'ThemeSelector must not be imported in App.tsx');
  assert.ok(!appSource.includes('PublicAppearance'), 'PublicAppearance must not be defined in App.tsx');
  assert.ok(!appSource.includes('publicPage'), 'publicPage prop must not be used in App.tsx');
});

test('DashboardShell imports ThemeSelector and applies data-harbor-dashboard-theme scope', () => {
  assert.ok(dashboardShellSource.includes("from '../../theme/ThemeSelector'"), 'DashboardShell must import ThemeSelector');
  assert.ok(dashboardShellSource.includes('data-harbor-dashboard-theme'), 'DashboardShell must set data-harbor-dashboard-theme attribute');
  assert.ok(dashboardShellSource.includes('useTheme'), 'DashboardShell must use useTheme hook');
});

test('ThemeSelector renders Light/Dark/System options with icons', () => {
  assert.ok(themeSelectorSource.includes('Sun'), 'ThemeSelector must include Sun icon for Light');
  assert.ok(themeSelectorSource.includes('Moon'), 'ThemeSelector must include Moon icon for Dark');
  assert.ok(themeSelectorSource.includes('Monitor'), 'ThemeSelector must include Monitor icon for System');
  assert.ok(themeSelectorSource.includes('role="radio"'), 'ThemeSelector options must be radio buttons');
  assert.ok(themeSelectorSource.includes('aria-checked'), 'ThemeSelector options must have aria-checked');
});

test('theme-init.js no longer applies dark class or colorScheme to documentElement', () => {
  assert.ok(!themeInitSource.includes('classList.toggle'), 'theme-init.js must not toggle dark class on documentElement');
  assert.ok(!themeInitSource.includes('colorScheme'), 'theme-init.js must not set colorScheme on documentElement');
  assert.ok(themeInitSource.includes('getResolvedTheme'), 'theme-init.js must expose getResolvedTheme');
  assert.ok(themeInitSource.includes('subscribeResolved'), 'theme-init.js must expose subscribeResolved');
  assert.ok(themeInitSource.includes('isDark'), 'theme-init.js must expose isDark');
});

test('theme.css custom variant targets data-harbor-dashboard-theme', () => {
  const themeCss = readFileSync(new URL('../src/styles/theme.css', import.meta.url), 'utf8');
  assert.ok(themeCss.includes('[data-harbor-dashboard-theme="dark"]'), 'theme.css must scope dark variant to data-harbor-dashboard-theme');
  assert.ok(themeCss.includes('#f5f1e5'), 'Light mode background should be original cream');
  assert.ok(themeCss.includes('#123c5c'), 'Light mode foreground should be original deep blue');
  assert.ok(themeCss.includes('#101916'), 'Dark mode background should be deep charcoal-green');
  assert.ok(themeCss.includes('#8FD0A7'), 'Dark mode primary should be muted emerald/sage');
  assert.ok(themeCss.includes('#1B2822'), 'Dark mode card should be muted dark green-gray');
  assert.ok(themeCss.includes('#EEF4EF'), 'Dark mode foreground should be warm off-white');
});
