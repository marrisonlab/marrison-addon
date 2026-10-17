/* Run: node tests/image-sizes-admin-js-test.js; regeneration scope and stop/restart contracts. */
'use strict';
const assert = require('assert');
const fs = require('fs');
const vm = require('vm');
const path = require('path');
const source = fs.readFileSync(path.join(__dirname, '../marrison-addon/assets/js/admin-image-sizes.js'), 'utf8');

function harness() {
    const controls = new Map();
    const requests = [];
    const confirmations = [];
    const document = {};
    class Control {
        constructor(id) { this.id = id; this.value = ''; this.visible = true; this.props = {}; this.events = {}; this.content = []; this.styles = {}; }
        on(event, fn) { this.events[event] = fn; return this; }
        val(value) { if (value === undefined) return this.value; this.value = value; return this; }
        prop(key, value) { if (value === undefined) return this.props[key]; this.props[key] = value; return this; }
        text(value) { if (value === undefined) return this.label || this.content.join(''); this.content = [value]; return this; }
        find() { return { text: () => this.value + ' (1440 × 550 px)' }; }
        show() { this.visible = true; return this; }
        hide() { this.visible = false; return this; }
        toggle(show) { this.visible = show; return this; }
        is() { return !!this.props.checked; }
        css(key, value) { this.styles[key] = value; return this; }
        empty() { this.content = []; return this; }
        append(value) { this.content.push(value); return this; }
        prepend(value) { this.content.unshift(value); return this; }
    }
    function $(selector) {
        if (selector === document) return { ready: fn => fn($) };
        if (!controls.has(selector)) controls.set(selector, new Control(selector));
        return controls.get(selector);
    }
    $.ajax = request => requests.push(request);
    vm.runInNewContext(source, {
        jQuery: $, document,
        confirm: message => { confirmations.push(message); return true; },
        marrison_vars: {
            ajax_url: '/wp-admin/admin-ajax.php', nonce: 'fixture', confirm_message: 'Tutte le miniature?',
            confirm_size_message: 'Solo %s?', regenerate_all: 'Rigenera tutte', regenerate_selected: 'Rigenera selezionata',
            process_stopped: 'Interrotto', found_images: 'Trovate %d immagini', no_images: 'Nessuna immagine',
            done_message: 'Finito', avif_testing: 'Test AVIF',
            done_summary: 'Completata. Riuscite: %1$d. Saltate: %2$d. Errori: %3$d.',
            done_with_errors: 'Terminata con errori. Riuscite: %1$d. Saltate: %2$d. Errori: %3$d.'
        }
    });
    return {
        $, requests, confirmations,
        choose: value => { $('#marrison-regenerate-size').val(value).events.change(); },
        click: id => $('#' + id).events.click({ preventDefault() {} })
    };
}

const selected = harness();
selected.$('#marrison-cleanup-disabled').prop('checked', true);
selected.choose('coverlarge');
assert.strictEqual(selected.$('#marrison-cleanup-disabled-controls').visible, false);
assert.strictEqual(selected.$('#marrison-regenerate-btn').text(), 'Rigenera selezionata');
selected.click('marrison-regenerate-btn');
assert.match(selected.confirmations[0], /coverlarge/);
assert.strictEqual(selected.$('#marrison-regenerate-size').prop('disabled'), true);
selected.requests[0].success({ success: true, data: { ids: [69, 97] } });
assert.strictEqual(selected.requests[1].data.size, 'coverlarge');
assert.strictEqual(selected.requests[1].data.cleanup, false, 'Targeted generation must never clean up other sizes.');
// Even a programmatic selection change must not alter the running scope.
selected.$('#marrison-regenerate-size').val('thumbnail');
selected.requests[1].success({ success: true, data: { message: 'Selected size generated' } });
assert.strictEqual(selected.requests[2].data.size, 'coverlarge');
selected.requests[2].success({ success: false, data: { message: 'Selected size failed' } });
assert.match(selected.$('#marrison-log-list').content.join(' '), /Selected size failed/);
assert.strictEqual(selected.requests[3].data.action, 'marrison_clear_elementor_css_cache');
assert.strictEqual(selected.$('#marrison-regenerate-size').prop('disabled'), false);
assert.match(selected.$('#marrison-log-list').content.join(' '), /Terminata con errori\. Riuscite: 1\. Saltate: 0\. Errori: 1\./);

