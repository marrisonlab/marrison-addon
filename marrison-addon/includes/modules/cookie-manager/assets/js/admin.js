/**
 * Admin JavaScript per Marrison Cookie Manager
 */
(function($) {
    'use strict';

    var MarrisonCookieAdmin = {
        init: function() {
            this.bindEvents();
            if ($('#marrison-cookie-table-body').length) {
                this.loadCookies();
            }
        },

        bindEvents: function() {
            $(document).on('click', '#marrison-receipts-load', function() { MarrisonCookieAdmin.receiptsOffset = 0; MarrisonCookieAdmin.loadReceipts(false); });
            $(document).on('click', '#marrison-receipts-next', function() { MarrisonCookieAdmin.loadReceipts(false); });
            $(document).on('click', '#marrison-receipts-export', function() { MarrisonCookieAdmin.receiptsOffset = 0; MarrisonCookieAdmin.loadReceipts(true); });
            $(document).on('click', '.marrison-copy-shortcode', function() {
                MarrisonCookieAdmin.copyShortcode(this);
            });
            $(document).on('click', '#marrison-open-wizard', function(e) {
                e.preventDefault();
                MarrisonCookieAdmin.openWizard();
            });

            $(document).on('click', '#marrison-scan-cookies', function(e) {
                e.preventDefault();
                MarrisonCookieAdmin.scanCookies();
            });

            $(document).on('click', '#marrison-repair-cookie-table', function(e) {
                e.preventDefault();
                MarrisonCookieAdmin.repairCookieTable();
            });

            $(document).on('change', '#marrison-category-filter', function(e) {
                e.preventDefault();
                MarrisonCookieAdmin.loadCookies($(this).val());
            });

            $(document).on('click', '.marrison-btn-delete', function(e) {
                e.preventDefault();
                var cookieId = $(this).data('cookie-id');

                if (confirm(marrisonCookieAdmin.confirmDeleteText || 'Are you sure you want to delete this cookie?')) {
                    MarrisonCookieAdmin.deleteCookie(cookieId);
                }
            });

            $(document).on('change', '.marrison-category-select', function(e) {
                e.preventDefault();
                MarrisonCookieAdmin.updateCookieCategory($(this).data('cookie-id'), $(this).val());
            });
        },

        loadReceipts: async function(exportAll) {
            var rows = [], snapshots = {}, snapshotIntegrity = {}, offset = this.receiptsOffset || 0;
            var date = $('#marrison-receipt-date').val();
            var $buttons = $('#marrison-receipts-load, #marrison-receipts-next, #marrison-receipts-export, #marrison-receipt-date');
            $buttons.prop('disabled', true);
            $('#marrison-receipts-status').text('Lettura ricevute…');
            try {
                do {
                    var response = await $.ajax({ url: marrisonCookieAdmin.ajaxUrl, type: 'POST', data: { action: 'marrison_cookie_consent_receipts', nonce: marrisonCookieAdmin.nonce, date: date, offset: offset } });
                    if (!response.success) { throw new Error(response.data && response.data.message || 'Lettura delle ricevute non riuscita.'); }
                    rows = rows.concat(response.data.receipts);
                    Object.assign(snapshots, response.data.snapshots);
                    Object.assign(snapshotIntegrity, response.data.snapshot_integrity);
                    offset = response.data.next_offset;
                    $('#marrison-receipts-status').text(rows.length + ' ricevute lette.');
                } while (exportAll && offset !== null);
                this.receiptsOffset = offset;
                $('#marrison-receipts-next').prop('hidden', offset === null);
                this.renderReceipts(rows, snapshots, snapshotIntegrity);
                if (exportAll) {
                    var url = URL.createObjectURL(new Blob([JSON.stringify({ date: date, receipts: rows, snapshots: snapshots, snapshot_integrity: snapshotIntegrity }, null, 2)], { type: 'application/json' }));
                    var link = document.createElement('a'); link.href = url; link.download = 'cookie-consent-' + date + '.json'; link.click();
                    setTimeout(function() { URL.revokeObjectURL(url); }, 1000);
                }
            } catch (error) { $('#marrison-receipts-status').text(error.message || 'Errore di connessione.'); }
            finally { $buttons.prop('disabled', false); }
        },

        renderReceipts: function(rows, snapshots, snapshotIntegrity) {
            var $view = $('#marrison-receipts-result').empty();
            if (!rows.length) { $('<p>').text('Nessuna ricevuta registrata in questa giornata.').appendTo($view); return; }
            var types = { accept_all: 'Tutti accettati', reject_all: 'Solo necessari', custom: 'Scelta personalizzata' };
            var categories = { necessary: 'Necessari', functional: 'Funzionali', analytics: 'Analitici', marketing: 'Marketing' };
            var events = { choice: 'Scelta del visitatore', withdrawal: 'Consenso ridotto o revocato', legacy_refusal: 'Rifiuto precedente conservato' };
            var $table = $('<table>').addClass('widefat striped');
            var $head = $('<tr>');
            ['Data e ora', 'Scelta e categorie', 'Validità', 'Verifica'].forEach(function(label) { $('<th>').attr('scope', 'col').text(label).appendTo($head); });
            $('<thead>').append($head).appendTo($table);
            var $body = $('<tbody>').appendTo($table);
            rows.forEach(function(row) {
                var $row = $('<tr>').appendTo($body);
                $('<td>').text(MarrisonCookieAdmin.formatDate(row.created_at)).appendTo($row);
                var $choice = $('<td>').appendTo($row);
                $('<strong>').text(types[row.type] || 'Scelta non riconosciuta').appendTo($choice);
                $('<p>').text((row.categories || []).map(function(key) { return categories[key] || key; }).join(', ')).appendTo($choice);
                var $details = $('<details>').appendTo($choice);
                $('<summary>').text('Dettagli della ricevuta').appendTo($details);
                var snapshot = snapshots[row.policy_revision];
                var facts = [events[row.event] || row.event, 'Ricevuta: ' + row.receipt_id, 'Visitatore (codice casuale): ' + row.visitor_id,
                    'Revisione informativa: ' + (snapshot && snapshot.revision_label || 'non disponibile')];
                if (snapshot && snapshot.privacy_profile && snapshot.privacy_profile.controller_name) { facts.push('Titolare: ' + snapshot.privacy_profile.controller_name); }
                facts.forEach(function(text) { $('<p>').text(text).appendTo($details); });
                $('<td>').text('Fino al ' + MarrisonCookieAdmin.formatDate(row.expires_at)).appendTo($row);
                var intact = row.integrity_valid === true && snapshotIntegrity[row.policy_revision] === true;
                $('<td>').text(intact ? 'Ricevuta e informativa integre' : 'Integrità non confermata: verifica necessaria').addClass(intact ? 'marrison-receipt-valid' : 'marrison-receipt-invalid').appendTo($row);
            });
            $table.appendTo($view);
        },

        copyShortcode: function(button) {
            var $button = $(button);
            var $item = $button.closest('.marrison-shortcode-item');
            var input = $item.find('.marrison-shortcode-value')[0];
            var $status = $item.find('.marrison-shortcode-copy-status');
            if (!input) { return; }
            $button.prop('disabled', true);
            $status.text('');
            function done(success) {
                $button.prop('disabled', false);
                $status.text($button.attr(success ? 'data-copied-text' : 'data-copy-error-text'));
            }
            function fallback() {
                input.focus();
                input.select();
                input.setSelectionRange(0, input.value.length);
                var copied = false;
                try { copied = document.execCommand('copy'); } catch (error) { /* Keep the value selected for manual copy. */ }
                done(copied);
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(input.value).then(function() { done(true); }, fallback);
            } else { fallback(); }
        },

        openWizard: function() {
            if ($('.marrison-wizard-overlay').length === 0) {
                alert('Il wizard non e disponibile in questa pagina.');
                return;
            }

            $.ajax({
                url: marrisonCookieAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'marrison_wizard_open',
                    nonce: marrisonCookieAdmin.nonce
                }
            });

            $('.marrison-wizard-overlay').addClass('active');
            $('.marrison-wizard-overlay').css({ display: '', opacity: '', visibility: '' });
            $('body').css('overflow', 'hidden');

            if (window.MarrisonWizard) {
                window.MarrisonWizard.currentStep = 1;
                window.MarrisonWizard.showStep(1);
                window.MarrisonWizard.updateProgress();
            }
        },

        scanCookies: function() {
            if (window.MarrisonCookieScanner) {
                window.MarrisonCookieScanner.start();
                return;
            }
            var $button = $('#marrison-scan-cookies');
            var $status = $('#marrison-scan-status');
            var $dbStatus = $('#marrison-db-status');

            if ($button.length === 0) {
                return;
            }

            $button.prop('disabled', true);
            $status
                .removeClass('marrison-scan-status-success marrison-scan-status-error')
                .addClass('marrison-scan-status-loading')
                .text(marrisonCookieAdmin.scanningText || 'Scanning...')
                .show();

            $.ajax({
                url: marrisonCookieAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'marrison_scan_cookies',
                    nonce: marrisonCookieAdmin.nonce
                },
                success: function(response) {
                    var data = response.data || {};

                    if (response.success) {
                        var scanText = marrisonCookieAdmin.scanSuccessText || 'Scan completed!';
                        if (data.count || data.count === 0) {
                            scanText += ' (' + data.count + ' cookie)';
                        }

                        $status
                            .removeClass('marrison-scan-status-loading')
                            .addClass('marrison-scan-status-success')
                            .text(scanText);

                        if (data.storage_label && $dbStatus.length) {
                            $dbStatus
                                .removeClass('marrison-scan-status-loading marrison-scan-status-error')
                                .addClass('marrison-scan-status-success')
                                .text('Storage scansione: ' + data.storage_label + (data.storage_detail ? ' (' + data.storage_detail + ')' : ''))
                                .show();
                        }

                        MarrisonCookieAdmin.loadCookies();
                    } else {
                        $status
                            .removeClass('marrison-scan-status-loading')
                            .addClass('marrison-scan-status-error')
                            .text((marrisonCookieAdmin.errorText || 'Error: ') + (data.message || 'Unknown'));
                    }

                    $button.prop('disabled', false);

                    setTimeout(function() {
                        $status.fadeOut();
                    }, 3000);
                },
                error: function() {
                    $status
                        .removeClass('marrison-scan-status-loading')
                        .addClass('marrison-scan-status-error')
                        .text(marrisonCookieAdmin.connectionErrorText || 'Connection error');

                    $button.prop('disabled', false);
                }
            });
        },

        repairCookieTable: function() {
            var $button = $('#marrison-repair-cookie-table');
            var $status = $('#marrison-db-status');

            if ($button.length === 0) {
                return;
            }

            $button.prop('disabled', true);
            $status
                .removeClass('marrison-scan-status-success marrison-scan-status-error')
                .addClass('marrison-scan-status-loading')
                .text(marrisonCookieAdmin.repairingTableText || 'Verifica tabella in corso...')
                .show();

            $.ajax({
                url: marrisonCookieAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'marrison_repair_cookie_table',
                    nonce: marrisonCookieAdmin.nonce
                },
                success: function(response) {
                    var data = response.data || {};
                    var detail = data.table ? ' (' + data.database + '.' + data.table + ', ' + data.method + ')' : '';

                    if (response.success) {
                        $status
                            .removeClass('marrison-scan-status-loading')
                            .addClass('marrison-scan-status-success')
                            .text((data.message || marrisonCookieAdmin.tableReadyText || 'Tabella cookie pronta.') + detail);
                    } else {
                        $status
                            .removeClass('marrison-scan-status-loading')
                            .addClass('marrison-scan-status-error')
                            .text((marrisonCookieAdmin.errorText || 'Error: ') + (data.message || 'Unknown') + detail);
                    }

                    $button.prop('disabled', false);
                },
                error: function() {
                    $status
                        .removeClass('marrison-scan-status-loading')
                        .addClass('marrison-scan-status-error')
                        .text(marrisonCookieAdmin.connectionErrorText || 'Connection error');

                    $button.prop('disabled', false);
                }
            });
        },

        loadCookies: function(category) {
            if (window.MarrisonCookieScanner) {
                window.MarrisonCookieScanner.loadInventory(category || 'all');
                return;
            }
            category = category || 'all';

            $.ajax({
                url: marrisonCookieAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'marrison_get_scanned_cookies',
                    nonce: marrisonCookieAdmin.nonce,
                    category: category
                },
                success: function(response) {
                    if (response.success) {
                        MarrisonCookieAdmin.renderCookieTable(response.data.cookies);
                    }
                }
            });
        },

        renderCookieTable: function(cookies) {
            var $tbody = $('#marrison-cookie-table-body');
            $tbody.empty();

            if (!cookies || cookies.length === 0) {
                $tbody.append('<tr><td colspan="7">' + (marrisonCookieAdmin.noCookiesFoundText || 'No cookies found') + '</td></tr>');
                return;
            }

            var categoryOptions =
                '<option value="necessary">' + (marrisonCookieAdmin.categoryNecessary || 'Necessary') + '</option>' +
                '<option value="functional">' + (marrisonCookieAdmin.categoryFunctional || 'Functional') + '</option>' +
                '<option value="analytics">' + (marrisonCookieAdmin.categoryAnalytics || 'Analytics') + '</option>' +
                '<option value="marketing">Marketing</option>';

            $.each(cookies, function(index, cookie) {
                var selectedOptions = categoryOptions.replace(
                    'value="' + cookie.cookie_category + '"',
                    'value="' + cookie.cookie_category + '" selected'
                );

                var row =
                    '<tr>' +
                        '<td><strong>' + MarrisonCookieAdmin.escapeHtml(cookie.cookie_name) + '</strong></td>' +
                        '<td><span class="marrison-category-badge marrison-category-' + MarrisonCookieAdmin.escapeHtml(cookie.cookie_category) + '">' +
                            MarrisonCookieAdmin.getCategoryLabel(cookie.cookie_category) +
                        '</span></td>' +
                        '<td>' + MarrisonCookieAdmin.escapeHtml(cookie.cookie_domain) + '<br><small>Path: ' + MarrisonCookieAdmin.escapeHtml(cookie.cookie_path || '/') + '</small></td>' +
                        '<td>' + MarrisonCookieAdmin.escapeHtml(cookie.cookie_expiration_status === 'session' ? 'Sessione' : (cookie.cookie_expiration ? MarrisonCookieAdmin.formatDate(cookie.cookie_expiration.replace(' ', 'T') + 'Z') : 'Non disponibile')) + '</td>' +
                        '<td>' + MarrisonCookieAdmin.renderEvidence(cookie) + '</td>' +
                        '<td>' + MarrisonCookieAdmin.formatDate(cookie.scan_date) + (cookie.observation_stale ? '<br><small>Osservazione da aggiornare (oltre 30 giorni)</small>' : '') + '</td>' +
                        '<td><div class="marrison-cookie-actions">' +
                            '<select class="marrison-category-select" data-cookie-id="' + parseInt(cookie.id, 10) + '">' + selectedOptions + '</select>' +
                            '<button class="marrison-btn-delete" data-cookie-id="' + parseInt(cookie.id, 10) + '">Elimina</button>' +
                        '</div></td>' +
                    '</tr>';

                $tbody.append(row);
            });
        },

        renderEvidence: function(cookie) {
            var html = cookie.source === 'catalogo' ? 'Catalogo · dichiarato, non osservato' : MarrisonCookieAdmin.escapeHtml(cookie.source);
            if (!cookie.evidence || !cookie.evidence.observations) { return html; }
            html += '<details><summary>Prove rilevate</summary><ul>';
            cookie.evidence.observations.forEach(function(observation) {
                html += '<li>' + MarrisonCookieAdmin.escapeHtml((observation.phase === 'before' ? 'Prima del consenso' : 'Dopo il consenso') + ' · ' + observation.method + ' · ' + observation.url) + '</li>';
            });
            return html + '</ul></details>';
        },

        deleteCookie: function(cookieId) {
            $.ajax({
                url: marrisonCookieAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'marrison_delete_cookie',
                    nonce: marrisonCookieAdmin.nonce,
                    cookie_id: cookieId
                },
                success: function(response) {
                    if (response.success) {
                        MarrisonCookieAdmin.loadCookies($('#marrison-category-filter').val());
                    } else {
                        alert('Errore: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Errore di connessione');
                }
            });
        },

        updateCookieCategory: function(cookieId, category) {
            $.ajax({
                url: marrisonCookieAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'marrison_update_cookie_category',
                    nonce: marrisonCookieAdmin.nonce,
                    cookie_id: cookieId,
                    category: category
                },
                success: function(response) {
                    if (response.success) {
                        MarrisonCookieAdmin.loadCookies($('#marrison-category-filter').val());
                    } else {
                        alert('Errore: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Errore di connessione');
                }
            });
        },

        getCategoryLabel: function(category) {
            var labels = {
                necessary: marrisonCookieAdmin.categoryNecessary || 'Necessary',
                functional: marrisonCookieAdmin.categoryFunctional || 'Functional',
                analytics: marrisonCookieAdmin.categoryAnalytics || 'Analytics',
                marketing: 'Marketing'
            };

            return labels[category] || category;
        },

        formatDate: function(dateString) {
            var date = new Date(dateString);
            return date.toLocaleDateString('it-IT', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        },

        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }
    };

    window.MarrisonCookieAdmin = MarrisonCookieAdmin;
    $(document).ready(function() {
        MarrisonCookieAdmin.init();
    });
})(jQuery);
