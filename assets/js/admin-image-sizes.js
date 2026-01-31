jQuery(document).ready(function($) {
    console.log('Marrison Addon: Image Sizes Script Loaded');
    // alert('Marrison Addon: Script Loaded'); // Debug
    var $regenerateBtn = $('#marrison-regenerate-btn');
    var $progressBar = $('#marrison-progress-bar');
    var $progressFill = $('#marrison-progress-fill');
    var $progressText = $('#marrison-progress-text');
    var $logContainer = $('#marrison-log-container');
    var $logList = $('#marrison-log-list');
    var $stopBtn = $('#marrison-stop-btn');

    var isProcessing = false;
    var totalImages = 0;
    var processedImages = 0;
    var imageIds = [];
    var cleanupDisabled = false;

    $regenerateBtn.on('click', function(e) {
        e.preventDefault();
        
        if (isProcessing) return;
        
        if (!confirm(marrison_vars.confirm_message)) {
            return;
        }

        startRegeneration();
    });

    $stopBtn.on('click', function(e) {
        e.preventDefault();
        isProcessing = false;
        $stopBtn.hide();
        $regenerateBtn.show().prop('disabled', false);
        $logList.prepend('<li><span style="color: red;">' + marrison_vars.process_stopped + '</span></li>');
    });

    function startRegeneration() {
        isProcessing = true;
        cleanupDisabled = $('#marrison-cleanup-disabled').is(':checked');
        $regenerateBtn.prop('disabled', true).hide();
        $stopBtn.show();
        $progressBar.show();
        $logContainer.show();
        $logList.empty();
        $progressFill.css('width', '0%');
        $progressText.text('0%');
        processedImages = 0;

        // Step 1: Get all image IDs
        $.ajax({
            url: marrison_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'marrison_get_image_ids',
                nonce: marrison_vars.nonce
            },
            success: function(response) {
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
                finishRegeneration('Server Error getting image IDs');
            }
        });
    }

    function processNextImage() {
        if (!isProcessing) return;

        if (imageIds.length === 0) {
            finishRegeneration(marrison_vars.done_message);
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
                cleanup: cleanupDisabled
            },
            success: function(response) {
                processedImages++;
                updateProgress();

                if (response.success) {
                    $logList.prepend('<li class="success">' + response.data.message + '</li>');
                } else {
                    $logList.prepend('<li class="error">' + response.data.message + '</li>');
                }
                
                processNextImage();
            },
            error: function(xhr, status, error) {
                processedImages++;
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

    function finishRegeneration(message) {
        isProcessing = false;
        $regenerateBtn.show().prop('disabled', false);
        $stopBtn.hide();
        $logList.prepend('<li><strong>' + message + '</strong></li>');
        $progressFill.css('width', '100%');
    }
});