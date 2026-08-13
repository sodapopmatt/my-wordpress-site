<?php
/**
 * This File Contains the Endpoints Class of the Plugin.
 *
 * @package ITFB\ImportCampaigns;
 * @since 2.0.0
 */

namespace ITFB\ImportCampaigns;

use ITFB\ImportCampaigns\Helper;
use ITFB\ImportCampaigns\BackgroundProcessing\ImportCampaignsProcess;

defined( 'ABSPATH' ) || exit;

/**
 * The Endpoints class.
 *
 * Handles the Endpoints functionality of the plugin.
 *
 * @since      2.0.0
 * @package    ITFB\ImportCampaigns
 */
class Endpoints {

	/**
	 * Total queued campaigns result.
	 *
	 * @var int $total_queued_campaigns_result
	 * @since 2.0.0
	 */
	public $total_queued_campaigns_result = 0;

	/**
	 * The import campaigns process.
	 *
	 * @var ImportCampaignsProcess $import_campaigns_process
	 */
	public $import_campaigns_process;

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 *
	 * @since    2.0.0
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'handle_background_processes' ) );
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
		add_action( 'itfb_import_campaigns', array( $this, 'handle_scheduled_import' ) );
		add_action( 'init', Helper::class . '::include_action_scheduler' );
	}

	/**
	 * Register the endpoints.
	 *
	 * @since 2.0.0
	 */
	public function register_endpoints() {
		register_rest_route(
			'itfb/v1',
			'/import-defaults-options',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'import_defaults_options' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			'itfb/v1',
			'/import-campaigns',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'import_campaigns' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			'itfb/v1',
			'/import-status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'import_status' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			'itfb/v1',
			'/manage-import-job',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'manage_import_job' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			'itfb/v1',
			'/get-scheduled-imports',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_scheduled_imports' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			'itfb/v1',
			'/delete-scheduled-import/',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_scheduled_import' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Get import defaults options.
	 *
	 * @param    \WP_REST_Request $request   The request object.
	 * @since    1.0.0
	 */
	public function import_defaults_options( \WP_REST_Request $request ) {
		$data = array();

		// All post types and taxonomies and terms.
		$data = array_merge( $data, Helper::get_all_post_types_tax_term() );

		// Current server time.
		$data['current_server_time'] = gmdate( '(D) H:i' );

		// All post statuses.
		$data = array_merge( $data, Helper::get_all_post_statuses() );

		// All authors users.
		$data = array_merge( $data, Helper::get_all_authors() );

		return rest_ensure_response( $data );
	}

	/**
	 * Import campaigns.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function import_campaigns( $request ) {
		// Get all parameters.
		$params = array(
			'credentials'       => json_decode( sanitize_text_field( $request->get_param( 'credentials' ) ), true ),
			'audience'          => sanitize_text_field( $request->get_param( 'audience' ) ),
			'post_status'       => json_decode( sanitize_text_field( $request->get_param( 'post_status' ) ), true ),
			'schedule_settings' => json_decode( sanitize_text_field( $request->get_param( 'schedule_settings' ) ), true ),
			'post_type'         => sanitize_text_field( $request->get_param( 'post_type' ) ),
			'taxonomy'          => sanitize_text_field( $request->get_param( 'taxonomy' ) ),
			'taxonomy_term'     => sanitize_text_field( $request->get_param( 'taxonomy_term' ) ),
			'author'            => sanitize_text_field( $request->get_param( 'author' ) ),
			'import_cm_tags_as' => sanitize_text_field( $request->get_param( 'import_cm_tags_as' ) ),
			'import_option'     => sanitize_text_field( $request->get_param( 'import_option' ) ),
		);

		$validation = Validator::validate_all_parameters( $params );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$this->total_queued_campaigns_result = ( new ImportCampaigns( $params, $this->import_campaigns_process, 'manual' ) )->fetch_and_push_campaigns_to_import_queue();

		if ( is_wp_error( $this->total_queued_campaigns_result ) ) {
			return $this->total_queued_campaigns_result;
		}

		$output = array(
			'message'                => $this->total_queued_campaigns_result['total_queued_campaigns'] . ' campaigns are being fetched and pushed to the import queue.',
			'total_queued_campaigns' => $this->total_queued_campaigns_result['total_queued_campaigns'],
			'group_name'             => $this->total_queued_campaigns_result['group_name'],
		);

		if ( 'on' === $params['schedule_settings']['enabled'] ) {
			$schedule_import_result = Helper::schedule_import_campaigns( $params );
			if ( is_wp_error( $schedule_import_result ) ) {
				$output['schedule_id'] = $schedule_import_result->get_error_message();
			} else {
				$output['schedule_id'] = $schedule_import_result;
			}
		}

		return rest_ensure_response( $output );
	}

	/**
	 * Handle scheduled import.
	 *
	 * @param array $params The parameters array.
	 */
	public function handle_scheduled_import( $params ) {
		$this->total_queued_campaigns = ( new ImportCampaigns( $params, $this->import_campaigns_process, 'auto' ) )->fetch_and_push_campaigns_to_import_queue();
	}

	/**
	 * Get import status.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function import_status( \WP_REST_Request $request ) {
		$group_name = $request->get_param( 'group_name' );

		if ( ! $group_name ) {
			return new \WP_Error( 'no_group_name', 'Group name is required.', array( 'status' => 400 ) );
		}

		// ✅ FIX: safely handle missing is_active() method.
		if ( method_exists( $this->import_campaigns_process, 'is_active' ) ) {
			$active = $this->import_campaigns_process->is_active();
		} else {
			// fallback: check if the queue is non-empty
			$active = ! empty( $this->import_campaigns_process->queue );
		}

		if ( $active ) {
			if ( method_exists( $this->import_campaigns_process, 'is_paused' ) && $this->import_campaigns_process->is_paused() ) {
				$output['status'] = 'paused';
			} else {
				$output['status'] = 'active';
			}

			$remaining_campaigns = ImportTable::get_remaining_campaigns_count( $group_name );
			$output['remaining_campaigns'] = $remaining_campaigns;

		} else {
			$output['status'] = 'not_active';
		}

		return rest_ensure_response( $output );
	}

	// (rest of your file unchanged, including manage_import_job, handle_cancel_action, get_scheduled_imports, delete_scheduled_import, handle_background_processes)

	public function manage_import_job( \WP_REST_Request $request ) {
		// ... existing code unchanged ...
	}

	private function handle_cancel_action( $schedule_id, $group_name ) {
		// ... existing code unchanged ...
	}

	public function get_scheduled_imports( \WP_REST_Request $request ) {
		// ... existing code unchanged ...
	}

	public function delete_scheduled_import( \WP_REST_Request $request ) {
		// ... existing code unchanged ...
	}

	public function handle_background_processes() {
		$this->import_campaigns_process = new ImportCampaignsProcess();
	}
}
