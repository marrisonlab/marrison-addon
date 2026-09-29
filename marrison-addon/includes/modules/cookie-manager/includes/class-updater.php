<?php
/**
 * GitHub updater per Marrison Cookie Manager.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Marrison_Cookie_Updater {

    private static $instance = null;
    private $plugin_slug = 'marrison-cookie';
    private $github_user = 'marrisonlab';
    private $github_repo = 'marrison-cookie';
    private $github_api_url = 'https://api.github.com/repos/marrisonlab/marrison-cookie';

    /**
     * Ottieni istanza singleton.
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Costruttore.
     */
    private function __construct() {
        add_action('admin_init', array($this, 'init_hooks'));
        add_action('upgrader_process_complete', array($this, 'clean_cache_after_update'), 10, 2);
    }

    /**
     * Inizializza i filtri dell'updater solo in admin.
     */
    public function init_hooks() {
        add_filter('site_transient_update_plugins', array($this, 'check_update'), 999);
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
        add_filter('upgrader_source_selection', array($this, 'fix_github_source_folder'), 10, 4);
    }

    /**
     * Inietta l'aggiornamento GitHub nel transient nativo dei plugin.
     */
    public function check_update($transient) {
        if (!is_admin()) {
            return $transient;
        }

        if (!is_object($transient)) {
            $transient = new stdClass();
        }

        if (!isset($transient->response) || !is_array($transient->response)) {
            $transient->response = array();
        }

        if (!isset($transient->no_update) || !is_array($transient->no_update)) {
            $transient->no_update = array();
        }

        if (!isset($transient->checked) || !is_array($transient->checked)) {
            $transient->checked = array();
        }

        $plugin_file = MARRISON_COOKIE_PLUGIN_BASENAME;
        $installed = !empty($transient->checked[$plugin_file]) ? $transient->checked[$plugin_file] : MARRISON_COOKIE_VERSION;
        $release = $this->get_latest_release();

        if (!$release || empty($release['version'])) {
            $transient->checked[$plugin_file] = $installed;
            return $transient;
        }

        $item = (object) array(
            'id'            => $this->plugin_slug,
            'slug'          => $this->plugin_slug,
            'plugin'        => $plugin_file,
            'new_version'   => $release['version'],
            'url'           => $release['url'],
            'package'       => $release['download_url'],
            'tested'        => '6.9',
            'requires'      => '5.0',
            'requires_php'  => '7.0',
            'icons'         => array(),
            'banners'       => array(),
            'banners_rtl'   => array(),
            'compatibility' => new stdClass(),
        );

        unset($transient->response[$plugin_file], $transient->no_update[$plugin_file]);

        if (version_compare($installed, $release['version'], '<')) {
            $transient->response[$plugin_file] = $item;
        } else {
            $transient->no_update[$plugin_file] = $item;
        }

        $transient->checked[$plugin_file] = $installed;

        return $transient;
    }

    /**
     * Mostra i dettagli del plugin nel modal "Vedi dettagli".
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== $this->plugin_slug) {
            return $result;
        }

        $release = $this->get_latest_release();
        $version = $release && !empty($release['version']) ? $release['version'] : MARRISON_COOKIE_VERSION;
        $homepage = 'https://github.com/' . $this->github_user . '/' . $this->github_repo;

        return (object) array(
            'name'             => 'Marrison Cookie Manager',
            'slug'             => $this->plugin_slug,
            'version'          => $version,
            'author'           => '<a href="https://marrisonlab.com">MarrisonLab</a>',
            'author_profile'   => 'https://marrisonlab.com',
            'homepage'         => $homepage,
            'plugin_url'       => $homepage,
            'download_url'     => $release && !empty($release['download_url']) ? $release['download_url'] : $this->build_download_url('v' . $version),
            'requires'         => '5.0',
            'requires_php'     => '7.0',
            'tested'           => '6.9',
            'last_updated'     => $release && !empty($release['published_at']) ? $release['published_at'] : current_time('mysql'),
            'sections'         => array(
                'description' => '<p>Plugin WordPress per la gestione dei cookie con scansione automatica e banner di consenso personalizzabile.</p>',
                'changelog'   => !empty($release['body']) ? wpautop(esc_html($release['body'])) : '<p>Consulta il repository GitHub per il changelog completo.</p>',
            ),
            'active_installs'  => 0,
            'rating'           => 100,
            'ratings'          => array(5 => 100),
            'num_ratings'      => 0,
            'support_url'      => $homepage . '/issues',
        );
    }

    /**
     * Recupera la release GitHub più recente e usa il tag come versione.
     */
    private function get_latest_release() {
        $cached = get_transient('marrison_cookie_github_release');
        if ($cached !== false) {
            return $cached;
        }

        if (get_transient('marrison_cookie_github_fetch_failed') !== false) {
            return false;
        }

        $headers = array(
            'Accept'     => 'application/vnd.github.v3+json',
            'User-Agent' => 'WordPress/MarrisonCookieManager',
        );

        $response = wp_remote_get($this->github_api_url . '/releases/latest', array(
            'timeout' => 10,
            'headers' => $headers,
        ));

        $code = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);
        $body = null;

        if (!is_wp_error($response) && $code === 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
        }

        if (empty($body['tag_name'])) {
            $body = $this->get_highest_release_from_list($headers);
        }

        if (empty($body['tag_name'])) {
            $body = $this->get_highest_tag_from_list($headers);
        }

        if (empty($body['tag_name'])) {
            set_transient('marrison_cookie_github_fetch_failed', 1, 5 * MINUTE_IN_SECONDS);
            return false;
        }

        $tag = sanitize_text_field($body['tag_name']);
        $version = ltrim($tag, 'vV');

        if (!preg_match('/^\d+(?:\.\d+){1,3}(?:[-+][0-9A-Za-z.-]+)?$/', $version)) {
            set_transient('marrison_cookie_github_fetch_failed', 1, 5 * MINUTE_IN_SECONDS);
            return false;
        }

        $release = array(
            'tag_name'     => $tag,
            'version'      => $version,
            'url'          => !empty($body['html_url']) ? esc_url_raw($body['html_url']) : 'https://github.com/' . $this->github_user . '/' . $this->github_repo,
            'download_url' => $this->build_download_url($tag),
            'published_at' => !empty($body['published_at']) ? sanitize_text_field($body['published_at']) : '',
            'body'         => !empty($body['body']) ? (string) $body['body'] : '',
        );

        set_transient('marrison_cookie_github_release', $release, 6 * HOUR_IN_SECONDS);

        return $release;
    }

    /**
     * Fallback: sceglie la release non draft con versione più alta.
     */
    private function get_highest_release_from_list($headers) {
        $response = wp_remote_get($this->github_api_url . '/releases?per_page=10', array(
            'timeout' => 10,
            'headers' => $headers,
        ));

        $code = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);
        if (is_wp_error($response) || $code !== 200) {
            return null;
        }

        $releases = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($releases)) {
            return null;
        }

        $best = null;
        foreach ($releases as $release) {
            if (!is_array($release) || empty($release['tag_name']) || !empty($release['draft'])) {
                continue;
            }

            $version = ltrim((string) $release['tag_name'], 'vV');
            if ($best === null || version_compare($version, ltrim((string) $best['tag_name'], 'vV'), '>')) {
                $best = $release;
            }
        }

        return $best;
    }

    /**
     * Fallback ulteriore: usa i tag del repository quando non esistono release GitHub.
     */
    private function get_highest_tag_from_list($headers) {
        $response = wp_remote_get($this->github_api_url . '/tags?per_page=10', array(
            'timeout' => 10,
            'headers' => $headers,
        ));

        $code = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);
        if (is_wp_error($response) || $code !== 200) {
            return null;
        }

        $tags = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($tags)) {
            return null;
        }

        $best = null;
        foreach ($tags as $tag) {
            if (!is_array($tag) || empty($tag['name'])) {
                continue;
            }

            $version = ltrim((string) $tag['name'], 'vV');
            if (!preg_match('/^\d+(?:\.\d+){1,3}(?:[-+][0-9A-Za-z.-]+)?$/', $version)) {
                continue;
            }

            if ($best === null || version_compare($version, ltrim((string) $best['tag_name'], 'vV'), '>')) {
                $best = array(
                    'tag_name' => (string) $tag['name'],
                    'html_url' => 'https://github.com/' . $this->github_user . '/' . $this->github_repo . '/releases/tag/' . rawurlencode((string) $tag['name']),
                    'published_at' => '',
                    'body' => '',
                );
            }
        }

        return $best;
    }

    /**
     * Usa il tag come nel Marrison Custom Updater: v{version}.zip.
     */
    private function build_download_url($tag) {
        return 'https://github.com/' . $this->github_user . '/' . $this->github_repo . '/archive/refs/tags/' . rawurlencode($tag) . '.zip';
    }

    /**
     * Mantiene stabile la cartella del plugin quando GitHub aggiunge il suffisso del tag.
     */
    public function fix_github_source_folder($source, $remote_source, $upgrader, $hook_extra = null) {
        if (!$this->is_current_plugin_upgrade($hook_extra)) {
            return $source;
        }

        if (basename(untrailingslashit($source)) === $this->plugin_slug) {
            return $source;
        }

        global $wp_filesystem;
        if (!$wp_filesystem || empty($remote_source)) {
            return $source;
        }

        $destination = trailingslashit($remote_source) . $this->plugin_slug;
        if ($wp_filesystem->exists($destination)) {
            $wp_filesystem->delete($destination, true);
        }

        if ($wp_filesystem->move($source, $destination, true)) {
            return $destination;
        }

        return $source;
    }

    /**
     * Verifica che l'upgrade corrente riguardi questo plugin.
     */
    private function is_current_plugin_upgrade($hook_extra) {
        if (!is_array($hook_extra)) {
            return false;
        }

        if (!empty($hook_extra['plugin'])) {
            return $hook_extra['plugin'] === MARRISON_COOKIE_PLUGIN_BASENAME;
        }

        if (!empty($hook_extra['plugins']) && is_array($hook_extra['plugins'])) {
            return in_array(MARRISON_COOKIE_PLUGIN_BASENAME, $hook_extra['plugins'], true);
        }

        return false;
    }

    /**
     * Pulisce la cache interna solo dopo update completato.
     */
    public function clean_cache_after_update($upgrader, $options) {
        if (empty($options['type']) || $options['type'] !== 'plugin') {
            return;
        }

        $plugins = array();
        if (!empty($options['plugins']) && is_array($options['plugins'])) {
            $plugins = $options['plugins'];
        } elseif (!empty($options['plugin'])) {
            $plugins = array($options['plugin']);
        }

        if (in_array(MARRISON_COOKIE_PLUGIN_BASENAME, $plugins, true)) {
            delete_transient('marrison_cookie_github_release');
            delete_transient('marrison_cookie_github_fetch_failed');
        }
    }
}
