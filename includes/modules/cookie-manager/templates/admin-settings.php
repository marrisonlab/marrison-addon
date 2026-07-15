<?php
/**
 * Template per la pagina delle impostazioni admin
 */
$settings = Marrison_Cookie_Admin_Settings::get_instance()->get_settings();
$scanner = Marrison_Cookie_Scanner::get_instance();
$categories = $scanner->get_categories();
?>

<div class="wrap marrison-cookie-settings">
    <div class="marrison-admin-header">
        <div class="marrison-admin-title">
            <h1><?php _e('Marrison Cookie Manager', 'marrison-cookie'); ?></h1>
            <p><?php _e('Gestisci i cookie e il banner di consenso del tuo sito WordPress.', 'marrison-cookie'); ?></p>
        </div>
        <button type="button" id="marrison-open-wizard" class="marrison-wizard-launch-btn">
            <?php _e('Avvia Wizard', 'marrison-cookie'); ?>
        </button>
    </div>
    
    <h2 class="nav-tab-wrapper marrison-nav-tabs">
        <a href="<?php echo esc_url(admin_url('admin.php?page=marrison-cookie&tab=banner')); ?>" class="nav-tab <?php echo $active_tab === 'banner' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Banner', 'marrison-cookie'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=marrison-cookie&tab=appearance')); ?>" class="nav-tab <?php echo $active_tab === 'appearance' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Aspetto', 'marrison-cookie'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=marrison-cookie&tab=behavior')); ?>" class="nav-tab <?php echo $active_tab === 'behavior' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Comportamento', 'marrison-cookie'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=marrison-cookie&tab=scanner')); ?>" class="nav-tab <?php echo $active_tab === 'scanner' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Scanner Cookie', 'marrison-cookie'); ?>
        </a>
    </h2>
    
    <form method="post" action="">
        <?php wp_nonce_field('marrison_cookie_settings_nonce'); ?>
        
        <?php if ($active_tab === 'banner'): ?>
            <div class="marrison-card">
                <div class="marrison-card-header">
                    <h2><?php _e('Impostazioni Banner', 'marrison-cookie'); ?></h2>
                    <p><?php _e('Personalizza testi e link del banner cookie.', 'marrison-cookie'); ?></p>
                </div>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="banner_title"><?php _e('Titolo Banner', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="banner_title" 
                                   name="banner_title" 
                                   value="<?php echo esc_attr($settings['banner_title']); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="banner_description"><?php _e('Descrizione Banner', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <textarea id="banner_description" 
                                      name="banner_description" 
                                      rows="4" 
                                      class="large-text"><?php echo esc_textarea($settings['banner_description']); ?></textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="accept_button_text"><?php _e('Testo Bottone Accetta', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="accept_button_text" 
                                   name="accept_button_text" 
                                   value="<?php echo esc_attr($settings['accept_button_text']); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="reject_button_text"><?php _e('Testo Bottone Rifiuta', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="reject_button_text" 
                                   name="reject_button_text" 
                                   value="<?php echo esc_attr($settings['reject_button_text']); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="customize_button_text"><?php _e('Testo Bottone Personalizza', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="customize_button_text" 
                                   name="customize_button_text" 
                                   value="<?php echo esc_attr($settings['customize_button_text']); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="privacy_policy_url"><?php _e('URL Privacy Policy', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="privacy_policy_url" 
                                   name="privacy_policy_url" 
                                   value="<?php echo esc_url($settings['privacy_policy_url']); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="cookie_policy_url"><?php _e('URL Cookie Policy', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="cookie_policy_url" 
                                   name="cookie_policy_url" 
                                   value="<?php echo esc_url($settings['cookie_policy_url']); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                </table>
            </div>
            
        <?php elseif ($active_tab === 'appearance'): ?>
            <div class="marrison-card">
                <div class="marrison-card-header">
                    <h2><?php _e('Impostazioni Aspetto', 'marrison-cookie'); ?></h2>
                    <p><?php _e('Scegli colori e posizione del banner.', 'marrison-cookie'); ?></p>
                </div>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="banner_layout"><?php _e('Formato Banner', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <select id="banner_layout" name="banner_layout">
                                <option value="bar" <?php selected($settings['banner_layout'], 'bar'); ?>>
                                    <?php _e('Barra', 'marrison-cookie'); ?>
                                </option>
                                <option value="box" <?php selected($settings['banner_layout'], 'box'); ?>>
                                    <?php _e('Box', 'marrison-cookie'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="banner_position"><?php _e('Posizione Barra', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <select id="banner_position" name="banner_position">
                                <option value="top" <?php selected($settings['banner_position'], 'top'); ?>>
                                    <?php _e('In alto', 'marrison-cookie'); ?>
                                </option>
                                <option value="bottom" <?php selected($settings['banner_position'], 'bottom'); ?>>
                                    <?php _e('In basso', 'marrison-cookie'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="box_position"><?php _e('Posizione Box', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <select id="box_position" name="box_position">
                                <option value="top-left" <?php selected($settings['box_position'], 'top-left'); ?>>
                                    <?php _e('In alto a sinistra', 'marrison-cookie'); ?>
                                </option>
                                <option value="top-right" <?php selected($settings['box_position'], 'top-right'); ?>>
                                    <?php _e('In alto a destra', 'marrison-cookie'); ?>
                                </option>
                                <option value="bottom-left" <?php selected($settings['box_position'], 'bottom-left'); ?>>
                                    <?php _e('In basso a sinistra', 'marrison-cookie'); ?>
                                </option>
                                <option value="bottom-right" <?php selected($settings['box_position'], 'bottom-right'); ?>>
                                    <?php _e('In basso a destra', 'marrison-cookie'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="banner_background_color"><?php _e('Colore Sfondo Banner', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <input type="color" 
                                   id="banner_background_color" 
                                   name="banner_background_color" 
                                   value="<?php echo esc_attr($settings['banner_background_color']); ?>">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="banner_text_color"><?php _e('Colore Testo Banner', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <input type="color" 
                                   id="banner_text_color" 
                                   name="banner_text_color" 
                                   value="<?php echo esc_attr($settings['banner_text_color']); ?>">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="button_background_color"><?php _e('Colore Sfondo Bottoni', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <input type="color" 
                                   id="button_background_color" 
                                   name="button_background_color" 
                                   value="<?php echo esc_attr($settings['button_background_color']); ?>">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="button_text_color"><?php _e('Colore Testo Bottoni', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <input type="color" 
                                   id="button_text_color" 
                                   name="button_text_color" 
                                   value="<?php echo esc_attr($settings['button_text_color']); ?>">
                        </td>
                    </tr>
                </table>
            </div>
            
        <?php elseif ($active_tab === 'behavior'): ?>
            <div class="marrison-card">
                <div class="marrison-card-header">
                    <h2><?php _e('Impostazioni Comportamento', 'marrison-cookie'); ?></h2>
                    <p><?php _e('Configura durata consenso e scansione automatica.', 'marrison-cookie'); ?></p>
                </div>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="show_banner"><?php _e('Mostra Banner', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <label class="marrison-admin-switch">
                                <input type="checkbox"
                                       id="show_banner"
                                       name="show_banner"
                                       value="1"
                                       <?php checked($settings['show_banner'], true); ?>>
                                <span class="marrison-admin-switch-slider"></span>
                                <span class="marrison-admin-switch-label"><?php _e('Attiva banner cookie', 'marrison-cookie'); ?></span>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="consent_duration"><?php _e('Durata Consenso (giorni)', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="consent_duration" 
                                   name="consent_duration" 
                                   value="<?php echo esc_attr($settings['consent_duration']); ?>" 
                                   min="1" 
                                   max="365">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="auto_scan"><?php _e('Scansione Automatica', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <label class="marrison-admin-switch">
                                <input type="checkbox"
                                       id="auto_scan"
                                       name="auto_scan"
                                       value="1"
                                       <?php checked($settings['auto_scan'], true); ?>>
                                <span class="marrison-admin-switch-slider"></span>
                                <span class="marrison-admin-switch-label"><?php _e('Attiva scansione automatica dei cookie', 'marrison-cookie'); ?></span>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="scan_interval"><?php _e('Intervallo Scansione (giorni)', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="scan_interval" 
                                   name="scan_interval" 
                                   value="<?php echo esc_attr($settings['scan_interval']); ?>" 
                                   min="1" 
                                   max="30">
                        </td>
                    </tr>
                </table>
            </div>
            
        <?php elseif ($active_tab === 'scanner'): ?>
            <div class="marrison-card">
                <div class="marrison-card-header">
                    <h2><?php _e('Scanner Cookie', 'marrison-cookie'); ?></h2>
                    <p><?php _e('Scansiona e categorizza i cookie del tuo sito.', 'marrison-cookie'); ?></p>
                </div>
                
                <div class="marrison-scanner-controls">
                    <button type="button" id="marrison-scan-cookies" class="button button-primary">
                        <?php _e('Avvia Scansione', 'marrison-cookie'); ?>
                    </button>
                    
                    <span id="marrison-scan-status"></span>
                </div>
                
                <div class="marrison-scanner-filters">
                    <label for="marrison-category-filter"><?php _e('Filtra per categoria:', 'marrison-cookie'); ?></label>
                    <select id="marrison-category-filter">
                        <option value="all"><?php _e('Tutte', 'marrison-cookie'); ?></option>
                        <?php foreach ($categories as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="marrison-cookie-list">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Nome Cookie', 'marrison-cookie'); ?></th>
                                <th><?php _e('Categoria', 'marrison-cookie'); ?></th>
                                <th><?php _e('Dominio', 'marrison-cookie'); ?></th>
                                <th><?php _e('Fonte', 'marrison-cookie'); ?></th>
                                <th><?php _e('Data Scansione', 'marrison-cookie'); ?></th>
                                <th><?php _e('Azioni', 'marrison-cookie'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="marrison-cookie-table-body">
                            <tr>
                                <td colspan="6"><?php _e('Clicca su "Avvia Scansione" per vedere i cookie', 'marrison-cookie'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($active_tab !== 'scanner'): ?>
            <p class="submit">
                <input type="submit" 
                       name="marrison_save_settings" 
                       class="button button-primary" 
                       value="<?php echo esc_attr__('Salva Impostazioni', 'marrison-cookie'); ?>">
            </p>
        <?php endif; ?>
    </form>
</div>
