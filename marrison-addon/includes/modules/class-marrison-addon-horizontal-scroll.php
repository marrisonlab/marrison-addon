<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds horizontal scrolling to ordinary Elementor Containers.
 */
class Marrison_Addon_Horizontal_Scroll {

	private $assets_enqueued = false;

	public function __construct() {
		add_action( 'elementor/element/container/section_layout/after_section_end', [ $this, 'register_controls' ] );
		add_action( 'elementor/frontend/container/before_render', [ $this, 'before_render' ] );
		add_filter( 'elementor/frontend/builder_content_data', [ $this, 'maybe_enqueue_assets_from_data' ] );
	}

	public function register_controls( $container ) {
		$container->start_controls_section(
			'marrison_horizontal_scroll_section',
			[
				'label' => esc_html__( 'Marrison — Scroll Orizzontale', 'marrison-addon' ),
				'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
			]
		);

		$container->add_control(
			'marrison_horizontal_scroll_enabled',
			[
				'label'        => esc_html__( 'Abilita Scroll Orizzontale', 'marrison-addon' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'description'  => esc_html__( 'Usa un Container figlio disposto in orizzontale e imposta le larghezze dei suoi elementi con Elementor.', 'marrison-addon' ),
			]
		);

		$container->add_control(
			'marrison_horizontal_scroll_direction',
			[
				'label'     => esc_html__( 'Direzione', 'marrison-addon' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'options'   => [
					'rtl' => esc_html__( 'Destra → Sinistra', 'marrison-addon' ),
					'ltr' => esc_html__( 'Sinistra → Destra', 'marrison-addon' ),
				],
				'default'   => 'rtl',
				'condition' => [ 'marrison_horizontal_scroll_enabled' => 'yes' ],
			]
		);

		$container->add_control(
			'marrison_horizontal_scroll_pin',
			[
				'label'        => esc_html__( 'Pin', 'marrison-addon' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => [ 'marrison_horizontal_scroll_enabled' => 'yes' ],
			]
		);

		$container->add_control(
			'marrison_horizontal_scroll_speed',
			[
				'label'       => esc_html__( 'Durata scroll', 'marrison-addon' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 0.25,
				'max'         => 4,
				'step'        => 0.05,
				'default'     => 1,
				'description' => esc_html__( '1 = distanza naturale; 0,5 = più rapido; 2 = più lento. Con Pin, la distanza verticale è (larghezza contenuto − larghezza visibile) × durata.', 'marrison-addon' ),
				'condition'   => [ 'marrison_horizontal_scroll_enabled' => 'yes' ],
			]
		);

		$container->add_control(
			'marrison_horizontal_scroll_snap',
			[
				'label'        => esc_html__( 'Snap per slide', 'marrison-addon' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'description'  => esc_html__( 'Mostra una slide alla volta invece di muovere il contenuto in modo continuo.', 'marrison-addon' ),
				'condition'    => [ 'marrison_horizontal_scroll_enabled' => 'yes' ],
			]
		);

		$container->add_control(
			'marrison_horizontal_scroll_snap_threshold',
			[
				'label'       => esc_html__( 'Soglia snap rotella', 'marrison-addon' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 40,
				'max'         => 600,
				'step'        => 10,
				'default'     => 220,
				'description' => esc_html__( 'Aumenta il valore se lo snap scatta troppo facilmente. Valori più bassi rendono il cambio slide più immediato.', 'marrison-addon' ),
				'condition'   => [
					'marrison_horizontal_scroll_enabled' => 'yes',
					'marrison_horizontal_scroll_snap'    => 'yes',
				],
			]
		);

		$container->add_control(
			'marrison_horizontal_scroll_background_scale',
			[
				'label'        => esc_html__( 'Scala sfondo', 'marrison-addon' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'description'  => esc_html__( 'Scala lo sfondo del Container esterno, immagine, video o colore, seguendo l’avanzamento dello scroll orizzontale.', 'marrison-addon' ),
				'condition'    => [ 'marrison_horizontal_scroll_enabled' => 'yes' ],
			]
		);

		$container->add_control(
			'marrison_horizontal_scroll_background_scale_from',
			[
				'label'       => esc_html__( 'Scala sfondo iniziale (%)', 'marrison-addon' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 0,
				'max'         => 200,
				'step'        => 1,
				'default'     => 0,
				'description' => esc_html__( '0 = invisibile; 100 = dimensione naturale del Container.', 'marrison-addon' ),
				'condition'   => [
					'marrison_horizontal_scroll_enabled'          => 'yes',
					'marrison_horizontal_scroll_background_scale' => 'yes',
				],
			]
		);

		$container->add_control(
			'marrison_horizontal_scroll_background_scale_to',
			[
				'label'       => esc_html__( 'Scala sfondo finale (%)', 'marrison-addon' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 0,
				'max'         => 200,
				'step'        => 1,
				'default'     => 100,
				'description' => esc_html__( '100 porta lo sfondo alla dimensione del Container a fine scroll.', 'marrison-addon' ),
				'condition'   => [
					'marrison_horizontal_scroll_enabled'          => 'yes',
					'marrison_horizontal_scroll_background_scale' => 'yes',
				],
			]
		);

		$container->add_responsive_control(
			'marrison_horizontal_scroll_device',
			[
				'label'          => esc_html__( 'Attivo su questo dispositivo', 'marrison-addon' ),
				'type'           => \Elementor\Controls_Manager::SWITCHER,
				'return_value'   => 'yes',
				'default'        => 'yes',
				'tablet_default' => 'yes',
				'mobile_default' => '',
				'devices'        => [ 'desktop', 'tablet', 'mobile' ],
				'condition'      => [ 'marrison_horizontal_scroll_enabled' => 'yes' ],
			]
		);

		$container->end_controls_section();
	}

	public function before_render( $container ) {
		if ( ! is_object( $container ) || ! method_exists( $container, 'get_data' ) || ! method_exists( $container, 'add_render_attribute' ) ) {
			return;
		}

		$settings = $container->get_data( 'settings' );
		if ( ! is_array( $settings ) || 'yes' !== ( $settings['marrison_horizontal_scroll_enabled'] ?? '' ) ) {
			return;
		}

		if ( ! Marrison_Addon_Context::is_public_frontend_request() ) {
			return;
		}

		$speed = isset( $settings['marrison_horizontal_scroll_speed'] ) && is_numeric( $settings['marrison_horizontal_scroll_speed'] )
			? (float) $settings['marrison_horizontal_scroll_speed']
			: 1.0;

		$config = [
			'direction' => 'ltr' === ( $settings['marrison_horizontal_scroll_direction'] ?? 'rtl' ) ? 'ltr' : 'rtl',
			'pin'       => 'yes' === ( $settings['marrison_horizontal_scroll_pin'] ?? 'yes' ),
			'speed'     => max( 0.25, min( 4, $speed ) ),
			'snap'      => 'yes' === ( $settings['marrison_horizontal_scroll_snap'] ?? '' ),
			'snapThreshold' => $this->normalize_snap_threshold( $settings['marrison_horizontal_scroll_snap_threshold'] ?? 220 ),
			'backgroundScale' => [
				'enabled' => 'yes' === ( $settings['marrison_horizontal_scroll_background_scale'] ?? '' ),
				'from'    => $this->normalize_scale_percent( $settings['marrison_horizontal_scroll_background_scale_from'] ?? 0 ),
				'to'      => $this->normalize_scale_percent( $settings['marrison_horizontal_scroll_background_scale_to'] ?? 100 ),
			],
			'devices'   => [
				'desktop' => 'yes' === ( $settings['marrison_horizontal_scroll_device'] ?? 'yes' ),
				'tablet'  => 'yes' === ( $settings['marrison_horizontal_scroll_device_tablet'] ?? 'yes' ),
				'mobile'  => 'yes' === ( $settings['marrison_horizontal_scroll_device_mobile'] ?? '' ),
			],
			'breakpoints' => $this->get_breakpoints(),
		];

		$container->add_render_attribute( '_wrapper', 'data-marrison-horizontal-scroll', wp_json_encode( $config ) );
		$this->enqueue_assets();
	}

	private function normalize_scale_percent( $value ) {
		$value = is_numeric( $value ) ? (float) $value : 0.0;
		return max( 0, min( 2, $value / 100 ) );
	}

	private function normalize_snap_threshold( $value ) {
		$value = is_numeric( $value ) ? (int) $value : 220;
		return max( 40, min( 600, $value ) );
	}

	/**
	 * Elementor can serve cached Container markup without calling before_render.
	 * This filter still receives the document's data before cached markup is printed.
	 */
	public function maybe_enqueue_assets_from_data( $elements ) {
		if ( Marrison_Addon_Context::is_public_frontend_request() && is_array( $elements ) && $this->has_enabled_container( $elements ) ) {
			$this->enqueue_assets();
		}

		return $elements;
	}

	private function has_enabled_container( $elements ) {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			if ( 'container' === ( $element['elType'] ?? '' ) && 'yes' === ( $element['settings']['marrison_horizontal_scroll_enabled'] ?? '' ) ) {
				return true;
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) && $this->has_enabled_container( $element['elements'] ) ) {
				return true;
			}
		}

		return false;
	}

	private function get_breakpoints() {
		$values = [ 'mobile' => 767, 'tablet' => 1024 ];
		$plugin = \Elementor\Plugin::$instance;
		if ( ! isset( $plugin->breakpoints ) || ! method_exists( $plugin->breakpoints, 'get_active_breakpoints' ) ) {
			return $values;
		}

		foreach ( $plugin->breakpoints->get_active_breakpoints() as $name => $breakpoint ) {
			if ( isset( $values[ $name ] ) && method_exists( $breakpoint, 'get_value' ) ) {
				$values[ $name ] = (int) $breakpoint->get_value();
			}
		}

		return $values;
	}

	private function enqueue_assets() {
		if ( $this->assets_enqueued ) {
			return;
		}

		$this->assets_enqueued = true;
		$plugin_root_file = Marrison_Addon::plugin_file();

		wp_enqueue_style(
			'marrison-addon-horizontal-scroll',
			plugins_url( 'assets/css/horizontal-scroll.css', $plugin_root_file ),
			[],
			Marrison_Addon::asset_version( 'assets/css/horizontal-scroll.css' )
		);
		wp_enqueue_script(
			'marrison-addon-horizontal-scroll',
			plugins_url( 'assets/js/horizontal-scroll.js', $plugin_root_file ),
			[ 'elementor-frontend' ],
			Marrison_Addon::asset_version( 'assets/js/horizontal-scroll.js' ),
			true
		);
	}
}