const all = harness();
all.choose('thumbnail');
all.choose('');
all.$('#marrison-cleanup-disabled').prop('checked', true);
assert.strictEqual(all.$('#marrison-cleanup-disabled-controls').visible, true);
all.click('marrison-regenerate-btn');
assert.strictEqual(all.confirmations[0], 'Tutte le miniature?');
all.requests[0].success({ success: true, data: { ids: [97] } });
assert.strictEqual(all.requests[1].data.size, '', 'Full regeneration must retain its original scope.');
assert.strictEqual(all.requests[1].data.cleanup, true, 'Full regeneration must retain optional cleanup.');
all.requests[1].success({ success: true, data: { message: 'All sizes generated' } });
assert.strictEqual(all.$('#marrison-regenerate-btn').prop('disabled'), false);
assert.strictEqual(all.$('#marrison-cleanup-disabled').prop('disabled'), false);

const stopped = harness();
stopped.choose('coverlarge');
stopped.click('marrison-regenerate-btn');
stopped.requests[0].success({ success: true, data: { ids: [97, 98] } });
stopped.click('marrison-stop-btn');
assert.strictEqual(stopped.$('#marrison-regenerate-btn').prop('disabled'), true, 'A pending request must finish before another run can start.');
assert.strictEqual(stopped.$('#marrison-regenerate-size').prop('disabled'), true);
stopped.requests[1].success({ success: true, data: { message: 'Last in-flight image' } });
assert.strictEqual(stopped.requests.filter(r => r.data.action === 'marrison_regenerate_single_image').length, 1, 'Stopping must prevent the next image from starting.');
assert.strictEqual(stopped.requests[2].data.action, 'marrison_clear_elementor_css_cache', 'A stopped run must still refresh CSS after its in-flight change.');
assert.strictEqual(stopped.$('#marrison-regenerate-size').prop('disabled'), false);
assert.strictEqual(stopped.$('#marrison-regenerate-btn').prop('disabled'), false);
assert.notStrictEqual(stopped.$('#marrison-progress-fill').styles.width, '100%', 'A stopped queue must not appear complete.');

const earlyStop = harness();
earlyStop.click('marrison-regenerate-btn');
earlyStop.click('marrison-stop-btn');
earlyStop.requests[0].success({ success: true, data: { ids: [97] } });
assert.strictEqual(earlyStop.requests.length, 1, 'Stopping while loading IDs must not start any regeneration.');
assert.strictEqual(earlyStop.$('#marrison-regenerate-btn').prop('disabled'), false);

const failed = harness();
failed.choose('coverlarge');
failed.click('marrison-regenerate-btn');
failed.requests[0].error();
assert.strictEqual(failed.$('#marrison-regenerate-size').prop('disabled'), false);
assert.strictEqual(failed.$('#marrison-regenerate-btn').prop('disabled'), false);

const skipped = harness();
skipped.choose('coverlarge');
skipped.click('marrison-regenerate-btn');
skipped.requests[0].success({ success: true, data: { ids: [69, 97, 98] } });
skipped.requests[1].success({ success: true, data: { skipped: true, reason: 'svg', message: 'SVG saltato' } });
assert.match(skipped.$('#marrison-log-list').content[0], /class="skipped"/);
assert.doesNotMatch(skipped.$('#marrison-log-list').content[0], /class="error"|class="success"/);
skipped.requests[2].success({ success: true, data: { message: 'Raster rigenerato' } });
skipped.requests[3].error(null, 'timeout', 'timeout');
assert.match(skipped.$('#marrison-log-list').content.join(' '), /Riuscite: 1\. Saltate: 1\. Errori: 1\./, 'Skipped SVGs, successful rasters and genuine failures must have separate counts.');

// Starting another run must reset all counters; an SVG-only queue has zero errors.
const restartIndex = skipped.requests.length;
skipped.choose('');
skipped.click('marrison-regenerate-btn');
skipped.requests[restartIndex].success({ success: true, data: { ids: [69] } });
skipped.requests[restartIndex + 1].success({ success: true, data: { skipped: true, reason: 'svg', message: 'SVG saltato' } });
assert.match(skipped.$('#marrison-log-list').content.join(' '), /Completata\. Riuscite: 0\. Saltate: 1\. Errori: 0\./);
assert.doesNotMatch(skipped.$('#marrison-log-list').content.join(' '), /class="error"|Terminata con errori/);

console.log('Image Sizes admin selected/full/stop/SVG reporting: PASS');
