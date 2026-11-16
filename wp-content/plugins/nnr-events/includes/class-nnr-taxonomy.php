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
		add_action( 'init', array( $this, 'maybe_merge_duplicate_events_term' ), 25 );
	}

	/**
	 * An earlier version registered a second "Events" term (slug "events") as
	 * the default, next to the original "Events" term (slug "pasadena-events").
	 * WordPress won't delete the default term, so that duplicate was stuck.
	 * Make the original the default, move any events across, drop the duplicate.
	 */
	public function maybe_merge_duplicate_events_term() {
		if ( get_option( 'nnr_events_merged_events_term_v1' ) ) {
			return;
		}

		$keep = get_term_by( 'slug', 'pasadena-events', self::TAXONOMY );
		$dupe = get_term_by( 'slug', 'events', self::TAXONOMY );
		if ( ! $keep ) {
			// Nothing to merge into; leave things alone and don't retry forever.
			update_option( 'nnr_events_merged_events_term_v1', 1 );
			return;
		}

		update_option( 'default_term_' . self::TAXONOMY, (int) $keep->term_id );

		if ( $dupe ) {
			$post_ids = get_objects_in_term( $dupe->term_id, self::TAXONOMY );
			if ( ! is_wp_error( $post_ids ) ) {
				foreach ( $post_ids as $post_id ) {
					wp_set_object_terms( (int) $post_id, (int) $keep->term_id, self::TAXONOMY, true );
				}
			}
			wp_delete_term( $dupe->term_id, self::TAXONOMY );
		}

		update_option( 'nnr_events_merged_events_term_v1', 1 );
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
					'slug' => 'pasadena-events',
				),
			)
		);
	}

	/**
	 * Seed the default event categories. Safe to call multiple times.
	 */
	public function seed_terms() {
		$terms = array(
			'pasadena-events' => __( 'Events', 'nnr-events' ),
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
