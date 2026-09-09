<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NNR_Shortcode_Page {

	const PAGE_SLUG = 'nnr-events-shortcode';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_page() {
		add_submenu_page(
			'edit.php?post_type=event',
			__( 'Shortcode', 'nnr-events' ),
			__( 'Shortcode', 'nnr-events' ),
			'edit_posts',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function enqueue_assets( $hook ) {
		if ( 'event_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
	}

	public function render_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$categories = get_terms(
			array(
				'taxonomy'   => NNR_Taxonomy::TAXONOMY,
				'hide_empty' => false,
			)
		);
		if ( ! is_array( $categories ) ) {
			$categories = array();
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Shortcode', 'nnr-events' ); ?></h1>

			<h2><?php esc_html_e( 'Reference', 'nnr-events' ); ?></h2>
			<table class="widefat striped" style="max-width:800px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Attribute', 'nnr-events' ); ?></th>
						<th><?php esc_html_e( 'Default', 'nnr-events' ); ?></th>
						<th><?php esc_html_e( 'Description', 'nnr-events' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>category</code></td>
						<td><em><?php esc_html_e( '(none)', 'nnr-events' ); ?></em></td>
						<td><?php esc_html_e( 'One or more Event Category slugs, comma-separated (e.g. "live-music,trivia" shows either). Leave blank to show all categories.', 'nnr-events' ); ?></td>
					</tr>
					<tr>
						<td><code>limit</code></td>
						<td>5</td>
						<td><?php esc_html_e( 'Maximum number of events to display.', 'nnr-events' ); ?></td>
					</tr>
					<tr>
						<td><code>columns</code></td>
						<td>3</td>
						<td><?php esc_html_e( 'Grid columns (1-4). Only affects grid layout.', 'nnr-events' ); ?></td>
					</tr>
					<tr>
						<td><code>layout</code></td>
						<td>grid</td>
						<td><?php esc_html_e( '"grid" or "list".', 'nnr-events' ); ?></td>
					</tr>
					<tr>
						<td><code>image</code></td>
						<td>show</td>
						<td><?php esc_html_e( '"show" or "hide" the event thumbnail.', 'nnr-events' ); ?></td>
					</tr>
					<tr>
						<td><code>color</code></td>
						<td>
							<?php
							printf(
								/* translators: %s: link to the Events settings page */
								esc_html__( '(%s)', 'nnr-events' ),
								'<a href="' . esc_url( admin_url( 'edit.php?post_type=event&page=' . NNR_Settings::PAGE_SLUG ) ) . '">' . esc_html__( 'site setting', 'nnr-events' ) . '</a>'
							);
							?>
						</td>
						<td><?php esc_html_e( 'Hex accent color override for just this listing, e.g. #2563eb.', 'nnr-events' ); ?></td>
					</tr>
					<tr>
						<td><code>filter</code></td>
						<td>none</td>
						<td><?php esc_html_e( '"pills" adds clickable category pills above the listing that filter it live, without a page reload.', 'nnr-events' ); ?></td>
					</tr>
				</tbody>
			</table>
			<p class="description">
				<?php esc_html_e( 'Listings always show only upcoming events: anything marked Recurring Weekly, or anything with a start date today or later.', 'nnr-events' ); ?>
			</p>

			<hr />

			<h2><?php esc_html_e( 'Generator', 'nnr-events' ); ?></h2>
			<table class="form-table" id="nnr-shortcode-generator">
				<tbody>
					<tr>
						<th><?php esc_html_e( 'Category', 'nnr-events' ); ?></th>
						<td>
							<fieldset id="nnr-gen-category">
								<?php if ( empty( $categories ) ) : ?>
									<p class="description"><?php esc_html_e( 'No categories yet.', 'nnr-events' ); ?></p>
								<?php else : ?>
									<?php foreach ( $categories as $term ) : ?>
										<label style="display:block;margin-bottom:4px;">
											<input type="checkbox" class="nnr-gen-category-option" value="<?php echo esc_attr( $term->slug ); ?>" />
											<?php echo esc_html( $term->name ); ?>
										</label>
									<?php endforeach; ?>
								<?php endif; ?>
								<p class="description"><?php esc_html_e( 'Leave all unchecked to show every category.', 'nnr-events' ); ?></p>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th><label for="nnr-gen-limit"><?php esc_html_e( 'Limit', 'nnr-events' ); ?></label></th>
						<td><input type="number" id="nnr-gen-limit" value="5" min="1" step="1" class="small-text" /></td>
					</tr>
					<tr>
						<th><label for="nnr-gen-layout"><?php esc_html_e( 'Layout', 'nnr-events' ); ?></label></th>
						<td>
							<select id="nnr-gen-layout">
								<option value="grid"><?php esc_html_e( 'Grid', 'nnr-events' ); ?></option>
								<option value="list"><?php esc_html_e( 'List', 'nnr-events' ); ?></option>
							</select>
						</td>
					</tr>
					<tr id="nnr-gen-columns-row">
						<th><label for="nnr-gen-columns"><?php esc_html_e( 'Columns', 'nnr-events' ); ?></label></th>
						<td><input type="number" id="nnr-gen-columns" value="3" min="1" max="4" step="1" class="small-text" /></td>
					</tr>
					<tr>
						<th><label for="nnr-gen-image"><?php esc_html_e( 'Image', 'nnr-events' ); ?></label></th>
						<td>
							<select id="nnr-gen-image">
								<option value="show"><?php esc_html_e( 'Show', 'nnr-events' ); ?></option>
								<option value="hide"><?php esc_html_e( 'Hide', 'nnr-events' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="nnr-gen-color"><?php esc_html_e( 'Color Override', 'nnr-events' ); ?></label></th>
						<td>
							<input type="text" id="nnr-gen-color" class="nnr-color-picker" data-default-color="" value="" />
							<p class="description"><?php esc_html_e( 'Optional. Leave empty to use the site-wide accent color.', 'nnr-events' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Filter Pills', 'nnr-events' ); ?></th>
						<td>
							<label>
								<input type="checkbox" id="nnr-gen-filter" />
								<?php esc_html_e( 'Show clickable category pills above the listing', 'nnr-events' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Shortcode', 'nnr-events' ); ?></th>
						<td>
							<input type="text" id="nnr-gen-output" readonly class="large-text code" style="max-width:500px;" value="[nnr_events]" />
							<button type="button" class="button" id="nnr-gen-copy"><?php esc_html_e( 'Copy', 'nnr-events' ); ?></button>
							<span id="nnr-gen-copied" style="display:none;color:#2271b1;margin-left:6px;"><?php esc_html_e( 'Copied!', 'nnr-events' ); ?></span>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<script>
		jQuery(function ($) {
			$('.nnr-color-picker').wpColorPicker({
				change: function () { setTimeout(updateShortcode, 10); },
				clear: updateShortcode
			});

			function updateShortcode() {
				var parts = [];

				var categories = $('.nnr-gen-category-option:checked').map(function () {
					return this.value;
				}).get();
				if (categories.length) { parts.push('category="' + categories.join(',') + '"'); }

				var limit = parseInt($('#nnr-gen-limit').val(), 10);
				if (limit && limit !== 5) { parts.push('limit="' + limit + '"'); }

				var layout = $('#nnr-gen-layout').val();
				if (layout && layout !== 'grid') { parts.push('layout="' + layout + '"'); }

				if (layout !== 'list') {
					var columns = parseInt($('#nnr-gen-columns').val(), 10);
					if (columns && columns !== 3) { parts.push('columns="' + columns + '"'); }
					$('#nnr-gen-columns-row').show();
				} else {
					$('#nnr-gen-columns-row').hide();
				}

				var image = $('#nnr-gen-image').val();
				if (image && image !== 'show') { parts.push('image="' + image + '"'); }

				var color = $('#nnr-gen-color').val();
				if (color) { parts.push('color="' + color + '"'); }

				if ($('#nnr-gen-filter').is(':checked')) { parts.push('filter="pills"'); }

				var shortcode = parts.length ? '[nnr_events ' + parts.join(' ') + ']' : '[nnr_events]';
				$('#nnr-gen-output').val(shortcode);
			}

			$('#nnr-shortcode-generator').on('input change', 'select, input[type="number"], input[type="checkbox"]', updateShortcode);

			$('#nnr-gen-copy').on('click', function () {
				var input = document.getElementById('nnr-gen-output');
				input.select();
				input.setSelectionRange(0, 99999);

				var done = function () {
					$('#nnr-gen-copied').show().delay(1200).fadeOut();
				};

				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(input.value).then(done);
				} else {
					document.execCommand('copy');
					done();
				}
			});

			updateShortcode();
		});
		</script>
		<?php
	}
}
