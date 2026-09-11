<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NNR_Shortcode {

	const AJAX_ACTION = 'nnr_load_more_events';
	const NONCE_ACTION = 'nnr_load_more';

	public function __construct() {
		add_shortcode( 'nnr_events', array( $this, 'render' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_load_more' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( $this, 'ajax_load_more' ) );
	}

	/**
	 * The event description lives in its own meta field (not the post
	 * excerpt) so it's never subject to WordPress's excerpt trimming/manual
	 * excerpt quirks. Events created before this field existed fall back to
	 * their content.
	 */
	public static function get_description( $post_id ) {
		$description = get_post_meta( $post_id, '_nnr_description', true );
		if ( '' !== $description ) {
			return $description;
		}
		return wp_strip_all_tags( get_the_content( '', false, $post_id ) );
	}

	/**
	 * Prefers a directly linked image (no Media Library upload needed) over
	 * the featured image, so events don't have to add to the media library.
	 */
	public static function get_image_url( $post_id ) {
		$url = get_post_meta( $post_id, '_nnr_image_url', true );
		if ( $url ) {
			return $url;
		}
		return get_the_post_thumbnail_url( $post_id, 'medium_large' );
	}

	private function normalize_atts( $atts ) {
		$atts = shortcode_atts(
			array(
				'category' => '',
				'limit'    => 5,
				'columns'  => 3,
				'layout'   => 'grid',
				'image'    => 'show',
				'color'    => '',
				'filter'   => 'none',
			),
			$atts,
			'nnr_events'
		);

		$atts['category'] = $this->parse_categories( $atts['category'] );
		$atts['limit']    = min( 20, max( 1, (int) $atts['limit'] ) );
		$atts['columns']  = min( 4, max( 1, (int) $atts['columns'] ) );
		$atts['layout']   = ( 'list' === $atts['layout'] ) ? 'list' : 'grid';
		$atts['image']    = ( 'hide' === $atts['image'] ) ? 'hide' : 'show';
		$atts['filter']   = ( 'pills' === $atts['filter'] ) ? 'pills' : 'none';

		$color = sanitize_hex_color( $atts['color'] );
		if ( ! $color ) {
			$color = get_option( NNR_Settings::OPTION_NAME, '' );
		}
		$atts['color'] = $color;

		return $atts;
	}

	/**
	 * Accepts a comma-separated list of category slugs (or a single slug)
	 * and returns a clean array of valid, non-empty slugs.
	 */
	private function parse_categories( $raw ) {
		$slugs = array();
		foreach ( explode( ',', (string) $raw ) as $slug ) {
			$slug = sanitize_title( trim( $slug ) );
			if ( $slug && ! in_array( $slug, $slugs, true ) ) {
				$slugs[] = $slug;
			}
		}
		return $slugs;
	}

	public function render( $atts ) {
		$atts  = $this->normalize_atts( $atts );
		$query = $this->run_query( $atts, 0 );

		if ( $query->have_posts() ) {
			list( $cards_html, $events_for_schema ) = $this->render_cards( $query, $atts );
			$has_more = $query->found_posts > $atts['limit'];
		} else {
			$cards_html        = $this->empty_state_html();
			$events_for_schema = array();
			$has_more          = false;
		}

		ob_start();
		?>
		<div class="nnr-events-wrap"<?php echo $this->wrapper_style( $atts ); ?>>
			<?php if ( 'pills' === $atts['filter'] ) : ?>
				<?php echo $this->render_filter_pills( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
			<div class="<?php echo esc_attr( $this->wrapper_class( $atts ) ); ?>">
				<?php echo $cards_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<div class="nnr-events__load-more-wrap"<?php echo $has_more ? '' : ' hidden'; ?>>
				<button
					type="button"
					class="nnr-events__load-more"
					data-offset="<?php echo esc_attr( $atts['limit'] ); ?>"
					data-limit="<?php echo esc_attr( $atts['limit'] ); ?>"
					data-category="<?php echo esc_attr( implode( ',', $atts['category'] ) ); ?>"
					data-layout="<?php echo esc_attr( $atts['layout'] ); ?>"
					data-image="<?php echo esc_attr( $atts['image'] ); ?>"
					data-color="<?php echo esc_attr( $atts['color'] ); ?>"
					data-nonce="<?php echo esc_attr( wp_create_nonce( self::NONCE_ACTION ) ); ?>"
					<?php echo $has_more ? '' : 'hidden'; ?>
				>
					<?php esc_html_e( 'Load More', 'nnr-events' ); ?>
				</button>
			</div>
		</div>
		<?php
		$html = ob_get_clean();

		$html .= $this->render_schema( $events_for_schema );

		return $html;
	}

	/**
	 * Renders an "All" pill (resetting to whatever category restriction the
	 * shortcode/block itself was given) plus one pill per category. If the
	 * shortcode already restricts to specific categories, only those are
	 * offered — "All" means "all of the ones this listing allows", not
	 * every category on the site.
	 */
	private function render_filter_pills( $atts ) {
		if ( ! empty( $atts['category'] ) ) {
			$terms = array();
			foreach ( $atts['category'] as $slug ) {
				$term = get_term_by( 'slug', $slug, NNR_Taxonomy::TAXONOMY );
				if ( $term ) {
					$terms[] = $term;
				}
			}
		} else {
			$terms = get_terms(
				array(
					'taxonomy'   => NNR_Taxonomy::TAXONOMY,
					'hide_empty' => true,
				)
			);
			if ( ! is_array( $terms ) ) {
				$terms = array();
			}
		}

		if ( empty( $terms ) ) {
			return '';
		}

		$all_value = implode( ',', $atts['category'] );

		ob_start();
		?>
		<div class="nnr-events__filters" role="group" aria-label="<?php esc_attr_e( 'Filter events by category', 'nnr-events' ); ?>">
			<button type="button" class="nnr-events__filter-pill is-active" data-category="<?php echo esc_attr( $all_value ); ?>" aria-pressed="true">
				<?php esc_html_e( 'All', 'nnr-events' ); ?>
			</button>
			<?php foreach ( $terms as $term ) : ?>
				<button type="button" class="nnr-events__filter-pill" data-category="<?php echo esc_attr( $term->slug ); ?>" aria-pressed="false">
					<?php echo esc_html( $term->name ); ?>
				</button>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private function empty_state_html() {
		return '<p class="nnr-events__empty">' . esc_html__( 'No upcoming events right now — check back soon.', 'nnr-events' ) . '</p>';
	}

	public function ajax_load_more() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$atts = $this->normalize_atts(
			array(
				'category' => isset( $_POST['category'] ) ? wp_unslash( $_POST['category'] ) : '',
				'layout'   => isset( $_POST['layout'] ) ? wp_unslash( $_POST['layout'] ) : 'grid',
				'image'    => isset( $_POST['image'] ) ? wp_unslash( $_POST['image'] ) : 'show',
				'color'    => isset( $_POST['color'] ) ? wp_unslash( $_POST['color'] ) : '',
				'limit'    => isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 5,
			)
		);

		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;

		$query = $this->run_query( $atts, $offset );

		if ( ! $query->have_posts() ) {
			wp_send_json_success(
				array(
					'html'     => 0 === $offset ? $this->empty_state_html() : '',
					'has_more' => false,
				)
			);
		}

		list( $cards_html, $events_for_schema ) = $this->render_cards( $query, $atts );
		unset( $events_for_schema );

		wp_send_json_success(
			array(
				'html'     => $cards_html,
				'has_more' => $query->found_posts > ( $offset + $atts['limit'] ),
			)
		);
	}

	private function run_query( $atts, $offset ) {
		$query_args = NNR_Query::get_upcoming_args(
			array(
				'posts_per_page' => $atts['limit'],
				'offset'         => $offset,
			)
		);

		if ( ! empty( $atts['category'] ) ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => NNR_Taxonomy::TAXONOMY,
					'field'    => 'slug',
					'terms'    => $atts['category'],
				),
			);
		}

		return new WP_Query( $query_args );
	}

	/**
	 * Renders each post in $query through the event-card template.
	 *
	 * @return array [ string $html, array $events_for_schema ]
	 */
	private function render_cards( $query, $atts ) {
		$events_for_schema = array();

		ob_start();
		while ( $query->have_posts() ) :
			$query->the_post();
			$event = $this->get_event_data( get_the_ID() );
			$events_for_schema[] = $event;
			include NNR_EVENTS_PATH . 'templates/parts/event-card.php';
		endwhile;
		wp_reset_postdata();
		$html = ob_get_clean();

		return array( $html, $events_for_schema );
	}

	private function wrapper_class( $atts ) {
		return 'nnr-events nnr-events--' . $atts['layout'];
	}

	private function wrapper_style( $atts ) {
		$style_props = array();
		if ( 'grid' === $atts['layout'] ) {
			$style_props[] = sprintf( '--nnr-columns:%d', $atts['columns'] );
		}
		if ( $atts['color'] ) {
			$style_props[] = sprintf( '--nnr-accent:%s', $atts['color'] );
		}
		return $style_props ? sprintf( ' style="%s;"', esc_attr( implode( ';', $style_props ) ) ) : '';
	}

	private function get_event_data( $post_id ) {
		$sessions      = get_post_meta( $post_id, '_nnr_sessions', true );
		$multi_session = '1' === get_post_meta( $post_id, '_nnr_multi_session', true ) && is_array( $sessions ) && ! empty( $sessions );

		return array(
			'id'            => $post_id,
			'title'         => get_the_title( $post_id ),
			'permalink'     => '',
			'description'   => self::get_description( $post_id ),
			'image'         => self::get_image_url( $post_id ),
			'start_date'    => get_post_meta( $post_id, '_nnr_start_date', true ),
			'start_time'    => get_post_meta( $post_id, '_nnr_start_time', true ),
			'end_date'      => get_post_meta( $post_id, '_nnr_end_date', true ),
			'end_time'      => get_post_meta( $post_id, '_nnr_end_time', true ),
			'recurring'     => '1' === get_post_meta( $post_id, '_nnr_recurring_weekly', true ),
			'multi_session' => $multi_session,
			'sessions'      => $multi_session ? $sessions : array(),
			'venue'         => get_post_meta( $post_id, '_nnr_venue', true ),
			'address'       => get_post_meta( $post_id, '_nnr_address', true ),
			'price'         => get_post_meta( $post_id, '_nnr_price', true ),
			'ticket_url'    => get_post_meta( $post_id, '_nnr_ticket_url', true ),
			'button_text'   => get_post_meta( $post_id, '_nnr_button_text', true ),
		);
	}

	/**
	 * Returns a ready-to-print HTML string (not plain text): "Saturday, Sep 5
	 * · 8:00 PM" style, or "Weekly · 7:00 PM" for recurring events. The day
	 * name always renders as two spans — full and abbreviated — so CSS can
	 * swap to the short form on narrow screens without the date/time/badge
	 * wrapping awkwardly mid-phrase.
	 */
	public static function format_date_label( $event ) {
		if ( $event['recurring'] ) {
			$label = esc_html__( 'Weekly', 'nnr-events' );
			if ( $event['start_date'] ) {
				$ts = strtotime( $event['start_date'] );
				if ( $ts ) {
					$label = self::day_markup( date_i18n( 'l', $ts ), date_i18n( 'D', $ts ) );
				}
			}
			if ( $event['start_time'] ) {
				$label .= ' · ' . esc_html( self::format_time( $event['start_time'] ) );
			}
			return $label;
		}

		if ( ! $event['start_date'] ) {
			return '';
		}

		$ts = strtotime( $event['start_date'] );
		if ( ! $ts ) {
			return '';
		}

		$label = self::day_markup( date_i18n( 'l', $ts ), date_i18n( 'D', $ts ) ) . esc_html( date_i18n( ', M j', $ts ) );
		if ( $event['start_time'] ) {
			$label .= ' · ' . esc_html( self::format_time( $event['start_time'] ) );
		}
		return $label;
	}

	/**
	 * "Runs through Sep 12" note for events spanning more than one day.
	 * Kept separate from format_date_label() (its own line on the card)
	 * rather than appended inline, since the date row already competes for
	 * space with the calendar icon and the "Weekly" badge.
	 */
	public static function format_date_range_note( $event ) {
		if ( $event['recurring'] || ! $event['start_date'] || ! $event['end_date'] ) {
			return '';
		}
		if ( $event['end_date'] === $event['start_date'] ) {
			return '';
		}

		$end_ts = strtotime( $event['end_date'] );
		if ( ! $end_ts ) {
			return '';
		}

		return sprintf(
			/* translators: %s: end date, e.g. "Sep 12" */
			esc_html__( 'Runs through %s', 'nnr-events' ),
			esc_html( date_i18n( 'M j', $end_ts ) )
		);
	}

	/**
	 * True for multi-day (non-recurring) events where today falls between
	 * the start and end date, inclusive.
	 */
	public static function is_ongoing( $event ) {
		if ( $event['recurring'] || ! $event['start_date'] || ! $event['end_date'] ) {
			return false;
		}
		if ( $event['end_date'] === $event['start_date'] ) {
			return false;
		}

		$today = current_time( 'Y-m-d' );
		return $event['start_date'] <= $today && $today <= $event['end_date'];
	}

	/**
	 * One line per day for multi-session events, e.g. "Fri, Sep 11 · 6:00 PM
	 * – 9:00 PM". Stacked (one per session) rather than joined into a single
	 * line, since that reads more clearly and avoids the wrapping problems
	 * a long combined string caused elsewhere on the card.
	 */
	public static function format_session_label( $session ) {
		$ts = strtotime( $session['date'] );
		if ( ! $ts ) {
			return '';
		}

		$label = esc_html( date_i18n( 'D, M j', $ts ) );

		if ( ! empty( $session['start_time'] ) ) {
			$label .= ' · ' . esc_html( self::format_time( $session['start_time'] ) );
			if ( ! empty( $session['end_time'] ) ) {
				$label .= '–' . esc_html( self::format_time( $session['end_time'] ) );
			}
		}

		return $label;
	}

	private static function day_markup( $full, $abbr ) {
		return '<span class="nnr-event-card__day-full">' . esc_html( $full ) . '</span>'
			. '<span class="nnr-event-card__day-abbr">' . esc_html( $abbr ) . '</span>';
	}

	private static function format_time( $time_24h ) {
		$ts = strtotime( $time_24h );
		return $ts ? date_i18n( 'g:i A', $ts ) : $time_24h;
	}

	private function render_schema( $events ) {
		$nodes = array();

		foreach ( $events as $event ) {
			if ( empty( $event['start_date'] ) || empty( $event['ticket_url'] ) ) {
				continue;
			}

			$start = $event['start_date'] . ( $event['start_time'] ? 'T' . $event['start_time'] . ':00' : '' );

			$node = array(
				'@type'     => 'Event',
				'name'      => $event['title'],
				'startDate' => $start,
				'url'       => $event['ticket_url'],
				'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
				'eventStatus' => 'https://schema.org/EventScheduled',
			);

			if ( $event['end_date'] ) {
				$node['endDate'] = $event['end_date'] . ( $event['end_time'] ? 'T' . $event['end_time'] . ':00' : '' );
			}

			if ( $event['venue'] || $event['address'] ) {
				$node['location'] = array(
					'@type'   => 'Place',
					'name'    => $event['venue'] ? $event['venue'] : $event['address'],
					'address' => $event['address'],
				);
			}

			if ( $event['image'] ) {
				$node['image'] = array( $event['image'] );
			}

			if ( $event['description'] ) {
				$node['description'] = wp_strip_all_tags( $event['description'] );
			}

			$offer = $this->price_to_offer( $event['price'], $event['ticket_url'] );
			if ( $offer ) {
				$node['offers'] = $offer;
			}

			$nodes[] = $node;
		}

		if ( empty( $nodes ) ) {
			return '';
		}

		$graph = array(
			'@context' => 'https://schema.org',
			'@graph'   => $nodes,
		);

		return "\n" . '<script type="application/ld+json">' . wp_json_encode( $graph, JSON_UNESCAPED_SLASHES ) . '</script>';
	}

	/**
	 * Only emit an Offer when the price string is unambiguous ("Free" or a clean "$N").
	 * Ranged/mixed strings like "$15-20" are left out rather than guessing.
	 */
	private function price_to_offer( $price, $url ) {
		$price = trim( (string) $price );

		if ( '' === $price ) {
			return null;
		}

		if ( 0 === strcasecmp( $price, 'free' ) ) {
			return array(
				'@type'         => 'Offer',
				'price'         => '0',
				'priceCurrency' => 'USD',
				'url'           => $url,
			);
		}

		if ( preg_match( '/^\$(\d+(?:\.\d{2})?)$/', $price, $m ) ) {
			return array(
				'@type'         => 'Offer',
				'price'         => $m[1],
				'priceCurrency' => 'USD',
				'url'           => $url,
			);
		}

		return null;
	}
}
