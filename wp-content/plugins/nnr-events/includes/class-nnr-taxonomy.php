<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NNR_Taxonomy {

	const TAXONOMY = 'event_category';

	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
	}

	public function register() {
		$labels = array(
			'name'          => __( 'Event Categories', 'nnr-events' ),
			'singular_name' => __( 'Event Category', 'nnr-events' ),
			'search_items'  => __( 'Search Event Categories', 'nnr-events' ),
			'all_items'     => __( 'All Event Categories', 'nnr-events' ),
			'edit_item'     => __( 'Edit Event Category', 'nnr-events' ),
			'update_item'   => __( 'Update Event Category', 'nnr-events' ),
			'add_new_item'  => __( 'Add New Event Category', 'nnr-events' ),
			'new_item_name' => __( 'New Event Category Name', 'nnr-events' ),
			'menu_name'     => __( 'Categories', 'nnr-events' ),
		);

		register_taxonomy(
			self::TAXONOMY,
			'event',
			array(
				'labels'             => $labels,
				'hierarchical'       => false,
				'public'             => false,
				'publicly_queryable' => false,
				'rewrite'            => false,
				'query_var'          => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_admin_column'  => true,
				'show_in_rest'       => true,
			)
		);
	}

	/**
	 * Seed the three default event categories. Safe to call multiple times.
	 */
	public function seed_terms() {
		$terms = array(
			'pasadena-events' => __( 'Pasadena Events', 'nnr-events' ),
			'live-music'      => __( 'Live Music', 'nnr-events' ),
			'trivia'          => __( 'Trivia', 'nnr-events' ),
		);

		foreach ( $terms as $slug => $name ) {
			if ( ! term_exists( $slug, self::TAXONOMY ) ) {
				wp_insert_term( $name, self::TAXONOMY, array( 'slug' => $slug ) );
			}
		}
	}
}
