(function () {
  'use strict';
  const active = new Map();
  const requests = new Map();
  function rows(container, entries) {
    if (!container) return;
    const fragment = document.createDocumentFragment();
    entries.forEach(entry => { const row = document.createElement('div'); row.className = 'marrison-bh__row'; ['label','hours'].forEach((key,i) => { const cell = document.createElement('span'); cell.className = i ? 'marrison-bh__times' : 'marrison-bh__day'; cell.textContent = entry[key]; row.append(cell); }); fragment.append(row); });
    container.replaceChildren(fragment);
  }
  function start(root) {
    if (active.has(root)) return;
    let config;
    try { config = JSON.parse(root.dataset.marrisonBusinessHours); } catch (_) { return; }
    let timer, refreshing = false, validUntil = 0;
    const state = {refresh:refresh}; active.set(root,state);
    async function refresh() {
      if (refreshing) return;
      clearTimeout(timer);
      if (!root.isConnected) { active.delete(root); return; }
      if (document.hidden) { timer = setTimeout(refresh,60000); return; }
      let delay = 60000;
      const status = root.querySelector('.marrison-bh__status');
      if (Date.now() >= validUntil - 50) {
        if (config.field) root.textContent = 'Verifica orari…';
        else { if (status) status.dataset.state = 'unknown'; const text = root.querySelector('.marrison-bh__status-text'); if (text) text.textContent = 'Verifica orari…'; }
      }
      refreshing = true;
      const requestedAt = Date.now();
      try {
        const url = new URL(config.endpoint,location.href); url.searchParams.set('action','marrison_business_hours'); url.searchParams.set('options',JSON.stringify(config.options || {}));
        if (config.field) { url.searchParams.set('field',config.field); url.searchParams.set('day',config.day || 1); }
        const key = url.toString();
        if (!requests.has(key)) {
          url.searchParams.set('_',Date.now());
          const controller = new AbortController(), timeout = setTimeout(() => controller.abort(),15000);
          const promise = fetch(url,{cache:'no-store',credentials:'omit',signal:controller.signal}).then(response => { if (!response.ok) throw new Error('HTTP'); return response.json(); }).then(payload => { if (!payload.success || !payload.data) throw new Error('Data'); return payload.data; }).finally(() => { clearTimeout(timeout); requests.delete(key); });
          requests.set(key,promise);
        }
        const data = await requests.get(key);
        if (!root.isConnected) return;
        const remaining = data.status.refresh_in * 1000 - (Date.now() - requestedAt);
        delay = Math.max(100,Math.min(60000, remaining));
        if (remaining <= 0) throw new Error('Transition reached during request');
        validUntil = Date.now() + remaining;
        if (config.field) root.textContent = data.value || '';
        else {
          const text = root.querySelector('.marrison-bh__status-text'); if (text) text.textContent = data.text;
          if (status) status.dataset.state = data.status.state;
          rows(root.querySelector('.marrison-bh__rows'),data.rows);
          const exceptions = root.querySelector('.marrison-bh__exceptions'); if (exceptions) { exceptions.hidden = !data.exceptions.length; rows(exceptions.querySelector('.marrison-bh__exception-rows'),data.exceptions); }
        }
      } catch (_) {
        // A failed refresh must never leave an old open/closed claim visible.
        if (config.field) root.textContent = 'Orari non disponibili';
        else { if (status) status.dataset.state = 'unknown'; const text = root.querySelector('.marrison-bh__status-text'); if (text) text.textContent = 'Stato non disponibile'; }
      } finally { refreshing = false; }
      timer = setTimeout(refresh,delay);
    }
    refresh();
  }
  function scan() { document.querySelectorAll('[data-marrison-business-hours]').forEach(start); active.forEach((_,root) => { if (!root.isConnected) active.delete(root); }); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',scan); else scan();
  let pending = false;
  new MutationObserver(records => { if (pending || !records.some(r => [...r.addedNodes].some(n => n.nodeType === 1 && (n.matches('[data-marrison-business-hours]') || n.querySelector('[data-marrison-business-hours]'))))) return; pending = true; queueMicrotask(() => { pending = false; scan(); }); }).observe(document.documentElement,{childList:true,subtree:true});
  document.addEventListener('visibilitychange',() => { if (!document.hidden) active.forEach(state => state.refresh()); });
  window.addEventListener('pageshow',() => active.forEach(state => state.refresh()));
})();
