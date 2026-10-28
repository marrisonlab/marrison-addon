(function () {
    'use strict';
    var config = window.marrisonLoginPreview, values = config.settings;
    var body = document.body, logo = document.querySelector('#login h1 a');
    var custom = document.getElementById('marrison-login-custom-css') || document.createElement('style');
    document.head.appendChild(custom);
    function render() {
        Object.keys(config.schema).forEach(function (key) {
            var type = config.schema[key][0], value = values[key];
            var name = '--mld-' + key.replace(/_/g, '-');
            if (['color', 'number', 'select'].indexOf(type) !== -1) body.style.setProperty(name, value);
            if (type === 'image') body.style.setProperty(name, value ? 'url(' + JSON.stringify(value) + ')' : 'none');
        });
        Object.keys(config.backgroundOpacities).forEach(function (key) {
            var hex = values[key].replace('#', '');
            if (hex.length === 3) hex = hex.split('').map(function (digit) { return digit + digit; }).join('');
            var rgb = parseInt(hex, 16);
            var alpha = Number(values[config.backgroundOpacities[key]]) / 100;
            body.style.setProperty('--mld-' + key.replace(/_/g, '-') + '-alpha', 'rgba(' + ((rgb >> 16) & 255) + ',' + ((rgb >> 8) & 255) + ',' + (rgb & 255) + ',' + alpha + ')');
        });
        body.style.setProperty('--mld-gradient', values.gradient_enabled ? 'linear-gradient(calc(var(--mld-gradient-angle)*1deg),var(--mld-page-bg-alpha),var(--mld-gradient-end-alpha))' : 'none');
        body.style.setProperty('--mld-button-width', values.button_full ? '100%' : 'auto');
        ['center', 'left', 'right', 'split'].forEach(function (name) { body.classList.toggle('mld-layout-' + name, values.layout === name); });
        ['left', 'right'].forEach(function (name) { body.classList.toggle('mld-side-' + name, values.split_side === name); });
        body.classList.toggle('mld-logo', !!(values.logo_enabled && values.logo_image));
        logo.textContent = values.logo_enabled && values.logo_image && values.logo_text ? values.logo_text : config.logoText;
        var languageSwitcher = document.querySelector('.language-switcher');
        if (languageSwitcher) languageSwitcher.hidden = !!values.hide_language_dropdown;
        // No preview links perform navigation or authentication.
        custom.textContent = values.custom_css || '';
    }
    Object.keys(config.schema).forEach(function (key) {
        wp.customize(config.option + '[' + key + ']', function (setting) {
            values[key] = setting.get();
            setting.bind(function (value) { values[key] = value; render(); });
            render();
        });
    });
}());
