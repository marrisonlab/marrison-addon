jQuery(document).ready(function($) {
    
    // Toggle Switch Handler
    $('.marrison-ajax-toggle').on('change', function() {
        var $input = $(this);
        var isChecked = $input.is(':checked');
        var optionName = $input.data('option');
        var key = $input.data('key');
        var isInverse = $input.data('inverse') === true;
        var shouldReload = $input.data('reload') === true;
        
        // Determine value to send
        var valueToSend;
        if (isInverse) {
            valueToSend = isChecked ? 0 : 1;
        } else {
            valueToSend = isChecked ? 1 : 0;
        }

        // Disable input during request
        $input.prop('disabled', true);
        
        // Add loading state
        $input.closest('.marrison-switch').css('opacity', '0.5');

        saveOption(optionName, key, valueToSend, function(success) {
            $input.prop('disabled', false);
            $input.closest('.marrison-switch').css('opacity', '1');
            if (!success) {
                $input.prop('checked', !isChecked);
            } else if (shouldReload) {
                // Reload page if required by the module (to show/hide menu items)
                window.location.reload();
            }
        });
    });

    // Text Input Handler (Debounced or on Blur)
    $('.marrison-ajax-input').on('change', function() {
        var $input = $(this);
        var value = $input.val();
        var optionName = $input.data('option');
        var key = $input.data('key');

        $input.prop('disabled', true);
        $input.css('opacity', '0.5');

        saveOption(optionName, key, value, function(success) {
            $input.prop('disabled', false);
            $input.css('opacity', '1');
        });
    });

    // Force Update Handler
    $('.marrison-force-update').on('click', function() {
        var $btn = $(this);
        var $status = $('.marrison-update-status');
        var originalText = $btn.text();
        
        $btn.addClass('marrison-btn-loading').prop('disabled', true);
        $status.removeClass('success error').text('');
        
        $.ajax({
            url: marrison_global.ajax_url,
            type: 'POST',
            data: {
                action: 'marrison_force_update_check',
                nonce: marrison_global.nonce
            },
            success: function(response) {
                $btn.removeClass('marrison-btn-loading').prop('disabled', false);
                
                if (response.success) {
                    var isFound = response.data.found;
                    $status.addClass(isFound ? 'success' : 'success').text(response.data.message);
                    
                    if (isFound) {
                        // Optionally redirect to plugins page after a delay
                        setTimeout(function() {
                            if(confirm('Aggiornamento trovato! Vuoi andare alla pagina dei plugin per installarlo?')) {
                                window.location.href = 'plugins.php';
                            }
                        }, 500);
                    }
                } else {
                    $status.addClass('error').text('Errore: ' + (response.data.message || 'Sconosciuto'));
                }
            },
            error: function() {
                $btn.removeClass('marrison-btn-loading').prop('disabled', false);
                $status.addClass('error').text(marrison_global.connection_error || 'Errore di connessione');
            }
        });
    });

    function saveOption(optionName, key, value, callback) {
        $.ajax({
            url: marrison_global.ajax_url,
            type: 'POST',
            data: {
                action: 'marrison_save_option',
                nonce: marrison_global.nonce,
                option_name: optionName,
                key: key,
                value: value
            },
            success: function(response) {
                if (!response.success) {
                    alert((marrison_global.error_saving || 'Errore durante il salvataggio') + ': ' + (response.data.message || 'Errore sconosciuto'));
                    if (callback) callback(false);
                } else {
                    if (callback) callback(true);
                }
            },
            error: function() {
                alert(marrison_global.connection_error || 'Errore di connessione');
                if (callback) callback(false);
            }
        });
    }
});
