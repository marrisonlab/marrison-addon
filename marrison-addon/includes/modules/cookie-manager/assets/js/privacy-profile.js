(function () {
    'use strict';
    function update(profile) {
        const source = profile.querySelector('[data-privacy-field="identity_source"]');
        if (!source) return;
        const business = source.value === 'business_info';
        profile.querySelectorAll('[data-privacy-manual]').forEach(node => { node.hidden = business; });
        profile.querySelectorAll('[data-privacy-business]').forEach(node => { node.hidden = !business; });
    }
    document.addEventListener('change', event => {
        if (!event.target.matches('[data-privacy-field="identity_source"]')) return;
        const profile = event.target.closest('.marrison-privacy-profile');
        if (profile) update(profile);
    });
    function init() { document.querySelectorAll('.marrison-privacy-profile').forEach(update); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
}());
