<?php
if (!defined('ABSPATH')) { exit; }
/** Shared structured profile, rendered in settings and in the optional wizard step. */
$privacy_profile = Marrison_Addon_Cookie_Policy_Content::stored_profile();
$privacy_business = $privacy_profile['identity_source'] === 'business_info';
$privacy_prefix = isset($privacy_prefix) ? $privacy_prefix : 'privacy-profile-';
$privacy_groups = array();
foreach (Marrison_Addon_Cookie_Policy_Content::fields() as $key => $field) { $privacy_groups[$field['group']][$key] = $field; }
?>
<div class="marrison-privacy-profile">
    <p>Tutti i campi sono facoltativi. Compila quelli pertinenti al sito: lo shortcode mostra il testo usando i dati salvati, anche se lasci altri campi vuoti.</p>
    <?php foreach ($privacy_groups as $group => $fields): ?>
        <details class="marrison-profile-group" <?php if (in_array($group, array('Origine dei dati', 'Titolare e contatti', 'Moduli e richieste'), true)) { echo 'open'; } ?> <?php if ($group === 'Titolare e contatti') { echo 'data-privacy-manual' . ($privacy_business ? ' hidden' : ''); } ?>>
            <summary><strong><?php echo esc_html($group); ?></strong></summary>
            <?php foreach ($fields as $key => $field): $type = $field['type'] ?? 'text'; $id = $privacy_prefix . $key; ?>
                <div class="marrison-profile-field">
                    <?php if ($type === 'checkbox'): ?>
                        <input type="hidden" name="privacy_profile[<?php echo esc_attr($key); ?>]" value="0">
                        <label for="<?php echo esc_attr($id); ?>"><input data-privacy-field="<?php echo esc_attr($key); ?>" type="checkbox" id="<?php echo esc_attr($id); ?>" name="privacy_profile[<?php echo esc_attr($key); ?>]" value="1" <?php checked((bool) $privacy_profile[$key], true); ?>> <?php echo esc_html($field['label']); ?></label>
                    <?php else: ?>
                        <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($field['label']); ?></label>
                        <?php if ($type === 'select'): ?>
                            <select data-privacy-field="<?php echo esc_attr($key); ?>" id="<?php echo esc_attr($id); ?>" name="privacy_profile[<?php echo esc_attr($key); ?>]">
                                <?php foreach ($field['options'] as $value => $label): ?><option value="<?php echo esc_attr($value); ?>" <?php selected($privacy_profile[$key], $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
                            </select>
                        <?php elseif ($type === 'textarea'): ?>
                            <textarea data-privacy-field="<?php echo esc_attr($key); ?>" id="<?php echo esc_attr($id); ?>" name="privacy_profile[<?php echo esc_attr($key); ?>]" rows="3" maxlength="6000"><?php echo esc_textarea($privacy_profile[$key]); ?></textarea>
                        <?php else: ?>
                            <input data-privacy-field="<?php echo esc_attr($key); ?>" type="<?php echo esc_attr($type); ?>" id="<?php echo esc_attr($id); ?>" name="privacy_profile[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($privacy_profile[$key]); ?>" maxlength="1500">
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if ($group === 'Origine dei dati'): ?>
                <p>I dati manuali restano salvati quando cambi origine. Cookie Manager funziona anche senza il modulo Info Azienda.</p>
                <div data-privacy-business <?php if (!$privacy_business) { echo 'hidden'; } ?>>
                    <?php if (Marrison_Addon_Cookie_Policy_Content::business_available()): ?>
                        <p><span class="dashicons dashicons-shield" aria-hidden="true"></span> La policy legge i dati aggiornati da
                            <a href="<?php echo esc_url(admin_url('admin.php?page=marrison-business-info')); ?>">Info Azienda → Privacy e titolare</a>.</p>
                        <?php $business_missing = Marrison_Addon_Business_Info_Store::missing_privacy(); if ($business_missing): ?>
                            <div class="notice notice-warning inline"><p><strong>Dati anagrafici mancanti in Info Azienda:</strong> <?php echo esc_html(implode(', ', $business_missing)); ?>. Puoi comunque salvare e pubblicare la policy.</p></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="notice notice-warning inline"><p>Info Azienda non è attivo. Scegli “Inserisci qui (Cookie Manager)” per compilare i dati, oppure attiva il modulo Business Info.</p></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </details>
    <?php endforeach; ?>
</div>
