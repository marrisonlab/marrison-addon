(function () {
    'use strict';
    if (!window.elementor || !window.marrisonQueryFallback) return;
    const config = window.marrisonQueryFallback;
    elementor.hooks.addAction('panel/open_editor/widget/jet-listing-grid', function (panel, model) {
        const page = panel.getCurrentPageView();
        const settings = model.get('settings');
        if (!page || !page.getControlModel || !settings) return;
        let lastPrimary;
        function update() {
            const control = page.getControlModel('marrison_query_fallback_id');
            const warning = page.getControlModel('marrison_query_fallback_warning');
            if (!control || !warning) return;
            const primary = String(settings.get('custom_query_id') || '');
            const fallback = String(settings.get('marrison_query_fallback_id') || '');
            const options = Object.assign({}, config.queries);
            if (primary) delete options[primary];
            if (primary !== lastPrimary) {
                lastPrimary = primary;
                control.set('options', options);
                const select = page.getControlViewByName('marrison_query_fallback_id');
                if (select) {
                    if (select.select2Instance) select.select2Instance.destroy();
                    select.render();
                }
            }
            let message = '';
            if (!primary || !fallback || primary === fallback || !config.queries[primary] || !options[fallback]) {
                message = config.invalid;
            }
            const unsupported = ['use_load_more', 'lazy_load', 'is_archive_template', 'use_random_posts_num'].find(key => {
                return [true, 1, '1', 'true', 'yes', 'on'].includes(settings.get(key));
            });
            if (unsupported) message = (config.unsupportedSettings || {})[unsupported] || config.unsupported;
            if (!config.compatible) message = config.apiMissing;
            // Only translated module-owned strings enter the native RAW_HTML control.
            warning.set('raw', message);
            const notice = page.getControlViewByName('marrison_query_fallback_warning');
            if (notice) notice.render();
        }
        page.listenTo(settings, 'change:custom_query_id change:marrison_query_fallback_id change:marrison_query_fallback_enable change:use_load_more change:lazy_load change:is_archive_template change:use_random_posts_num', update);
        update();
    });
}());
