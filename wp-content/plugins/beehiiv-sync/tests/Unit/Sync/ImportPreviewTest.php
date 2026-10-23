<?php
declare(strict_types=1);

namespace BeehiivSync\Tests\Unit\Sync;

use BeehiivSync\Api\Client;
use BeehiivSync\Settings\Schema;
use BeehiivSync\Sync\ContentSanitizer;
use BeehiivSync\Sync\ImportParams;
use BeehiivSync\Sync\ImportPreview;
use BeehiivSync\Sync\PostMapper;
use BeehiivSync\Tests\Support\FakePostRepository;
use BeehiivSync\Tests\Support\FakeTransport;
use PHPUnit\Framework\TestCase;

final class ImportPreviewTest extends TestCase {

	private function preview( FakeTransport $transport, FakePostRepository $repo ): ImportPreview {
		$client = new Client( 'sk_test', 'pub_123', $transport, Client::BASE_URL, Client::MAX_RETRIES, static fn( int $s ) => null );
		return new ImportPreview( $client, new PostMapper( new ContentSanitizer() ), $repo );
	}

	private function params( array $statuses, array $extra = [] ): ImportParams {
		return ImportParams::build(
			[ 'beehiiv_statuses' => $statuses, 'per_page' => 2 ] + $extra,
			Schema::defaults()['defaults']
		);
	}

	/**
	 * @param string[]                $ids
	 * @param array<string, int|null> $dates  Publish timestamps by id (default 1700000000).
	 */
	private function page_response( int $page, int $total_pages, array $ids, array $dates = [] ): array {
		$data = array_map(
			static fn( string $id ): array => [
				'id'           => $id,
				'title'        => 'Title ' . $id,
				'slug'         => 'slug-' . $id,
				'status'       => 'confirmed',
				'audience'     => 'free',
				'publish_date' => array_key_exists( $id, $dates ) ? $dates[ $id ] : 1700000000,
				'content'      => [ 'free' => [ 'web' => '<p>' . $id . '</p>' ] ],
				'content_tags' => [],
			],
			$ids
		);

		return [
			'status' => 200,
			'body'   => (string) json_encode(
				[
					'page'          => $page,
					'total_pages'   => $total_pages,
					'total_results' => $total_pages * 2,
					'data'          => $data,
				]
			),
		];
	}

	public function test_fetches_only_the_requested_page(): void {
		$transport = new FakeTransport( [ $this->page_response( 2, 3, [ 'p3', 'p4' ] ) ] );

		$result = $this->preview( $transport, new FakePostRepository() )
			->build_page( $this->params( [ 'confirmed' ] ), 'confirmed', 2 );

		self::assertCount( 1, $transport->calls );
		self::assertStringContainsString( 'page=2', $transport->calls[0]['url'] );
		self::assertStringContainsString( 'status=confirmed', $transport->calls[0]['url'] );
		self::assertSame( [ 'p3', 'p4' ], array_column( $result['items'], 'beehiiv_id' ) );
		self::assertSame( 2, $result['summary']['new'] );
		self::assertSame( 3, $result['total_pages'] );
	}

	public function test_next_cursor_advances_page_then_status_then_ends(): void {
		// ImportParams normalizes statuses to canonical order: draft, then confirmed.
		$params = $this->params( [ 'confirmed', 'draft' ] );
		self::assertSame( [ 'draft', 'confirmed' ], $params->beehiiv_statuses );

		$mid = $this->preview( new FakeTransport( [ $this->page_response( 1, 2, [ 'a' ] ) ] ), new FakePostRepository() )
			->build_page( $params, 'draft', 1 );
		self::assertSame( [ 'status' => 'draft', 'page' => 2 ], $mid['next'] );

		$last_of_status = $this->preview( new FakeTransport( [ $this->page_response( 2, 2, [ 'b' ] ) ] ), new FakePostRepository() )
			->build_page( $params, 'draft', 2 );
		self::assertSame( [ 'status' => 'confirmed', 'page' => 1 ], $last_of_status['next'] );

		$final = $this->preview( new FakeTransport( [ $this->page_response( 1, 1, [ 'c' ] ) ] ), new FakePostRepository() )
			->build_page( $params, 'confirmed', 1 );
		self::assertNull( $final['next'] );
	}

	public function test_unchanged_posts_are_counted_but_not_listed(): void {
		$repo      = new FakePostRepository();
		$transport = new FakeTransport( [ $this->page_response( 1, 1, [ 'p1', 'p2' ] ) ] );
		$params    = $this->params( [ 'confirmed' ] );

		// Work out p1's real hash with a first pass, then seed it as already imported.
		$first = $this->preview( $transport, $repo )->build_page( $params, 'confirmed', 1 );
		self::assertSame( 2, $first['summary']['new'] );

		$mapper = new PostMapper( new ContentSanitizer() );
		$p1     = \BeehiivSync\Api\Dto\BeehiivPost::from_array( json_decode( $this->page_response( 1, 1, [ 'p1' ] )['body'], true )['data'][0] );
		$repo->seed( 'p1', 42, $mapper->plan( $p1, $params->defaults )->content_hash );

		$second = $this->preview( new FakeTransport( [ $this->page_response( 1, 1, [ 'p1', 'p2' ] ) ] ), $repo )
			->build_page( $params, 'confirmed', 1 );

		self::assertSame( 1, $second['summary']['unchanged'] );
		self::assertSame( 1, $second['summary']['new'] );
		self::assertSame( [ 'p2' ], array_column( $second['items'], 'beehiiv_id' ) );
	}

	public function test_date_range_filters_posts_and_stops_at_older_ones(): void {
		$params = $this->params( [ 'draft', 'confirmed' ], [ 'date_from' => '2026-10-01', 'date_to' => '2026-10-15' ] );
		$dates  = [
			'newer'   => strtotime( '2026-10-20 UTC' ),
			'in'      => strtotime( '2026-10-10 UTC' ),
			'undated' => null,
			'older'   => strtotime( '2026-09-20 UTC' ),
		];

		// Page 1 of 5, newest first: one post past the end date, one in range,
		// one undated, then one before the start date — so pages 2-5 are older.
		$transport = new FakeTransport( [ $this->page_response( 1, 5, array_keys( $dates ), $dates ) ] );
		$result    = $this->preview( $transport, new FakePostRepository() )->build_page( $params, 'draft', 1 );

		self::assertStringContainsString( 'order_by=publish_date', $transport->calls[0]['url'] );
		self::assertSame( [ 'in' ], array_column( $result['items'], 'beehiiv_id' ) );
		self::assertSame( 1, $result['summary']['total'] );
		self::assertSame( [ 'status' => 'confirmed', 'page' => 1 ], $result['next'] );
	}

	public function test_date_range_keeps_paging_while_posts_are_in_range(): void {
		$params    = $this->params( [ 'confirmed' ], [ 'date_from' => '2026-10-01' ] );
		$dates     = [ 'a' => strtotime( '2026-10-12 UTC' ), 'b' => strtotime( '2026-10-11 UTC' ) ];
		$transport = new FakeTransport( [ $this->page_response( 1, 3, [ 'a', 'b' ], $dates ) ] );

		$result = $this->preview( $transport, new FakePostRepository() )->build_page( $params, 'confirmed', 1 );

		self::assertSame( [ 'a', 'b' ], array_column( $result['items'], 'beehiiv_id' ) );
		self::assertSame( [ 'status' => 'confirmed', 'page' => 2 ], $result['next'] );
	}
}
