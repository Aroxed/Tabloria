<?php

namespace Publishly\REST;

use Publishly\Database;
use Publishly\Services\CsvParser;
use Publishly\Services\XlsxParser;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UploadController {
	private Database $database;

	public function __construct( ?Database $database = null ) {
		$this->database = $database ?: new Database();
	}

	public function register_routes(): void {
		register_rest_route(
			'publishly/v1',
			'/upload',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'upload' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);
	}

	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	public function upload( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$files = $request->get_file_params();
		if ( empty( $files['file'] ) ) {
			return new WP_Error( 'publishly_missing_file', __( 'Please choose a spreadsheet file to upload.', 'publishly' ), array( 'status' => 400 ) );
		}

		$file = $files['file'];
		if ( ! empty( $file['error'] ) ) {
			return new WP_Error( 'publishly_upload_error', __( 'The spreadsheet upload failed.', 'publishly' ), array( 'status' => 400 ) );
		}

		$filename = sanitize_file_name( $file['name'] ?? '' );
		$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'csv', 'xlsx' ), true ) ) {
			return new WP_Error( 'publishly_invalid_file_type', __( 'Publishly supports CSV files in this MVP. XLSX support is prepared for a later update.', 'publishly' ), array( 'status' => 400 ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$upload = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'test_type' => false,
				'mimes'     => array(
					'csv'  => 'text/csv',
					'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				),
			)
		);

		if ( isset( $upload['error'] ) ) {
			return new WP_Error( 'publishly_upload_failed', sanitize_text_field( $upload['error'] ), array( 'status' => 400 ) );
		}

		$parser = 'csv' === $ext ? new CsvParser() : new XlsxParser();
		$parsed = $parser->parse( $upload['file'] );

		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$source_id = $this->database->create_source(
			array(
				'name'              => sanitize_text_field( $request->get_param( 'name' ) ?: $filename ),
				'original_filename' => $filename,
				'file_type'         => $ext,
				'columns'           => $parsed['columns'],
			),
			$parsed['rows']
		);

		$source = $this->database->get_source( $source_id );

		return new WP_REST_Response(
			array(
				'source' => $source,
				'sample' => $parsed['sample'],
			),
			201
		);
	}
}
