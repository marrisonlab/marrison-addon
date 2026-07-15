<?php
/**
 * Template per il banner cookie
 */
$banner_layout = in_array($banner_layout, array('bar', 'box'), true) ? $banner_layout : 'bar';
$banner_position = in_array($banner_position, array('top', 'bottom'), true) ? $banner_position : 'bottom';
$box_position = in_array($box_position, array('top-left', 'top-right', 'bottom-left', 'bottom-right'), true) ? $box_position : 'bottom-right';
$position_class = $banner_layout === 'box' ? 'marrison-box-' . $box_position : 'marrison-banner-' . $banner_position;
$layout_class = 'marrison-banner-layout-' . $banner_layout;
?>
<div id="marrison-cookie-banner" class="marrison-cookie-banner <?php echo esc_attr($layout_class); ?> <?php echo esc_attr($position_class); ?>" style="background-color: <?php echo esc_attr($banner_bg_color); ?>; color: <?php echo esc_attr($banner_text_color); ?>;">
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
            
            <button type="button" id="marrison-reject-all" class="marrison-button marrison-button-reject" style="background-color: <?php echo esc_attr($button_bg_color); ?>; color: <?php echo esc_attr($button_text_color); ?>;">
                <?php echo esc_html($reject_button_text); ?>
            </button>
            
            <button type="button" id="marrison-customize" class="marrison-button marrison-button-customize" style="background-color: <?php echo esc_attr($button_bg_color); ?>; color: <?php echo esc_attr($button_text_color); ?>;">
                <?php echo esc_html($customize_button_text); ?>
            </button>
        </div>
    </div>
    
    <!-- Modal per personalizzazione -->
    <div id="marrison-cookie-modal" class="marrison-modal" style="display: none;">
        <div class="marrison-modal-content" style="background-color: <?php echo esc_attr($banner_bg_color); ?>; color: <?php echo esc_attr($banner_text_color); ?>;">
            <div class="marrison-modal-header">
                <h3><?php _e('Personalizza Cookie', 'marrison-cookie'); ?></h3>
                <button type="button" id="marrison-close-modal" class="marrison-close-button">&times;</button>
            </div>
            
            <div class="marrison-modal-body">
                <?php 
                $banner = Marrison_Cookie_Banner::get_instance();
                $categories = $banner->get_cookie_categories();
                ?>
                
                <?php foreach ($categories as $key => $category): ?>
                    <?php $checked_by_default = $category['required'] || $key === 'functional'; ?>
                    <div class="marrison-cookie-category" data-category="<?php echo esc_attr($key); ?>">
                        <div class="marrison-category-header">
                            <div class="marrison-category-info">
                                <h4><?php echo esc_html($category['name']); ?></h4>
                                <p><?php echo esc_html($category['description']); ?></p>
                            </div>
                            <label class="marrison-switch">
                                <input type="checkbox" 
                                       class="marrison-category-checkbox" 
                                       data-category="<?php echo esc_attr($key); ?>"
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
                <button type="button" id="marrison-save-preferences" class="marrison-button marrison-button-save" style="background-color: <?php echo esc_attr($button_bg_color); ?>; color: <?php echo esc_attr($button_text_color); ?>;">
                    <?php _e('Salva Preferenze', 'marrison-cookie'); ?>
                </button>
            </div>
        </div>
    </div>
</div>
