<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Widget-local Posts Query composition. No Query Builder singleton is modified. */
class Marrison_Addon_Query_Fallback {
	private $running = false;

	public function __construct() {
		add_action( 'elementor/element/jet-listing-grid/section_custom_query/before_section_end', [ $this, 'register_controls' ] );
		add_filter( 'jet-engine/listing/grid/query/query', [ $this, 'filter_items' ], 20, 3 );
		add_action( 'elementor/editor/after_enqueue_scripts', [ $this, 'enqueue_editor' ] );
	}

	private function manager() {
		$class = '\\Jet_Engine\\Query_Builder\\Manager';
		return class_exists( $class ) ? $class::instance() : null;
	}

	public function register_controls( $widget ) {
		$manager = $this->manager();
		if ( ! $manager ) {
			return;
		}
		$condition = [ 'custom_query' => 'yes', 'marrison_query_fallback_enable' => 'yes' ];
		$widget->add_control( 'marrison_query_fallback_enable', [
			'label' => __( 'Enable Query Fallback', 'marrison-addon' ),
			'type' => \Elementor\Controls_Manager::SWITCHER,
			'default' => '', 'return_value' => 'yes',
			'condition' => [ 'custom_query' => 'yes' ],
		] );
		// Same live registry as JetEngine's Custom Query control, restricted to Posts.
		$options = $manager->get_queries_for_options( false, 'posts' );
		$options[''] = __( 'Seleziona una query', 'marrison-addon' );
		$widget->add_control( 'marrison_query_fallback_id', [
			'label' => __( 'Fallback Query', 'marrison-addon' ),
			'type' => \Elementor\Controls_Manager::SELECT2,
			'options' => $options, 'default' => '', 'label_block' => true,
			'condition' => $condition,
		] );
		$widget->add_control( 'marrison_query_fallback_mode', [
			'label' => __( 'Fallback Mode', 'marrison-addon' ),
			'type' => \Elementor\Controls_Manager::CHOOSE,
			'label_block' => true,
			'toggle' => false,
			'options' => [
				'empty' => [ 'title' => __( 'Only when empty', 'marrison-addon' ), 'icon' => 'eicon-ban' ],
				'fill' => [ 'title' => __( 'Fill missing items', 'marrison-addon' ), 'icon' => 'eicon-plus' ],
			],
			'default' => 'empty', 'condition' => $condition,
		] );
		$widget->add_control( 'marrison_query_fallback_notice', [
			'type' => \Elementor\Controls_Manager::RAW_HTML,
			'raw' => __( 'Solo Posts Query, senza paginazione, Load More o Lazy Load. Il limite dipende dalla query principale. Il fallback è sospeso quando vengono applicati filtri.', 'marrison-addon' ),
			'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			'condition' => $condition,
		] );
		$widget->add_control( 'marrison_query_fallback_warning', [
			'type' => \Elementor\Controls_Manager::RAW_HTML, 'raw' => '',
			'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
			'condition' => $condition,
		] );
	}

	public function enqueue_editor() {
		$manager = $this->manager();
		if ( ! $manager ) {
			return;
		}
		$path = 'assets/js/marrison-query-fallback-editor.js';
		wp_enqueue_script( 'marrison-query-fallback-editor', plugins_url( $path, Marrison_Addon::plugin_file() ), [ 'elementor-editor' ], Marrison_Addon::asset_version( $path ), true );
		$style = 'assets/css/marrison-query-fallback-editor.css';
		wp_enqueue_style( 'marrison-query-fallback-editor', plugins_url( $style, Marrison_Addon::plugin_file() ), [], Marrison_Addon::asset_version( $style ) );
		$options = $manager->get_queries_for_options( false, 'posts' );
		$options[''] = __( 'Seleziona una query', 'marrison-addon' );
		wp_localize_script( 'marrison-query-fallback-editor', 'marrisonQueryFallback', [
			'queries' => $options,
			'unsupportedSettings' => [
				'use_load_more' => __( 'Fallback sospeso: Load More è attivo.', 'marrison-addon' ),
				'lazy_load' => __( 'Fallback sospeso: Lazy Load è attivo.', 'marrison-addon' ),
				'is_archive_template' => __( 'Fallback sospeso: il listing è impostato come archivio.', 'marrison-addon' ),
				'use_random_posts_num' => __( 'Fallback sospeso: il numero casuale di elementi è attivo.', 'marrison-addon' ),
			],
			'compatible' => method_exists( $manager, 'get_query_by_id_for_context' ),
			'apiMissing' => __( 'Fallback sospeso: questa versione di JetEngine non espone la verifica del contesto delle query richiesta dal modulo.', 'marrison-addon' ),
			'invalid' => __( 'Fallback sospeso: scegli due Posts Query diverse e ancora disponibili.', 'marrison-addon' ),
			'unsupported' => __( 'Fallback sospeso: disattiva Load More, Lazy Load, uso come archivio e numero casuale di elementi.', 'marrison-addon' ),
		] );
	}

