(function($) {
    'use strict';

    $(document).ready(function() {
        const preloader = $('#marrison-preloader');
        const progressBar = preloader.find('.marrison-preloader-progress-bar');
        const percentageText = preloader.find('.marrison-preloader-percentage');
        
        let progress = 0;
        let interval;
        
        // Progress Bar Simulation
        if (progressBar.length) {
            interval = setInterval(function() {
                let increment = Math.random() * 10;
                if (progress > 80) increment = Math.random() * 2;
                if (progress > 95) increment = 0.5;
                
                progress += increment;
                
                if (progress >= 99) progress = 99;
                
                updateProgress(progress);
            }, 200);
        }

        function updateProgress(value) {
            const rounded = Math.floor(value);
            progressBar.css('width', value + '%');
            percentageText.text(rounded + '%');
        }

        $(window).on('load', function() {
            if (interval) clearInterval(interval);
            updateProgress(100);

            const duration = (marrison_preloader_settings && marrison_preloader_settings.transition_duration) 
                             ? parseInt(marrison_preloader_settings.transition_duration) 
                             : 500;

            if (preloader.length) {
                setTimeout(function() {
                    if (preloader.hasClass('marrison-anim-fade')) {
                        preloader.css('transition-duration', (duration / 1000) + 's');
                    }
                    
                    preloader.addClass('marrison-loaded');
                }, 500);
            }
        });

        // Exit transition on internal link click
        if (preloader.length) {
            $(document).on('click', 'a', function(e) {
                const link = $(this);
                const href = link.attr('href');

                if (!href || href.charAt(0) === '#' || href.indexOf('mailto:') === 0 || href.indexOf('tel:') === 0) {
                    return;
                }

                if (link.attr('target') === '_blank') {
                    return;
                }

                if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
                    return;
                }

                const url = new URL(href, window.location.href);
                if (url.origin !== window.location.origin) {
                    return;
                }

                if (link.data('noPreloader') === 1 || link.hasClass('no-preloader')) {
                    return;
                }

                e.preventDefault();

                // Prepare entrance animation for exiting page
                preloader.addClass('marrison-enter');
                
                // Detect animation type for timing
                const isSlide = preloader.hasClass('marrison-anim-slide-up') || preloader.hasClass('marrison-anim-slide-left');
                const isSplit = preloader.hasClass('marrison-anim-split');
                const isShutter = preloader.hasClass('marrison-anim-shutter-vert');
                
                let enterDelay = 300; // fade default
                if (isSlide || isSplit) enterDelay = 800;
                if (isShutter) enterDelay = 900;

                // Sequence: show overlay from hidden state, then animate to visible
                requestAnimationFrame(function() {
                    preloader.removeClass('marrison-loaded');
                    // force reflow
                    void preloader[0].offsetWidth;
                    preloader.removeClass('marrison-enter');
                });

                if (progressBar.length) {
                    progress = 0;
                    updateProgress(0);
                }

                setTimeout(function() {
                    window.location.href = href;
                }, enterDelay);
            });

            // Global fallback: show preloader on any page unload/navigation
            // Ensures exit animation appears even when navigation isn't triggered by a standard <a> click
            window.addEventListener('beforeunload', function() {
                // Prepare and show overlay
                preloader.addClass('marrison-enter');
                requestAnimationFrame(function() {
                    preloader.removeClass('marrison-loaded');
                    void preloader[0].offsetWidth;
                    preloader.removeClass('marrison-enter');
                });
            });
        }
    });

})(jQuery);
