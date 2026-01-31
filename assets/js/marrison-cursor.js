(function($) {
    'use strict';

    const cursor = {
        init: function() {
            if (!marrison_cursor_settings) return;

            this.settings = marrison_cursor_settings;
            this.createCursor();
            this.bindEvents();
            this.animate();
        },

        createCursor: function() {
            this.dot = $('<div class="marrison-cursor-dot"></div>').appendTo('body');
            this.circle = $('<div class="marrison-cursor-circle"></div>').appendTo('body');

            // Apply settings
            if (this.settings.dot_color) {
                this.dot.css('background-color', this.settings.dot_color);
            }
            if (this.settings.circle_color) {
                this.circle.css('border-color', this.settings.circle_color);
            }

            // Apply shape class to body
            if (this.settings.shape && this.settings.shape !== 'circle') {
                $('body').addClass('marrison-cursor-' + this.settings.shape);
            }
            
            // State
            this.mouseX = 0;
            this.mouseY = 0;
            this.circleX = 0;
            this.circleY = 0;
            this.dotX = 0;
            this.dotY = 0;
            
            // For elastic effect
            this.velX = 0;
            this.velY = 0;
            
            $('body').addClass('marrison-cursor-active');
        },

        bindEvents: function() {
            const self = this;

            $(document).on('mousemove', function(e) {
                self.mouseX = e.clientX;
                self.mouseY = e.clientY;
            });

            // Hover effects
            const hoverSelectors = 'a, button, input[type="submit"], input[type="button"], .elementor-button, [role="button"]';
            
            $(document).on('mouseenter', hoverSelectors, function() {
                $('body').addClass('marrison-cursor-hover');
                if (self.settings.hover_color) {
                    self.circle.css('background-color', self.settings.hover_color);
                    self.circle.css('border-color', 'transparent');
                }
            }).on('mouseleave', hoverSelectors, function() {
                $('body').removeClass('marrison-cursor-hover');
                if (self.settings.circle_color) {
                    self.circle.css('border-color', self.settings.circle_color);
                    self.circle.css('background-color', 'transparent');
                }
            });
        },

        animate: function() {
            const self = this;
            const animationType = this.settings.animation || 'lag';
            
            // Dot follows instantly (or very fast)
            this.dotX += (this.mouseX - this.dotX) * 1;
            this.dotY += (this.mouseY - this.dotY) * 1;
            
            // Circle animation logic based on type
            if (animationType === 'fast') {
                // Fast follow (almost instant)
                this.circleX += (this.mouseX - this.circleX) * 0.5;
                this.circleY += (this.mouseY - this.circleY) * 0.5;
            } else if (animationType === 'elastic') {
                // Elastic / Spring physics
                const tension = 0.08;
                const friction = 0.75;
                
                const targetX = this.mouseX;
                const targetY = this.mouseY;
                
                this.velX += (targetX - this.circleX) * tension;
                this.velY += (targetY - this.circleY) * tension;
                
                this.velX *= friction;
                this.velY *= friction;
                
                this.circleX += this.velX;
                this.circleY += this.velY;
            } else {
                // Standard Lag (default)
                this.circleX += (this.mouseX - this.circleX) * 0.15;
                this.circleY += (this.mouseY - this.circleY) * 0.15;
            }

            // Rotate logic for diamond shape
            let rotate = '';
            if (this.settings.shape === 'diamond') {
                 rotate = ' rotate(45deg)';
            }

            this.dot.css('transform', `translate3d(${this.dotX}px, ${this.dotY}px, 0) translate(-50%, -50%)${rotate}`);
            this.circle.css('transform', `translate3d(${this.circleX}px, ${this.circleY}px, 0) translate(-50%, -50%)${rotate}`);

            requestAnimationFrame(function() {
                self.animate();
            });
        }
    };

    $(document).ready(function() {
        // Only init on non-touch or if forced (though CSS handles hiding)
        if (window.matchMedia("(pointer: fine)").matches) {
            cursor.init();
        }
    });

})(jQuery);
