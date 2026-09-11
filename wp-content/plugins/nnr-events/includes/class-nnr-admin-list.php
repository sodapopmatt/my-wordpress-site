<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a dynamic "Expired" view to the Events admin list, plus a status
 * column, without ever changing the underlying post data.
 */
class NNR_Admin_List {

	const FILTER_KEY = 'nnr_event_status';

	const QUICK_EDIT_FIELDS = array( '_nnr_start_date', '_nnr_start_time', '_nnr_end_date', '_nnr_end_time', '_nnr_venue', '_nnr_price' );

	public function __construct() {
		add_filter( 'views_edit-event', array( $this, 'add_expired_view' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_expired_query' ) );
		add_action( 'pre_get_posts', array( $this, 'sort_by_start_date' ) );
		add_filter( 'manage_event_posts_columns', array( $this, 'add_status_column' ) );
		add_filter( 'manage_edit-event_sortable_columns', array( $this, 'add_sortable_columns' ) );
		add_action( 'manage_event_posts_custom_column', array( $this, 'render_status_column' ), 10, 2 );
		add_action( 'quick_edit_custom_box', array( $this, 'render_quick_edit_fields' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_quick_edit_script' ) );
	}

	public function enqueue_quick_edit_script( $hook ) {
		if ( 'edit.php' !== $hook || 'event' !== ( $_GET['post_type'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		wp_enqueue_script(
			'nnr-events-quick-edit',
			NNR_EVENTS_URL . 'assets/js/admin-quick-edit.js',
			array( 'jquery', 'inline-edit-post' ),
			NNR_EVENTS_VERSION,
			true
		);
	}

	/**
	 * The Quick Edit fields for Start/End Date+Time, Venue, and Price —
	 * Description, Image, Ticket URL etc. are left to the full editor since
	 * an inline row isn't a great place for a rich text field or a repeater.
	 */
	public function render_quick_edit_fields( $column_name, $post_type ) {
		if ( 'nnr_start_date' !== $column_name || 'event' !== $post_type ) {
			return;
		}
		?>
		<fieldset class="inline-edit-col-right nnr-quick-edit">
			<div class="inline-edit-col">
				<label>
					<span class="title"><?php esc_html_e( 'Start Date', 'nnr-events' ); ?></span>
					<input type="date" name="_nnr_start_date" />
				</label>
				<label>
					<span class="title"><?php esc_html_e( 'Start Time', 'nnr-events' ); ?></span>
					<input type="time" name="_nnr_start_time" />
				</label>
				<label>
					<span class="title"><?php esc_html_e( 'End Date', 'nnr-events' ); ?></span>
					<input type="date" name="_nnr_end_date" />
				</label>
				<label>
					<span class="title"><?php esc_html_e( 'End Time', 'nnr-events' ); ?></span>
					<input type="time" name="_nnr_end_time" />
				</label>
				<label>
					<span class="title"><?php esc_html_e( 'Venue', 'nnr-events' ); ?></span>
					<input type="text" name="_nnr_venue" />
				</label>
				<label>
					<span class="title"><?php esc_html_e( 'Price', 'nnr-events' ); ?></span>
					<input type="text" name="_nnr_price" />
				</label>
				<input type="hidden" name="<?php echo esc_attr( NNR_Meta_Box::NONCE_NAME ); ?>" class="nnr-quick-edit-nonce" value="" />
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Meta query fragment matching events that have passed and don't recur.
	 * A multi-day event counts as expired only once its end date has passed,
	 * mirroring the "upcoming" logic used by NNR_Query.
	 */
	private static function expired_meta_query() {
		$today = current_time( 'Y-m-d' );

		return array(
			'relation' => 'AND',
			array(
				'relation' => 'OR',
				array(
					'key'     => '_nnr_recurring_weekly',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_nnr_recurring_weekly',
					'value'   => '1',
					'compare' => '!=',
				),
			),
			array(
				'relation' => 'OR',
				array(
					'relation' => 'AND',
					array(
						'key'     => '_nnr_end_date',
						'value'   => '',
						'compare' => '!=',
					),
					array(
						'key'     => '_nnr_end_date',
						'value'   => $today,
						'compare' => '<',
						'type'    => 'DATE',
					),
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
						'compare' => '<',
						'type'    => 'DATE',
					),
				),
			),
		);
	}

	public function add_expired_view( $views ) {
		$count = new WP_Query(
			array(
				'post_type'      => 'event',
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => self::expired_meta_query(),
			)
		);

		$is_current = isset( $_GET[ self::FILTER_KEY ] ) && 'expired' === $_GET[ self::FILTER_KEY ]; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$url = add_query_arg(
			array(
				'post_type'        => 'event',
				self::FILTER_KEY   => 'expired',
			),
			admin_url( 'edit.php' )
		);

		$views['nnr_expired'] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
			esc_url( $url ),
			$is_current ? ' class="current" aria-current="page"' : '',
			esc_html__( 'Expired', 'nnr-events' ),
			$count->found_posts
		);

		return $views;
	}

	public function filter_expired_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'event' !== $query->get( 'post_type' ) ) {
			return;
		}

		if ( ! isset( $_GET[ self::FILTER_KEY ] ) || 'expired' !== $_GET[ self::FILTER_KEY ] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$query->set( 'meta_query', self::expired_meta_query() );
	}

	public function add_status_column( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['nnr_status']     = __( 'Status', 'nnr-events' );
				$new['nnr_start_date'] = __( 'Start Date', 'nnr-events' );
			}
		}
		return $new;
	}

	public function add_sortable_columns( $columns ) {
		$columns['nnr_start_date'] = 'nnr_start_date';
		return $columns;
	}

	public function sort_by_start_date( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'event' !== $query->get( 'post_type' ) ) {
			return;
		}

		// No explicit sort requested (a fresh visit to the list, or after
		// using the search/date/status filters without clicking a column
		// header) — default to soonest-first by start date instead of the
		// normal newest-published-first.
		if ( ! $query->get( 'orderby' ) && ! isset( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$query->set( 'meta_key', '_nnr_start_date' );
			$query->set( 'orderby', 'meta_value' );
			$query->set( 'order', 'ASC' );
			return;
		}

		if ( 'nnr_start_date' !== $query->get( 'orderby' ) ) {
			return;
		}

		$query->set( 'meta_key', '_nnr_start_date' );
		$query->set( 'orderby', 'meta_value' );
	}

	public function render_status_column( $column, $post_id ) {
		$recurring  = '1' === get_post_meta( $post_id, '_nnr_recurring_weekly', true );
		$start_date = get_post_meta( $post_id, '_nnr_start_date', true );
		$start_time = get_post_meta( $post_id, '_nnr_start_time', true );
		$end_date   = get_post_meta( $post_id, '_nnr_end_date', true );

		if ( 'nnr_start_date' === $column ) {
			if ( ! $start_date ) {
				echo '&#8212;';
				return;
			}

			$ts = strtotime( $start_date );
			if ( ! $ts ) {
				echo '&#8212;';
				return;
			}

			echo esc_html( date_i18n( 'M j, Y', $ts ) );

			if ( $end_date && $end_date !== $start_date ) {
				$end_ts = strtotime( $end_date );
				if ( $end_ts ) {
					echo esc_html( ' &ndash; ' . date_i18n( 'M j, Y', $end_ts ) );
				}
			}

			if ( $start_time ) {
				$time_ts = strtotime( $start_time );
				if ( $time_ts ) {
					echo '<br /><span style="color:#646970;">' . esc_html( date_i18n( 'g:i A', $time_ts ) ) . '</span>';
				}
			}
			return;
		}

		if ( 'nnr_status' !== $column ) {
			return;
		}

		$this->render_quick_edit_inline_data( $post_id );

		if ( $recurring ) {
			echo '<span style="color:#4d611f;font-weight:600;">' . esc_html__( 'Weekly', 'nnr-events' ) . '</span>';
			return;
		}

		$effective_end = $end_date ? $end_date : $start_date;

		if ( $effective_end && $effective_end < current_time( 'Y-m-d' ) ) {
			echo '<span style="color:#b32d2e;font-weight:600;">' . esc_html__( 'Expired', 'nnr-events' ) . '</span>';
			return;
		}

		echo '<span style="color:#2271b1;">' . esc_html__( 'Upcoming', 'nnr-events' ) . '</span>';
	}

	/**
	 * A hidden per-row data block the Quick Edit JS reads from to populate
	 * its fields, since WordPress only knows how to do that for its own
	 * built-in columns.
	 */
	private function render_quick_edit_inline_data( $post_id ) {
		echo '<div class="hidden nnr-quick-edit-data" id="nnr-inline-' . (int) $post_id . '"';
		foreach ( self::QUICK_EDIT_FIELDS as $key ) {
			$attr = str_replace( '_', '-', substr( $key, 1 ) );
			echo ' data-' . esc_attr( $attr ) . '="' . esc_attr( get_post_meta( $post_id, $key, true ) ) . '"';
		}
		echo ' data-nonce="' . esc_attr( wp_create_nonce( NNR_Meta_Box::NONCE_ACTION ) ) . '"></div>';
	}
}
