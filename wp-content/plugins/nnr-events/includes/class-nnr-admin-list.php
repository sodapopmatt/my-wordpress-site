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
		add_filter( 'views_edit-event', array( $this, 'build_views' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_expired_query' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_active_query' ) );
		add_action( 'pre_get_posts', array( $this, 'sort_by_start_date' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_by_category' ) );
		add_filter( 'manage_event_posts_columns', array( $this, 'add_status_column' ) );
		add_filter( 'manage_edit-event_sortable_columns', array( $this, 'add_sortable_columns' ) );
		add_action( 'manage_event_posts_custom_column', array( $this, 'render_status_column' ), 10, 2 );
		add_action( 'quick_edit_custom_box', array( $this, 'render_quick_edit_fields' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_quick_edit_script' ) );
		add_action( 'restrict_manage_posts', array( $this, 'render_category_dropdown' ) );
	}

	/**
	 * WordPress only auto-generates a category filter dropdown for the
	 * built-in "category" taxonomy on regular posts — a custom taxonomy
	 * like this one needs its dropdown added explicitly.
	 */
	public function render_category_dropdown( $post_type ) {
		if ( 'event' !== $post_type ) {
			return;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => NNR_Taxonomy::TAXONOMY,
				'hide_empty' => false,
			)
		);
		if ( ! is_array( $terms ) || empty( $terms ) ) {
			return;
		}

		$current = isset( $_GET['event_category'] ) ? sanitize_title( wp_unslash( $_GET['event_category'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<label class="screen-reader-text" for="nnr-filter-by-category"><?php esc_html_e( 'Filter by category', 'nnr-events' ); ?></label>
		<select name="event_category" id="nnr-filter-by-category">
			<option value=""><?php esc_html_e( 'All categories', 'nnr-events' ); ?></option>
			<?php foreach ( $terms as $term ) : ?>
				<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $current, $term->slug ); ?>>
					<?php echo esc_html( $term->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
		// WordPress's own filter form already carries a hidden post_status
		// field, so applying this dropdown while on "Draft" stays on
		// "Draft" for free. There's no such field for our own Expired flag
		// though, so without this, filtering by category while on
		// "Expired" would silently drop back to "Active".
		if ( isset( $_GET[ self::FILTER_KEY ] ) && 'expired' === $_GET[ self::FILTER_KEY ] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<input type="hidden" name="' . esc_attr( self::FILTER_KEY ) . '" value="expired" />';
		}
	}

	public function filter_by_category( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'event' !== $query->get( 'post_type' ) ) {
			return;
		}

		if ( empty( $_GET['event_category'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$slug = sanitize_title( wp_unslash( $_GET['event_category'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $slug ) {
			return;
		}

		$query->set(
			'tax_query',
			array(
				array(
					'taxonomy' => NNR_Taxonomy::TAXONOMY,
					'field'    => 'slug',
					'terms'    => $slug,
				),
			)
		);
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
	public static function expired_meta_query() {
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

	/**
	 * Replaces WordPress's default views row (All / Published / Draft /
	 * Trash / ...) with exactly four: Active (published & not expired —
	 * the default view), Draft, Expired (published & expired), and Trash.
	 */
	public function build_views( $views ) {
		$is_draft_current   = isset( $_GET['post_status'] ) && 'draft' === $_GET['post_status']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_trash_current   = isset( $_GET['post_status'] ) && 'trash' === $_GET['post_status']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_expired_current = isset( $_GET[ self::FILTER_KEY ] ) && 'expired' === $_GET[ self::FILTER_KEY ]; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_active_current  = ! $is_draft_current && ! $is_trash_current && ! $is_expired_current;

		$active_count = new WP_Query(
			array(
				'post_type'      => 'event',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => NNR_Query::upcoming_meta_query(),
			)
		);

		$expired_count = new WP_Query(
			array(
				'post_type'      => 'event',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => self::expired_meta_query(),
			)
		);

		$counts      = wp_count_posts( 'event' );
		$draft_count = isset( $counts->draft ) ? (int) $counts->draft : 0;
		$trash_count = isset( $counts->trash ) ? (int) $counts->trash : 0;

		$new_views = array();

		$new_views['nnr_active'] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
			esc_url( admin_url( 'edit.php?post_type=event' ) ),
			$is_active_current ? ' class="current" aria-current="page"' : '',
			esc_html__( 'Active', 'nnr-events' ),
			$active_count->found_posts
		);

		$new_views['draft'] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
			esc_url( admin_url( 'edit.php?post_type=event&post_status=draft' ) ),
			$is_draft_current ? ' class="current" aria-current="page"' : '',
			esc_html__( 'Draft', 'nnr-events' ),
			$draft_count
		);

		$new_views['nnr_expired'] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
			esc_url( admin_url( 'edit.php?post_type=event&' . self::FILTER_KEY . '=expired' ) ),
			$is_expired_current ? ' class="current" aria-current="page"' : '',
			esc_html__( 'Expired', 'nnr-events' ),
			$expired_count->found_posts
		);

		$new_views['trash'] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
			esc_url( admin_url( 'edit.php?post_type=event&post_status=trash' ) ),
			$is_trash_current ? ' class="current" aria-current="page"' : '',
			esc_html__( 'Trash', 'nnr-events' ),
			$trash_count
		);

		return $new_views;
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

		$query->set( 'post_status', 'publish' );
		$query->set( 'meta_query', self::expired_meta_query() );
	}

	/**
	 * The default view: published events that aren't expired. Applies
	 * whenever no other status is explicitly chosen — a fresh visit to the
	 * list included — so "Active" is what people land on by default.
	 */
	public function filter_active_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'event' !== $query->get( 'post_type' ) ) {
			return;
		}

		// WordPress's own filter form carries a hidden post_status field
		// that defaults to "all" whenever no specific status view is
		// active — including while on the default Active view itself, e.g.
		// after submitting the category dropdown. Treat that the same as
		// no post_status at all, so filtering by category doesn't silently
		// drop out of "Active" into "every status".
		$requested_status = isset( $_GET['post_status'] ) ? wp_unslash( $_GET['post_status'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' !== $requested_status && 'all' !== $requested_status ) {
			return;
		}

		if ( isset( $_GET[ self::FILTER_KEY ] ) && 'expired' === $_GET[ self::FILTER_KEY ] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$query->set( 'post_status', 'publish' );
		$query->set( 'meta_query', NNR_Query::upcoming_meta_query() );
	}

	public function add_status_column( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['nnr_status']     = __( 'Status', 'nnr-events' );
				$new['nnr_start_date'] = __( 'Start Date', 'nnr-events' );
				$new['nnr_start_time'] = __( 'Start Time', 'nnr-events' );
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
			$requested_status = isset( $_GET['post_status'] ) ? wp_unslash( $_GET['post_status'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			// Draft/Trash: unlike Active/Expired, a dateless event is
			// completely normal here (often exactly why it's a draft, or
			// it was trashed before ever getting a date) — sorting by
			// meta_key/orderby=meta_value alone would silently exclude it,
			// since that shortcut requires the meta row to exist.
			if ( in_array( $requested_status, array( 'draft', 'trash' ), true ) ) {
				$query->set( 'meta_query', NNR_Query::orderable_sort_date_meta_query() );
				$query->set( 'orderby', array( 'nnr_sort_date_clause' => 'ASC' ) );
				return;
			}
			$query->set( 'meta_key', '_nnr_sort_date' );
			$query->set( 'orderby', 'meta_value' );
			$query->set( 'order', 'ASC' );
			return;
		}

		if ( 'nnr_start_date' !== $query->get( 'orderby' ) ) {
			return;
		}

		$query->set( 'meta_key', '_nnr_sort_date' );
		$query->set( 'orderby', 'meta_value' );
	}

	/**
	 * Recurring/expired/upcoming determination shared by the Status column
	 * here and by NNR_Manage_Events' own table, so both render identically
	 * from one place. Returns [ 'label' => string, 'color' => css color ].
	 */
	public static function get_status( $post_id ) {
		$recurring = '1' === get_post_meta( $post_id, '_nnr_recurring_weekly', true );

		if ( $recurring ) {
			return array(
				'label' => __( 'Weekly', 'nnr-events' ),
				'color' => '#4d611f',
			);
		}

		$start_date    = get_post_meta( $post_id, '_nnr_start_date', true );
		$end_date      = get_post_meta( $post_id, '_nnr_end_date', true );
		$effective_end = $end_date ? $end_date : $start_date;

		if ( $effective_end && $effective_end < current_time( 'Y-m-d' ) ) {
			return array(
				'label' => __( 'Expired', 'nnr-events' ),
				'color' => '#b32d2e',
			);
		}

		return array(
			'label' => __( 'Upcoming', 'nnr-events' ),
			'color' => '#2271b1',
		);
	}

	public function render_status_column( $column, $post_id ) {
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
			return;
		}

		if ( 'nnr_start_time' === $column ) {
			if ( ! $start_time ) {
				echo '&#8212;';
				return;
			}

			$time_ts = strtotime( $start_time );
			if ( ! $time_ts ) {
				echo '&#8212;';
				return;
			}

			echo esc_html( date_i18n( 'g:i A', $time_ts ) );
			return;
		}

		if ( 'nnr_status' !== $column ) {
			return;
		}

		$this->render_quick_edit_inline_data( $post_id );

		$status = self::get_status( $post_id );
		printf( '<span style="color:%s;font-weight:600;">%s</span>', esc_attr( $status['color'] ), esc_html( $status['label'] ) );
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
