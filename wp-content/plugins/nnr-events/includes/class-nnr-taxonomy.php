<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NNR_Taxonomy {

	const TAXONOMY = 'event_category';

	/** Bump when seed_terms() gains new terms, so already-active sites get them. */
	const SEED_OPTION = 'nnr_events_terms_seeded_v2';

	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'init', array( $this, 'maybe_seed_terms' ), 20 );
	}

	/**
	 * Activation only fires on a fresh activate, not when a deploy updates
	 * an already-active plugin, so new default terms are seeded once here.
	 */
	public function maybe_seed_terms() {
		if ( get_option( self::SEED_OPTION ) ) {
			return;
		}
		$this->seed_terms();
		update_option( self::SEED_OPTION, 1 );
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
				// The admin list's category filter dropdown is added
				// explicitly by this plugin (see NNR_Admin_List) and reads
				// $_GET directly, so it doesn't need this. Leaving it off
				// avoids a second, redundant dropdown that some WP
				// core/theme code adds automatically once a taxonomy's
				// query_var is truthy.
				'query_var'          => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_admin_column'  => true,
				'show_in_rest'       => true,
				'default_term'       => array(
					'name' => __( 'Events', 'nnr-events' ),
					'slug' => 'events',
				),
			)
		);
	}

	/**
	 * Seed the default event categories. Safe to call multiple times.
	 */
	public function seed_terms() {
		$terms = array(
			'events'           => __( 'Events', 'nnr-events' ),
			'pasadena-events' => __( 'Pasadena Events', 'nnr-events' ),
			'live-music'      => __( 'Live Music', 'nnr-events' ),
			'trivia'          => __( 'Trivia', 'nnr-events' ),
			'plays-theater'   => __( 'Plays & Theater', 'nnr-events' ),
		);

		foreach ( $terms as $slug => $name ) {
			if ( ! term_exists( $slug, self::TAXONOMY ) ) {
				wp_insert_term( $name, self::TAXONOMY, array( 'slug' => $slug ) );
			}
		}
	}
}
