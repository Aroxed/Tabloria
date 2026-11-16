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

		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- The file was capped at 5 MB above.
		if ( false === $contents ) {
			return new WP_Error( 'tabloria_csv_open_failed', __( 'The uploaded CSV file could not be opened.', 'tabloria-searchable-displays' ) );
		}
		if ( str_starts_with( $contents, "\xFF\xFE" ) || str_starts_with( $contents, "\xFE\xFF" ) ) {
			$encoding = str_starts_with( $contents, "\xFF\xFE" ) ? 'UTF-16LE' : 'UTF-16BE';
			$contents = function_exists( 'iconv' ) ? iconv( $encoding, 'UTF-8', substr( $contents, 2 ) ) : false;
			if ( false === $contents ) {
				return new WP_Error( 'tabloria_csv_encoding', __( 'This CSV could not be converted to UTF-8. Export it as UTF-8 CSV and try again.', 'tabloria-searchable-displays' ), array( 'status' => 400 ) );
			}
		} elseif ( str_starts_with( $contents, "\xEF\xBB\xBF" ) ) {
			$contents = substr( $contents, 3 );
		}
		if ( 1 !== preg_match( '//u', $contents ) ) {
			return new WP_Error( 'tabloria_csv_encoding', __( 'The CSV is not valid UTF-8. Export it as UTF-8 CSV and try again.', 'tabloria-searchable-displays' ), array( 'status' => 400 ) );
		}

		$handle = fopen( 'php://temp', 'w+b' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- fgetcsv() requires a seekable stream.
		if ( false === $handle ) {
			return new WP_Error( 'tabloria_csv_open_failed', __( 'The uploaded CSV file could not be opened.', 'tabloria-searchable-displays' ) );
		}
		$written = fwrite( $handle, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writes to a private temporary stream only.
		if ( false === $written || strlen( $contents ) !== $written ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the temporary stream on a short write.
			return new WP_Error( 'tabloria_csv_open_failed', __( 'The uploaded CSV file could not be read completely.', 'tabloria-searchable-displays' ) );
		}
		$delimiter = $this->detect_delimiter( $handle );
		rewind( $handle );

		$headers = fgetcsv( $handle, 0, $delimiter, '"', '' );
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

		while ( false !== ( $data = fgetcsv( $handle, 0, $delimiter, '"', '' ) ) ) {
			if ( $this->is_blank_row( $data ) ) {
				continue;
			}

			if ( $row_index >= SpreadsheetSanitizer::MAX_ROWS ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the fgetcsv() stream opened above.
				return new WP_Error( 'tabloria_csv_too_many_rows', __( 'The CSV file has too many rows for this MVP release.', 'tabloria-searchable-displays' ), array( 'status' => 400 ) );
			}
			if ( count( $data ) !== count( $headers ) ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the temporary stream.
				return new WP_Error( 'tabloria_csv_column_mismatch', sprintf( __( 'CSV row %d has a different number of columns than the header. Check the delimiter and quoted values.', 'tabloria-searchable-displays' ), $row_index + 2 ), array( 'status' => 400 ) );
			}

			$row = array();
			foreach ( $keys as $index => $key ) {
				$row[ $key ] = $this->sanitizer->sanitize_value( $data[ $index ] ?? '', 'text' );
			}

			$rows[ $row_index ] = $row;
			$row_index++;
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the fgetcsv() stream opened above.
		if ( 0 === $row_index ) {
			return new WP_Error( 'tabloria_csv_no_rows', __( 'The CSV needs at least one data row.', 'tabloria-searchable-displays' ), array( 'status' => 400 ) );
		}

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

	private function detect_delimiter( $handle ): string {
		$best = ',';
		$best_score = -1;
		foreach ( array( ',', ';', "\t" ) as $candidate ) {
			rewind( $handle );
			$widths = array();
			while ( count( $widths ) < 8 && false !== ( $row = fgetcsv( $handle, 0, $candidate, '"', '' ) ) ) {
				if ( ! $this->is_blank_row( $row ) ) {
					$widths[] = count( $row );
				}
			}
			if ( ! $widths ) {
				continue;
			}
			$header_width = $widths[0];
			$matching = count( array_filter( $widths, static fn( int $width ): bool => $width === $header_width ) );
			$score = $header_width > 1 ? ( $matching * 100 + $header_width ) : 0;
			if ( $score > $best_score ) {
				$best_score = $score;
				$best = $candidate;
			}
		}
		return $best;
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
