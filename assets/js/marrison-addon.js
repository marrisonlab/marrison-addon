jQuery(document).ready(function($) {
    // Wrapped Link Handler
    $(document).on('click', '[data-marrison-addon]', function(e) {
        // 1. Ignore clicks on interactive elements
        if ($(e.target).closest('a, button, input, select, textarea').length) {
            return;
        }

        var $wrapper = $(this);
        
        // 2. Get data explicitly from attribute to avoid jQuery parsing issues/caching
        var rawData = $wrapper.attr('data-marrison-addon');
        
        if (!rawData) {
            return;
        }

        // 3. Parse JSON manually
        var settings = {};
        try {
            settings = JSON.parse(rawData);
        } catch (error) {
            console.error('Marrison Addon: Failed to parse wrapped link data', error);
            return;
        }

        if (!settings.url) {
            return;
        }

        var url = settings.url;
        var target = settings.is_external === 'on' ? '_blank' : '_self';

        // 4. Navigate
        if (target === '_blank') {
            window.open(url, target);
        } else {
            location.href = url;
        }
    });
});