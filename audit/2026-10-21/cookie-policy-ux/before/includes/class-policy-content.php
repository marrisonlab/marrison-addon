<?php
/** Configured policy content and stable evidence of the information offered to visitors. */
final class Marrison_Addon_Cookie_Policy_Content {
    public static function snapshot() {
        $snapshot = array('schema' => 1, 'revision_label' => (string) get_option('marrison_cookie_policy_revision', '1'));
        foreach (array('privacy_policy_content', 'cookie_policy_content', 'privacy_policy_url', 'cookie_policy_url', 'banner_title', 'banner_description', 'functional_sources', 'analytics_sources', 'marketing_sources') as $key) {
            $snapshot[$key] = (string) get_option('marrison_cookie_' . $key, '');
        }
        $snapshot['local_pages'] = array();
        foreach (array('privacy_policy_url', 'cookie_policy_url') as $key) {
            $url = $snapshot[$key];
            $id = $url && function_exists('url_to_postid') ? url_to_postid($url) : 0;
            $post = $id && function_exists('get_post') ? get_post($id) : null;
            if ($post && (!isset($post->post_status) || $post->post_status === 'publish')) {
                $snapshot['local_pages'][$key] = array('id' => (int) $id, 'content' => (string) $post->post_content,
                    'elementor' => function_exists('get_post_meta') ? (string) get_post_meta($id, '_elementor_data', true) : '');
            }
        }
        return $snapshot;
    }

    public static function revision($snapshot = null) {
        return hash('sha256', wp_json_encode($snapshot === null ? self::snapshot() : $snapshot, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    public static function consent_version($snapshot = null) {
        $snapshot = $snapshot === null ? self::snapshot() : $snapshot;
        // The controller changes the revision for significant processing changes.
        // Formatting/layout corrections alone must not solicit a new choice.
        $contract = array('revision_label' => $snapshot['revision_label']);
        foreach (array('functional_sources', 'analytics_sources', 'marketing_sources') as $key) {
            $rules = array_values(array_unique(array_filter(array_map('trim', preg_split('/[\r\n]+/', strtolower($snapshot[$key]))))));
            sort($rules, SORT_STRING);
            $contract[$key] = $rules;
        }
        return hash('sha256', wp_json_encode($contract, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    public static function content($kind) {
        $key = $kind === 'privacy' ? 'privacy_policy_content' : 'cookie_policy_content';
        $content = (string) get_option('marrison_cookie_' . $key, '');
        // Policy text cannot recursively invoke itself or run arbitrary shortcodes.
        $content = str_replace(array('[marrison_privacy_policy]', '[marrison_cookie_policy_content]', '[marrison_cookie_policy]'), '', $content);
        if (trim($content) === '') {
            if ($kind === 'cookie') { return '<h1>Cookie Policy</h1>'; }
            return '<h1>Privacy Policy</h1><p>' . esc_html(marrison_cookie_site_text('L’informativa non è stata ancora configurata dal titolare del sito.', 'The website controller has not configured this notice yet.')) . '</p>';
        }
        return wpautop(wp_kses_post($content));
    }
}