	private function enabled( $value ) {
		return is_scalar( $value ) && filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	private function query_id( $value ) {
		return is_scalar( $value ) && ctype_digit( (string) $value ) ? absint( $value ) : 0;
	}

	private function is_filtered_request( $manager, $query ) {
		$filters = $manager->listings->filters ?? null;
		if ( is_object( $filters ) && method_exists( $filters, 'is_filters_request' ) ) {
			return (bool) $filters->is_filters_request( $query );
		}
		// Older integrations: fail closed only on a request carrying filter state.
		return function_exists( 'jet_smart_filters' ) && ( ! empty( $_REQUEST['jsf'] ) || ! empty( $_REQUEST['jet-smart-filters'] ) || ( isset( $_REQUEST['action'] ) && 'jet_smart_filters' === $_REQUEST['action'] ) );
	}

	private function supported_request( $settings, $widget ) {
		foreach ( [ 'use_load_more', 'lazy_load', 'is_archive_template', 'use_random_posts_num' ] as $key ) {
			if ( $this->enabled( $settings[ $key ] ?? false ) ) {
				return false;
			}
		}
		if ( (int) ( $settings['current_page'] ?? 1 ) > 1 || (int) ( $widget->query_vars['page'] ?? 1 ) > 1 ) {
			return false;
		}
		// Public AJAX settings are client supplied. Do not execute an arbitrary fallback ID.
		if ( ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) ) {
			return isset( \Elementor\Plugin::$instance->editor ) && method_exists( \Elementor\Plugin::$instance->editor, 'is_edit_mode' ) && \Elementor\Plugin::$instance->editor->is_edit_mode();
		}
		return true;
	}

