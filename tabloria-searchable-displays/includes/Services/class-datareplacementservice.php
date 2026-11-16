<?php

namespace Tabloria\Services;

use Tabloria\Database;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DataReplacementService {
	private Database $database;

	public function __construct( ?Database $database = null ) {
		$this->database = $database ?: new Database();
	}

	public function replace( array $source, string $path, string $filename ): array|WP_Error {
		$source_id = absint( $source['id'] ?? 0 );
		$lock_key  = 'tabloria_source_lock_' . $source_id;
		if ( ! $source_id ) {
			return new WP_Error( 'tabloria_source_not_found', __( 'Source spreadsheet not found.', 'tabloria-searchable-displays' ), array( 'status' => 404 ) );
		}
		if ( ! add_option( $lock_key, time(), '', false ) ) {
			$locked_at = (int) get_option( $lock_key, 0 );
			if ( $locked_at > 0 && $locked_at < time() - 600 ) {
				delete_option( $lock_key );
			}
			if ( ! add_option( $lock_key, time(), '', false ) ) {
				return new WP_Error( 'tabloria_source_busy', __( 'This source is already being updated. Try again shortly.', 'tabloria-searchable-displays' ), array( 'status' => 409 ) );
			}
		}

		try {
			$parsed = ( new CsvParser() )->parse( $path );
			if ( is_wp_error( $parsed ) ) {
				return $parsed;
			}

			$summary = ( new DataUpdater() )->compare_columns( $source['columns'], $parsed['columns'] );
			if ( $summary['missingCount'] > 0 ) {
				$missing = array_map( static fn( array $column ): string => (string) ( $column['label'] ?? $column['key'] ?? '' ), array_slice( $summary['missing'], 0, 3 ) );
				return new WP_Error(
					'tabloria_csv_missing_columns',
					sprintf(
						/* translators: %s: comma-separated column names. */
						__( 'The replacement is missing existing columns (%s). Keep the same headers so saved Display fields keep working. No data was changed.', 'tabloria-searchable-displays' ),
						implode( ', ', $missing )
					),
					array( 'status' => 409 )
				);
			}
			if ( ! $this->database->update_source_data( $source_id, $parsed['columns'], $parsed['rows'], $filename, 'csv' ) ) {
				return new WP_Error( 'tabloria_source_update_failed', __( 'The replacement could not be saved. The previous CSV data is still available.', 'tabloria-searchable-displays' ), array( 'status' => 500 ) );
			}

			return array(
				'summary' => $summary,
				'source'  => $this->database->get_source( $source_id ),
				'sample'  => array_slice( $parsed['rows'], 0, 24 ),
			);
		} finally {
			delete_option( $lock_key );
		}
	}
}
