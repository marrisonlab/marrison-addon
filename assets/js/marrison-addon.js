document.addEventListener('click', function(event) {
    var wrapper = event.target.closest('[data-marrison-addon]');

    if (!wrapper) {
        return;
    }

    if (event.target.closest('a, button, input, select, textarea, [role="button"]')) {
        return;
    }

    var rawData = wrapper.getAttribute('data-marrison-addon');

    if (!rawData) {
        return;
    }

    var settings;

    try {
        settings = JSON.parse(rawData);
    } catch (error) {
        return;
    }

    if (!settings.url) {
        return;
    }

    if (settings.is_external === 'on') {
        window.open(settings.url, '_blank', 'noopener');
        return;
    }

    window.location.href = settings.url;
});
