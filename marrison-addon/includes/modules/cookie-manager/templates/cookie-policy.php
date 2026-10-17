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
                                <th scope="row"><?php echo esc_html($cookie->cookie_name); ?></th>
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
</section>
