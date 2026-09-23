<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps _nnr_sort_date current for recurring weekly events, whose correct
 * value (the next occurrence on/after today) changes daily even without
 * anyone editing the event. NNR_Meta_Box::save() already refreshes it the
 * moment an event is saved; this covers the rest of the time.
 */
class NNR_Cron {

	const CRON_HOOK          = 'nnr_events_refresh_recurring_dates';
	const MIGRATION_OPTION   = 'nnr_events_sort_date_backfilled_v1';
	const LAST_REFRESH_OPTION = 'nnr_events_recurring_last_refresh_date';

	public function __construct() {
		add_action( 'init', array( $this, 'schedule' ) );
		add_action( 'init', array( $this, 'maybe_run_backfill' ) );
		add_action( 'init', array( $this, 'maybe_refresh_recurring_dates' ) );
		add_action( self::CRON_HOOK, array( $this, 'refresh_recurring_dates' ) );
	}

	public function schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Real WP-Cron only runs via a background HTTP loopback request that a
	 * page load triggers — many hosts block or throttle that (security
	 * plugins, firewalls), so schedule() alone isn't reliable in practice.
	 * This guarantees the same refresh happens on the first page load of
	 * each new day regardless, using a cheap date-stamp option instead of
	 * depending on that request actually succeeding.
	 */
	public function maybe_refresh_recurring_dates() {
		$today = current_time( 'Y-m-d' );
		if ( get_option( self::LAST_REFRESH_OPTION ) === $today ) {
			return;
		}

		$this->refresh_recurring_dates();
		update_option( self::LAST_REFRESH_OPTION, $today );
	}

	/**
	 * Populates _nnr_sort_date for every existing event the first time
	 * this version of the plugin runs, so upgrading doesn't leave events
	 * without it until they're next edited or the daily cron ticks —
	 * guarded by an option rather than register_activation_hook, since an
	 * already-active plugin being updated via deploy never re-fires that.
	 */
	public function maybe_run_backfill() {
		if ( get_option( self::MIGRATION_OPTION ) ) {
			return;
		}

		$this->run_backfill();
		update_option( self::MIGRATION_OPTION, 1 );
	}

	public function run_backfill() {
		$query = new WP_Query(
			array(
				'post_type'      => 'event',
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private', 'trash' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		foreach ( $query->posts as $post_id ) {
			update_post_meta( $post_id, '_nnr_sort_date', NNR_Query::compute_sort_date( $post_id ) );
		}
	}

	public function refresh_recurring_dates() {
		$query = new WP_Query(
			array(
				'post_type'      => 'event',
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'   => '_nnr_recurring_weekly',
						'value' => '1',
					),
				),
			)
		);

		foreach ( $query->posts as $post_id ) {
			update_post_meta( $post_id, '_nnr_sort_date', NNR_Query::compute_sort_date( $post_id ) );
		}
	}
}
