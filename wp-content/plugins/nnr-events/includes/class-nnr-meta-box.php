<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NNR_Meta_Box {

	const NONCE_ACTION = 'nnr_save_event_details';
	const NONCE_NAME   = 'nnr_event_details_nonce';

	/**
	 * Field key => [ label, type ]. Type drives both the rendered input and sanitization.
	 */
	private $fields = array(
		'_nnr_start_date'      => array( 'label' => 'Start Date', 'type' => 'date' ),
		'_nnr_start_time'      => array( 'label' => 'Start Time', 'type' => 'time' ),
		'_nnr_end_date'        => array( 'label' => 'End Date', 'type' => 'date' ),
		'_nnr_end_time'        => array( 'label' => 'End Time', 'type' => 'time' ),
		'_nnr_multi_session'   => array( 'label' => 'Different Times Each Day', 'type' => 'checkbox' ),
		'_nnr_recurring_weekly'=> array( 'label' => 'Recurring Weekly', 'type' => 'checkbox' ),
		'_nnr_description'     => array( 'label' => 'Description', 'type' => 'richtext' ),
		'_nnr_image_url'       => array( 'label' => 'Image URL', 'type' => 'url' ),
		'_nnr_venue'           => array( 'label' => 'Venue Name', 'type' => 'text' ),
		'_nnr_address'         => array( 'label' => 'Address', 'type' => 'text' ),
		'_nnr_price'           => array( 'label' => 'Price', 'type' => 'text' ),
		'_nnr_ticket_url'      => array( 'label' => 'URL', 'type' => 'url' ),
		'_nnr_button_text'     => array( 'label' => 'Button Text', 'type' => 'text' ),
	);

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_event', array( $this, 'save' ) );
	}

	public function add_meta_box() {
		add_meta_box(
			'nnr_event_details',
			__( 'Event Details', 'nnr-events' ),
			array( $this, 'render' ),
			'event',
			'normal',
			'high'
		);
	}

	public function render( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		// Marks this as the full meta box (vs. Quick Edit, which posts only a
		// subset of fields) so save() knows whether an absent checkbox means
		// "leave it alone" or "user unchecked it".
		echo '<input type="hidden" name="_nnr_full_form" value="1" />';

		$values = array();
		foreach ( $this->fields as $key => $field ) {
			$values[ $key ] = get_post_meta( $post->ID, $key, true );
		}

		if ( '' === $values['_nnr_price'] ) {
			$values['_nnr_price'] = 'Free';
		}
		if ( '' === $values['_nnr_button_text'] ) {
			$values['_nnr_button_text'] = 'Website';
		}

		$sessions = get_post_meta( $post->ID, '_nnr_sessions', true );
		if ( ! is_array( $sessions ) || empty( $sessions ) ) {
			$sessions = array( array( 'date' => '', 'start_time' => '', 'end_time' => '' ) );
		}
		?>
		<table class="form-table nnr-event-fields">
			<tbody>
				<tr id="nnr_single_dates_row">
					<th><label for="_nnr_start_date"><?php esc_html_e( 'Start Date', 'nnr-events' ); ?></label></th>
					<td>
						<input type="date" id="_nnr_start_date" name="_nnr_start_date" value="<?php echo esc_attr( $values['_nnr_start_date'] ); ?>" <?php echo $values['_nnr_multi_session'] ? '' : 'required'; ?> />
						<input type="time" id="_nnr_start_time" name="_nnr_start_time" value="<?php echo esc_attr( $values['_nnr_start_time'] ); ?>" style="margin-left:8px;" />
					</td>
				</tr>
				<tr id="nnr_single_end_dates_row">
					<th><label for="_nnr_end_date"><?php esc_html_e( 'End Date', 'nnr-events' ); ?></label></th>
					<td>
						<input type="date" id="_nnr_end_date" name="_nnr_end_date" value="<?php echo esc_attr( $values['_nnr_end_date'] ); ?>" />
						<input type="time" id="_nnr_end_time" name="_nnr_end_time" value="<?php echo esc_attr( $values['_nnr_end_time'] ); ?>" style="margin-left:8px;" />
						<p class="description"><?php esc_html_e( 'Optional.', 'nnr-events' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="_nnr_multi_session"><?php esc_html_e( 'Different Times Each Day', 'nnr-events' ); ?></label></th>
					<td>
						<label>
							<input type="checkbox" id="_nnr_multi_session" name="_nnr_multi_session" value="1" <?php checked( $values['_nnr_multi_session'], '1' ); ?> />
							<?php esc_html_e( 'This event has a different start/end time on each day (e.g. Fri 6pm, Sat 10am, Sun 12pm)', 'nnr-events' ); ?>
						</label>
					</td>
				</tr>
				<tr id="nnr_sessions_row" <?php echo $values['_nnr_multi_session'] ? '' : 'hidden'; ?>>
					<th><?php esc_html_e( 'Day Times', 'nnr-events' ); ?></th>
					<td>
						<table id="nnr_sessions_table">
							<tbody>
								<?php foreach ( $sessions as $i => $session ) : ?>
									<tr class="nnr-session-row">
										<td><input type="date" name="_nnr_sessions[<?php echo (int) $i; ?>][date]" value="<?php echo esc_attr( $session['date'] ); ?>" /></td>
										<td><input type="time" name="_nnr_sessions[<?php echo (int) $i; ?>][start_time]" value="<?php echo esc_attr( $session['start_time'] ); ?>" /></td>
										<td><input type="time" name="_nnr_sessions[<?php echo (int) $i; ?>][end_time]" value="<?php echo esc_attr( $session['end_time'] ); ?>" /></td>
										<td><button type="button" class="button nnr-session-remove"><?php esc_html_e( 'Remove', 'nnr-events' ); ?></button></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<button type="button" class="button" id="nnr_session_add"><?php esc_html_e( 'Add another day', 'nnr-events' ); ?></button>
						<p class="description"><?php esc_html_e( 'Start time is required per day; end time is optional.', 'nnr-events' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="_nnr_recurring_weekly"><?php esc_html_e( 'Recurring Weekly', 'nnr-events' ); ?></label></th>
					<td>
						<label>
							<input type="checkbox" id="_nnr_recurring_weekly" name="_nnr_recurring_weekly" value="1" <?php checked( $values['_nnr_recurring_weekly'], '1' ); ?> />
							<?php esc_html_e( 'This event repeats every week (e.g. a standing trivia night)', 'nnr-events' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Recurring events always show as upcoming in listings, regardless of the start date above.', 'nnr-events' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="nnr_description_editor"><?php esc_html_e( 'Description', 'nnr-events' ); ?></label></th>
					<td>
						<?php
						wp_editor(
							$values['_nnr_description'],
							'nnr_description_editor',
							array(
								'textarea_name' => '_nnr_description',
								'textarea_rows' => 6,
								'teeny'         => true,
								'media_buttons' => false,
								'quicktags'     => true,
							)
						);
						?>
						<p class="description"><?php esc_html_e( 'Shown on the event card. Not limited in length.', 'nnr-events' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="_nnr_image_url"><?php esc_html_e( 'Image URL', 'nnr-events' ); ?></label></th>
					<td>
						<input type="url" id="_nnr_image_url" name="_nnr_image_url" value="<?php echo esc_attr( $values['_nnr_image_url'] ); ?>" class="regular-text" placeholder="https://" />
						<p class="description"><?php esc_html_e( 'Link to an image hosted elsewhere (e.g. the ticket listing\'s own photo) instead of uploading one here. Falls back to the Featured Image below if left blank.', 'nnr-events' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="_nnr_venue"><?php esc_html_e( 'Venue Name', 'nnr-events' ); ?></label></th>
					<td><input type="text" id="_nnr_venue" name="_nnr_venue" value="<?php echo esc_attr( $values['_nnr_venue'] ); ?>" class="regular-text" /></td>
				</tr>
				<tr>
					<th><label for="_nnr_address"><?php esc_html_e( 'Address', 'nnr-events' ); ?></label></th>
					<td><input type="text" id="_nnr_address" name="_nnr_address" value="<?php echo esc_attr( $values['_nnr_address'] ); ?>" class="regular-text" /></td>
				</tr>
				<tr>
					<th><label for="_nnr_price"><?php esc_html_e( 'Price', 'nnr-events' ); ?></label></th>
					<td><input type="text" id="_nnr_price" name="_nnr_price" value="<?php echo esc_attr( $values['_nnr_price'] ); ?>" class="regular-text" placeholder="Free, $10, $15-20" /></td>
				</tr>
				<tr>
					<th><label for="_nnr_ticket_url"><?php esc_html_e( 'URL', 'nnr-events' ); ?></label></th>
					<td>
						<input type="url" id="_nnr_ticket_url" name="_nnr_ticket_url" value="<?php echo esc_attr( $values['_nnr_ticket_url'] ); ?>" class="regular-text" placeholder="https://" required />
						<p class="description"><?php esc_html_e( 'Where the ticket button on the front end will link to.', 'nnr-events' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="_nnr_button_text"><?php esc_html_e( 'Button Text', 'nnr-events' ); ?></label></th>
					<td>
						<input type="text" id="_nnr_button_text" name="_nnr_button_text" value="<?php echo esc_attr( $values['_nnr_button_text'] ); ?>" class="regular-text" placeholder="Get Tickets" />
						<p class="description"><?php esc_html_e( 'Leave blank to use the default "Get Tickets" label.', 'nnr-events' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>
		<script>
		( function () {
			var startInput = document.getElementById( '_nnr_start_date' );
			var endInput = document.getElementById( '_nnr_end_date' );
			if ( ! startInput || ! endInput ) {
				return;
			}

			var userEditedEnd = !! endInput.value;
			var syncing = false;

			endInput.addEventListener( 'input', function () {
				if ( syncing ) {
					return;
				}
				userEditedEnd = true;
			} );

			startInput.addEventListener( 'change', function () {
				if ( userEditedEnd ) {
					return;
				}
				syncing = true;
				endInput.value = startInput.value;
				syncing = false;
			} );
		} )();

		( function () {
			var toggle = document.getElementById( '_nnr_multi_session' );
			var singleRows = [
				document.getElementById( 'nnr_single_dates_row' ),
				document.getElementById( 'nnr_single_end_dates_row' ),
			];
			var sessionsRow = document.getElementById( 'nnr_sessions_row' );
			var sessionsBody = document.querySelector( '#nnr_sessions_table tbody' );
			var addBtn = document.getElementById( 'nnr_session_add' );
			if ( ! toggle || ! sessionsRow || ! sessionsBody || ! addBtn ) {
				return;
			}

			function applyToggle() {
				var on = toggle.checked;
				singleRows.forEach( function ( row ) {
					if ( row ) {
						row.hidden = on;
					}
				} );
				sessionsRow.hidden = ! on;
				document.getElementById( '_nnr_start_date' ).required = ! on;
			}

			toggle.addEventListener( 'change', applyToggle );
			applyToggle();

			function nextIndex() {
				var rows = sessionsBody.querySelectorAll( '.nnr-session-row' );
				var max = -1;
				rows.forEach( function ( row ) {
					var input = row.querySelector( 'input[name*="[date]"]' );
					var match = input && input.name.match( /\[(\d+)\]/ );
					if ( match ) {
						max = Math.max( max, parseInt( match[ 1 ], 10 ) );
					}
				} );
				return max + 1;
			}

			addBtn.addEventListener( 'click', function () {
				var i = nextIndex();
				var tr = document.createElement( 'tr' );
				tr.className = 'nnr-session-row';
				tr.innerHTML =
					'<td><input type="date" name="_nnr_sessions[' + i + '][date]" /></td>' +
					'<td><input type="time" name="_nnr_sessions[' + i + '][start_time]" /></td>' +
					'<td><input type="time" name="_nnr_sessions[' + i + '][end_time]" /></td>' +
					'<td><button type="button" class="button nnr-session-remove"><?php echo esc_js( __( 'Remove', 'nnr-events' ) ); ?></button></td>';
				sessionsBody.appendChild( tr );
			} );

			sessionsBody.addEventListener( 'click', function ( e ) {
				var btn = e.target.closest( '.nnr-session-remove' );
				if ( ! btn ) {
					return;
				}
				var rows = sessionsBody.querySelectorAll( '.nnr-session-row' );
				if ( rows.length <= 1 ) {
					btn.closest( '.nnr-session-row' ).querySelectorAll( 'input' ).forEach( function ( input ) {
						input.value = '';
					} );
					return;
				}
				btn.closest( '.nnr-session-row' ).remove();
			} );
		} )();
		</script>
		<?php
	}

	public function save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( $_POST[ self::NONCE_NAME ], self::NONCE_ACTION ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$is_full_form = isset( $_POST['_nnr_full_form'] );

		// For a multi-session event, these four are derived from the
		// sessions list (see save_sessions()) rather than directly editable.
		// Quick Edit has no sessions UI, so quick-editing them there would
		// just desync from the real per-day times — skip in that case.
		$derived_date_keys = array( '_nnr_start_date', '_nnr_start_time', '_nnr_end_date', '_nnr_end_time' );
		$is_multi_session  = '1' === get_post_meta( $post_id, '_nnr_multi_session', true );

		foreach ( $this->fields as $key => $field ) {
			if ( ! $is_full_form && $is_multi_session && in_array( $key, $derived_date_keys, true ) ) {
				continue;
			}
			if ( 'checkbox' === $field['type'] ) {
				// Only the full meta box submits every checkbox, so an absent
				// one there really does mean "unchecked". Quick Edit posts a
				// smaller subset of fields — an absent checkbox there just
				// means it wasn't part of that form, not that it was cleared.
				if ( $is_full_form ) {
					update_post_meta( $post_id, $key, isset( $_POST[ $key ] ) ? '1' : '0' );
				}
				continue;
			}

			if ( ! isset( $_POST[ $key ] ) ) {
				continue;
			}

			$raw = wp_unslash( $_POST[ $key ] );

			switch ( $field['type'] ) {
				case 'date':
					$value = $this->sanitize_date( $raw );
					break;
				case 'time':
					$value = $this->sanitize_time( $raw );
					break;
				case 'url':
					$value = esc_url_raw( $raw );
					break;
				case 'textarea':
					$value = sanitize_textarea_field( $raw );
					break;
				case 'richtext':
					$value = wp_kses_post( $raw );
					break;
				default:
					$value = sanitize_text_field( $raw );
			}

			update_post_meta( $post_id, $key, $value );
		}

		// Sessions are only editable via the full meta box (Quick Edit has no
		// repeater for them) — skip so a Quick Edit save can't wipe them out.
		if ( $is_full_form ) {
			$this->save_sessions( $post_id );
		}
	}

	/**
	 * Saves the per-day time slots, then — when "Different Times Each Day" is
	 * on — overwrites the single start/end date/time meta with the earliest
	 * and latest session so expiry checks, sorting, and structured data
	 * (which all read those flat fields) stay correct without needing to
	 * know sessions exist at all.
	 */
	private function save_sessions( $post_id ) {
		$multi_session = '1' === get_post_meta( $post_id, '_nnr_multi_session', true );

		$raw_sessions = isset( $_POST['_nnr_sessions'] ) && is_array( $_POST['_nnr_sessions'] ) ? wp_unslash( $_POST['_nnr_sessions'] ) : array();

		$sessions = array();
		foreach ( $raw_sessions as $row ) {
			$date = $this->sanitize_date( isset( $row['date'] ) ? $row['date'] : '' );
			if ( '' === $date ) {
				continue;
			}
			$start_time = $this->sanitize_time( isset( $row['start_time'] ) ? $row['start_time'] : '' );
			if ( '' === $start_time ) {
				continue;
			}
			$sessions[] = array(
				'date'       => $date,
				'start_time' => $start_time,
				'end_time'   => $this->sanitize_time( isset( $row['end_time'] ) ? $row['end_time'] : '' ),
			);
		}

		usort(
			$sessions,
			function ( $a, $b ) {
				return strcmp( $a['date'] . $a['start_time'], $b['date'] . $b['start_time'] );
			}
		);

		update_post_meta( $post_id, '_nnr_sessions', $sessions );

		if ( ! $multi_session || empty( $sessions ) ) {
			return;
		}

		$first = $sessions[0];
		$last  = $sessions[ count( $sessions ) - 1 ];

		update_post_meta( $post_id, '_nnr_start_date', $first['date'] );
		update_post_meta( $post_id, '_nnr_start_time', $first['start_time'] );
		update_post_meta( $post_id, '_nnr_end_date', $last['date'] );
		update_post_meta( $post_id, '_nnr_end_time', $last['end_time'] );
	}

	private function sanitize_date( $value ) {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		$d = DateTime::createFromFormat( 'Y-m-d', $value );
		return ( $d && $d->format( 'Y-m-d' ) === $value ) ? $value : '';
	}

	private function sanitize_time( $value ) {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		$d = DateTime::createFromFormat( 'H:i', $value );
		return ( $d && $d->format( 'H:i' ) === $value ) ? $value : '';
	}
}
