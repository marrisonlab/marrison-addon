jQuery(document).ready(function($) {
    var $regenerateBtn = $('#marrison-regenerate-btn');
    var $progressBar = $('#marrison-progress-bar');
    var $progressFill = $('#marrison-progress-fill');
    var $progressText = $('#marrison-progress-text');
    var $logContainer = $('#marrison-log-container');
    var $logList = $('#marrison-log-list');
    var $stopBtn = $('#marrison-stop-btn');
    var $testAvifBtn = $('#marrison-test-avif-btn');
    var $avifTestResult = $('#marrison-avif-test-result');
    var $sizeSelect = $('#marrison-regenerate-size');
    var $cleanupControls = $('#marrison-cleanup-disabled-controls');
    var $cleanupCheckbox = $('#marrison-cleanup-disabled');

    var isProcessing = false;
    var totalImages = 0;
    var processedImages = 0;
    var imageIds = [];
    var cleanupDisabled = false;
    var selectedSize = '';
    var successfulImages = 0;
    var skippedImages = 0;
    var failedImages = 0;

    function updateSizeSelection() {
        var singleSize = !!$sizeSelect.val();
        $regenerateBtn.text(singleSize ? marrison_vars.regenerate_selected : marrison_vars.regenerate_all);
        $cleanupControls.toggle(!singleSize);
        $cleanupCheckbox.prop('disabled', singleSize);
    }

    function restoreControls() {
        $regenerateBtn.show().prop('disabled', false);
        $sizeSelect.prop('disabled', false);
        $stopBtn.hide();
        updateSizeSelection();
    }

    $sizeSelect.on('change', updateSizeSelection);
    updateSizeSelection();

    $regenerateBtn.on('click', function(e) {
        e.preventDefault();
        
        if (isProcessing) return;
        
        var message = $sizeSelect.val()
            ? marrison_vars.confirm_size_message.replace('%s', $sizeSelect.find('option:selected').text())
            : marrison_vars.confirm_message;
        if (!confirm(message)) {
            return;
        }

        startRegeneration();
    });

    $stopBtn.on('click', function(e) {
        e.preventDefault();
        isProcessing = false;
        $stopBtn.hide();
        // Keep controls locked until the current request returns, so a new run cannot overlap it.
        $logList.prepend('<li><span style="color: red;">' + marrison_vars.process_stopped + '</span></li>');
    });

    $testAvifBtn.on('click', function(e) {
        e.preventDefault();

        $testAvifBtn.prop('disabled', true);
        $avifTestResult.css('color', '').text(marrison_vars.avif_testing);

        $.ajax({
            url: marrison_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'marrison_test_avif_support',
                nonce: marrison_vars.nonce
            },
            success: function(response) {
                var details = response.data && response.data.details ? ' (' + response.data.details.join(' - ') + ')' : '';
                var message = response.data && response.data.message ? response.data.message : 'Risposta non valida.';

                $avifTestResult
                    .css('color', response.success ? '#008a20' : '#d63638')
                    .text(message + details);
            },
            error: function() {
                $avifTestResult
                    .css('color', '#d63638')
                    .text('Errore durante il test AVIF.');
            },
            complete: function() {
                $testAvifBtn.prop('disabled', false);
            }
        });
    });

    function startRegeneration() {
        isProcessing = true;
        selectedSize = $sizeSelect.val() || '';
        cleanupDisabled = !selectedSize && $cleanupCheckbox.is(':checked');
        $sizeSelect.prop('disabled', true);
        $cleanupCheckbox.prop('disabled', true);
        $regenerateBtn.prop('disabled', true).hide();
        $stopBtn.show();
        $progressBar.show();
        $logContainer.show();
        $logList.empty();
        $progressFill.css('width', '0%');
        $progressText.text('0%');
        processedImages = 0;
        successfulImages = 0;
        skippedImages = 0;
        failedImages = 0;

        // Step 1: Get all image IDs
        $.ajax({
            url: marrison_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'marrison_get_image_ids',
                nonce: marrison_vars.nonce
            },
            success: function(response) {
                if (!isProcessing) {
                    restoreControls();
                    return;
                }
                if (response.success) {
                    imageIds = response.data.ids;
                    totalImages = imageIds.length;
                    
                    if (totalImages === 0) {
                        finishRegeneration(marrison_vars.no_images);
                        return;
                    }

                    $logList.append('<li>' + marrison_vars.found_images.replace('%d', totalImages) + '</li>');
                    processNextImage();
                } else {
                    finishRegeneration('Error: ' + response.data.message);
                }
            },
            error: function() {
                if (!isProcessing) {
                    restoreControls();
                    return;
                }
                finishRegeneration('Server Error getting image IDs');
            }
        });
    }

    function processNextImage() {
        if (!isProcessing) {
            finishRegeneration(null, false);
            return;
        }

        if (imageIds.length === 0) {
            var summary = failedImages ? marrison_vars.done_with_errors : marrison_vars.done_summary;
            finishRegeneration(summary.replace('%1$d', successfulImages).replace('%2$d', skippedImages).replace('%3$d', failedImages));
            return;
        }

        var id = imageIds.shift(); // Get next ID

        $.ajax({
            url: marrison_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'marrison_regenerate_single_image',
                nonce: marrison_vars.nonce,
                id: id,
                size: selectedSize,
                cleanup: cleanupDisabled
            },
            success: function(response) {
                processedImages++;
                updateProgress();

                if (response.success && response.data.skipped) {
                    skippedImages++;
                    $logList.prepend('<li class="skipped">' + response.data.message + '</li>');
                } else if (response.success) {
                    successfulImages++;
                    $logList.prepend('<li class="success">' + response.data.message + '</li>');
                } else {
                    failedImages++;
                    $logList.prepend('<li class="error">' + response.data.message + '</li>');
                }
                
                processNextImage();
            },
            error: function(xhr, status, error) {
                processedImages++;
                failedImages++;
                updateProgress();
                $logList.prepend('<li class="error">Error processing ID ' + id + ': ' + error + '</li>');
                processNextImage();
            }
        });
    }

    function updateProgress() {
        var percentage = Math.round((processedImages / totalImages) * 100);
        $progressFill.css('width', percentage + '%');
        $progressText.text(percentage + '% (' + processedImages + '/' + totalImages + ')');
    }

    function finishRegeneration(message, completed) {
        isProcessing = false;
        restoreControls();
        if (message) {
            $logList.prepend('<li><strong>' + message + '</strong></li>');
        }
        if (completed !== false) {
            $progressFill.css('width', '100%');
        }

        $.ajax({
            url: marrison_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'marrison_clear_elementor_css_cache',
                nonce: marrison_vars.nonce
            },
            success: function(response) {
                if (response.success && response.data.message) {
                    $logList.prepend('<li class="success">' + response.data.message + '</li>');
                }
            }
        });
    }
});
