<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NNR_Query {

	/**
	 * Build WP_Query args for "upcoming" events: anything recurring weekly,
	 * or anything whose start date hasn't passed yet. Ordered chronologically.
	 *
	 * @param array $overrides Extra/overriding WP_Query args (e.g. tax_query, posts_per_page).
	 * @return array
	 */
	public static function get_upcoming_args( $overrides = array() ) {
		$today = current_time( 'Y-m-d' );

		$args = array(
			'post_type'      => 'event',
			'post_status'    => 'publish',
			'posts_per_page' => 5,
			'meta_key'       => '_nnr_start_date',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'   => '_nnr_recurring_weekly',
					'value' => '1',
				),
				array(
					'key'     => '_nnr_start_date',
					'value'   => $today,
					'compare' => '>=',
					'type'    => 'DATE',
				),
			),
		);

		return array_merge( $args, $overrides );
	}
}
