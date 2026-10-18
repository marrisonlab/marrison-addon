/* Authenticated coordinator: resumable HTTP crawl and isolated browser measurements. */
(function ($) {
    'use strict';
    var config = window.marrisonCookieScanner;
    if (!config) { return; }
    var engine = {
        running: false, job: null, frame: null, timer: null, generation: 0,
        request: function (action, data) {
            return new Promise(function (resolve, reject) {
                $.ajax({ url: config.ajaxUrl, type: 'POST', data: $.extend({ action: 'marrison_addon_cookie_engine_' + action, nonce: config.nonce }, data || {}),
                    success: function (response) {
                        if (!response || !response.success) { var error = new Error(response && response.data && response.data.message || 'Risposta dello scanner non valida.'); error.code = response && response.data && response.data.code; reject(error); return; }
                        var runtime = response.data && response.data.runtime;
                        if (!runtime || runtime.protocol !== config.protocol || runtime.revision !== config.revision || !runtime.compatible) { reject(new Error('Il motore PHP attivo non corrisponde ai file dello scanner. Verifica aggiornamento, copie del plugin e cache PHP.')); return; }
                        $('#marrison-engine-runtime').text('v' + runtime.plugin_version + ' · ' + runtime.revision + ' · ' + runtime.scanner_hash.slice(0, 12));
                        engine.runtime = runtime;
                        resolve(response.data);
                    },
                    error: function (xhr) {
                        var data = xhr.responseJSON && xhr.responseJSON.data;
                        reject(new Error(data && data.message || 'Scansione interrotta: risposta HTTP ' + xhr.status + '. Puoi riprendere dal punto salvato.'));
                    }
                });
            });
        },
        status: function (text, failed) {
            $('#marrison-scan-status, #wizard_scan_status').text(text).toggleClass('error marrison-scan-status-error', !!failed).show();
        },
        render: function (job) {
            this.job = job;
            $('#marrison-scan-cancel').prop('hidden', !job || ['http', 'browser'].indexOf(job.status) < 0);
            $('#marrison-scan-resume').prop('hidden', !job || this.running || ['http', 'browser'].indexOf(job.status) < 0);
            $('#marrison-scan-cookies, #wizard_scan_button').prop('disabled', this.running);
            if (!job) { $('#marrison-engine-summary').text('Nessuna scansione del nuovo motore.'); return; }
            var names = { http: 'Scansione delle pagine', browser: 'Analisi nel browser', completed: 'Scansione completata', partial: 'Scansione parziale', cancelled: 'Scansione interrotta', failed: 'Scansione non riuscita' };
            $('#marrison-engine-summary').text((names[job.status] || job.status) + ' · ' + job.http_done + ' pagine HTTP · ' + job.browser_done + '/' + job.browser_total + ' prove browser · ' + job.cookie_count + ' cookie distinti');
            $('#marrison-engine-coverage').text(job.coverage + (job.limit_reached ? ' Raggiunto il limite di ' + job.limit + ' pagine: copertura parziale.' : ''));
            $('#marrison-engine-progress').prop('max', job.status === 'http' ? Math.max(1, job.http_done + job.http_failed + job.queued) : Math.max(1, job.browser_total)).val(job.status === 'http' ? job.http_done + job.http_failed : job.browser_done);
            $('#marrison-engine-current').text(job.next ? (job.next.phase === 'before' ? 'Prima del consenso simulato: ' : 'Dopo il consenso simulato: ') + job.next.url : (job.queued ? job.queued + ' URL in coda' : ''));
            $('#marrison-engine-details').text(JSON.stringify({ runtime: this.runtime, issues: job.issues, issues_total: job.issues_total }, null, 2));
        },
        fail: function (error) {
            this.running = false;
            this.generation++;
            this.removeFrame();
            this.status(error.message || String(error), true);
            this.render(this.job);
        },
        removeFrame: function () {
            clearTimeout(this.timer);
            if (this.frame) { this.frame.remove(); this.frame = null; }
        },
        start: async function (wizard) {
            if (this.running) { return; }
            this.running = true;
            this.render(this.job);
            this.status('Preparazione della scansione…');
            try {
                var data = await this.request('start', { limit: $('#marrison-scan-limit').val() || 500 });
                // A fresh top-level document resets the browser's ephemeral cookie partition.
                sessionStorage.setItem('marrisonCookieScanRun', data.job.id);
                if (wizard) { sessionStorage.setItem('marrisonCookieScanWizard', '1'); }
                window.location.reload();
            } catch (error) { this.fail(error); }
        },
        resume: async function () {
            if (this.running) { return; }
            try {
                var data = await this.request('state');
                this.running = true;
                this.generation++;
                this.render(data.job);
                this.advance();
            } catch (error) { this.fail(error); }
        },
        advance: async function () {
            if (!this.running || !this.job) { return; }
            var generation = this.generation;
            try {
                if (this.job.status === 'http') {
                    this.status('Scansione delle pagine del sito…');
                    var data = await this.request('step', { job: this.job.id });
                    if (generation !== this.generation) { return; }
                    this.render(data.job);
                    this.timer = setTimeout(function () { engine.advance(); }, 150);
                } else if (this.job.status === 'browser') {
                    if (!('credentialless' in HTMLIFrameElement.prototype)) { throw new Error('La parte HTTP è salvata. Per la scansione JavaScript isolata apri questo pannello in Chrome o Edge aggiornato e premi Riprendi.'); }
                    this.measure(this.job.next);
                } else {
                    this.running = false;
                    sessionStorage.removeItem('marrisonCookieScanRun');
                    this.removeFrame();
                    this.render(this.job);
                    this.status(this.job.status === 'completed' ? 'Scansione completata: ' + this.job.cookie_count + ' cookie distinti.' : 'Scansione ' + this.job.status + ': consulta il rapporto di copertura.', this.job.status !== 'completed');
                    await this.loadInventory('all');
                }
            } catch (error) {
                if (error.code === 'engine_busy' && this.running) { this.timer = setTimeout(function () { engine.advance(); }, 1200); }
                else { this.fail(error); }
            }
        },
        measure: function (next) {
            if (!next) { this.fail(new Error('La pagina da analizzare non è disponibile.')); return; }
            this.removeFrame();
            var host = document.getElementById('marrison-browser-preview');
            if (!host) {
                host = document.createElement('div'); host.id = 'marrison-browser-preview';
                var note = document.createElement('p'); note.textContent = 'Analisi in una sessione isolata. Il consenso viene simulato senza cambiare le tue preferenze.'; host.appendChild(note);
                var target = document.getElementById('marrison-scan-engine') || document.getElementById('wizard_scan_status');
                if (!target) { this.fail(new Error('Pannello di scansione non disponibile.')); return; }
                target.appendChild(host);
            }
            var frame = document.createElement('iframe');
            frame.credentialless = true;
            frame.setAttribute('sandbox', 'allow-scripts allow-same-origin');
            frame.setAttribute('referrerpolicy', 'no-referrer');
            frame.title = 'Analisi cookie: ' + next.url;
            frame.style.cssText = 'width:100%;height:500px;border:1px solid #dcdcde;pointer-events:none;background:#fff';
            this.frame = frame;
            this.status((next.phase === 'before' ? 'Prima' : 'Dopo') + ' del consenso simulato: ' + next.url);
            this.timer = setTimeout(function () { engine.browserResult({ error: 'La pagina non ha risposto al motore browser (iframe bloccato, script non caricato o timeout).' }); }, 14000);
            frame.src = next.frame_url;
            host.appendChild(frame);
        },
        browserResult: async function (result) {
            var next = this.job && this.job.next;
            if (!this.running || !next || !this.frame) { return; }
            var generation = this.generation;
            this.removeFrame();
            try {
                var data = await this.request('browser', { job: this.job.id, result: JSON.stringify($.extend({}, result, { url: next.url, phase: next.phase, token: next.token })) });
                if (generation !== this.generation) { return; }
                this.render(data.job);
                this.timer = setTimeout(function () { engine.advance(); }, 150);
            } catch (error) { this.fail(error); }
        },
        loadInventory: async function (category) {
            try {
                var data = await this.request('inventory', { category: category || 'all' });
                if (window.MarrisonCookieAdmin) { window.MarrisonCookieAdmin.renderCookieTable(data.cookies); }
                if (window.MarrisonWizard) { window.MarrisonWizard.renderCookieTable(data.cookies); $('#wizard_cookie_results').show(); }
                if (!this.running) { this.render(data.job); }
            } catch (error) { this.fail(error); }
        },
        init: async function () {
            $(document).on('click', '#marrison-scan-resume', function () { engine.resume(); });
            $(document).on('click', '#marrison-scan-cancel', async function () {
                if (!engine.job) { return; }
                engine.running = false; engine.generation++; engine.removeFrame();
                try { var data = await engine.request('cancel', { job: engine.job.id }); sessionStorage.removeItem('marrisonCookieScanRun'); engine.render(data.job); engine.status('Scansione interrotta.'); }
                catch (error) { engine.fail(error); }
            });
            $(document).on('click', '#marrison-scan-export', async function () {
                try {
                    var data = await engine.request('export');
                    var url = URL.createObjectURL(new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' }));
                    var link = document.createElement('a'); link.href = url; link.download = 'marrison-cookie-scan.json'; link.click(); setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
                } catch (error) { engine.fail(error); }
            });
            window.addEventListener('message', function (event) {
                var data = event.data;
                if (!engine.frame || event.source !== engine.frame.contentWindow || event.origin !== config.origin || !data || data.type !== 'marrison-cookie-scan' || !engine.job || data.job !== engine.job.id || data.token !== engine.job.next.token) { return; }
                engine.browserResult(data);
            });
            if (!$('#marrison-cookie-table-body').length && !sessionStorage.getItem('marrisonCookieScanRun')) { return; }
            try {
                var data = await this.request('state'); this.render(data.job);
                if (data.job && sessionStorage.getItem('marrisonCookieScanRun') === data.job.id && ['http', 'browser'].indexOf(data.job.status) >= 0) { this.resume(); }
            } catch (error) { this.fail(error); }
        }
    };
    window.MarrisonCookieScanner = engine;
    $(function () { engine.init(); });
}(jQuery));
