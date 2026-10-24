<?php
/** Catalog classification uses the existing protected settings form. */
if (!defined('ABSPATH')) { exit; }
$service_catalog = Marrison_Addon_Cookie_Scan_Services::catalog();
$discovered_hosts = Marrison_Addon_Cookie_Scan_Services::discovered_hosts();
?>
<div class="marrison-card">
    <div class="marrison-card-header">
        <h2><?php esc_html_e('Catalogo servizi e classificazione', 'marrison-cookie'); ?></h2>
        <p><?php esc_html_e('Le schede descrivono possibili cookie, non cookie osservati. La classificazione di Stripe, WooPayments, PayPal e Klarna va confermata dal consulente privacy. Le regole per dominio o percorso hanno precedenza sulla categoria del catalogo.', 'marrison-cookie'); ?></p>
    </div>
    <?php if (Marrison_Addon_Cookie_Scan_Services::catalog_error()): ?>
        <p role="alert"><?php esc_html_e('Catalogo non disponibile o non valido. Ripristina i file del plugin; nessuna dichiarazione viene dedotta dal file danneggiato.', 'marrison-cookie'); ?></p>
    <?php endif; ?>
    <table class="form-table">
    <?php foreach ($service_catalog as $service_profile): ?>
        <tr>
            <th scope="row"><label for="service-<?php echo esc_attr($service_profile['id']); ?>"><?php echo esc_html($service_profile['name']); ?></label></th>
            <td>
                <select id="service-<?php echo esc_attr($service_profile['id']); ?>" name="service_categories[<?php echo esc_attr($service_profile['id']); ?>]">
                    <?php foreach ($categories as $category_id => $category_label): ?>
                    <option value="<?php echo esc_attr($category_id); ?>" <?php selected(Marrison_Addon_Cookie_Scan_Services::category($service_profile), $category_id); ?>><?php echo esc_html($category_label); ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="marrison-category-badge marrison-category-<?php echo $service_profile['verified'] ? 'functional' : 'uncategorized'; ?>"><?php echo esc_html($service_profile['verified'] ? __('Fonte verificata', 'marrison-cookie') : __('Da verificare', 'marrison-cookie')); ?></span>
                <a href="<?php echo esc_url($service_profile['source_url']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Documentazione ufficiale', 'marrison-cookie'); ?></a>
                <p class="description"><?php echo esc_html(($service_profile['notes'] ?? '') . ' Revisione: ' . $service_profile['last_reviewed']); ?></p>
                <details><summary><?php esc_html_e('Cookie dichiarati dal catalogo', 'marrison-cookie'); ?></summary>
                    <?php if (!$service_profile['cookies']): ?><p><?php esc_html_e('Nessun cookie dichiarato. Consulta le note del servizio.', 'marrison-cookie'); ?></p><?php endif; ?>
                    <ul><?php foreach ($service_profile['cookies'] as $declared_cookie): ?>
                        <li><?php echo esc_html($declared_cookie['name'] . ' · ' . $declared_cookie['domain'] . ' · ' . $declared_cookie['duration'] . ' · ' . $declared_cookie['purpose']); ?></li>
                    <?php endforeach; ?></ul>
                    <p><a href="<?php echo esc_url($service_profile['provider']['privacy_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($service_profile['provider']['name']); ?></a></p>
                </details>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php foreach ($discovered_hosts as $service_host => $host_category): $host_key = Marrison_Addon_Cookie_Scan_Services::host_id($service_host); ?>
        <tr>
            <th scope="row"><label for="service-<?php echo esc_attr($host_key); ?>"><?php echo esc_html($service_host); ?></label></th>
            <td>
                <select id="service-<?php echo esc_attr($host_key); ?>" name="service_categories[<?php echo esc_attr($host_key); ?>]">
                    <option value="" <?php selected($host_category, 'unclassified'); ?>><?php esc_html_e('Servizio non classificato — bloccato', 'marrison-cookie'); ?></option>
                    <?php foreach ($categories as $category_id => $category_label): ?>
                    <option value="<?php echo esc_attr($category_id); ?>" <?php selected($host_category, $category_id); ?>><?php echo esc_html($category_label); ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="marrison-category-badge marrison-category-uncategorized"><?php esc_html_e('Da verificare', 'marrison-cookie'); ?></span>
                <p class="description"><?php esc_html_e('Cookie e finalità non sono dedotti dall’host. Il servizio resta bloccato anche dopo Accetta tutti finché non viene classificato.', 'marrison-cookie'); ?></p>
            </td>
        </tr>
    <?php endforeach; ?>
    </table>
</div>
