<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Product_Discount {

	const META_KEY = '_marrison_discount_percentage';
	const BACKFILL_VERSION = '1';
	const BACKFILL_VERSION_OPTION = 'marrison_product_discount_meta_version';
	const BACKFILL_PAGE_OPTION = 'marrison_product_discount_backfill_page';
	const BACKFILL_HOOK = 'marrison_product_discount_backfill_batch';
	const BACKFILL_GROUP = 'marrison-addon';
	const BACKFILL_BATCH_SIZE = 50;
	const SCHEDULE_OPTION = 'marrison_product_discount_sync_frequency';
	const SCHEDULED_FREQUENCY_OPTION = 'marrison_product_discount_scheduled_frequency';
	const SCHEDULE_TRIGGER_HOOK = 'marrison_product_discount_scheduled_sync';
	const SCHEDULE_BATCH_HOOK = 'marrison_product_discount_scheduled_sync_batch';
	const SCHEDULE_PAGE_OPTION = 'marrison_product_discount_scheduled_sync_page';

	private $queued_product_ids = array();

	public function __construct() {
		if ( ! Marrison_Addon::is_woocommerce_active() ) {
			return;
		}

		add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'wp_ajax_marrison_product_discount_batch_start', [ $this, 'ajax_batch_start' ] );
		add_action( 'wp_ajax_marrison_product_discount_batch_step', [ $this, 'ajax_batch_step' ] );
		add_filter( 'cron_schedules', [ $this, 'add_cron_schedules' ] );
		add_action( 'init', [ $this, 'maybe_schedule_backfill' ], 20 );
		add_action( 'init', [ $this, 'sync_recurring_schedule' ], 25 );
		add_action( self::BACKFILL_HOOK, [ $this, 'run_backfill_batch' ] );
		add_action( self::SCHEDULE_TRIGGER_HOOK, [ $this, 'start_scheduled_sync' ] );
		add_action( self::SCHEDULE_BATCH_HOOK, [ $this, 'run_scheduled_sync_batch' ] );

		add_action( 'woocommerce_after_product_object_save', [ $this, 'sync_saved_product' ], 20, 2 );
		add_action( 'woocommerce_updated_product_price', [ $this, 'sync_product_id' ], 20 );
		add_action( 'woocommerce_save_product_variation', [ $this, 'queue_variation_parent_sync' ], 20, 2 );
		add_action( 'woocommerce_before_delete_product_variation', [ $this, 'queue_variation_parent_sync' ], 20 );
		add_action( 'woocommerce_trash_product_variation', [ $this, 'queue_variation_parent_sync' ], 20 );
		add_action( 'wc_product_start_scheduled_sale', [ $this, 'sync_product_id' ], 20 );
		add_action( 'wc_product_end_scheduled_sale', [ $this, 'sync_product_id' ], 20 );
		add_action( 'wc_after_products_starting_sales', [ $this, 'sync_product_ids' ], 20 );
		add_action( 'wc_after_products_ending_sales', [ $this, 'sync_product_ids' ], 20 );

		add_action( 'transition_post_status', [ $this, 'handle_post_status_transition' ], 20, 3 );
		add_action( 'added_post_meta', [ $this, 'queue_price_meta_product_sync' ], 20, 4 );
		add_action( 'updated_post_meta', [ $this, 'queue_price_meta_product_sync' ], 20, 4 );
		add_action( 'deleted_post_meta', [ $this, 'queue_price_meta_product_sync' ], 20, 4 );
		add_action( 'before_delete_post', [ $this, 'queue_deleted_product_parent' ], 10, 2 );
		add_action( 'shutdown', [ $this, 'sync_queued_products' ] );
	}

	public function register_widgets( $widgets_manager ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return;
		}

		require_once plugin_dir_path( __FILE__ ) . 'product-discount/widgets/product-discount-widget.php';

		$widgets_manager->register( new \Marrison_Addon\Modules\Product_Discount\Widgets\Product_Discount_Widget() );
	}

	public function add_admin_menu() {
		add_submenu_page(
			'marrison_addon_panel',
			esc_html__( 'Sconto Prodotto', 'marrison-addon' ),
			esc_html__( 'Sconto Prodotto', 'marrison-addon' ),
			'manage_options',
			'marrison_addon_product_discount',
			[ $this, 'render_admin_page' ]
		);
	}

	public function enqueue_admin_assets( $hook ) {
		if ( ! isset( $_GET['page'] ) || 'marrison_addon_product_discount' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}

		$plugin_root_file = dirname( dirname( dirname( __FILE__ ) ) ) . '/marrison-addon.php';
		wp_enqueue_script( 'marrison-admin-product-discount', plugins_url( 'assets/js/admin-product-discount.js', $plugin_root_file ), [ 'jquery' ], Marrison_Addon::VERSION, true );
		wp_localize_script(
			'marrison-admin-product-discount',
			'marrisonProductDiscount',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'marrison_product_discount_batch' ),
				'confirmMessage' => __( 'Vuoi ricalcolare ora la percentuale di sconto per tutti i prodotti?', 'marrison-addon' ),
				'startingText'   => __( 'Preparazione batch...', 'marrison-addon' ),
				'emptyText'      => __( 'Nessun prodotto trovato.', 'marrison-addon' ),
				'doneText'       => __( 'Batch completato.', 'marrison-addon' ),
				'errorText'      => __( 'Errore durante il batch.', 'marrison-addon' ),
			)
		);

		wp_add_inline_style(
			'wp-admin',
			'
			#marrison-product-discount-progress {
				width: 100%;
				max-width: 720px;
				background-color: #f0f0f1;
				border: 1px solid #c3c4c7;
				height: 28px;
				margin: 14px 0 8px;
				position: relative;
				display: none;
			}
			#marrison-product-discount-progress-fill {
				width: 0%;
				height: 100%;
				background-color: #2271b1;
				transition: width 0.2s;
			}
			#marrison-product-discount-progress-text {
				position: absolute;
				top: 0;
				left: 0;
				width: 100%;
				height: 100%;
				text-align: center;
				line-height: 28px;
				font-weight: 600;
				color: #1d2327;
			}
			#marrison-product-discount-status {
				margin-top: 8px;
				font-weight: 600;
			}
			.marrison-product-discount-query-code {
				display: inline-block;
				background: #f0f0f1;
				padding: 2px 6px;
				border-radius: 3px;
				font-family: Consolas, Monaco, monospace;
			}
			'
		);
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['marrison_product_discount_schedule_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['marrison_product_discount_schedule_nonce'] ) ), 'marrison_product_discount_schedule' ) ) {
			$frequency = isset( $_POST['marrison_product_discount_sync_frequency'] ) ? sanitize_key( wp_unslash( $_POST['marrison_product_discount_sync_frequency'] ) ) : 'manual';
			$frequency = array_key_exists( $frequency, $this->get_frequency_options() ) ? $frequency : 'manual';
			update_option( self::SCHEDULE_OPTION, $frequency );
			$this->sync_recurring_schedule( true );
			add_settings_error( 'marrison_product_discount_messages', 'marrison_product_discount_saved', __( 'Impostazioni salvate.', 'marrison-addon' ), 'updated' );
		}

		$frequency = get_option( self::SCHEDULE_OPTION, 'manual' );
		$next_run = wp_next_scheduled( self::SCHEDULE_TRIGGER_HOOK );
		?>
		<div class="wrap marrison-admin-page marrison-admin-page-product-discount">
			<h1><?php echo esc_html__( 'Sconto Prodotto', 'marrison-addon' ); ?></h1>
			<p><?php echo esc_html__( 'Gestisci il meta numerico usato per ordinare i prodotti per percentuale di sconto.', 'marrison-addon' ); ?></p>
			<?php settings_errors( 'marrison_product_discount_messages' ); ?>

			<div class="marrison-module-card" style="margin-bottom: 20px;">
				<div class="marrison-card-header">
					<h2 class="marrison-card-title" style="font-size: 1.3em; margin: 0;"><?php echo esc_html__( 'Ricalcolo manuale', 'marrison-addon' ); ?></h2>
				</div>
				<p class="marrison-card-desc">
					<?php echo esc_html__( 'Avvia un batch manuale per ricalcolare il meta _marrison_discount_percentage su tutti i prodotti esistenti. Utile dopo import o sincronizzazioni massive.', 'marrison-addon' ); ?>
				</p>
				<button type="button" id="marrison-product-discount-run-batch" class="button button-primary button-large">
					<?php echo esc_html__( 'Esegui batch ora', 'marrison-addon' ); ?>
				</button>
				<div id="marrison-product-discount-progress">
					<div id="marrison-product-discount-progress-fill"></div>
					<div id="marrison-product-discount-progress-text">0%</div>
				</div>
				<div id="marrison-product-discount-status"></div>
			</div>

			<div class="marrison-module-card" style="margin-bottom: 20px;">
				<div class="marrison-card-header">
					<h2 class="marrison-card-title" style="font-size: 1.3em; margin: 0;"><?php echo esc_html__( 'Ricalcolo automatico', 'marrison-addon' ); ?></h2>
				</div>
				<p class="marrison-card-desc">
					<?php echo esc_html__( 'Scegli ogni quanto ricalcolare il meta in background. Serve come rete di sicurezza per plugin di sync che aggiornano prezzi senza passare dagli hook WooCommerce standard.', 'marrison-addon' ); ?>
				</p>
				<form method="post">
					<?php wp_nonce_field( 'marrison_product_discount_schedule', 'marrison_product_discount_schedule_nonce' ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="marrison_product_discount_sync_frequency"><?php echo esc_html__( 'Frequenza batch', 'marrison-addon' ); ?></label>
							</th>
							<td>
								<select id="marrison_product_discount_sync_frequency" name="marrison_product_discount_sync_frequency">
									<?php foreach ( $this->get_frequency_options() as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $frequency, $value ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
								<?php if ( $next_run ) : ?>
									<p class="description">
										<?php
										printf(
											/* translators: %s: Scheduled date. */
											esc_html__( 'Prossima esecuzione: %s', 'marrison-addon' ),
											esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_run ) )
										);
										?>
									</p>
								<?php endif; ?>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Salva frequenza', 'marrison-addon' ) ); ?>
				</form>
			</div>

			<div class="marrison-module-card">
				<div class="marrison-card-header">
					<h2 class="marrison-card-title" style="font-size: 1.3em; margin: 0;"><?php echo esc_html__( 'Query JetEngine', 'marrison-addon' ); ?></h2>
				</div>
				<p><?php echo esc_html__( 'Per ordinare i prodotti dal maggiore sconto al minore in JetEngine Query Builder:', 'marrison-addon' ); ?></p>
				<ol>
					<li><?php echo esc_html__( 'Post Type: Prodotti / product.', 'marrison-addon' ); ?></li>
					<li><?php echo esc_html__( 'Order By: valore meta numerico / numeric meta value.', 'marrison-addon' ); ?></li>
					<li><?php echo esc_html__( 'Meta key:', 'marrison-addon' ); ?> <span class="marrison-product-discount-query-code"><?php echo esc_html( self::META_KEY ); ?></span></li>
					<li><?php echo esc_html__( 'Order: DESC, dal valore più alto al più basso.', 'marrison-addon' ); ?></li>
				</ol>
				<p class="description">
					<?php echo esc_html__( 'Il valore salvato è numerico e non formattato: niente simbolo %, prefissi o arrotondamenti Elementor.', 'marrison-addon' ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	public static function get_discount_percentage( $product ) {
		if ( ! $product instanceof \WC_Product || ! $product->is_on_sale() ) {
			return 0;
		}

		if ( $product->is_type( 'variable' ) && method_exists( $product, 'get_variation_prices' ) ) {
			return self::get_variable_discount_percentage( $product );
		}

		$regular_price = (float) $product->get_regular_price();
		$sale_price = (float) $product->get_sale_price();

		return self::calculate_discount_percentage( $regular_price, $sale_price );
	}

	public static function get_variable_discount_percentage( $product ) {
		$prices = $product->get_variation_prices( false );

		if ( empty( $prices['regular_price'] ) || empty( $prices['sale_price'] ) ) {
			return 0;
		}

		$highest_discount = 0;

		foreach ( $prices['regular_price'] as $variation_id => $regular_price ) {
			$sale_price = isset( $prices['sale_price'][ $variation_id ] ) ? $prices['sale_price'][ $variation_id ] : 0;
			$active_price = isset( $prices['price'][ $variation_id ] ) ? $prices['price'][ $variation_id ] : $sale_price;

			if ( (float) $sale_price !== (float) $active_price ) {
				continue;
			}

			$highest_discount = max(
				$highest_discount,
				self::calculate_discount_percentage( (float) $regular_price, (float) $sale_price )
			);
		}

		return $highest_discount;
	}

	public static function calculate_discount_percentage( $regular_price, $sale_price ) {
		if ( $regular_price <= 0 || $sale_price < 0 || $sale_price >= $regular_price ) {
			return 0;
		}

		return ( ( $regular_price - $sale_price ) / $regular_price ) * 100;
	}

	public function ajax_batch_start() {
		$this->verify_ajax_request();

		$total = $this->get_total_products_count();

		wp_send_json_success(
			array(
				'total'     => $total,
				'batchSize' => self::BACKFILL_BATCH_SIZE,
				'done'      => 0 === $total,
			)
		);
	}

	public function ajax_batch_step() {
		$this->verify_ajax_request();

		$page = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;
		$result = $this->process_product_batch( $page );

		if ( ! empty( $result['done'] ) ) {
			update_option( self::BACKFILL_VERSION_OPTION, self::BACKFILL_VERSION );
			delete_option( self::BACKFILL_PAGE_OPTION );
		}

		wp_send_json_success( $result );
	}

	public function add_cron_schedules( $schedules ) {
		if ( ! isset( $schedules['marrison_product_discount_daily'] ) ) {
			$schedules['marrison_product_discount_daily'] = array(
				'interval' => DAY_IN_SECONDS,
				'display'  => __( 'Marrison Product Discount - Daily', 'marrison-addon' ),
			);
		}

		if ( ! isset( $schedules['marrison_product_discount_weekly'] ) ) {
			$schedules['marrison_product_discount_weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Marrison Product Discount - Weekly', 'marrison-addon' ),
			);
		}

		if ( ! isset( $schedules['marrison_product_discount_monthly'] ) ) {
			$schedules['marrison_product_discount_monthly'] = array(
				'interval' => 30 * DAY_IN_SECONDS,
				'display'  => __( 'Marrison Product Discount - Monthly', 'marrison-addon' ),
			);
		}

		return $schedules;
	}

	public function sync_recurring_schedule( $force = false ) {
		$frequency = get_option( self::SCHEDULE_OPTION, 'manual' );
		$frequency = array_key_exists( $frequency, $this->get_frequency_options() ) ? $frequency : 'manual';
		$scheduled_frequency = get_option( self::SCHEDULED_FREQUENCY_OPTION, '' );

		if ( ! $force && $frequency === $scheduled_frequency && ( 'manual' === $frequency || wp_next_scheduled( self::SCHEDULE_TRIGGER_HOOK ) ) ) {
			return;
		}

		$this->clear_recurring_schedule();

		if ( 'manual' === $frequency ) {
			update_option( self::SCHEDULED_FREQUENCY_OPTION, 'manual' );
			delete_option( self::SCHEDULE_PAGE_OPTION );
			return;
		}

		wp_schedule_event( time() + HOUR_IN_SECONDS, 'marrison_product_discount_' . $frequency, self::SCHEDULE_TRIGGER_HOOK );
		update_option( self::SCHEDULED_FREQUENCY_OPTION, $frequency );
	}

	public function start_scheduled_sync() {
		if ( false !== get_option( self::SCHEDULE_PAGE_OPTION, false ) ) {
			$this->schedule_single_scheduled_sync_batch();
			return;
		}

		update_option( self::SCHEDULE_PAGE_OPTION, 1 );
		$this->schedule_single_scheduled_sync_batch();
	}

	public function run_scheduled_sync_batch() {
		$page = max( 1, absint( get_option( self::SCHEDULE_PAGE_OPTION, 1 ) ) );
		$result = $this->process_product_batch( $page );

		if ( ! empty( $result['done'] ) ) {
			delete_option( self::SCHEDULE_PAGE_OPTION );
			update_option( self::BACKFILL_VERSION_OPTION, self::BACKFILL_VERSION );
			return;
		}

		update_option( self::SCHEDULE_PAGE_OPTION, $page + 1 );
		$this->schedule_single_scheduled_sync_batch();
	}

	public function sync_saved_product( $product, $data_store ) {
		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		$this->sync_product( $product );
	}

	public function sync_product_id( $product_id ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return;
		}

		$product = wc_get_product( $product_id );

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		$this->sync_product( $product );
	}

	public function sync_product_ids( $product_ids ) {
		if ( ! is_array( $product_ids ) ) {
			return;
		}

		foreach ( $product_ids as $product_id ) {
			$this->sync_product_id( $product_id );
		}
	}

	public function queue_variation_parent_sync( $variation_id ) {
		$parent_id = wp_get_post_parent_id( $variation_id );

		if ( $parent_id ) {
			$this->queue_product_sync( $parent_id );
		}
	}

	public function handle_post_status_transition( $new_status, $old_status, $post ) {
		if ( ! $post instanceof \WP_Post || $new_status === $old_status ) {
			return;
		}

		if ( 'product' === $post->post_type ) {
			$this->queue_product_sync( $post->ID );
			return;
		}

		if ( 'product_variation' === $post->post_type && ! empty( $post->post_parent ) ) {
			$this->queue_product_sync( $post->post_parent );
		}
	}

	public function queue_price_meta_product_sync( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( ! in_array( $meta_key, $this->get_price_meta_keys(), true ) ) {
			return;
		}

		$post_type = get_post_type( $object_id );

		if ( 'product' === $post_type ) {
			$this->queue_product_sync( $object_id );
			return;
		}

		if ( 'product_variation' === $post_type ) {
			$parent_id = wp_get_post_parent_id( $object_id );

			if ( $parent_id ) {
				$this->queue_product_sync( $parent_id );
			}
		}
	}

	public function queue_deleted_product_parent( $post_id, $post ) {
		if ( ! $post instanceof \WP_Post || 'product_variation' !== $post->post_type || empty( $post->post_parent ) ) {
			return;
		}

		$this->queue_product_sync( $post->post_parent );
	}

	public function sync_queued_products() {
		if ( empty( $this->queued_product_ids ) ) {
			return;
		}

		$product_ids = array_keys( $this->queued_product_ids );
		$this->queued_product_ids = array();

		foreach ( $product_ids as $product_id ) {
			$this->sync_product_id( $product_id );
		}
	}

	public function maybe_schedule_backfill() {
		if ( $this->is_backfill_complete() ) {
			return;
		}

		if ( function_exists( 'as_next_scheduled_action' ) && false !== as_next_scheduled_action( self::BACKFILL_HOOK, array(), self::BACKFILL_GROUP ) ) {
			return;
		}

		if ( ! function_exists( 'as_next_scheduled_action' ) && wp_next_scheduled( self::BACKFILL_HOOK ) ) {
			return;
		}

		$this->schedule_backfill_batch();
	}

	public function run_backfill_batch() {
		if ( $this->is_backfill_complete() || ! function_exists( 'wc_get_products' ) ) {
			return;
		}

		$page = max( 1, absint( get_option( self::BACKFILL_PAGE_OPTION, 1 ) ) );
		$result = $this->process_product_batch( $page );

		if ( ! empty( $result['done'] ) ) {
			update_option( self::BACKFILL_VERSION_OPTION, self::BACKFILL_VERSION );
			delete_option( self::BACKFILL_PAGE_OPTION );
			return;
		}

		update_option( self::BACKFILL_PAGE_OPTION, $page + 1 );
		$this->schedule_backfill_batch();
	}

	private function sync_product( $product ) {
		if ( $product->is_type( 'variation' ) && method_exists( $product, 'get_parent_id' ) ) {
			$parent_id = $product->get_parent_id();

			if ( $parent_id ) {
				$this->queue_product_sync( $parent_id );
			}

			return;
		}

		$discount = self::get_discount_percentage( $product );
		update_post_meta( $product->get_id(), self::META_KEY, $discount > 0 ? (float) $discount : 0 );
	}

	private function verify_ajax_request() {
		check_ajax_referer( 'marrison_product_discount_batch', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permesso negato.', 'marrison-addon' ) ) );
		}
	}

	private function get_frequency_options() {
		return array(
			'manual'  => __( 'Solo manuale', 'marrison-addon' ),
			'daily'   => __( 'Una volta al giorno', 'marrison-addon' ),
			'weekly'  => __( 'Una volta alla settimana', 'marrison-addon' ),
			'monthly' => __( 'Una volta al mese', 'marrison-addon' ),
		);
	}

	private function clear_recurring_schedule() {
		wp_clear_scheduled_hook( self::SCHEDULE_TRIGGER_HOOK );
		wp_clear_scheduled_hook( self::SCHEDULE_BATCH_HOOK );
	}

	private function schedule_single_scheduled_sync_batch() {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			if ( function_exists( 'as_next_scheduled_action' ) && false !== as_next_scheduled_action( self::SCHEDULE_BATCH_HOOK, array(), self::BACKFILL_GROUP ) ) {
				return;
			}

			as_enqueue_async_action( self::SCHEDULE_BATCH_HOOK, array(), self::BACKFILL_GROUP );
			return;
		}

		if ( ! wp_next_scheduled( self::SCHEDULE_BATCH_HOOK ) ) {
			wp_schedule_single_event( time() + 30, self::SCHEDULE_BATCH_HOOK );
		}
	}

	private function get_total_products_count() {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return 0;
		}

		$result = wc_get_products(
			array(
				'status'   => $this->get_product_statuses(),
				'limit'    => 1,
				'page'     => 1,
				'paginate' => true,
				'return'   => 'ids',
				'orderby'  => 'ID',
				'order'    => 'ASC',
			)
		);

		return is_object( $result ) && isset( $result->total ) ? absint( $result->total ) : 0;
	}

	private function process_product_batch( $page ) {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array(
				'page'           => $page,
				'nextPage'       => $page,
				'processed'      => 0,
				'batchProcessed' => 0,
				'total'          => 0,
				'totalPages'     => 0,
				'done'           => true,
			);
		}

		$result = wc_get_products(
			array(
				'status'   => $this->get_product_statuses(),
				'limit'    => self::BACKFILL_BATCH_SIZE,
				'page'     => $page,
				'paginate' => true,
				'return'   => 'ids',
				'orderby'  => 'ID',
				'order'    => 'ASC',
			)
		);

		$product_ids = is_object( $result ) && isset( $result->products ) ? $result->products : array();
		$total = is_object( $result ) && isset( $result->total ) ? absint( $result->total ) : count( $product_ids );
		$total_pages = is_object( $result ) && isset( $result->max_num_pages ) ? absint( $result->max_num_pages ) : 0;

		foreach ( $product_ids as $product_id ) {
			$this->sync_product_id( $product_id );
		}

		$batch_processed = count( $product_ids );
		$processed = min( $total, ( ( $page - 1 ) * self::BACKFILL_BATCH_SIZE ) + $batch_processed );
		$done = empty( $product_ids ) || $page >= $total_pages;

		return array(
			'page'           => $page,
			'nextPage'       => $page + 1,
			'processed'      => $processed,
			'batchProcessed' => $batch_processed,
			'total'          => $total,
			'totalPages'     => $total_pages,
			'done'           => $done,
		);
	}

	private function get_product_statuses() {
		if ( function_exists( 'wc_get_product_statuses' ) ) {
			return array_keys( wc_get_product_statuses() );
		}

		return array( 'publish', 'pending', 'draft', 'future', 'private' );
	}

	private function queue_product_sync( $product_id ) {
		$product_id = absint( $product_id );

		if ( $product_id ) {
			$this->queued_product_ids[ $product_id ] = true;
		}
	}

	private function get_price_meta_keys() {
		return array(
			'_regular_price',
			'_sale_price',
			'_price',
			'_sale_price_dates_from',
			'_sale_price_dates_to',
		);
	}

	private function is_backfill_complete() {
		return self::BACKFILL_VERSION === get_option( self::BACKFILL_VERSION_OPTION );
	}

	private function schedule_backfill_batch() {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::BACKFILL_HOOK, array(), self::BACKFILL_GROUP );
			return;
		}

		wp_schedule_single_event( time() + 10, self::BACKFILL_HOOK );
	}
}
