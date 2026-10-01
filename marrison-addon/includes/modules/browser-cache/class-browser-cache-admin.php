<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Browser_Cache_Admin {

	const PAGE_SLUG    = 'marrison_addon_browser_cache';
	const NONCE_ACTION = 'marrison_browser_cache_action';

	private $server;
	private $diagnostics;
	private $diagnostic_result = null;

	public function __construct( Marrison_Addon_Browser_Cache_Server $server, Marrison_Addon_Browser_Cache_Diagnostics $diagnostics ) {
		$this->server      = $server;
		$this->diagnostics = $diagnostics;

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'maybe_auto_configure' ), 30 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'marrison_addon/module_status_changed', array( $this, 'handle_module_status_changed' ), 10, 2 );
	}

	public function add_admin_menu() {
		add_submenu_page(
			'marrison_addon_panel',
			esc_html__( 'Browser Cache', 'marrison-addon' ),
			esc_html__( 'Browser Cache', 'marrison-addon' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_admin_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'marrison_browser_cache_group',
			Marrison_Addon_Browser_Cache_Server::OPTION_SETTINGS,
			array(
				'sanitize_callback' => array( $this->server, 'sanitize_settings' ),
				'default'           => $this->server->get_default_settings(),
			)
		);
	}

	public function enqueue_admin_assets() {
		if ( ! $this->is_admin_page() ) {
			return;
		}

		$plugin_root_file = Marrison_Addon::plugin_file();

		wp_enqueue_script(
			'marrison-admin-browser-cache',
			plugins_url( 'assets/js/admin-browser-cache.js', $plugin_root_file ),
			array(),
			Marrison_Addon::asset_version( 'assets/js/admin-browser-cache.js' ),
			true
		);
	}

	public function maybe_auto_configure() {
		if ( wp_doing_ajax() || ! current_user_can( 'manage_options' ) || ! $this->is_marrison_admin_context() ) {
			return;
		}

		$this->sync_server_configuration( false );
	}

	public function handle_module_status_changed( $module_id, $enabled ) {
		if ( 'browser_cache' !== $module_id ) {
			return;
		}

		if ( $enabled ) {
			$this->sync_server_configuration( true );
			return;
		}

		$result = $this->server->remove_htaccess_rules();

		if ( is_wp_error( $result ) ) {
			$this->server->update_state(
				array(
					'status'  => 'error',
					'message' => $result->get_error_message(),
				)
			);
		}
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$notice   = $this->handle_page_action();
		$settings = $this->server->get_settings();
		$server   = $this->server->detect_server();
		$method   = $this->server->get_method();
		$state    = $this->server->get_state();
		$status   = $this->get_display_status( $state, $method );
		?>
		<div class="wrap marrison-admin-page marrison-admin-page-browser-cache">
			<h1><?php esc_html_e( 'Browser Cache', 'marrison-addon' ); ?></h1>
			<p><?php esc_html_e( 'Ottimizza gli header cache degli asset statici mantenendo intatto il versioning WordPress, Elementor e degli altri plugin.', 'marrison-addon' ); ?></p>

			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
					<p><?php echo esc_html( $notice['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Impostazioni Browser Cache salvate. La configurazione server viene sincronizzata in admin, non sul frontend.', 'marrison-addon' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="marrison-module-card">
				<div class="marrison-card-header">
					<h2 class="marrison-card-title"><?php esc_html_e( 'Stato', 'marrison-addon' ); ?></h2>
				</div>
				<div class="marrison-browser-cache-summary-grid">
					<div>
						<span class="marrison-browser-cache-label"><?php esc_html_e( 'Server rilevato', 'marrison-addon' ); ?></span>
						<strong><?php echo esc_html( $server['label'] ); ?></strong>
						<?php if ( ! empty( $server['raw'] ) ) : ?>
							<span class="description"><?php echo esc_html( $server['raw'] ); ?></span>
						<?php endif; ?>
					</div>
					<div>
						<span class="marrison-browser-cache-label"><?php esc_html_e( 'Metodo', 'marrison-addon' ); ?></span>
						<strong><?php echo esc_html( $method['label'] ); ?></strong>
					</div>
					<div>
						<span class="marrison-browser-cache-label"><?php esc_html_e( 'Stato', 'marrison-addon' ); ?></span>
						<?php $this->render_status_badge( $status ); ?>
					</div>
					<div>
						<span class="marrison-browser-cache-label"><?php esc_html_e( 'Ultimo aggiornamento', 'marrison-addon' ); ?></span>
						<strong><?php echo esc_html( ! empty( $state['updated_at'] ) ? $state['updated_at'] : __( 'Mai eseguito', 'marrison-addon' ) ); ?></strong>
					</div>
				</div>
				<?php if ( ! empty( $state['message'] ) ) : ?>
					<p class="description marrison-browser-cache-state-message"><?php echo esc_html( $state['message'] ); ?></p>
				<?php endif; ?>
			</div>

			<div class="marrison-module-card marrison-settings-card">
				<div class="marrison-card-header">
					<h2 class="marrison-card-title"><?php esc_html_e( 'Impostazioni', 'marrison-addon' ); ?></h2>
				</div>
				<form action="options.php" method="post">
					<?php settings_fields( 'marrison_browser_cache_group' ); ?>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><?php esc_html_e( 'Abilita Browser Cache', 'marrison-addon' ); ?></th>
								<td>
									<label class="marrison-switch">
										<input type="checkbox"
											class="marrison-ajax-toggle"
											data-option="marrison_addon_modules"
											data-key="browser_cache"
											data-reload="true"
											data-redirect="<?php echo esc_url( admin_url( 'admin.php?page=marrison_addon_panel' ) ); ?>"
											checked>
										<span class="marrison-slider"></span>
									</label>
									<p class="description"><?php esc_html_e( 'Spegnendo il modulo vengono rimosse solo le regole Marrison da .htaccess. Le impostazioni restano salvate.', 'marrison-addon' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="marrison-browser-cache-versioned-ttl"><?php esc_html_e( 'TTL asset versionati', 'marrison-addon' ); ?></label></th>
								<td>
									<input type="number" min="<?php echo esc_attr( HOUR_IN_SECONDS ); ?>" max="<?php echo esc_attr( 5 * YEAR_IN_SECONDS ); ?>" class="regular-text" id="marrison-browser-cache-versioned-ttl" name="<?php echo esc_attr( Marrison_Addon_Browser_Cache_Server::OPTION_SETTINGS ); ?>[versioned_ttl]" value="<?php echo esc_attr( $settings['versioned_ttl'] ); ?>">
									<p class="description"><?php echo esc_html( sprintf( __( 'Default: %s. Usato per URL con ?ver=, ?v=, ?version= o file con hash/versione nel nome.', 'marrison-addon' ), $this->server->seconds_to_human_label( YEAR_IN_SECONDS ) ) ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="marrison-browser-cache-unversioned-ttl"><?php esc_html_e( 'TTL asset non versionati', 'marrison-addon' ); ?></label></th>
								<td>
									<input type="number" min="<?php echo esc_attr( HOUR_IN_SECONDS ); ?>" max="<?php echo esc_attr( YEAR_IN_SECONDS ); ?>" class="regular-text" id="marrison-browser-cache-unversioned-ttl" name="<?php echo esc_attr( Marrison_Addon_Browser_Cache_Server::OPTION_SETTINGS ); ?>[unversioned_ttl]" value="<?php echo esc_attr( $settings['unversioned_ttl'] ); ?>">
									<p class="description"><?php echo esc_html( sprintf( __( 'Default prudente: %s. Non usa immutable per non bloccare asset senza cache busting affidabile.', 'marrison-addon' ), $this->server->seconds_to_human_label( WEEK_IN_SECONDS ) ) ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Immutable', 'marrison-addon' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( Marrison_Addon_Browser_Cache_Server::OPTION_SETTINGS ); ?>[immutable_versioned]" value="1" <?php checked( ! empty( $settings['immutable_versioned'] ) ); ?>>
										<?php esc_html_e( 'Aggiungi immutable solo agli asset versionati o hashati', 'marrison-addon' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Tipologie asset', 'marrison-addon' ); ?></th>
								<td class="marrison-browser-cache-check-grid">
									<?php foreach ( $this->get_asset_type_labels() as $type => $label ) : ?>
										<label>
											<input type="checkbox" name="<?php echo esc_attr( Marrison_Addon_Browser_Cache_Server::OPTION_SETTINGS ); ?>[asset_types][<?php echo esc_attr( $type ); ?>]" value="1" <?php checked( ! empty( $settings['asset_types'][ $type ] ) ); ?>>
											<?php echo esc_html( $label ); ?>
										</label>
									<?php endforeach; ?>
								</td>
							</tr>
						</tbody>
					</table>
					<?php submit_button( __( 'Salva impostazioni', 'marrison-addon' ) ); ?>
				</form>
			</div>

			<div class="marrison-module-card">
				<div class="marrison-card-header">
					<h2 class="marrison-card-title"><?php esc_html_e( 'Versioning e cache busting', 'marrison-addon' ); ?></h2>
				</div>
				<p class="marrison-card-desc"><?php esc_html_e( 'Il versioning degli asset WordPress (?ver=...) viene mantenuto. Quando la versione cambia, cambia l URL e il browser scarica automaticamente la nuova risorsa.', 'marrison-addon' ); ?></p>
				<p class="marrison-card-desc"><?php esc_html_e( 'Il modulo non rimuove query string, non altera handle WordPress e non applica regole a HTML, REST API, AJAX, wp-admin, login, checkout o altre risposte dinamiche.', 'marrison-addon' ); ?></p>
			</div>

			<?php $this->render_server_configuration_card( $settings, $method ); ?>
			<?php $this->render_diagnostics_card( $settings ); ?>
		</div>
		<?php
	}

	private function render_server_configuration_card( $settings, $method ) {
		$htaccess_rules = $this->server->build_htaccess_rules( $settings );
		$nginx_config   = $this->server->build_nginx_config( $settings );
		?>
		<div class="marrison-module-card">
			<div class="marrison-card-header">
				<h2 class="marrison-card-title"><?php esc_html_e( 'Configurazione server', 'marrison-addon' ); ?></h2>
			</div>

			<?php if ( 'htaccess' === $method['slug'] ) : ?>
				<p class="marrison-card-desc"><?php esc_html_e( 'Su Apache/LiteSpeed Marrison puo gestire un blocco .htaccess isolato e idempotente. Le direttive sono protette da IfModule per evitare errori 500 quando mod_expires, mod_headers o mod_setenvif non sono disponibili.', 'marrison-addon' ); ?></p>
				<div class="marrison-browser-cache-actions">
					<form method="post">
						<?php wp_nonce_field( self::NONCE_ACTION, 'marrison_browser_cache_nonce' ); ?>
						<input type="hidden" name="marrison_browser_cache_action" value="apply_rules">
						<?php submit_button( __( 'Applica regole .htaccess', 'marrison-addon' ), 'primary', 'submit', false ); ?>
					</form>
					<form method="post">
						<?php wp_nonce_field( self::NONCE_ACTION, 'marrison_browser_cache_nonce' ); ?>
						<input type="hidden" name="marrison_browser_cache_action" value="remove_rules">
						<?php submit_button( __( 'Rimuovi regole Marrison', 'marrison-addon' ), 'secondary', 'submit', false ); ?>
					</form>
				</div>
				<?php if ( is_wp_error( $htaccess_rules ) ) : ?>
					<p class="description"><?php echo esc_html( $htaccess_rules->get_error_message() ); ?></p>
				<?php else : ?>
					<label for="marrison-browser-cache-htaccess"><?php esc_html_e( 'Regole .htaccess generate', 'marrison-addon' ); ?></label>
					<textarea id="marrison-browser-cache-htaccess" class="large-text code marrison-browser-cache-code" rows="13" readonly><?php echo esc_textarea( $htaccess_rules ); ?></textarea>
				<?php endif; ?>
			<?php else : ?>
				<p class="marrison-card-desc"><?php esc_html_e( 'Marrison non modifica configurazioni Nginx o server esterne a WordPress. Copia la configurazione nel server block corretto e ricarica Nginx dal pannello hosting o via SSH.', 'marrison-addon' ); ?></p>
				<textarea id="marrison-browser-cache-nginx" class="large-text code marrison-browser-cache-code" rows="18" readonly><?php echo esc_textarea( $nginx_config ); ?></textarea>
				<p>
					<button type="button" class="button button-secondary" id="marrison-browser-cache-copy-nginx" data-target="marrison-browser-cache-nginx"><?php esc_html_e( 'Copia configurazione Nginx', 'marrison-addon' ); ?></button>
					<span id="marrison-browser-cache-copy-status" class="description" aria-live="polite"></span>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_diagnostics_card( $settings ) {
		?>
		<div class="marrison-module-card">
			<div class="marrison-card-header">
				<h2 class="marrison-card-title"><?php esc_html_e( 'Diagnostica header reali', 'marrison-addon' ); ?></h2>
			</div>
			<p class="marrison-card-desc"><?php esc_html_e( 'La verifica viene eseguita solo su richiesta. Marrison legge gli header HTTP effettivamente restituiti dal server per alcuni asset reali della home page.', 'marrison-addon' ); ?></p>
			<form method="post" class="marrison-browser-cache-diagnostics-form">
				<?php wp_nonce_field( self::NONCE_ACTION, 'marrison_browser_cache_nonce' ); ?>
				<input type="hidden" name="marrison_browser_cache_action" value="diagnostics">
				<?php submit_button( __( 'Verifica cache browser', 'marrison-addon' ), 'secondary', 'submit', false ); ?>
			</form>

			<?php if ( is_array( $this->diagnostic_result ) ) : ?>
				<?php $this->render_diagnostic_result( $this->diagnostic_result, $settings ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_diagnostic_result( $result, $settings ) {
		$summary = isset( $result['summary'] ) && is_array( $result['summary'] ) ? $result['summary'] : array();
		$samples = isset( $result['samples'] ) && is_array( $result['samples'] ) ? $result['samples'] : array();
		?>
		<div class="marrison-browser-cache-diagnostic-result">
			<p>
				<strong><?php echo esc_html( isset( $summary['message'] ) ? $summary['message'] : '' ); ?></strong>
				<span class="description"><?php echo esc_html( isset( $result['checked_at'] ) ? $result['checked_at'] : '' ); ?></span>
			</p>
			<?php if ( empty( $samples ) ) : ?>
				<p class="description"><?php esc_html_e( 'Nessun risultato da mostrare.', 'marrison-addon' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat striped marrison-browser-cache-diagnostics-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Asset', 'marrison-addon' ); ?></th>
							<th><?php esc_html_e( 'Cache-Control', 'marrison-addon' ); ?></th>
							<th><?php esc_html_e( 'Expires', 'marrison-addon' ); ?></th>
							<th><?php esc_html_e( 'Age', 'marrison-addon' ); ?></th>
							<th><?php esc_html_e( 'ETag', 'marrison-addon' ); ?></th>
							<th><?php esc_html_e( 'Last-Modified', 'marrison-addon' ); ?></th>
							<th><?php esc_html_e( 'Risultato', 'marrison-addon' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $samples as $sample ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $sample['label'] ); ?></strong>
									<span class="description"><?php echo esc_html( ! empty( $sample['versioned'] ) ? __( 'Versionato', 'marrison-addon' ) : __( 'Non versionato', 'marrison-addon' ) ); ?></span>
									<code><?php echo esc_html( $sample['url'] ); ?></code>
								</td>
								<?php foreach ( array( 'cache-control', 'expires', 'age', 'etag', 'last-modified' ) as $header ) : ?>
									<td><?php echo esc_html( isset( $sample['headers'][ $header ] ) && '' !== $sample['headers'][ $header ] ? $sample['headers'][ $header ] : '-' ); ?></td>
								<?php endforeach; ?>
								<td>
									<?php $this->render_status_badge( $this->diagnostic_status_to_badge( $sample['result'] ) ); ?>
									<span class="description"><?php echo esc_html( $sample['message'] ); ?></span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	private function handle_page_action() {
		if ( empty( $_POST['marrison_browser_cache_action'] ) ) {
			return null;
		}

		check_admin_referer( self::NONCE_ACTION, 'marrison_browser_cache_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			return array(
				'type'    => 'error',
				'message' => __( 'Permessi insufficienti.', 'marrison-addon' ),
			);
		}

		$action   = sanitize_key( wp_unslash( $_POST['marrison_browser_cache_action'] ) );
		$settings = $this->server->get_settings();

		if ( 'diagnostics' === $action ) {
			$this->diagnostic_result = $this->diagnostics->run( $settings );
			$summary = $this->diagnostic_result['summary'];

			if ( ! empty( $summary['all_ok'] ) ) {
				$this->server->update_state(
					array(
						'status'        => $this->server->htaccess_has_rules() ? 'active' : 'already_configured',
						'message'       => __( 'Browser cache gia configurata sugli asset verificati.', 'marrison-addon' ),
						'settings_hash' => $this->server->get_settings_hash( $settings ),
					)
				);
			}

			return array(
				'type'    => ! empty( $summary['all_ok'] ) ? 'success' : 'warning',
				'message' => isset( $summary['message'] ) ? $summary['message'] : __( 'Diagnostica completata.', 'marrison-addon' ),
			);
		}

		if ( 'apply_rules' === $action ) {
			$result = $this->sync_server_configuration( true );

			if ( is_wp_error( $result ) ) {
				return array(
					'type'    => 'error',
					'message' => $result->get_error_message(),
				);
			}

			return array(
				'type'    => 'success',
				'message' => __( 'Configurazione Browser Cache aggiornata.', 'marrison-addon' ),
			);
		}

		if ( 'remove_rules' === $action ) {
			$result = $this->server->remove_htaccess_rules();

			if ( is_wp_error( $result ) ) {
				return array(
					'type'    => 'error',
					'message' => $result->get_error_message(),
				);
			}

			return array(
				'type'    => 'success',
				'message' => __( 'Regole Marrison rimosse da .htaccess.', 'marrison-addon' ),
			);
		}

		return null;
	}

	private function sync_server_configuration( $force ) {
		$settings = $this->server->get_settings();
		$method   = $this->server->get_method();
		$state    = $this->server->get_state();
		$hash     = $this->server->get_settings_hash( $settings );

		if ( ! $force && isset( $state['settings_hash'], $state['status'] ) && $hash === $state['settings_hash'] && in_array( $state['status'], array( 'active', 'already_configured', 'manual', 'error' ), true ) ) {
			return true;
		}

		if ( 'htaccess' !== $method['slug'] ) {
			$this->server->update_state(
				array(
					'status'        => 'manual',
					'message'       => __( 'Configurazione server manuale necessaria. Nessun file di configurazione esterno viene modificato automaticamente.', 'marrison-addon' ),
					'settings_hash' => $hash,
				)
			);

			return true;
		}

		$diagnostic = $this->diagnostics->run( $settings );

		if ( ! empty( $diagnostic['summary']['all_ok'] ) && ! $this->server->htaccess_has_rules() ) {
			$this->server->update_state(
				array(
					'status'        => 'already_configured',
					'message'       => __( 'Browser cache gia configurata da server, CDN o altro plugin. Marrison non ha aggiunto regole duplicate.', 'marrison-addon' ),
					'settings_hash' => $hash,
				)
			);

			return true;
		}

		$result = $this->server->apply_htaccess_rules( $settings );

		if ( is_wp_error( $result ) ) {
			$this->server->update_state(
				array(
					'status'        => 'error',
					'message'       => $result->get_error_message(),
					'settings_hash' => $hash,
				)
			);
		}

		return $result;
	}

	private function get_display_status( $state, $method ) {
		$status = isset( $state['status'] ) ? $state['status'] : 'inactive';

		if ( 'nginx_manual' === $method['slug'] || 'manual' === $method['slug'] ) {
			$status = in_array( $status, array( 'active', 'already_configured' ), true ) ? $status : 'manual';
		}

		$map = array(
			'active'             => array(
				'class' => 'success',
				'label' => __( 'ATTIVO', 'marrison-addon' ),
			),
			'already_configured' => array(
				'class' => 'success',
				'label' => __( 'BROWSER CACHE GIA CONFIGURATA', 'marrison-addon' ),
			),
			'manual'             => array(
				'class' => 'warning',
				'label' => __( 'CONFIGURAZIONE SERVER NECESSARIA', 'marrison-addon' ),
			),
			'error'              => array(
				'class' => 'error',
				'label' => __( 'ERRORE CONFIGURAZIONE', 'marrison-addon' ),
			),
			'inactive'           => array(
				'class' => 'muted',
				'label' => __( 'NON ATTIVO', 'marrison-addon' ),
			),
		);

		return isset( $map[ $status ] ) ? $map[ $status ] : $map['inactive'];
	}

	private function diagnostic_status_to_badge( $status ) {
		$map = array(
			'ok'      => array(
				'class' => 'success',
				'label' => __( 'OK', 'marrison-addon' ),
			),
			'warning' => array(
				'class' => 'warning',
				'label' => __( 'ATTENZIONE', 'marrison-addon' ),
			),
			'error'   => array(
				'class' => 'error',
				'label' => __( 'ERRORE', 'marrison-addon' ),
			),
		);

		return isset( $map[ $status ] ) ? $map[ $status ] : $map['error'];
	}

	private function render_status_badge( $status ) {
		printf(
			'<span class="marrison-browser-cache-status %1$s">%2$s</span>',
			esc_attr( $status['class'] ),
			esc_html( $status['label'] )
		);
	}

	private function get_asset_type_labels() {
		return array(
			'css'    => __( 'CSS', 'marrison-addon' ),
			'js'     => __( 'JavaScript', 'marrison-addon' ),
			'fonts'  => __( 'Font', 'marrison-addon' ),
			'images' => __( 'Immagini', 'marrison-addon' ),
		);
	}

	private function is_admin_page() {
		return isset( $_GET['page'] ) && self::PAGE_SLUG === sanitize_key( wp_unslash( $_GET['page'] ) );
	}

	private function is_marrison_admin_context() {
		if ( ! isset( $_GET['page'] ) ) {
			return false;
		}

		$page = sanitize_key( wp_unslash( $_GET['page'] ) );

		return 'marrison_addon_panel' === $page || self::PAGE_SLUG === $page;
	}
}
