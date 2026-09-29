jQuery(function($) {
    var $buttons = $('#marrison-lgf-scan, #marrison-lgf-update, #marrison-lgf-scan-update');
    var $status = $('#marrison-lgf-status');

    function setBusy(isBusy) {
        $buttons.prop('disabled', isBusy);
    }

    function renderStatus(success, message, logs) {
        var className = success ? 'success' : 'error';
        var html = '<div class="marrison-lgf-result ' + className + '"><strong>' + escapeHtml(message || '') + '</strong>';

        if (logs && logs.length) {
            html += '<ul>';
            logs.forEach(function(log) {
                html += '<li>' + escapeHtml(log) + '</li>';
            });
            html += '</ul>';
        }

        html += '</div>';
        $status.html(html).show();
    }

    function runAction(action, confirmMessage) {
        if (confirmMessage && !window.confirm(confirmMessage)) {
            return;
        }

        setBusy(true);
        renderStatus(true, marrisonLocalGoogleFonts.working, []);

        $.ajax({
            url: marrisonLocalGoogleFonts.ajaxUrl,
            method: 'POST',
            data: {
                action: action,
                nonce: marrisonLocalGoogleFonts.nonce
            }
        }).done(function(response) {
            var data = response && response.data ? response.data : {};
            var message = data.message || (response && response.success ? 'Operazione completata.' : 'Operazione non riuscita.');
            var logs = data.logs || [];

            renderStatus(!!(response && response.success), message, logs);

            if (response && response.success) {
                window.setTimeout(function() {
                    window.location.reload();
                }, 1200);
            }
        }).fail(function() {
            renderStatus(false, marrisonLocalGoogleFonts.connectionError, []);
        }).always(function() {
            setBusy(false);
        });
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    $('#marrison-lgf-scan').on('click', function(e) {
        e.preventDefault();
        runAction(marrisonLocalGoogleFonts.scanAction, marrisonLocalGoogleFonts.scanConfirm);
    });

    $('#marrison-lgf-update').on('click', function(e) {
        e.preventDefault();
        runAction(marrisonLocalGoogleFonts.updateAction, marrisonLocalGoogleFonts.updateConfirm);
    });

    $('#marrison-lgf-scan-update').on('click', function(e) {
        e.preventDefault();
        runAction(marrisonLocalGoogleFonts.scanUpdateAction, marrisonLocalGoogleFonts.scanUpdateConfirm);
    });
});
