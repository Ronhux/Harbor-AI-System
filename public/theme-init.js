(function () {
  if (window.harborAppearance) return;
  var key = 'harborai_theme';
  var preference = 'system';
  var media = window.matchMedia('(prefers-color-scheme: dark)');
  var listeners = new Set();
  var resolveListeners = new Set();
  function valid(value) { return value === 'light' || value === 'dark' || value === 'system'; }
  try { var saved = localStorage.getItem(key); if (valid(saved)) preference = saved; } catch (_) {}
  function resolvedDark() {
    return preference === 'dark' || (preference === 'system' && media.matches);
  }
  function notify() { listeners.forEach(function (listener) { listener(); }); }
  function notifyResolved() { resolveListeners.forEach(function (listener) { listener(); }); }
  window.harborAppearance = {
    getPreference: function () { return preference; },
    setPreference: function (value) { if (!valid(value)) return; preference = value; try { localStorage.setItem(key, value); } catch (_) {} notify(); notifyResolved(); },
    subscribe: function (listener) { listeners.add(listener); return function () { listeners.delete(listener); }; },
    subscribeResolved: function (listener) { resolveListeners.add(listener); return function () { resolveListeners.delete(listener); }; },
    getResolvedTheme: function () { return resolvedDark() ? 'dark' : 'light'; },
    isDark: resolvedDark
  };
  media.addEventListener('change', function () { notify(); notifyResolved(); });
  window.addEventListener('storage', function (event) { if (event.key === key || event.key === null) { preference = valid(event.newValue) ? event.newValue : 'system'; notify(); notifyResolved(); } });
})();
