'use strict';
const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const source = fs.readFileSync(require('path').join(__dirname, '../marrison-addon/assets/js/marrison-cursor.js'), 'utf8');
function classList() { const values = new Set(); return { add: (...items) => items.forEach(item => values.add(item)), remove: (...items) => items.forEach(item => values.delete(item)), contains: item => values.has(item) }; }
for (const animation of ['lag', 'fast', 'elastic']) {
    let writes = 0;
    let nextId = 0;
    const frames = new Map();
    const listeners = {};
    const windowListeners = {};
    const document = {
        hidden: false,
        documentElement: { classList: classList() },
        body: { classList: classList(), appendChild() {} },
        createElement() { return { style: new Proxy({}, { set(target, name, value) { if (name === 'transform') { writes++; } target[name] = value; return true; } }) }; },
        addEventListener: (name, fn) => { listeners[name] = fn; }
    };
    const window = {
        location: { search: '' }, innerWidth: 1200, innerHeight: 800,
        matchMedia: query => ({ matches: query === '(pointer: fine)' }),
        requestAnimationFrame(fn) { frames.set(++nextId, fn); return nextId; },
        cancelAnimationFrame(id) { frames.delete(id); },
        addEventListener: (name, fn) => { windowListeners[name] = fn; }
    };
    vm.runInNewContext(source, { window, document, URLSearchParams, marrison_cursor_settings: { animation } });
    function move(x, disabled = false) { listeners.pointermove({ clientX: x, clientY: 100, target: { closest: selector => disabled && selector.startsWith('#wpadminbar') ? {} : null } }); }
    function flush(limit = 600) { let count = 0; while (frames.size && count++ < limit) { const scheduled = [...frames.values()]; frames.clear(); scheduled.forEach(fn => fn()); } return count; }
    assert.equal(frames.size, 0, 'No frame before pointer enters.');
    move(100); flush();
    move(700); assert(frames.size > 0); flush();
    assert.equal(frames.size, 0, animation + ' must settle.');
    const stableWrites = writes; flush(); assert.equal(writes, stableWrites);
    move(800); listeners.pointerleave(); assert.equal(frames.size, 0); flush(); assert.equal(writes, stableWrites);
    move(600); assert.equal(frames.size, 1); document.hidden = true; listeners.visibilitychange(); assert.equal(frames.size, 0);
    document.hidden = false; move(500); flush(); move(900, true); assert.equal(frames.size, 0);
    move(650); assert.equal(frames.size, 1); windowListeners.blur(); assert.equal(frames.size, 0);
}
console.log('Cursor: no idle, hidden, disabled-zone or settled RAF; movement restarts all three animations PASS.');
