<?php
/**
 * Template per il wizard di configurazione iniziale (Popup Modal)
 */
$wizard = Marrison_Setup_Wizard::get_instance();
$current_step = $wizard->get_current_step();
$total_steps = $wizard->get_total_steps();
?>

<!-- Overlay del wizard -->
<div class="marrison-wizard-overlay">
    <div class="marrison-wizard-wrapper">
        <div class="marrison-wizard-header">
            <button class="marrison-wizard-close" aria-label="<?php echo esc_attr__('Chiudi', 'marrison-cookie'); ?>">&times;</button>
            <h1><?php _e('Marrison Cookie Manager', 'marrison-cookie'); ?></h1>
            <p><?php _e('Configurazione guidata in pochi semplici passaggi', 'marrison-cookie'); ?></p>
        </div>
        
        <div class="marrison-wizard-progress">
            <div class="marrison-progress-bar">
                <?php for ($i = 1; $i <= $total_steps; $i++): ?>
                    <div class="marrison-progress-step <?php echo $i < $current_step ? 'completed' : ($i === $current_step ? 'active' : ''); ?>" data-step="<?php echo esc_attr($i); ?>">
                        <span class="marrison-progress-number"><?php echo esc_html($i); ?></span>
                        <span class="marrison-progress-label">
                            <?php
                            $labels = array(
                                1 => __('Benvenuto', 'marrison-cookie'),
                                2 => __('Banner', 'marrison-cookie'),
                                3 => __('Cookie', 'marrison-cookie'),
                                4 => __('Aspetto', 'marrison-cookie'),
                                5 => __('Pagine', 'marrison-cookie')
                            );
                            echo esc_html($labels[$i]);
                            ?>
                        </span>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
        
        <div class="marrison-wizard-content">
            <!-- Step 1: Benvenuto -->
            <div class="marrison-wizard-step <?php echo $current_step === 1 ? 'active' : ''; ?>" data-step="1">
                <h2 class="marrison-step-title"><?php _e('Benvenuto nel Wizard di Configurazione', 'marrison-cookie'); ?></h2>
                <p class="marrison-step-description">
                    <?php _e('Questo wizard ti guiderà attraverso la configurazione iniziale del plugin Marrison Cookie Manager. In pochi minuti potrai:', 'marrison-cookie'); ?>
                </p>
                <ul style="color: #666; line-height: 1.8; margin-bottom: 30px;">
                    <li><?php _e('Scansionare i cookie presenti sul tuo sito', 'marrison-cookie'); ?></li>
                    <li><?php _e('Personalizzare il banner di accettazione', 'marrison-cookie'); ?></li>
                    <li><?php _e('Categorizzare i cookie rilevati', 'marrison-cookie'); ?></li>
                    <li><?php _e('Creare automaticamente le pagine Privacy e Cookie Policy', 'marrison-cookie'); ?></li>
                </ul>
                <p class="marrison-step-description">
                    <?php _e('Il wizard può essere rieseguito in qualsiasi momento dalle impostazioni del plugin.', 'marrison-cookie'); ?>
                </p>
            </div>
            
            <!-- Step 2: Banner -->
            <div class="marrison-wizard-step <?php echo $current_step === 2 ? 'active' : ''; ?>" data-step="2">
                <h2 class="marrison-step-title"><?php _e('Personalizza il Banner', 'marrison-cookie'); ?></h2>
                <p class="marrison-step-description">
                    <?php _e('Configura il testo e i pulsanti del banner che apparirà ai visitatori del tuo sito.', 'marrison-cookie'); ?>
                </p>
                
                <div class="marrison-form-group">
                    <label for="wizard_banner_title"><?php _e('Titolo del Banner', 'marrison-cookie'); ?></label>
                    <input type="text" id="wizard_banner_title" value="<?php echo esc_attr(get_option('marrison_cookie_banner_title', 'Gestione Cookie')); ?>">
                </div>
                
                <div class="marrison-form-group">
                    <label for="wizard_banner_description"><?php _e('Descrizione del Banner', 'marrison-cookie'); ?></label>
                    <textarea id="wizard_banner_description" rows="4"><?php echo esc_textarea(get_option('marrison_cookie_banner_description', 'Utilizziamo i cookie per migliorare la tua esperienza.')); ?></textarea>
                </div>
                
                <div class="marrison-form-group">
                    <label for="wizard_accept_text"><?php _e('Testo pulsante "Accetta"', 'marrison-cookie'); ?></label>
                    <input type="text" id="wizard_accept_text" value="<?php echo esc_attr(get_option('marrison_cookie_accept_button_text', 'Accetta tutti')); ?>">
                </div>
                
                <div class="marrison-form-group">
                    <label for="wizard_reject_text"><?php _e('Testo pulsante "Rifiuta"', 'marrison-cookie'); ?></label>
                    <input type="text" id="wizard_reject_text" value="<?php echo esc_attr(get_option('marrison_cookie_reject_button_text', 'Rifiuta tutti')); ?>">
                </div>
                
                <div class="marrison-form-group">
                    <label for="wizard_customize_text"><?php _e('Testo pulsante "Personalizza"', 'marrison-cookie'); ?></label>
                    <input type="text" id="wizard_customize_text" value="<?php echo esc_attr(get_option('marrison_cookie_customize_button_text', 'Personalizza')); ?>">
                </div>
            </div>
            
            <!-- Step 3: Cookie -->
            <div class="marrison-wizard-step <?php echo $current_step === 3 ? 'active' : ''; ?>" data-step="3">
                <h2 class="marrison-step-title"><?php _e('Scansione e Categorizzazione Cookie', 'marrison-cookie'); ?></h2>
                <p class="marrison-step-description">
                    <?php _e('Scansiona i cookie presenti sul tuo sito e assegna loro le categorie appropriate.', 'marrison-cookie'); ?>
                </p>
                
                <button type="button" id="wizard_scan_button" class="marrison-scan-button">
                    <?php _e('Avvia Scansione Cookie', 'marrison-cookie'); ?>
                </button>
                
                <div id="wizard_scan_status" class="marrison-scan-status"></div>
                
                <div id="wizard_cookie_results" style="display: none;">
                    <h3><?php _e('Cookie Rilevati', 'marrison-cookie'); ?></h3>
                    <p><?php _e('Assegna una categoria a ogni cookie rilevato:', 'marrison-cookie'); ?></p>
                    
                    <table class="marrison-cookie-table">
                        <thead>
                            <tr>
                                <th><?php _e('Nome Cookie', 'marrison-cookie'); ?></th>
                                <th><?php _e('Categoria', 'marrison-cookie'); ?></th>
                                <th><?php _e('Fonte', 'marrison-cookie'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="wizard_cookie_table_body">
                            <tr>
                                <td colspan="3"><?php _e('Clicca su "Avvia Scansione" per rilevare i cookie', 'marrison-cookie'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Step 4: Aspetto -->
            <div class="marrison-wizard-step <?php echo $current_step === 4 ? 'active' : ''; ?>" data-step="4">
                <h2 class="marrison-step-title"><?php _e('Personalizza l\'Aspetto', 'marrison-cookie'); ?></h2>
                <p class="marrison-step-description">
                    <?php _e('Scegli i colori e la posizione del banner per adattarlo al design del tuo sito.', 'marrison-cookie'); ?>
                </p>
                
                <div class="marrison-form-group">
                    <label for="wizard_banner_layout"><?php _e('Formato Banner', 'marrison-cookie'); ?></label>
                    <select id="wizard_banner_layout">
                        <option value="bar" <?php selected(get_option('marrison_cookie_banner_layout', 'bar'), 'bar'); ?>>
                            <?php _e('Barra', 'marrison-cookie'); ?>
                        </option>
                        <option value="box" <?php selected(get_option('marrison_cookie_banner_layout', 'bar'), 'box'); ?>>
                            <?php _e('Box', 'marrison-cookie'); ?>
                        </option>
                    </select>
                </div>

                <div class="marrison-form-group">
                    <label for="wizard_banner_position"><?php _e('Posizione Barra', 'marrison-cookie'); ?></label>
                    <select id="wizard_banner_position">
                        <option value="bottom" <?php selected(get_option('marrison_cookie_banner_position', 'bottom'), 'bottom'); ?>>
                            <?php _e('In basso', 'marrison-cookie'); ?>
                        </option>
                        <option value="top" <?php selected(get_option('marrison_cookie_banner_position', 'bottom'), 'top'); ?>>
                            <?php _e('In alto', 'marrison-cookie'); ?>
                        </option>
                    </select>
                </div>

                <div class="marrison-form-group">
                    <label for="wizard_box_position"><?php _e('Posizione Box', 'marrison-cookie'); ?></label>
                    <select id="wizard_box_position">
                        <option value="top-left" <?php selected(get_option('marrison_cookie_box_position', 'bottom-right'), 'top-left'); ?>>
                            <?php _e('In alto a sinistra', 'marrison-cookie'); ?>
                        </option>
                        <option value="top-right" <?php selected(get_option('marrison_cookie_box_position', 'bottom-right'), 'top-right'); ?>>
                            <?php _e('In alto a destra', 'marrison-cookie'); ?>
                        </option>
                        <option value="bottom-left" <?php selected(get_option('marrison_cookie_box_position', 'bottom-right'), 'bottom-left'); ?>>
                            <?php _e('In basso a sinistra', 'marrison-cookie'); ?>
                        </option>
                        <option value="bottom-right" <?php selected(get_option('marrison_cookie_box_position', 'bottom-right'), 'bottom-right'); ?>>
                            <?php _e('In basso a destra', 'marrison-cookie'); ?>
                        </option>
                    </select>
                </div>
                
                <div class="marrison-form-group">
                    <label><?php _e('Colore Sfondo Banner', 'marrison-cookie'); ?></label>
                    <div class="marrison-color-picker-group">
                        <input type="color" id="wizard_banner_bg_color" value="<?php echo esc_attr(get_option('marrison_cookie_banner_background_color', '#ffffff')); ?>">
                        <span><?php echo esc_html(get_option('marrison_cookie_banner_background_color', '#ffffff')); ?></span>
                    </div>
                </div>
                
                <div class="marrison-form-group">
                    <label><?php _e('Colore Testo Banner', 'marrison-cookie'); ?></label>
                    <div class="marrison-color-picker-group">
                        <input type="color" id="wizard_banner_text_color" value="<?php echo esc_attr(get_option('marrison_cookie_banner_text_color', '#333333')); ?>">
                        <span><?php echo esc_html(get_option('marrison_cookie_banner_text_color', '#333333')); ?></span>
                    </div>
                </div>
                
                <div class="marrison-form-group">
                    <label><?php _e('Colore Bottoni', 'marrison-cookie'); ?></label>
                    <div class="marrison-color-picker-group">
                        <input type="color" id="wizard_button_bg_color" value="<?php echo esc_attr(get_option('marrison_cookie_button_background_color', '#0073aa')); ?>">
                        <span><?php echo esc_html(get_option('marrison_cookie_button_background_color', '#0073aa')); ?></span>
                    </div>
                </div>
                
                <div class="marrison-form-group">
                    <label><?php _e('Colore Testo Bottoni', 'marrison-cookie'); ?></label>
                    <div class="marrison-color-picker-group">
                        <input type="color" id="wizard_button_text_color" value="<?php echo esc_attr(get_option('marrison_cookie_button_text_color', '#ffffff')); ?>">
                        <span><?php echo esc_html(get_option('marrison_cookie_button_text_color', '#ffffff')); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Step 5: Pagine -->
            <div class="marrison-wizard-step <?php echo $current_step === 5 ? 'active' : ''; ?>" data-step="5">
                <h2 class="marrison-step-title"><?php _e('Crea Pagine Policy', 'marrison-cookie'); ?></h2>
                <p class="marrison-step-description">
                    <?php _e('Crea automaticamente le pagine Privacy Policy e Cookie Policy con contenuti precompilati.', 'marrison-cookie'); ?>
                </p>
                
                <div class="marrison-page-options">
                    <div class="marrison-page-option" id="privacy_option">
                        <input type="checkbox" id="create_privacy" checked>
                        <div>
                            <h4><?php _e('Privacy Policy', 'marrison-cookie'); ?></h4>
                            <p><?php _e('Crea una pagina Privacy Policy completa con tutti i requisiti GDPR.', 'marrison-cookie'); ?></p>
                        </div>
                    </div>
                    
                    <div class="marrison-page-option" id="cookie_option">
                        <input type="checkbox" id="create_cookie" checked>
                        <div>
                            <h4><?php _e('Cookie Policy', 'marrison-cookie'); ?></h4>
                            <p><?php _e('Crea una pagina Cookie Policy con spiegazioni dettagliate.', 'marrison-cookie'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div id="wizard_pages_status" class="marrison-scan-status" style="margin-top: 20px;"></div>
            </div>
            
            <!-- Step Completato -->
            <div class="marrison-wizard-step" data-step="completed">
                <div class="marrison-success-message">
                    <div class="marrison-success-icon">✓</div>
                    <h2><?php _e('Configurazione Completata!', 'marrison-cookie'); ?></h2>
                    <p>
                        <?php _e('Il plugin Marrison Cookie Manager è stato configurato con successo. Il banner cookie è ora attivo sul tuo sito.', 'marrison-cookie'); ?>
                    </p>
                    <p>
                        <?php _e('Puoi modificare tutte le impostazioni in qualsiasi momento dal menu "Impostazioni > Cookie Manager".', 'marrison-cookie'); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="marrison-wizard-footer">
            <div class="marrison-wizard-info">
                <span id="step_indicator"><?php printf(__('Step %d di %d', 'marrison-cookie'), $current_step, $total_steps); ?></span>
            </div>
            <div class="marrison-wizard-buttons">
                <button type="button" id="wizard_prev" class="marrison-btn marrison-btn-secondary" style="<?php echo $current_step === 1 ? 'display: none;' : ''; ?>">
                    <?php _e('Indietro', 'marrison-cookie'); ?>
                </button>
                <button type="button" id="wizard_next" class="marrison-btn marrison-btn-primary">
                    <?php echo $current_step === $total_steps ? __('Completa', 'marrison-cookie') : __('Avanti', 'marrison-cookie'); ?>
                </button>
            </div>
        </div>
    </div>
</div>
