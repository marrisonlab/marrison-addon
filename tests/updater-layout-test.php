<?php
/** Regression for both distribution layouts and filesystem failures. */
define( 'ABSPATH', __DIR__ . '/' );
function add_action() {}
function add_filter() {}
function plugin_basename( $file ) { return 'marrison-addon/marrison-addon.php'; }
function trailingslashit( $path ) { return rtrim( $path, '/\\' ) . '/'; }
function __( $text ) { return $text; }
class WP_Error {
    public $code;
    public function __construct( $code, $message ) { $this->code = $code; }
}
class Layout_Filesystem {
    public $files = [];
    public $collision = false;
    public $fail = false;
    public $moves = [];
    public function is_file( $path ) { return isset( $this->files[$path] ); }
    public function exists( $path ) { return $this->collision; }
    public function move( $source, $destination ) {
        $this->moves[] = [$source, $destination];
        if ( $this->fail ) { return false; }
        foreach ( $this->files as $path => $value ) {
            if ( strpos( $path, $source ) === 0 ) {
                $this->files[$destination . substr( $path, strlen( $source ) )] = $value;
                unset( $this->files[$path] );
            }
        }
        return true;
    }
}
require __DIR__ . '/../marrison-addon/includes/class-marrison-addon-updater.php';
$updater = new Marrison_Addon_Updater( '/plugin.php', 'test', 'test' );
$cases = [
    'github' => ['/unpack/repo-1/', '/unpack/repo-1/marrison-addon/marrison-addon.php', false, false, false],
    'release' => ['/unpack/release/', '/unpack/release/marrison-addon.php', false, false, false],
    'already_correct' => ['/unpack/marrison-addon/', '/unpack/marrison-addon/marrison-addon.php', false, false, false],
    'missing_entry' => ['/unpack/invalid/', '/unpack/invalid/README.md', false, false, true],
    'collision' => ['/unpack/release/', '/unpack/release/marrison-addon.php', true, false, true],
    'move_failure' => ['/unpack/release/', '/unpack/release/marrison-addon.php', false, true, true],
];
foreach ( $cases as $name => $case ) {
    $GLOBALS['wp_filesystem'] = new Layout_Filesystem();
    $filesystem = $GLOBALS['wp_filesystem'];
    $filesystem->files[$case[1]] = true;
    $filesystem->collision = $case[2];
    $filesystem->fail = $case[3];
    $result = $updater->fix_folder_name( $case[0], '/unpack/', null, ['plugin'=>'marrison-addon/marrison-addon.php'] );
    if ( $case[4] ? !( $result instanceof WP_Error ) : ( $result !== '/unpack/marrison-addon/' || !$filesystem->is_file( $result . 'marrison-addon.php' ) ) ) {
        throw new RuntimeException( 'FAIL ' . $name );
    }
    if ( $name === 'collision' && $filesystem->moves ) { throw new RuntimeException( 'Destination overwritten' ); }
}
$unchanged = $updater->fix_folder_name( '/other/', '/unpack/', null, ['plugin'=>'other/other.php'] );
if ( $unchanged !== '/other/' ) { throw new RuntimeException( 'Unrelated plugin changed' ); }
echo "PASS updater layouts, collision, missing entry, failed move and unrelated plugin\n";
