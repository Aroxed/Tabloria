<?php
/**
 * Compares column sets when a source is updated
 *
 * @package Tabloria
 */

namespace Tabloria\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compares old and new column sets to report matched, missing, and new columns.
 */
class DataUpdater {
	/**
	 * Compare the columns of an existing source with a replacement file.
	 *
	 * @param array $old_columns Columns of the existing source.
	 * @param array $new_columns Columns of the replacement file.
	 *
	 * @return array Matched, missing, and new columns.
	 */
	public function compare_columns( array $old_columns, array $new_columns ): array {
		$old_by_key = array();
		$new_by_key = array();

		foreach ( $old_columns as $column ) {
			$key = sanitize_key( $column['key'] ?? '' );
			if ( $key ) {
				$old_by_key[ $key ] = $column;
			}
		}

		foreach ( $new_columns as $column ) {
			$key = sanitize_key( $column['key'] ?? '' );
			if ( $key ) {
				$new_by_key[ $key ] = $column;
			}
		}

		$matched = array();
		$missing = array();
		$new = array();

		foreach ( $old_by_key as $key => $old_column ) {
			if ( isset( $new_by_key[ $key ] ) ) {
				$matched[] = array(
					'old' => $old_column,
					'new' => $new_by_key[ $key ],
				);
				continue;
			}

			$missing[] = $old_column;
		}

		$matched_new_keys = array_map(
			static fn( array $pair ): string => sanitize_key( $pair['new']['key'] ?? '' ),
			$matched
		);

		foreach ( $new_by_key as $key => $column ) {
			if ( ! in_array( $key, $matched_new_keys, true ) ) {
				$new[] = $column;
			}
		}

		return array(
			'matched'      => $matched,
			'new'          => $new,
			'missing'      => $missing,
			'matchedCount' => count( $matched ),
			'newCount'     => count( $new ),
			'missingCount' => count( $missing ),
		);
	}

}
