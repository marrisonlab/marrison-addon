<?php
/**
 * Template per le preferenze cookie
 */
$banner = Marrison_Cookie_Banner::get_instance();
$categories = $banner->get_cookie_categories();
$current_consent = isset($_COOKIE['marrison_cookie_consent']) ? sanitize_text_field($_COOKIE['marrison_cookie_consent']) : '';
?>

<div class="marrison-preferences-widget">
    <h3><?php _e('Preferenze Cookie', 'marrison-cookie'); ?></h3>
    
    <div class="marrison-preferences-content">
        <?php foreach ($categories as $key => $category): ?>
            <?php $checked_by_default = $category['required'] || $key === 'functional'; ?>
            <div class="marrison-pref-category">
                <div class="marrison-pref-header">
                    <div class="marrison-pref-info">
                        <h4><?php echo esc_html($category['name']); ?></h4>
                        <p><?php echo esc_html($category['description']); ?></p>
                    </div>
                    <label class="marrison-switch">
                        <input type="checkbox" 
                               class="marrison-pref-checkbox" 
                               data-category="<?php echo esc_attr($key); ?>"
                               <?php checked($checked_by_default, true); ?>
                               <?php disabled($category['required'], true); ?>>
                        <span class="marrison-slider"></span>
                    </label>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="marrison-preferences-actions">
        <button type="button" id="marrison-update-prefs" class="marrison-button marrison-button-update">
            <?php _e('Aggiorna Preferenze', 'marrison-cookie'); ?>
        </button>
    </div>
    
    <div id="marrison-prefs-message" class="marrison-message" style="display: none;"></div>
</div>
