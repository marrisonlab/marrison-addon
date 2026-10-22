<?php
/** Provider definitions are declarations, never runtime cookie observations. */
class Marrison_Addon_Cookie_Scan_Services {
    public static function identify($resource) {
        $parts = wp_parse_url($resource);
        $host = strtolower($parts['host'] ?? '');
        $path = strtolower($parts['path'] ?? '/');
        $google = 'https://policies.google.com/technologies/cookies';
        $profiles = array();
        if (($host === 'maps.google.com' && strpos($path, '/maps') === 0)
            || (in_array($host, array('google.com', 'www.google.com'), true) && strpos($path, '/maps/embed') === 0)
            || ($host === 'maps.googleapis.com' && strpos($path, '/maps/api/') === 0)) {
            $profiles[] = array('id' => 'google-maps', 'name' => 'Google Maps', 'category' => 'marketing', 'source_url' => $google,
                'cookies' => array(array('name' => 'NID', 'domain' => '.google.com', 'duration' => '6 mesi', 'purpose' => 'Preferenze e personalizzazione dei servizi Google; dipende dalle scelte dell’utente.')));
        }
        if ($host === 'google-analytics.com' || substr($host, -21) === '.google-analytics.com'
            || (in_array($host, array('googletagmanager.com', 'www.googletagmanager.com'), true) && $path === '/gtag/js'
                && preg_match('/(?:^|&)id=(?:G-|UA-)/i', $parts['query'] ?? ''))) {
            $profiles[] = array('id' => 'google-analytics', 'name' => 'Google Analytics', 'category' => 'analytics',
                'source_url' => 'https://developers.google.com/analytics/devguides/collection/ga4/cookie-usage',
                'cookies' => array(array('name' => '_ga', 'domain' => 'Dominio del sito', 'duration' => '2 anni (configurabile)', 'purpose' => 'Distinzione degli utenti.'),
                    array('name' => '_ga_*', 'domain' => 'Dominio del sito', 'duration' => '2 anni (configurabile)', 'purpose' => 'Persistenza dello stato della sessione; il suffisso varia.')));
        }
        if (in_array($host, array('youtube.com', 'www.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'), true) && strpos($path, '/embed/') === 0) {
            $profiles[] = array('id' => 'youtube', 'name' => 'YouTube', 'category' => 'marketing', 'source_url' => $google,
                'cookies' => array(array('name' => 'YSC', 'domain' => '.youtube.com', 'duration' => 'Sessione', 'purpose' => 'Interazioni durante la visualizzazione dei video; può comparire dopo la riproduzione.'),
                    array('name' => 'PREF', 'domain' => '.youtube.com', 'duration' => '8 mesi', 'purpose' => 'Preferenze del lettore; dipende dall’utilizzo del servizio.')));
        }
        foreach ($profiles as &$profile) {
            foreach ($profile['cookies'] as &$cookie) { $cookie['evidence'] = 'provider_documentation'; $cookie['observed'] = false; }
            unset($cookie);
        }
        unset($profile);
        return $profiles;
    }
}
