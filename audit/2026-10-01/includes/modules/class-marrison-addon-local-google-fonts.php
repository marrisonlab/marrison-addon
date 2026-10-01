<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Local_Google_Fonts {

	private const PAGE_SLUG = 'marrison_addon_local_google_fonts';
	private const NONCE_ACTION = 'marrison_local_google_fonts_nonce';
	private const AJAX_SCAN = 'marrison_local_google_fonts_scan';
	private const AJAX_UPDATE = 'marrison_local_google_fonts_update';
	private const AJAX_SCAN_UPDATE = 'marrison_local_google_fonts_scan_update';
	private const STORAGE_SUBDIR = 'marrison-addon/fonts/local-google-fonts';
	private const ELEMENTOR_GOOGLE_FONTS_CSS_SUBDIR = 'elementor/google-fonts/css';
	private const MANIFEST_FILE = 'manifest.json';
	private const FONT_DISPLAY = 'swap';
	private const GOOGLE_CSS_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36';
	private const MAX_CSS_FILES = 600;
	private const MAX_CSS_FILE_BYTES = 1048576;
	private const MAX_TOTAL_CSS_BYTES = 12582912;
	private const MAX_SOURCE_FILES = 600;
	private const MAX_SOURCE_FILE_BYTES = 1048576;
	private const MAX_TOTAL_SOURCE_BYTES = 12582912;

	private $manifest_cache = null;
	private $removed_google_styles = array();
	private $google_style_diagnostics = array();
	private $active_css_variant_cache = array();
	private $scan_ignored_fonts = array();
	private $scan_local_fonts = array();
	private $scan_google_fonts = array();
	private $elementor_fonts_cache = null;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_' . self::AJAX_SCAN, array( $this, 'ajax_scan_fonts' ) );
		add_action( 'wp_ajax_' . self::AJAX_UPDATE, array( $this, 'ajax_update_fonts' ) );
		add_action( 'wp_ajax_' . self::AJAX_SCAN_UPDATE, array( $this, 'ajax_scan_and_update_fonts' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_local_css' ), 20 );
		add_action( 'wp_print_styles', array( $this, 'dequeue_google_font_styles' ), 1000 );
		add_action( 'wp_print_footer_scripts', array( $this, 'dequeue_google_font_styles' ), 0 );
		add_filter( 'style_loader_tag', array( $this, 'filter_google_font_style_tag' ), 10, 4 );
		add_filter( 'wp_resource_hints', array( $this, 'filter_google_font_resource_hints' ), 10, 2 );
	}

	public function add_admin_menu() {
		add_submenu_page(
			'marrison_addon_panel',
			esc_html__( 'Local Google Fonts', 'marrison-addon' ),
			esc_html__( 'Local Google Fonts', 'marrison-addon' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_admin_page' )
		);
	}

	public function enqueue_admin_assets( $hook = '' ) {
		if ( ! $this->is_admin_page() ) {
			return;
		}

		$plugin_root_file = Marrison_Addon::plugin_file();

		wp_enqueue_script(
			'marrison-admin-local-google-fonts',
			plugins_url( 'assets/js/admin-local-google-fonts.js', $plugin_root_file ),
			array( 'jquery' ),
			Marrison_Addon::asset_version( 'assets/js/admin-local-google-fonts.js' ),
			true
		);

		wp_localize_script(
			'marrison-admin-local-google-fonts',
			'marrisonLocalGoogleFonts',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( self::NONCE_ACTION ),
				'scanAction'     => self::AJAX_SCAN,
				'updateAction'   => self::AJAX_UPDATE,
				'scanUpdateAction' => self::AJAX_SCAN_UPDATE,
				'working'        => __( 'Operazione in corso...', 'marrison-addon' ),
				'scanConfirm'    => __( 'Avviare la scansione dei font? L operazione puo richiedere qualche minuto.', 'marrison-addon' ),
				'updateConfirm'  => __( 'Scaricare o aggiornare i font locali trovati dalla scansione?', 'marrison-addon' ),
				'scanUpdateConfirm' => __( 'Scansionare e aggiornare subito i font locali?', 'marrison-addon' ),
				'connectionError' => __( 'Errore di connessione durante l operazione.', 'marrison-addon' ),
			)
		);
	}

	public function enqueue_local_css() {
		if ( ! Marrison_Addon_Context::is_public_frontend_request() ) {
			return;
		}

		$active = $this->get_active_manifest();
		if ( ! $active ) {
			return;
		}

		if ( empty( $active['css_url'] ) || empty( $active['version'] ) ) {
			return;
		}

		wp_enqueue_style(
			'marrison-local-google-fonts',
			$active['css_url'],
			array(),
			$active['version']
		);
	}

	public function dequeue_google_font_styles() {
		if ( ! Marrison_Addon_Context::is_public_frontend_request() ) {
			return;
		}

		$active = $this->get_active_manifest();
		if ( ! $active ) {
			return;
		}

		$wp_styles = wp_styles();
		if ( empty( $wp_styles ) || empty( $wp_styles->queue ) || empty( $wp_styles->registered ) ) {
			return;
		}

		$this->process_google_font_style_handles( $wp_styles, $active );
	}

	public function filter_google_font_style_tag( $html, $handle, $href, $media ) {
		if ( ! Marrison_Addon_Context::is_public_frontend_request() ) {
			return $html;
		}

		$href = $this->normalize_asset_url( $href );
		if ( ! $this->is_google_fonts_stylesheet_url( $href ) ) {
			return $html;
		}

		$active = $this->get_active_manifest();
		if ( ! $active ) {
			return $html;
		}

		$coverage = $this->evaluate_google_stylesheet_coverage( $href, $active );
		if ( empty( $coverage['covered'] ) ) {
			return $html;
		}

		$this->removed_google_styles[ $handle ] = $href;

		return '';
	}

	private function process_google_font_style_handles( $wp_styles, $active ) {
		if ( empty( $wp_styles ) || empty( $wp_styles->registered ) ) {
			return array();
		}

		$handles = $this->get_style_handles_pending_print( $wp_styles );
		$diagnostics = array();

		foreach ( $handles as $handle ) {
			if ( empty( $wp_styles->registered[ $handle ] ) || empty( $wp_styles->registered[ $handle ]->src ) ) {
				continue;
			}

			$src = $this->normalize_asset_url( $wp_styles->registered[ $handle ]->src );

			if ( ! $this->is_google_fonts_stylesheet_url( $src ) ) {
				continue;
			}

			$coverage = $this->evaluate_google_stylesheet_coverage( $src, $active );
			$diagnostic = array(
				'handle' => $handle,
				'url' => $src,
				'requested' => $coverage['requested'],
				'coverage' => $coverage['covered'] ? 'complete' : 'incomplete',
				'missing' => $coverage['missing'],
				'action' => 'kept',
				'dependency_removals' => array(),
			);

			if ( ! $coverage['covered'] ) {
				$diagnostics[] = $diagnostic;
				continue;
			}

			$diagnostic['dependency_removals'] = $this->remove_style_dependency_handle( $wp_styles, $handle );
			$diagnostic['action'] = 'removed';

			$this->remove_style_handle_from_print_queues( $wp_styles, $handle );
			$this->removed_google_styles[ $handle ] = $src;
			$diagnostics[] = $diagnostic;
		}

		$this->google_style_diagnostics = $diagnostics;

		return $diagnostics;
	}

	private function get_style_handles_pending_print( $wp_styles ) {
		$handles = array();
		$visited = array();
		$queue   = ! empty( $wp_styles->queue ) ? (array) $wp_styles->queue : array();

		foreach ( $queue as $handle ) {
			$this->collect_style_handle_with_dependencies( $wp_styles, $handle, $handles, $visited );
		}

		return array_keys( $handles );
	}

	private function collect_style_handle_with_dependencies( $wp_styles, $handle, &$handles, &$visited ) {
		$handle = (string) $handle;

		if ( '' === $handle || isset( $visited[ $handle ] ) ) {
			return;
		}

		$visited[ $handle ] = true;
		$handles[ $handle ] = true;

		if ( empty( $wp_styles->registered[ $handle ] ) || empty( $wp_styles->registered[ $handle ]->deps ) ) {
			return;
		}

		foreach ( (array) $wp_styles->registered[ $handle ]->deps as $dependency ) {
			$this->collect_style_handle_with_dependencies( $wp_styles, $dependency, $handles, $visited );
		}
	}

	private function remove_style_dependency_handle( $wp_styles, $dependency_handle ) {
		$removals = array();

		foreach ( (array) $wp_styles->registered as $handle => $style ) {
			if ( empty( $style->deps ) || ! is_array( $style->deps ) || ! in_array( $dependency_handle, $style->deps, true ) ) {
				continue;
			}

			$style->deps = array_values(
				array_filter(
					$style->deps,
					function ( $dependency ) use ( $dependency_handle ) {
						return $dependency !== $dependency_handle;
					}
				)
			);

			$removals[] = array(
				'handle' => $handle,
				'removed_dependency' => $dependency_handle,
			);
		}

		return $removals;
	}

	private function remove_style_handle_from_print_queues( $wp_styles, $handle ) {
		foreach ( array( 'queue', 'to_do' ) as $property ) {
			if ( ! isset( $wp_styles->{$property} ) || ! is_array( $wp_styles->{$property} ) ) {
				continue;
			}

			$wp_styles->{$property} = array_values(
				array_filter(
					$wp_styles->{$property},
					function ( $queued_handle ) use ( $handle ) {
						return $queued_handle !== $handle;
					}
				)
			);
		}
	}

	public function filter_google_font_resource_hints( $urls, $relation_type ) {
		if ( ! in_array( $relation_type, array( 'dns-prefetch', 'preconnect' ), true ) ) {
			return $urls;
		}

		if ( ! Marrison_Addon_Context::is_public_frontend_request() || ! $this->get_active_manifest() ) {
			return $urls;
		}

		$filtered = array();

		foreach ( (array) $urls as $key => $url ) {
			$href = is_array( $url ) && isset( $url['href'] ) ? $url['href'] : $url;

			if ( is_string( $href ) && $this->is_google_fonts_host( $href ) ) {
				continue;
			}

			$filtered[ $key ] = $url;
		}

		return $filtered;
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$manifest = $this->load_manifest();
		$scan     = isset( $manifest['scan'] ) && is_array( $manifest['scan'] ) ? $manifest['scan'] : $this->get_empty_scan();
		$active   = isset( $manifest['active'] ) && is_array( $manifest['active'] ) ? $manifest['active'] : array();
		?>
		<div class="wrap marrison-admin-page marrison-admin-page-local-google-fonts">
			<h1><?php esc_html_e( 'Local Google Fonts', 'marrison-addon' ); ?></h1>
			<p><?php esc_html_e( 'Serve localmente i Google Fonts gia usati dal sito senza modificare widget, template, Global Fonts o CSS salvati.', 'marrison-addon' ); ?></p>

			<div class="marrison-module-card marrison-lgf-summary-card">
				<div class="marrison-card-header">
					<h2 class="marrison-card-title"><?php esc_html_e( 'Stato modulo', 'marrison-addon' ); ?></h2>
				</div>
				<div class="marrison-lgf-summary-grid">
					<div>
						<span class="marrison-lgf-label"><?php esc_html_e( 'Modulo', 'marrison-addon' ); ?></span>
						<strong class="marrison-lgf-status success"><?php esc_html_e( 'Attivo', 'marrison-addon' ); ?></strong>
					</div>
					<div>
						<span class="marrison-lgf-label"><?php esc_html_e( 'Ultima scansione', 'marrison-addon' ); ?></span>
						<strong><?php echo esc_html( ! empty( $scan['scanned_at'] ) ? $scan['scanned_at'] : __( 'Mai eseguita', 'marrison-addon' ) ); ?></strong>
					</div>
					<div>
						<span class="marrison-lgf-label"><?php esc_html_e( 'CSS locale', 'marrison-addon' ); ?></span>
						<?php if ( ! empty( $active['css_file'] ) && $this->get_active_manifest() ) : ?>
							<strong class="marrison-lgf-status success"><?php esc_html_e( 'Disponibile', 'marrison-addon' ); ?></strong>
						<?php else : ?>
							<strong class="marrison-lgf-status warning"><?php esc_html_e( 'Non configurato', 'marrison-addon' ); ?></strong>
						<?php endif; ?>
					</div>
					<div>
						<span class="marrison-lgf-label"><?php esc_html_e( 'Ultimo aggiornamento', 'marrison-addon' ); ?></span>
						<strong><?php echo esc_html( ! empty( $active['generated_at'] ) ? $active['generated_at'] : __( 'Mai eseguito', 'marrison-addon' ) ); ?></strong>
					</div>
				</div>
			</div>

			<div class="marrison-module-card">
				<div class="marrison-card-header">
					<h2 class="marrison-card-title"><?php esc_html_e( 'Azioni', 'marrison-addon' ); ?></h2>
				</div>
				<p class="marrison-card-desc"><?php esc_html_e( 'Le operazioni pesanti avvengono solo qui, su richiesta dell amministratore. Il frontend non effettua scansioni o download.', 'marrison-addon' ); ?></p>
				<div class="marrison-lgf-actions">
					<button type="button" class="button button-secondary" id="marrison-lgf-scan"><?php esc_html_e( 'Scansiona font', 'marrison-addon' ); ?></button>
					<button type="button" class="button button-secondary" id="marrison-lgf-update"><?php esc_html_e( 'Scarica/Aggiorna font locali', 'marrison-addon' ); ?></button>
					<button type="button" class="button button-primary" id="marrison-lgf-scan-update"><?php esc_html_e( 'Scansiona e aggiorna', 'marrison-addon' ); ?></button>
				</div>
				<div id="marrison-lgf-status" class="marrison-lgf-notice" aria-live="polite"></div>
			</div>

			<div class="marrison-module-card">
				<div class="marrison-card-header">
					<h2 class="marrison-card-title"><?php esc_html_e( 'Font individuati', 'marrison-addon' ); ?></h2>
				</div>
				<?php $this->render_fonts_table( $scan, $active ); ?>
			</div>

			<div class="marrison-module-card">
				<div class="marrison-card-header">
					<h2 class="marrison-card-title"><?php esc_html_e( 'Diagnostica', 'marrison-addon' ); ?></h2>
				</div>
				<?php $this->render_diagnostics( $manifest ); ?>
			</div>
		</div>
		<?php
	}

	public function ajax_scan_fonts() {
		$this->verify_ajax_request();

		$manifest         = $this->load_manifest();
		$scan             = $this->scan_fonts();
		$manifest['scan'] = $scan;
		$manifest['last_logs'] = $this->build_scan_logs( $scan );

		$saved = $this->save_manifest( $manifest );
		if ( is_wp_error( $saved ) ) {
			wp_send_json_error(
				array(
					'message' => $saved->get_error_message(),
					'logs'    => $manifest['last_logs'],
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of font families. */
					__( 'Scansione completata. Famiglie individuate: %d.', 'marrison-addon' ),
					count( $scan['fonts'] )
				),
				'logs'    => $manifest['last_logs'],
			)
		);
	}

	public function ajax_update_fonts() {
		$this->verify_ajax_request();

		$manifest = $this->load_manifest();
		$scan     = isset( $manifest['scan'] ) && is_array( $manifest['scan'] ) ? $manifest['scan'] : $this->get_empty_scan();

		if ( empty( $scan['fonts'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Nessuna scansione disponibile. Esegui prima Scansiona font.', 'marrison-addon' ),
					'logs'    => array( __( 'Aggiornamento interrotto: mancano famiglie da elaborare.', 'marrison-addon' ) ),
				)
			);
		}

		$this->handle_update_from_scan( $manifest, $scan );
	}

	public function ajax_scan_and_update_fonts() {
		$this->verify_ajax_request();

		$manifest         = $this->load_manifest();
		$scan             = $this->scan_fonts();
		$manifest['scan'] = $scan;

		if ( empty( $scan['fonts'] ) ) {
			$manifest['last_logs'] = $this->build_scan_logs( $scan );
			$this->save_manifest( $manifest );

			wp_send_json_error(
				array(
					'message' => __( 'Scansione completata, ma non sono stati individuati Google Fonts candidati.', 'marrison-addon' ),
					'logs'    => $manifest['last_logs'],
				)
			);
		}

		$this->handle_update_from_scan( $manifest, $scan );
	}

	private function handle_update_from_scan( $manifest, $scan ) {
		$result = $this->download_fonts_from_scan( $scan );

		if ( is_wp_error( $result ) ) {
			$error_data = $result->get_error_data();
			$result_logs = is_array( $error_data ) && ! empty( $error_data['logs'] ) && is_array( $error_data['logs'] ) ? $error_data['logs'] : array();
			$logs = array_merge( $this->build_scan_logs( $scan ), $result_logs );

			$manifest['last_update_errors'] = array( $result->get_error_message() );
			$manifest['last_logs']          = array_merge( $logs, $manifest['last_update_errors'] );
			$this->save_manifest( $manifest );

			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'logs'    => $manifest['last_logs'],
				)
			);
		}

		$logs = array_merge( $this->build_scan_logs( $scan ), isset( $result['logs'] ) ? $result['logs'] : array() );

		$manifest['active']             = $result['active'];
		$manifest['last_update_errors'] = array();
		$manifest['last_logs']          = $logs;

		$saved = $this->save_manifest( $manifest );
		if ( is_wp_error( $saved ) ) {
			wp_send_json_error(
				array(
					'message' => $saved->get_error_message(),
					'logs'    => $logs,
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'Font locali aggiornati e CSS generato.', 'marrison-addon' ),
				'logs'    => $logs,
			)
		);
	}

	private function verify_ajax_request() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'marrison-addon' ) ) );
		}
	}

	private function render_fonts_table( $scan, $active ) {
		if ( empty( $scan['fonts'] ) || ! is_array( $scan['fonts'] ) ) {
			?>
			<p class="marrison-card-desc"><?php esc_html_e( 'Nessun font ancora individuato. Avvia una scansione per popolare questo elenco.', 'marrison-addon' ); ?></p>
			<?php
			return;
		}

		?>
		<table class="wp-list-table widefat striped marrison-lgf-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Famiglia', 'marrison-addon' ); ?></th>
					<th><?php esc_html_e( 'Tipo', 'marrison-addon' ); ?></th>
					<th><?php esc_html_e( 'Peso', 'marrison-addon' ); ?></th>
					<th><?php esc_html_e( 'Stile', 'marrison-addon' ); ?></th>
					<th><?php esc_html_e( 'Stato locale', 'marrison-addon' ); ?></th>
					<th><?php esc_html_e( 'Sorgenti', 'marrison-addon' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $scan['fonts'] as $font ) : ?>
					<?php
					if ( empty( $font['variants'] ) || ! is_array( $font['variants'] ) ) {
						continue;
					}
					$first_row = true;
					$rowspan   = count( $font['variants'] );
					?>
					<?php foreach ( $font['variants'] as $variant ) : ?>
						<?php
						$classification = isset( $font['classification'] ) ? $font['classification'] : 'unknown';
						$is_google      = 'google' === $classification;
						$is_local       = $is_google && $this->active_manifest_has_variant( $active, $font['family'], $variant['style'], $variant['weight'] );
						$sources  = ! empty( $variant['sources'] ) && is_array( $variant['sources'] ) ? $variant['sources'] : array();
						?>
						<tr>
							<?php if ( $first_row ) : ?>
								<td rowspan="<?php echo esc_attr( $rowspan ); ?>"><strong><?php echo esc_html( $font['family'] ); ?></strong></td>
								<td rowspan="<?php echo esc_attr( $rowspan ); ?>"><?php echo esc_html( $this->format_font_classification( $classification ) ); ?></td>
								<?php $first_row = false; ?>
							<?php endif; ?>
							<td><?php echo esc_html( $variant['weight'] ); ?></td>
							<td><?php echo esc_html( $variant['style'] ); ?></td>
							<td>
								<?php if ( $is_local ) : ?>
									<span class="marrison-lgf-status success"><?php esc_html_e( 'Locale', 'marrison-addon' ); ?></span>
								<?php elseif ( ! $is_google ) : ?>
									<span class="marrison-lgf-status muted"><?php esc_html_e( 'Ignorato dal download', 'marrison-addon' ); ?></span>
								<?php else : ?>
									<span class="marrison-lgf-status warning"><?php esc_html_e( 'Da scaricare', 'marrison-addon' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( implode( ', ', array_slice( $sources, 0, 4 ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_diagnostics( $manifest ) {
		$scan = isset( $manifest['scan'] ) && is_array( $manifest['scan'] ) ? $manifest['scan'] : $this->get_empty_scan();

		if ( ! empty( $scan['stats'] ) ) {
			?>
			<div class="marrison-lgf-diagnostics-grid">
				<?php foreach ( $scan['stats'] as $label => $value ) : ?>
					<div>
						<span class="marrison-lgf-label"><?php echo esc_html( $this->format_stat_label( $label ) ); ?></span>
						<strong><?php echo esc_html( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) ); ?></strong>
					</div>
				<?php endforeach; ?>
			</div>
			<?php
		}

		if ( ! empty( $scan['remote_stylesheets'] ) ) {
			?>
			<h3><?php esc_html_e( 'URL Google Fonts individuati', 'marrison-addon' ); ?></h3>
			<ul class="marrison-lgf-code-list">
				<?php foreach ( array_slice( $scan['remote_stylesheets'], 0, 20 ) as $url ) : ?>
					<?php $coverage = ! empty( $manifest['active'] ) ? $this->evaluate_google_stylesheet_coverage( $url, $manifest['active'] ) : null; ?>
					<li>
						<code><?php echo esc_html( $url ); ?></code>
						<?php if ( is_array( $coverage ) && ! empty( $coverage['requested'] ) ) : ?>
							<span class="marrison-lgf-diagnostic-line"><?php echo esc_html( $this->format_google_stylesheet_request( $coverage['requested'] ) ); ?></span>
							<span class="marrison-lgf-diagnostic-line">
								<?php if ( ! empty( $coverage['covered'] ) ) : ?>
									<?php esc_html_e( 'Copertura locale: COMPLETA. Azione frontend: richiesta remota rimossa.', 'marrison-addon' ); ?>
								<?php else : ?>
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: missing font variants. */
											__( 'Copertura locale: INCOMPLETA - manca %s. Azione frontend: richiesta remota mantenuta.', 'marrison-addon' ),
											$this->format_missing_google_variants( $coverage['missing'] )
										)
									);
									?>
								<?php endif; ?>
							</span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php
		}

		if ( ! empty( $scan['local_fonts'] ) && is_array( $scan['local_fonts'] ) ) {
			?>
			<h3><?php esc_html_e( 'Font locali/custom ignorati dal downloader', 'marrison-addon' ); ?></h3>
			<ul class="marrison-lgf-log">
				<?php foreach ( array_slice( $scan['local_fonts'], 0, 30 ) as $font ) : ?>
					<li><?php echo esc_html( $font['family'] . ' - ' . implode( ', ', array_slice( $font['sources'], 0, 4 ) ) ); ?></li>
				<?php endforeach; ?>
			</ul>
			<?php
		}

		if ( ! empty( $scan['ignored_fonts'] ) && is_array( $scan['ignored_fonts'] ) ) {
			?>
			<h3><?php esc_html_e( 'Falsi positivi scartati', 'marrison-addon' ); ?></h3>
			<ul class="marrison-lgf-log">
				<?php foreach ( array_slice( $scan['ignored_fonts'], 0, 30 ) as $font ) : ?>
					<li><?php echo esc_html( $font['value'] . ' - ' . $font['reason'] . ' - ' . implode( ', ', array_slice( $font['sources'], 0, 3 ) ) ); ?></li>
				<?php endforeach; ?>
			</ul>
			<?php
		}

		$warnings = ! empty( $scan['warnings'] ) && is_array( $scan['warnings'] ) ? $scan['warnings'] : array();
		$errors   = ! empty( $manifest['last_update_errors'] ) && is_array( $manifest['last_update_errors'] ) ? $manifest['last_update_errors'] : array();
		$logs     = ! empty( $manifest['last_logs'] ) && is_array( $manifest['last_logs'] ) ? $manifest['last_logs'] : array();

		if ( ! empty( $warnings ) ) {
			?>
			<h3><?php esc_html_e( 'Avvisi scansione', 'marrison-addon' ); ?></h3>
			<ul class="marrison-lgf-log">
				<?php foreach ( $warnings as $warning ) : ?>
					<li class="warning"><?php echo esc_html( $warning ); ?></li>
				<?php endforeach; ?>
			</ul>
			<?php
		}

		if ( ! empty( $errors ) ) {
			?>
			<h3><?php esc_html_e( 'Errori ultimo aggiornamento', 'marrison-addon' ); ?></h3>
			<ul class="marrison-lgf-log">
				<?php foreach ( $errors as $error ) : ?>
					<li class="error"><?php echo esc_html( $error ); ?></li>
				<?php endforeach; ?>
			</ul>
			<?php
		}

		if ( ! empty( $logs ) ) {
			?>
			<h3><?php esc_html_e( 'Log recente', 'marrison-addon' ); ?></h3>
			<ul class="marrison-lgf-log">
				<?php foreach ( array_slice( $logs, -30 ) as $log ) : ?>
					<li><?php echo esc_html( $log ); ?></li>
				<?php endforeach; ?>
			</ul>
			<?php
		}
	}

	private function scan_fonts() {
		$this->scan_ignored_fonts = array();
		$this->scan_local_fonts   = array();
		$this->scan_google_fonts  = array();

		$fonts             = array();
		$remote_stylesheets = array();
		$warnings          = array();
		$elementor_document_ids = array();
		$stats             = array(
			'elementor_posts' => 0,
			'post_content'    => 0,
			'css_files'       => 0,
			'css_bytes'       => 0,
			'source_files'    => 0,
			'source_bytes'    => 0,
		);

		$this->scan_elementor_data( $fonts, $stats, $warnings, $elementor_document_ids );
		$this->scan_elementor_global_settings( $fonts, $warnings );
		$this->scan_post_content( $fonts, $remote_stylesheets, $stats, $warnings );
		$this->scan_additional_css( $fonts, $remote_stylesheets );
		$this->scan_css_files( $fonts, $remote_stylesheets, $stats, $warnings, $elementor_document_ids );

		$this->classify_fonts( $fonts );
		$fonts = $this->prepare_fonts_for_manifest( $fonts );
		ksort( $remote_stylesheets );

		return array(
			'scanned_at'         => current_time( 'mysql' ),
			'fonts'              => $fonts,
			'remote_stylesheets' => array_values( $remote_stylesheets ),
			'local_fonts'        => $this->prepare_local_fonts_for_manifest(),
			'ignored_fonts'      => $this->prepare_ignored_fonts_for_manifest(),
			'warnings'           => array_values( array_unique( $warnings ) ),
			'stats'              => $stats,
		);
	}

	private function scan_elementor_data( &$fonts, &$stats, &$warnings, &$elementor_document_ids ) {
		global $wpdb;

		if ( empty( $wpdb ) ) {
			return;
		}

		$limit  = 100;
		$offset = 0;

		do {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.ID, p.post_title, p.post_type, p.post_status, pm.meta_value
					FROM {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
					WHERE pm.meta_key = %s
						AND p.post_type <> %s
						AND p.post_status NOT IN ('trash', 'auto-draft')
					ORDER BY p.ID ASC
					LIMIT %d OFFSET %d",
					'_elementor_data',
					'revision',
					$limit,
					$offset
				)
			);

			if ( empty( $rows ) ) {
				break;
			}

			foreach ( $rows as $row ) {
				if ( ! $this->is_current_frontend_document_status( $row->post_status ) ) {
					continue;
				}

				$stats['elementor_posts']++;
				$elementor_document_ids[ absint( $row->ID ) ] = absint( $row->ID );
				$source = $this->format_post_source( $row, true );

				$data = json_decode( wp_unslash( $row->meta_value ), true );
				if ( ! is_array( $data ) ) {
					continue;
				}

				$this->scan_elementor_payload( $data, $source, $fonts );
			}

			$offset += $limit;
		} while ( count( $rows ) === $limit );

		if ( 0 === (int) $stats['elementor_posts'] ) {
			$warnings[] = __( 'Nessun contenuto Elementor con _elementor_data trovato durante la scansione.', 'marrison-addon' );
		}
	}

	private function scan_elementor_global_settings( &$fonts, &$warnings ) {
		$options = array(
			'elementor_scheme_typography',
			'elementor_global_typography',
		);

		foreach ( $options as $option_name ) {
			$value = get_option( $option_name, null );
			if ( is_array( $value ) ) {
				$this->scan_elementor_payload( $value, 'Elementor option: ' . $option_name, $fonts );
			}
		}

		$kit_id = absint( get_option( 'elementor_active_kit' ) );
		if ( $kit_id ) {
			$page_settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
			if ( is_array( $page_settings ) ) {
				$this->scan_elementor_payload( $page_settings, 'Elementor active kit', $fonts );
			}
		} elseif ( did_action( 'elementor/loaded' ) ) {
			$warnings[] = __( 'Elementor risulta attivo ma non e stato trovato un kit globale attivo.', 'marrison-addon' );
		}
	}

	private function scan_elementor_payload( $payload, $source, &$fonts ) {
		if ( ! is_array( $payload ) ) {
			return;
		}

		$this->collect_elementor_settings_fonts( $payload, $source, $fonts );

		foreach ( $payload as $value ) {
			if ( is_array( $value ) ) {
				$this->scan_elementor_payload( $value, $source, $fonts );
			}
		}
	}

	private function collect_elementor_settings_fonts( $settings, $source, &$fonts ) {
		foreach ( $settings as $key => $value ) {
			if ( ! is_string( $key ) || false === strpos( $key, 'font_family' ) || ! is_scalar( $value ) ) {
				continue;
			}

			$family = $this->normalize_font_family( (string) $value );
			if ( '' === $family ) {
				continue;
			}

			$prefix = substr( $key, 0, strpos( $key, 'font_family' ) );
			$weight = $this->find_related_setting_value(
				$settings,
				array(
					$prefix . 'font_weight',
					$prefix . 'typography_font_weight',
					str_replace( 'font_family', 'font_weight', $key ),
				)
			);
			$style = $this->find_related_setting_value(
				$settings,
				array(
					$prefix . 'font_style',
					$prefix . 'typography_font_style',
					str_replace( 'font_family', 'font_style', $key ),
				)
			);

			$this->add_font_variant( $fonts, $family, $weight, $style, $source );

			if ( $this->is_elementor_google_font( $family ) ) {
				$this->mark_google_font_family( $family, $source );
			}
		}
	}

	private function find_related_setting_value( $settings, $keys ) {
		foreach ( $keys as $key ) {
			if ( isset( $settings[ $key ] ) && is_scalar( $settings[ $key ] ) && '' !== (string) $settings[ $key ] ) {
				return (string) $settings[ $key ];
			}
		}

		return '';
	}

	private function is_current_frontend_document_status( $status ) {
		$allowed_statuses = apply_filters(
			'marrison_addon/local_google_fonts/scannable_statuses',
			array( 'publish', 'private', 'inherit' )
		);

		return in_array( (string) $status, (array) $allowed_statuses, true );
	}

	private function format_post_source( $post, $is_elementor ) {
		$title = ! empty( $post->post_title ) ? $post->post_title : '#' . $post->ID;

		if ( $is_elementor && 'elementor_library' === $post->post_type ) {
			return sprintf(
				/* translators: %s: Elementor template title. */
				__( 'Elementor Template: %s', 'marrison-addon' ),
				$title
			);
		}

		if ( $is_elementor ) {
			return sprintf(
				/* translators: %s: page title. */
				__( 'Elementor: %s', 'marrison-addon' ),
				$title
			);
		}

		return sprintf(
			/* translators: 1: post type, 2: post title. */
			__( 'Contenuto %1$s: %2$s', 'marrison-addon' ),
			$post->post_type,
			$title
		);
	}

	private function scan_post_content( &$fonts, &$remote_stylesheets, &$stats, &$warnings ) {
		global $wpdb;

		if ( empty( $wpdb ) ) {
			return;
		}

		$limit  = 100;
		$offset = 0;

		do {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID, post_title, post_type, post_status, post_content
					FROM {$wpdb->posts}
					WHERE post_status NOT IN ('trash', 'auto-draft')
						AND post_type <> %s
						AND (post_content LIKE %s OR post_content LIKE %s)
					ORDER BY ID ASC
					LIMIT %d OFFSET %d",
					'revision',
					'%font-family%',
					'%fonts.googleapis.com%',
					$limit,
					$offset
				)
			);

			if ( empty( $rows ) ) {
				break;
			}

			foreach ( $rows as $row ) {
				if ( ! $this->is_current_frontend_document_status( $row->post_status ) ) {
					continue;
				}

				$stats['post_content']++;
				$source = $this->format_post_source( $row, false );
				$this->extract_fonts_from_html( $row->post_content, $source, $fonts, $remote_stylesheets );
			}

			$offset += $limit;
		} while ( count( $rows ) === $limit );
	}

	private function scan_additional_css( &$fonts, &$remote_stylesheets ) {
		if ( ! function_exists( 'wp_get_custom_css' ) ) {
			return;
		}

		$custom_css = wp_get_custom_css();
		if ( is_string( $custom_css ) && '' !== trim( $custom_css ) ) {
			$this->extract_fonts_from_css( $custom_css, __( 'CSS personalizzato WordPress', 'marrison-addon' ), $fonts, $remote_stylesheets );
		}
	}

	private function scan_css_files( &$fonts, &$remote_stylesheets, &$stats, &$warnings, $elementor_document_ids ) {
		$scan_state = array(
			'files' => 0,
			'bytes' => 0,
			'limit_reached' => false,
		);
		$source_scan_state = array(
			'files' => 0,
			'bytes' => 0,
			'limit_reached' => false,
		);

		$uploads = wp_upload_dir();
		if ( empty( $uploads['error'] ) && ! empty( $uploads['basedir'] ) ) {
			$elementor_css_dir = trailingslashit( $uploads['basedir'] ) . 'elementor/css';
			$this->scan_elementor_css_files( $elementor_css_dir, $elementor_document_ids, $fonts, $remote_stylesheets, $stats, $warnings, $scan_state );
		}

		$theme_dirs = array_unique(
			array_filter(
				array(
					function_exists( 'get_stylesheet_directory' ) ? get_stylesheet_directory() : '',
					function_exists( 'get_template_directory' ) ? get_template_directory() : '',
				)
			)
		);

		foreach ( $theme_dirs as $theme_dir ) {
			$this->scan_css_dir( $theme_dir, 'Theme CSS', $fonts, $remote_stylesheets, $stats, $warnings, $scan_state );
			$this->scan_google_stylesheet_source_dir( $theme_dir, 'Theme source', $fonts, $remote_stylesheets, $stats, $warnings, $source_scan_state );
		}

		foreach ( $this->get_active_plugin_dirs() as $plugin_dir ) {
			$this->scan_css_dir( $plugin_dir, 'Plugin CSS', $fonts, $remote_stylesheets, $stats, $warnings, $scan_state );
			$this->scan_google_stylesheet_source_dir( $plugin_dir, 'Plugin source', $fonts, $remote_stylesheets, $stats, $warnings, $source_scan_state );
		}

		if ( $scan_state['limit_reached'] ) {
			$warnings[] = sprintf(
				/* translators: %d: max css files. */
				__( 'La scansione CSS si e fermata al limite di %d file per evitare un lavoro eccessivo.', 'marrison-addon' ),
				self::MAX_CSS_FILES
			);
		}

		if ( $source_scan_state['limit_reached'] ) {
			$warnings[] = sprintf(
				/* translators: %d: max source files. */
				__( 'La scansione dei sorgenti si e fermata al limite di %d file per evitare un lavoro eccessivo.', 'marrison-addon' ),
				self::MAX_SOURCE_FILES
			);
		}
	}

	private function scan_css_dir( $dir, $label, &$fonts, &$remote_stylesheets, &$stats, &$warnings, &$scan_state ) {
		if ( empty( $dir ) || ! is_dir( $dir ) || ! is_readable( $dir ) || $scan_state['limit_reached'] ) {
			return;
		}

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS )
			);
		} catch ( Exception $e ) {
			$warnings[] = sprintf(
				/* translators: 1: directory, 2: error message. */
				__( 'Directory CSS non leggibile: %1$s (%2$s)', 'marrison-addon' ),
				$dir,
				$e->getMessage()
			);
			return;
		}

		foreach ( $iterator as $file ) {
			if ( $scan_state['files'] >= self::MAX_CSS_FILES || $scan_state['bytes'] >= self::MAX_TOTAL_CSS_BYTES ) {
				$scan_state['limit_reached'] = true;
				return;
			}

			if ( ! $file->isFile() || 'css' !== strtolower( $file->getExtension() ) ) {
				continue;
			}

			$path = $file->getPathname();
			$size = $file->getSize();

			if ( $size <= 0 || $size > self::MAX_CSS_FILE_BYTES || ! is_readable( $path ) ) {
				continue;
			}

			$contents = file_get_contents( $path );
			if ( false === $contents ) {
				continue;
			}

			$scan_state['files']++;
			$scan_state['bytes'] += $size;
			$stats['css_files']++;
			$stats['css_bytes'] += $size;

			$this->extract_fonts_from_css( $contents, $label . ': ' . basename( $path ), $fonts, $remote_stylesheets );
		}
	}

	private function scan_google_stylesheet_source_dir( $dir, $label, &$fonts, &$remote_stylesheets, &$stats, &$warnings, &$scan_state ) {
		if ( empty( $dir ) || ! is_dir( $dir ) || ! is_readable( $dir ) || $scan_state['limit_reached'] ) {
			return;
		}

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS )
			);
		} catch ( Exception $e ) {
			$warnings[] = sprintf(
				/* translators: 1: directory, 2: error message. */
				__( 'Directory sorgenti non leggibile: %1$s (%2$s)', 'marrison-addon' ),
				$dir,
				$e->getMessage()
			);
			return;
		}

		foreach ( $iterator as $file ) {
			if ( $scan_state['files'] >= self::MAX_SOURCE_FILES || $scan_state['bytes'] >= self::MAX_TOTAL_SOURCE_BYTES ) {
				$scan_state['limit_reached'] = true;
				return;
			}

			if ( ! $file->isFile() || ! $this->is_google_stylesheet_source_file( $file->getExtension() ) ) {
				continue;
			}

			$path = $file->getPathname();
			$size = $file->getSize();

			if ( $size <= 0 || $size > self::MAX_SOURCE_FILE_BYTES || ! is_readable( $path ) ) {
				continue;
			}

			$contents = file_get_contents( $path );
			if ( false === $contents ) {
				continue;
			}

			$scan_state['files']++;
			$scan_state['bytes'] += $size;
			$stats['source_files']++;
			$stats['source_bytes'] += $size;

			if ( false === stripos( $contents, 'fonts.googleapis.com' ) ) {
				continue;
			}

			$this->extract_remote_google_stylesheets( $contents, $remote_stylesheets, $fonts, $label . ': ' . basename( $path ) );
		}
	}

	private function is_google_stylesheet_source_file( $extension ) {
		return in_array( strtolower( (string) $extension ), array( 'php', 'js', 'html', 'htm', 'twig' ), true );
	}

	private function scan_elementor_css_files( $dir, $elementor_document_ids, &$fonts, &$remote_stylesheets, &$stats, &$warnings, &$scan_state ) {
		if ( empty( $dir ) || ! is_dir( $dir ) || ! is_readable( $dir ) || $scan_state['limit_reached'] ) {
			return;
		}

		$files = array(
			trailingslashit( $dir ) . 'global.css' => 'Elementor generated CSS: global.css',
		);

		foreach ( (array) $elementor_document_ids as $document_id ) {
			$document_id = absint( $document_id );
			if ( $document_id > 0 ) {
				$files[ trailingslashit( $dir ) . 'post-' . $document_id . '.css' ] = 'Elementor generated CSS: post-' . $document_id . '.css';
			}
		}

		foreach ( $files as $path => $source ) {
			if ( $scan_state['files'] >= self::MAX_CSS_FILES || $scan_state['bytes'] >= self::MAX_TOTAL_CSS_BYTES ) {
				$scan_state['limit_reached'] = true;
				return;
			}

			if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
				continue;
			}

			$size = filesize( $path );
			if ( $size <= 0 || $size > self::MAX_CSS_FILE_BYTES ) {
				continue;
			}

			$contents = file_get_contents( $path );
			if ( false === $contents ) {
				continue;
			}

			$scan_state['files']++;
			$scan_state['bytes'] += $size;
			$stats['css_files']++;
			$stats['css_bytes'] += $size;

			$this->extract_fonts_from_css( $contents, $source, $fonts, $remote_stylesheets );
		}
	}

	private function get_active_plugin_dirs() {
		$plugin_dirs = array();
		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			$sitewide = (array) get_site_option( 'active_sitewide_plugins', array() );
			$active_plugins = array_merge( $active_plugins, array_keys( $sitewide ) );
		}

		foreach ( array_unique( $active_plugins ) as $plugin_file ) {
			$plugin_file = plugin_basename( $plugin_file );
			$path        = WP_PLUGIN_DIR . '/' . $plugin_file;
			$dir         = dirname( $path );

			if ( is_dir( $dir ) ) {
				$plugin_dirs[] = $dir;
			}
		}

		return array_unique( $plugin_dirs );
	}

	private function extract_fonts_from_html( $html, $source, &$fonts, &$remote_stylesheets ) {
		$this->extract_remote_google_stylesheets( $html, $remote_stylesheets, $fonts, $source );

		if ( preg_match_all( '/<style\b[^>]*>(.*?)<\/style>/is', $html, $matches ) ) {
			foreach ( $matches[1] as $css ) {
				$this->extract_fonts_from_css( html_entity_decode( $css, ENT_QUOTES, get_bloginfo( 'charset' ) ), $source, $fonts, $remote_stylesheets );
			}
		}

		if ( preg_match_all( '/\sstyle=(["\'])(.*?)\1/is', $html, $matches ) ) {
			foreach ( $matches[2] as $style_attr ) {
				$declarations = html_entity_decode( $style_attr, ENT_QUOTES, get_bloginfo( 'charset' ) );
				$this->extract_fonts_from_declaration_block( $declarations, $source, $fonts );
			}
		}
	}

	private function extract_fonts_from_css( $css, $source, &$fonts, &$remote_stylesheets ) {
		if ( ! is_string( $css ) || '' === trim( $css ) ) {
			return;
		}

		$this->extract_remote_google_stylesheets( $css, $remote_stylesheets, $fonts, $source );
		$this->extract_font_face_definitions( $css, $source );

		$css = preg_replace( '!/\*.*?\*/!s', '', $css );
		if ( ! is_string( $css ) ) {
			return;
		}

		$css = preg_replace( '/@font-face\s*\{[^{}]*\}/i', '', $css );
		if ( ! is_string( $css ) ) {
			return;
		}

		if ( preg_match_all( '/\{([^{}]*font-family[^{}]*)\}/i', $css, $blocks ) ) {
			foreach ( $blocks[1] as $block ) {
				$this->extract_fonts_from_declaration_block( $block, $source, $fonts );
			}
		}
	}

	private function extract_fonts_from_declaration_block( $block, $source, &$fonts ) {
		$declarations = $this->parse_css_declarations( $block );
		if ( empty( $declarations['font-family'] ) ) {
			return;
		}

		$raw_family = $this->get_first_font_family_token( $declarations['font-family'] );
		$family     = $this->normalize_font_family( $raw_family['value'] );
		if ( '' === $family ) {
			if ( '' !== $raw_family['raw'] ) {
				$this->mark_ignored_font_family( $raw_family['raw'], $source, $raw_family['reason'] );
			}
			return;
		}

		$weight = isset( $declarations['font-weight'] ) ? $declarations['font-weight'] : '';
		$style  = isset( $declarations['font-style'] ) ? $declarations['font-style'] : '';

		$this->add_font_variant( $fonts, $family, $weight, $style, $source );
	}

	private function extract_remote_google_stylesheets( $content, &$remote_stylesheets, &$fonts, $source ) {
		if ( ! preg_match_all( '#(?:https?:)?//fonts\.googleapis\.com/[^\'"\)\s<>]+#i', $content, $matches ) ) {
			return;
		}

		foreach ( $matches[0] as $url ) {
			$url = html_entity_decode( $url, ENT_QUOTES, 'UTF-8' );
			if ( 0 === strpos( $url, '//' ) ) {
				$url = 'https:' . $url;
			}
			$remote_stylesheets[ $url ] = $url;

			$families = $this->parse_google_stylesheet_families( $url );
			foreach ( $families as $family => $variants ) {
				$source_label = sprintf(
					/* translators: %s: Google Font family. */
					__( 'Google Fonts URL: %s', 'marrison-addon' ),
					$family
				);

				$this->mark_google_font_family(
					$family,
					$source_label,
					$variants
				);

				foreach ( $variants as $variant ) {
					if ( empty( $variant['weight'] ) || empty( $variant['style'] ) ) {
						continue;
					}

					$this->add_font_variant( $fonts, $family, $variant['weight'], $variant['style'], $source_label );
				}
			}
		}
	}

	private function extract_font_face_definitions( $css, $source ) {
		if ( ! preg_match_all( '/@font-face\s*\{([^}]*)\}/i', $css, $matches ) ) {
			return;
		}

		foreach ( $matches[1] as $block ) {
			$declarations = $this->parse_css_declarations( $block );
			if ( empty( $declarations['font-family'] ) || empty( $declarations['src'] ) ) {
				continue;
			}

			$raw_family = $this->get_first_font_family_token( $declarations['font-family'] );
			$family     = $this->normalize_font_family( $raw_family['value'] );
			if ( '' === $family ) {
				continue;
			}

			if ( $this->src_contains_google_font_file( $declarations['src'] ) ) {
				$this->mark_google_font_family( $family, $source );
				continue;
			}

			if ( $this->src_contains_local_font_file( $declarations['src'] ) ) {
				$this->mark_local_font_family( $family, $source );
			}
		}
	}

	private function src_contains_google_font_file( $src ) {
		return false !== stripos( $src, 'fonts.gstatic.com' );
	}

	private function src_contains_local_font_file( $src ) {
		return (bool) preg_match( '/\.(woff2?|ttf|otf|eot)(?:[?#][^\'"\)]*)?/i', $src );
	}

	private function mark_google_font_family( $family, $source, $declared_variants = array() ) {
		$family = $this->normalize_font_family( $family );
		if ( '' === $family ) {
			return;
		}

		$key = $this->family_key( $family );
		if ( empty( $this->scan_google_fonts[ $key ] ) ) {
			$this->scan_google_fonts[ $key ] = array(
				'family' => $family,
				'sources' => array(),
				'declared_variants' => array(),
			);
		}

		$this->append_limited_unique( $this->scan_google_fonts[ $key ]['sources'], $source );

		foreach ( (array) $declared_variants as $variant ) {
			if ( empty( $variant['weight'] ) || empty( $variant['style'] ) ) {
				continue;
			}

			$variant_key = $this->variant_key( $variant['style'], $variant['weight'] );
			$this->scan_google_fonts[ $key ]['declared_variants'][ $variant_key ] = array(
				'weight' => $this->normalize_font_weight( $variant['weight'] ),
				'style'  => $this->normalize_font_style( $variant['style'] ),
			);
		}
	}

	private function mark_local_font_family( $family, $source ) {
		$family = $this->normalize_font_family( $family );
		if ( '' === $family ) {
			return;
		}

		$key = $this->family_key( $family );
		if ( empty( $this->scan_local_fonts[ $key ] ) ) {
			$this->scan_local_fonts[ $key ] = array(
				'family' => $family,
				'sources' => array(),
			);
		}

		$this->append_limited_unique( $this->scan_local_fonts[ $key ]['sources'], $source );
	}

	private function mark_ignored_font_family( $value, $source, $reason ) {
		$value = trim( wp_strip_all_tags( (string) $value ) );
		if ( '' === $value ) {
			return;
		}

		$key = strtolower( $value . '|' . $reason );
		if ( empty( $this->scan_ignored_fonts[ $key ] ) ) {
			$this->scan_ignored_fonts[ $key ] = array(
				'value'   => $value,
				'reason'  => $reason,
				'sources' => array(),
			);
		}

		$this->append_limited_unique( $this->scan_ignored_fonts[ $key ]['sources'], $source );
	}

	private function get_first_font_family_token( $value ) {
		$raw = trim( html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' ) );
		$raw = preg_replace( '/\s*!important\s*$/i', '', $raw );
		$raw = is_string( $raw ) ? trim( $raw ) : '';

		if ( '' === $raw ) {
			return array(
				'raw'    => '',
				'value'  => '',
				'reason' => __( 'Valore vuoto.', 'marrison-addon' ),
			);
		}

		$items = $this->split_css_font_family_list( $raw );
		$first = isset( $items[0] ) ? trim( $items[0] ) : '';

		if ( '' === $first ) {
			return array(
				'raw'    => $raw,
				'value'  => '',
				'reason' => __( 'Lista font-family vuota.', 'marrison-addon' ),
			);
		}

		if ( $this->has_unbalanced_quotes( $first ) ) {
			return array(
				'raw'    => $first,
				'value'  => '',
				'reason' => __( 'Virgolette non bilanciate.', 'marrison-addon' ),
			);
		}

		if ( preg_match( '/[{};:]|\b(var|url)\s*\(/i', $first ) ) {
			return array(
				'raw'    => $first,
				'value'  => '',
				'reason' => __( 'Valore CSS non valido come famiglia font.', 'marrison-addon' ),
			);
		}

		$value = trim( $first );
		if ( strlen( $value ) >= 2 ) {
			$first_char = substr( $value, 0, 1 );
			$last_char  = substr( $value, -1 );
			if ( ( '"' === $first_char && '"' === $last_char ) || ( "'" === $first_char && "'" === $last_char ) ) {
				$value = substr( $value, 1, -1 );
			}
		}

		$value = stripcslashes( trim( $value ) );

		return array(
			'raw'    => $first,
			'value'  => $value,
			'reason' => $this->is_system_font_family( $value ) ? __( 'Generic/system font family.', 'marrison-addon' ) : __( 'Font family non valida.', 'marrison-addon' ),
		);
	}

	private function split_css_font_family_list( $value ) {
		$items = array();
		$current = '';
		$quote = '';
		$length = strlen( $value );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $value[ $i ];

			if ( '' !== $quote ) {
				$current .= $char;
				if ( $char === $quote && ( 0 === $i || '\\' !== $value[ $i - 1 ] ) ) {
					$quote = '';
				}
				continue;
			}

			if ( '"' === $char || "'" === $char ) {
				$quote = $char;
				$current .= $char;
				continue;
			}

			if ( ',' === $char ) {
				$items[] = $current;
				$current = '';
				continue;
			}

			$current .= $char;
		}

		$items[] = $current;

		return $items;
	}

	private function has_unbalanced_quotes( $value ) {
		$single = 0;
		$double = 0;
		$length = strlen( $value );

		for ( $i = 0; $i < $length; $i++ ) {
			if ( $i > 0 && '\\' === $value[ $i - 1 ] ) {
				continue;
			}

			if ( "'" === $value[ $i ] ) {
				$single++;
			} elseif ( '"' === $value[ $i ] ) {
				$double++;
			}
		}

		return 0 !== $single % 2 || 0 !== $double % 2;
	}

	private function add_font_variant( &$fonts, $family, $weight = '', $style = '', $source = '' ) {
		$family = $this->normalize_font_family( $family );
		if ( '' === $family ) {
			return;
		}

		$weight = $this->normalize_font_weight( $weight );
		$style  = $this->normalize_font_style( $style );
		$family_key = $this->family_key( $family );
		$variant_key = $this->variant_key( $style, $weight );

		if ( empty( $fonts[ $family_key ] ) ) {
			$fonts[ $family_key ] = array(
				'family'   => $family,
				'variants' => array(),
				'sources'  => array(),
			);
		}

		if ( empty( $fonts[ $family_key ]['variants'][ $variant_key ] ) ) {
			$fonts[ $family_key ]['variants'][ $variant_key ] = array(
				'weight'  => $weight,
				'style'   => $style,
				'sources' => array(),
			);
		}

		if ( '' !== $source ) {
			$this->append_limited_unique( $fonts[ $family_key ]['sources'], $source );
			$this->append_limited_unique( $fonts[ $family_key ]['variants'][ $variant_key ]['sources'], $source );
		}
	}

	private function append_limited_unique( &$items, $value, $limit = 8 ) {
		$value = trim( wp_strip_all_tags( (string) $value ) );
		if ( '' === $value || in_array( $value, $items, true ) ) {
			return;
		}

		if ( count( $items ) < $limit ) {
			$items[] = $value;
		}
	}

	private function prepare_fonts_for_manifest( $fonts ) {
		uasort(
			$fonts,
			function ( $a, $b ) {
				return strcasecmp( $a['family'], $b['family'] );
			}
		);

		foreach ( $fonts as &$font ) {
			uasort(
				$font['variants'],
				function ( $a, $b ) {
					$style_compare = strcmp( $a['style'], $b['style'] );
					if ( 0 !== $style_compare ) {
						return $style_compare;
					}

					return (int) $a['weight'] <=> (int) $b['weight'];
				}
			);
		}

		return $fonts;
	}

	private function classify_fonts( &$fonts ) {
		foreach ( $fonts as $family_key => &$font ) {
			$family = isset( $font['family'] ) ? $font['family'] : '';
			$key    = $this->family_key( $family );

			if ( isset( $this->scan_google_fonts[ $key ] ) ) {
				$font['classification'] = 'google';
				$font['classification_label'] = __( 'Google Font confermato', 'marrison-addon' );
				$font['evidence_sources'] = $this->scan_google_fonts[ $key ]['sources'];

				foreach ( $this->scan_google_fonts[ $key ]['sources'] as $source ) {
					$this->append_limited_unique( $font['sources'], $source );
				}
				continue;
			}

			if ( isset( $this->scan_local_fonts[ $key ] ) ) {
				$font['classification'] = 'local';
				$font['classification_label'] = __( 'Font locale/custom', 'marrison-addon' );
				$font['evidence_sources'] = $this->scan_local_fonts[ $key ]['sources'];

				foreach ( $this->scan_local_fonts[ $key ]['sources'] as $source ) {
					$this->append_limited_unique( $font['sources'], $source );
				}
				continue;
			}

			$font['classification'] = 'unknown';
			$font['classification_label'] = __( 'Famiglia sconosciuta/non verificata', 'marrison-addon' );
			$font['evidence_sources'] = array();
		}
		unset( $font );
	}

	private function prepare_local_fonts_for_manifest() {
		$fonts = $this->scan_local_fonts;
		uasort(
			$fonts,
			function ( $a, $b ) {
				return strcasecmp( $a['family'], $b['family'] );
			}
		);

		return array_values( $fonts );
	}

	private function prepare_ignored_fonts_for_manifest() {
		$fonts = $this->scan_ignored_fonts;
		uasort(
			$fonts,
			function ( $a, $b ) {
				return strcasecmp( $a['value'], $b['value'] );
			}
		);

		return array_values( $fonts );
	}

	private function format_font_classification( $classification ) {
		$labels = array(
			'google'  => __( 'Google Font confermato', 'marrison-addon' ),
			'local'   => __( 'Font locale/custom', 'marrison-addon' ),
			'unknown' => __( 'Sconosciuto/non verificato', 'marrison-addon' ),
		);

		return isset( $labels[ $classification ] ) ? $labels[ $classification ] : $labels['unknown'];
	}

	private function format_google_stylesheet_request( $requested ) {
		$parts = array();

		foreach ( (array) $requested as $family => $variants ) {
			$variant_labels = array();

			foreach ( (array) $variants as $variant ) {
				if ( empty( $variant['weight'] ) || empty( $variant['style'] ) ) {
					continue;
				}

				$style = $this->normalize_font_style( $variant['style'] );
				$weight = $this->normalize_font_weight( $variant['weight'] );
				$variant_labels[] = 'normal' === $style ? $weight : $weight . ' ' . $style;
			}

			$parts[] = $family . ' ' . implode( ',', array_unique( $variant_labels ) );
		}

		return sprintf(
			/* translators: %s: requested font variants. */
			__( 'Google Fonts remoto: %s', 'marrison-addon' ),
			implode( ' | ', $parts )
		);
	}

	private function format_missing_google_variants( $missing ) {
		$items = array();

		foreach ( (array) $missing as $variant ) {
			if ( empty( $variant['family'] ) || empty( $variant['weight'] ) || empty( $variant['style'] ) ) {
				continue;
			}

			$style = $this->normalize_font_style( $variant['style'] );
			$weight = $this->normalize_font_weight( $variant['weight'] );
			$items[] = $variant['family'] . ' ' . $weight . ( 'normal' === $style ? '' : ' ' . $style );
		}

		return '' !== implode( ', ', $items ) ? implode( ', ', $items ) : __( 'varianti non disponibili', 'marrison-addon' );
	}

	private function download_fonts_from_scan( $scan ) {
		if ( empty( $scan['fonts'] ) || ! is_array( $scan['fonts'] ) ) {
			return new WP_Error( 'marrison_lgf_no_fonts', __( 'Nessun font da scaricare.', 'marrison-addon' ) );
		}

		$google_fonts = array_filter(
			$scan['fonts'],
			function ( $font ) {
				return ! empty( $font['classification'] ) && 'google' === $font['classification'];
			}
		);

		if ( empty( $google_fonts ) ) {
			return new WP_Error(
				'marrison_lgf_no_confirmed_google_fonts',
				__( 'Nessun Google Font confermato da scaricare. Le famiglie locali/custom o non verificate sono state ignorate.', 'marrison-addon' ),
				array( 'logs' => $this->build_ignored_download_logs( $scan ) )
			);
		}

		$storage = $this->get_storage();
		if ( is_wp_error( $storage ) ) {
			return $storage;
		}

		$logs          = array();
		$css_rules     = array();
		$active_fonts  = array();
		$external_local_fonts = array(
			'elementor' => $this->get_elementor_local_google_fonts_map(),
		);
		$fatal_errors  = array();
		$skipped       = array();
		$source_css_urls = array();

		foreach ( $google_fonts as $font ) {
			if ( empty( $font['family'] ) || empty( $font['variants'] ) || ! is_array( $font['variants'] ) ) {
				continue;
			}

			$family   = $font['family'];
			$variants = array_values( $font['variants'] );
			$variants_to_download = $this->filter_variants_missing_from_external_local_fonts( $family, $variants, $external_local_fonts );

			if ( empty( $variants_to_download ) ) {
				$logs[] = sprintf(
					/* translators: %s: font family. */
					__( '%s: tutte le varianti richieste sono gia servite localmente da Elementor.', 'marrison-addon' ),
					$family
				);
				continue;
			}

			$variants = $variants_to_download;
			$css_url  = $this->build_google_css_url( $family, $variants );

			if ( '' === $css_url ) {
				$skipped[] = sprintf(
					/* translators: %s: font family. */
					__( '%s saltato: varianti non valide.', 'marrison-addon' ),
					$family
				);
				continue;
			}

			$response = wp_remote_get(
				$css_url,
				array(
					'timeout'     => 20,
					'redirection' => 3,
					'user-agent'  => self::GOOGLE_CSS_USER_AGENT,
				)
			);

			if ( is_wp_error( $response ) ) {
				$fatal_errors[] = sprintf(
					/* translators: 1: font family, 2: error message. */
					__( '%1$s: errore richiesta CSS Google Fonts: %2$s', 'marrison-addon' ),
					$family,
					$response->get_error_message()
				);
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );

			if ( in_array( $code, array( 400, 404 ), true ) ) {
				$skipped[] = sprintf(
					/* translators: %s: font family. */
					__( '%s non confermato come Google Font dalla stylesheet Google.', 'marrison-addon' ),
					$family
				);
				continue;
			}

			if ( 200 !== $code || '' === trim( (string) $body ) ) {
				$fatal_errors[] = sprintf(
					/* translators: 1: font family, 2: HTTP status. */
					__( '%1$s: risposta Google Fonts non valida (HTTP %2$d).', 'marrison-addon' ),
					$family,
					$code
				);
				continue;
			}

			$faces = $this->parse_google_font_faces( $body );
			if ( empty( $faces ) ) {
				$skipped[] = sprintf(
					/* translators: %s: font family. */
					__( '%s saltato: nessun @font-face WOFF2 leggibile nella risposta Google.', 'marrison-addon' ),
					$family
				);
				continue;
			}

			$source_css_urls[] = $css_url;
			$family_key = $this->family_key( $family );
			$active_fonts[ $family_key ] = array(
				'family'   => $family,
				'variants' => array(),
				'files'    => array(),
			);

			foreach ( $faces as $index => $face ) {
				if ( $this->family_key( $face['family'] ) !== $family_key ) {
					continue;
				}

				if ( $this->external_local_fonts_have_variant( $external_local_fonts, $family, $face['style'], $face['weight'] ) ) {
					continue;
				}

				if ( ! $this->is_allowed_google_font_file_url( $face['src'] ) ) {
					$fatal_errors[] = sprintf(
						/* translators: %s: font family. */
						__( '%s: URL font non consentito nella risposta Google.', 'marrison-addon' ),
						$family
					);
					continue;
				}

				$font_filename = $this->build_font_filename( $family, $face, $index );
				$font_path     = trailingslashit( $storage['dir'] ) . $font_filename;

				if ( file_exists( $font_path ) && filesize( $font_path ) > 0 ) {
					$logs[] = sprintf(
						/* translators: 1: font family, 2: file name. */
						__( '%1$s: file gia presente (%2$s).', 'marrison-addon' ),
						$family,
						$font_filename
					);
				} else {
					$downloaded = $this->download_font_file( $face['src'], $font_path );
					if ( is_wp_error( $downloaded ) ) {
						$fatal_errors[] = sprintf(
							/* translators: 1: font family, 2: error message. */
							__( '%1$s: download fallito: %2$s', 'marrison-addon' ),
							$family,
							$downloaded->get_error_message()
						);
						continue;
					}

					$logs[] = sprintf(
						/* translators: 1: font family, 2: file name. */
						__( '%1$s: scaricato %2$s.', 'marrison-addon' ),
						$family,
						$font_filename
					);
				}

				$rule = $this->build_local_font_face_rule( $family, $face, $font_filename );
				if ( '' !== $rule ) {
					$css_rules[] = $rule;
				}

				$active_fonts[ $family_key ]['files'][] = $font_filename;
				$variant_key = $this->variant_key( $face['style'], $face['weight'] );
				$active_fonts[ $family_key ]['variants'][ $variant_key ] = array(
					'style'  => $this->normalize_font_style( $face['style'] ),
					'weight' => $this->normalize_font_weight( $face['weight'] ),
				);
			}

			if ( empty( $active_fonts[ $family_key ]['files'] ) ) {
				unset( $active_fonts[ $family_key ] );
			}
		}

		if ( ! empty( $fatal_errors ) ) {
			return new WP_Error(
				'marrison_lgf_download_failed',
				__( 'Aggiornamento interrotto: i font locali precedenti sono stati mantenuti.', 'marrison-addon' ) . ' ' . implode( ' ', array_unique( $fatal_errors ) ),
				array( 'logs' => array_merge( $logs, $fatal_errors, $skipped ) )
			);
		}

		$external_local_fonts = $this->filter_external_local_fonts_to_scan( $external_local_fonts, $google_fonts );

		if ( ( empty( $active_fonts ) || empty( $css_rules ) ) && empty( $external_local_fonts ) ) {
			return new WP_Error(
				'marrison_lgf_no_google_fonts',
				__( 'Nessun Google Font valido e stato scaricato. Il CSS locale non e stato aggiornato.', 'marrison-addon' ),
				array( 'logs' => array_merge( $logs, $skipped ) )
			);
		}

		$css_file = '';
		$css_hash = '';
		$css_path = '';

		if ( ! empty( $css_rules ) ) {
			$css = "/* Marrison Addon Local Google Fonts - generated " . current_time( 'mysql' ) . " */\n\n" . implode( "\n\n", $css_rules ) . "\n";
			$css_hash = substr( md5( $css ), 0, 12 );
			$css_file = 'local-google-fonts-' . $css_hash . '.css';
			$css_path = trailingslashit( $storage['dir'] ) . $css_file;

			$written = $this->write_file_safely( $css_path, $css );
			if ( is_wp_error( $written ) ) {
				return $written;
			}
		}

		foreach ( $skipped as $message ) {
			$logs[] = $message;
		}

		$logs = array_merge( $logs, $this->build_ignored_download_logs( $scan ) );

		if ( '' !== $css_file ) {
			$logs[] = sprintf(
				/* translators: %s: generated CSS file. */
				__( 'CSS locale generato: %s.', 'marrison-addon' ),
				$css_file
			);
		} else {
			$logs[] = __( 'CSS locale Marrison non generato: tutte le varianti Google richieste risultano gia coperte da font locali esterni.', 'marrison-addon' );
		}

		return array(
			'active' => array(
				'generated_at' => current_time( 'mysql' ),
				'css_file'     => $css_file,
				'css_url'      => '' !== $css_file ? trailingslashit( $storage['url'] ) . rawurlencode( $css_file ) : '',
				'version'      => '' !== $css_file ? $css_hash . '-' . filemtime( $css_path ) : (string) time(),
				'families'     => $active_fonts,
				'external_local_fonts' => $external_local_fonts,
				'source_css_urls' => array_values( array_unique( $source_css_urls ) ),
				'skipped'      => $skipped,
			),
			'logs' => $logs,
		);
	}

	private function download_font_file( $url, $target_path ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 30,
				'redirection' => 3,
				'user-agent'  => self::GOOGLE_CSS_USER_AGENT,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 200 !== $code || '' === (string) $body ) {
			return new WP_Error(
				'marrison_lgf_font_http_error',
				sprintf(
					/* translators: %d: HTTP status. */
					__( 'Risposta font non valida (HTTP %d).', 'marrison-addon' ),
					$code
				)
			);
		}

		if ( 'wOF2' !== substr( $body, 0, 4 ) ) {
			return new WP_Error( 'marrison_lgf_font_not_woff2', __( 'Il file scaricato non sembra un WOFF2 valido.', 'marrison-addon' ) );
		}

		return $this->write_file_safely( $target_path, $body );
	}

	private function get_elementor_local_google_fonts_map() {
		$map = array();
		$uploads = wp_upload_dir();

		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) || empty( $uploads['baseurl'] ) ) {
			return $map;
		}

		$css_dir = trailingslashit( wp_normalize_path( $uploads['basedir'] ) ) . self::ELEMENTOR_GOOGLE_FONTS_CSS_SUBDIR;
		if ( ! is_dir( $css_dir ) || ! is_readable( $css_dir ) ) {
			return $map;
		}

		$files = glob( trailingslashit( $css_dir ) . '*.css' );
		if ( empty( $files ) || ! is_array( $files ) ) {
			return $map;
		}

		foreach ( $files as $css_path ) {
			if ( ! is_readable( $css_path ) || filesize( $css_path ) <= 0 || filesize( $css_path ) > self::MAX_CSS_FILE_BYTES ) {
				continue;
			}

			$css = file_get_contents( $css_path );
			if ( ! is_string( $css ) || '' === trim( $css ) ) {
				continue;
			}

			foreach ( $this->parse_google_font_faces( $css ) as $face ) {
				if ( empty( $face['family'] ) || empty( $face['style'] ) || empty( $face['weight'] ) || empty( $face['src'] ) ) {
					continue;
				}

				$font_path = $this->resolve_local_font_path_from_css_url( $face['src'], $css_path, $uploads );
				if ( '' === $font_path || ! file_exists( $font_path ) || filesize( $font_path ) <= 0 ) {
					continue;
				}

				$family_key  = $this->family_key( $face['family'] );
				$variant_key = $this->variant_key( $face['style'], $face['weight'] );

				if ( empty( $map[ $family_key ] ) ) {
					$map[ $family_key ] = array(
						'family'   => $face['family'],
						'variants' => array(),
						'css_files' => array(),
					);
				}

				$map[ $family_key ]['variants'][ $variant_key ] = array(
					'style'  => $this->normalize_font_style( $face['style'] ),
					'weight' => $this->normalize_font_weight( $face['weight'] ),
				);
				$this->append_limited_unique( $map[ $family_key ]['css_files'], basename( $css_path ), 20 );
			}
		}

		return $map;
	}

	private function resolve_local_font_path_from_css_url( $url, $css_path, $uploads ) {
		$url = html_entity_decode( trim( (string) $url ), ENT_QUOTES, 'UTF-8' );
		if ( '' === $url || false !== stripos( $url, 'fonts.gstatic.com' ) || false !== stripos( $url, 'fonts.googleapis.com' ) ) {
			return '';
		}

		$upload_base_dir = ! empty( $uploads['basedir'] ) ? untrailingslashit( wp_normalize_path( $uploads['basedir'] ) ) : '';
		$upload_base_url = ! empty( $uploads['baseurl'] ) ? untrailingslashit( $uploads['baseurl'] ) : '';
		$upload_base_url_path = '' !== $upload_base_url ? wp_parse_url( $upload_base_url, PHP_URL_PATH ) : '';
		$upload_base_url_path = is_string( $upload_base_url_path ) ? untrailingslashit( $upload_base_url_path ) : '';

		if ( 0 === strpos( $url, '//' ) ) {
			$url = ( is_ssl() ? 'https:' : 'http:' ) . $url;
		}

		if ( preg_match( '#^https?://#i', $url ) ) {
			if ( '' === $upload_base_url || 0 !== strpos( $url, $upload_base_url ) ) {
				return '';
			}

			$relative = ltrim( substr( $url, strlen( $upload_base_url ) ), '/' );
			return $this->normalize_local_path_inside_base( $upload_base_dir . '/' . rawurldecode( $relative ), $upload_base_dir );
		}

		if ( 0 === strpos( $url, '/' ) ) {
			if ( '' === $upload_base_url_path || 0 !== strpos( $url, $upload_base_url_path ) ) {
				return '';
			}

			$relative = ltrim( substr( $url, strlen( $upload_base_url_path ) ), '/' );
			$path = $upload_base_dir . '/' . $relative;
		} else {
			$path = dirname( $css_path ) . '/' . $url;
		}

		return $this->normalize_local_path_inside_base( $path, $upload_base_dir );
	}

	private function normalize_local_path_inside_base( $path, $base_dir ) {
		if ( '' === $path || '' === $base_dir ) {
			return '';
		}

		$path = wp_normalize_path( rawurldecode( $path ) );
		$base_dir = untrailingslashit( wp_normalize_path( $base_dir ) );
		$parts = array();

		foreach ( explode( '/', $path ) as $part ) {
			if ( '' === $part || '.' === $part ) {
				continue;
			}

			if ( '..' === $part ) {
				array_pop( $parts );
				continue;
			}

			$parts[] = $part;
		}

		$normalized = ( 0 === strpos( $path, '/' ) ? '/' : '' ) . implode( '/', $parts );

		if ( 0 !== strpos( strtolower( $normalized ), strtolower( trailingslashit( $base_dir ) ) ) ) {
			return '';
		}

		return $normalized;
	}

	private function filter_variants_missing_from_external_local_fonts( $family, $variants, $external_local_fonts ) {
		$missing = array();

		foreach ( (array) $variants as $variant ) {
			if ( empty( $variant['weight'] ) || empty( $variant['style'] ) ) {
				continue;
			}

			if ( $this->external_local_fonts_have_variant( $external_local_fonts, $family, $variant['style'], $variant['weight'] ) ) {
				continue;
			}

			$missing[] = $variant;
		}

		return $missing;
	}

	private function external_local_fonts_have_variant( $external_local_fonts, $family, $style, $weight ) {
		if ( empty( $external_local_fonts ) || ! is_array( $external_local_fonts ) ) {
			return false;
		}

		$family_key  = $this->family_key( $family );
		$variant_key = $this->variant_key( $style, $weight );

		foreach ( $external_local_fonts as $provider_fonts ) {
			if ( ! empty( $provider_fonts[ $family_key ]['variants'][ $variant_key ] ) ) {
				return true;
			}
		}

		return false;
	}

	private function filter_external_local_fonts_to_scan( $external_local_fonts, $google_fonts ) {
		$filtered = array();

		foreach ( (array) $google_fonts as $font ) {
			if ( empty( $font['family'] ) || empty( $font['variants'] ) || ! is_array( $font['variants'] ) ) {
				continue;
			}

			foreach ( (array) $external_local_fonts as $provider => $provider_fonts ) {
				if ( empty( $provider_fonts ) || ! is_array( $provider_fonts ) ) {
					continue;
				}

				$family_key = $this->family_key( $font['family'] );
				if ( empty( $provider_fonts[ $family_key ]['variants'] ) ) {
					continue;
				}

				foreach ( $font['variants'] as $variant ) {
					$variant_key = $this->variant_key( $variant['style'], $variant['weight'] );
					if ( empty( $provider_fonts[ $family_key ]['variants'][ $variant_key ] ) ) {
						continue;
					}

					if ( empty( $filtered[ $provider ][ $family_key ] ) ) {
						$filtered[ $provider ][ $family_key ] = array(
							'family'   => $provider_fonts[ $family_key ]['family'],
							'variants' => array(),
						);
					}

					$filtered[ $provider ][ $family_key ]['variants'][ $variant_key ] = $provider_fonts[ $family_key ]['variants'][ $variant_key ];
				}
			}
		}

		return $filtered;
	}

	private function parse_google_font_faces( $css ) {
		$faces = array();

		if ( ! preg_match_all( '/@font-face\s*\{([^}]*)\}/i', $css, $matches ) ) {
			return $faces;
		}

		foreach ( $matches[1] as $block ) {
			$declarations = $this->parse_css_declarations( $block );
			if ( empty( $declarations['font-family'] ) || empty( $declarations['src'] ) ) {
				continue;
			}

			$src = $this->extract_woff2_url_from_src( $declarations['src'] );
			if ( '' === $src ) {
				continue;
			}

			$faces[] = array(
				'family'        => $this->normalize_font_family( $declarations['font-family'] ),
				'style'         => $this->normalize_font_style( isset( $declarations['font-style'] ) ? $declarations['font-style'] : '' ),
				'weight'        => isset( $declarations['font-weight'] ) ? trim( $declarations['font-weight'] ) : '400',
				'unicode_range' => isset( $declarations['unicode-range'] ) ? trim( $declarations['unicode-range'] ) : '',
				'src'           => $src,
			);
		}

		return $faces;
	}

	private function parse_css_declarations( $block ) {
		$declarations = array();

		if ( preg_match_all( '/([a-z-]+)\s*:\s*([^;]+);?/i', $block, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$declarations[ strtolower( trim( $match[1] ) ) ] = trim( $match[2] );
			}
		}

		return $declarations;
	}

	private function extract_woff2_url_from_src( $src ) {
		if ( ! preg_match_all( '/url\((["\']?)(.*?)\1\)/i', $src, $matches ) ) {
			return '';
		}

		foreach ( $matches[2] as $url ) {
			$url = trim( $url );
			if ( false !== stripos( $url, '.woff2' ) ) {
				return html_entity_decode( $url, ENT_QUOTES, 'UTF-8' );
			}
		}

		return '';
	}

	private function build_local_font_face_rule( $family, $face, $filename ) {
		if ( empty( $family ) || empty( $face['style'] ) || empty( $face['weight'] ) || empty( $filename ) ) {
			return '';
		}

		$rule   = array();
		$rule[] = '@font-face {';
		$rule[] = '    font-family: "' . $this->escape_css_string( $family ) . '";';
		$rule[] = '    font-style: ' . $this->sanitize_css_identifier_value( $face['style'], 'normal' ) . ';';
		$rule[] = '    font-weight: ' . $this->sanitize_css_weight_value( $face['weight'] ) . ';';
		$rule[] = '    font-display: ' . self::FONT_DISPLAY . ';';
		$rule[] = '    src: url("./' . rawurlencode( $filename ) . '") format("woff2");';

		if ( ! empty( $face['unicode_range'] ) ) {
			$unicode_range = preg_replace( '/[^Uu\+\-\?,\sA-Fa-f0-9]/', '', $face['unicode_range'] );
			if ( '' !== trim( $unicode_range ) ) {
				$rule[] = '    unicode-range: ' . trim( $unicode_range ) . ';';
			}
		}

		$rule[] = '}';

		return implode( "\n", $rule );
	}

	private function build_google_css_url( $family, $variants ) {
		$family = $this->normalize_font_family( $family );
		if ( '' === $family ) {
			return '';
		}

		$has_italic = false;
		$items      = array();

		foreach ( $variants as $variant ) {
			$weight = isset( $variant['weight'] ) ? $this->normalize_font_weight( $variant['weight'] ) : '400';
			$style  = isset( $variant['style'] ) ? $this->normalize_font_style( $variant['style'] ) : 'normal';
			$has_italic = $has_italic || 'italic' === $style;
			$items[] = array(
				'style'  => $style,
				'weight' => $weight,
			);
		}

		$items = $this->unique_variants( $items );
		if ( empty( $items ) ) {
			return '';
		}

		usort(
			$items,
			function ( $a, $b ) {
				if ( $a['style'] === $b['style'] ) {
					return (int) $a['weight'] <=> (int) $b['weight'];
				}

				return 'normal' === $a['style'] ? -1 : 1;
			}
		);

		$family_query = str_replace( '%20', '+', rawurlencode( $family ) );

		if ( $has_italic ) {
			$values = array();
			foreach ( $items as $item ) {
				$values[] = ( 'italic' === $item['style'] ? '1' : '0' ) . ',' . $item['weight'];
			}

			$family_query .= ':ital,wght@' . implode( ';', $values );
		} else {
			$weights = array();
			foreach ( $items as $item ) {
				$weights[] = $item['weight'];
			}

			$family_query .= ':wght@' . implode( ';', array_unique( $weights ) );
		}

		return 'https://fonts.googleapis.com/css2?family=' . $family_query . '&display=' . self::FONT_DISPLAY;
	}

	private function unique_variants( $variants ) {
		$unique = array();

		foreach ( $variants as $variant ) {
			$weight = $this->normalize_font_weight( $variant['weight'] );
			$style  = $this->normalize_font_style( $variant['style'] );
			$unique[ $this->variant_key( $style, $weight ) ] = array(
				'weight' => $weight,
				'style'  => $style,
			);
		}

		return array_values( $unique );
	}

	private function parse_google_stylesheet_families( $url ) {
		$families = array();
		$url      = html_entity_decode( $url, ENT_QUOTES, 'UTF-8' );
		$parts    = wp_parse_url( $url );

		if ( empty( $parts['query'] ) ) {
			return $families;
		}

		$query = str_replace( '&amp;', '&', $parts['query'] );
		foreach ( explode( '&', $query ) as $pair ) {
			$bits = explode( '=', $pair, 2 );
			if ( 2 !== count( $bits ) || 'family' !== rawurldecode( $bits[0] ) ) {
				continue;
			}

			$family_specs = explode( '|', rawurldecode( str_replace( '+', ' ', $bits[1] ) ) );
			foreach ( $family_specs as $family_spec ) {
				$parsed = $this->parse_google_family_spec( $family_spec );
				foreach ( $parsed as $family => $variants ) {
					if ( empty( $families[ $family ] ) ) {
						$families[ $family ] = array();
					}

					foreach ( $variants as $variant ) {
						$families[ $family ][ $this->variant_key( $variant['style'], $variant['weight'] ) ] = $variant;
					}
				}
			}
		}

		return $families;
	}

	private function parse_google_family_spec( $spec ) {
		$spec = trim( $spec );
		if ( '' === $spec ) {
			return array();
		}

		$family       = $spec;
		$variant_spec = '';

		if ( false !== strpos( $spec, ':' ) ) {
			list( $family, $variant_spec ) = explode( ':', $spec, 2 );
		}

		$family = $this->normalize_font_family( $family );
		if ( '' === $family ) {
			return array();
		}

		$variants = array();

		if ( '' === $variant_spec ) {
			$variants[] = array(
				'weight' => '400',
				'style'  => 'normal',
			);
		} elseif ( false !== strpos( $variant_spec, '@' ) ) {
			list( $axes, $values ) = explode( '@', $variant_spec, 2 );
			$axes = array_map( 'trim', explode( ',', $axes ) );

			foreach ( explode( ';', $values ) as $tuple ) {
				$tuple_values = array_map( 'trim', explode( ',', $tuple ) );
				$style        = 'normal';
				$weights      = array( '400' );

				foreach ( $axes as $index => $axis ) {
					$value = isset( $tuple_values[ $index ] ) ? $tuple_values[ $index ] : '';
					if ( 'ital' === $axis ) {
						$style = '1' === $value ? 'italic' : 'normal';
					}
					if ( 'wght' === $axis ) {
						$weights = $this->expand_google_weight_value( $value );
					}
				}

				foreach ( $weights as $weight ) {
					$variants[] = array(
						'weight' => $weight,
						'style'  => $style,
					);
				}
			}
		} else {
			foreach ( explode( ',', $variant_spec ) as $token ) {
				$token = trim( strtolower( $token ) );
				$style = false !== strpos( $token, 'italic' ) ? 'italic' : 'normal';
				$weight = preg_replace( '/[^0-9]/', '', $token );
				if ( '' === $weight || 'regular' === $token || 'italic' === $token ) {
					$weight = '400';
				}

				$variants[] = array(
					'weight' => $this->normalize_font_weight( $weight ),
					'style'  => $style,
				);
			}
		}

		return array( $family => $this->unique_variants( $variants ) );
	}

	private function expand_google_weight_value( $value ) {
		$value = trim( (string) $value );

		if ( preg_match( '/^([1-9]00)\.\.([1-9]00)$/', $value, $matches ) ) {
			$start = max( 100, min( 900, (int) $matches[1] ) );
			$end   = max( 100, min( 900, (int) $matches[2] ) );

			if ( $start > $end ) {
				$tmp = $start;
				$start = $end;
				$end = $tmp;
			}

			$weights = array();
			for ( $weight = $start; $weight <= $end; $weight += 100 ) {
				$weights[] = (string) $weight;
			}

			return $weights;
		}

		return array( $this->normalize_font_weight( $value ) );
	}

	private function get_active_manifest() {
		$manifest = $this->load_manifest();
		if ( empty( $manifest['active'] ) || ! is_array( $manifest['active'] ) ) {
			return false;
		}

		$active = $manifest['active'];
		$has_marrison_fonts = ! empty( $active['families'] ) && is_array( $active['families'] );
		$has_external_fonts = ! empty( $active['external_local_fonts'] ) && is_array( $active['external_local_fonts'] );

		if ( ! $has_marrison_fonts && ! $has_external_fonts ) {
			return false;
		}

		$storage = $this->get_storage( false );
		if ( is_wp_error( $storage ) ) {
			return false;
		}

		$css_path = '';
		if ( ! empty( $active['css_file'] ) ) {
			$css_path = trailingslashit( $storage['dir'] ) . basename( $active['css_file'] );
			if ( ! file_exists( $css_path ) || filesize( $css_path ) <= 0 ) {
				return false;
			}
		}

		foreach ( $has_marrison_fonts ? $active['families'] : array() as $family ) {
			if ( empty( $family['files'] ) || ! is_array( $family['files'] ) ) {
				return false;
			}

			foreach ( $family['files'] as $file ) {
				$font_path = trailingslashit( $storage['dir'] ) . basename( $file );
				if ( ! file_exists( $font_path ) || filesize( $font_path ) <= 0 ) {
					return false;
				}
			}
		}

		$active['css_url'] = ! empty( $active['css_file'] ) ? trailingslashit( $storage['url'] ) . rawurlencode( basename( $active['css_file'] ) ) : '';
		$active['version'] = ! empty( $active['version'] ) ? $active['version'] : ( '' !== $css_path ? (string) filemtime( $css_path ) : 'external-local-fonts' );

		return $active;
	}

	private function is_google_stylesheet_covered_by_active_manifest( $url, $active ) {
		$coverage = $this->evaluate_google_stylesheet_coverage( $url, $active );

		return ! empty( $coverage['covered'] );
	}

	private function evaluate_google_stylesheet_coverage( $url, $active ) {
		$families = $this->parse_google_stylesheet_families( $url );
		$result = array(
			'covered'   => false,
			'requested' => array(),
			'missing'   => array(),
		);

		$has_marrison_fonts = ! empty( $active['families'] ) && is_array( $active['families'] );
		$has_external_fonts = ! empty( $active['external_local_fonts'] ) && is_array( $active['external_local_fonts'] );

		if ( empty( $families ) || ( ! $has_marrison_fonts && ! $has_external_fonts ) ) {
			return $result;
		}

		foreach ( $families as $family => $variants ) {
			$requested_variants = array();

			foreach ( (array) $variants as $variant ) {
				if ( empty( $variant['weight'] ) || empty( $variant['style'] ) ) {
					continue;
				}

				$style  = $this->normalize_font_style( $variant['style'] );
				$weight = $this->normalize_font_weight( $variant['weight'] );
				$requested_variants[] = array(
					'style'  => $style,
					'weight' => $weight,
				);

				if ( ! $this->active_manifest_has_variant( $active, $family, $style, $weight ) ) {
					$result['missing'][] = array(
						'family' => $family,
						'style'  => $style,
						'weight' => $weight,
					);
				}
			}

			$result['requested'][ $family ] = $requested_variants;
		}

		$result['covered'] = empty( $result['missing'] ) && ! empty( $result['requested'] );

		return $result;
	}

	private function active_manifest_has_variant( $active, $family, $style, $weight ) {
		if ( ( empty( $active['families'] ) || ! is_array( $active['families'] ) ) && ( empty( $active['external_local_fonts'] ) || ! is_array( $active['external_local_fonts'] ) ) ) {
			return false;
		}

		$family_key = $this->family_key( $family );
		$variant_key = $this->variant_key( $this->normalize_font_style( $style ), $this->normalize_font_weight( $weight ) );

		if ( ! empty( $active['families'][ $family_key ]['variants'][ $variant_key ] ) ) {
			return true;
		}

		if ( $this->external_local_fonts_have_variant( isset( $active['external_local_fonts'] ) ? $active['external_local_fonts'] : array(), $family, $style, $weight ) ) {
			return true;
		}

		return $this->active_local_css_has_variant( $active, $family, $style, $weight );
	}

	private function active_local_css_has_variant( $active, $family, $style, $weight ) {
		if ( empty( $active['css_file'] ) ) {
			return false;
		}

		$css_file = basename( $active['css_file'] );
		if ( ! isset( $this->active_css_variant_cache[ $css_file ] ) ) {
			$this->active_css_variant_cache[ $css_file ] = $this->read_active_local_css_variants( $css_file );
		}

		$family_key = $this->family_key( $family );
		$variant_key = $this->variant_key( $style, $weight );

		return ! empty( $this->active_css_variant_cache[ $css_file ][ $family_key ][ $variant_key ] );
	}

	private function read_active_local_css_variants( $css_file ) {
		$variants = array();
		$storage  = $this->get_storage( false );

		if ( is_wp_error( $storage ) ) {
			return $variants;
		}

		$css_path = trailingslashit( $storage['dir'] ) . basename( $css_file );
		if ( ! file_exists( $css_path ) || ! is_readable( $css_path ) ) {
			return $variants;
		}

		$css = file_get_contents( $css_path );
		if ( ! is_string( $css ) || '' === trim( $css ) ) {
			return $variants;
		}

		$faces = $this->parse_google_font_faces( $css );
		foreach ( $faces as $face ) {
			if ( empty( $face['family'] ) || empty( $face['style'] ) || empty( $face['weight'] ) || empty( $face['src'] ) ) {
				continue;
			}

			$font_path = trailingslashit( $storage['dir'] ) . basename( $face['src'] );
			if ( ! file_exists( $font_path ) || filesize( $font_path ) <= 0 ) {
				continue;
			}

			$family_key = $this->family_key( $face['family'] );
			$variant_key = $this->variant_key( $face['style'], $face['weight'] );
			$variants[ $family_key ][ $variant_key ] = true;
		}

		return $variants;
	}

	private function load_manifest() {
		if ( null !== $this->manifest_cache ) {
			return $this->manifest_cache;
		}

		$manifest = $this->get_default_manifest();
		$storage  = $this->get_storage( false );

		if ( ! is_wp_error( $storage ) ) {
			$path = trailingslashit( $storage['dir'] ) . self::MANIFEST_FILE;
			if ( file_exists( $path ) && is_readable( $path ) ) {
				$decoded = json_decode( file_get_contents( $path ), true );
				if ( is_array( $decoded ) ) {
					$manifest = array_replace_recursive( $manifest, $decoded );
				}
			}
		}

		$this->manifest_cache = $manifest;

		return $manifest;
	}

	private function save_manifest( $manifest ) {
		$storage = $this->get_storage();
		if ( is_wp_error( $storage ) ) {
			return $storage;
		}

		$manifest['updated_at'] = current_time( 'mysql' );
		$path = trailingslashit( $storage['dir'] ) . self::MANIFEST_FILE;
		$json = wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		if ( ! is_string( $json ) || '' === $json ) {
			return new WP_Error( 'marrison_lgf_manifest_json', __( 'Impossibile generare il manifest JSON.', 'marrison-addon' ) );
		}

		$result = $this->write_file_safely( $path, $json );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->manifest_cache = $manifest;

		return true;
	}

	private function get_default_manifest() {
		return array(
			'version' => 1,
			'updated_at' => '',
			'scan' => $this->get_empty_scan(),
			'active' => array(),
			'last_logs' => array(),
			'last_update_errors' => array(),
		);
	}

	private function get_empty_scan() {
		return array(
			'scanned_at' => '',
			'fonts' => array(),
			'remote_stylesheets' => array(),
			'warnings' => array(),
			'stats' => array(),
		);
	}

	private function get_storage( $create = true ) {
		$uploads = wp_upload_dir();

		if ( ! empty( $uploads['error'] ) ) {
			return new WP_Error(
				'marrison_lgf_upload_dir',
				sprintf(
					/* translators: %s: WordPress upload directory error. */
					__( 'Directory upload WordPress non disponibile: %s', 'marrison-addon' ),
					$uploads['error']
				)
			);
		}

		if ( empty( $uploads['basedir'] ) || empty( $uploads['baseurl'] ) ) {
			return new WP_Error( 'marrison_lgf_upload_dir_missing', __( 'Directory upload WordPress non disponibile.', 'marrison-addon' ) );
		}

		$base_dir = untrailingslashit( wp_normalize_path( $uploads['basedir'] ) );
		$dir      = $base_dir . '/' . self::STORAGE_SUBDIR;
		$url      = trailingslashit( $uploads['baseurl'] ) . self::STORAGE_SUBDIR;

		if ( 0 !== strpos( strtolower( wp_normalize_path( $dir ) ), strtolower( trailingslashit( $base_dir ) ) ) ) {
			return new WP_Error( 'marrison_lgf_storage_outside_uploads', __( 'La directory dei font locali deve restare dentro uploads.', 'marrison-addon' ) );
		}

		if ( $create ) {
			$prepared = $this->prepare_storage_directory( $base_dir, self::STORAGE_SUBDIR );
			if ( is_wp_error( $prepared ) ) {
				return $prepared;
			}
		}

		if ( $create && ! is_writable( $dir ) ) {
			return new WP_Error(
				'marrison_lgf_storage_unwritable',
				sprintf(
					/* translators: %s: target directory path. */
					__( 'La directory dei font locali non e scrivibile: %s', 'marrison-addon' ),
					$dir
				)
			);
		}

		return array(
			'dir' => $dir,
			'url' => $url,
		);
	}

	private function prepare_storage_directory( $base_dir, $relative_dir ) {
		$base_dir = untrailingslashit( wp_normalize_path( $base_dir ) );

		if ( ! is_dir( $base_dir ) && ! wp_mkdir_p( $base_dir ) ) {
			return new WP_Error(
				'marrison_lgf_upload_base_create',
				sprintf(
					/* translators: 1: upload base path, 2: diagnostic details. */
					__( 'Impossibile creare la directory upload base: %1$s. %2$s', 'marrison-addon' ),
					$base_dir,
					$this->get_directory_diagnostic( $base_dir )
				)
			);
		}

		if ( ! is_writable( $base_dir ) ) {
			return new WP_Error(
				'marrison_lgf_upload_base_unwritable',
				sprintf(
					/* translators: 1: upload base path, 2: diagnostic details. */
					__( 'La directory upload base non e scrivibile: %1$s. %2$s', 'marrison-addon' ),
					$base_dir,
					$this->get_directory_diagnostic( $base_dir )
				)
			);
		}

		$current = $base_dir;
		$parts   = array_filter( explode( '/', trim( wp_normalize_path( $relative_dir ), '/' ) ) );

		foreach ( $parts as $part ) {
			$current .= '/' . sanitize_file_name( $part );

			if ( is_dir( $current ) ) {
				continue;
			}

			if ( ! wp_mkdir_p( $current ) ) {
				clearstatcache( true, $current );

				if ( ! is_dir( $current ) ) {
					return new WP_Error(
						'marrison_lgf_storage_create',
						sprintf(
							/* translators: 1: target directory path, 2: diagnostic details. */
							__( 'Impossibile creare la directory dei font locali: %1$s. %2$s', 'marrison-addon' ),
							$current,
							$this->get_directory_diagnostic( $current )
						)
					);
				}
			}
		}

		return true;
	}

	private function get_directory_diagnostic( $path ) {
		$path   = wp_normalize_path( $path );
		$parent = dirname( $path );

		return sprintf(
			/* translators: 1: yes/no, 2: parent directory, 3: yes/no, 4: yes/no. */
			__( 'Esiste: %1$s. Parent: %2$s. Parent esiste: %3$s. Parent scrivibile: %4$s.', 'marrison-addon' ),
			is_dir( $path ) ? __( 'si', 'marrison-addon' ) : __( 'no', 'marrison-addon' ),
			$parent,
			is_dir( $parent ) ? __( 'si', 'marrison-addon' ) : __( 'no', 'marrison-addon' ),
			is_writable( $parent ) ? __( 'si', 'marrison-addon' ) : __( 'no', 'marrison-addon' )
		);
	}

	private function write_file_safely( $path, $contents ) {
		$dir = dirname( $path );
		if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
			return new WP_Error(
				'marrison_lgf_unwritable_dir',
				sprintf(
					/* translators: 1: target directory path, 2: diagnostic details. */
					__( 'Directory di destinazione non scrivibile: %1$s. %2$s', 'marrison-addon' ),
					$dir,
					$this->get_directory_diagnostic( $dir )
				)
			);
		}

		$tmp = trailingslashit( $dir ) . '.' . basename( $path ) . '.' . wp_generate_password( 8, false, false ) . '.tmp';
		$bytes = file_put_contents( $tmp, $contents, LOCK_EX );

		if ( false === $bytes || $bytes <= 0 ) {
			@unlink( $tmp );
			return new WP_Error( 'marrison_lgf_write_failed', __( 'Scrittura file non riuscita.', 'marrison-addon' ) );
		}

		if ( ! @rename( $tmp, $path ) ) {
			if ( file_exists( $path ) ) {
				@unlink( $path );
			}
			if ( ! @rename( $tmp, $path ) ) {
				@unlink( $tmp );
				return new WP_Error( 'marrison_lgf_rename_failed', __( 'Impossibile finalizzare il file generato.', 'marrison-addon' ) );
			}
		}

		return true;
	}

	private function normalize_asset_url( $src ) {
		if ( 0 === strpos( $src, '//' ) ) {
			return ( is_ssl() ? 'https:' : 'http:' ) . $src;
		}

		if ( 0 === strpos( $src, '/' ) ) {
			return home_url( $src );
		}

		return $src;
	}

	private function is_google_fonts_stylesheet_url( $url ) {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) ) {
			return false;
		}

		$host = strtolower( $parts['host'] );

		return 'fonts.googleapis.com' === $host;
	}

	private function is_allowed_google_font_file_url( $url ) {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return false;
		}

		return 'https' === strtolower( $parts['scheme'] ) && 'fonts.gstatic.com' === strtolower( $parts['host'] ) && false !== stripos( $url, '.woff2' );
	}

	private function is_google_fonts_host( $url ) {
		$normalized = 0 === strpos( $url, '//' ) ? 'https:' . $url : $url;
		$parts      = wp_parse_url( $normalized );

		if ( empty( $parts['host'] ) ) {
			return false;
		}

		$host = strtolower( $parts['host'] );

		return in_array( $host, array( 'fonts.googleapis.com', 'fonts.gstatic.com' ), true );
	}

	private function is_elementor_google_font( $family ) {
		$family = $this->normalize_font_family( $family );
		if ( '' === $family ) {
			return false;
		}

		if ( null === $this->elementor_fonts_cache ) {
			$this->elementor_fonts_cache = array();

			if ( class_exists( '\Elementor\Fonts' ) && method_exists( '\Elementor\Fonts', 'get_fonts' ) ) {
				$fonts = \Elementor\Fonts::get_fonts();
				if ( is_array( $fonts ) ) {
					foreach ( $fonts as $font_name => $font_type ) {
						$font_key = $this->family_key( $font_name );
						$type     = is_scalar( $font_type ) ? strtolower( (string) $font_type ) : '';

						if ( false !== strpos( $type, 'google' ) ) {
							$this->elementor_fonts_cache[ $font_key ] = true;
						}
					}
				}
			}
		}

		return ! empty( $this->elementor_fonts_cache[ $this->family_key( $family ) ] );
	}

	private function normalize_font_family( $value ) {
		$value = trim( wp_strip_all_tags( (string) $value ) );
		if ( '' === $value || false !== strpos( $value, 'var(' ) ) {
			return '';
		}

		$token = $this->get_first_font_family_token( $value );
		$value = trim( $token['value'] );
		$value = preg_replace( '/\s+/', ' ', $value );

		if ( ! is_string( $value ) || '' === $value || $this->is_system_font_family( $value ) ) {
			return '';
		}

		return $value;
	}

	private function is_system_font_family( $family ) {
		$family = strtolower( trim( $family ) );
		$system = array(
			'arial',
			'helvetica',
			'times',
			'times new roman',
			'georgia',
			'verdana',
			'tahoma',
			'trebuchet ms',
			'courier',
			'courier new',
			'sans-serif',
			'serif',
			'monospace',
			'cursive',
			'fantasy',
			'system-ui',
			'ui-serif',
			'ui-sans-serif',
			'ui-monospace',
			'ui-rounded',
			'emoji',
			'math',
			'fangsong',
			'inherit',
			'initial',
			'unset',
			'revert',
			'revert-layer',
			'default',
			'blinkmacsystemfont',
			'dashicons',
			'eicons',
			'elementskit',
			'elementor-icons',
			'font awesome 5 brands',
			'font awesome 5 free',
			'font awesome 6 brands',
			'font awesome 6 free',
			'fontawesome',
			'fontawesome brands',
			'fontawesome regular',
			'fontawesome solid',
			'icomoon',
			'ionicons',
			'linearicons',
			'themify',
			'woocommerce',
		);

		return in_array( $family, $system, true ) || 0 === strpos( $family, '-' );
	}

	private function normalize_font_weight( $value ) {
		$value = strtolower( trim( (string) $value ) );

		if ( '' === $value || false !== strpos( $value, 'var(' ) ) {
			return '400';
		}

		if ( 'normal' === $value || 'regular' === $value ) {
			return '400';
		}

		if ( 'bold' === $value ) {
			return '700';
		}

		if ( preg_match( '/([1-9]00)/', $value, $matches ) ) {
			$weight = (int) $matches[1];
			if ( $weight >= 100 && $weight <= 900 ) {
				return (string) $weight;
			}
		}

		return '400';
	}

	private function normalize_font_style( $value ) {
		$value = strtolower( trim( (string) $value ) );

		if ( false !== strpos( $value, 'italic' ) || false !== strpos( $value, 'oblique' ) ) {
			return 'italic';
		}

		return 'normal';
	}

	private function family_key( $family ) {
		return strtolower( preg_replace( '/\s+/', ' ', trim( (string) $family ) ) );
	}

	private function variant_key( $style, $weight ) {
		return $this->normalize_font_style( $style ) . '|' . $this->normalize_font_weight( $weight );
	}

	private function build_font_filename( $family, $face, $index ) {
		$family_slug = sanitize_title( $family );
		if ( '' === $family_slug ) {
			$family_slug = 'font';
		}

		$weight = preg_replace( '/[^0-9a-zA-Z-]+/', '-', (string) $face['weight'] );
		$style  = preg_replace( '/[^a-zA-Z-]+/', '-', (string) $face['style'] );
		$hash   = substr( md5( $face['src'] ), 0, 10 );

		return strtolower( $family_slug . '-' . $style . '-' . $weight . '-' . absint( $index ) . '-' . $hash . '.woff2' );
	}

	private function sanitize_css_identifier_value( $value, $fallback ) {
		$value = strtolower( trim( (string) $value ) );
		$value = preg_replace( '/[^a-z-]/', '', $value );

		return '' !== $value ? $value : $fallback;
	}

	private function sanitize_css_weight_value( $value ) {
		$value = trim( (string) $value );
		$value = preg_replace( '/[^0-9\s]/', '', $value );
		$value = trim( preg_replace( '/\s+/', ' ', $value ) );

		return '' !== $value ? $value : '400';
	}

	private function escape_css_string( $value ) {
		return str_replace( array( '\\', '"' ), array( '\\\\', '\"' ), (string) $value );
	}

	private function build_scan_logs( $scan ) {
		$logs = array();
		$count = ! empty( $scan['fonts'] ) && is_array( $scan['fonts'] ) ? count( $scan['fonts'] ) : 0;
		$google_count = 0;
		$local_count = 0;
		$unknown_count = 0;

		if ( ! empty( $scan['fonts'] ) && is_array( $scan['fonts'] ) ) {
			foreach ( $scan['fonts'] as $font ) {
				$classification = isset( $font['classification'] ) ? $font['classification'] : 'unknown';
				if ( 'google' === $classification ) {
					$google_count++;
				} elseif ( 'local' === $classification ) {
					$local_count++;
				} else {
					$unknown_count++;
				}
			}
		}

		$logs[] = sprintf(
			/* translators: %d: font family count. */
			__( 'Scansione: %d famiglie candidate individuate.', 'marrison-addon' ),
			$count
		);
		$logs[] = sprintf(
			/* translators: 1: Google Fonts count, 2: local font count, 3: unknown font count. */
			__( 'Classificazione: %1$d Google Fonts confermati, %2$d locali/custom, %3$d sconosciuti ignorati dal download.', 'marrison-addon' ),
			$google_count,
			$local_count,
			$unknown_count
		);

		if ( ! empty( $scan['remote_stylesheets'] ) ) {
			$logs[] = sprintf(
				/* translators: %d: Google Fonts URL count. */
				__( 'URL Google Fonts individuati: %d.', 'marrison-addon' ),
				count( $scan['remote_stylesheets'] )
			);
		}

		if ( ! empty( $scan['warnings'] ) ) {
			foreach ( $scan['warnings'] as $warning ) {
				$logs[] = $warning;
			}
		}

		return $logs;
	}

	private function build_ignored_download_logs( $scan ) {
		$logs = array();
		$local = 0;
		$unknown = 0;

		if ( ! empty( $scan['fonts'] ) && is_array( $scan['fonts'] ) ) {
			foreach ( $scan['fonts'] as $font ) {
				$classification = isset( $font['classification'] ) ? $font['classification'] : 'unknown';
				if ( 'local' === $classification ) {
					$local++;
				} elseif ( 'google' !== $classification ) {
					$unknown++;
				}
			}
		}

		if ( $local > 0 ) {
			$logs[] = sprintf(
				/* translators: %d: local/custom font count. */
				__( 'Downloader: %d famiglie locali/custom ignorate.', 'marrison-addon' ),
				$local
			);
		}

		if ( $unknown > 0 ) {
			$logs[] = sprintf(
				/* translators: %d: unknown font count. */
				__( 'Downloader: %d famiglie non verificate ignorate.', 'marrison-addon' ),
				$unknown
			);
		}

		return $logs;
	}

	private function format_stat_label( $label ) {
		$labels = array(
			'elementor_posts' => __( 'Contenuti Elementor', 'marrison-addon' ),
			'post_content'    => __( 'Contenuti inline', 'marrison-addon' ),
			'css_files'       => __( 'File CSS letti', 'marrison-addon' ),
			'css_bytes'       => __( 'Byte CSS letti', 'marrison-addon' ),
			'source_files'    => __( 'File sorgente letti', 'marrison-addon' ),
			'source_bytes'    => __( 'Byte sorgenti letti', 'marrison-addon' ),
		);

		return isset( $labels[ $label ] ) ? $labels[ $label ] : $label;
	}

	private function is_admin_page() {
		return isset( $_GET['page'] ) && self::PAGE_SLUG === sanitize_key( wp_unslash( $_GET['page'] ) );
	}
}
