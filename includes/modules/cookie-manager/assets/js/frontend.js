/**
 * Frontend JavaScript per Marrison Cookie Manager
 */
(function($) {
    'use strict';

    var MarrisonCookie = {
        init: function() {
            $('#marrison-cookie-modal').appendTo('body');

            this.bindEvents();
            this.applyExistingPreferences();
            this.checkExistingConsent();
        },

        bindEvents: function() {
            $(document).on('click', '#marrison-accept-all', function(e) {
                e.preventDefault();
                MarrisonCookie.saveConsent('accept_all', []);
            });

            $(document).on('click', '#marrison-reject-all', function(e) {
                e.preventDefault();
                MarrisonCookie.saveConsent('reject_all', []);
            });

            $(document).on('click', '#marrison-customize', function(e) {
                e.preventDefault();
                MarrisonCookie.loadCookieList();
                $('#marrison-cookie-modal').show();
            });

            $(document).on('click', '#marrison-close-modal', function(e) {
                e.preventDefault();
                $('#marrison-cookie-modal').hide();
            });

            $(document).on('click', '#marrison-cookie-modal', function(e) {
                if (e.target === this) {
                    $(this).hide();
                }
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
        },

        checkExistingConsent: function() {
            // hasConsent copre anche i cookie HttpOnly creati dalle versioni precedenti.
            var consent = MarrisonCookie.getCookie('marrison_cookie_consent') || (marrisonCookie.hasConsent ? 'stored' : null);
            var $banner = $('#marrison-cookie-banner');

            if (consent) {
                $banner.hide();
                MarrisonCookie.showFloatingWidget();
            } else if ($banner.length) {
                $banner.show();
                MarrisonCookie.hideFloatingWidget();
            } else {
                MarrisonCookie.showFloatingWidget();
            }
        },

        showFloatingWidget: function() {
            $('#marrison-floating-widget').show();
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

        loadCookieList: function() {
            var $modalBody = $('#marrison-cookie-modal .marrison-modal-body');

            if ($modalBody.data('cookiesLoaded') || $modalBody.find('.marrison-cookie-list-loading').length) {
                return;
            }

            $modalBody.find('.marrison-category-cookie-list').empty();
            $modalBody.prepend($('<p class="marrison-cookie-list-loading"></p>').text(marrisonCookie.loadingText));

            $.ajax({
                url: marrisonCookie.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'marrison_get_cookie_list',
                    nonce: marrisonCookie.nonce
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
            var consent = MarrisonCookie.getCookie('marrison_cookie_consent');
            var categoriesCookie = MarrisonCookie.getCookie('marrison_cookie_categories');
            var categories = [];

            if (categoriesCookie) {
                categories = decodeURIComponent(categoriesCookie).split('|');
            } else if (consent === 'accept_all') {
                categories = ['necessary', 'functional', 'analytics', 'marketing'];
            } else if (consent === 'reject_all') {
                categories = ['necessary'];
            }

            if (!categories.length) {
                return;
            }

            $('.marrison-category-checkbox, .marrison-pref-checkbox').each(function() {
                var $checkbox = $(this);
                var category = $checkbox.data('category');

                if (!$checkbox.prop('disabled')) {
                    $checkbox.prop('checked', categories.indexOf(category) !== -1);
                }
            });
        },

        saveConsent: function(consentType, categories) {
            $.ajax({
                url: marrisonCookie.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'marrison_save_consent',
                    nonce: marrisonCookie.nonce,
                    consent_type: consentType,
                    categories: categories
                },
                success: function(response) {
                    if (response.success) {
                        $('#marrison-cookie-banner').hide();
                        $('#marrison-cookie-modal').hide();
                        MarrisonCookie.showFloatingWidget();
                    } else if (response.data && response.data.message) {
                        window.console && console.error('Errore salvataggio consenso:', response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    window.console && console.error('Errore AJAX:', error);
                }
            });
        },

        updatePreferences: function(categories) {
            $.ajax({
                url: marrisonCookie.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'marrison_update_preferences',
                    nonce: marrisonCookie.nonce,
                    categories: categories
                },
                success: function(response) {
                    if (response.success) {
                        $('#marrison-prefs-message')
                            .removeClass('error')
                            .addClass('success')
                            .text(response.data.message)
                            .show();

                        setTimeout(function() {
                            $('#marrison-prefs-message').fadeOut();
                        }, 3000);

                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        $('#marrison-prefs-message')
                            .removeClass('success')
                            .addClass('error')
                            .text(response.data.message)
                            .show();
                    }
                },
                error: function() {
                    $('#marrison-prefs-message')
                        .removeClass('success')
                        .addClass('error')
                        .text(marrisonCookie.updateErrorText || 'Error while updating')
                        .show();
                }
            });
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
