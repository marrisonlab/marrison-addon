(function() {
    'use strict';

    var preloader = document.getElementById('marrison-preloader');

    if (!preloader) {
        return;
    }

    var progressBar = preloader.querySelector('.marrison-preloader-progress-bar');
    var percentageText = preloader.querySelector('.marrison-preloader-percentage');
    var progress = 0;
    var progressTimer = null;
    var duration = 500;

    if (typeof marrison_preloader_settings !== 'undefined' && marrison_preloader_settings.transition_duration) {
        duration = parseInt(marrison_preloader_settings.transition_duration, 10) || 500;
    }

    function updateProgress(value) {
        if (!progressBar || !percentageText) {
            return;
        }

        progressBar.style.width = value + '%';
        percentageText.textContent = Math.floor(value) + '%';
    }

    function startProgressSimulation() {
        if (!progressBar) {
            return;
        }

        progressTimer = window.setInterval(function() {
            var increment = Math.random() * 10;

            if (progress > 80) {
                increment = Math.random() * 2;
            }

            if (progress > 95) {
                increment = 0.5;
            }

            progress = Math.min(progress + increment, 99);
            updateProgress(progress);
        }, 180);
    }

    function showPreloaderForExit() {
        preloader.classList.add('marrison-enter');

        window.requestAnimationFrame(function() {
            preloader.classList.remove('marrison-loaded');
            void preloader.offsetWidth;
            preloader.classList.remove('marrison-enter');
        });
    }

    function getExitDelay() {
        if (preloader.classList.contains('marrison-anim-shutter-vert')) {
            return Math.max(duration, 900);
        }

        if (preloader.classList.contains('marrison-anim-slide-up') || preloader.classList.contains('marrison-anim-slide-left') || preloader.classList.contains('marrison-anim-split')) {
            return Math.max(duration, 800);
        }

        return Math.max(duration, 300);
    }

    function hidePreloader() {
        if (progressTimer) {
            window.clearInterval(progressTimer);
        }

        updateProgress(100);
        window.setTimeout(function() {
            preloader.classList.add('marrison-loaded');
        }, 250);
    }

    function shouldInterceptLink(link, event) {
        if (!link || !link.href) {
            return false;
        }

        if (link.hasAttribute('download') || link.classList.contains('no-preloader') || link.dataset.noPreloader === '1') {
            return false;
        }

        if (link.target === '_blank' || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
            return false;
        }

        if (link.closest('#wpadminbar, #elementor-panel, .elementor-editor-header')) {
            return false;
        }

        var url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin) {
            return false;
        }

        if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) {
            return false;
        }

        return true;
    }

    document.addEventListener('click', function(event) {
        var link = event.target.closest('a');

        if (!shouldInterceptLink(link, event)) {
            return;
        }

        event.preventDefault();
        showPreloaderForExit();

        if (progressBar) {
            progress = 0;
            updateProgress(0);
        }

        window.setTimeout(function() {
            window.location.href = link.href;
        }, getExitDelay());
    });

    window.addEventListener('beforeunload', function() {
        showPreloaderForExit();
    });

    if (document.readyState === 'complete') {
        hidePreloader();
    } else {
        window.addEventListener('load', hidePreloader, { once: true });
    }

    startProgressSimulation();
})();
