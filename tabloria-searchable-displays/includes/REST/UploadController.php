<?php
/**
 * REST controller for spreadsheet uploads
 *
 * @package Tabloria
 */

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

/**
 * Handles the REST endpoint that accepts a CSV upload.
 */
class UploadController {
	/**
	 * Database access layer.
	 *
	 * @var Database
	 */
	private Database $database;
	/**
	 * Spreadsheet sanitizer.
	 *
	 * @var SpreadsheetSanitizer
	 */
	private SpreadsheetSanitizer $sanitizer;

	/**
	 * Set up the controller.
	 *
	 * @param Database|null             $database  Optional database access layer.
	 * @param SpreadsheetSanitizer|null $sanitizer Optional spreadsheet sanitizer.
	 */
	public function __construct( ?Database $database = null, ?SpreadsheetSanitizer $sanitizer = null ) {
		$this->database  = $database ?: new Database();
		$this->sanitizer = $sanitizer ?: new SpreadsheetSanitizer();
	}

	/**
	 * Register the upload route.
	 */
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

	/**
	 * Allow administrators to upload.
	 *
	 * @return bool True when the user can manage options.
	 */
	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Validate and parse an uploaded CSV, save it as a source, and return its summary.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response|WP_Error Source summary, or an error.
	 */
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

		// PHP removes the request's temporary file after this request. The parsed rows
		// live in our database, so keeping a raw CSV in public uploads is unnecessary.
		$parsed = ( new CsvParser() )->parse( $file['tmp_name'] );

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
		if ( ! $source_id ) {
			return new WP_Error( 'tabloria_source_save_failed', __( 'The CSV was read, but its data could not be saved. Please try again.', 'tabloria-searchable-displays' ), array( 'status' => 500 ) );
		}

		$source = $this->database->get_source( $source_id );

		return new WP_REST_Response(
			array(
				'source' => $source,
				'sample' => $parsed['sample'],
			),
			201
		);
	}

	/**
	 * Detect uploads that exceeded post_max_size and were dropped by PHP.
	 *
	 * @return bool|WP_Error False when fine, otherwise an error.
	 */
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
