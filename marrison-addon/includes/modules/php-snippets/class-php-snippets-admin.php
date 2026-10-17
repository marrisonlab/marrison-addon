<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Marrison_Addon_PHP_Snippets_Admin {
	const PAGE = 'marrison_addon_php_snippets';
	private $store;
	private $notice;
	private $draft;

	public function __construct( Marrison_Addon_PHP_Snippets_Store $store ) {
		$this->store = $store;
		// Runtime boots before plugins_loaded, so our callback is registered before
		// the parent menu's callback. Register the submenu after the parent exists.
		add_action( 'admin_menu', [ $this, 'menu' ], 20 );
		add_action( 'admin_init', [ $this, 'handle_request' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
	}

	private function is_page() {
		return isset( $_GET['page'] ) && self::PAGE === $_GET['page'];
	}

	private function url( $args = [] ) {
		return add_query_arg( $args, admin_url( 'admin.php?page=' . self::PAGE ) );
	}

	public function menu() {
		if ( Marrison_Addon_PHP_Snippets_Store::can_manage() ) {
			add_submenu_page( 'marrison_addon_panel', 'PHP Snippets', 'PHP Snippets', 'manage_options', self::PAGE, [ $this, 'render' ] );
		}
	}

	public function assets() {
		if ( ! $this->is_page() || ! Marrison_Addon_PHP_Snippets_Store::can_manage() ) {
			return;
		}
		wp_enqueue_style( 'marrison-admin-php-snippets', plugins_url( 'assets/css/admin-php-snippets.css', Marrison_Addon::plugin_file() ), [], Marrison_Addon::asset_version( 'assets/css/admin-php-snippets.css' ) );
		$settings = wp_enqueue_code_editor( [ 'type' => 'application/x-httpd-php', 'codemirror' => [ 'mode' => [ 'name' => 'php', 'startOpen' => true ], 'lineNumbers' => true, 'indentUnit' => 4, 'indentWithTabs' => true ] ] );
		if ( $settings ) {
			wp_add_inline_script( 'code-editor', 'jQuery(function(){var field=document.getElementById("marrison-php-snippet-code");if(field){wp.codeEditor.initialize(field,' . wp_json_encode( $settings ) . ');}});' );
		}
	}

	public function handle_request() {
		if ( ! $this->is_page() ) {
			return;
		}
		if ( ! Marrison_Addon_PHP_Snippets_Store::can_manage() ) {
			wp_die( esc_html__( 'Sono richieste le capability manage_options e unfiltered_html.', 'marrison-addon' ), '', [ 'response' => 403 ] );
		}
		$installed = $this->store->install();
		if ( is_wp_error( $installed ) ) {
			$this->notice = $installed;
			return;
		}
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}
		$result = $this->process_action();
		if ( is_wp_error( $result ) ) {
			$this->notice = $result;
			return;
		}
		wp_safe_redirect( $this->url( [ 'saved' => 1 ] ) );
		exit;
	}

	private function posted( $key ) {
		return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
	}

	public function process_action() {
		if ( ! Marrison_Addon_PHP_Snippets_Store::can_manage() || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return new WP_Error( 'permission', __( 'Operazione non autorizzata.', 'marrison-addon' ) );
		}
		$id = absint( $this->posted( 'id' ) );
		$operation = $this->posted( 'operation' );
		if ( ! in_array( $operation, [ 'save', 'activate', 'deactivate', 'duplicate', 'delete', 'deactivate_all' ], true )
			|| ! wp_verify_nonce( $this->posted( '_wpnonce' ), 'marrison_php_snippets_' . $operation . '_' . $id ) ) {
			return new WP_Error( 'nonce', __( 'Richiesta non valida o scaduta. Ricarica la pagina.', 'marrison-addon' ) );
		}
		if ( 'deactivate_all' === $operation ) {
			return $this->store->deactivate_all() ? true : new WP_Error( 'storage', __( 'Disattivazione non completata.', 'marrison-addon' ) );
		}
		$revision = $this->posted( 'revision' );
		if ( 'activate' === $operation && ! Marrison_Addon_PHP_Snippets_Stream::available() ) {
			return new WP_Error( 'runtime', __( 'Esecuzione PHP in memoria non disponibile su questo server: attivazione bloccata.', 'marrison-addon' ) );
		}
		if ( 'save' === $operation ) {
			$this->draft = [ 'id' => $id, 'name' => sanitize_text_field( $this->posted( 'name' ) ), 'code' => Marrison_Addon_PHP_Snippets_Store::normalize_code( $this->posted( 'code' ) ), 'scope' => $this->posted( 'scope' ), 'revision' => $revision ];
			// Code is deliberately not passed through KSES or text sanitizers.
			return $this->store->save( $id, $this->draft['name'], $this->draft['code'], $this->draft['scope'], $revision );
		}
		return $this->store->change_state( $id, $revision, $operation );
	}

	private function action_form( $operation, $label, $row = null ) {
		$id = $row ? $row['id'] : 0;
		?>
		<form method="post" action="<?php echo esc_url( $this->url() ); ?>" class="marrison-snippet-action">
			<?php wp_nonce_field( 'marrison_php_snippets_' . $operation . '_' . $id ); ?>
			<input type="hidden" name="operation" value="<?php echo esc_attr( $operation ); ?>">
			<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>">
			<input type="hidden" name="revision" value="<?php echo esc_attr( $row ? $row['revision'] : '' ); ?>">
			<button class="button button-secondary" type="submit"><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
	}

	public function render() {
		if ( ! Marrison_Addon_PHP_Snippets_Store::can_manage() ) {
			wp_die( esc_html__( 'Operazione non autorizzata.', 'marrison-addon' ), '', [ 'response' => 403 ] );
		}
		$scopes = [ 'everywhere' => __( 'Ovunque', 'marrison-addon' ), 'frontend' => __( 'Solo frontend', 'marrison-addon' ), 'backend' => __( 'Solo backend', 'marrison-addon' ) ];
		$editing = isset( $_GET['edit'] ) && is_scalar( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$entry = $this->draft ?: ( $editing && $this->store->ready() ? $this->store->get( $editing ) : null );
		$entry = $entry ?: [ 'id' => 0, 'name' => '', 'code' => '', 'scope' => 'everywhere', 'revision' => '' ];
		?>
		<div class="wrap marrison-admin-page marrison-php-snippets">
			<h1>PHP Snippets</h1>
			<p><?php esc_html_e( 'Gli snippet vengono caricati prima di plugins_loaded. Salvataggio e duplicazione li lasciano disattivati; attivali dopo la verifica del codice.', 'marrison-addon' ); ?></p>
			<?php if ( $this->notice ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $this->notice->get_error_message() ); ?></p></div>
			<?php elseif ( isset( $_GET['saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Operazione completata.', 'marrison-addon' ); ?></p></div>
			<?php endif; ?>
			<?php if ( defined( 'MARRISON_ADDON_PHP_SNIPPETS_SAFE_MODE' ) && MARRISON_ADDON_PHP_SNIPPETS_SAFE_MODE ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'Modalità di recupero attiva: nessuno snippet viene eseguito.', 'marrison-addon' ); ?></p></div>
			<?php endif; ?>
			<?php if ( ! Marrison_Addon_PHP_Snippets_Stream::available() ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Esecuzione PHP in memoria non disponibile su questo server: gli snippet non vengono eseguiti.', 'marrison-addon' ); ?></p></div>
			<?php endif; ?>
			<?php if ( ! $this->store->ready() ) { echo '</div>'; return; } ?>
			<div class="marrison-module-card">
				<h2><?php esc_html_e( 'Snippet registrati', 'marrison-addon' ); ?></h2>
				<div class="marrison-snippets-table"><table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'Nome', 'marrison-addon' ); ?></th><th><?php esc_html_e( 'Stato', 'marrison-addon' ); ?></th><th><?php esc_html_e( 'Ambito', 'marrison-addon' ); ?></th><th><?php esc_html_e( 'Azioni', 'marrison-addon' ); ?></th></tr></thead>
					<tbody>
					<?php $rows = $this->store->listing(); ?>
					<?php foreach ( (array) $rows as $row ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( $this->url( [ 'edit' => $row['id'] ] ) ); ?>"><?php echo esc_html( $row['name'] ); ?></a>
							<?php if ( $row['last_error'] ) : ?><details><summary><?php esc_html_e( 'Errore: snippet sospeso', 'marrison-addon' ); ?></summary><pre><?php echo esc_html( $row['last_error'] ); ?></pre></details><?php endif; ?></td>
							<td><?php echo esc_html( $row['active'] ? __( 'Attivo', 'marrison-addon' ) : __( 'Disattivato', 'marrison-addon' ) ); ?></td>
							<td><?php echo esc_html( $scopes[ $row['scope'] ] ?? $row['scope'] ); ?></td>
							<td class="marrison-snippet-actions">
								<a class="button" href="<?php echo esc_url( $this->url( [ 'edit' => $row['id'] ] ) ); ?>"><?php esc_html_e( 'Modifica', 'marrison-addon' ); ?></a>
								<?php $this->action_form( $row['active'] ? 'deactivate' : 'activate', $row['active'] ? __( 'Disattiva', 'marrison-addon' ) : __( 'Attiva', 'marrison-addon' ), $row ); ?>
								<?php $this->action_form( 'duplicate', __( 'Duplica', 'marrison-addon' ), $row ); ?>
								<?php $this->action_form( 'delete', __( 'Elimina', 'marrison-addon' ), $row ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
					<?php if ( ! $rows ) : ?><tr><td colspan="4"><?php esc_html_e( 'Nessuno snippet registrato.', 'marrison-addon' ); ?></td></tr><?php endif; ?>
					</tbody>
				</table></div>
				<p><a class="button" href="<?php echo esc_url( $this->url() ); ?>"><?php esc_html_e( 'Nuovo snippet', 'marrison-addon' ); ?></a></p>
			</div>
			<div class="marrison-module-card">
				<h2><?php echo esc_html( $entry['id'] ? __( 'Modifica snippet', 'marrison-addon' ) : __( 'Nuovo snippet', 'marrison-addon' ) ); ?></h2>
				<form method="post" action="<?php echo esc_url( $this->url( $entry['id'] ? [ 'edit' => $entry['id'] ] : [] ) ); ?>">
					<?php wp_nonce_field( 'marrison_php_snippets_save_' . $entry['id'] ); ?>
					<input type="hidden" name="operation" value="save">
					<input type="hidden" name="id" value="<?php echo esc_attr( $entry['id'] ); ?>">
					<input type="hidden" name="revision" value="<?php echo esc_attr( $entry['revision'] ); ?>">
					<p><label for="marrison-snippet-name"><?php esc_html_e( 'Nome', 'marrison-addon' ); ?></label><br><input class="regular-text" id="marrison-snippet-name" name="name" maxlength="190" required value="<?php echo esc_attr( $entry['name'] ); ?>"></p>
					<p><label for="marrison-snippet-scope"><?php esc_html_e( 'Ambito di esecuzione', 'marrison-addon' ); ?></label><br><select id="marrison-snippet-scope" name="scope">
					<?php foreach ( $scopes as $scope => $label ) : ?><option value="<?php echo esc_attr( $scope ); ?>" <?php selected( $entry['scope'], $scope ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
					</select></p>
					<p><label for="marrison-php-snippet-code"><?php esc_html_e( 'Codice PHP (senza tag di apertura)', 'marrison-addon' ); ?></label></p>
					<textarea id="marrison-php-snippet-code" name="code" rows="16" class="large-text code" spellcheck="false"><?php echo esc_textarea( $entry['code'] ); ?></textarea>
					<?php submit_button( __( 'Salva disattivato', 'marrison-addon' ) ); ?>
				</form>
			</div>
			<div class="marrison-module-card">
				<h2><?php esc_html_e( 'Recupero', 'marrison-addon' ); ?></h2>
				<p><?php esc_html_e( 'Questa pagina, il pannello moduli e il login saltano sempre gli snippet. Puoi disattivare gli snippet problematici anche se impediscono il normale caricamento del sito.', 'marrison-addon' ); ?></p>
				<?php $this->action_form( 'deactivate_all', __( 'Disattiva tutti gli snippet', 'marrison-addon' ) ); ?>
				<p><?php esc_html_e( 'Per sospendere tutte le esecuzioni, aggiungi in wp-config.php prima del caricamento di WordPress:', 'marrison-addon' ); ?></p>
				<code>define( 'MARRISON_ADDON_PHP_SNIPPETS_SAFE_MODE', true );</code>
				<p><?php esc_html_e( 'La validazione controlla la sintassi, non la correttezza del codice. Un errore fatale può interrompere la richiesta corrente: lo snippet identificato viene sospeso per le richieste successive. Arresti del processo, exit e alcuni errori in shutdown o in dipendenze non sono recuperabili automaticamente. PHP personalizzato ha gli stessi privilegi del plugin e non è una sandbox.', 'marrison-addon' ); ?></p>
				<p><?php esc_html_e( 'Frontend comprende le richieste non amministrative, inclusi REST e cron; backend comprende wp-admin e admin-ajax. Login e pagine di recupero sono esclusi in ogni ambito.', 'marrison-addon' ); ?></p>
			</div>
		</div>
		<?php
	}
}
