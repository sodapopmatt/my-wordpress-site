<?php
declare(strict_types=1);

namespace BeehiivSync\Sync;

use BeehiivSync\Settings\Schema;

/**
 * Validated parameters for one import invocation.
 *
 * Bundles together the request-level overrides (audience, status
 * filter) with the persisted defaults from settings, so the
 * importer doesn't have to consult both layers.
 */
final class ImportParams {

	/**
	 * @param string[]              $beehiiv_statuses  Filter sent to beehiiv: confirmed/draft/archived.
	 * @param array<string, mixed>  $defaults          The 'defaults' block from settings.
	 * @param string[]|null         $selected_ids      When set, only these beehiiv ids are imported
	 *                                                  (the user's preview selection). null = import all.
	 * @param string|null           $date_from         Optional time frame, Y-m-d in the site's timezone
	 * @param string|null           $date_to           (inclusive). null on both = all time.
	 */
	public function __construct(
		public readonly string $audience,
		public readonly array $beehiiv_statuses,
		public readonly int $per_page,
		public readonly array $defaults,
		public readonly ?array $selected_ids = null,
		public readonly ?string $date_from = null,
		public readonly ?string $date_to = null,
	) {}

	/**
	 * @param array<string, mixed> $input
	 * @param array<string, mixed> $defaults
	 */
	public static function build( array $input, array $defaults ): self {
		$audience = isset( $input['audience'] ) && in_array( $input['audience'], Schema::AUDIENCES, true )
			? $input['audience']
			: (string) ( $defaults['audience'] ?? 'all' );

		$valid    = [ 'draft', 'confirmed', 'archived' ];
		$statuses = isset( $input['beehiiv_statuses'] ) && is_array( $input['beehiiv_statuses'] )
			? array_values( array_intersect( $valid, $input['beehiiv_statuses'] ) )
			: [ 'confirmed' ];

		if ( $statuses === [] ) {
			$statuses = [ 'confirmed' ];
		}

		$per_page = isset( $input['per_page'] ) ? (int) $input['per_page'] : 50;
		$per_page = max( 1, min( 100, $per_page ) );

		$selected_ids = null;
		if ( isset( $input['selected_ids'] ) && is_array( $input['selected_ids'] ) ) {
			$selected_ids = array_values(
				array_filter(
					array_map( 'strval', $input['selected_ids'] ),
					static fn( string $id ): bool => $id !== ''
				)
			);
		}

		$date_from = self::sanitize_date( $input['date_from'] ?? null );
		$date_to   = self::sanitize_date( $input['date_to'] ?? null );
		if ( $date_from !== null && $date_to !== null && $date_from > $date_to ) {
			[ $date_from, $date_to ] = [ $date_to, $date_from ];
		}

		return new self( $audience, $statuses, $per_page, $defaults, $selected_ids, $date_from, $date_to );
	}

	private static function sanitize_date( mixed $value ): ?string {
		if ( ! is_string( $value ) || preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) !== 1 ) {
			return null;
		}
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $value : null;
	}

	public function has_date_range(): bool {
		return $this->date_from !== null || $this->date_to !== null;
	}

	/**
	 * Where a post's publish date falls relative to the time frame:
	 * 'in' (or always, with no time frame), 'newer', 'older', or 'undated'
	 * (no publish date, e.g. an unscheduled draft — only shown under all time).
	 */
	public function date_range_position( ?int $publish_date ): string {
		if ( ! $this->has_date_range() ) {
			return 'in';
		}
		if ( $publish_date === null || $publish_date <= 0 ) {
			return 'undated';
		}

		$tz = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
		if ( $this->date_from !== null && $publish_date < ( new \DateTimeImmutable( $this->date_from . ' 00:00:00', $tz ) )->getTimestamp() ) {
			return 'older';
		}
		if ( $this->date_to !== null && $publish_date > ( new \DateTimeImmutable( $this->date_to . ' 23:59:59', $tz ) )->getTimestamp() ) {
			return 'newer';
		}
		return 'in';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'audience'         => $this->audience,
			'beehiiv_statuses' => $this->beehiiv_statuses,
			'per_page'         => $this->per_page,
			'defaults'         => $this->defaults,
			'selected_ids'     => $this->selected_ids,
			'date_from'        => $this->date_from,
			'date_to'          => $this->date_to,
		];
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function from_array( array $data ): self {
		return new self(
			audience: (string) ( $data['audience'] ?? 'all' ),
			beehiiv_statuses: is_array( $data['beehiiv_statuses'] ?? null ) ? $data['beehiiv_statuses'] : [ 'confirmed' ],
			per_page: (int) ( $data['per_page'] ?? 50 ),
			defaults: is_array( $data['defaults'] ?? null ) ? $data['defaults'] : [],
			selected_ids: is_array( $data['selected_ids'] ?? null ) ? array_values( array_map( 'strval', $data['selected_ids'] ) ) : null,
			date_from: self::sanitize_date( $data['date_from'] ?? null ),
			date_to: self::sanitize_date( $data['date_to'] ?? null ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function api_query( string $status, int $page ): array {
		$query = [
			'page'      => $page,
			'limit'     => $this->per_page,
			'status'    => $status,
			'expand'    => $this->expand_for_audience(),
			// Stable, append-only ordering: oldest first so new posts created
			// mid-run land on the last page instead of shifting earlier pages
			// (which would otherwise make the same post appear on two pages).
			'order_by'  => 'created',
			'direction' => 'asc',
		];
		// With a time frame, newest-published first instead, so the walk can
		// stop at the first post older than the frame (see date_range_position()).
		if ( $this->has_date_range() ) {
			$query['order_by']  = 'publish_date';
			$query['direction'] = 'desc';
		}
		if ( $this->audience !== 'all' ) {
			$query['audience'] = $this->audience;
		}
		return $query;
	}

	/**
	 * Beehiiv requires explicit `expand` to return post bodies.
	 *
	 * @return string[]
	 */
	private function expand_for_audience(): array {
		return match ( $this->audience ) {
			'free'    => [ 'free_web_content' ],
			'premium' => [ 'premium_web_content' ],
			default   => [ 'free_web_content', 'premium_web_content' ],
		};
	}
}
