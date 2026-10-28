<?php
/** Native, optional login appearance editor. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Marrison_Addon_Login_Designer {
	const OPTION = 'marrison_addon_login_designer';
	private $settings;

	public function __construct() {
		// The Customizer may also publish changesets from cron; keep its registration hook.
		add_action( 'customize_register', [ $this, 'customize' ] );
		if ( is_admin() ) {
			add_action( 'admin_menu', [ $this, 'menu' ] );
			add_action( 'customize_controls_enqueue_scripts', [ $this, 'controls_assets' ] );
		}
		if ( isset( $_GET['marrison_login_preview'] ) ) {
			add_action( 'template_redirect', [ $this, 'preview' ], 0 );
		}
		if ( Marrison_Addon_Context::is_login_request() ) {
			add_action( 'login_enqueue_scripts', [ $this, 'styles' ], 99 );
			add_action( 'login_head', [ $this, 'custom_css' ], 99 );
			add_filter( 'login_headerurl', [ $this, 'logo_url' ] );
			add_filter( 'login_headertext', [ $this, 'logo_text' ] );
			add_filter( 'login_display_language_dropdown', [ $this, 'display_language_dropdown' ] );
		}
	}

	/** Only invoked while configuring/publishing this module or turning it off. */
	public function non_autoload_option() {
		add_option( self::OPTION, [], '', false );
		if ( function_exists( 'wp_set_option_autoload' ) ) {
			wp_set_option_autoload( self::OPTION, false );
		} else {
			// WordPress 6.0–6.3 has no public API for changing autoload alone.
			global $wpdb;
			if ( $wpdb->update( $wpdb->options, [ 'autoload'=>'no' ], [ 'option_name'=>self::OPTION, 'autoload'=>'yes' ] ) ) {
				wp_cache_delete( 'alloptions', 'options' );
				wp_cache_delete( self::OPTION, 'options' );
			}
		}
	}

	/** type, default, label, section, optional choices or numeric bounds. */
	public static function schema() {
		return [
			'logo_enabled' => [ 'checkbox', false, 'Logo personalizzato', 'logo' ],
			'logo_image' => [ 'image', '', 'Immagine logo', 'logo' ],
			'logo_width' => [ 'number', 84, 'Larghezza massima logo (px)', 'logo', 20, 600 ],
			'logo_height' => [ 'number', 84, 'Altezza logo (px)', 'logo', 20, 400 ],
			'logo_margin' => [ 'number', 25, 'Margine inferiore (px)', 'logo', 0, 150 ],
			'logo_url' => [ 'url', '', 'Destinazione logo (vuoto: home del sito)', 'logo' ],
			'logo_text' => [ 'text', '', 'Testo accessibile (vuoto: WordPress)', 'logo' ],
			'page_bg' => [ 'color', '#f0f0f1', 'Colore sfondo', 'background' ],
			'page_opacity' => [ 'number', 100, 'Opacità sfondo pagina (%)', 'background', 0, 100 ],
			'page_image' => [ 'image', '', 'Immagine sfondo', 'background' ],
			'page_position' => [ 'select', 'center center', 'Posizione', 'background', self::positions() ],
			'page_size' => [ 'select', 'cover', 'Dimensione', 'background', [ 'cover'=>'Cover', 'contain'=>'Contain', 'auto'=>'Auto' ] ],
			'page_repeat' => [ 'select', 'no-repeat', 'Ripetizione', 'background', [ 'no-repeat'=>'Nessuna', 'repeat'=>'Ripeti', 'repeat-x'=>'Orizzontale', 'repeat-y'=>'Verticale' ] ],
			'gradient_enabled' => [ 'checkbox', false, 'Attiva gradiente', 'background' ],
			'gradient_end' => [ 'color', '#c3c4c7', 'Secondo colore gradiente', 'background' ],
			'gradient_opacity' => [ 'number', 100, 'Opacità secondo colore (%)', 'background', 0, 100 ],
			'gradient_angle' => [ 'number', 135, 'Angolo gradiente', 'background', 0, 360 ],
			'overlay_color' => [ 'color', '#000000', 'Colore overlay', 'background' ],
			'overlay_opacity' => [ 'number', 0, 'Opacità overlay (%)', 'background', 0, 100 ],
			'hide_language_dropdown' => [ 'checkbox', false, 'Nascondi selettore lingua', 'layout' ],
			'layout' => [ 'select', 'center', 'Posizione del form', 'layout', [ 'center'=>'Centrato', 'left'=>'Sinistra', 'right'=>'Destra', 'split'=>'Due colonne (area grafica + form)' ] ],
			'split_side' => [ 'select', 'right', 'Lato form', 'layout', [ 'left'=>'Sinistra', 'right'=>'Destra' ] ],
			'split_ratio' => [ 'number', 50, 'Larghezza area form (%)', 'layout', 30, 70 ],
			'split_bg' => [ 'color', '#2271b1', 'Colore area grafica', 'layout' ],
			'split_opacity' => [ 'number', 100, 'Opacità area grafica (%)', 'layout', 0, 100 ],
			'split_image' => [ 'image', '', 'Immagine area grafica', 'layout' ],
			'split_position' => [ 'select', 'center center', 'Posizione area grafica', 'layout', self::positions() ],
			'form_width' => [ 'number', 320, 'Larghezza massima form (px)', 'form', 240, 800 ],
			'form_bg' => [ 'color', '#ffffff', 'Sfondo form', 'form' ],
			'form_opacity' => [ 'number', 100, 'Opacità sfondo (%)', 'form', 0, 100 ],
			'form_padding' => [ 'number', 26, 'Padding (px)', 'form', 0, 100 ],
			'form_radius' => [ 'number', 0, 'Raggio bordo (px)', 'form', 0, 100 ],
			'form_border_color' => [ 'color', '#c3c4c7', 'Colore bordo', 'form' ],
			'form_border_width' => [ 'number', 1, 'Spessore bordo (px)', 'form', 0, 10 ],
			'shadow_color' => [ 'color', '#000000', 'Ombra / glow: colore', 'form' ],
			'shadow_x' => [ 'number', 0, 'Ombra: offset orizzontale (px)', 'form', -50, 50 ],
			'shadow_spread' => [ 'number', 0, 'Ombra / glow: espansione (px)', 'form', 0, 40 ],
			'shadow_y' => [ 'number', 1, 'Ombra: offset verticale (px)', 'form', -50, 50 ],
			'shadow_blur' => [ 'number', 3, 'Ombra: sfocatura (px)', 'form', 0, 100 ],
			'shadow_opacity' => [ 'number', 4, 'Ombra: opacità (%)', 'form', 0, 100 ],
			'input_bg' => [ 'color', '#ffffff', 'Sfondo input', 'inputs' ],
			'input_opacity' => [ 'number', 100, 'Opacità sfondo input (%)', 'inputs', 0, 100 ],
			'input_text' => [ 'color', '#2c3338', 'Testo input', 'inputs' ],
			'input_border_color' => [ 'color', '#8c8f94', 'Bordo input', 'inputs' ],
			'input_border_width' => [ 'number', 1, 'Spessore bordo (px)', 'inputs', 0, 10 ],
			'input_radius' => [ 'number', 4, 'Raggio bordo (px)', 'inputs', 0, 50 ],
			'input_padding' => [ 'number', 3, 'Padding verticale input (px)', 'inputs', 0, 30 ],
			'input_padding_x' => [ 'number', 3, 'Padding orizzontale input (px)', 'inputs', 0, 60 ],
			'input_size' => [ 'number', 24, 'Dimensione testo input (px)', 'inputs', 12, 48 ],
			'input_focus' => [ 'color', '#2271b1', 'Bordo focus', 'inputs' ],
			'label_color' => [ 'color', '#2c3338', 'Colore etichette', 'inputs' ],
			'button_bg' => [ 'color', '#2271b1', 'Sfondo pulsante', 'button' ],
			'button_opacity' => [ 'number', 100, 'Opacità sfondo pulsante (%)', 'button', 0, 100 ],
			'button_text' => [ 'color', '#ffffff', 'Testo pulsante', 'button' ],
			'button_hover' => [ 'color', '#135e96', 'Sfondo hover', 'button' ],
			'button_hover_opacity' => [ 'number', 100, 'Opacità sfondo hover (%)', 'button', 0, 100 ],
			'button_hover_text' => [ 'color', '#ffffff', 'Testo hover', 'button' ],
			'button_radius' => [ 'number', 3, 'Raggio bordo (px)', 'button', 0, 60 ],
			'button_padding' => [ 'number', 12, 'Padding orizzontale (px)', 'button', 0, 60 ],
			'button_padding_y' => [ 'number', 0, 'Padding verticale (px)', 'button', 0, 40 ],
			'button_size' => [ 'number', 13, 'Dimensione testo (px)', 'button', 10, 32 ],
			'button_weight' => [ 'select', '400', 'Peso font', 'button', [ '400'=>'Normale', '500'=>'Medio', '600'=>'Semibold', '700'=>'Grassetto' ] ],
			'button_full' => [ 'checkbox', false, 'Larghezza 100%', 'button' ],
			'font' => [ 'select', 'system-ui, sans-serif', 'Font di sistema', 'type', [ 'system-ui, sans-serif'=>'Sistema', 'Arial, sans-serif'=>'Arial', 'Georgia, serif'=>'Georgia', 'monospace'=>'Monospace' ] ],
			'text_size' => [ 'number', 14, 'Dimensione testo (px)', 'type', 12, 24 ],
			'text_color' => [ 'color', '#3c434a', 'Colore testo', 'type' ],
			'link_color' => [ 'color', '#50575e', 'Colore link', 'type' ],
			'link_hover' => [ 'color', '#135e96', 'Colore link hover', 'type' ],
			'label_size' => [ 'number', 14, 'Dimensione etichette (px)', 'type', 12, 28 ],
			'message_bg' => [ 'color', '#ffffff', 'Sfondo messaggi', 'type' ],
			'message_opacity' => [ 'number', 100, 'Opacità sfondo messaggi (%)', 'type', 0, 100 ],
			'message_color' => [ 'color', '#3c434a', 'Testo messaggi', 'type' ],
			'message_border' => [ 'color', '#72aee6', 'Accento messaggi informativi', 'type' ],
			'custom_css' => [ 'textarea', '', 'CSS avanzato (solo login)', 'css' ],
		];
	}

	private static function positions() { return [ 'center center'=>'Centro', 'left center'=>'Sinistra', 'right center'=>'Destra', 'center top'=>'Alto', 'center bottom'=>'Basso' ]; }
	public static function defaults() { return array_map( static function ( $field ) { return $field[1]; }, self::schema() ); }
	public static function sanitize_value( $value, $key ) {
		$schema = self::schema();
		if ( ! isset( $schema[$key] ) ) { return null; }
		$f = $schema[$key];
		if ( ! is_scalar( $value ) ) { return $f[1]; }
		switch ( $f[0] ) {
			case 'checkbox': return in_array( $value, [ true, 1, '1', 'true' ], true );
			case 'number': return is_numeric( $value ) ? max( $f[4], min( $f[5], (int) $value ) ) : $f[1];
			case 'color': return sanitize_hex_color( $value ) ?: $f[1];
			case 'select': return isset( $f[4][$value] ) ? (string) $value : $f[1];
			case 'image': case 'url': return esc_url_raw( $value, [ 'http', 'https' ] );
			case 'textarea': return wp_strip_all_tags( $value );
			default: return sanitize_text_field( $value );
		}
	}
	public function settings() {
		if ( null === $this->settings ) {
			$stored = get_option( self::OPTION, [] );
			$this->settings = self::defaults();
			foreach ( is_array( $stored ) ? $stored : [] as $key => $value ) {
				if ( array_key_exists( $key, $this->settings ) ) { $this->settings[$key] = self::sanitize_value( $value, $key ); }
			}
		}
		return $this->settings;
	}
	public function menu() {
		add_submenu_page( 'marrison_addon_panel', 'Login Designer', 'Login Designer', 'manage_options', 'marrison_addon_login_designer', [ $this, 'admin_page' ] );
	}
	public function admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$this->non_autoload_option();
		$url = add_query_arg( [ 'url'=>add_query_arg( 'marrison_login_preview', '1', home_url( '/' ) ), 'autofocus[panel]'=>'marrison_login', 'return'=>admin_url( 'admin.php?page=marrison_addon_login_designer' ) ], admin_url( 'customize.php' ) );
		echo '<div class="wrap"><h1>Login Designer</h1><p>Modulo attivo. Personalizza il login WordPress con anteprima live. Le modifiche si applicano solo dopo Pubblica. Spegnendo il modulo o disattivando Marrison Addon, le impostazioni di Login Designer vengono eliminate.</p>';
		if ( current_user_can( 'customize' ) ) {
			echo '<a class="button button-primary" href="' . esc_url( $url ) . '">Personalizza login</a><p>Puoi ripristinare i predefiniti nel pannello Preset del Customizer e pubblicarli dopo averli verificati.</p>';
		} else { echo '<p>Per aprire il Customizer è richiesta anche la capability customize.</p>'; }
		echo '</div>';
	}
	private function editor_request() {
		if ( ! isset( $_GET['url'] ) || ! is_string( $_GET['url'] ) ) { return false; }
		$query = wp_parse_url( wp_unslash( $_GET['url'] ), PHP_URL_QUERY );
		parse_str( is_string( $query ) ? $query : '', $args );
		return isset( $args['marrison_login_preview'] ) && '1' === $args['marrison_login_preview'];
	}
	public function customize( $manager ) {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$relevant = $this->editor_request() || isset( $_GET['marrison_login_preview'] );
		if ( ! $relevant ) {
			$values = $manager->unsanitized_post_values();
			// New AJAX changeset data are not yet included in unsanitized_post_values()
			// when customize_register runs. Recognize those requests before validation.
			foreach ( [ 'customize_changeset_data', 'customized' ] as $field ) {
				if ( isset( $_POST[$field] ) && is_string( $_POST[$field] ) ) {
					$posted = json_decode( wp_unslash( $_POST[$field] ), true );
					if ( is_array( $posted ) ) { $values = array_merge( $values, $posted ); }
				}
			}
			foreach ( array_keys( $values ) as $id ) {
				if ( 'mld_preset' === $id || 0 === strpos( (string) $id, self::OPTION . '[' ) ) { $relevant = true; break; }
			}
		}
		if ( ! $relevant ) { return; }
		add_action( 'customize_save', [ $this, 'non_autoload_option' ] );
		add_action( 'customize_save_after', [ $this, 'non_autoload_option' ] );
		$manager->add_panel( 'marrison_login', [ 'title'=>'Login Designer', 'priority'=>1, 'capability'=>'manage_options' ] );
		$sections = [ 'presets'=>'Preset e ripristino', 'logo'=>'Logo', 'background'=>'Sfondo pagina', 'layout'=>'Layout e posizione del form', 'form'=>'Contenitore form', 'inputs'=>'Campi input', 'button'=>'Pulsante', 'type'=>'Tipografia e messaggi', 'css'=>'CSS avanzato' ];
		foreach ( $sections as $id => $title ) {
			$args = [ 'title'=>$title, 'panel'=>'marrison_login', 'capability'=>'manage_options' ];
			if ( 'layout' === $id ) { $args['description'] = 'Scegli la posizione del form. Con Due colonne puoi personalizzare anche l’area grafica laterale. Su tablet e smartphone (fino a 782 px) l’area grafica è nascosta e il form torna centrato. Per lo sfondo dell’intera pagina usa Sfondo pagina.'; }
			$manager->add_section( 'mld_' . $id, $args );
		}
		$manager->add_setting( 'mld_preset', [ 'type'=>'marrison_login_ui', 'default'=>'', 'capability'=>'manage_options', 'transport'=>'postMessage', 'sanitize_callback'=>'sanitize_key' ] );
		// This setting is UI-only: never persisted by the Customizer.
		$manager->add_control( 'mld_preset', [ 'section'=>'mld_presets', 'label'=>'Applica preset (bozza)', 'type'=>'select', 'choices'=>[ ''=>'Seleziona…', 'classic'=>'WordPress Classic / Ripristina predefiniti', 'minimal'=>'Minimal', 'corporate'=>'Corporate', 'dark'=>'Dark', 'glass'=>'Glass', 'split'=>'Split' ] ] );
		foreach ( self::schema() as $key => $field ) {
			if ( 'custom_css' === $key && ! current_user_can( 'edit_css' ) ) { continue; }
			$id = self::OPTION . '[' . $key . ']';
			$manager->add_setting( $id, [ 'type'=>'option', 'default'=>$field[1], 'capability'=>'custom_css' === $key ? 'edit_css' : 'manage_options', 'transport'=>'postMessage', 'sanitize_callback'=>static function ( $value ) use ( $key ) { return self::sanitize_value( $value, $key ); } ] );
			$args = [ 'label'=>$field[2], 'section'=>'mld_' . $field[3], 'type'=>$field[0] ];
			if ( 0 === strpos( $key, 'split_' ) ) {
				$args['active_callback'] = static function () use ( $manager ) { return 'split' === $manager->get_setting( self::OPTION . '[layout]' )->value(); };
			}
			if ( 'shadow_color' === $key ) { $args['description'] = 'Per un glow: scegli un colore luminoso, offset 0, sfocatura 20–30 px, espansione 2–4 px e opacità 60–100%.'; }
			if ( 'split_bg' === $key ) { $args['description'] = 'Colore dell’area laterale nel layout Due colonne. Un’immagine, se selezionata, copre il colore.'; }
			if ( 'select' === $field[0] ) { $args['choices'] = $field[4]; }
			if ( 'number' === $field[0] ) { $args['input_attrs'] = [ 'min'=>$field[4], 'max'=>$field[5], 'step'=>1 ]; }
			if ( 'color' === $field[0] ) { $manager->add_control( new WP_Customize_Color_Control( $manager, $id, $args ) ); }
			elseif ( 'image' === $field[0] ) { $manager->add_control( new WP_Customize_Image_Control( $manager, $id, $args ) ); }
			else { $manager->add_control( $id, $args ); }
		}
	}
	public static function presets() {
		$d = self::defaults();
		return [
			'classic'=>$d,
			'minimal'=>array_merge( $d, [ 'page_bg'=>'#ffffff', 'form_border_width'=>0, 'shadow_opacity'=>0, 'button_full'=>true ] ),
			'corporate'=>array_merge( $d, [ 'page_bg'=>'#e8eef5', 'form_radius'=>10, 'form_padding'=>32, 'button_full'=>true, 'button_weight'=>'600', 'shadow_blur'=>30, 'shadow_opacity'=>12 ] ),
			'dark'=>array_merge( $d, [ 'page_bg'=>'#111827', 'form_bg'=>'#1f2937', 'input_bg'=>'#111827', 'input_text'=>'#f9fafb', 'label_color'=>'#f9fafb', 'text_color'=>'#f9fafb', 'link_color'=>'#d1d5db', 'link_hover'=>'#ffffff', 'form_border_color'=>'#374151', 'message_bg'=>'#1f2937', 'message_color'=>'#ffffff', 'form_radius'=>12 ] ),
			'glass'=>array_merge( $d, [ 'page_bg'=>'#1e3a8a', 'gradient_enabled'=>true, 'gradient_end'=>'#7c3aed', 'form_opacity'=>85, 'form_radius'=>20, 'shadow_blur'=>40, 'shadow_opacity'=>20 ] ),
			'split'=>array_merge( $d, [ 'layout'=>'split', 'page_bg'=>'#ffffff', 'split_bg'=>'#1e3a8a', 'form_border_width'=>0, 'shadow_opacity'=>0, 'button_full'=>true ] ),
		];
	}
	private function asset_version( $path ) {
		return Marrison_Addon::VERSION . '.' . filemtime( Marrison_Addon::plugin_path( $path ) );
	}
	public function controls_assets() {
		if ( ! $this->editor_request() || ! current_user_can( 'manage_options' ) ) { return; }
		wp_enqueue_script( 'marrison-login-controls', plugins_url( 'assets/js/login-designer-controls.js', Marrison_Addon::plugin_file() ), [ 'customize-controls' ], $this->asset_version( 'assets/js/login-designer-controls.js' ), true );
		wp_localize_script( 'marrison-login-controls', 'marrisonLoginPresets', [ 'presets'=>self::presets(), 'confirm'=>'Sostituire le impostazioni correnti con questo preset? Le modifiche restano in bozza fino a Pubblica.' ] );
	}
	public function display_language_dropdown( $display ) {
		$s = $this->settings();
		return $s['hide_language_dropdown'] ? false : $display;
	}
	public function logo_url( $url ) { $s = $this->settings(); return $s['logo_enabled'] && $s['logo_image'] && $s['logo_url'] ? $s['logo_url'] : home_url( '/' ); }
	public function logo_text( $text ) { $s = $this->settings(); return $s['logo_enabled'] && $s['logo_image'] && $s['logo_text'] ? $s['logo_text'] : $text; }

	/** Shared PHP/JS protocol: numeric values are raw; CSS applies units via calc(). */
	public static function background_opacities() {
		return [ 'page_bg'=>'page_opacity', 'gradient_end'=>'gradient_opacity', 'split_bg'=>'split_opacity', 'form_bg'=>'form_opacity', 'input_bg'=>'input_opacity', 'button_bg'=>'button_opacity', 'button_hover'=>'button_hover_opacity', 'message_bg'=>'message_opacity', 'shadow_color'=>'shadow_opacity' ];
	}
	public static function variables( $s ) {
		$css = '';
		foreach ( self::schema() as $key => $f ) {
			if ( in_array( $f[0], [ 'color', 'number', 'select' ], true ) ) { $css .= '--mld-' . str_replace( '_', '-', $key ) . ':' . $s[$key] . ';'; }
			if ( 'image' === $f[0] ) { $css .= '--mld-' . str_replace( '_', '-', $key ) . ':' . ( $s[$key] ? 'url(' . wp_json_encode( esc_url_raw( $s[$key] ), JSON_HEX_TAG | JSON_HEX_AMP ) . ')' : 'none' ) . ';'; }
		}
		foreach ( self::background_opacities() as $color => $opacity ) {
			$hex = ltrim( $s[$color], '#' );
			if ( 3 === strlen( $hex ) ) { $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2]; }
			$rgb = hexdec( $hex );
			$css .= '--mld-' . str_replace( '_', '-', $color ) . '-alpha:rgba(' . ( ( $rgb >> 16 ) & 255 ) . ',' . ( ( $rgb >> 8 ) & 255 ) . ',' . ( $rgb & 255 ) . ',' . ( $s[$opacity] / 100 ) . ');';
		}
		$css .= '--mld-gradient:' . ( $s['gradient_enabled'] ? 'linear-gradient(calc(var(--mld-gradient-angle)*1deg),var(--mld-page-bg-alpha),var(--mld-gradient-end-alpha))' : 'none' ) . ';';
		$css .= '--mld-button-width:' . ( $s['button_full'] ? '100%' : 'auto' ) . ';';
		$css .= '--mld-logo-display:' . ( $s['logo_enabled'] && $s['logo_image'] ? 'block' : 'none' ) . ';';
		return 'body.login{' . $css . '}';
	}
	public function styles() {
		$s = $this->settings();
		wp_enqueue_style( 'marrison-login-designer', plugins_url( 'assets/css/login-designer.css', Marrison_Addon::plugin_file() ), [ 'login' ], $this->asset_version( 'assets/css/login-designer.css' ) );
		wp_add_inline_style( 'marrison-login-designer', self::variables( $s ) );
		// Values are constrained by schema; custom CSS is confined to this document.

		add_filter( 'login_body_class', function ( $classes ) use ( $s ) { $classes[] = 'mld-layout-' . $s['layout']; $classes[] = 'mld-side-' . $s['split_side']; if ( $s['logo_enabled'] && $s['logo_image'] ) { $classes[] = 'mld-logo'; } return $classes; } );
	}
	public function custom_css() {
		$s = $this->settings();
		echo '<style id="marrison-login-custom-css">' . $s['custom_css'] . '</style>'; // Tags stripped by sanitize_value().
	}
	public function preview() {
		if ( ! isset( $_GET['marrison_login_preview'] ) ) { return; }
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'customize' ) ) { wp_die( esc_html__( 'Accesso non autorizzato.', 'marrison-addon' ), '', [ 'response'=>403 ] ); }
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
		$this->styles();
		$s = $this->settings();
		if ( is_customize_preview() ) {
			wp_enqueue_script( 'marrison-login-preview', plugins_url( 'assets/js/login-designer-preview.js', Marrison_Addon::plugin_file() ), [ 'customize-preview' ], $this->asset_version( 'assets/js/login-designer-preview.js' ), true );
			wp_localize_script( 'marrison-login-preview', 'marrisonLoginPreview', [ 'settings'=>$s, 'schema'=>self::schema(), 'backgroundOpacities'=>self::background_opacities(), 'option'=>self::OPTION, 'logoText'=>__( 'Powered by WordPress' ), 'logoUrl'=>home_url( '/' ) ] );
		}
		// Print only core login assets and the Customizer channel, never theme styles/scripts.
		?><!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Login Designer — Anteprima</title><?php wp_print_styles( [ 'dashicons', 'buttons', 'forms', 'l10n', 'login', 'marrison-login-designer', 'customize-preview' ] ); $this->custom_css(); if ( is_customize_preview() ) { global $wp_customize; $wp_customize->customize_preview_loading_style(); } ?></head>
		<body class="login wp-core-ui mld-layout-<?php echo esc_attr( $s['layout'] ); ?> mld-side-<?php echo esc_attr( $s['split_side'] ); ?> <?php echo $s['logo_enabled'] && $s['logo_image'] ? 'mld-logo' : ''; ?>">
		<div id="login"><h1 role="presentation"><a href="#" tabindex="-1"><?php echo esc_html( $this->logo_text( __( 'Powered by WordPress' ) ) ); ?></a></h1>
		<div class="message"><p>Anteprima grafica — nessuna autenticazione viene eseguita.</p></div>
		<form id="loginform" onsubmit="return false;" autocomplete="off"><p><label for="user_login">Nome utente o indirizzo email</label><input type="text" id="user_login" class="input" value="utente.esempio" autocomplete="off"></p>
		<div class="user-pass-wrap"><label for="user_pass">Password</label><div class="wp-pwd"><input type="password" id="user_pass" class="input password-input" value="anteprima" autocomplete="new-password"></div></div>
		<p class="forgetmenot"><input type="checkbox" id="rememberme"><label for="rememberme">Ricordami</label></p><p class="submit"><button type="button" class="button button-primary button-large">Accedi</button></p></form>
		<p id="nav"><a href="#" onclick="return false;">Password dimenticata?</a></p><p id="backtoblog"><a href="#" onclick="return false;">← Torna al sito</a></p><div class="privacy-policy-page-link"><a href="#" onclick="return false;">Privacy Policy</a></div></div>
		<?php if ( get_available_languages() ) : ?>
		<div class="language-switcher" <?php echo $s['hide_language_dropdown'] ? 'hidden' : ''; ?>><form onsubmit="return false;">
		<label for="language-switcher-locales"><span class="dashicons dashicons-translation" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Language' ); ?></span></label>
		<?php wp_dropdown_languages( [ 'name'=>'wp_lang', 'id'=>'language-switcher-locales', 'selected'=>get_locale(), 'show_available_translations'=>false, 'explicit_option_en_us'=>true ] ); ?>
		<button type="button" class="button"><?php esc_html_e( 'Change' ); ?></button></form></div>
		<?php endif; ?>
		<?php if ( is_customize_preview() ) { $wp_customize->customize_preview_settings(); wp_scripts()->do_items( [ 'customize-preview', 'marrison-login-preview' ] ); } ?></body></html><?php
		exit;
	}
}
