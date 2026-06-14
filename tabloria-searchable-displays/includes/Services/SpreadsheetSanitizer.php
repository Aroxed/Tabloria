<?php

namespace Tabloria\Services;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SpreadsheetSanitizer {
	public const MAX_FILE_SIZE    = 5242880;
	public const MAX_COLUMNS      = 80;
	public const MAX_ROWS         = 60000;
	public const MAX_HEADER_BYTES = 120;
	public const MAX_CELL_BYTES   = 5000;

	private const TYPES = array( 'text', 'email', 'url', 'image', 'date', 'phone', 'price', 'number', 'status' );

	public function validate_uploaded_csv( array $file ): array|WP_Error {
		if ( ! empty( $file['error'] ) ) {
			return new WP_Error(
				'tabloria_upload_error',
				$this->upload_error_message( absint( $file['error'] ) ),
				array( 'status' => 400 )
			);
		}

		if ( empty( $file['tmp_name'] ) || ! is_readable( $file['tmp_name'] ) ) {
			return new WP_Error( 'tabloria_csv_unreadable', __( 'The uploaded CSV file could not be read.', 'tabloria-searchable-displays' ), array( 'status' => 400 ) );
		}

		$filename = sanitize_file_name( $file['name'] ?? '' );
		$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( 'csv' !== $ext ) {
			return new WP_Error( 'tabloria_invalid_file_type', __( 'Tabloria currently accepts CSV files only. Please export your spreadsheet as CSV and upload it again.', 'tabloria-searchable-displays' ), array( 'status' => 400 ) );
		}

		$size = absint( $file['size'] ?? filesize( $file['tmp_name'] ) );
		if ( $size <= 0 ) {
			return new WP_Error( 'tabloria_csv_empty_file', __( 'The uploaded CSV file is empty.', 'tabloria-searchable-displays' ), array( 'status' => 400 ) );
		}

		if ( $size > self::MAX_FILE_SIZE ) {
			return new WP_Error( 'tabloria_csv_too_large', __( 'The CSV file is too large for this MVP release.', 'tabloria-searchable-displays' ), array( 'status' => 400 ) );
		}

		$file_error = $this->validate_csv_file_path( $file['tmp_name'] );
		if ( is_wp_error( $file_error ) ) {
			return $file_error;
		}

		return array(
			'filename' => $filename,
			'ext'      => $ext,
			'size'     => $size,
		);
	}

