<?php

namespace Tabloria\Services;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CsvParser {
	private ColumnDetector $detector;
	private SpreadsheetSanitizer $sanitizer;

	public function __construct( ?ColumnDetector $detector = null, ?SpreadsheetSanitizer $sanitizer = null ) {
		$this->detector  = $detector ?: new ColumnDetector();
		$this->sanitizer = $sanitizer ?: new SpreadsheetSanitizer();
	}

	public function parse( string $path ): array|WP_Error {
		$file_error = $this->sanitizer->validate_csv_file_path( $path );
		if ( is_wp_error( $file_error ) ) {
			return $file_error;
		}

		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CSV parsing requires a stream handle for fgetcsv().
		if ( false === $handle ) {
			return new WP_Error( 'tabloria_csv_open_failed', __( 'The uploaded CSV file could not be opened.', 'tabloria-searchable-displays' ) );
		}

		$headers = fgetcsv( $handle );
		if ( ! is_array( $headers ) || empty( $headers ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the fgetcsv() stream opened above.
			return new WP_Error( 'tabloria_csv_empty', __( 'The CSV file needs a header row.', 'tabloria-searchable-displays' ) );
		}

		$headers = $this->sanitizer->sanitize_headers( $headers );
		if ( is_wp_error( $headers ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the fgetcsv() stream opened above.
			return $headers;
		}

		$keys      = $this->unique_keys( $headers );
		$rows      = array();
		$row_index = 0;

		while ( false !== ( $data = fgetcsv( $handle ) ) ) {
			if ( $this->is_blank_row( $data ) ) {
				continue;
			}

			if ( $row_index >= SpreadsheetSanitizer::MAX_ROWS ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the fgetcsv() stream opened above.
				return new WP_Error( 'tabloria_csv_too_many_rows', __( 'The CSV file has too many rows for this MVP release.', 'tabloria-searchable-displays' ), array( 'status' => 400 ) );
			}

			$row = array();
			foreach ( $keys as $index => $key ) {
				$row[ $key ] = $this->sanitizer->sanitize_value( $data[ $index ] ?? '', 'text' );
			}

			$rows[ $row_index ] = $row;
			$row_index++;
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the fgetcsv() stream opened above.

		$columns = $this->detector->detect_columns( $headers, $rows );
		foreach ( $columns as $index => $column ) {
			$columns[ $index ]['key'] = $keys[ $index ];
		}
		$columns = $this->sanitizer->sanitize_columns( $columns );
		$rows    = $this->sanitizer->sanitize_rows( $rows, $columns );

		return array(
			'columns' => $columns,
			'rows'    => $rows,
			'sample'  => array_slice( $rows, 0, 24 ),
		);
	}

	private function unique_keys( array $headers ): array {
		$seen = array();
		$keys = array();

		foreach ( $headers as $header ) {
			$base = $this->detector->normalize_key( (string) $header );
			$key  = $base;
			$i    = 2;

			while ( isset( $seen[ $key ] ) ) {
				$key = $base . '_' . $i;
				$i++;
			}

			$seen[ $key ] = true;
			$keys[]       = $key;
		}

		return $keys;
	}

	private function is_blank_row( array $row ): bool {
		foreach ( $row as $value ) {
			if ( '' !== trim( (string) $value ) ) {
				return false;
			}
		}

		return true;
	}
}
