jQuery(document).ready(function($){
    
    // Color Picker
    $('.marrison-color-field').wpColorPicker();

    // Media Uploader
    var mediaUploader;

    $('.marrison-media-upload-btn').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var inputField = button.siblings('.marrison-media-url');
        var previewContainer = button.siblings('.marrison-media-preview');

        // If the uploader object has already been created, reopen the dialog
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        // Extend the wp.media object
        mediaUploader = wp.media.frames.file_frame = wp.media({
            title: 'Scegli Immagine',
            button: {
                text: 'Usa questa immagine'
            },
            multiple: false
        });

        // When a file is selected, grab the URL and set it as the text field's value
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            inputField.val(attachment.url);
            
            // Update preview
            if (previewContainer.length) {
                previewContainer.html('<img src="' + attachment.url + '" style="max-width: 100%; height: auto;">');
            } else {
                button.after('<div class="marrison-media-preview" style="margin-top: 10px; max-width: 200px;"><img src="' + attachment.url + '" style="max-width: 100%; height: auto;"></div>');
            }
        });

        // Open the uploader dialog
        mediaUploader.open();
    });

});
