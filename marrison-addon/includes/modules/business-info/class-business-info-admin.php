<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Marrison_Addon_Business_Info_Admin {
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'menu' ], 20 );
		add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
		add_action( 'admin_post_marrison_business_info_save', [ $this, 'save' ] );
	}
	public function menu() { add_submenu_page( 'marrison_addon_panel', __( 'Info Azienda', 'marrison-addon' ), __( 'Info Azienda', 'marrison-addon' ), 'manage_options', 'marrison-business-info', [ $this, 'render' ] ); }
	public function assets() {
		if ( 'marrison-business-info' !== ( $_GET['page'] ?? '' ) || ! current_user_can( 'manage_options' ) ) { return; }
		wp_enqueue_media();
		foreach ( [ 'css', 'js' ] as $ext ) {
			$path = 'includes/modules/business-info/assets/admin.' . $ext;
			if ( 'css' === $ext ) { wp_enqueue_style( 'marrison-business-info-admin', plugins_url( $path, Marrison_Addon::plugin_file() ), [], Marrison_Addon::asset_version( $path ) ); }
			else { wp_enqueue_script( 'marrison-business-info-admin', plugins_url( $path, Marrison_Addon::plugin_file() ), [ 'media-views' ], Marrison_Addon::asset_version( $path ), true ); }
		}
		$data = Marrison_Addon_Business_Info_Store::data();
		$draft = get_transient( 'marrison_bi_draft_' . get_current_user_id() );
		if ( is_array( $draft ) && isset( $_GET['invalid'] ) ) { $data = $draft['data']; }
		wp_localize_script( 'marrison-business-info-admin', 'marrisonBusinessInfoAdmin', [ 'data' => $data, 'company' => Marrison_Addon_Business_Info_Store::company_fields(), 'contactTypes' => Marrison_Addon_Business_Info_Store::contact_types(), 'customTypes' => Marrison_Addon_Business_Info_Store::custom_types(), 'days' => Marrison_Addon_Business_Hours::days(), 'logoUrl' => wp_get_attachment_image_url( absint( $data['company']['logo'] ?? 0 ), 'thumbnail' ), 'locked' => array_map( static function( $rows ) { return array_column( $rows, 'key', 'id' ); }, array_intersect_key( Marrison_Addon_Business_Info_Store::data(), array_flip( [ 'contacts', 'locations', 'social', 'custom' ] ) ) ) ] );
	}
	public function save() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permessi insufficienti.', 'marrison-addon' ) ); }
		check_admin_referer( 'marrison_business_info_save' );
		$payload = isset( $_POST['business_info_payload'] ) && is_string( $_POST['business_info_payload'] ) ? wp_unslash( $_POST['business_info_payload'] ) : '';
		$input = json_decode( $payload, true ); $result = Marrison_Addon_Business_Info_Store::sanitize( $input );
		$url = admin_url( 'admin.php?page=marrison-business-info' );
		if ( is_wp_error( $result ) ) {
			$this->save_error( $input, $result->get_error_message(), $url );
		}
		update_option( Marrison_Addon_Business_Info_Store::OPTION, $result, false );
		if ( get_option( Marrison_Addon_Business_Info_Store::OPTION ) !== $result ) {
			$this->save_error( $input, __( 'Salvataggio non riuscito nel database. Riprova.', 'marrison-addon' ), $url );
		}
		delete_transient( 'marrison_bi_draft_' . get_current_user_id() );
		do_action( 'marrison_addon/business_info_updated', $result );
		wp_safe_redirect( $url . '&saved=1' ); exit;
	}
	private function save_error( $input, $message, $url ) {
		set_transient( 'marrison_bi_draft_' . get_current_user_id(), [ 'data' => is_array( $input ) ? $input : Marrison_Addon_Business_Info_Store::data(), 'message' => $message ], 600 );
		wp_safe_redirect( $url . '&invalid=1' ); exit;
	}
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		echo '<div class="wrap marrison-business-info-admin"><h1>' . esc_html__( 'Info Azienda', 'marrison-addon' ) . '</h1>';
		if ( isset( $_GET['saved'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Dati salvati. I contenuti collegati useranno i valori aggiornati.', 'marrison-addon' ) . '</p></div>'; }
		$draft = get_transient( 'marrison_bi_draft_' . get_current_user_id() );
		if ( isset( $_GET['invalid'] ) ) {
			$message = is_array( $draft ) ? $draft['message'] . ' ' . __( 'Le modifiche sono ancora nel modulo: correggi e salva nuovamente.', 'marrison-addon' ) : __( 'Salvataggio non riuscito. La bozza non è disponibile; verifica i dati prima di riprovare.', 'marrison-addon' );
			echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
		}
		echo '<p><span class="dashicons dashicons-shield" aria-hidden="true"></span> ' . esc_html__( 'L’icona scudo indica i dati usati dal Cookie Manager. Tutti i campi possono essere lasciati vuoti; il riepilogo segnala le informazioni essenziali mancanti.', 'marrison-addon' ) . '</p>';
		echo '<div id="marrison-bi-completeness" role="status" aria-live="polite"></div>';
		echo '<form id="marrison-business-info-form" method="post" novalidate action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'marrison_business_info_save' );
		echo '<input type="hidden" name="action" value="marrison_business_info_save"><input type="hidden" id="marrison-business-info-payload" name="business_info_payload">';
		echo '<noscript><div class="notice notice-error"><p>' . esc_html__( 'Attiva JavaScript per compilare e salvare Info Azienda.', 'marrison-addon' ) . '</p></div></noscript><div id="marrison-business-info-editor"></div>';
		echo '<p class="submit"><button id="marrison-business-info-submit" type="submit" class="button button-primary" disabled>' . esc_html__( 'Salva Info Azienda', 'marrison-addon' ) . '</button></p></form>';
		echo '<details><summary>' . esc_html__( 'Uso nei contenuti', 'marrison-addon' ) . '</summary><p><code>[marrison_info key="email_principale"]</code> · <code>[marrison_info key="contacts.email_principale" output="link"]</code></p><p><code>[marrison_business_hours]</code> · <code>[marrison_business_status]</code></p><p>' . esc_html__( 'In Elementor scegli Marrison Business Info nei tag dinamici oppure il widget Marrison Business Hours. Le chiavi salvate restano fisse anche dopo il riordinamento. Per campi omonimi usa il percorso completo: contacts.chiave, locations.chiave.city, social.chiave o custom.chiave.', 'marrison-addon' ) . '</p></details></div>';
	}
}
