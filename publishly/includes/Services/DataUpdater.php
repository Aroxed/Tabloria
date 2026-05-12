<?php

namespace Publishly\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DataUpdater {
	private ColumnDetector $detector;

	public function __construct( ?ColumnDetector $detector = null ) {
		$this->detector = $detector ?: new ColumnDetector();
	}

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

			$label_match = $this->find_label_match( $old_column, $new_by_key );
			if ( $label_match ) {
				$matched[] = array(
					'old' => $old_column,
					'new' => $label_match,
				);
			} else {
				$missing[] = $old_column;
			}
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

	private function find_label_match( array $old_column, array $new_columns ): ?array {
		$old_label = $this->detector->normalize_key( (string) ( $old_column['label'] ?? '' ) );

		foreach ( $new_columns as $new_column ) {
			$new_label = $this->detector->normalize_key( (string) ( $new_column['label'] ?? '' ) );
			if ( $old_label && $new_label && $old_label === $new_label ) {
				return $new_column;
			}
		}

		return null;
	}
}
