<?php
/** Dettagli dinamici della Cookie Policy; nessun valore dei cookie viene pubblicato. */
if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="marrison-cookie-policy-details">
    <p><a href="#marrison-cookie-preferences" class="marrison-open-cookie-preferences" aria-haspopup="dialog"><?php echo esc_html(marrison_cookie_site_text('Personalizza le preferenze cookie', 'Customize cookie preferences')); ?></a></p>
    <h2><?php echo esc_html(marrison_cookie_site_text('Cookie rilevati dalla scansione', 'Cookies detected by the scan')); ?></h2>
    <?php if (empty($cookies)): ?>
        <p><?php echo esc_html(marrison_cookie_site_text('Nessun cookie rilevato dalla scansione.', 'No cookies detected by the scan.')); ?></p>
    <?php else: ?>
        <?php if (array_filter($cookies, function($cookie) { return !empty($cookie->observation_stale); })): ?>
            <p><?php echo esc_html(marrison_cookie_site_text('Alcune osservazioni risalgono a oltre 30 giorni fa. Restano elencate in attesa di una nuova scansione.', 'Some observations are over 30 days old. They remain listed until a new scan.')); ?></p>
        <?php endif; ?>
        <?php foreach ($cookies_by_category as $category_key => $category_cookies): ?>
            <?php if (empty($category_cookies)) { continue; } ?>
            <h3><?php echo esc_html($categories[$category_key]['name']); ?></h3>
            <div class="marrison-cookie-policy-table-wrap">
                <table class="marrison-cookie-policy-table">
                    <caption><?php echo esc_html(marrison_cookie_site_text('Cookie rilevati: ', 'Detected cookies: ') . $categories[$category_key]['name']); ?></caption>
                    <thead><tr>
                        <th scope="col">Cookie</th>
                        <th scope="col"><?php echo esc_html(marrison_cookie_site_text('Dominio', 'Domain')); ?></th>
                        <th scope="col"><?php echo esc_html(marrison_cookie_site_text('Scadenza rilevata', 'Detected expiration')); ?></th>
                        <th scope="col"><?php echo esc_html(marrison_cookie_site_text('Descrizione', 'Description')); ?></th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($category_cookies as $cookie): ?>
                            <tr>
                                <th scope="row"><?php echo esc_html($cookie->cookie_name); ?><?php if (!empty($cookie->observation_stale)): ?><br><small><?php echo esc_html(marrison_cookie_site_text('Osservazione da aggiornare', 'Observation to refresh')); ?></small><?php endif; ?></th>
                                <?php foreach (array('cookie_domain', 'cookie_expiration', 'cookie_description') as $field): ?>
                                    <td><?php echo esc_html($field === 'cookie_expiration' ? $this->get_cookie_expiration_label($cookie) : (!empty($cookie->$field) ? $cookie->$field : marrison_cookie_site_text('Non disponibile', 'Not available'))); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php $services = class_exists('Marrison_Addon_Cookie_Scan_Engine', false) ? Marrison_Addon_Cookie_Scan_Engine::get_instance()->service_inventory() : array(); $profiles = array(); ?>
    <?php foreach ($services as $service) { foreach ($service['profiles'] ?? array() as $key => $profile) { $profiles[$key] = $profile; } } ?>
    <?php if ($profiles): ?>
        <h3><?php echo esc_html(marrison_cookie_site_text('Servizi rilevati e cookie documentati', 'Detected services and documented cookies')); ?></h3>
        <p><?php echo esc_html(marrison_cookie_site_text('I seguenti servizi sono stati riconosciuti nelle pagine analizzate. I cookie elencati sono documentati dai fornitori: la loro installazione dipende dal consenso, dall’utilizzo e dal browser e non è attestata dalla scansione.', 'The following services were identified in the tested pages. Listed cookies are documented by their providers: their installation depends on consent, usage and the browser and is not confirmed by this scan.')); ?></p>
        <?php foreach ($profiles as $profile): ?>
            <h4><?php echo esc_html($profile['name']); ?></h4>
            <ul><?php foreach ($profile['cookies'] as $cookie): ?><li><strong><?php echo esc_html($cookie['name']); ?></strong> — <?php echo esc_html($cookie['domain'] . ' · ' . $cookie['duration'] . '. ' . $cookie['purpose']); ?></li><?php endforeach; ?></ul>
            <p><a href="<?php echo esc_url($profile['source_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(marrison_cookie_site_text('Documentazione del fornitore', 'Provider documentation')); ?></a></p>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
