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
	 * _nnr_sort_date is set (an EXISTS/NOT EXISTS pair under an OR is
	 * tautologically true) while still giving 'orderby' a named clause to
	 * sort by. Plain top-level meta_key + orderby=meta_value instead
	 * silently excludes any post without that meta row — fine for
	 * Active/Expired, where "upcoming"/"expired" already implies a date
	 * exists, but wrong for Draft/Trash/All, where a dateless event (very
	 * plausible before it's ever been given a date) is completely normal
	 * and should still show up in the list.
	 */
	public static function orderable_sort_date_meta_query() {
		return array(
			'relation'             => 'OR',
			'nnr_sort_date_clause' => array(
				'key'     => '_nnr_sort_date',
				'compare' => 'EXISTS',
			),
			array(
				'key'     => '_nnr_sort_date',
				'compare' => 'NOT EXISTS',
			),
		);
	}

	/**
	 * Given any date that falls on the target weekday, returns the next
	 * date (today included) that falls on that same weekday. Used to
	 * project a recurring weekly event's fixed start_date anchor (which
	 * only exists to record which weekday it repeats on — see
	 * NNR_Shortcode::format_date_label()) forward to its next actual
	 * occurrence, since that anchor itself never changes.
	 */
	public static function get_next_weekly_occurrence( $anchor_date ) {
		$ts = strtotime( $anchor_date );
		if ( ! $ts ) {
			return $anchor_date;
		}

		$today_ts       = strtotime( current_time( 'Y-m-d' ) );
		$anchor_weekday = (int) gmdate( 'N', $ts );
		$today_weekday  = (int) gmdate( 'N', $today_ts );

		$days_ahead = $anchor_weekday - $today_weekday;
		if ( $days_ahead < 0 ) {
			$days_ahead += 7;
		}

		return gmdate( 'Y-m-d', $today_ts + ( $days_ahead * DAY_IN_SECONDS ) );
	}

	/**
	 * The canonical value to store in _nnr_sort_date for a given post: the
	 * raw start date for a one-time event, or the next upcoming occurrence
	 * for a recurring weekly one. This is the only thing queries should
	 * sort/group by — _nnr_start_date itself stays frozen at whatever
	 * anchor date it was first given.
	 */
	public static function compute_sort_date( $post_id ) {
		$start_date = get_post_meta( $post_id, '_nnr_start_date', true );
		if ( '' === $start_date ) {
			return '';
		}

		$recurring = '1' === get_post_meta( $post_id, '_nnr_recurring_weekly', true );
		if ( ! $recurring ) {
			return $start_date;
		}

		return self::get_next_weekly_occurrence( $start_date );
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
			'meta_key'       => '_nnr_sort_date',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'meta_query'     => self::upcoming_meta_query(),
		);

		return array_merge( $args, $overrides );
	}
}
