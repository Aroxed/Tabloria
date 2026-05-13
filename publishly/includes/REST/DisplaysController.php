<?php

namespace Publishly\REST;

use Publishly\Database;
use Publishly\Services\CsvParser;
use Publishly\Services\DataUpdater;
use Publishly\Services\DisplayGenerator;
use Publishly\Services\FrontendRenderer;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DisplaysController {
	private Database $database;
	private DisplayGenerator $generator;

	public function __construct( ?Database $database = null, ?DisplayGenerator $generator = null ) {
		$this->database  = $database ?: new Database();
		$this->generator = $generator ?: new DisplayGenerator();
	}

	public function register_routes(): void {
		register_rest_route(
			'publishly/v1',
			'/displays',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( $this, 'read_permissions_check' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create' ),
					'permission_callback' => array( $this, 'manage_permissions_check' ),
				),
			)
		);

		register_rest_route(
			'publishly/v1',
			'/displays/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'show' ),
					'permission_callback' => array( $this, 'read_permissions_check' ),
				),
				array(
					'methods'             => 'PUT,PATCH',
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( $this, 'manage_permissions_check' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete' ),
					'permission_callback' => array( $this, 'manage_permissions_check' ),
				),
			)
		);

		register_rest_route(
			'publishly/v1',
			'/displays/(?P<id>\d+)/render-data',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'render_data' ),
				'permission_callback' => array( $this, 'read_permissions_check' ),
			)
		);

		register_rest_route(
			'publishly/v1',
			'/displays/(?P<id>\d+)/update-data',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_data' ),
				'permission_callback' => array( $this, 'manage_permissions_check' ),
			)
		);
	}

	public function read_permissions_check(): bool {
		return current_user_can( 'edit_posts' );
	}

	public function manage_permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	public function index(): WP_REST_Response {
		return new WP_REST_Response( $this->database->list_displays() );
	}

	public function show( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$display = $this->database->get_display( absint( $request['id'] ) );
		if ( ! $display ) {
			return new WP_Error( 'publishly_display_not_found', __( 'Published Display not found.', 'publishly' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $display );
	}

	public function create( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$source_id = absint( $request->get_param( 'sourceId' ) ?: $request->get_param( 'source_id' ) );
		$source    = $this->database->get_source( $source_id );

		if ( ! $source ) {
			return new WP_Error( 'publishly_source_required', __( 'Choose a source spreadsheet before saving a Display.', 'publishly' ), array( 'status' => 400 ) );
		}

		$display_type = sanitize_key( $request->get_param( 'displayType' ) ?: 'searchable_listings' );
		$config       = $request->get_param( 'config' );
		if ( ! is_array( $config ) ) {
			$config = $this->generator->generate( $source, $display_type, array( 'title' => $request->get_param( 'name' ) ) );
		}

		$config = $this->generator->sanitize_config( $config, $source );
		$name   = sanitize_text_field( $request->get_param( 'name' ) ?: ( $config['title'] ?? __( 'Published Display', 'publishly' ) ) );

		$display_id = $this->database->create_display(
			array(
				'source_id'    => $source_id,
				'name'         => $name,
				'display_type' => $display_type,
				'config'       => $config,
				'status'       => sanitize_key( $request->get_param( 'status' ) ?: 'published' ),
			)
		);

		return new WP_REST_Response( $this->database->get_display( $display_id ), 201 );
	}

	public function update( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$display = $this->database->get_display( absint( $request['id'] ) );
		if ( ! $display ) {
			return new WP_Error( 'publishly_display_not_found', __( 'Published Display not found.', 'publishly' ), array( 'status' => 404 ) );
		}

		$source = $this->database->get_source( absint( $display['source_id'] ) );
		if ( ! $source ) {
			return new WP_Error( 'publishly_source_not_found', __( 'Source spreadsheet not found.', 'publishly' ), array( 'status' => 404 ) );
		}

		$config = $request->get_param( 'config' );
		$data   = array();
		if ( is_array( $config ) ) {
			$data['config'] = $this->generator->sanitize_config( $config, $source );
		}
		$display_type = $request->get_param( 'displayType' );
		if ( null === $display_type && ! empty( $data['config']['displayType'] ) ) {
			$display_type = $data['config']['displayType'];
		}
		if ( null !== $display_type ) {
			$data['display_type'] = sanitize_key( $display_type );
		}
		if ( null !== $request->get_param( 'name' ) ) {
			$data['name'] = sanitize_text_field( $request->get_param( 'name' ) );
		}
		if ( null !== $request->get_param( 'status' ) ) {
			$data['status'] = sanitize_key( $request->get_param( 'status' ) );
		}

		$this->database->update_display( absint( $display['id'] ), $data );

		return new WP_REST_Response( $this->database->get_display( absint( $display['id'] ) ) );
	}

	public function delete( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$display = $this->database->get_display( absint( $request['id'] ) );
		if ( ! $display ) {
			return new WP_Error( 'publishly_display_not_found', __( 'Published Display not found.', 'publishly' ), array( 'status' => 404 ) );
		}

		$this->database->delete_display( absint( $display['id'] ) );
		return new WP_REST_Response( array( 'deleted' => true ) );
	}

	public function render_data( WP_REST_Request $request ): WP_REST_Response {
		$renderer = new FrontendRenderer( $this->database );
		return new WP_REST_Response( $renderer->render_preview_data( absint( $request['id'] ) ) );
	}

	public function update_data( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$display = $this->database->get_display( absint( $request['id'] ) );
		if ( ! $display ) {
			return new WP_Error( 'publishly_display_not_found', __( 'Published Display not found.', 'publishly' ), array( 'status' => 404 ) );
		}

		$source = $this->database->get_source( absint( $display['source_id'] ) );
		if ( ! $source ) {
			return new WP_Error( 'publishly_source_not_found', __( 'Source spreadsheet not found.', 'publishly' ), array( 'status' => 404 ) );
		}

		$files = $request->get_file_params();
		if ( empty( $files['file'] ) ) {
			return new WP_Error( 'publishly_missing_file', __( 'Please choose a replacement CSV file.', 'publishly' ), array( 'status' => 400 ) );
		}

		$file     = $files['file'];
		$filename = sanitize_file_name( $file['name'] ?? '' );
		$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( 'csv' !== $ext ) {
			return new WP_Error( 'publishly_update_csv_only', __( 'Data updates currently support CSV files.', 'publishly' ), array( 'status' => 400 ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$upload = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'test_type' => false,
			)
		);
		if ( isset( $upload['error'] ) ) {
			return new WP_Error( 'publishly_upload_failed', sanitize_text_field( $upload['error'] ), array( 'status' => 400 ) );
		}

		$parsed = ( new CsvParser() )->parse( $upload['file'] );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$summary = ( new DataUpdater() )->compare_columns( $source['columns'], $parsed['columns'] );
		$this->database->update_source_data( absint( $source['id'] ), $parsed['columns'], $parsed['rows'], $filename, 'csv' );

		return new WP_REST_Response(
			array(
				'summary' => $summary,
				'source'  => $this->database->get_source( absint( $source['id'] ) ),
				'sample'  => array_slice( $parsed['rows'], 0, 24 ),
			)
		);
	}
}
