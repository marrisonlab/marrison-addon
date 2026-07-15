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
            $(document).on('click', '#marrison-open-wizard', function(e) {
                e.preventDefault();
                MarrisonCookieAdmin.openWizard();
            });

            $(document).on('click', '#marrison-scan-cookies', function(e) {
                e.preventDefault();
                MarrisonCookieAdmin.scanCookies();
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
            var $button = $('#marrison-scan-cookies');
            var $status = $('#marrison-scan-status');

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
                    if (response.success) {
                        $status
                            .removeClass('marrison-scan-status-loading')
                            .addClass('marrison-scan-status-success')
                            .text(marrisonCookieAdmin.scanSuccessText || 'Scan completed!');

                        MarrisonCookieAdmin.loadCookies();
                    } else {
                        $status
                            .removeClass('marrison-scan-status-loading')
                            .addClass('marrison-scan-status-error')
                            .text((marrisonCookieAdmin.errorText || 'Error: ') + (response.data.message || 'Unknown'));
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

        loadCookies: function(category) {
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
                $tbody.append('<tr><td colspan="6">' + (marrisonCookieAdmin.noCookiesFoundText || 'No cookies found') + '</td></tr>');
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
                        '<td>' + MarrisonCookieAdmin.escapeHtml(cookie.cookie_domain) + '</td>' +
                        '<td>' + MarrisonCookieAdmin.escapeHtml(cookie.source) + '</td>' +
                        '<td>' + MarrisonCookieAdmin.formatDate(cookie.scan_date) + '</td>' +
                        '<td><div class="marrison-cookie-actions">' +
                            '<select class="marrison-category-select" data-cookie-id="' + parseInt(cookie.id, 10) + '">' + selectedOptions + '</select>' +
                            '<button class="marrison-btn-delete" data-cookie-id="' + parseInt(cookie.id, 10) + '">Elimina</button>' +
                        '</div></td>' +
                    '</tr>';

                $tbody.append(row);
            });
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

    $(document).ready(function() {
        MarrisonCookieAdmin.init();
    });
})(jQuery);
