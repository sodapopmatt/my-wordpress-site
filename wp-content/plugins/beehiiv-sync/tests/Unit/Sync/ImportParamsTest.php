<?php
declare(strict_types=1);

namespace BeehiivSync\Tests\Unit\Sync;

use BeehiivSync\Settings\Schema;
use BeehiivSync\Sync\ImportParams;
use PHPUnit\Framework\TestCase;

final class ImportParamsTest extends TestCase {

	private function defaults(): array {
		return Schema::defaults()['defaults'];
	}

	public function test_unknown_audience_falls_back_to_settings_default(): void {
		$p = ImportParams::build( [ 'audience' => 'evil' ], $this->defaults() );
		self::assertSame( 'all', $p->audience );
	}

	public function test_known_audience_overrides_default(): void {
		$p = ImportParams::build( [ 'audience' => 'free' ], $this->defaults() );
		self::assertSame( 'free', $p->audience );
	}

	public function test_unknown_statuses_are_dropped(): void {
		$p = ImportParams::build(
			[ 'beehiiv_statuses' => [ 'confirmed', 'trash', 'draft' ] ],
			$this->defaults()
		);
		// Statuses are normalized to the canonical order (draft, confirmed,
		// archived), not the order the caller passed them in.
		self::assertSame( [ 'draft', 'confirmed' ], $p->beehiiv_statuses );
	}

	public function test_empty_status_list_falls_back_to_confirmed(): void {
		$p = ImportParams::build( [ 'beehiiv_statuses' => [] ], $this->defaults() );
		self::assertSame( [ 'confirmed' ], $p->beehiiv_statuses );
	}

	public function test_per_page_is_clamped(): void {
		self::assertSame( 100, ImportParams::build( [ 'per_page' => 999 ], $this->defaults() )->per_page );
		self::assertSame( 1, ImportParams::build( [ 'per_page' => 0 ], $this->defaults() )->per_page );
	}

	public function test_api_query_omits_audience_when_all(): void {
		$p     = ImportParams::build( [ 'audience' => 'all' ], $this->defaults() );
		$query = $p->api_query( 'confirmed', 2 );
		self::assertArrayNotHasKey( 'audience', $query );
		self::assertSame( 2, $query['page'] );
		self::assertSame( 'confirmed', $query['status'] );
	}

	public function test_api_query_includes_audience_when_filtered(): void {
		$p     = ImportParams::build( [ 'audience' => 'premium' ], $this->defaults() );
		$query = $p->api_query( 'confirmed', 1 );
		self::assertSame( 'premium', $query['audience'] );
	}

	public function test_api_query_requests_expand_for_audience_all(): void {
		$p     = ImportParams::build( [ 'audience' => 'all' ], $this->defaults() );
		$query = $p->api_query( 'confirmed', 1 );
		self::assertSame( [ 'free_web_content', 'premium_web_content' ], $query['expand'] );
	}

	public function test_api_query_requests_only_free_expand_for_free_audience(): void {
		$p     = ImportParams::build( [ 'audience' => 'free' ], $this->defaults() );
		$query = $p->api_query( 'confirmed', 1 );
		self::assertSame( [ 'free_web_content' ], $query['expand'] );
	}

	public function test_api_query_requests_only_premium_expand_for_premium_audience(): void {
		$p     = ImportParams::build( [ 'audience' => 'premium' ], $this->defaults() );
		$query = $p->api_query( 'confirmed', 1 );
		self::assertSame( [ 'premium_web_content' ], $query['expand'] );
	}

	public function test_round_trip_through_array(): void {
		$p1 = ImportParams::build( [ 'audience' => 'premium', 'beehiiv_statuses' => [ 'draft' ] ], $this->defaults() );
		$p2 = ImportParams::from_array( $p1->to_array() );
		self::assertSame( $p1->audience, $p2->audience );
		self::assertSame( $p1->beehiiv_statuses, $p2->beehiiv_statuses );
	}

	public function test_date_range_is_optional_and_validated(): void {
		$all = ImportParams::build( [], $this->defaults() );
		self::assertFalse( $all->has_date_range() );
		self::assertSame( 'created', $all->api_query( 'confirmed', 1 )['order_by'] );

		$bad = ImportParams::build( [ 'date_from' => '2026-02-30', 'date_to' => 'yesterday' ], $this->defaults() );
		self::assertFalse( $bad->has_date_range() );

		$swapped = ImportParams::build( [ 'date_from' => '2026-10-31', 'date_to' => '2026-10-01' ], $this->defaults() );
		self::assertSame( '2026-10-01', $swapped->date_from );
		self::assertSame( '2026-10-31', $swapped->date_to );
	}

	public function test_date_range_orders_newest_published_first_and_round_trips(): void {
		$p     = ImportParams::build( [ 'date_from' => '2026-10-01' ], $this->defaults() );
		$query = $p->api_query( 'confirmed', 1 );
		self::assertSame( 'publish_date', $query['order_by'] );
		self::assertSame( 'desc', $query['direction'] );

		$p2 = ImportParams::from_array( $p->to_array() );
		self::assertSame( '2026-10-01', $p2->date_from );
		self::assertNull( $p2->date_to );
	}

	public function test_date_range_position_is_inclusive_by_day(): void {
		// No wp_timezone() in unit tests, so bounds are UTC days.
		$p = ImportParams::build( [ 'date_from' => '2026-10-01', 'date_to' => '2026-10-31' ], $this->defaults() );
		self::assertSame( 'older', $p->date_range_position( strtotime( '2026-09-30 23:59:59 UTC' ) ) );
		self::assertSame( 'in', $p->date_range_position( strtotime( '2026-10-01 00:00:00 UTC' ) ) );
		self::assertSame( 'in', $p->date_range_position( strtotime( '2026-10-31 23:59:59 UTC' ) ) );
		self::assertSame( 'newer', $p->date_range_position( strtotime( '2026-11-01 00:00:00 UTC' ) ) );
		self::assertSame( 'undated', $p->date_range_position( null ) );

		$open = ImportParams::build( [ 'date_from' => '2026-10-01' ], $this->defaults() );
		self::assertSame( 'in', $open->date_range_position( strtotime( '2030-01-01 UTC' ) ) );
		self::assertSame( 'in', ImportParams::build( [], $this->defaults() )->date_range_position( null ) );
	}
}
