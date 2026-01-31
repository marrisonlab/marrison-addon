jQuery(document).ready(function($) {
    
    // Toggle Switch Handler
    $('.marrison-ajax-toggle').on('change', function() {
        var $input = $(this);
        var isChecked = $input.is(':checked');
        var optionName = $input.data('option');
        var key = $input.data('key');
        var isInverse = $input.data('inverse') === true;
        
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
