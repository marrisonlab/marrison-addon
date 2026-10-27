<?php
/** Upgrade regression: restore the legacy state without bypassing runtime integrity checks. */
ob_start(); require __DIR__ . '/php-snippets-php-test.php'; ob_end_clean();
$mode = $argv[1] ?? '';
if ( $mode === 'file_edit' ) { define( 'DISALLOW_FILE_EDIT', true ); }
if ( $mode === 'file_mods' ) { define( 'DISALLOW_FILE_MODS', true ); }
$legacy = function( $id, $active, $error = '' ) {
    return array_merge( snippet_row( $id, '$GLOBALS["trace"][]="restored";', 'everywhere', $active ), [ 'signature' => '', 'last_error' => $error ] );
};
$wpdb->rows = [
    1 => $legacy( 1, 1 ),
    2 => $legacy( 2, 0, $store::SIGNATURE_SUSPENSION ),
    3 => $legacy( 3, 0 ),
    4 => $legacy( 4, 0, 'Errore PHP reale: funzione inesistente.' ),
    5 => array_merge( snippet_row( 5, 'return;' ), [ 'code' => '$GLOBALS["trace"][]="changed";' ] ),
    6 => array_merge( $legacy( 6, 0, $store::SIGNATURE_SUSPENSION ), [ 'code' => '$broken = ;' ] ),
];
$GLOBALS['fixture_options'] = $GLOBALS['fixture_transients'] = [];
$suspended = run_child( 'retry', [ 2 => $legacy( 2, 1 ) ] );
check( 0 === $suspended['status'] && ! $suspended['stored'][2]['active'] && $store::INTEGRITY_SUSPENSION === $suspended['stored'][2]['last_error'], 'Could not reproduce the upgrade suspension.' );
$wpdb->rows[2] = $suspended['stored'][2];
$wpdb->rows[8] = $legacy( 8, 0, $store::SIGNATURE_SUSPENSION );
$original = $wpdb->rows;
$GLOBALS['caps']['unfiltered_html'] = false;
check( is_wp_error( $store->restore_legacy_snippets() ) && $original === $wpdb->rows, 'Unauthorized migration changed snippets.' );
$GLOBALS['caps']['unfiltered_html'] = true;
define( 'WP_CONTENT_DIR', sys_get_temp_dir() . '/marrison-snippet-recovery-' . bin2hex( random_bytes( 6 ) ) );
try {
    mkdir( WP_CONTENT_DIR . '/uploads', 0777, true );
    touch( WP_CONTENT_DIR . '/uploads/marrison-snippets-off' );
    check( true === $store->restore_legacy_snippets() && $original === $wpdb->rows && ! get_option( $store::COMPATIBILITY_OPTION ), 'Emergency safe mode did not defer migration.' );
} finally {
    unlink( WP_CONTENT_DIR . '/uploads/marrison-snippets-off' ); rmdir( WP_CONTENT_DIR . '/uploads' ); rmdir( WP_CONTENT_DIR );
}
if ( $mode === 'file_mods' ) {
    check( true === $store->restore_legacy_snippets() && $original === $wpdb->rows && ! get_option( $store::COMPATIBILITY_OPTION ), 'Full modifications lock did not defer migration.' );
    echo "PASS snippet compatibility: full modifications lock retained\n";
    exit;
}
$wpdb->fail = true;
check( is_wp_error( $store->restore_legacy_snippets() ) && ! get_option( $store::COMPATIBILITY_OPTION ), 'Failed migration was marked complete.' );
$wpdb->fail = false;
// An ordinary authenticated admin request repairs the upgrade without a special signing action.
$_GET = [ 'page' => 'unrelated' ]; $_SERVER['REQUEST_METHOD'] = 'GET';
$admin->handle_request();
check( get_option( $store::COMPATIBILITY_OPTION ), 'Normal admin request did not run the migration.' );
check( $wpdb->rows[1]['active'] && Marrison_Addon_PHP_Snippets_Security::verify( $wpdb->rows[1] ), 'Legacy active snippet was not retained and signed.' );
check( $wpdb->rows[2]['active'] && '' === $wpdb->rows[2]['last_error'] && Marrison_Addon_PHP_Snippets_Security::verify( $wpdb->rows[2] ), 'Signature-only suspension was not repaired.' );
check( $wpdb->rows[8]['active'] && Marrison_Addon_PHP_Snippets_Security::verify( $wpdb->rows[8] ), 'The previous release signature-only suspension was not repaired.' );
check( ! $wpdb->rows[3]['active'] && ! $wpdb->rows[4]['active'] && $wpdb->rows[4]['last_error'] === $original[4]['last_error'], 'Manual deactivation or real PHP quarantine was reversed.' );
check( $wpdb->rows[5] === $original[5] && $wpdb->rows[6] === $original[6], 'Changed signed code or invalid PHP was automatically approved.' );
foreach ( [1, 2] as $id ) {
    $executed = run_child( 'retry', [ $id => $wpdb->rows[$id] ] );
    check( 0 === $executed['status'] && [ 'restored' ] === $executed['out']['trace'], 'Restored snippet did not actually execute.' );
}
$migrated = $wpdb->rows;
check( true === $store->restore_legacy_snippets() && $migrated === $wpdb->rows, 'Migration is not idempotent.' );
$wpdb->rows[7] = $legacy( 7, 1 );
check( true === $store->restore_legacy_snippets() && '' === $wpdb->rows[7]['signature'], 'Later unsigned database writes were automatically signed.' );
$rejected = run_child( 'retry', [ 7 => $wpdb->rows[7] ] );
check( 0 === $rejected['status'] && [] === $rejected['out']['trace'] && ! $rejected['stored'][7]['active'], 'Later unsigned code executed.' );
$row = $wpdb->rows[3];
check( 3 === post_action( $admin, 'activate', 3, [ 'revision' => $row['revision'] ] ) && Marrison_Addon_PHP_Snippets_Security::verify( $wpdb->rows[3] ), 'Normal authenticated activation did not sign the selected code.' );
echo 'PASS snippet compatibility: ' . ( $mode ?: 'normal' ) . "; legacy active/suspended execution, manual and PHP-error states retained, permissions, retries, one-time migration, subsequent integrity checks\n";
