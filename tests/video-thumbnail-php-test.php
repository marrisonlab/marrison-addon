<?php
/** Local fixtures: php tests/video-thumbnail-php-test.php; repeat with -d disable_functions=exec. */
error_reporting( E_ALL );
ini_set( 'display_errors', '1' );
define( 'ABSPATH', sys_get_temp_dir() . '/mvt-wp-fixture/' );
$GLOBALS['mvt_settings'] = array( 'local_video_enabled' => true, 'ffmpeg_path' => sys_get_temp_dir() . '/mvt-missing-' . uniqid() );
$GLOBALS['mvt_meta'] = array();
$GLOBALS['mvt_reads'] = 0;
$GLOBALS['mvt_mime'] = 'video/mp4';
$GLOBALS['mvt_readable'] = false;
function add_action() {}
function __( $text ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $text ) { return esc_html( $text ); }
function esc_attr( $text ) { return esc_html( $text ); }
function esc_html_e( $text ) { echo esc_html( $text ); }
function current_user_can() { return true; }
function get_option() { return $GLOBALS['mvt_settings']; }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
function sanitize_text_field( $text ) { return trim( (string) $text ); }
function wp_unslash( $value ) { return $value; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
function apply_filters( $hook, $value ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
function get_post_mime_type() { return $GLOBALS['mvt_mime']; }
function get_post_meta( $id, $key ) { return $GLOBALS['mvt_meta'][ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['mvt_meta'][ $id ][ $key ] = $value; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['mvt_meta'][ $id ][ $key ] ); }
function get_attached_file() {
    $GLOBALS['mvt_reads']++;
    if ( $GLOBALS['mvt_readable'] ) { throw new RuntimeException( 'generation-pipeline-reached' ); }
    return sys_get_temp_dir() . '/mvt-unreadable-' . uniqid();
}
function wp_strip_all_tags( $text ) { return strip_tags( $text ); }
function settings_fields() {}
function checked( $value, $expected = true ) { if ( $value == $expected ) { echo 'checked="checked"'; } }
function disabled( $value, $expected = true ) { if ( $value == $expected ) { echo 'disabled="disabled"'; } }
function selected( $value, $expected ) { if ( $value == $expected ) { echo 'selected="selected"'; } }
function submit_button( $label ) { echo '<button>' . esc_html( $label ) . '</button>'; }
require dirname( __DIR__ ) . '/marrison-addon/includes/modules/class-marrison-addon-video-thumbnail.php';
$checks = 0;
function mvt_check( $condition, $message ) {
    global $checks;
    if ( ! $condition ) { throw new RuntimeException( $message ); }
    $checks++;
}
function mvt_status( $module ) {
    $method = new ReflectionMethod( $module, 'get_ffmpeg_status' );
    if ( PHP_VERSION_ID < 80100 ) { $method->setAccessible( true ); }
    return $method->invoke( $module );
}
function mvt_html( $module ) {
    ob_start();
    try { $module->render_admin_page(); return ob_get_contents(); }
    finally { ob_end_clean(); }
}
$module = new Marrison_Addon_Video_Thumbnail();
$status = mvt_status( $module );
mvt_check( ! $status['available'], 'Missing binary or disabled exec must be unavailable.' );
mvt_check( false !== strpos( $status['message'], 'YouTube restano disponibili' ), 'Warning must explain the available workflow.' );
mvt_check( false === stripos( $status['message'], 'installa' ), 'Optional dependency must not require a server installation.' );
$html = mvt_html( $module );
mvt_check( false !== strpos( $html, 'mvt-ffmpeg-status warning' ), 'Unavailable capability must be a warning.' );
mvt_check( ! preg_match( '/class="mvt-ffmpeg-status[^\"]*error/', $html ), 'Unavailable capability must not be rendered as an error.' );
mvt_check( preg_match( '/<input type="checkbox"[^>]*local_video_enabled[^>]*disabled="disabled"/', $html ), 'Unavailable automatic generation must be disabled.' );
mvt_check( ! preg_match( '/<input type="checkbox"[^>]*local_video_enabled[^>]*checked="checked"/', $html ), 'Control must reflect effective availability.' );
mvt_check( preg_match( '/<input type="hidden"[^>]*local_video_enabled[^>]*value="1"/', $html ), 'Saving other settings must preserve the stored enabled preference.' );
mvt_check( false !== strpos( $html, 'id="mvt-fetch"' ) && false !== strpos( $html, 'id="mvt-url"' ), 'YouTube controls must remain available.' );
$module->maybe_generate_local_video_cover( 10 );
mvt_check( 0 === $GLOBALS['mvt_reads'], 'Unavailable generation must stop before touching the uploaded video.' );
mvt_check( empty( $GLOBALS['mvt_meta'] ), 'Unavailable optional capability must not create an attachment failure.' );
$GLOBALS['mvt_settings']['local_video_enabled'] = false;
$html = mvt_html( $module );
mvt_check( ! preg_match( '/<input type="hidden"[^>]*local_video_enabled/', $html ), 'A disabled stored preference must not be enabled by a hidden input.' );
$module->maybe_generate_local_video_cover( 11 );
mvt_check( 0 === $GLOBALS['mvt_reads'], 'Disabled generation must leave uploads untouched.' );
if ( ! function_exists( 'exec' ) ) {
    mvt_check( false !== strpos( $status['message'], 'non permette di eseguire' ), 'Disabled exec must have a truthful capability warning.' );
    echo "Video Thumbnail: $checks checks passed (exec disabled).\n";
    exit;
}
$fixture = sys_get_temp_dir() . '/mvt-ffmpeg-' . uniqid() . ( 'Windows' === PHP_OS_FAMILY ? '.cmd' : '.sh' );
function mvt_binary_fixture( $path, $message, $exit_code ) {
    $contents = 'Windows' === PHP_OS_FAMILY
        ? "@echo off\r\necho $message\r\nexit /b $exit_code\r\n"
        : "#!/bin/sh\nprintf '%s\\n' '$message'\nexit $exit_code\n";
    file_put_contents( $path, $contents );
    if ( 'Windows' !== PHP_OS_FAMILY ) { chmod( $path, 0700 ); }
}
try {
    $GLOBALS['mvt_settings']['ffmpeg_path'] = $fixture;
    $GLOBALS['mvt_settings']['local_video_enabled'] = true;
    mvt_binary_fixture( $fixture, 'Unrelated application', 0 );
    mvt_check( ! mvt_status( $module )['available'], 'Exit zero from an unrelated program must not claim FFmpeg availability.' );
    mvt_binary_fixture( $fixture, 'ffmpeg version local-fixture', 1 );
    mvt_check( ! mvt_status( $module )['available'], 'A failed version command must be unavailable.' );
    mvt_binary_fixture( $fixture, 'ffmpeg version local-fixture', 0 );
    mvt_check( mvt_status( $module )['available'], 'A successful FFmpeg version fixture must be available.' );
    $html = mvt_html( $module );
    mvt_check( false !== strpos( $html, 'mvt-ffmpeg-status success' ), 'Available capability must retain a success status.' );
    mvt_check( preg_match( '/<input type="checkbox"[^>]*local_video_enabled[^>]*checked="checked"/', $html ), 'Enabled generation must be checked when available.' );
    mvt_check( ! preg_match( '/<input type="checkbox"[^>]*local_video_enabled[^>]*disabled="disabled"/', $html ), 'Available generation must remain editable.' );
    mvt_check( ! preg_match( '/<input type="hidden"[^>]*local_video_enabled/', $html ), 'Available generation must allow the admin to turn it off.' );
    $module->maybe_generate_local_video_cover( 12 );
    mvt_check( 'File video non leggibile.' === get_post_meta( 12, '_mvt_local_cover_error' ), 'Real input failures must retain their existing diagnostics.' );
    $GLOBALS['mvt_readable'] = true;
    try { $module->maybe_generate_local_video_cover( 13 ); }
    catch ( RuntimeException $error ) { mvt_check( 'generation-pipeline-reached' === $error->getMessage(), 'Available generation must reach the existing upload pipeline.' ); }
    mvt_check( 2 === $GLOBALS['mvt_reads'], 'Available FFmpeg must preserve the existing input path.' );
    $GLOBALS['mvt_meta'][14]['_mvt_local_cover_id'] = 100;
    $module->maybe_generate_local_video_cover( 14 );
    mvt_check( 2 === $GLOBALS['mvt_reads'], 'Existing covers must not be generated again.' );
    $GLOBALS['mvt_mime'] = 'image/jpeg';
    $module->maybe_generate_local_video_cover( 15 );
    mvt_check( 2 === $GLOBALS['mvt_reads'], 'Other uploads must remain unaffected.' );
} finally {
    if ( is_file( $fixture ) ) { unlink( $fixture ); }
}
echo "Video Thumbnail: $checks checks passed (local binary fixtures, no real frame extraction).\n";