	/** Hook runs after JetEngine resolves the primary query and its original context. */
	public function filter_items( $items, $settings, $widget ) {
		if ( $this->running || ! is_array( $items ) || ! is_array( $settings ) || ! is_object( $widget )
			|| ! $this->enabled( $settings['custom_query'] ?? false )
			|| ! $this->enabled( $settings['marrison_query_fallback_enable'] ?? false )
			|| ! $this->supported_request( $settings, $widget ) ) {
			return $items;
		}
		$main_id = $this->query_id( $settings['custom_query_id'] ?? 0 );
		$fallback_id = $this->query_id( $settings['marrison_query_fallback_id'] ?? 0 );
		$mode = $settings['marrison_query_fallback_mode'] ?? 'empty';
		if ( ! $main_id || ! $fallback_id || $main_id === $fallback_id || ! in_array( $mode, [ 'empty', 'fill' ], true ) ) {
			return $items;
		}
		$manager = $this->manager();
		// Require the contextual authorization API; fail closed on older JetEngine.
		if ( ! $manager || ! method_exists( $manager, 'get_query_by_id_for_context' )
			|| (int) ( $widget->listing_query_id ?? 0 ) !== $main_id ) {
			return $items;
		}
		$primary = $manager->get_query_by_id( $main_id );
		if ( ! $primary instanceof \Jet_Engine\Query_Builder\Queries\Posts_Query ) {
			return $items;
		}
		if ( $this->is_filtered_request( $manager, $primary ) ) {
			return $items;
		}
		$limit = (int) $primary->get_items_per_page();
		if ( $limit < 1 || count( $items ) >= $limit || ( 'empty' === $mode && $items ) ) {
			return $items;
		}
		$data = jet_engine()->listings->data;
		$listing = method_exists( $data, 'get_listing' ) ? $data->get_listing() : null;
		$listing_id = $listing && method_exists( $listing, 'get_main_id' ) ? $listing->get_main_id() : ( $settings['lisitng_id'] ?? 0 );
		$is_listing_ajax = method_exists( jet_engine()->listings, 'is_listing_ajax' ) && jet_engine()->listings->is_listing_ajax();
		$context = [ 'type' => $is_listing_ajax ? 'listing_ajax' : 'listing', 'query_id' => $fallback_id, 'listing_id' => $listing_id, 'settings' => $settings ];
		$template = $manager->get_query_by_id_for_context( $fallback_id, $context );
		if ( ! $template instanceof \Jet_Engine\Query_Builder\Queries\Posts_Query || $this->is_filtered_request( $manager, $template ) ) {
			return $items;
		}
		$this->running = true;
		try {
			$query = clone $template;
			$query->final_query = null;
			$query->final_query_raw = null;
			$query->parsed_macros = [];
			$query->reset_query();
			// Avoid sharing the singleton's cached result/count with another grid/context.
			$query->cache_query = false;
			$query->setup_query();
			$main_args = $primary->get_query_args();
			$excluded = $this->ids( $main_args['post__not_in'] ?? [] );
			$excluded = array_unique( array_merge( $excluded, $this->ids( $query->final_query['post__not_in'] ?? [] ) ) );
			$query->final_query['post__not_in'] = $excluded;
			// WP_Query ignores post__not_in when post__in exists: enforce both explicitly.
			if ( ! empty( $query->final_query['post__in'] ) ) {
				$included = array_values( array_diff( $this->ids( $query->final_query['post__in'] ), $excluded ) );
				if ( ! $included ) {
					return $items;
				}
				$query->final_query['post__in'] = $included;
			}
			$result = [];
			$seen = array_fill_keys( $excluded, true );
			foreach ( $items as $item ) {
				if ( ! $item instanceof \WP_Post ) {
					return $items;
				}
				if ( ! isset( $seen[ $item->ID ] ) ) {
					$result[] = $item;
					$seen[ $item->ID ] = true;
				}
			}
			$offset = max( 0, (int) ( $query->final_query['offset'] ?? 0 ) );
			$batch_size = min( 200, max( 20, $limit ) );
			// Progress through original ordered results, including overlaps, without losing offset semantics.
			for ( $batch = 0; $batch < 50 && count( $result ) < $limit; $batch++ ) {
				$query->final_query['posts_per_page'] = $batch_size;
				$query->final_query['offset'] = $offset;
				$query->final_query['paged'] = 1;
				$query->final_query['page'] = 1;
				$query->reset_query();
				$candidates = $query->get_items();
				if ( ! is_array( $candidates ) || ! $candidates ) {
					break;
				}
				foreach ( $candidates as $item ) {
					if ( $item instanceof \WP_Post && ! isset( $seen[ $item->ID ] ) ) {
						$result[] = $item;
						$seen[ $item->ID ] = true;
						if ( count( $result ) >= $limit ) {
							break;
						}
					}
				}
				$offset += $batch_size;
				if ( count( $candidates ) < $batch_size ) {
					break;
				}
			}
			// Local render state only: JetEngine retains its native WP_Post rendering/reset logic.
			if ( $result !== $items && isset( $widget->posts_query ) && $widget->posts_query instanceof \WP_Query ) {
				$widget->posts_query = clone $widget->posts_query;
				$widget->posts_query->posts = $result;
				$widget->posts_query->post_count = count( $result );
				$widget->posts_query->found_posts = count( $result );
				$widget->posts_query->max_num_pages = 1;
				$widget->query_vars['pages'] = 1;
			}
			return $result;
		} finally {
			$this->running = false;
		}
	}

	private function ids( $value ) {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\s,]+/', $value );
		}
		return is_array( $value ) ? array_values( array_filter( array_map( 'absint', $value ) ) ) : [];
	}
}
