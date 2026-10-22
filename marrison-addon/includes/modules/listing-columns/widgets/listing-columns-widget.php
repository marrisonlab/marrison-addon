<?php
namespace Marrison_Addon\Modules\Listing_Columns\Widgets;

use Elementor\Controls_Manager as Controls;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Listing_Columns_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'marrison_listing_columns'; }
	public function get_title() { return esc_html__( 'Colonne Listing', 'marrison-addon' ); }
	public function get_icon() { return 'eicon-columns'; }
	public function get_categories() { return [ 'general' ]; }
	public function get_keywords() { return [ 'listing', 'grid', 'griglia', 'colonne', 'jetengine', 'marrison' ]; }
	public function get_style_depends() { return [ 'marrison-listing-columns' ]; }
	public function get_script_depends() { return [ 'marrison-listing-columns' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'listing', [ 'label' => esc_html__( 'Listing e colonne', 'marrison-addon' ) ] );
		$this->add_control( 'target_selector', [
			'label' => esc_html__( 'Selettore CSS del Listing', 'marrison-addon' ),
			'type' => Controls::TEXT, 'default' => '.griglia-variabile', 'placeholder' => '#catalogo',
			'label_block' => true,
			'description' => esc_html__( 'Assegna al widget Listing Grid un ID CSS (es. catalogo) e scrivi #catalogo qui, oppure usa una classe (es. .griglia-variabile). Lo stesso selettore può controllare più listing. Non usare con slider o masonry.', 'marrison-addon' ),
		] );
		foreach ( [ 'min_columns' => [ 'Colonne minime', 2 ], 'max_columns' => [ 'Colonne massime', 4 ], 'default_columns' => [ 'Colonne iniziali', 3 ] ] as $name => $control ) {
			$this->add_responsive_control( $name, [ 'label' => esc_html__( $control[0], 'marrison-addon' ), 'type' => Controls::NUMBER, 'min' => 1, 'max' => 12, 'step' => 1, 'default' => $control[1] ] );
		}
		$this->add_control( 'remember', [ 'label' => esc_html__( 'Ricorda la scelta', 'marrison-addon' ), 'type' => Controls::SWITCHER, 'default' => 'yes', 'description' => esc_html__( 'Salva la preferenza nel browser, separata per pagina, selettore e breakpoint. Disattivato nell’editor.', 'marrison-addon' ) ] );
		$this->add_control( 'min_viewport', [ 'label' => esc_html__( 'Attiva da larghezza (px)', 'marrison-addon' ), 'type' => Controls::NUMBER, 'min' => 0, 'max' => 3840, 'step' => 1, 'default' => 768, 'description' => esc_html__( 'Sotto questa larghezza i pulsanti sono nascosti e il Listing mantiene le sue colonne responsive. Usa 0 per tutti i dispositivi.', 'marrison-addon' ) ] );
		$this->end_controls_section();

		$this->start_controls_section( 'appearance', [ 'label' => esc_html__( 'Pulsanti e icone', 'marrison-addon' ) ] );
		$this->add_control( 'display_mode', [ 'label' => esc_html__( 'Mostra', 'marrison-addon' ), 'type' => Controls::SELECT, 'default' => 'both', 'options' => [ 'both' => esc_html__( 'Icona e numero', 'marrison-addon' ), 'icon' => esc_html__( 'Solo icona', 'marrison-addon' ), 'number' => esc_html__( 'Solo numero', 'marrison-addon' ) ] ] );
		$this->add_control( 'icon_position', [ 'label' => esc_html__( 'Posizione icona', 'marrison-addon' ), 'type' => Controls::SELECT, 'default' => 'before', 'options' => [ 'before' => esc_html__( 'Prima', 'marrison-addon' ), 'after' => esc_html__( 'Dopo', 'marrison-addon' ), 'above' => esc_html__( 'Sopra', 'marrison-addon' ) ], 'condition' => [ 'display_mode' => 'both' ] ] );
		$this->add_control( 'group_label', [ 'label' => esc_html__( 'Etichetta accessibile', 'marrison-addon' ), 'type' => Controls::TEXT, 'default' => esc_html__( 'Numero di colonne', 'marrison-addon' ) ] );
		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'columns', [ 'label' => esc_html__( 'Numero colonne', 'marrison-addon' ), 'type' => Controls::NUMBER, 'min' => 1, 'max' => 12, 'default' => 2 ] );
		$repeater->add_control( 'label', [ 'label' => esc_html__( 'Testo pulsante', 'marrison-addon' ), 'type' => Controls::TEXT, 'description' => esc_html__( 'Vuoto: mostra il numero di colonne.', 'marrison-addon' ) ] );
		$repeater->add_control( 'icon', [ 'label' => esc_html__( 'Icona personalizzata', 'marrison-addon' ), 'type' => Controls::ICONS, 'description' => esc_html__( 'Vuota: usa la griglia SVG automatica. Puoi scegliere dalla libreria Elementor o caricare un SVG.', 'marrison-addon' ) ] );
		$this->add_control( 'custom_buttons', [ 'label' => esc_html__( 'Personalizza singoli pulsanti', 'marrison-addon' ), 'type' => Controls::REPEATER, 'fields' => $repeater->get_controls(), 'default' => [], 'title_field' => '{{{ columns }}}' ] );
		$this->end_controls_section();

		$this->start_controls_section( 'layout_style', [ 'label' => esc_html__( 'Disposizione', 'marrison-addon' ), 'tab' => Controls::TAB_STYLE ] );
		$this->add_responsive_control( 'alignment', [ 'label' => esc_html__( 'Allineamento', 'marrison-addon' ), 'type' => Controls::CHOOSE, 'options' => [
			'flex-start' => [ 'title' => esc_html__( 'Sinistra', 'marrison-addon' ), 'icon' => 'eicon-text-align-left' ],
			'center' => [ 'title' => esc_html__( 'Centro', 'marrison-addon' ), 'icon' => 'eicon-text-align-center' ],
			'flex-end' => [ 'title' => esc_html__( 'Destra', 'marrison-addon' ), 'icon' => 'eicon-text-align-right' ],
		], 'default' => 'flex-end', 'selectors' => [ '{{WRAPPER}} .marrison-listing-columns' => 'justify-content: {{VALUE}};' ] ] );
		$this->add_responsive_control( 'direction', [ 'label' => esc_html__( 'Direzione', 'marrison-addon' ), 'type' => Controls::SELECT, 'default' => 'row', 'options' => [ 'row' => esc_html__( 'Orizzontale', 'marrison-addon' ), 'column' => esc_html__( 'Verticale', 'marrison-addon' ) ], 'selectors' => [ '{{WRAPPER}} .marrison-listing-columns' => 'flex-direction: {{VALUE}};' ] ] );
		$this->slider( 'buttons_gap', 'Spazio tra pulsanti', '{{WRAPPER}} .marrison-listing-columns', 'gap', 8 );
		$this->dimensions( 'group_padding', 'Padding gruppo', '{{WRAPPER}} .marrison-listing-columns', 'padding' );
		$this->end_controls_section();

		$this->start_controls_section( 'buttons_style', [ 'label' => esc_html__( 'Pulsanti', 'marrison-addon' ), 'tab' => Controls::TAB_STYLE ] );
		$button = '{{WRAPPER}} .marrison-listing-columns__button';
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [ 'name' => 'typography', 'selector' => $button ] );
		$this->dimensions( 'button_padding', 'Padding', $button, 'padding' );
		$this->dimensions( 'button_radius', 'Raggio bordo', $button, 'border-radius' );
		$this->slider( 'button_width', 'Larghezza minima', $button, 'min-width' );
		$this->slider( 'button_height', 'Altezza minima', $button, 'min-height' );
		$this->slider( 'icon_gap', 'Spazio icona e testo', $button, 'gap', 6 );
		$this->start_controls_tabs( 'button_states' );
		foreach ( [ 'normal' => [ 'Normale', $button ], 'hover' => [ 'Hover', $button . ':hover' ], 'active' => [ 'Attivo', $button . '[aria-pressed="true"]' ], 'focus' => [ 'Focus', $button . ':focus-visible' ] ] as $state => $data ) {
			$this->start_controls_tab( $state . '_tab', [ 'label' => esc_html__( $data[0], 'marrison-addon' ) ] );
			$this->add_control( $state . '_color', [ 'label' => esc_html__( 'Colore testo', 'marrison-addon' ), 'type' => Controls::COLOR, 'selectors' => [ $data[1] => 'color: {{VALUE}};' ] ] );
			$this->add_control( $state . '_icon_color', [ 'label' => esc_html__( 'Colore icona', 'marrison-addon' ), 'type' => Controls::COLOR, 'selectors' => [ $data[1] . ' .marrison-listing-columns__icon' => 'color: {{VALUE}};' ] ] );
			$this->add_group_control( \Elementor\Group_Control_Background::get_type(), [ 'name' => $state . '_background', 'types' => [ 'classic', 'gradient' ], 'selector' => $data[1] ] );
			$this->add_group_control( \Elementor\Group_Control_Border::get_type(), [ 'name' => $state . '_border', 'selector' => $data[1] ] );
			$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), [ 'name' => $state . '_shadow', 'selector' => $data[1] ] );
			$this->end_controls_tab();
		}
		$this->end_controls_tabs();
		$this->end_controls_section();

		$this->start_controls_section( 'icons_style', [ 'label' => esc_html__( 'Icone', 'marrison-addon' ), 'tab' => Controls::TAB_STYLE, 'condition' => [ 'display_mode!' => 'number' ] ] );
		$this->slider( 'icon_size', 'Dimensione', '{{WRAPPER}} .marrison-listing-columns__icon', 'font-size', 24 );
		$this->add_control( 'icon_stroke', [ 'label' => esc_html__( 'Spessore griglia SVG', 'marrison-addon' ), 'type' => Controls::SLIDER, 'range' => [ 'px' => [ 'min' => 0.5, 'max' => 3, 'step' => 0.1 ] ], 'selectors' => [ '{{WRAPPER}} .marrison-listing-columns__grid' => 'stroke-width: {{SIZE}};' ] ] );
		$this->end_controls_section();
		// Elementor supplies the native Advanced tab (responsive visibility, motion, attributes, CSS, etc.).
	}

	private function slider( $name, $label, $selector, $property, $default = null ) {
		$args = [ 'label' => esc_html__( $label, 'marrison-addon' ), 'type' => Controls::SLIDER, 'size_units' => [ 'px', 'em', 'rem' ], 'range' => [ 'px' => [ 'min' => 0, 'max' => 300 ] ], 'selectors' => [ $selector => $property . ': {{SIZE}}{{UNIT}};' ] ];
		if ( null !== $default ) { $args['default'] = [ 'size' => $default, 'unit' => 'px' ]; }
		$this->add_responsive_control( $name, $args );
	}

	private function dimensions( $name, $label, $selector, $property ) {
		$this->add_responsive_control( $name, [ 'label' => esc_html__( $label, 'marrison-addon' ), 'type' => Controls::DIMENSIONS, 'size_units' => [ 'px', '%', 'em', 'rem' ], 'selectors' => [ $selector => $property . ': {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
	}

	/** Resolve empty responsive values using Elementor's desktop-to-mobile inheritance. */
	private function get_column_profiles( $settings ) {
		$base = [];
		foreach ( [ 'min_columns' => 2, 'max_columns' => 4, 'default_columns' => 3 ] as $key => $fallback ) {
			$base[ $key ] = isset( $settings[ $key ] ) && '' !== $settings[ $key ] ? (int) $settings[ $key ] : $fallback;
		}
		$profiles = [ $this->column_profile( 'desktop', $base ) ];
		$breakpoints = isset( \Elementor\Plugin::$instance->breakpoints ) ? \Elementor\Plugin::$instance->breakpoints->get_active_breakpoints() : [];
		$ordered = [];
		foreach ( $breakpoints as $name => $breakpoint ) {
			$ordered[] = [ 'name' => $name, 'width' => (int) $breakpoint->get_value(), 'direction' => $breakpoint->get_direction() ];
		}
		usort( $ordered, static function ( $a, $b ) { return $b['width'] <=> $a['width']; } );
		$inherited = $base;
		foreach ( $ordered as $breakpoint ) {
			// Widescreen inherits desktop, never a tablet/laptop override.
			$values = 'min' === $breakpoint['direction'] ? $base : $inherited;
			foreach ( array_keys( $base ) as $key ) {
				$responsive_key = $key . '_' . $breakpoint['name'];
				if ( isset( $settings[ $responsive_key ] ) && '' !== $settings[ $responsive_key ] ) {
					$values[ $key ] = (int) $settings[ $responsive_key ];
				}
			}
			$profiles[] = array_merge( $breakpoint, $this->column_profile( $breakpoint['name'], $values ) );
			if ( 'max' === $breakpoint['direction'] ) { $inherited = $values; }
		}
		return $profiles;
	}

	private function column_profile( $name, $values ) {
		$min = max( 1, min( 12, $values['min_columns'] ) );
		$max = max( $min, min( 12, $values['max_columns'] ) );
		return [ 'name' => $name, 'minColumns' => $min, 'maxColumns' => $max, 'defaultColumns' => max( $min, min( $max, $values['default_columns'] ) ) ];
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$profiles = $this->get_column_profiles( $settings );
		$desktop = $profiles[0];
		$min = min( array_column( $profiles, 'minColumns' ) );
		$max = max( array_column( $profiles, 'maxColumns' ) );
		$default = $desktop['defaultColumns'];
		$mode = in_array( $settings['display_mode'] ?? '', [ 'both', 'icon', 'number' ], true ) ? $settings['display_mode'] : 'both';
		$position = in_array( $settings['icon_position'] ?? '', [ 'before', 'after', 'above' ], true ) ? $settings['icon_position'] : 'before';
		$config = [ 'selector' => trim( $settings['target_selector'] ?? '.griglia-variabile' ), 'defaultColumns' => $default, 'profiles' => $profiles, 'remember' => 'yes' === ( $settings['remember'] ?? 'yes' ), 'minViewport' => max( 0, min( 3840, (int) ( $settings['min_viewport'] ?? 768 ) ) ) ];
		$custom = [];
		foreach ( (array) ( $settings['custom_buttons'] ?? [] ) as $item ) {
			$custom[ (int) ( $item['columns'] ?? 0 ) ] = $item;
		}
		?>
		<div class="marrison-listing-columns marrison-listing-columns--<?php echo esc_attr( $position ); ?>" role="group" aria-label="<?php echo esc_attr( $settings['group_label'] ?? __( 'Numero di colonne', 'marrison-addon' ) ); ?>" data-marrison-listing-columns="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
			<?php for ( $columns = $min; $columns <= $max; $columns++ ) :
				$item = $custom[ $columns ] ?? [];
				$label = isset( $item['label'] ) && '' !== trim( $item['label'] ) ? $item['label'] : (string) $columns;
				$accessible = sprintf( _n( '%d colonna', '%d colonne', $columns, 'marrison-addon' ), $columns );
				?>
				<button class="marrison-listing-columns__button" type="button" data-cols="<?php echo esc_attr( $columns ); ?>" aria-label="<?php echo esc_attr( $accessible ); ?>" aria-pressed="<?php echo $columns === $default ? 'true' : 'false'; ?>"<?php echo $columns < $desktop['minColumns'] || $columns > $desktop['maxColumns'] ? ' hidden' : ''; ?>>
					<?php if ( 'number' !== $mode ) : ?>
						<span class="marrison-listing-columns__icon" aria-hidden="true">
							<?php if ( ! empty( $item['icon']['value'] ) ) {
								\Elementor\Icons_Manager::render_icon( $item['icon'], [ 'aria-hidden' => 'true' ] );
							} else {
								$this->render_grid_icon( $columns );
							} ?>
						</span>
					<?php endif; ?>
					<?php if ( 'icon' !== $mode ) : ?><span class="marrison-listing-columns__label"><?php echo esc_html( $label ); ?></span><?php endif; ?>
				</button>
			<?php endfor; ?>
		</div>
		<?php
	}

	private function render_grid_icon( $columns ) {
		$gap = $columns <= 4 ? 2 : ( $columns <= 8 ? 1 : 0.6 );
		$width = ( 20 - $gap * ( $columns - 1 ) ) / $columns;
		echo '<svg class="marrison-listing-columns__grid" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true" focusable="false">';
		for ( $column = 0; $column < $columns; $column++ ) {
			for ( $row = 0; $row < 2; $row++ ) {
				echo '<rect x="' . esc_attr( round( 2 + $column * ( $width + $gap ), 3 ) ) . '" y="' . (int) ( 3 + $row * 10 ) . '" width="' . esc_attr( round( $width, 3 ) ) . '" height="8" rx="0.6" />';
			}
		}
		echo '</svg>';
	}
}
