<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A second, faster way to create/edit events: one admin page with a table
 * and an AJAX slide-over panel, living alongside the classic post-edit
 * screen (not replacing it).
 *
 * Saving reuses NNR_Meta_Box::save() as-is: that method is hooked to
 * save_post_event and reads every field straight from $_POST (exactly how
 * Quick Edit's save already works, via WordPress core's own inline-save
 * AJAX action). As long as this panel's form uses the same field names and
 * includes NNR_Meta_Box's own nonce field, calling wp_insert_post()/
 * wp_update_post() below fires that same save logic — including the
 * sessions repeater handling — with no duplicated sanitization code.
 */
class NNR_Manage_Events {

	const MENU_SLUG     = 'nnr-manage-events';
	const NONCE_ACTION  = 'nnr_manage_events';
	const AJAX_SAVE     = 'nnr_manage_event_save';
	const AJAX_DELETE   = 'nnr_manage_event_delete';
	const AJAX_RESTORE  = 'nnr_manage_event_restore';
	const PER_PAGE      = 20;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_' . self::AJAX_SAVE, array( $this, 'ajax_save' ) );
		add_action( 'wp_ajax_' . self::AJAX_DELETE, array( $this, 'ajax_delete' ) );
		add_action( 'wp_ajax_' . self::AJAX_RESTORE, array( $this, 'ajax_restore' ) );
	}

	public function add_page() {
		add_submenu_page(
			'edit.php?post_type=event',
			__( 'Quick Manage', 'nnr-events' ),
			__( 'Quick Manage', 'nnr-events' ),
			'edit_posts',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function enqueue_assets( $hook ) {
		if ( 'event_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'nnr-admin-manage-events',
			NNR_EVENTS_URL . 'assets/css/admin-manage-events.css',
			array(),
			NNR_EVENTS_VERSION
		);

		wp_enqueue_script(
			'nnr-admin-manage-events',
			NNR_EVENTS_URL . 'assets/js/admin-manage-events.js',
			array( 'jquery', 'editor' ),
			NNR_EVENTS_VERSION,
			true
		);
	}

	/**
	 * Meta keys (with type) whose values are copied straight into the JS
	 * data blob per event, driving the panel's inputs when Edit is clicked.
	 */
	private function get_event_data_for_js( $post_id ) {
		$data = array(
			'id'          => $post_id,
			'title'       => get_the_title( $post_id ),
			'post_status' => get_post_status( $post_id ),
		);

		foreach ( NNR_Meta_Box::get_fields() as $key => $field ) {
			$data[ $key ] = get_post_meta( $post_id, $key, true );
		}

		$sessions              = get_post_meta( $post_id, '_nnr_sessions', true );
		$data['_nnr_sessions'] = is_array( $sessions ) ? array_values( $sessions ) : array();

		$terms              = wp_get_object_terms( $post_id, NNR_Taxonomy::TAXONOMY, array( 'fields' => 'ids' ) );
		$data['categories'] = is_wp_error( $terms ) ? array() : array_map( 'intval', $terms );

		return $data;
	}

	/**
	 * One <tr>, used both for the page's initial table and for the AJAX
	 * save response (inserting/replacing a row without a page reload).
	 */
	private function render_table_row( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		$terms     = get_the_terms( $post_id, NNR_Taxonomy::TAXONOMY );
		$cat_names = is_array( $terms ) ? wp_list_pluck( $terms, 'name' ) : array();

		$start_date = get_post_meta( $post_id, '_nnr_start_date', true );
		$start_time = get_post_meta( $post_id, '_nnr_start_time', true );
		$end_date   = get_post_meta( $post_id, '_nnr_end_date', true );

		$when = '&#8212;';
		if ( $start_date ) {
			$ts = strtotime( $start_date );
			if ( $ts ) {
				$when = date_i18n( 'M j, Y', $ts );
				if ( $end_date && $end_date !== $start_date ) {
					$end_ts = strtotime( $end_date );
					if ( $end_ts ) {
						$when .= ' &ndash; ' . date_i18n( 'M j, Y', $end_ts );
					}
				}
				if ( $start_time ) {
					$time_ts = strtotime( $start_time );
					if ( $time_ts ) {
						$when .= ' &middot; ' . date_i18n( 'g:i A', $time_ts );
					}
				}
			}
		}

		if ( 'trash' === $post->post_status ) {
			$status = array(
				'label' => __( 'Trash', 'nnr-events' ),
				'color' => '#b32d2e',
			);
		} elseif ( 'draft' === $post->post_status ) {
			$status = array(
				'label' => __( 'Draft', 'nnr-events' ),
				'color' => '#996800',
			);
		} else {
			$status = NNR_Admin_List::get_status( $post_id );
		}

		$is_trash = 'trash' === $post->post_status;

		ob_start();
		?>
		<tr data-id="<?php echo (int) $post_id; ?>">
			<td class="title column-title has-row-actions column-primary">
				<strong><?php echo esc_html( $post->post_title ? $post->post_title : __( '(no title)', 'nnr-events' ) ); ?></strong>
				<div class="row-actions">
					<?php if ( $is_trash ) : ?>
						<span class="untrash">
							<a href="#" class="nnr-restore-event" data-id="<?php echo (int) $post_id; ?>"><?php esc_html_e( 'Restore', 'nnr-events' ); ?></a> |
						</span>
						<span class="delete">
							<a href="#" class="nnr-delete-forever-event" data-id="<?php echo (int) $post_id; ?>"><?php esc_html_e( 'Delete Permanently', 'nnr-events' ); ?></a>
						</span>
					<?php else : ?>
						<span class="edit">
							<a href="#" class="nnr-edit-event" data-id="<?php echo (int) $post_id; ?>"><?php esc_html_e( 'Edit', 'nnr-events' ); ?></a> |
						</span>
						<span class="trash">
							<a href="#" class="nnr-trash-event" data-id="<?php echo (int) $post_id; ?>"><?php esc_html_e( 'Trash', 'nnr-events' ); ?></a>
						</span>
					<?php endif; ?>
				</div>
			</td>
			<td><?php echo $cat_names ? esc_html( implode( ', ', $cat_names ) ) : '&#8212;'; ?></td>
			<td><?php echo wp_kses( $when, array() ); ?></td>
			<td><span style="color:<?php echo esc_attr( $status['color'] ); ?>;font-weight:600;"><?php echo esc_html( $status['label'] ); ?></span></td>
		</tr>
		<?php
		return ob_get_clean();
	}

	private function status_tab_url( $status ) {
		$args = array(
			'post_type' => 'event',
			'page'      => self::MENU_SLUG,
		);
		if ( 'active' !== $status ) {
			$args['nnr_status'] = $status;
		}
		return admin_url( 'edit.php?' . http_build_query( $args ) );
	}

	public function render_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$status = isset( $_GET['nnr_status'] ) ? sanitize_key( wp_unslash( $_GET['nnr_status'] ) ) : 'active'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $status, array( 'active', 'draft', 'expired', 'trash' ), true ) ) {
			$status = 'active';
		}
		$category = isset( $_GET['event_category'] ) ? sanitize_title( wp_unslash( $_GET['event_category'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$query_args = array(
			'post_type'      => 'event',
			'posts_per_page' => self::PER_PAGE,
			'paged'          => $paged,
		);

		switch ( $status ) {
			case 'draft':
				// Unlike Active/Expired, a draft legitimately may not have a
				// start date yet (that's often exactly why it's still a
				// draft) — ordering by _nnr_sort_date must not require the
				// meta row to exist, or dateless drafts silently vanish from
				// the list entirely (its count above would still include
				// them, which is how this was caught).
				$query_args['post_status'] = 'draft';
				$query_args['meta_query']  = NNR_Query::orderable_sort_date_meta_query();
				$query_args['orderby']     = array( 'nnr_sort_date_clause' => 'ASC' );
				break;
			case 'trash':
				$query_args['post_status'] = 'trash';
				$query_args['meta_query']  = NNR_Query::orderable_sort_date_meta_query();
				$query_args['orderby']     = array( 'nnr_sort_date_clause' => 'ASC' );
				break;
			case 'expired':
				$query_args['post_status'] = 'publish';
				$query_args['meta_key']    = '_nnr_sort_date';
				$query_args['orderby']     = 'meta_value';
				$query_args['order']       = 'ASC';
				$query_args['meta_query']  = NNR_Admin_List::expired_meta_query();
				break;
			default:
				$status                    = 'active';
				$query_args['post_status'] = 'publish';
				$query_args['meta_key']    = '_nnr_sort_date';
				$query_args['orderby']     = 'meta_value';
				$query_args['order']       = 'ASC';
				$query_args['meta_query']  = NNR_Query::upcoming_meta_query();
		}

		if ( $search ) {
			$query_args['s'] = $search;
		}

		if ( $category ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => NNR_Taxonomy::TAXONOMY,
					'field'    => 'slug',
					'terms'    => $category,
				),
			);
		}

		$query = new WP_Query( $query_args );

		$terms = get_terms(
			array(
				'taxonomy'   => NNR_Taxonomy::TAXONOMY,
				'hide_empty' => false,
			)
		);
		if ( ! is_array( $terms ) ) {
			$terms = array();
		}

		$events_data = array();
		foreach ( $query->posts as $event_post ) {
			$events_data[ $event_post->ID ] = $this->get_event_data_for_js( $event_post->ID );
		}

		wp_localize_script(
			'nnr-admin-manage-events',
			'NNRManageEvents',
			array(
				'ajaxUrl'             => admin_url( 'admin-ajax.php' ),
				'nonce'               => wp_create_nonce( self::NONCE_ACTION ),
				'saveAction'          => self::AJAX_SAVE,
				'deleteAction'        => self::AJAX_DELETE,
				'restoreAction'       => self::AJAX_RESTORE,
				'confirmDelete'       => __( 'Move this event to Trash?', 'nnr-events' ),
				'confirmDeleteForever' => __( 'Permanently delete this event? This cannot be undone.', 'nnr-events' ),
				'addTitle'            => __( 'Add New Event', 'nnr-events' ),
				'editTitle'           => __( 'Edit Event', 'nnr-events' ),
				'removeText'          => __( 'Remove', 'nnr-events' ),
				'noEventsText'        => __( 'No events found.', 'nnr-events' ),
				'events'              => $events_data,
			)
		);

		$active_count  = ( new WP_Query( array( 'post_type' => 'event', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_query' => NNR_Query::upcoming_meta_query() ) ) )->found_posts;
		$draft_count   = ( new WP_Query( array( 'post_type' => 'event', 'post_status' => 'draft', 'posts_per_page' => 1, 'fields' => 'ids' ) ) )->found_posts;
		$expired_count = ( new WP_Query( array( 'post_type' => 'event', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_query' => NNR_Admin_List::expired_meta_query() ) ) )->found_posts;
		$trash_count   = ( new WP_Query( array( 'post_type' => 'event', 'post_status' => 'trash', 'posts_per_page' => 1, 'fields' => 'ids' ) ) )->found_posts;

		$tabs = array(
			'active'  => array( __( 'Active', 'nnr-events' ), $active_count ),
			'draft'   => array( __( 'Draft', 'nnr-events' ), $draft_count ),
			'expired' => array( __( 'Expired', 'nnr-events' ), $expired_count ),
			'trash'   => array( __( 'Trash', 'nnr-events' ), $trash_count ),
		);
		?>
		<div class="wrap nnr-manage-events">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Quick Manage Events', 'nnr-events' ); ?></h1>
			<button type="button" class="page-title-action" id="nnr-add-event"><?php esc_html_e( 'Add New Event', 'nnr-events' ); ?></button>
			<hr class="wp-header-end" />

			<ul class="subsubsub">
				<?php $i = 0; foreach ( $tabs as $key => $tab ) : $i++; ?>
					<li>
						<a href="<?php echo esc_url( $this->status_tab_url( $key ) ); ?>" class="<?php echo $status === $key ? 'current' : ''; ?>">
							<?php echo esc_html( $tab[0] ); ?> <span class="count">(<?php echo (int) $tab[1]; ?>)</span>
						</a>
						<?php echo $i < count( $tabs ) ? ' |' : ''; ?>
					</li>
				<?php endforeach; ?>
			</ul>

			<form method="get" class="nnr-manage-events__filters">
				<input type="hidden" name="post_type" value="event" />
				<input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>" />
				<input type="hidden" name="nnr_status" value="<?php echo esc_attr( $status ); ?>" />
				<select name="event_category">
					<option value=""><?php esc_html_e( 'All categories', 'nnr-events' ); ?></option>
					<?php foreach ( $terms as $term ) : ?>
						<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $category, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search title', 'nnr-events' ); ?>" />
				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'nnr-events' ); ?></button>
			</form>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Title', 'nnr-events' ); ?></th>
						<th><?php esc_html_e( 'Category', 'nnr-events' ); ?></th>
						<th><?php esc_html_e( 'When', 'nnr-events' ); ?></th>
						<th><?php esc_html_e( 'Status', 'nnr-events' ); ?></th>
					</tr>
				</thead>
				<tbody id="nnr-events-tbody">
					<?php if ( $query->have_posts() ) : ?>
						<?php foreach ( $query->posts as $event_post ) : ?>
							<?php echo $this->render_table_row( $event_post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endforeach; ?>
					<?php else : ?>
						<tr class="nnr-events-empty-row"><td colspan="4"><?php esc_html_e( 'No events found.', 'nnr-events' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $query->max_num_pages > 1 ) : ?>
				<div class="tablenav">
					<div class="tablenav-pages">
						<?php
						echo wp_kses_post(
							paginate_links(
								array(
									'base'    => add_query_arg( 'paged', '%#%' ),
									'format'  => '',
									'current' => $paged,
									'total'   => $query->max_num_pages,
								)
							)
						);
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<div id="nnr-event-panel-overlay" hidden></div>
		<div id="nnr-event-panel" hidden aria-hidden="true">
			<div class="nnr-event-panel__header">
				<h2 id="nnr-event-panel-title"><?php esc_html_e( 'Add New Event', 'nnr-events' ); ?></h2>
				<button type="button" class="button-link" id="nnr-event-panel-close" aria-label="<?php esc_attr_e( 'Close', 'nnr-events' ); ?>">&times;</button>
			</div>
			<form id="nnr-event-form">
				<?php wp_nonce_field( NNR_Meta_Box::NONCE_ACTION, NNR_Meta_Box::NONCE_NAME ); ?>
				<input type="hidden" name="_nnr_full_form" value="1" />
				<input type="hidden" name="action" value="<?php echo esc_attr( self::AJAX_SAVE ); ?>" />
				<input type="hidden" name="nnr_nonce" value="<?php echo esc_attr( wp_create_nonce( self::NONCE_ACTION ) ); ?>" />
				<input type="hidden" name="post_id" id="nnr_qm_post_id" value="0" />

				<table class="form-table">
					<tbody>
						<tr>
							<th><label for="nnr_qm_title"><?php esc_html_e( 'Title', 'nnr-events' ); ?></label></th>
							<td><input type="text" id="nnr_qm_title" name="post_title" class="regular-text" required /></td>
						</tr>
						<tr id="nnr_qm_single_dates_row">
							<th><label for="nnr_qm_start_date"><?php esc_html_e( 'Start Date', 'nnr-events' ); ?></label></th>
							<td>
								<input type="date" id="nnr_qm_start_date" name="_nnr_start_date" required />
								<input type="time" id="nnr_qm_start_time" name="_nnr_start_time" style="margin-left:8px;" />
							</td>
						</tr>
						<tr id="nnr_qm_single_end_dates_row">
							<th><label for="nnr_qm_end_date"><?php esc_html_e( 'End Date', 'nnr-events' ); ?></label></th>
							<td>
								<input type="date" id="nnr_qm_end_date" name="_nnr_end_date" />
								<input type="time" id="nnr_qm_end_time" name="_nnr_end_time" style="margin-left:8px;" />
								<p class="description"><?php esc_html_e( 'Optional.', 'nnr-events' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="nnr_qm_multi_session"><?php esc_html_e( 'Different Times Each Day', 'nnr-events' ); ?></label></th>
							<td>
								<label>
									<input type="checkbox" id="nnr_qm_multi_session" name="_nnr_multi_session" value="1" />
									<?php esc_html_e( 'This event has a different start/end time on each day', 'nnr-events' ); ?>
								</label>
							</td>
						</tr>
						<tr id="nnr_qm_sessions_row" hidden>
							<th><?php esc_html_e( 'Day Times', 'nnr-events' ); ?></th>
							<td>
								<table id="nnr_qm_sessions_table">
									<tbody>
										<tr class="nnr-session-row">
											<td><input type="date" name="_nnr_sessions[0][date]" /></td>
											<td><input type="time" name="_nnr_sessions[0][start_time]" /></td>
											<td><input type="time" name="_nnr_sessions[0][end_time]" /></td>
											<td><button type="button" class="button nnr-session-remove"><?php esc_html_e( 'Remove', 'nnr-events' ); ?></button></td>
										</tr>
									</tbody>
								</table>
								<button type="button" class="button" id="nnr_qm_session_add"><?php esc_html_e( 'Add another day', 'nnr-events' ); ?></button>
								<p class="description"><?php esc_html_e( 'Start time is required per day; end time is optional.', 'nnr-events' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="nnr_qm_recurring_weekly"><?php esc_html_e( 'Recurring Weekly', 'nnr-events' ); ?></label></th>
							<td>
								<label>
									<input type="checkbox" id="nnr_qm_recurring_weekly" name="_nnr_recurring_weekly" value="1" />
									<?php esc_html_e( 'This event repeats every week', 'nnr-events' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th><label for="nnr_qm_description_editor"><?php esc_html_e( 'Description', 'nnr-events' ); ?></label></th>
							<td>
								<?php
								wp_editor(
									'',
									'nnr_qm_description_editor',
									array(
										'textarea_name' => '_nnr_description',
										'textarea_rows' => 6,
										'teeny'         => true,
										'media_buttons' => false,
										'quicktags'     => true,
										'tinymce'       => array(
											'setup' => 'function(ed){function fix(){var b=ed.getBody();if(b&&"true"!==b.contentEditable){b.setAttribute("contenteditable","true");}}ed.on("init click focus",fix);}',
										),
									)
								);
								?>
							</td>
						</tr>
						<tr>
							<th><label for="nnr_qm_image_url"><?php esc_html_e( 'Image URL', 'nnr-events' ); ?></label></th>
							<td><input type="url" id="nnr_qm_image_url" name="_nnr_image_url" class="regular-text" placeholder="https://" /></td>
						</tr>
						<tr>
							<th><label for="nnr_qm_venue"><?php esc_html_e( 'Venue Name', 'nnr-events' ); ?></label></th>
							<td><input type="text" id="nnr_qm_venue" name="_nnr_venue" class="regular-text" /></td>
						</tr>
						<tr>
							<th><label for="nnr_qm_address"><?php esc_html_e( 'Address', 'nnr-events' ); ?></label></th>
							<td><input type="text" id="nnr_qm_address" name="_nnr_address" class="regular-text" /></td>
						</tr>
						<tr>
							<th><label for="nnr_qm_price"><?php esc_html_e( 'Price', 'nnr-events' ); ?></label></th>
							<td><input type="text" id="nnr_qm_price" name="_nnr_price" class="regular-text" placeholder="Free, $10, $15-20" /></td>
						</tr>
						<tr>
							<th><label for="nnr_qm_ticket_url"><?php esc_html_e( 'URL', 'nnr-events' ); ?></label></th>
							<td><input type="url" id="nnr_qm_ticket_url" name="_nnr_ticket_url" class="regular-text" placeholder="https://" required /></td>
						</tr>
						<tr>
							<th><label for="nnr_qm_button_text"><?php esc_html_e( 'Button Text', 'nnr-events' ); ?></label></th>
							<td><input type="text" id="nnr_qm_button_text" name="_nnr_button_text" class="regular-text" placeholder="Get Tickets" /></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Categories', 'nnr-events' ); ?></th>
							<td id="nnr_qm_categories">
								<?php foreach ( $terms as $term ) : ?>
									<label style="display:block;">
										<input type="checkbox" name="nnr_categories[]" value="<?php echo (int) $term->term_id; ?>" />
										<?php echo esc_html( $term->name ); ?>
									</label>
								<?php endforeach; ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Status', 'nnr-events' ); ?></th>
							<td>
								<label><input type="radio" name="post_status" value="draft" checked /> <?php esc_html_e( 'Draft', 'nnr-events' ); ?></label>
								&nbsp;&nbsp;
								<label><input type="radio" name="post_status" value="publish" /> <?php esc_html_e( 'Published', 'nnr-events' ); ?></label>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Event', 'nnr-events' ); ?></button>
					<button type="button" class="button" id="nnr-event-cancel"><?php esc_html_e( 'Cancel', 'nnr-events' ); ?></button>
					<span class="spinner" id="nnr-event-spinner"></span>
				</p>
			</form>
		</div>
		<?php
	}

	public function ajax_save() {
		check_ajax_referer( self::NONCE_ACTION, 'nnr_nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do that.', 'nnr-events' ) ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to edit this event.', 'nnr-events' ) ) );
		}

		$title  = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '';
		$status = ( isset( $_POST['post_status'] ) && 'publish' === $_POST['post_status'] ) ? 'publish' : 'draft';

		if ( '' === $title ) {
			wp_send_json_error( array( 'message' => __( 'Title is required.', 'nnr-events' ) ) );
		}

		$postarr = array(
			'post_type'   => 'event',
			'post_title'  => $title,
			'post_status' => $status,
		);

		if ( $post_id ) {
			$postarr['ID'] = $post_id;
			$result        = wp_update_post( $postarr, true );
		} else {
			$result = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$post_id = $result;

		$categories = ( isset( $_POST['nnr_categories'] ) && is_array( $_POST['nnr_categories'] ) )
			? array_map( 'absint', wp_unslash( $_POST['nnr_categories'] ) )
			: array();
		wp_set_object_terms( $post_id, $categories, NNR_Taxonomy::TAXONOMY );

		wp_send_json_success(
			array(
				'post_id'    => $post_id,
				'row_html'   => $this->render_table_row( $post_id ),
				'event_data' => $this->get_event_data_for_js( $post_id ),
			)
		);
	}

	public function ajax_delete() {
		check_ajax_referer( self::NONCE_ACTION, 'nnr_nonce' );

		$post_id   = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$permanent = ! empty( $_POST['permanent'] );

		if ( ! $post_id || ! current_user_can( 'delete_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do that.', 'nnr-events' ) ) );
		}

		if ( $permanent ) {
			if ( ! wp_delete_post( $post_id, true ) ) {
				wp_send_json_error( array( 'message' => __( 'Could not permanently delete that event.', 'nnr-events' ) ) );
			}
		} elseif ( ! wp_trash_post( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not trash that event.', 'nnr-events' ) ) );
		}

		wp_send_json_success();
	}

	public function ajax_restore() {
		check_ajax_referer( self::NONCE_ACTION, 'nnr_nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id || ! current_user_can( 'delete_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do that.', 'nnr-events' ) ) );
		}

		if ( ! wp_untrash_post( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not restore that event.', 'nnr-events' ) ) );
		}

		wp_send_json_success(
			array(
				'row_html'   => $this->render_table_row( $post_id ),
				'event_data' => $this->get_event_data_for_js( $post_id ),
			)
		);
	}
}
