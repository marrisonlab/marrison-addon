(function() {
    'use strict';

    if (typeof marrison_cursor_settings === 'undefined') {
        return;
    }

    if (!window.matchMedia('(pointer: fine)').matches || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    var params = new URLSearchParams(window.location.search);
    if (params.has('elementor-preview') || params.get('action') === 'elementor') {
        return;
    }

    var state = {
        dot: null,
        circle: null,
        dotX: 0,
        dotY: 0,
        circleX: 0,
        circleY: 0,
        mouseX: window.innerWidth / 2,
        mouseY: window.innerHeight / 2,
        velX: 0,
        velY: 0,
        insideViewport: false
    };

    var root = document.documentElement;
    var body = document.body;
    var hoverSelector = 'a, button, input[type="submit"], input[type="button"], .elementor-button, [role="button"]';
    var disabledSelector = '#wpadminbar, #elementor-panel, .elementor-editor-header, .elementor-context-menu, #elementor-mode-switcher, .elementor-editor-active .elementor-element-overlay';

    function createCursor() {
        state.dot = document.createElement('div');
        state.dot.className = 'marrison-cursor-dot';
        state.circle = document.createElement('div');
        state.circle.className = 'marrison-cursor-circle';

        state.dot.style.backgroundColor = marrison_cursor_settings.dot_color;
        state.circle.style.borderColor = marrison_cursor_settings.circle_color;

        body.appendChild(state.dot);
        body.appendChild(state.circle);

        if (marrison_cursor_settings.shape && marrison_cursor_settings.shape !== 'circle') {
            body.classList.add('marrison-cursor-' + marrison_cursor_settings.shape);
        }

        body.classList.add('marrison-cursor-active');
    }

    function setDisabledZone(target) {
        if (target && target.closest(disabledSelector)) {
            body.classList.add('marrison-cursor-disabled-zone');
            body.classList.remove('marrison-cursor-hover');
            state.circle.style.backgroundColor = 'transparent';
            state.circle.style.borderColor = marrison_cursor_settings.circle_color;
            return;
        }

        body.classList.remove('marrison-cursor-disabled-zone');
    }

    function setHoverState(target) {
        if (body.classList.contains('marrison-cursor-disabled-zone')) {
            body.classList.remove('marrison-cursor-hover');
            state.circle.style.backgroundColor = 'transparent';
            state.circle.style.borderColor = marrison_cursor_settings.circle_color;
            return;
        }

        if (target && target.closest(hoverSelector)) {
            body.classList.add('marrison-cursor-hover');
            state.circle.style.backgroundColor = marrison_cursor_settings.hover_color;
            state.circle.style.borderColor = 'transparent';
            return;
        }

        body.classList.remove('marrison-cursor-hover');
        state.circle.style.backgroundColor = 'transparent';
        state.circle.style.borderColor = marrison_cursor_settings.circle_color;
    }

    function handlePointerMove(event) {
        state.insideViewport = true;
        state.mouseX = event.clientX;
        state.mouseY = event.clientY;

        setDisabledZone(event.target);
        setHoverState(event.target);
        root.classList.add('marrison-cursor-visible');
    }

    function animate() {
        var animationType = marrison_cursor_settings.animation || 'lag';

        state.dotX += (state.mouseX - state.dotX) * 0.55;
        state.dotY += (state.mouseY - state.dotY) * 0.55;

        if (animationType === 'fast') {
            state.circleX += (state.mouseX - state.circleX) * 0.32;
            state.circleY += (state.mouseY - state.circleY) * 0.32;
        } else if (animationType === 'elastic') {
            state.velX += (state.mouseX - state.circleX) * 0.08;
            state.velY += (state.mouseY - state.circleY) * 0.08;
            state.velX *= 0.72;
            state.velY *= 0.72;
            state.circleX += state.velX;
            state.circleY += state.velY;
        } else {
            state.circleX += (state.mouseX - state.circleX) * 0.16;
            state.circleY += (state.mouseY - state.circleY) * 0.16;
        }

        var rotate = marrison_cursor_settings.shape === 'diamond' ? ' rotate(45deg)' : '';
        state.dot.style.transform = 'translate3d(' + state.dotX + 'px,' + state.dotY + 'px,0) translate(-50%, -50%)' + rotate;
        state.circle.style.transform = 'translate3d(' + state.circleX + 'px,' + state.circleY + 'px,0) translate(-50%, -50%)' + rotate;

        window.requestAnimationFrame(animate);
    }

    function hideCursor() {
        root.classList.remove('marrison-cursor-visible');
        body.classList.remove('marrison-cursor-hover');
        body.classList.remove('marrison-cursor-disabled-zone');
    }

    createCursor();

    document.addEventListener('pointermove', handlePointerMove, { passive: true });
    document.addEventListener('pointerleave', hideCursor, { passive: true });
    window.addEventListener('blur', hideCursor);
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            hideCursor();
        }
    });

    animate();
})();
