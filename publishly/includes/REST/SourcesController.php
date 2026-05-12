<?php

namespace Publishly\REST;

use Publishly\Database;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SourcesController {
	private Database $database;

	public function __construct( ?Database $database = null ) {
		$this->database = $database ?: new Database();
	}

	public function register_routes(): void {
		register_rest_route(
			'publishly/v1',
			'/sources',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'index' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			'publishly/v1',
			'/sources/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'show' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => 'is_numeric',
					),
				),
			)
		);
	}

	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	public function index(): WP_REST_Response {
		return new WP_REST_Response( $this->database->list_sources() );
	}

	public function show( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$source = $this->database->get_source( absint( $request['id'] ) );
		if ( ! $source ) {
			return new WP_Error( 'publishly_source_not_found', __( 'Source spreadsheet not found.', 'publishly' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response(
			array(
				'source' => $source,
				'sample' => $this->database->get_rows( absint( $source['id'] ), 24 ),
			)
		);
	}
}
