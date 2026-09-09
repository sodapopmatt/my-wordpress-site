<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NNR_Shortcode {

	public function __construct() {
		add_shortcode( 'nnr_events', array( $this, 'render' ) );
	}

	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'category' => '',
				'limit'    => 5,
				'columns'  => 3,
				'layout'   => 'grid',
				'image'    => 'show',
				'color'    => '',
			),
			$atts,
			'nnr_events'
		);

		$limit   = max( 1, (int) $atts['limit'] );
		$columns = min( 4, max( 1, (int) $atts['columns'] ) );
		$layout  = ( 'list' === $atts['layout'] ) ? 'list' : 'grid';
		$atts['image'] = ( 'hide' === $atts['image'] ) ? 'hide' : 'show';
		$color   = sanitize_hex_color( $atts['color'] );

		$query_args = NNR_Query::get_upcoming_args(
			array(
				'posts_per_page' => $limit,
			)
		);

		if ( ! empty( $atts['category'] ) ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => NNR_Taxonomy::TAXONOMY,
					'field'    => 'slug',
					'terms'    => sanitize_title( $atts['category'] ),
				),
			);
		}

		$query = new WP_Query( $query_args );

		if ( ! $query->have_posts() ) {
			return '<p class="nnr-events__empty">' . esc_html__( 'No upcoming events right now — check back soon.', 'nnr-events' ) . '</p>';
		}

		$wrapper_class = 'nnr-events nnr-events--' . $layout;

		$style_props = array();
		if ( 'grid' === $layout ) {
			$style_props[] = sprintf( '--nnr-columns:%d', $columns );
		}
		if ( $color ) {
			$style_props[] = sprintf( '--nnr-accent:%s', $color );
		}
		$style = $style_props ? sprintf( ' style="%s;"', esc_attr( implode( ';', $style_props ) ) ) : '';

		$events_for_schema = array();

		ob_start();
		?>
		<div class="<?php echo esc_attr( $wrapper_class ); ?>"<?php echo $style; ?>>
			<?php
			while ( $query->have_posts() ) :
				$query->the_post();
				$event = $this->get_event_data( get_the_ID() );
				$events_for_schema[] = $event;
				include NNR_EVENTS_PATH . 'templates/parts/event-card.php';
			endwhile;
			wp_reset_postdata();
			?>
		</div>
		<?php
		$html = ob_get_clean();

		$html .= $this->render_schema( $events_for_schema );

		return $html;
	}

	private function get_event_data( $post_id ) {
		return array(
			'id'          => $post_id,
			'title'       => get_the_title( $post_id ),
			'permalink'   => '',
			'excerpt'     => get_the_excerpt( $post_id ),
			'image'       => get_the_post_thumbnail_url( $post_id, 'medium_large' ),
			'start_date'  => get_post_meta( $post_id, '_nnr_start_date', true ),
			'start_time'  => get_post_meta( $post_id, '_nnr_start_time', true ),
			'end_date'    => get_post_meta( $post_id, '_nnr_end_date', true ),
			'end_time'    => get_post_meta( $post_id, '_nnr_end_time', true ),
			'recurring'   => '1' === get_post_meta( $post_id, '_nnr_recurring_weekly', true ),
			'venue'       => get_post_meta( $post_id, '_nnr_venue', true ),
			'address'     => get_post_meta( $post_id, '_nnr_address', true ),
			'price'       => get_post_meta( $post_id, '_nnr_price', true ),
			'ticket_url'  => get_post_meta( $post_id, '_nnr_ticket_url', true ),
		);
	}

	/**
	 * Human-readable "Fri, Sep 12 - 8:00 PM" style label, or "Weekly - 7:00 PM" for recurring events.
	 */
	public static function format_date_label( $event ) {
		if ( $event['recurring'] ) {
			$label = __( 'Weekly', 'nnr-events' );
			if ( $event['start_date'] ) {
				$ts = strtotime( $event['start_date'] );
				if ( $ts ) {
					$label = date_i18n( 'l', $ts );
				}
			}
			if ( $event['start_time'] ) {
				$label .= ' · ' . self::format_time( $event['start_time'] );
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

		$label = date_i18n( 'D, M j', $ts );
		if ( $event['start_time'] ) {
			$label .= ' · ' . self::format_time( $event['start_time'] );
		}
		return $label;
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

			if ( $event['excerpt'] ) {
				$node['description'] = wp_strip_all_tags( $event['excerpt'] );
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
