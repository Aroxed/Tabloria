<?php
/**
 * REST controller for sources and their refresh settings
 *
 * @package Tabloria
 */

namespace Tabloria\REST;

use Tabloria\Database;
use Tabloria\Services\SourceSync;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles REST endpoints for sources and their automatic refresh.
 */
class SourcesController {
	/**
	 * Database access layer.
	 *
	 * @var Database
	 */
	private Database $database;
	/**
	 * Source refresh service.
	 *
	 * @var SourceSync
	 */
	private SourceSync $sync;

	/**
	 * Set up the controller.
	 *
	 * @param Database|null   $database Optional database access layer.
	 * @param SourceSync|null $sync     Optional source sync service.
	 */
	public function __construct( ?Database $database = null, ?SourceSync $sync = null ) {
		$this->database = $database ?: new Database();
		$this->sync = $sync ?: new SourceSync( $this->database );
	}

	/**
	 * Register the source routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			'tabloria/v1',
			'/sources',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'index' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			'tabloria/v1',
			'/sources/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'show' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => static fn( $value ): bool => is_numeric( $value ),
					),
				),
			)
		);
		register_rest_route(
			'tabloria/v1',
			'/sources/(?P<id>\\d+)/sync',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'save_sync' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);
		register_rest_route(
			'tabloria/v1',
			'/sources/(?P<id>\\d+)/refresh',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'refresh' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);
	}

	/**
	 * Allow administrators to manage sources.
	 *
	 * @return bool True when the user can manage options.
	 */
	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * List sources.
	 *
	 * @return WP_REST_Response List of sources.
	 */
	public function index(): WP_REST_Response {
		return new WP_REST_Response( $this->database->list_sources() );
	}

	/**
	 * Get one source with a sample of its rows and its refresh settings.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response|WP_Error Source, or an error if not found.
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$source = $this->database->get_source( absint( $request['id'] ) );
		if ( ! $source ) {
			return new WP_Error( 'tabloria_source_not_found', __( 'Source spreadsheet not found.', 'tabloria-searchable-displays' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response(
			array(
				'source' => $source,
				'sample' => $this->database->get_rows( absint( $source['id'] ), 24 ),
				'sync'   => $this->sync->get_settings( absint( $source['id'] ) ),
			)
		);
	}

	/**
	 * Save the automatic refresh settings of a source.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response|WP_Error Saved settings, or an error.
	 */
	public function save_sync( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$settings = $this->sync->save_settings( absint( $request['id'] ), (array) $request->get_json_params() );
		return is_wp_error( $settings ) ? $settings : new WP_REST_Response( $settings );
	}

	/**
	 * Refresh a source from its connected CSV file now.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response|WP_Error Refresh result, or an error.
	 */
	public function refresh( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = $this->sync->refresh( absint( $request['id'] ) );
		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result );
	}
}
