<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API for the Events Hopper (a separate app where events found by an
 * agent are reviewed and approved). Authenticated with a WordPress
 * Application Password, so every request runs as a real user and normal
 * capabilities apply.
 *
 *   GET  /nnr-events/v1/ping        auth check
 *   GET  /nnr-events/v1/categories  event categories (slug, name)
 *   GET  /nnr-events/v1/events      existing upcoming events, for dedup
 *   POST /nnr-events/v1/events      create or update one event
 *
 * Writes go through NNR_Meta_Box::apply_fields(), the same code the admin
 * forms use, so a Hopper event is indistinguishable from a hand-entered one.
 */
class NNR_REST {

	const NS = 'nnr-events/v1';

	/** Meta set on events created through this API. */
	const META_HOPPER_ID  = '_nnr_hopper_id';
	const META_SOURCE_URL = '_nnr_source_url';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			self::NS,
			'/ping',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'ping' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);

		register_rest_route(
			self::NS,
			'/categories',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_categories' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);

		register_rest_route(
			self::NS,
			'/events',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_events' ),
					'permission_callback' => array( $this, 'can_edit' ),
					'args'                => array(
						'after' => array(
							'type'    => 'string',
							'default' => '',
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'upsert_event' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
			)
		);
	}

	public function can_edit() {
		return current_user_can( 'edit_posts' );
	}

	public function ping() {
		$user = wp_get_current_user();
		return array(
			'ok'          => true,
			'user'        => $user->user_login,
			'can_publish' => current_user_can( 'publish_posts' ),
			'version'     => NNR_EVENTS_VERSION,
		);
	}

	public function list_categories() {
		$terms = get_terms(
			array(
				'taxonomy'   => NNR_Taxonomy::TAXONOMY,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}
		return array_map(
			function ( $t ) {
				return array(
					'id'   => $t->term_id,
					'slug' => $t->slug,
					'name' => $t->name,
				);
			},
			$terms
		);
	}

	/**
	 * Events whose sort date is on/after `after` (default today), in any
	 * non-trashed status, so the Hopper can skip what's already on the site.
	 */
	public function list_events( WP_REST_Request $request ) {
		$after = NNR_Meta_Box::sanitize_date( $request->get_param( 'after' ) );
		if ( '' === $after ) {
			$after = current_time( 'Y-m-d' );
		}

		$query = new WP_Query(
			array(
				'post_type'      => 'event',
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => 500,
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'     => '_nnr_sort_date',
						'value'   => $after,
						'compare' => '>=',
						'type'    => 'DATE',
					),
				),
			)
		);

		$out = array();
		foreach ( $query->posts as $post ) {
			$out[] = $this->event_summary( $post->ID );
		}
		return $out;
	}

	/**
	 * Body (JSON):
	 *   hopper_id      string  required. The Hopper's own id; makes retries idempotent.
	 *   title          string  required
	 *   days           [{date: Y-m-d, start_time?: H:i, end_time?: H:i}]  required, >= 1
	 *   runs_through   Y-m-d   optional (single-day events spanning several days)
	 *   recurring_weekly bool
	 *   description    string  (HTML allowed, kses'd)
	 *   venue, address, price, button_text  string
	 *   ticket_url, image_url, source_url   string (URL)
	 *   categories     [slug]  unknown slugs are created
	 *   status         publish|draft|pending  (default publish)
	 */
	public function upsert_event( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'nnr_bad_body', 'Expected a JSON body.', array( 'status' => 400 ) );
		}

		$hopper_id = isset( $body['hopper_id'] ) ? sanitize_text_field( (string) $body['hopper_id'] ) : '';
		$title     = isset( $body['title'] ) ? sanitize_text_field( (string) $body['title'] ) : '';
		$days      = isset( $body['days'] ) && is_array( $body['days'] ) ? array_values( $body['days'] ) : array();

		if ( '' === $hopper_id || '' === $title ) {
			return new WP_Error( 'nnr_missing', 'hopper_id and title are required.', array( 'status' => 400 ) );
		}

		$valid_days = array_filter(
			$days,
			function ( $d ) {
				return is_array( $d ) && '' !== NNR_Meta_Box::sanitize_date( isset( $d['date'] ) ? $d['date'] : '' );
			}
		);
		if ( empty( $valid_days ) ) {
			return new WP_Error( 'nnr_no_dates', 'At least one day with a valid date (Y-m-d) is required.', array( 'status' => 400 ) );
		}

		$status = isset( $body['status'] ) ? (string) $body['status'] : 'publish';
		if ( ! in_array( $status, array( 'publish', 'draft', 'pending' ), true ) ) {
			$status = 'publish';
		}
		if ( 'publish' === $status && ! current_user_can( 'publish_posts' ) ) {
			$status = 'pending';
		}

		$source_url = isset( $body['source_url'] ) ? esc_url_raw( (string) $body['source_url'] ) : '';

		$existing_id = $this->find_existing( $hopper_id, $source_url, NNR_Meta_Box::sanitize_date( reset( $valid_days )['date'] ) );
		if ( $existing_id && ! current_user_can( 'edit_post', $existing_id ) ) {
			return new WP_Error( 'nnr_forbidden', 'You cannot edit the matching event.', array( 'status' => 403 ) );
		}

		// Status is applied last, after the meta is written, so anything hooked
		// to publishing (caches, feeds) never sees a dateless event. A new
		// event starts as a draft until then.
		$postarr = array(
			'post_type'  => 'event',
			'post_title' => $title,
		);
		if ( $existing_id ) {
			$postarr['ID'] = $existing_id;
			$result        = wp_update_post( wp_slash( $postarr ), true );
		} else {
			$postarr['post_status'] = 'draft';
			$result                 = wp_insert_post( wp_slash( $postarr ), true );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$post_id = (int) $result;

		$str = function ( $key ) use ( $body ) {
			return isset( $body[ $key ] ) && is_scalar( $body[ $key ] ) ? (string) $body[ $key ] : '';
		};

		$ticket_url  = $str( 'ticket_url' );
		$button_text = $str( 'button_text' );
		if ( '' === $button_text && '' !== $ticket_url ) {
			$button_text = 'Website';
		}

		$input = array(
			'_nnr_sessions'         => array_map(
				function ( $d ) {
					return array(
						'date'       => isset( $d['date'] ) ? (string) $d['date'] : '',
						'start_time' => isset( $d['start_time'] ) ? (string) $d['start_time'] : '',
						'end_time'   => isset( $d['end_time'] ) ? (string) $d['end_time'] : '',
					);
				},
				$valid_days
			),
			'_nnr_runs_through'     => $str( 'runs_through' ),
			'_nnr_recurring_weekly' => ! empty( $body['recurring_weekly'] ) ? '1' : '',
			'_nnr_description'      => $str( 'description' ),
			'_nnr_image_url'        => $str( 'image_url' ),
			'_nnr_venue'            => $str( 'venue' ),
			'_nnr_address'          => $str( 'address' ),
			'_nnr_price'            => '' !== $str( 'price' ) ? $str( 'price' ) : 'Free',
			'_nnr_ticket_url'       => $ticket_url,
			'_nnr_button_text'      => $button_text,
		);

		// Also refreshes _nnr_sort_date.
		NNR_Meta_Box::apply_fields( $post_id, $input, true );
		update_post_meta( $post_id, self::META_HOPPER_ID, $hopper_id );
		if ( '' !== $source_url ) {
			update_post_meta( $post_id, self::META_SOURCE_URL, $source_url );
		}

		$slugs = isset( $body['categories'] ) && is_array( $body['categories'] ) ? $body['categories'] : array();
		$term_ids = $this->resolve_categories( $slugs );
		if ( ! empty( $term_ids ) ) {
			wp_set_object_terms( $post_id, $term_ids, NNR_Taxonomy::TAXONOMY );
		}

		if ( get_post_status( $post_id ) !== $status ) {
			$result = wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => $status,
				),
				true
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		$response = $this->event_summary( $post_id );
		$response['action'] = $existing_id ? 'updated' : 'created';
		return rest_ensure_response( $response );
	}

	/**
	 * Same Hopper id first (a retry or a re-approval after editing), then the
	 * same source page + start date (the event was entered some other way).
	 */
	private function find_existing( $hopper_id, $source_url, $start_date ) {
		$statuses = array( 'publish', 'draft', 'pending', 'future', 'private' );

		$ids = get_posts(
			array(
				'post_type'      => 'event',
				'post_status'    => $statuses,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::META_HOPPER_ID,
				'meta_value'     => $hopper_id,
			)
		);
		if ( $ids ) {
			return (int) $ids[0];
		}

		if ( '' === $source_url || '' === $start_date ) {
			return 0;
		}

		$ids = get_posts(
			array(
				'post_type'      => 'event',
				'post_status'    => $statuses,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => self::META_SOURCE_URL,
						'value' => $source_url,
					),
					array(
						'key'   => '_nnr_start_date',
						'value' => $start_date,
					),
				),
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	private function resolve_categories( array $slugs ) {
		$ids = array();
		foreach ( $slugs as $slug ) {
			$slug = sanitize_title( (string) $slug );
			if ( '' === $slug ) {
				continue;
			}
			// "events" is the old name for the general category (slug pasadena-events).
			// Never auto-create it as a second category.
			if ( 'events' === $slug ) {
				$slug = 'pasadena-events';
			}
			$term = get_term_by( 'slug', $slug, NNR_Taxonomy::TAXONOMY );
			if ( ! $term && current_user_can( 'manage_categories' ) ) {
				$created = wp_insert_term( ucwords( str_replace( '-', ' ', $slug ) ), NNR_Taxonomy::TAXONOMY, array( 'slug' => $slug ) );
				if ( ! is_wp_error( $created ) ) {
					$ids[] = (int) $created['term_id'];
				}
				continue;
			}
			if ( $term ) {
				$ids[] = (int) $term->term_id;
			}
		}
		return $ids;
	}

	private function event_summary( $post_id ) {
		$terms = wp_get_object_terms( $post_id, NNR_Taxonomy::TAXONOMY, array( 'fields' => 'slugs' ) );
		return array(
			'id'          => (int) $post_id,
			'title'       => get_the_title( $post_id ),
			'status'      => get_post_status( $post_id ),
			'start_date'  => (string) get_post_meta( $post_id, '_nnr_start_date', true ),
			'end_date'    => (string) get_post_meta( $post_id, '_nnr_end_date', true ),
			'venue'       => (string) get_post_meta( $post_id, '_nnr_venue', true ),
			'source_url'  => (string) get_post_meta( $post_id, self::META_SOURCE_URL, true ),
			'hopper_id'   => (string) get_post_meta( $post_id, self::META_HOPPER_ID, true ),
			'categories'  => is_wp_error( $terms ) ? array() : $terms,
			'edit_url'    => admin_url( 'post.php?post=' . (int) $post_id . '&action=edit' ),
		);
	}
}
