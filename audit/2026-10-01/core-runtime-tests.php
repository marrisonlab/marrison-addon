<?php
ob_start();
require __DIR__ . '/wp-runtime-probe.php';
ob_end_clean();
$tests = [];
$fixture_root = __DIR__ . '/updater-fixture-' . bin2hex(random_bytes(3));
mkdir($fixture_root);
$source = $fixture_root . '/marrison-addon-1.3.43/';
mkdir($source);
mkdir($source . 'marrison-addon');
file_put_contents($source . 'marrison-addon/marrison-addon.php', "<?php\n/* Plugin Name: Marrison Addon */\n");
require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
$GLOBALS['wp_filesystem'] = new WP_Filesystem_Direct(null);
$updater = new Marrison_Addon_Updater(Marrison_Addon::plugin_file(), 'marrisonlab', 'marrison-addon');
$selected = $updater->fix_folder_name($source, $fixture_root . '/', null, ['plugin'=>'marrison-addon/marrison-addon.php']);
$tests['updater_github_archive_layout'] = [
    'returned_path'=>$selected,
    'main_file_at_selected_root'=>file_exists($selected . 'marrison-addon.php'),
    'main_file_one_level_deeper'=>file_exists($selected . 'marrison-addon/marrison-addon.php'),
    'expected'=>'Main plugin file should be present at the selected source root.',
];
$tests['context'] = [];
foreach (['public'=>[], 'editor'=>['action'=>'elementor'], 'preview'=>['elementor-preview'=>'94']] as $context=>$flags) {
    $_REQUEST = $flags;
    $tests['context'][$context] = Marrison_Addon_Context::is_public_frontend_request();
}
$_REQUEST = [];
$tests['pages'] = [];
foreach (get_posts(['post_type'=>'page', 'post_status'=>['publish','draft'], 'numberposts'=>30]) as $page) {
    $data = get_post_meta($page->ID, '_elementor_data', true);
    $tests['pages'][] = ['id'=>$page->ID, 'title'=>$page->post_title, 'status'=>$page->post_status,
        'url'=>get_permalink($page->ID), 'elementor_bytes'=>strlen((string)$data)];
}
$tests['runtime_plugin_errors'] = $GLOBALS['audit_errors'];
file_put_contents(__DIR__ . '/core-runtime-results.json', wp_json_encode($tests, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo wp_json_encode($tests, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
