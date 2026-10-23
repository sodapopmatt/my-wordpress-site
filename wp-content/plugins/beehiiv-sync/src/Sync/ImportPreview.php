<?php
declare(strict_types=1);

namespace BeehiivSync\Sync;

use BeehiivSync\Api\Client;

/**
 * Synchronous dry-run of an import, one beehiiv page at a time.
 *
 * Fetches a single page of one beehiiv status, runs each post through the
 * same PostMapper the real importer uses, and reports what *would* happen
 * (new / update / unchanged / skip) without writing anything to WordPress.
 *
 * The preview screen calls this repeatedly, following the returned `next`
 * cursor, and merges the pages client-side. Keeping each REST request to a
 * single beehiiv call stops large publications from blowing the PHP / proxy
 * request timeout — walking every page in one request used to fail on cold
 * caches and only succeed on a retry.
 */
final class ImportPreview {

	public const ACTION_NEW       = 'new';
	public const ACTION_UPDATE    = 'update';
	public const ACTION_UNCHANGED = 'unchanged';
	public const ACTION_SKIP      = 'skip';

	public function __construct(
		private readonly Client $client,
		private readonly PostMapper $mapper,
		private readonly PostRepository $repository,
	) {}

	/**
	 * Preview one page of one beehiiv status.
	 *
	 * @return array{
	 *   items: array<int, array<string, mixed>>,
	 *   summary: array<string, int>,
	 *   status: string,
	 *   page: int,
	 *   total_pages: int,
	 *   total_results: int,
	 *   next: array{status: string, page: int}|null
	 * }
	 */
	public function build_page( ImportParams $params, string $beehiiv_status, int $page ): array {
		$items   = [];
		$summary = [
			self::ACTION_NEW       => 0,
			self::ACTION_UPDATE    => 0,
			self::ACTION_UNCHANGED => 0,
			self::ACTION_SKIP      => 0,
			'total'                => 0,
		];

		$result = $this->client->list_posts( $params->api_query( $beehiiv_status, $page ) );

		// With a time frame, posts arrive newest-published first; the first
		// one older than the frame means every later page is older too.
		$reached_older = false;

		foreach ( $result->posts as $post ) {
			$position = $params->date_range_position( $post->publish_date );
			if ( $position === 'older' ) {
				$reached_older = true;
			}
			if ( $position !== 'in' ) {
				continue;
			}

			$existing = $this->repository->find_existing(
				$post->id,
				$post->slug,
				(string) ( $params->defaults['post_type'] ?? 'post' )
			);
			$plan     = $this->mapper->plan( $post, $params->defaults, $existing );
			$action   = $this->classify( $plan );

			$summary[ $action ]++;
			$summary['total']++;

			// Only surface posts that would actually be imported (new or
			// update). Unchanged/skipped posts are reflected in the summary
			// counts but kept out of the selectable list.
			if ( $action !== self::ACTION_NEW && $action !== self::ACTION_UPDATE ) {
				continue;
			}

			$items[] = [
				'beehiiv_id'       => $post->id,
				'title'            => $post->title,
				'beehiiv_status'   => $post->status,
				'publish_date'     => $post->publish_date,
				'web_url'          => $post->web_url,
				'action'           => $action,
				'skip_reason'      => $plan->skip_reason,
				'existing_post_id' => $plan->existing_post_id,
				'target_status'    => $plan->post_args['post_status'] ?? null,
				'selectable'       => true,
			];
		}

		return [
			'items'         => $items,
			'summary'       => $summary,
			'status'        => $beehiiv_status,
			'page'          => $page,
			'total_pages'   => $result->total_pages,
			'total_results' => $result->total_results,
			'next'          => $this->next_cursor( $params, $beehiiv_status, $page, $reached_older ? $page : $result->total_pages ),
		];
	}

	/**
	 * @return array{status: string, page: int}|null  null when every page of every status has been seen.
	 */
	private function next_cursor( ImportParams $params, string $beehiiv_status, int $page, int $total_pages ): ?array {
		if ( $page < $total_pages ) {
			return [ 'status' => $beehiiv_status, 'page' => $page + 1 ];
		}

		$statuses = $params->beehiiv_statuses;
		$index    = array_search( $beehiiv_status, $statuses, true );
		if ( $index !== false && isset( $statuses[ $index + 1 ] ) ) {
			return [ 'status' => $statuses[ $index + 1 ], 'page' => 1 ];
		}

		return null;
	}

	private function classify( ImportPlan $plan ): string {
		if ( $plan->action === ImportPlan::ACTION_INSERT ) {
			return self::ACTION_NEW;
		}
		if ( $plan->action === ImportPlan::ACTION_UPDATE ) {
			return self::ACTION_UPDATE;
		}
		if ( $plan->skip_reason === 'unchanged' ) {
			return self::ACTION_UNCHANGED;
		}
		return self::ACTION_SKIP;
	}
}
