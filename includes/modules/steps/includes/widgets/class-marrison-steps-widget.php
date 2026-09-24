<?php
/**
 * Widget Elementor Marrison Steps.
 *
 * @package MarrisonSteps
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Repeater;
use Elementor\Utils;
use Elementor\Widget_Base;

class Marrison_Steps_Widget extends Widget_Base {
	public function get_name(): string {
		return 'marrison_steps';
	}

	public function get_title(): string {
		return esc_html__( 'Marrison Steps', 'marrison-steps' );
	}

	public function get_icon(): string {
		return 'eicon-flow';
	}

	public function get_categories(): array {
		return array( 'general' );
	}

	public function get_style_depends(): array {
		return array( 'marrison-steps' );
	}

	protected function register_controls(): void {
		$this->register_content_controls();
		$this->register_connector_controls();
		$this->register_style_controls();
	}

	private function register_content_controls(): void {
		$this->start_controls_section(
			'section_steps',
			array(
				'label' => esc_html__( 'Step', 'marrison-steps' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'media_type',
			array(
				'label'   => esc_html__( 'Elemento visivo', 'marrison-steps' ),
				'type'    => Controls_Manager::CHOOSE,
				'options' => array(
					'number' => array(
						'title' => esc_html__( 'Numero', 'marrison-steps' ),
						'icon'  => 'eicon-number-field',
					),
					'image'  => array(
						'title' => esc_html__( 'Immagine', 'marrison-steps' ),
						'icon'  => 'eicon-image',
					),
					'icon'   => array(
						'title' => esc_html__( 'Icona', 'marrison-steps' ),
						'icon'  => 'eicon-star',
					),
				),
				'default' => 'number',
				'toggle'  => false,
			)
		);

		$repeater->add_control(
			'number',
			array(
				'label'       => esc_html__( 'Numero', 'marrison-steps' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '1',
				'label_block' => false,
				'condition'   => array(
					'media_type' => 'number',
				),
			)
		);

		$repeater->add_control(
			'image',
			array(
				'label'     => esc_html__( 'Immagine', 'marrison-steps' ),
				'type'      => Controls_Manager::MEDIA,
				'default'   => array(
					'url' => Utils::get_placeholder_image_src(),
				),
				'condition' => array(
					'media_type' => 'image',
				),
			)
		);

		$repeater->add_control(
			'icon',
			array(
				'label'       => esc_html__( 'Icona', 'marrison-steps' ),
				'type'        => Controls_Manager::ICONS,
				'default'     => array(
					'value'   => 'fas fa-check',
					'library' => 'fa-solid',
				),
				'label_block' => true,
				'condition'   => array(
					'media_type' => 'icon',
				),
			)
		);

		$repeater->add_control(
			'title',
			array(
				'label'       => esc_html__( 'Titolo', 'marrison-steps' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Titolo step', 'marrison-steps' ),
				'label_block' => true,
			)
		);

		$repeater->add_control(
			'text',
			array(
				'label'      => esc_html__( 'Testo', 'marrison-steps' ),
				'type'       => Controls_Manager::TEXTAREA,
				'default'    => esc_html__( 'Descrizione dello step.', 'marrison-steps' ),
				'rows'       => 4,
				'show_label' => true,
			)
		);

		$this->add_control(
			'steps',
			array(
				'label'       => esc_html__( 'Lista step', 'marrison-steps' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'default'     => array(
					array(
						'number' => '1',
						'title'  => esc_html__( 'Primo step', 'marrison-steps' ),
						'text'   => esc_html__( 'Descrivi qui il primo passaggio.', 'marrison-steps' ),
					),
					array(
						'number' => '2',
						'title'  => esc_html__( 'Secondo step', 'marrison-steps' ),
						'text'   => esc_html__( 'Descrivi qui il secondo passaggio.', 'marrison-steps' ),
					),
					array(
						'number' => '3',
						'title'  => esc_html__( 'Terzo step', 'marrison-steps' ),
						'text'   => esc_html__( 'Descrivi qui il terzo passaggio.', 'marrison-steps' ),
					),
				),
				'title_field' => '{{{ title }}}',
			)
		);

		$this->add_control(
			'steps_limit_note',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Il widget mostra da 2 a 8 step. Se ne aggiungi più di 8, quelli successivi non vengono renderizzati.', 'marrison-steps' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);

		$this->end_controls_section();
	}

	private function register_connector_controls(): void {
		$this->start_controls_section(
			'section_connectors',
			array(
				'label' => esc_html__( 'Linee e frecce', 'marrison-steps' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'connector_type',
			array(
				'label'   => esc_html__( 'Tipo', 'marrison-steps' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'arrow',
				'options' => array(
					'none'         => esc_html__( 'Nessuno', 'marrison-steps' ),
					'line'         => esc_html__( 'Linea', 'marrison-steps' ),
					'arrow'        => esc_html__( 'Freccia', 'marrison-steps' ),
					'double-arrow' => esc_html__( 'Doppia freccia', 'marrison-steps' ),
					'chevron'      => esc_html__( 'Chevron', 'marrison-steps' ),
					'triangle'     => esc_html__( 'Triangolo', 'marrison-steps' ),
				),
			)
		);

		$this->add_control(
			'connector_style',
			array(
				'label'     => esc_html__( 'Stile linea', 'marrison-steps' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'solid',
				'options'   => array(
					'solid'  => esc_html__( 'Continua', 'marrison-steps' ),
					'dashed' => esc_html__( 'Tratteggiata', 'marrison-steps' ),
					'dotted' => esc_html__( 'Puntinata', 'marrison-steps' ),
				),
				'condition' => array(
					'connector_type!' => 'none',
				),
				'selectors' => array(
					'{{WRAPPER}} .marrison-steps' => '--marrison-steps-connector-style: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'connector_color',
			array(
				'label'     => esc_html__( 'Colore', 'marrison-steps' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'condition' => array(
					'connector_type!' => 'none',
				),
				'selectors' => array(
					'{{WRAPPER}} .marrison-steps' => '--marrison-steps-connector-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'connector_width',
			array(
				'label'      => esc_html__( 'Spessore', 'marrison-steps' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 1,
						'max' => 12,
					),
				),
				'default'    => array(
					'size' => 2,
					'unit' => 'px',
				),
				'condition'  => array(
					'connector_type!' => 'none',
				),
				'selectors'  => array(
					'{{WRAPPER}} .marrison-steps' => '--marrison-steps-connector-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'connector_length',
			array(
				'label'      => esc_html__( 'Lunghezza orizzontale', 'marrison-steps' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array(
					'px' => array(
						'min' => 16,
						'max' => 240,
					),
					'%'  => array(
						'min' => 10,
						'max' => 100,
					),
				),
				'default'    => array(
					'size' => 80,
					'unit' => 'px',
				),
				'condition'  => array(
					'connector_type!' => 'none',
				),
				'selectors'  => array(
					'{{WRAPPER}} .marrison-steps' => '--marrison-steps-connector-horizontal-length: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'connector_vertical_length',
			array(
				'label'      => esc_html__( 'Lunghezza verticale', 'marrison-steps' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 8,
						'max' => 160,
					),
				),
				'default'    => array(
					'size' => 36,
					'unit' => 'px',
				),
				'condition'  => array(
					'connector_type!' => 'none',
				),
				'selectors'  => array(
					'{{WRAPPER}} .marrison-steps' => '--marrison-steps-connector-vertical-length: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'arrow_size',
			array(
				'label'      => esc_html__( 'Dimensione freccia', 'marrison-steps' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 6,
						'max' => 32,
					),
				),
				'default'    => array(
					'size' => 12,
					'unit' => 'px',
				),
				'condition'  => array(
					'connector_type!' => 'none',
				),
				'selectors'  => array(
					'{{WRAPPER}} .marrison-step-connector-head' => '--marrison-steps-arrow-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	private function register_style_controls(): void {
		$this->start_controls_section(
			'section_layout_style',
			array(
				'label' => esc_html__( 'Layout', 'marrison-steps' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'layout_direction',
			array(
				'label'                => esc_html__( 'Orientamento', 'marrison-steps' ),
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => array(
					'horizontal' => array(
						'title' => esc_html__( 'Orizzontale', 'marrison-steps' ),
						'icon'  => 'eicon-navigation-horizontal',
					),
					'vertical'   => array(
						'title' => esc_html__( 'Verticale', 'marrison-steps' ),
						'icon'  => 'eicon-navigation-vertical',
					),
				),
				'default'              => 'horizontal',
				'tablet_default'       => 'horizontal',
				'mobile_default'       => 'vertical',
				'toggle'               => false,
				'frontend_available'   => true,
			)
		);

		$this->add_responsive_control(
			'align',
			array(
				'label'     => esc_html__( 'Allineamento', 'marrison-steps' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'left'   => array(
						'title' => esc_html__( 'Sinistra', 'marrison-steps' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Centro', 'marrison-steps' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => esc_html__( 'Destra', 'marrison-steps' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'default'   => 'center',
				'selectors' => array(
					'{{WRAPPER}} .marrison-step' => 'text-align: {{VALUE}}; align-items: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'step_gap',
			array(
				'label'      => esc_html__( 'Spazio tra step', 'marrison-steps' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 120,
					),
				),
				'default'    => array(
					'size' => 24,
					'unit' => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .marrison-steps-list' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'step_padding',
			array(
				'label'      => esc_html__( 'Padding step', 'marrison-steps' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .marrison-step-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'step_background',
				'selector' => '{{WRAPPER}} .marrison-step-card',
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'step_border',
				'selector' => '{{WRAPPER}} .marrison-step-card',
			)
		);

		$this->add_responsive_control(
			'step_border_radius',
			array(
				'label'      => esc_html__( 'Raggio bordo', 'marrison-steps' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .marrison-step-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'step_shadow',
				'selector' => '{{WRAPPER}} .marrison-step-card',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_marker_style',
			array(
				'label' => esc_html__( 'Numero, icona e immagine', 'marrison-steps' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'marker_size',
			array(
				'label'      => esc_html__( 'Dimensione', 'marrison-steps' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 24,
						'max' => 180,
					),
				),
				'default'    => array(
					'size' => 64,
					'unit' => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .marrison-step-marker' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'marker_color',
			array(
				'label'     => esc_html__( 'Colore numero/icona', 'marrison-steps' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .marrison-step-number, {{WRAPPER}} .marrison-step-icon' => 'color: {{VALUE}};',
					'{{WRAPPER}} .marrison-step-icon svg' => 'fill: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'marker_background',
			array(
				'label'     => esc_html__( 'Sfondo numero/icona', 'marrison-steps' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => array(
					'{{WRAPPER}} .marrison-step-number, {{WRAPPER}} .marrison-step-icon' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'marker_typography',
				'selector' => '{{WRAPPER}} .marrison-step-number, {{WRAPPER}} .marrison-step-icon',
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'marker_border',
				'selector' => '{{WRAPPER}} .marrison-step-marker',
			)
		);

		$this->add_responsive_control(
			'marker_border_radius',
			array(
				'label'      => esc_html__( 'Raggio bordo', 'marrison-steps' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'default'    => array(
					'top'      => 50,
					'right'    => 50,
					'bottom'   => 50,
					'left'     => 50,
					'unit'     => '%',
					'isLinked' => true,
				),
				'selectors'  => array(
					'{{WRAPPER}} .marrison-step-marker' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .marrison-step-image img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'marker_spacing',
			array(
				'label'      => esc_html__( 'Distanza dal titolo', 'marrison-steps' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 80,
					),
				),
				'default'    => array(
					'size' => 16,
					'unit' => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .marrison-step-marker' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_text_style',
			array(
				'label' => esc_html__( 'Titolo e testo', 'marrison-steps' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'title_color',
			array(
				'label'     => esc_html__( 'Colore titolo', 'marrison-steps' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => array(
					'{{WRAPPER}} .marrison-step-title' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'title_typography',
				'selector' => '{{WRAPPER}} .marrison-step-title',
			)
		);

		$this->add_responsive_control(
			'title_spacing',
			array(
				'label'      => esc_html__( 'Spazio sotto titolo', 'marrison-steps' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 60,
					),
				),
				'default'    => array(
					'size' => 8,
					'unit' => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .marrison-step-title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'text_color',
			array(
				'label'     => esc_html__( 'Colore testo', 'marrison-steps' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#4b5563',
				'selectors' => array(
					'{{WRAPPER}} .marrison-step-text' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'text_typography',
				'selector' => '{{WRAPPER}} .marrison-step-text',
			)
		);

		$this->end_controls_section();
	}

	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$steps    = isset( $settings['steps'] ) && is_array( $settings['steps'] ) ? array_slice( $settings['steps'], 0, 8 ) : array();

		if ( count( $steps ) < 2 ) {
			return;
		}

		$connector_type = isset( $settings['connector_type'] ) ? $settings['connector_type'] : 'arrow';
		$directions     = $this->get_layout_directions( $settings );
		$classes        = array(
			'marrison-steps',
			'marrison-steps-connector-' . sanitize_html_class( $connector_type ),
			'marrison-steps-layout-' . sanitize_html_class( $directions['desktop'] ),
			'marrison-steps-layout-tablet-' . sanitize_html_class( $directions['tablet'] ),
			'marrison-steps-layout-mobile-' . sanitize_html_class( $directions['mobile'] ),
		);
		$grid_columns   = $this->get_grid_columns_style( count( $steps ), $connector_type );
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
			<div class="marrison-steps-list" style="<?php echo esc_attr( $grid_columns ); ?>">
				<?php foreach ( $steps as $index => $step ) : ?>
					<div class="marrison-step">
						<div class="marrison-step-card">
							<?php $this->render_marker( $step, $index ); ?>

							<?php if ( ! empty( $step['title'] ) ) : ?>
								<h3 class="marrison-step-title"><?php echo esc_html( $step['title'] ); ?></h3>
							<?php endif; ?>

							<?php if ( ! empty( $step['text'] ) ) : ?>
								<div class="marrison-step-text"><?php echo wp_kses_post( nl2br( $step['text'] ) ); ?></div>
							<?php endif; ?>
						</div>
					</div>

					<?php if ( $index < count( $steps ) - 1 && 'none' !== $connector_type ) : ?>
						<div class="marrison-step-connector" aria-hidden="true">
							<span class="marrison-step-connector-line"></span>
							<?php if ( 'line' !== $connector_type ) : ?>
								<?php $this->render_connector_head( $connector_type ); ?>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	private function get_layout_directions( array $settings ): array {
		$desktop = $this->normalize_layout_direction( $settings['layout_direction'] ?? 'horizontal' );
		$tablet  = $this->normalize_layout_direction( $settings['layout_direction_tablet'] ?? $desktop );
		$mobile  = $this->normalize_layout_direction( $settings['layout_direction_mobile'] ?? $tablet );

		return array(
			'desktop' => $desktop,
			'tablet'  => $tablet,
			'mobile'  => $mobile,
		);
	}

	private function normalize_layout_direction( string $direction ): string {
		return 'vertical' === $direction ? 'vertical' : 'horizontal';
	}

	private function get_grid_columns_style( int $steps_count, string $connector_type ): string {
		$columns = array();

		for ( $index = 0; $index < $steps_count; $index++ ) {
			$columns[] = 'minmax(0, 1fr)';

			if ( $index < $steps_count - 1 && 'none' !== $connector_type ) {
				$columns[] = 'var(--marrison-steps-connector-horizontal-length)';
			}
		}

		return 'grid-template-columns: ' . implode( ' ', $columns ) . ';';
	}

	private function render_connector_head( string $connector_type ): void {
		if ( 'double-arrow' === $connector_type ) {
			?>
			<span class="marrison-step-connector-head marrison-step-connector-head-arrow marrison-step-connector-head-double-one"></span>
			<span class="marrison-step-connector-head marrison-step-connector-head-arrow marrison-step-connector-head-double-two"></span>
			<?php
			return;
		}

		$head_class = 'triangle' === $connector_type ? 'triangle' : ( 'chevron' === $connector_type ? 'chevron' : 'arrow' );
		?>
		<span class="marrison-step-connector-head marrison-step-connector-head-<?php echo esc_attr( $head_class ); ?>"></span>
		<?php
	}

	private function render_marker( array $step, int $index ): void {
		$media_type = isset( $step['media_type'] ) ? $step['media_type'] : 'number';

		if ( 'image' === $media_type && ! empty( $step['image']['url'] ) ) {
			$image_url = esc_url( $step['image']['url'] );
			$image_alt = ! empty( $step['title'] ) ? $step['title'] : sprintf(
				/* translators: %d: step number. */
				esc_html__( 'Step %d', 'marrison-steps' ),
				$index + 1
			);
			?>
			<div class="marrison-step-marker marrison-step-image">
				<img src="<?php echo $image_url; ?>" alt="<?php echo esc_attr( $image_alt ); ?>">
			</div>
			<?php
			return;
		}

		if ( 'icon' === $media_type && ! empty( $step['icon']['value'] ) ) {
			?>
			<div class="marrison-step-marker marrison-step-icon">
				<?php Icons_Manager::render_icon( $step['icon'], array( 'aria-hidden' => 'true' ) ); ?>
			</div>
			<?php
			return;
		}

		$number = isset( $step['number'] ) && '' !== $step['number'] ? $step['number'] : (string) ( $index + 1 );
		?>
		<div class="marrison-step-marker marrison-step-number">
			<?php echo esc_html( $number ); ?>
		</div>
		<?php
	}
}
