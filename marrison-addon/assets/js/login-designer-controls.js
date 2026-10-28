(function () {
    'use strict';
    wp.customize.bind('ready', function () {
        var previous = '';
        wp.customize('marrison_addon_login_designer[layout]', function (layout) {
            ['split_side', 'split_ratio', 'split_bg', 'split_opacity', 'split_image', 'split_position'].forEach(function (key) {
                wp.customize.control('marrison_addon_login_designer[' + key + ']', function (control) {
                    function update(value) { control.active.set(value === 'split'); }
                    update(layout.get());
                    layout.bind(update);
                });
            });
        });
        wp.customize('mld_preset', function (setting) {
            setting.bind(function (name) {
                if (!name || name === previous || !marrisonLoginPresets.presets[name]) return;
                if (!window.confirm(marrisonLoginPresets.confirm)) { setting.set(previous); return; }
                previous = name;
                var preset = marrisonLoginPresets.presets[name];
                Object.keys(preset).forEach(function (key) {
                    var target = wp.customize('marrison_addon_login_designer[' + key + ']');
                    if (target) target.set(preset[key]);
                });
                previous = '';
                setting.set('');
            });
        });
    });
}());
