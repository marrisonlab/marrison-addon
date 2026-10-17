(function($) {
    'use strict';

    var registered = false;
    var backgroundName = /^background_(?:(?:overlay|hover|overlay_hover)_)?image(?:_[a-z0-9_]+)?$/;

    function register() {
        var editor = window.elementor;
        if (registered || !editor || !editor.hooks || !window.Marionette || !window.marrisonDynamicBackgroundSizeEditor) {
            return;
        }
        registered = true;

        var BackgroundResolution = window.Marionette.Behavior.extend({
            initialize: function() {
                this.settings = this.view.options.container.settings;
                this.name = this.view.options.model.get('name');
                this.frame = null;
                this.listenTo(this.settings, 'change:__dynamic__ change:external:__dynamic__ change:' + this.name + ' change:external:' + this.name, this.scheduleSync);
                this.listenTo(this.view.options.model, 'change:placeholder', this.scheduleSync);
            },

            onRender: function() {
                this.sync();
            },

            onReady: function() {
                this.sync();
            },

            scheduleSync: function() {
                if (this.frame !== null) {
                    window.cancelAnimationFrame(this.frame);
                }
                var behavior = this;
                this.frame = window.requestAnimationFrame(function() {
                    behavior.frame = null;
                    behavior.sync();
                });
            },

            sync: function() {
                if (this.view.isDestroyed) {
                    return;
                }
                var dynamic = this.settings.get('__dynamic__') || {};
                var active = !!dynamic[this.name];
                this.view.$el.toggleClass('marrison-dynamic-background-size-control', active);

                var field = this.view.$el.find('.e-control-image-size');
                var description = field.children('.elementor-control-field-description');
                if (description.length && description.data('marrison-original-notice') === undefined) {
                    description.data('marrison-original-notice', description.text());
                }
                description.text(active ? window.marrisonDynamicBackgroundSizeEditor.notice : description.data('marrison-original-notice'));

                if (active) {
                    // Dynamic CSS uses this control's saved size, not its static responsive placeholder.
                    var value = this.settings.get(this.name) || {};
                    field.find('.e-image-size-select').val(typeof value.size === 'string' ? value.size : '').removeClass('e-select-placeholder');
                }
            },

            onDestroy: function() {
                if (this.frame !== null) {
                    window.cancelAnimationFrame(this.frame);
                }
            }
        });

        editor.hooks.addFilter('controls/base/behaviors', function(behaviors, view) {
            // Behaviors are created before Backbone assigns view.model and view.container.
            var model = view.options.model;
            var container = view.options.container;
            if (!model || !container || !container.model || container.model.get('elType') !== 'container' || model.get('type') !== 'media' || !model.get('has_sizes') || !backgroundName.test(model.get('name'))) {
                return behaviors;
            }
            behaviors.marrisonDynamicBackgroundSize = { behaviorClass: BackgroundResolution };
            return behaviors;
        });
    }

    $(window).on('elementor:init', register);
    register();
})(jQuery);
