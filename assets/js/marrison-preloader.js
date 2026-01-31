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
            // Random increment simulation
            interval = setInterval(function() {
                // Slower increment as it gets closer to 90%
                let increment = Math.random() * 10;
                if (progress > 80) increment = Math.random() * 2;
                if (progress > 95) increment = 0.5;
                
                progress += increment;
                
                // Cap at 99% until load event fires
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
            // Finish progress
            if (interval) clearInterval(interval);
            updateProgress(100);

            const duration = (marrison_preloader_settings && marrison_preloader_settings.transition_duration) 
                             ? parseInt(marrison_preloader_settings.transition_duration) 
                             : 500;

            if (preloader.length) {
                // Short delay to let users see 100%
                setTimeout(function() {
                    
                    // For fade animation, we can set transition duration dynamically
                    if (preloader.hasClass('marrison-anim-fade')) {
                        preloader.css('transition-duration', (duration / 1000) + 's');
                    }
                    
                    // Add class to trigger exit animation
                    preloader.addClass('marrison-loaded');

                    // Remove from DOM after transition completes
                    // Duration might need to be longer for slide/split animations (CSS has 0.8s)
                    let removeDelay = duration;
                    if (preloader.hasClass('marrison-anim-slide-up') || preloader.hasClass('marrison-anim-slide-left') || preloader.hasClass('marrison-anim-split')) {
                        removeDelay = 800; // Match CSS transition time
                    } else if (preloader.hasClass('marrison-anim-shutter-vert')) {
                        removeDelay = 900; // 0.3s delay + 0.6s transition
                    }

                    setTimeout(function() {
                        preloader.remove();
                    }, removeDelay + 100);

                }, 500); // 500ms delay at 100%
            }
        });
    });

})(jQuery);
