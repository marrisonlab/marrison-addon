/**
 * Frontend JavaScript per Marrison Cookie Manager
 */
(function($) {
    'use strict';

    var MarrisonCookie = {
        init: function() {
            this.showBanner = [false, '', '0', 0].indexOf(marrisonCookie.showBanner) === -1;
            $('#marrison-cookie-modal').appendTo('body');

            this.bindEvents();
            this.bindResponsiveClosedTrigger();
            this.refreshState();
            this.openPreferencesFromHash();
        },

        bindEvents: function() {
            $(document).on('click', '#marrison-accept-all', function(e) {
                e.preventDefault();
                MarrisonCookie.saveConsent('accept_all', []);
            });

            $(document).on('click', '#marrison-reject-all, #marrison-close-banner', function(e) {
                e.preventDefault();
                MarrisonCookie.saveConsent('reject_all', []);
            });

            $(document).on('click', '#marrison-customize', function(e) {
                e.preventDefault();
                MarrisonCookie.openPreferences(this);
            });

            $(document).on('click', '#marrison-close-modal', function(e) {
                e.preventDefault();
                MarrisonCookie.closePreferences();
            });

            $(document).on('click', '#marrison-cookie-modal', function(e) {
                if (e.target === this) {
                    MarrisonCookie.closePreferences();
                }
            });

            $(document).on('keydown', function(e) {
                if (!MarrisonCookie.modalOpen) { return; }
                if (e.key === 'Escape') { e.preventDefault(); MarrisonCookie.closePreferences(); return; }
                if (e.key !== 'Tab') { return; }
                var $items = MarrisonCookie.modalFocusables();
                var first = $items[0], last = $items[$items.length - 1];
                if (!$items.length) { e.preventDefault(); $('#marrison-cookie-dialog').trigger('focus'); }
                else if (e.shiftKey && (document.activeElement === first || document.activeElement === $('#marrison-cookie-dialog')[0])) { e.preventDefault(); last.focus(); }
                else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
            });

            $(document).on('focusin', function(e) {
                var modal = $('#marrison-cookie-modal')[0];
                if (MarrisonCookie.modalOpen && modal && !modal.contains(e.target)) { $('#marrison-close-modal').trigger('focus'); }
            });

            $(document).on('click', '#marrison-save-preferences', function(e) {
                e.preventDefault();
                MarrisonCookie.saveConsent('custom', MarrisonCookie.getSelectedCategories());
            });

            $(document).on('click', '#marrison-update-prefs', function(e) {
                e.preventDefault();
                MarrisonCookie.updatePreferences(MarrisonCookie.getSelectedCategories());
            });

            $(document).on('click', '#marrison-widget-button', function(e) {
                e.preventDefault();
                MarrisonCookie.openBanner();
            });

            $(document).on('click', 'a[href*="#marrison-cookie-preferences"], a[href*="#marrison-cookie-settings"], .marrison-open-cookie-preferences, [data-marrison-cookie-preferences]', function(e) {
                if (!MarrisonCookie.isPreferencesTrigger(this)) {
                    return;
                }

                e.preventDefault();
                MarrisonCookie.openPreferences(this);
            });
        },

        ajax: function(options, retried) {
            var request = $.extend({}, options);
            var originalSuccess = options.success || function() {};
            var originalError = options.error || function() {};

            request.data = $.extend({}, options.data || {}, {
                nonce: marrisonCookie.nonce
            });
            request.error = function(xhr, status, error) {
                if (!retried && MarrisonCookie.isNonceFailure(xhr)) {
                    MarrisonCookie.refreshNonce(function() {
                        MarrisonCookie.ajax(options, true);
                    }, function() {
                        originalError(xhr, status, error);
                    });
                    return;
                }

                originalError(xhr, status, error);
            };
            request.success = originalSuccess;

            $.ajax(request);
        },

        isNonceFailure: function(xhr) {
            return xhr && (xhr.status === 403 || $.trim(xhr.responseText || '') === '-1');
        },

        refreshNonce: function(done, fail) {
            $.ajax({
                url: marrisonCookie.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'marrison_cookie_refresh_nonce'
                },
                success: function(response) {
                    if (response && response.success && response.data && response.data.nonce) {
                        marrisonCookie.nonce = response.data.nonce;
                        done();
                    } else if (fail) {
                        fail();
                    }
                },
                error: function() {
                    if (fail) {
                        fail();
                    }
                }
            });
        },

        checkExistingConsent: function() {
            var consent = this.state && this.state.valid;
            var $banner = $('#marrison-cookie-banner');
            if (consent) { $banner.hide(); this.showFloatingWidget(); }
            else if ($banner.length && this.showBanner) { $banner.show(); this.hideFloatingWidget(); }
            else { $banner.hide(); this.hideFloatingWidget(); }
        },

        refreshState: function() {
            MarrisonCookie.ajax({ url: marrisonCookie.ajaxUrl, type: 'POST', data: { action: 'marrison_cookie_consent_state' },
                success: function(response) {
                    if (response && response.success && response.data && response.data.state) {
                        $('#marrison-consent-status, #marrison-modal-status').empty();
                        MarrisonCookie.acceptState(response.data.state);
                        MarrisonCookie.applyExistingPreferences();
                        MarrisonCookie.checkExistingConsent();
                        MarrisonCookie.activateAllowedBlockedContent();
                    } else { MarrisonCookie.consentError(response && response.data && response.data.message); }
                }, error: function() { MarrisonCookie.consentError(); }
            });
        },

        acceptState: function(state) {
            this.state = state;
            if (window.MarrisonConsentRuntime) { window.MarrisonConsentRuntime.setState(state); }
        },

        consentError: function(message) {
            $('#marrison-consent-status, #marrison-prefs-message, #marrison-modal-status').text(message || marrisonCookie.saveErrorText || 'Impossibile verificare o salvare le preferenze. Riprova.').show();
            $('<button type="button" class="marrison-consent-retry">Riprova verifica</button>').on('click', function() { MarrisonCookie.refreshState(); }).appendTo(this.modalOpen ? '#marrison-modal-status' : '#marrison-consent-status');
            this.checkExistingConsent();
        },

        showFloatingWidget: function() {
            var $widget = $('#marrison-floating-widget');

            if (!this.shouldShowFloatingWidget()) {
                $widget.hide();
                return;
            }

            $widget.show();
        },

        hideFloatingWidget: function() {
            $('#marrison-floating-widget').hide();
        },

        openBanner: function() {
            var $banner = $('#marrison-cookie-banner');

            if (!$banner.length) {
                return;
            }

            this.hideFloatingWidget();
            $banner.show();
        },

        modalFocusables: function() {
            return $('#marrison-cookie-modal').find('a[href], button, input, select, textarea, [tabindex]').filter(':visible').filter(function() {
                return !this.disabled && this.tabIndex >= 0;
            });
        },

        openPreferences: function(opener) {
            var $modal = $('#marrison-cookie-modal');

            if (!$modal.length) {
                this.openBanner();
                return;
            }

            this.loadCookieList();
            if (this.modalOpen) { return; }
            this.modalOpener = opener || document.activeElement;
            this.modalOpen = true;
            this.backgroundState = [];
            $modal.attr('aria-hidden', 'false').css('display', 'flex');
            $('#marrison-close-modal').trigger('focus');
            // Inert prevents pointer, keyboard and screen-reader access behind the dialog.
            Array.prototype.forEach.call(document.body.children, function(element) {
                if (element === $modal[0] || /^(SCRIPT|STYLE|LINK)$/.test(element.tagName)) { return; }
                MarrisonCookie.backgroundState.push({ element: element, inert: element.hasAttribute('inert') });
                element.setAttribute('inert', '');
            });
            this.bodyOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
        },

        closePreferences: function() {
            if (!this.modalOpen) { return; }
            this.modalOpen = false;
            $('#marrison-cookie-modal').hide().attr('aria-hidden', 'true');
            (this.backgroundState || []).forEach(function(item) {
                if (!item.inert) { item.element.removeAttribute('inert'); }
            });
            document.body.style.overflow = this.bodyOverflow || '';
            var $opener = $(this.modalOpener);
            if ($opener.length && document.documentElement.contains($opener[0]) && $opener.is(':visible')) { $opener.trigger('focus'); }
            else if ($('#marrison-widget-button').is(':visible')) { $('#marrison-widget-button').trigger('focus'); }
            this.backgroundState = [];
            this.modalOpener = null;
        },

        openPreferencesFromHash: function() {
            if (window.location.hash === '#marrison-cookie-preferences' || window.location.hash === '#marrison-cookie-settings') {
                this.openPreferences();
            }
        },

        isPreferencesTrigger: function(element) {
            var $element = $(element);
            var href = $element.attr('href') || '';

            return $element.hasClass('marrison-open-cookie-preferences') ||
                typeof $element.attr('data-marrison-cookie-preferences') !== 'undefined' ||
                href.indexOf('#marrison-cookie-preferences') !== -1 ||
                href.indexOf('#marrison-cookie-settings') !== -1;
        },

        bindResponsiveClosedTrigger: function() {
            var resizeTimer = null;

            $(window).on('resize orientationchange', function() {
                window.clearTimeout(resizeTimer);
                resizeTimer = window.setTimeout(function() {
                    MarrisonCookie.refreshClosedTriggerVisibility();
                }, 120);
            });
        },

        refreshClosedTriggerVisibility: function() {
            var consent = this.state && this.state.valid;
            var $banner = $('#marrison-cookie-banner');

            if (consent && (!$banner.length || !$banner.is(':visible'))) {
                this.showFloatingWidget();
            } else {
                this.hideFloatingWidget();
            }
        },

        shouldShowFloatingWidget: function() {
            return this.getClosedTriggerMode() === 'floating';
        },

        getClosedTriggerMode: function() {
            var modes = marrisonCookie.closedTriggerModes || {};
            var device = this.getCurrentDevice();
            var mode = modes[device] || 'floating';

            return mode === 'link' ? 'link' : 'floating';
        },

        getCurrentDevice: function() {
            if (window.matchMedia && window.matchMedia('(max-width: 768px)').matches) {
                return 'mobile';
            }

            if (window.matchMedia && window.matchMedia('(max-width: 1024px)').matches) {
                return 'tablet';
            }

            return 'desktop';
        },

        loadCookieList: function() {
            var $modalBody = $('#marrison-cookie-modal .marrison-modal-body');

            if ($modalBody.data('cookiesLoaded') || $modalBody.find('.marrison-cookie-list-loading').length) {
                return;
            }

            $modalBody.find('.marrison-category-cookie-list').empty();
            $modalBody.prepend($('<p class="marrison-cookie-list-loading"></p>').text(marrisonCookie.loadingText));

            MarrisonCookie.ajax({
                url: marrisonCookie.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'marrison_get_cookie_list'
                },
                success: function(response) {
                    $modalBody.find('.marrison-cookie-list-loading').remove();
                    if (response.success) {
                        MarrisonCookie.renderCookieLists(response.data);
                        $modalBody.data('cookiesLoaded', true);
                    }
                },
                error: function() {
                    $modalBody.find('.marrison-cookie-list-loading').remove();
                }
            });
        },

        renderCookieLists: function(data) {
            var categories = data.categories || {};
            var hasAnyCookie = false;

            $('.marrison-category-cookie-list').empty().removeClass('has-cookies');

            $.each(categories, function(category, html) {
                var $target = $('.marrison-category-cookie-list[data-cookie-list="' + category + '"]');

                if (html && $target.length) {
                    $target.html(html).addClass('has-cookies');
                    hasAnyCookie = true;
                }
            });

            if (!hasAnyCookie && data.message) {
                $('.marrison-category-cookie-list[data-cookie-list="functional"]')
                    .empty()
                    .append($('<p class="marrison-cookie-list-empty"></p>').text(data.message))
                    .addClass('has-cookies');
            }
        },

        getSelectedCategories: function() {
            var categories = [];

            $('.marrison-category-checkbox:checked, .marrison-pref-checkbox:checked').each(function() {
                var category = $(this).data('category');
                if (category && categories.indexOf(category) === -1) {
                    categories.push(category);
                }
            });

            return categories;
        },

        applyExistingPreferences: function() {
            var categories = MarrisonCookie.getAllowedCategories();
            $('.marrison-category-checkbox, .marrison-pref-checkbox').each(function() {
                var $checkbox = $(this);
                if (!$checkbox.prop('disabled')) { $checkbox.prop('checked', categories.indexOf($checkbox.data('category')) !== -1); }
            });
        },

        saveConsent: function(consentType, categories) {
            var previousCategories = MarrisonCookie.getAllowedCategories();
            var $buttons = $('#marrison-accept-all, #marrison-reject-all, #marrison-close-banner, #marrison-save-preferences, #marrison-update-prefs');
            $buttons.prop('disabled', true);
            $('#marrison-consent-status, #marrison-modal-status').empty();
            MarrisonCookie.ajax({ url: marrisonCookie.ajaxUrl, type: 'POST',
                data: { action: 'marrison_save_consent', consent_type: consentType, categories: categories, revision: this.state && this.state.revision || '' },
                success: function(response) {
                    $buttons.prop('disabled', false);
                    if (!response || !response.success || !response.data || !response.data.state || !response.data.state.valid) { MarrisonCookie.consentError(response && response.data && response.data.message); return; }
                    MarrisonCookie.acceptState(response.data.state);
                    MarrisonCookie.applyExistingPreferences();
                    var nextCategories = MarrisonCookie.getAllowedCategories();
                    if (previousCategories.some(function(category) { return category !== 'necessary' && nextCategories.indexOf(category) === -1; })) { location.reload(); return; }
                    MarrisonCookie.activateAllowedBlockedContent();
                    $('#marrison-cookie-banner').hide();
                    $('#marrison-prefs-message').text(response.data.message).show();
                    MarrisonCookie.showFloatingWidget();
                    MarrisonCookie.closePreferences();
                }, error: function() { $buttons.prop('disabled', false); MarrisonCookie.consentError(); }
            });
        },



        activateAllowedBlockedContent: function() {
            var allowedCategories = MarrisonCookie.getAllowedCategories();

            $('[data-marrison-cookie-blocked]').each(function() {
                var element = this;
                var $element = $(element);
                var category = $element.data('marrison-cookie-category');

                if (allowedCategories.indexOf(category) === -1) {
                    return;
                }

                if ($element.data('marrison-cookie-blocked') === 'script') {
                    MarrisonCookie.activateBlockedScript(element);
                } else if ($element.data('marrison-cookie-blocked') === 'iframe') {
                    MarrisonCookie.activateBlockedIframe(element);
                } else if ($element.data('marrison-cookie-blocked') === 'img') {
                    MarrisonCookie.activateBlockedIframe(element);
                }
            });
        },

        activateBlockedScript: function(element) {
            var replacement = document.createElement('script');
            var blockedSrc = element.getAttribute('data-marrison-blocked-src');
            var originalType = element.getAttribute('data-marrison-script-type');

            $.each(element.attributes, function(index, attr) {
                if (!attr || attr.name === 'type' || attr.name.indexOf('data-marrison-') === 0) {
                    return;
                }

                replacement.setAttribute(attr.name, attr.value);
            });

            if (originalType !== null) {
                replacement.setAttribute('type', originalType);
            }

            if (blockedSrc) {
                replacement.src = blockedSrc;
            } else {
                replacement.text = element.text || element.textContent || element.innerHTML || '';
            }

            element.parentNode.replaceChild(replacement, element);
        },

        activateBlockedIframe: function(element) {
            var blockedSrc = element.getAttribute('data-marrison-blocked-src');
            var blockedSrcset = element.getAttribute('data-marrison-blocked-srcset');

            if (blockedSrcset) {
                element.setAttribute('srcset', blockedSrcset);
                element.removeAttribute('data-marrison-blocked-srcset');
            }

            if (blockedSrc) {
                element.setAttribute('src', blockedSrc);
                element.removeAttribute('data-marrison-blocked-src');
                element.removeAttribute('data-marrison-cookie-blocked');
            }
            if (blockedSrcset) { element.removeAttribute('data-marrison-cookie-blocked'); }
        },

        getAllowedCategories: function() {
            if (marrisonCookie.scanPhase === 'after') { return ['necessary', 'functional', 'analytics', 'marketing']; }
            if (marrisonCookie.scanPhase === 'before') { return ['necessary']; }
            var state = this.state;
            if (!state || !state.valid || state.expires * 1000 <= Date.now()) { return ['necessary']; }
            return ['necessary'].concat(state.categories.filter(function(category) { return ['functional', 'analytics', 'marketing'].indexOf(category) !== -1; }));
        },

        updatePreferences: function(categories) {
            this.saveConsent('custom', categories);
        },

        getCookie: function(name) {
            var value = '; ' + document.cookie;
            var parts = value.split('; ' + name + '=');

            if (parts.length === 2) {
                return parts.pop().split(';').shift();
            }

            return null;
        }
    };

    $(document).ready(function() {
        MarrisonCookie.init();
    });
})(jQuery);
