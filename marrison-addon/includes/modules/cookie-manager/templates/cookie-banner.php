<?php
if (!defined('ABSPATH')) { exit; }
/**
 * Template per il banner cookie
 */
$banner_layout = in_array($banner_layout, array('bar', 'box'), true) ? $banner_layout : 'bar';
$banner_position = in_array($banner_position, array('top', 'bottom'), true) ? $banner_position : 'bottom';
$box_position = in_array($box_position, array('top-left', 'top-right', 'bottom-left', 'bottom-right'), true) ? $box_position : 'bottom-right';
$position_class = $banner_layout === 'box' ? 'marrison-box-' . $box_position : 'marrison-banner-' . $banner_position;
$layout_class = 'marrison-banner-layout-' . $banner_layout;
?>
<div id="marrison-cookie-banner" class="marrison-cookie-banner <?php echo esc_attr($layout_class); ?> <?php echo esc_attr($position_class); ?>" style="<?php if ($banner_hidden) { echo 'display: none; '; } ?>background-color: <?php echo esc_attr($banner_bg_color); ?>; color: <?php echo esc_attr($banner_text_color); ?>; --marrison-cookie-button-background: <?php echo esc_attr($button_bg_color); ?>;">
    <div class="marrison-banner-container">
        <div class="marrison-banner-content">
            <h3 class="marrison-banner-title"><?php echo esc_html($banner_title); ?></h3>
            <p class="marrison-banner-description"><?php echo esc_html($banner_description); ?></p>
            
            <?php if ($privacy_policy_url || $cookie_policy_url): ?>
                <div class="marrison-banner-links">
                    <?php if ($privacy_policy_url): ?>
                        <a href="<?php echo esc_url($privacy_policy_url); ?>" target="_blank" rel="noopener noreferrer" class="marrison-banner-link">
                            <?php _e('Privacy Policy', 'marrison-cookie'); ?>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($cookie_policy_url): ?>
                        <a href="<?php echo esc_url($cookie_policy_url); ?>" target="_blank" rel="noopener noreferrer" class="marrison-banner-link">
                            <?php _e('Cookie Policy', 'marrison-cookie'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="marrison-banner-buttons">
            <button type="button" id="marrison-accept-all" class="marrison-button marrison-button-accept" style="background-color: <?php echo esc_attr($button_bg_color); ?>; color: <?php echo esc_attr($button_text_color); ?>;">
                <?php echo esc_html($accept_button_text); ?>
            </button>
            
            <button type="button" id="marrison-reject-all" class="marrison-button marrison-button-reject" style="background-color: <?php echo esc_attr($button_bg_color); ?>; color: <?php echo esc_attr($button_text_color); ?>; border: 1px solid <?php echo esc_attr($button_bg_color); ?>;">
                <?php echo esc_html($reject_button_text); ?>
            </button>
            
            <button type="button" id="marrison-customize" class="marrison-button marrison-button-customize" aria-haspopup="dialog" aria-controls="marrison-cookie-dialog" style="background-color: <?php echo esc_attr($button_bg_color); ?>; color: <?php echo esc_attr($button_text_color); ?>; border: 1px solid <?php echo esc_attr($button_bg_color); ?>;">
                <?php echo esc_html($customize_button_text); ?>
            </button>
        </div>
        <p id="marrison-consent-status" role="status" aria-live="polite" style="width:100%;margin:4px 0"></p>
    </div>
    
    <!-- Modal per personalizzazione -->
    <div id="marrison-cookie-modal" class="marrison-modal" aria-hidden="true" style="display: none; --marrison-cookie-button-background: <?php echo esc_attr($button_bg_color); ?>;">
        <div id="marrison-cookie-dialog" class="marrison-modal-content" role="dialog" aria-modal="true" aria-labelledby="marrison-cookie-dialog-title" tabindex="-1" style="background-color: <?php echo esc_attr($banner_bg_color); ?>; color: <?php echo esc_attr($banner_text_color); ?>;">
            <div class="marrison-modal-header">
                <h3 id="marrison-cookie-dialog-title"><?php _e('Personalizza Cookie', 'marrison-cookie'); ?></h3>
                <button type="button" id="marrison-close-modal" class="marrison-close-button" aria-label="<?php echo esc_attr(marrison_cookie_site_text('Chiudi preferenze cookie', 'Close cookie preferences')); ?>">&times;</button>
            </div>
            
            <div class="marrison-modal-body">
                <?php 
                $banner = Marrison_Cookie_Banner::get_instance();
                $categories = $banner->get_cookie_categories();
                ?>
                
                <?php foreach ($categories as $key => $category): ?>
                    <?php $checked_by_default = $category['required']; ?>
                    <div class="marrison-cookie-category" data-category="<?php echo esc_attr($key); ?>">
                        <div class="marrison-category-header">
                            <div class="marrison-category-info">
                                <h4 id="marrison-cookie-category-<?php echo esc_attr($key); ?>"><?php echo esc_html($category['name']); ?></h4>
                                <p id="marrison-cookie-description-<?php echo esc_attr($key); ?>"><?php echo esc_html($category['description']); ?></p>
                                <?php if ($category['required']): ?><span class="marrison-category-required"><?php echo esc_html(marrison_cookie_site_text('Sempre attivi', 'Always active')); ?></span><?php endif; ?>
                            </div>
                            <label class="marrison-switch">
                                <input type="checkbox" 
                                       class="marrison-category-checkbox" 
                                       data-category="<?php echo esc_attr($key); ?>"
                                       aria-labelledby="marrison-cookie-category-<?php echo esc_attr($key); ?>"
                                       aria-describedby="marrison-cookie-description-<?php echo esc_attr($key); ?>"
                                       <?php checked($checked_by_default, true); ?>
                                       <?php disabled($category['required'], true); ?>>
                                <span class="marrison-slider"></span>
                            </label>
                        </div>
                        <div class="marrison-category-cookie-list" data-cookie-list="<?php echo esc_attr($key); ?>"></div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="marrison-modal-footer">
                <p id="marrison-modal-status" role="status" aria-live="polite"></p>
                <button type="button" id="marrison-save-preferences" class="marrison-button marrison-button-save" style="background-color: <?php echo esc_attr($button_bg_color); ?>; color: <?php echo esc_attr($button_text_color); ?>;">
                    <?php _e('Salva Preferenze', 'marrison-cookie'); ?>
                </button>
            </div>
        </div>
    </div>
</div>
