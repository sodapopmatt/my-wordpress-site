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
		'_nnr_recurring_weekly'=> array( 'label' => 'Recurring Weekly', 'type' => 'checkbox' ),
		'_nnr_venue'           => array( 'label' => 'Venue Name', 'type' => 'text' ),
		'_nnr_address'         => array( 'label' => 'Address', 'type' => 'text' ),
		'_nnr_price'           => array( 'label' => 'Price', 'type' => 'text' ),
		'_nnr_ticket_url'      => array( 'label' => 'Ticket / More Info URL', 'type' => 'url' ),
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

		$values = array();
		foreach ( $this->fields as $key => $field ) {
			$values[ $key ] = get_post_meta( $post->ID, $key, true );
		}
		?>
		<table class="form-table nnr-event-fields">
			<tbody>
				<tr>
					<th><label for="_nnr_start_date"><?php esc_html_e( 'Start Date', 'nnr-events' ); ?></label></th>
					<td>
						<input type="date" id="_nnr_start_date" name="_nnr_start_date" value="<?php echo esc_attr( $values['_nnr_start_date'] ); ?>" required />
						<input type="time" id="_nnr_start_time" name="_nnr_start_time" value="<?php echo esc_attr( $values['_nnr_start_time'] ); ?>" style="margin-left:8px;" />
					</td>
				</tr>
				<tr>
					<th><label for="_nnr_end_date"><?php esc_html_e( 'End Date', 'nnr-events' ); ?></label></th>
					<td>
						<input type="date" id="_nnr_end_date" name="_nnr_end_date" value="<?php echo esc_attr( $values['_nnr_end_date'] ); ?>" />
						<input type="time" id="_nnr_end_time" name="_nnr_end_time" value="<?php echo esc_attr( $values['_nnr_end_time'] ); ?>" style="margin-left:8px;" />
						<p class="description"><?php esc_html_e( 'Optional.', 'nnr-events' ); ?></p>
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
					<th><label for="_nnr_ticket_url"><?php esc_html_e( 'Ticket / More Info URL', 'nnr-events' ); ?></label></th>
					<td>
						<input type="url" id="_nnr_ticket_url" name="_nnr_ticket_url" value="<?php echo esc_attr( $values['_nnr_ticket_url'] ); ?>" class="regular-text" placeholder="https://" required />
						<p class="description"><?php esc_html_e( 'Where the "Get Tickets" button on the front end will link to.', 'nnr-events' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>
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

		foreach ( $this->fields as $key => $field ) {
			if ( 'checkbox' === $field['type'] ) {
				update_post_meta( $post_id, $key, isset( $_POST[ $key ] ) ? '1' : '0' );
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
				default:
					$value = sanitize_text_field( $raw );
			}

			update_post_meta( $post_id, $key, $value );
		}
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
