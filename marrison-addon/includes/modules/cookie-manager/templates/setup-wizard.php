<?php
if (!defined('ABSPATH')) { exit; }
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
                                5 => __('Privacy', 'marrison-cookie'),
                                6 => __('Shortcode', 'marrison-cookie')
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
                    <li><?php echo esc_html(marrison_cookie_site_text('Inserire Privacy e Cookie Policy nelle tue pagine tramite shortcode', 'Add Privacy and Cookie Policies to your pages using shortcodes')); ?></li>
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
                    <?php _e('Avvia l’analisi locale dei servizi. Mantieni aperto il pannello fino al termine oppure riprendi in seguito. I cookie del catalogo sono dichiarati, non osservati.', 'marrison-cookie'); ?>
                </p>
                
                <button type="button" id="wizard_scan_button" class="marrison-scan-button">
                    <?php _e('Avvia analisi statica', 'marrison-cookie'); ?>
                </button>
                
                <button type="button" id="wizard_scan_browser_button" class="button"><?php _e('Analisi statica + verifica browser', 'marrison-cookie'); ?></button>
                <p>
                    <label for="wizard_scan_limit"><?php _e('Limite campioni browser', 'marrison-cookie'); ?></label>
                    <input id="wizard_scan_limit" type="number" min="1" max="10" value="3" class="small-text">
                    <label for="wizard_scan_seconds"><?php _e('Tempo massimo browser (secondi)', 'marrison-cookie'); ?></label>
                    <input id="wizard_scan_seconds" type="number" min="10" max="120" value="<?php echo esc_attr(get_option('marrison_cookie_local_max_seconds', 30)); ?>" class="small-text">
                </p>
                <p><?php _e('La verifica browser richiede Chrome o Edge aggiornato e il pannello aperto; non vede i cookie di terze parti. Ogni nuovo avvio sostituisce il vecchio inventario.', 'marrison-cookie'); ?></p>
                <div id="wizard_scan_status" class="marrison-scan-status"></div>
                <div><h3><?php esc_html_e('Dati del browser', 'marrison-cookie'); ?></h3><p id="wizard_browser_storage_note"><?php esc_html_e('LocalStorage e sessionStorage sono elencati separatamente dai cookie, senza raccoglierne i valori.', 'marrison-cookie'); ?></p>
                    <table class="widefat"><thead><tr><th><?php esc_html_e('Chiave', 'marrison-cookie'); ?></th><th><?php esc_html_e('Tipo', 'marrison-cookie'); ?></th><th><?php esc_html_e('Origine', 'marrison-cookie'); ?></th><th><?php esc_html_e('Rilevazione', 'marrison-cookie'); ?></th></tr></thead><tbody id="wizard_browser_storage_rows"></tbody></table>
                </div>
                
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
            
            <!-- Step 5: Shortcode e collegamenti policy -->
            <div class="marrison-wizard-step <?php echo $current_step === 5 ? 'active' : ''; ?>" data-step="5">
                <h2 class="marrison-step-title">Genera la Privacy Policy</h2>
                <p class="marrison-step-description">Passaggio facoltativo. Puoi compilare i dati ora oppure scegliere Salta e completarli in Privacy e policy.</p>
                <?php $privacy_prefix = 'wizard-privacy-'; include MARRISON_COOKIE_PLUGIN_DIR . 'templates/privacy-fields.php'; ?>
            </div>
            <div class="marrison-wizard-step <?php echo $current_step === 6 ? 'active' : ''; ?>" data-step="6">
                <h2 class="marrison-step-title"><?php echo esc_html(marrison_cookie_site_text('Shortcode delle policy', 'Policy shortcodes')); ?></h2>
                <p class="marrison-step-description">
                    <?php echo esc_html(marrison_cookie_site_text('Scegli dove mostrare le policy nelle pagine del tuo sito.', 'Choose where to display the policies on your site pages.')); ?>
                </p>

                <?php include MARRISON_COOKIE_PLUGIN_DIR . 'templates/policy-shortcodes.php'; ?>
                <p><?php echo esc_html(marrison_cookie_site_text('Indica gli URL delle pagine da collegare nel banner. Puoi aggiungerli anche in seguito nelle impostazioni.', 'Enter the page URLs to link in the banner. You can also add them later in settings.')); ?></p>
                <div class="marrison-form-group">
                    <label for="wizard_privacy_policy_url">URL Privacy Policy</label>
                    <input type="url" id="wizard_privacy_policy_url" value="<?php echo esc_attr(get_option('marrison_cookie_privacy_policy_url', '')); ?>">
                </div>
                <div class="marrison-form-group">
                    <label for="wizard_cookie_policy_url">URL Cookie Policy</label>
                    <input type="url" id="wizard_cookie_policy_url" value="<?php echo esc_attr(get_option('marrison_cookie_cookie_policy_url', '')); ?>">
                </div>
                
                <div id="wizard_policy_status" class="marrison-scan-status" role="status" style="margin-top: 20px;"></div>
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
                <button type="button" id="wizard_skip_privacy" class="marrison-btn marrison-btn-secondary" style="<?php echo $current_step === 5 ? '' : 'display:none;'; ?>">Salta</button>
                <button type="button" id="wizard_next" class="marrison-btn marrison-btn-primary">
                    <?php echo $current_step === $total_steps ? __('Completa', 'marrison-cookie') : __('Avanti', 'marrison-cookie'); ?>
                </button>
            </div>
        </div>
    </div>
</div>
