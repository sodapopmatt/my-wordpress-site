<?php
declare(strict_types=1);

namespace BeehiivSync\Rest;

use BeehiivSync\Api\Client;
use BeehiivSync\Api\Credentials;
use BeehiivSync\Api\Exceptions\ApiException;
use BeehiivSync\Api\Exceptions\AuthException;
use BeehiivSync\Api\Exceptions\RateLimitException;
use BeehiivSync\Api\WpHttpTransport;
use BeehiivSync\Settings\Options;
use BeehiivSync\Settings\Schema;
use BeehiivSync\Sync\ContentSanitizer;
use BeehiivSync\Sync\Importer;
use BeehiivSync\Sync\ImportParams;
use BeehiivSync\Sync\ImportPreview;
use BeehiivSync\Sync\ImportRun;
use BeehiivSync\Sync\ItemProcessor;
use BeehiivSync\Sync\PostMapper;
use BeehiivSync\Sync\RunRepository;
use BeehiivSync\Sync\WpPostRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class ImportController extends Controller {

	private const PREVIEW_PER_PAGE = 20;

	// Seconds of queued import work each progress check may do (see drive_queue()).
	private const QUEUE_SLICE_SECONDS = 10;

	public function __construct(
		private readonly Credentials $credentials,
		private readonly Options $options,
		private readonly RunRepository $runs,
	) {}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/import/preview',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'preview' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/import',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'start' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/import/(?P<run_id>[A-Za-z0-9_\-]+)',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'status' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'run_id' => [ 'type' => 'string', 'required' => true ],
				],
			]
		);
	}

	public function preview( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! $this->credentials->exists() ) {
			return new WP_Error( 'beehiiv_sync_no_credentials', 'Connect beehiiv credentials first.', [ 'status' => 400 ] );
		}

		// Each call previews a single beehiiv page; the client follows the
		// returned `next` cursor. Pages are kept small because expanded post
		// bodies make every page multi-MB and slow for beehiiv to serve.
		$params = $this->build_params( $request, [ 'per_page' => self::PREVIEW_PER_PAGE ] );
		$input  = $this->input( $request );

		$status = isset( $input['status'] ) && in_array( $input['status'], $params->beehiiv_statuses, true )
			? (string) $input['status']
			: $params->beehiiv_statuses[0];
		$page   = max( 1, (int) ( $input['page'] ?? 1 ) );

		$preview = new ImportPreview(
			$this->make_client(),
			new PostMapper( new ContentSanitizer() ),
			new WpPostRepository()
		);

		try {
			$result = $preview->build_page( $params, $status, $page );
		} catch ( AuthException $e ) {
			return new WP_Error( 'beehiiv_sync_auth', 'Beehiiv rejected your credentials. Reconnect them and try again.', [ 'status' => 401 ] );
		} catch ( RateLimitException $e ) {
			return new WP_Error( 'beehiiv_sync_rate_limited', 'Beehiiv rate limit hit while previewing. Try again shortly.', [ 'status' => 429 ] );
		} catch ( ApiException $e ) {
			$upstream = $e->status_code ?? 0;
			$message  = $upstream >= 500
				? sprintf( 'Beehiiv returned a server error (HTTP %d) while previewing. It may be temporarily overloaded — please wait a moment and try again.', $upstream )
				: $e->getMessage();
			return new WP_Error( 'beehiiv_sync_api', $message, [ 'status' => 502 ] );
		}

		return new WP_REST_Response( $result, 200 );
	}

	public function start( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! $this->credentials->exists() ) {
			return new WP_Error( 'beehiiv_sync_no_credentials', 'Connect beehiiv credentials first.', [ 'status' => 400 ] );
		}

		$params = $this->build_params( $request );

		$importer = new Importer(
			$this->make_client(),
			new ItemProcessor( new PostMapper( new ContentSanitizer() ), new WpPostRepository() ),
			$this->runs,
		);

		$run_id = $importer->start( $params );

		return new WP_REST_Response( [ 'run_id' => $run_id, 'status' => 'queued' ], 202 );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function input( WP_REST_Request $request ): array {
		$body  = $request->get_json_params();
		$input = is_array( $body ) ? $body : $request->get_params();
		return is_array( $input ) ? $input : [];
	}

	/**
	 * @param array<string, mixed> $overrides  Input keys forced by the server, e.g. preview page size.
	 */
	private function build_params( WP_REST_Request $request, array $overrides = [] ): ImportParams {
		$input = array_merge( $this->input( $request ), $overrides );

		$persisted_defaults = $this->options->all()['defaults'];
		$override_defaults  = is_array( $input['defaults'] ?? null ) ? $input['defaults'] : [];
		$merged_defaults    = Schema::sanitize_defaults( $persisted_defaults, $override_defaults );

		return ImportParams::build( $input, $merged_defaults );
	}

	private function make_client(): Client {
		return new Client(
			(string) $this->credentials->api_key(),
			(string) $this->credentials->publication_id(),
			new WpHttpTransport()
		);
	}

	public function status( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$run_id = (string) $request->get_param( 'run_id' );
		$run    = $this->runs->load( $run_id );
		if ( $run === null ) {
			return new WP_Error( 'beehiiv_sync_run_not_found', 'Run not found or expired.', [ 'status' => 404 ] );
		}

		if ( $run->status === ImportRun::STATUS_QUEUED || $run->status === ImportRun::STATUS_RUNNING ) {
			$this->drive_queue();
			$run = $this->runs->load( $run_id ) ?? $run;
		}

		return new WP_REST_Response( $run->to_array() );
	}

	/**
	 * Run this plugin's due Action Scheduler jobs inside the status request.
	 *
	 * Action Scheduler normally runs from WP-Cron, which fires via a loopback
	 * HTTP request that this site's host blocks — so imports sat at "queued"
	 * forever. The import screen polls this endpoint while a run is in
	 * progress, so each poll works through the queue for a short slice of
	 * time instead. Jobs are claimed first, so a real cron run happening at
	 * the same time can't execute the same job twice.
	 */
	private function drive_queue(): void {
		if ( ! class_exists( \ActionScheduler::class ) ) {
			return;
		}

		$store    = \ActionScheduler::store();
		$runner   = \ActionScheduler::runner();
		$deadline = microtime( true ) + self::QUEUE_SLICE_SECONDS;

		while ( microtime( true ) < $deadline ) {
			// One job per claim, so the time slice is checked between jobs.
			$claim   = $store->stake_claim( 1, null, [], Importer::GROUP );
			$actions = $claim->get_actions();
			if ( $actions === [] ) {
				$store->release_claim( $claim );
				return;
			}
			foreach ( $actions as $action_id ) {
				$runner->process_action( $action_id, 'Beehiiv Sync import screen' );
			}
			$store->release_claim( $claim );
		}
	}
}
