<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NNR_Settings {

	const OPTION_NAME = 'nnr_events_accent_color';
	const PAGE_SLUG   = 'nnr-events-settings';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_settings_page() {
		add_submenu_page(
			'edit.php?post_type=event',
			__( 'Events Settings', 'nnr-events' ),
			__( 'Settings', 'nnr-events' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function register_setting() {
		register_setting(
			self::PAGE_SLUG,
			self::OPTION_NAME,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_color' ),
				'default'           => '',
			)
		);
	}

	public function sanitize_color( $value ) {
		$value = sanitize_hex_color( trim( (string) $value ) );
		return $value ? $value : '';
	}

	/**
	 * Default accent color, used when no site-wide setting or shortcode override is present.
	 */
	public static function get_default_color_fallback() {
		return '#a13a4c';
	}

	public function enqueue_assets( $hook ) {
		if ( 'event_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_add_inline_script(
			'wp-color-picker',
			'jQuery(function($){ $(".nnr-color-picker").wpColorPicker(); });'
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$color = get_option( self::OPTION_NAME, '' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Events Settings', 'nnr-events' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( self::PAGE_SLUG ); ?>
				<table class="form-table">
					<tbody>
						<tr>
							<th><label for="<?php echo esc_attr( self::OPTION_NAME ); ?>"><?php esc_html_e( 'Accent Color', 'nnr-events' ); ?></label></th>
							<td>
								<input
									type="text"
									id="<?php echo esc_attr( self::OPTION_NAME ); ?>"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>"
									value="<?php echo esc_attr( $color ); ?>"
									class="nnr-color-picker"
									data-default-color="<?php echo esc_attr( self::get_default_color_fallback() ); ?>"
								/>
								<p class="description">
									<?php esc_html_e( 'Used for dates, badges, and the ticket button across all event listings. A shortcode\'s color="#hex" attribute overrides this on that specific page.', 'nnr-events' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
