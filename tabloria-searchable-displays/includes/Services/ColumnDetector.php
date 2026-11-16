<?php
/**
 * Column type detection for imported spreadsheets
 *
 * @package Tabloria
 */

namespace Tabloria\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects the type of each spreadsheet column from its label and values.
 */
class ColumnDetector {
	/**
	 * Detect the label, key, and type of every column.
	 *
	 * @param array $headers Header labels.
	 * @param array $rows    Spreadsheet rows.
	 *
	 * @return array Column definitions.
	 */
	public function detect_columns( array $headers, array $rows ): array {
		$columns = array();

		foreach ( $headers as $index => $label ) {
			$key      = $this->normalize_key( (string) $label );
			$samples  = array();
			$max_rows = min( count( $rows ), 50 );

			for ( $i = 0; $i < $max_rows; $i++ ) {
				$samples[] = (string) ( array_values( $rows[ $i ] )[ $index ] ?? '' );
			}

			$columns[] = array(
				'key'        => $key,
				'label'      => sanitize_text_field( (string) $label ),
				'type'       => $this->detect_type( $samples, (string) $label ),
				'confidence' => 0.72,
			);
		}

		return $columns;
	}

	/**
	 * Turn a header label into a lowercase key of letters, digits, and underscores.
	 *
	 * @param string $label Column label.
	 *
	 * @return string Normalized key.
	 */
	public function normalize_key( string $label ): string {
		$key = remove_accents( strtolower( trim( $label ) ) );
		$key = preg_replace( '/[^a-z0-9]+/', '_', $key );
		$key = trim( (string) $key, '_' );

		return $key ?: 'field';
	}

	/**
	 * Guess a column type from its label and sample values.
	 *
	 * @param array  $values Values to inspect.
	 * @param string $label  Column label.
	 *
	 * @return string Column type.
	 */
	private function detect_type( array $values, string $label ): string {
		$label_key = $this->normalize_key( $label );

		if ( $this->label_has_token( $label_key, array( 'image', 'photo', 'picture', 'thumbnail' ) ) ) {
			return 'image';
		}
		if ( $this->label_has_token( $label_key, array( 'email' ) ) ) {
			return 'email';
		}
		if ( $this->label_has_token( $label_key, array( 'url', 'link', 'website' ) ) ) {
			return 'url';
		}
		if ( $this->label_has_token( $label_key, array( 'date', 'created_at', 'updated_at' ) ) ) {
			return 'date';
		}
		if ( $this->label_has_token( $label_key, array( 'phone', 'tel', 'mobile' ) ) ) {
			return 'phone';
		}
		if ( $this->label_has_token( $label_key, array( 'price', 'cost', 'rate', 'amount', 'total' ) ) ) {
			return 'price';
		}
		if ( $this->label_has_token( $label_key, array( 'quantity', 'qty', 'count', 'stock', 'rooms' ) ) ) {
			return 'number';
		}
		if ( $this->label_has_token( $label_key, array( 'status', 'state', 'availability' ) ) ) {
			return 'status';
		}
		if ( $this->is_identifier_or_name_label( $label_key ) ) {
			return 'text';
		}

		$values = array_values(
			array_filter(
				array_map( 'trim', $values ),
				static fn( string $value ): bool => '' !== $value
			)
		);

		if ( empty( $values ) ) {
			return 'text';
		}

		$total = count( $values );
		$hits  = array(
			'price'  => 0,
			'date'   => 0,
			'number' => 0,
			'status' => 0,
			'image'  => 0,
			'url'    => 0,
			'email'  => 0,
			'phone'  => 0,
		);

		$distinct = array();
		foreach ( $values as $value ) {
			$distinct[ strtolower( $value ) ] = true;

			if ( preg_match( '/(?:[$€£¥]|\b(?:usd|eur|gbp|uah)\b|\/\s?(?:mo|month|yr|year|day|hr|hour)\b)/i', $value ) && preg_match( '/\d/', $value ) ) {
				$hits['price']++;
			}
			if ( strtotime( $value ) && preg_match( '/\d{1,4}[-\/.]\d{1,2}[-\/.]\d{1,4}/', $value ) ) {
				$hits['date']++;
			}
			if ( is_numeric( str_replace( array( ',', '$' ), '', $value ) ) ) {
				$hits['number']++;
			}
			if ( preg_match( '/\.(jpg|jpeg|png|webp|gif)(\?.*)?$/i', $value ) ) {
				$hits['image']++;
			}
			if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
				$hits['url']++;
			}
			if ( is_email( $value ) ) {
				$hits['email']++;
			}
			if ( ! preg_match( '/^\d{4}[-\/.]\d{1,2}[-\/.]\d{1,2}$/', $value ) && preg_match( '/^\+?[0-9\s().-]{7,}$/', $value ) ) {
				$hits['phone']++;
			}
		}

		if ( $this->looks_like_status_values( array_keys( $distinct ), $total ) ) {
			$hits['status'] = $total;
		}

		foreach ( array( 'email', 'image', 'url', 'date', 'phone', 'price', 'number', 'status' ) as $type ) {
			if ( $hits[ $type ] / $total >= 0.65 ) {
				return $type;
			}
		}

		return 'text';
	}

	/**
	 * Check whether a normalized label contains any of the tokens.
	 *
	 * @param string $label_key Normalized label.
	 * @param array  $tokens    Tokens to look for.
	 *
	 * @return bool True when a token matches.
	 */
	private function label_has_token( string $label_key, array $tokens ): bool {
		foreach ( $tokens as $token ) {
			$token = preg_quote( $token, '/' );
			if ( preg_match( '/(^|_)' . $token . '($|_)/', $label_key ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether a label looks like an ID or a name.
	 *
	 * @param string $label_key Normalized label.
	 *
	 * @return bool True for identifier or name labels.
	 */
	private function is_identifier_or_name_label( string $label_key ): bool {
		if ( $this->label_has_token( $label_key, array( 'sku', 'code', 'id', 'uuid', 'slug', 'name', 'title', 'manufacturer', 'supplier', 'vendor' ) ) ) {
			return true;
		}

		return (bool) preg_match( '/(^|_)(order|work_order|invoice|reference|ref)_?number($|_)/', $label_key );
	}

	/**
	 * Check whether the values look like a small set of statuses.
	 *
	 * @param array $distinct_values Distinct values found in the column.
	 * @param int   $total           Total number of values.
	 *
	 * @return bool True when the values look like statuses.
	 */
	private function looks_like_status_values( array $distinct_values, int $total ): bool {
		if ( $total < 5 || empty( $distinct_values ) ) {
			return false;
		}

		if ( count( $distinct_values ) > max( 3, (int) floor( $total * 0.25 ) ) ) {
			return false;
		}

		$status_words = array(
			'active',
			'inactive',
			'available',
			'unavailable',
			'pending',
			'draft',
			'published',
			'open',
			'closed',
			'complete',
			'completed',
			'cancelled',
			'canceled',
			'reserved',
			'sold',
			'leased',
			'in stock',
			'out of stock',
			'low stock',
		);

		$matched = 0;
		foreach ( $distinct_values as $value ) {
			$value = strtolower( trim( (string) $value ) );
			if ( in_array( $value, $status_words, true ) || preg_match( '/^(yes|no|true|false|on|off)$/', $value ) ) {
				$matched++;
			}
		}

		return $matched === count( $distinct_values );
	}
}
