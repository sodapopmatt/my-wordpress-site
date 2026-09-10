<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers a Gutenberg block wrapping [nnr_events], with the same options
 * exposed as an Inspector panel instead of shortcode attributes.
 */
class NNR_Block {

	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
	}

	public function register() {
		wp_register_style(
			'nnr-events',
			NNR_EVENTS_URL . 'assets/css/nnr-events.css',
			array(),
			NNR_EVENTS_VERSION
		);

		wp_register_script(
			'nnr-events-block',
			NNR_EVENTS_URL . 'assets/js/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			NNR_EVENTS_VERSION,
			true
		);

		wp_register_script(
			'nnr-events-frontend',
			NNR_EVENTS_URL . 'assets/js/frontend.js',
			array(),
			NNR_EVENTS_VERSION,
			true
		);
		wp_localize_script(
			'nnr-events-frontend',
			'NNREventsFrontend',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'loadingText'      => __( 'Loading…', 'nnr-events' ),
				'closeLightboxText' => __( 'Close', 'nnr-events' ),
			)
		);

		$categories = get_terms(
			array(
				'taxonomy'   => NNR_Taxonomy::TAXONOMY,
				'hide_empty' => false,
			)
		);
		if ( ! is_array( $categories ) ) {
			$categories = array();
		}

		$category_options = array(
			array(
				'label' => __( 'All categories', 'nnr-events' ),
				'value' => '',
			),
		);
		foreach ( $categories as $term ) {
			$category_options[] = array(
				'label' => $term->name,
				'value' => $term->slug,
			);
		}

		wp_localize_script(
			'nnr-events-block',
			'NNREventsBlock',
			array(
				'categories' => $category_options,
			)
		);

		register_block_type(
			'nnr-events/event-list',
			array(
				'editor_script'   => 'nnr-events-block',
				'style'           => 'nnr-events',
				'view_script'     => 'nnr-events-frontend',
				'render_callback' => array( $this, 'render' ),
				'attributes'      => array(
					'category' => array(
						'type'    => 'string',
						'default' => '',
					),
					'limit'    => array(
						'type'    => 'number',
						'default' => 5,
					),
					'columns'  => array(
						'type'    => 'number',
						'default' => 3,
					),
					'layout'   => array(
						'type'    => 'string',
						'default' => 'grid',
					),
					'image'    => array(
						'type'    => 'string',
						'default' => 'show',
					),
					'color'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'filter'   => array(
						'type'    => 'string',
						'default' => 'none',
					),
				),
			)
		);
	}

	public function render( $attributes ) {
		$shortcode = new NNR_Shortcode();
		return $shortcode->render( $attributes );
	}
}