	public function validate_csv_file_path( string $path ): true|WP_Error {
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'tabloria_csv_unreadable', __( 'The uploaded CSV file could not be read.', 'tabloria-searchable-displays' ), array( 'status' => 400 ) );
		}

		$size = filesize( $path );
		if ( false === $size || $size <= 0 ) {
			return new WP_Error( 'tabloria_csv_empty_file', __( 'The uploaded CSV file is empty.', 'tabloria-searchable-displays' ), array( 'status' => 400 ) );
		}

		if ( $size > self::MAX_FILE_SIZE ) {
			return new WP_Error( 'tabloria_csv_too_large', __( 'The CSV file is too large for this MVP release.', 'tabloria-searchable-displays' ), array( 'status' => 400 ) );
		}

		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Reads a small sample from the uploaded CSV temp file.
		if ( false === $handle ) {
			return new WP_Error( 'tabloria_csv_open_failed', __( 'The uploaded CSV file could not be opened.', 'tabloria-searchable-displays' ), array( 'status' => 400 ) );
		}

		$sample = fread( $handle, 4096 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Reads only the first bytes to reject binary CSV uploads.
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the uploaded CSV temp file handle.

		if ( false !== $sample && str_contains( $sample, "\0" ) ) {
			return new WP_Error( 'tabloria_csv_binary', __( 'The uploaded file does not look like a plain CSV file.', 'tabloria-searchable-displays' ), array( 'status' => 400 ) );
		}

		return true;
	}

	public function sanitize_headers( array $headers ): array|WP_Error {
		if ( count( $headers ) > self::MAX_COLUMNS ) {
			return new WP_Error( 'tabloria_csv_too_many_columns', __( 'The CSV file has too many columns for this MVP release.', 'tabloria-searchable-displays' ), array( 'status' => 400 ) );
		}

		$clean = array();
		foreach ( $headers as $index => $header ) {
			$clean[] = $this->sanitize_header( $header, $index );
		}

		if ( empty( $clean ) ) {
			return new WP_Error( 'tabloria_csv_empty', __( 'The CSV file needs a header row.', 'tabloria-searchable-displays' ), array( 'status' => 400 ) );
		}

		return $clean;
	}

	public function sanitize_header( mixed $value, int $index = 0 ): string {
		$value = preg_replace( '/^\xEF\xBB\xBF/', '', $this->normalize_scalar( $value, self::MAX_HEADER_BYTES ) );
		$value = sanitize_text_field( $value );

		return '' !== $value ? $value : sprintf(
			/* translators: %d: column number. */
			__( 'Column %d', 'tabloria-searchable-displays' ),
			$index + 1
		);
	}

	public function sanitize_columns( array $columns ): array {
		$clean = array();
		$seen  = array();

		foreach ( array_slice( $columns, 0, self::MAX_COLUMNS ) as $index => $column ) {
			$key = sanitize_key( $column['key'] ?? '' );
			if ( '' === $key ) {
				$key = 'field_' . ( $index + 1 );
			}

			$base = $key;
			$i    = 2;
			while ( isset( $seen[ $key ] ) ) {
				$key = $base . '_' . $i;
				$i++;
			}
			$seen[ $key ] = true;

			$type       = sanitize_key( $column['type'] ?? 'text' );
			$confidence = is_numeric( $column['confidence'] ?? null ) ? (float) $column['confidence'] : 0.5;

			$clean[] = array(
				'key'        => $key,
				'label'      => $this->sanitize_header( $column['label'] ?? $key, $index ),
				'type'       => in_array( $type, self::TYPES, true ) ? $type : 'text',
				'confidence' => (float) max( 0, min( 1, $confidence ) ),
			);
		}

		return $clean;
	}

	public function sanitize_rows( array $rows, array $columns ): array {
		$clean = array();
		foreach ( array_slice( $rows, 0, self::MAX_ROWS, true ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$clean[] = $this->sanitize_row( $row, $columns );
		}

		return $clean;
	}

	public function sanitize_row( array $row, array $columns ): array {
		$clean = array();
		foreach ( $columns as $column ) {
			$key = sanitize_key( $column['key'] ?? '' );
			if ( '' === $key ) {
				continue;
			}

			$type          = sanitize_key( $column['type'] ?? 'text' );
			$clean[ $key ] = $this->sanitize_value( $row[ $key ] ?? '', $type );
		}

		return $clean;
	}

	public function sanitize_untyped_row( array $row ): array {
		$clean = array();
		foreach ( array_slice( $row, 0, self::MAX_COLUMNS, true ) as $key => $value ) {
			$key = sanitize_key( is_string( $key ) ? $key : 'field_' . ( absint( $key ) + 1 ) );
			if ( '' === $key ) {
				continue;
			}
			$clean[ $key ] = $this->sanitize_value( $value, 'text' );
		}

		return $clean;
	}

	public function sanitize_value( mixed $value, string $type = 'text' ): string {
		$type = sanitize_key( $type );

		return match ( $type ) {
			'email' => $this->sanitize_email_value( $value ),
			'url'   => $this->sanitize_url_value( $value ),
			'image' => $this->sanitize_image_url( $value ),
			'date'  => $this->sanitize_short_text( $value, 120 ),
			'phone' => $this->sanitize_short_text( $value, 80 ),
			'price' => $this->sanitize_short_text( $value, 80 ),
			'number' => $this->sanitize_short_text( $value, 80 ),
			'status' => $this->sanitize_short_text( $value, 80 ),
			default => $this->sanitize_text_value( $value ),
		};
	}

	public function sanitize_url_value( mixed $value ): string {
		$value = $this->normalize_scalar( $value, self::MAX_CELL_BYTES );
		$value = wp_strip_all_tags( $value );
		$value = trim( preg_replace( '/\s+/', '', $value ) ?? '' );

		if ( '' === $value || ! preg_match( '#^https?://#i', $value ) ) {
			return '';
		}

		return sanitize_url( $value, array( 'http', 'https' ) );
	}

	public function sanitize_image_url( mixed $value ): string {
		$url = $this->sanitize_url_value( $value );
		if ( '' === $url ) {
			return '';
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( ! preg_match( '/\.(?:jpg|jpeg|png|webp|gif)$/i', $path ) ) {
			return '';
		}

		return $url;
	}

	private function sanitize_email_value( mixed $value ): string {
		$value = $this->normalize_scalar( $value, 320 );
		$value = sanitize_email( $value );

		return is_email( $value ) ? $value : '';
	}

	private function sanitize_text_value( mixed $value ): string {
		$value = sanitize_text_field( $this->normalize_scalar( $value, self::MAX_CELL_BYTES ) );
		return $this->neutralize_formula_value( $value );
	}

	private function sanitize_short_text( mixed $value, int $max_bytes ): string {
		$value = sanitize_text_field( $this->normalize_scalar( $value, $max_bytes ) );
		return $this->neutralize_formula_value( $value );
	}

	private function normalize_scalar( mixed $value, int $max_bytes ): string {
		if ( is_array( $value ) || is_object( $value ) || is_resource( $value ) ) {
			return '';
		}

		$value = (string) $value;
		$value = wp_check_invalid_utf8( $value, true );
		$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value );
		$value = preg_replace( '/\s+/u', ' ', is_string( $value ) ? $value : '' );
		$value = trim( is_string( $value ) ? $value : '' );

		return $this->limit_bytes( $value, $max_bytes );
	}

	private function neutralize_formula_value( string $value ): string {
		if ( preg_match( '/^\s*(?:=|@|\+|-(?!\d))/', $value ) ) {
			return "'" . $value;
		}

		return $value;
	}

	private function upload_error_message( int $code ): string {
		return match ( $code ) {
			UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => sprintf(
				/* translators: 1: Tabloria max upload size, 2: PHP upload_max_filesize setting. */
				__( 'The CSV file is too large for the server. Tabloria accepts files up to %1$s, but PHP is currently limited to %2$s. Ask your host to raise upload_max_filesize and post_max_size.', 'tabloria-searchable-displays' ),
				size_format( self::MAX_FILE_SIZE ),
				ini_get( 'upload_max_filesize' )
			),
			UPLOAD_ERR_PARTIAL => __( 'The CSV file was only partially uploaded. Please try again.', 'tabloria-searchable-displays' ),
			UPLOAD_ERR_NO_FILE => __( 'No CSV file was uploaded.', 'tabloria-searchable-displays' ),
			UPLOAD_ERR_NO_TMP_DIR => __( 'The server is missing a temporary folder for uploads.', 'tabloria-searchable-displays' ),
			UPLOAD_ERR_CANT_WRITE => __( 'The server failed to write the uploaded CSV file to disk.', 'tabloria-searchable-displays' ),
			UPLOAD_ERR_EXTENSION => __( 'A PHP extension stopped the CSV upload.', 'tabloria-searchable-displays' ),
			default => __( 'The spreadsheet upload failed.', 'tabloria-searchable-displays' ),
		};
	}

	private function limit_bytes( string $value, int $max_bytes ): string {
		if ( strlen( $value ) <= $max_bytes ) {
			return $value;
		}

		if ( function_exists( 'mb_strcut' ) ) {
			return mb_strcut( $value, 0, $max_bytes, 'UTF-8' );
		}

		return substr( $value, 0, $max_bytes );
	}
}
