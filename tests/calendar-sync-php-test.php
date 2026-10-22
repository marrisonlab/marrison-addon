<?php
/** Local timezone regressions: php tests/calendar-sync-php-test.php */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );
function add_action() {}
function add_shortcode() {}
function wp_timezone() { return new DateTimeZone( 'Europe/Rome' ); }
require dirname( __DIR__ ) . '/marrison-addon/includes/modules/class-marrison-addon-calendar-sync.php';
$module = new Marrison_Addon_Calendar_Sync();
$method = new ReflectionMethod( $module, 'get_timestamp' );
if ( PHP_VERSION_ID < 80100 ) { $method->setAccessible( true ); }
$original_zone = date_default_timezone_get();
date_default_timezone_set( 'UTC' );
$cases = [
    [ '2026-10-19 10:00:00', '2026-10-19T08:00:00Z' ],
    [ '2026-12-19 10:00:00', '2026-12-19T09:00:00Z' ],
    [ '2026-10-19T10:00:00+03:00', '2026-10-19T07:00:00Z' ],
    [ '2026-10-19T10:00:00Z', '2026-10-19T10:00:00Z' ],
    [ 1792396800, '2026-10-19T08:00:00Z' ],
    [ '1792396800000', '2026-10-19T08:00:00Z' ],
];
try {
    foreach ( $cases as list( $input, $expected ) ) {
        $actual = $method->invoke( $module, $input );
        if ( $actual !== strtotime( $expected ) ) { throw new RuntimeException( 'Wrong timestamp for ' . $input ); }
    }
    foreach ( [ '', '   ', null, [], 'not a date' ] as $input ) {
        if ( false !== $method->invoke( $module, $input ) ) { throw new RuntimeException( 'Invalid date accepted.' ); }
    }
} finally { date_default_timezone_set( $original_zone ); }
echo "Calendar Sync: local DST, explicit offsets, seconds/milliseconds and invalid dates PASS.\n";
