<?php

namespace Publishly\Services;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CsvParser {
	private ColumnDetector $detector;

	public function __construct( ?ColumnDetector $detector = null ) {
		$this->detector = $detector ?: new ColumnDetector();
	}

	public function parse( string $path ): array|WP_Error {
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'publishly_csv_unreadable', __( 'The uploaded CSV file could not be read.', 'publishly' ) );
		}

		$handle = fopen( $path, 'rb' );
		if ( false === $handle ) {
			return new WP_Error( 'publishly_csv_open_failed', __( 'The uploaded CSV file could not be opened.', 'publishly' ) );
		}

		$headers = fgetcsv( $handle );
		if ( ! is_array( $headers ) || empty( $headers ) ) {
			fclose( $handle );
			return new WP_Error( 'publishly_csv_empty', __( 'The CSV file needs a header row.', 'publishly' ) );
		}

		$headers = array_map(
			static function ( string $header ): string {
				return sanitize_text_field( preg_replace( '/^\xEF\xBB\xBF/', '', trim( $header ) ) );
			},
			$headers
		);

		$keys      = $this->unique_keys( $headers );
		$rows      = array();
		$row_index = 0;

		while ( false !== ( $data = fgetcsv( $handle ) ) ) {
			if ( $this->is_blank_row( $data ) ) {
				continue;
			}

			$row = array();
			foreach ( $keys as $index => $key ) {
				$row[ $key ] = sanitize_text_field( (string) ( $data[ $index ] ?? '' ) );
			}

			$rows[ $row_index ] = $row;
			$row_index++;
		}

		fclose( $handle );

		$columns = $this->detector->detect_columns( $headers, $rows );
		foreach ( $columns as $index => $column ) {
			$columns[ $index ]['key'] = $keys[ $index ];
		}

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
