<?php

namespace Tabloria\REST;

use Tabloria\Database;
use Tabloria\Services\CsvParser;
use Tabloria\Services\SpreadsheetSanitizer;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UploadController {
	private Database $database;
	private SpreadsheetSanitizer $sanitizer;

	public function __construct( ?Database $database = null, ?SpreadsheetSanitizer $sanitizer = null ) {
		$this->database  = $database ?: new Database();
		$this->sanitizer = $sanitizer ?: new SpreadsheetSanitizer();
	}

	public function register_routes(): void {
		register_rest_route(
			'tabloria/v1',
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
			$post_too_large = $this->post_payload_too_large();
			if ( is_wp_error( $post_too_large ) ) {
				return $post_too_large;
			}

			return new WP_Error( 'tabloria_missing_file', __( 'Please choose a spreadsheet file to upload.', 'tabloria-searchable-displays' ), array( 'status' => 400 ) );
		}

		$file = $files['file'];
		$meta = $this->sanitizer->validate_uploaded_csv( $file );
		if ( is_wp_error( $meta ) ) {
			return $meta;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$upload = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'test_type' => false,
				'mimes'     => array(
					'csv' => 'text/csv',
				),
			)
		);

		if ( isset( $upload['error'] ) ) {
			return new WP_Error( 'tabloria_upload_failed', sanitize_text_field( $upload['error'] ), array( 'status' => 400 ) );
		}

		$parsed = ( new CsvParser() )->parse( $upload['file'] );

		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$source_id = $this->database->create_source(
			array(
				'name'              => sanitize_text_field( $request->get_param( 'name' ) ?: $meta['filename'] ),
				'original_filename' => $meta['filename'],
				'file_type'         => 'csv',
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

	private function post_payload_too_large(): bool|WP_Error {
		$content_length = absint( $_SERVER['CONTENT_LENGTH'] ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Used only as a numeric byte count for limit comparison.
		if ( $content_length <= 0 ) {
			return true;
		}

		$post_max_size = wp_convert_hr_to_bytes( (string) ini_get( 'post_max_size' ) );
		if ( $post_max_size > 0 && $content_length > $post_max_size ) {
			return new WP_Error(
				'tabloria_post_too_large',
				sprintf(
					/* translators: 1: Tabloria max upload size, 2: PHP post_max_size setting. */
					__( 'The CSV upload exceeded the server request size limit. Tabloria accepts files up to %1$s, but PHP post_max_size is currently %2$s.', 'tabloria-searchable-displays' ),
					size_format( SpreadsheetSanitizer::MAX_FILE_SIZE ),
					ini_get( 'post_max_size' )
				),
				array( 'status' => 400 )
			);
		}

		return true;
	}
}
