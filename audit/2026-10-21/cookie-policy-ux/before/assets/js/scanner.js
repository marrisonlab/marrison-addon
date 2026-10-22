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
                        if (!runtime || Number(runtime.protocol) !== Number(config.protocol) || runtime.revision !== config.revision || !runtime.compatible) {
                            $('#marrison-engine-details').text(JSON.stringify({ expected: { protocol: config.protocol, revision: config.revision }, runtime: runtime || null }, null, 2));
                            reject(new Error('Il motore PHP attivo non corrisponde ai file dello scanner. Verifica aggiornamento, copie del plugin e cache PHP.')); return;
                        }
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
            if (job && job.rescan_required === false) { $('[data-marrison-cookie-rescan-notice]').remove(); }
            $('#marrison-scan-cancel').prop('hidden', !job || ['http', 'browser'].indexOf(job.status) < 0);
            $('#marrison-scan-resume').prop('hidden', !job || this.running || ['http', 'browser'].indexOf(job.status) < 0);
            $('#marrison-scan-cookies, #wizard_scan_button').prop('disabled', this.running);
            if (!job) { $('#marrison-engine-summary').text('Nessuna scansione del nuovo motore.'); return; }
            var names = { http: 'Scansione delle pagine', browser: 'Analisi nel browser', completed: 'Scansione completata', partial: 'Scansione parziale', cancelled: 'Scansione interrotta', failed: 'Scansione non riuscita' };
            $('#marrison-engine-summary').text((names[job.status] || 'Stato non disponibile') + ' · ' + job.http_done + ' pagine campione · ' + job.browser_done + '/' + job.browser_total + ' verifiche · ' + job.cookie_count + ' cookie osservati' + (job.documented_cookie_count ? ' · ' + job.documented_cookie_count + (job.documented_cookie_count === 1 ? ' documentato' : ' documentati') : ''));
            $('#marrison-engine-coverage').text(job.coverage + (job.limit_reached ? ' Raggiunto il limite di ' + job.limit + (job.sampling ? ' campioni: copertura parziale.' : ' pagine: copertura parziale.') : ''));
            $('#marrison-engine-progress').prop('max', job.status === 'http' ? Math.max(1, job.http_done + job.http_failed + job.queued) : Math.max(1, job.browser_total)).val(job.status === 'http' ? job.http_done + job.http_failed : job.browser_done);
            $('#marrison-engine-current').text(job.next ? 'Verifica in background: ' + job.next.url : (job.queued ? job.queued + ' indirizzi da verificare' : ''));
            var services = job.services || [];
            var serviceNames = [];
            services.forEach(function (service) {
                var labels = Object.keys(service.profiles || {}).map(function (key) { return service.profiles[key].name; });
                (labels.length ? labels : [service.host]).forEach(function (label) { if (serviceNames.indexOf(label) < 0) { serviceNames.push(label); } });
            });
            $('#marrison-engine-services').text(services.length ? 'Servizi esterni rilevati: ' + serviceNames.join(', ') + '. La presenza di un servizio non dimostra che abbia installato cookie.' : '');
            var $serviceRows = $('#marrison-service-cookie-rows').empty(), profiles = {};
            services.forEach(function (service) {
                Object.keys(service.profiles || {}).forEach(function (key) {
                    if (profiles[key]) { return; } profiles[key] = true;
                    var profile = service.profiles[key];
                    (profile.cookies || []).forEach(function (cookie) {
                        var $row = $('<tr>');
                        [profile.name, cookie.name, cookie.domain, cookie.duration, cookie.purpose].forEach(function (text) { $('<td>').text(text).appendTo($row); });
                        var $source = $('<td>').text('Documentato dal fornitore · ');
                        $('<a>').attr({href: profile.source_url, target: '_blank', rel: 'noopener noreferrer'}).text('Fonte').appendTo($source);
                        $source.appendTo($row); $row.appendTo($serviceRows);
                    });
                });
            });
            $('#marrison-service-cookies').prop('hidden', !$serviceRows.children().length);
            var $storageRows = $('#marrison-browser-storage-rows, #wizard_browser_storage_rows').empty();
            (job.storage || []).forEach(function (entry) {
                var $row = $('<tr>');
                [entry.key, entry.type, entry.origin, 'Chiave osservata · valore escluso'].forEach(function (text) { $('<td>').text(text).appendTo($row); });
                $row.appendTo($storageRows);
            });
            $('#marrison-browser-storage-note, #wizard_browser_storage_note').text(job.background && !job.storage_supported && job.remote_requests ? 'Il motore su Commander non verifica ancora localStorage e sessionStorage: aggiorna la patch dello scanner.' :
                'LocalStorage e sessionStorage sono dati del browser distinti dai cookie. Sono elencate solo le chiavi realmente osservate, senza valori. Voci rilevate: ' + (job.storage_count || 0) + '.');
            var sampling = job.sampling;
            $('#marrison-engine-sampling').text(sampling ? sampling.selected + ' campioni selezionati da ' + sampling.candidates + ' URL · ' + sampling.equivalents + ' URL equivalenti non ripetuti' + (sampling.unselected ? ' · ' + sampling.unselected + ' gruppi oltre il limite' : '') : '');
            var $samples = $('#marrison-engine-samples').empty();
            (sampling && sampling.groups || []).forEach(function (group) {
                var $item = $('<li>').text(group.label + (group.template ? ' · ' + group.template : '') + ' · ' + group.represented_urls + ' URL nel gruppo · ');
                $('<a>').attr({ href: group.url, target: '_blank', rel: 'noopener noreferrer' }).text(group.url).appendTo($item);
                if (group.uncertain) { $item.append(document.createTextNode(' · ' + (group.uncertainty || 'percorso non risolto da WordPress'))); }
                $item.appendTo($samples);
            });
            $('#marrison-engine-details').text(JSON.stringify({ runtime: this.runtime, method: job.mode, remote_requests: job.remote_requests, remote_error: job.remote_error, response_pages: job.response_pages, issues: job.issues, issues_total: job.issues_total, services: services, sampling: sampling }, null, 2));
            if (job.remote_error && !this.running) { this.status(job.remote_error, true); }
        },
        resultText: function (job) {
            if (job.status === 'completed') {
                return 'Scansione completata: ' + job.cookie_count + ' cookie osservati.' + (job.documented_cookie_count ? ' Cookie documentati dei servizi rilevati: ' + job.documented_cookie_count + '.' : '');
            }
            if (job.status === 'partial') {
                var issue = job.issues && job.issues[0];
                return 'Scansione incompleta: ' + job.cookie_count + ' cookie rilevati; i risultati disponibili sono salvati. ' + (issue ? issue.message + (issue.url ? ' — ' + issue.url : '') : 'Non è stato possibile verificare tutte le pagine previste.');
            }
            return job.status === 'cancelled' ? 'Scansione interrotta.' : 'Scansione non riuscita: consulta i problemi rilevati.';
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
                var data = await this.request('start', { mode: $('#marrison-scan-mode').val() || 'commander', limit: $('#marrison-scan-limit').val() || 50, additional_urls: $('#marrison-scan-additional').val() || '' });
                if (data.job.background) {
                    this.render(data.job); this.watchBackground();
                    $(document).trigger('marrison-cookie-scan-started', [data.job, !!wizard]);
                    return;
                }
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
                if (data.job && data.job.background) {
                    data = await this.request('resume', { job: data.job.id });
                    this.render(data.job); this.watchBackground(); return;
                }
                this.running = true;
                this.generation++;
                this.render(data.job);
                this.advance();
            } catch (error) { this.fail(error); }
        },
        watchBackground: function () {
            this.removeFrame();
            this.running = true; this.generation++;
            this.render(this.job);
            this.status('Scansione in background avviata: puoi proseguire nel wizard, tornare alla bacheca o chiudere la pagina. I risultati vengono salvati sul server.');
            this.timer = setTimeout(function () { engine.pollBackground(); }, 2000);
        },
        pollBackground: async function () {
            if (!this.running || !this.job || !this.job.background) { return; }
            var generation = this.generation;
            try {
                var data = await this.request('state');
                if (generation !== this.generation) { return; }
                this.render(data.job);
                if (data.job && ['http', 'browser'].indexOf(data.job.status) >= 0 && (data.job.worker || {}).state !== 'paused') {
                    this.status((data.job.worker || {}).error || 'Scansione in background: puoi proseguire nel wizard, tornare alla bacheca o chiudere la pagina.');
                    this.timer = setTimeout(function () { engine.pollBackground(); }, 3000);
                } else {
                    this.running = false; this.render(data.job);
                    this.status(data.job && (data.job.worker || {}).state === 'paused' ? (data.job.worker.error || 'Scansione in pausa. Premi Riprendi.') : this.resultText(data.job), data.job && data.job.status !== 'completed');
                    await this.loadInventory('all');
                }
            } catch (error) {
                if (error.code === 'engine_busy') { this.timer = setTimeout(function () { engine.pollBackground(); }, 1500); }
                else { this.fail(error); }
            }
        },
        advance: async function () {
            if (!this.running || !this.job) { return; }
            if (this.job.background) { this.watchBackground(); return; }
            var generation = this.generation;
            try {
                if (this.job.status === 'http') {
                    this.status('Scansione delle pagine del sito…');
                    var data = await this.request('step', { job: this.job.id });
                    if (generation !== this.generation) { return; }
                    this.render(data.job);
                    this.timer = setTimeout(function () { engine.advance(); }, 150);
                } else if (this.job.status === 'browser') {
                    if (this.job.mode === 'commander') {
                        this.status('Browser remoto: verifica prima e dopo il consenso…');
                        var remote = await this.request('remote', { job: this.job.id });
                        if (generation !== this.generation) { return; }
                        this.render(remote.job);
                        this.timer = setTimeout(function () { engine.advance(); }, 300);
                        return;
                    }
                    if (!('credentialless' in HTMLIFrameElement.prototype)) { throw new Error('La parte HTTP è salvata. Per la scansione JavaScript isolata apri questo pannello in Chrome o Edge aggiornato e premi Riprendi.'); }
                    this.measure(this.job.next);
                } else {
                    this.running = false;
                    sessionStorage.removeItem('marrisonCookieScanRun');
                    this.removeFrame();
                    this.render(this.job);
                    this.status(this.resultText(this.job), this.job.status !== 'completed');
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
                host.setAttribute('aria-hidden', 'true');
                // Keep layout/intersection checks working for lazy embeds without displaying previews.
                host.style.cssText = 'position:fixed;left:0;top:0;width:1280px;max-width:100vw;height:800px;max-height:100vh;overflow:hidden;pointer-events:none;opacity:0;z-index:-2147483647';
                var target = document.getElementById('marrison-scan-engine') || document.getElementById('wizard_scan_status');
                if (!target) { this.fail(new Error('Pannello di scansione non disponibile.')); return; }
                target.appendChild(host);
            }
            var frame = document.createElement('iframe');
            frame.credentialless = true;
            frame.setAttribute('sandbox', 'allow-scripts allow-same-origin');
            frame.setAttribute('referrerpolicy', 'no-referrer');
            frame.title = 'Analisi cookie: ' + next.url;
            frame.tabIndex = -1;
            frame.setAttribute('aria-hidden', 'true');
            frame.style.cssText = 'width:100%;height:100%;border:0;pointer-events:none';
            this.frame = frame;
            this.status('Analisi in background delle pagine campione…');
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
            if (!$('#marrison-cookie-table-body, #wizard_cookie_table_body').length && !sessionStorage.getItem('marrisonCookieScanRun')) { return; }
            try {
                var data = await this.request('state'); this.render(data.job);
                if (data.job && data.job.stale) { this.status('La scansione precedente usa un altro metodo. Avvia una nuova scansione a campioni.'); return; }
                if (data.job && data.job.background && ['http', 'browser'].indexOf(data.job.status) >= 0) {
                    if ((data.job.worker || {}).state === 'paused') { this.status(data.job.worker.error || 'Scansione in pausa. Premi Riprendi.', true); }
                    else { this.watchBackground(); }
                    return;
                }
                if (data.job && sessionStorage.getItem('marrisonCookieScanRun') === data.job.id && ['http', 'browser'].indexOf(data.job.status) >= 0) { this.resume(); }
            } catch (error) { this.fail(error); }
        }
    };
    window.MarrisonCookieScanner = engine;
    $(function () { engine.init(); });
}(jQuery));
