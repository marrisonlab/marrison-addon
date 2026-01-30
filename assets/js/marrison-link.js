jQuery(document).ready(function($) {
    $(document).on('click', '[data-marrison-link]', function(e) {
        // Check if the clicked element is an interactive element or inside one
        if ($(e.target).closest('a, button, input, select, textarea').length) {
            return;
        }

        var linkData = $(this).data('marrison-link');
        
        if (!linkData || !linkData.url) {
            return;
        }

        var url = linkData.url;
        var target = linkData.is_external === 'on' ? '_blank' : '_self';

        if (target === '_blank') {
            window.open(url, target);
        } else {
            location.href = url;
        }
    });
});
