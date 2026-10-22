<?php
/** Run: php tests/product-discount-queue-test.php [--as]. */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );
$enabled = true;
$scheduled = $synced = $registered_hooks = $options = array();
class Marrison_Addon {
    public static function is_woocommerce_active() { return true; }
    public static function is_module_enabled( $module ) { return $GLOBALS['enabled']; }
}
function add_action( $hook, $callback, $priority = 10, $argc = 1 ) { $GLOBALS['registered_hooks'][ $hook ] = $callback; }
function add_filter() {}
function absint( $value ) { return abs( (int) $value ); }
function update_option( $key, $value ) { $GLOBALS['options'][ $key ] = $value; }
function delete_option( $key ) { unset( $GLOBALS['options'][ $key ] ); }
function wp_next_scheduled( $hook, $args = array() ) { return isset( $GLOBALS['scheduled'][ $hook ][ serialize( $args ) ] ) ? time() : false; }
function wp_schedule_single_event( $time, $hook, $args = array() ) { $GLOBALS['scheduled'][ $hook ][ serialize( $args ) ] = $args; return true; }
function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['scheduled'][ $hook ][ serialize( array() ) ] ); }
function wp_unschedule_hook( $hook ) { unset( $GLOBALS['scheduled'][ $hook ] ); }
if ( in_array( '--as', $argv, true ) ) {
    function as_enqueue_async_action( $hook, $args, $group, $unique = false ) {
        check( $unique && $group === 'marrison-addon', 'Deferred import actions must request Action Scheduler uniqueness in their own group.' );
        $GLOBALS['scheduled'][ $hook ][ serialize( $args ) ] = $args;
        return 1;
    }
    function as_unschedule_all_actions( $hook, $args, $group ) { unset( $GLOBALS['scheduled'][ $hook ] ); }
}
function check( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
require __DIR__ . '/../marrison-addon/includes/modules/class-marrison-addon-product-discount.php';
class Discount_Queue_Fixture extends Marrison_Addon_Product_Discount {
    public function sync_product_id( $product_id ) { $GLOBALS['synced'][] = $product_id; }
}
$module = new Discount_Queue_Fixture();
function queue( $ids ) {
    $ref = new ReflectionMethod( 'Marrison_Addon_Product_Discount', 'queue_product_sync' );
    if ( PHP_VERSION_ID < 80100 ) { $ref->setAccessible( true ); }
    foreach ( $ids as $id ) { $ref->invoke( $GLOBALS['module'], $id ); }
}
queue( array( 5, 1, 5, 0, 3 ) );
$module->sync_queued_products();
check( $synced === array( 1, 3, 5 ) && empty( $scheduled ), 'Small queues must still synchronize immediately, once per product.' );
$synced = array();
queue( range( 1000, 1 ) );
$module->sync_queued_products();
$hook = Marrison_Addon_Product_Discount::QUEUED_SYNC_HOOK;
check( empty( $synced ) && count( $scheduled[ $hook ] ) === 20, 'A 1000-product import must enqueue twenty bounded batches without recalculating at shutdown.' );
foreach ( $scheduled[ $hook ] as $args ) { check( count( $args['product_ids'] ) <= 50, 'Each deferred callback must process at most fifty IDs.' ); }
queue( range( 1, 1000 ) );
$module->sync_queued_products();
check( count( $scheduled[ $hook ] ) === 20, 'Repeated hooks for the same products must not create duplicate pending batches.' );
$first = reset( $scheduled[ $hook ] );
$module->run_queued_sync_batch( $first['product_ids'] );
check( count( $synced ) === 50 && $synced[0] === 1 && $synced[49] === 50, 'Background batches must calculate exactly the saved product IDs.' );
$synced = array();
$enabled = false;
$module->run_queued_sync_batch( $first['product_ids'] );
queue( array( 333 ) );
$module->handle_module_status_change( 'product_discount', false );
$module->sync_queued_products();
check( empty( $synced ) && empty( $scheduled[ $hook ] ), 'OFF must cancel all batch arguments and never run queued recalculations in the same request.' );
echo 'Product Discount queue/' . ( function_exists( 'as_enqueue_async_action' ) ? 'Action Scheduler' : 'WP-Cron' ) . ": PASS\n";
