<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NNR_Query {

	/**
	 * Meta query matching "not expired" events: anything recurring weekly,
	 * or anything that hasn't ended yet. A multi-day event stays "upcoming"
	 * through its end date, not just its start date. Shared by the frontend
	 * "upcoming" listing and the admin list's "Active" view so both agree
	 * on exactly what counts as active.
	 */
	public static function upcoming_meta_query() {
		$today = current_time( 'Y-m-d' );

		return array(
			'relation' => 'OR',
			array(
				'key'   => '_nnr_recurring_weekly',
				'value' => '1',
			),
			array(
				'key'     => '_nnr_end_date',
				'value'   => $today,
				'compare' => '>=',
				'type'    => 'DATE',
			),
			array(
				'relation' => 'AND',
				array(
					'relation' => 'OR',
					array(
						'key'     => '_nnr_end_date',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_nnr_end_date',
						'value'   => '',
						'compare' => '=',
					),
				),
				array(
					'key'     => '_nnr_start_date',
					'value'   => $today,
					'compare' => '>=',
					'type'    => 'DATE',
				),
			),
		);
	}

	/**
	 * A meta_query that matches every post regardless of whether
	 * _nnr_start_date is set (an EXISTS/NOT EXISTS pair under an OR is
	 * tautologically true) while still giving 'orderby' a named clause to
	 * sort by. Plain top-level meta_key + orderby=meta_value instead
	 * silently excludes any post without that meta row — fine for
	 * Active/Expired, where "upcoming"/"expired" already implies a date
	 * exists, but wrong for Draft/Trash/All, where a dateless event (very
	 * plausible before it's ever been given a date) is completely normal
	 * and should still show up in the list.
	 */
	public static function orderable_start_date_meta_query() {
		return array(
			'relation'              => 'OR',
			'nnr_start_date_clause' => array(
				'key'     => '_nnr_start_date',
				'compare' => 'EXISTS',
			),
			array(
				'key'     => '_nnr_start_date',
				'compare' => 'NOT EXISTS',
			),
		);
	}

	/**
	 * Build WP_Query args for "upcoming" events, ordered chronologically.
	 *
	 * @param array $overrides Extra/overriding WP_Query args (e.g. tax_query, posts_per_page).
	 * @return array
	 */
	public static function get_upcoming_args( $overrides = array() ) {
		$args = array(
			'post_type'      => 'event',
			'post_status'    => 'publish',
			'posts_per_page' => 5,
			'meta_key'       => '_nnr_start_date',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'meta_query'     => self::upcoming_meta_query(),
		);

		return array_merge( $args, $overrides );
	}
}
