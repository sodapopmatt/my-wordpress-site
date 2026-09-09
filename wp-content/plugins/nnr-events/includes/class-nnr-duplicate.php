<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a "Duplicate" row action to the Events admin list. Copies everything
 * (venue, address, price, ticket URL, button text, categories, featured
 * image) except the dates/times, since the whole point is reusing a venue
 * for a new date without retyping it.
 */
class NNR_Duplicate {

	const ACTION = 'nnr_duplicate_event';

	/**
	 * Meta keys intentionally left blank on the clone so the user has to
	 * fill in a new date before republishing.
	 */
	const SKIP_META = array(
		'_nnr_start_date',
		'_nnr_start_time',
		'_nnr_end_date',
		'_nnr_end_time',
	);

	public function __construct() {
		add_filter( 'post_row_actions', array( $this, 'add_row_action' ), 10, 2 );
		add_action( 'admin_action_' . self::ACTION, array( $this, 'handle_duplicate' ) );
	}

	public function add_row_action( $actions, $post ) {
		if ( 'event' !== $post->post_type || ! current_user_can( 'edit_posts' ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::ACTION,
					'post'   => $post->ID,
				),
				admin_url( 'admin.php' )
			),
			self::ACTION . '_' . $post->ID
		);

		$actions['nnr_duplicate'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Duplicate', 'nnr-events' ) . '</a>';

		return $actions;
	}

	public function handle_duplicate() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		if ( ! $post_id || ! check_admin_referer( self::ACTION . '_' . $post_id ) ) {
			wp_die( esc_html__( 'Invalid request.', 'nnr-events' ) );
		}

		$original = get_post( $post_id );

		if ( ! $original || 'event' !== $original->post_type || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to duplicate this event.', 'nnr-events' ) );
		}

		$new_id = wp_insert_post(
			array(
				'post_title'   => $original->post_title,
				'post_content' => $original->post_content,
				'post_excerpt' => $original->post_excerpt,
				'post_type'    => 'event',
				'post_status'  => 'draft',
				'post_author'  => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $new_id ) ) {
			wp_die( esc_html( $new_id->get_error_message() ) );
		}

		foreach ( get_post_meta( $post_id ) as $key => $values ) {
			if ( in_array( $key, self::SKIP_META, true ) ) {
				continue;
			}
			update_post_meta( $new_id, $key, maybe_unserialize( $values[0] ) );
		}

		$terms = wp_get_object_terms( $post_id, NNR_Taxonomy::TAXONOMY, array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $terms ) ) {
			wp_set_object_terms( $new_id, $terms, NNR_Taxonomy::TAXONOMY );
		}

		$thumbnail_id = get_post_thumbnail_id( $post_id );
		if ( $thumbnail_id ) {
			set_post_thumbnail( $new_id, $thumbnail_id );
		}

		wp_safe_redirect( get_edit_post_link( $new_id, 'raw' ) );
		exit;
	}
}
