<?php

namespace Tabloria;

use Tabloria\Services\SpreadsheetSanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Database {
	private SpreadsheetSanitizer $sanitizer;

	public function __construct( ?SpreadsheetSanitizer $sanitizer = null ) {
		$this->sanitizer = $sanitizer ?: new SpreadsheetSanitizer();
	}

	public static function sources_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'tabloria_sources';
	}

	public static function rows_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'tabloria_rows';
	}

	public static function displays_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'tabloria_displays';
	}

	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$sources         = self::sources_table();
		$rows            = self::rows_table();
		$displays        = self::displays_table();

		$sql = "
CREATE TABLE {$sources} (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	name VARCHAR(255) NOT NULL DEFAULT '',
	original_filename VARCHAR(255) NOT NULL DEFAULT '',
	file_type VARCHAR(20) NOT NULL DEFAULT 'csv',
	row_count INT UNSIGNED NOT NULL DEFAULT 0,
	columns_json LONGTEXT NOT NULL,
	created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
	created_at DATETIME NOT NULL,
	updated_at DATETIME NOT NULL,
	PRIMARY KEY  (id)
) {$charset_collate};
CREATE TABLE {$rows} (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	source_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
	row_index INT UNSIGNED NOT NULL DEFAULT 0,
	row_json LONGTEXT NOT NULL,
	created_at DATETIME NOT NULL,
	PRIMARY KEY  (id),
	KEY source_id (source_id),
	KEY row_index (row_index)
) {$charset_collate};
CREATE TABLE {$displays} (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	source_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
	name VARCHAR(255) NOT NULL DEFAULT '',
	display_type VARCHAR(80) NOT NULL DEFAULT 'searchable_listings',
	config_json LONGTEXT NOT NULL,
	status VARCHAR(20) NOT NULL DEFAULT 'draft',
	created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
	created_at DATETIME NOT NULL,
	updated_at DATETIME NOT NULL,
	PRIMARY KEY  (id),
	KEY source_id (source_id),
	KEY status (status)
) {$charset_collate};
";

		dbDelta( $sql );
	}

	public function create_source( array $source, array $rows ): int {
		global $wpdb;

		$columns = $this->sanitizer->sanitize_columns( (array) ( $source['columns'] ?? array() ) );
		$rows    = $this->sanitizer->sanitize_rows( $rows, $columns );
		$now = current_time( 'mysql' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Tabloria stores imported spreadsheets in plugin-owned custom tables.
		$wpdb->insert(
			self::sources_table(),
			array(
				'name'              => sanitize_text_field( $source['name'] ?? '' ),
				'original_filename' => sanitize_file_name( $source['original_filename'] ?? '' ),
				'file_type'         => sanitize_key( $source['file_type'] ?? 'csv' ),
				'row_count'         => count( $rows ),
				'columns_json'      => wp_json_encode( $columns ),
				'created_by'        => get_current_user_id(),
				'created_at'        => $now,
				'updated_at'        => $now,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
		);

		$source_id = absint( $wpdb->insert_id );
		$this->replace_rows( $source_id, $rows );

		return $source_id;
	}

	public function update_source_data( int $source_id, array $columns, array $rows, string $filename, string $file_type ): bool {
		global $wpdb;

		$columns = $this->sanitizer->sanitize_columns( $columns );
		$rows    = $this->sanitizer->sanitize_rows( $rows, $columns );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Tabloria stores imported spreadsheets in plugin-owned custom tables.
		$updated = $wpdb->update(
			self::sources_table(),
			array(
				'original_filename' => sanitize_file_name( $filename ),
				'file_type'         => sanitize_key( $file_type ),
				'row_count'         => count( $rows ),
				'columns_json'      => wp_json_encode( $columns ),
				'updated_at'        => current_time( 'mysql' ),
			),
			array( 'id' => $source_id ),
			array( '%s', '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return false;
		}

		$this->replace_rows( $source_id, $rows );
		return true;
	}

	public function replace_rows( int $source_id, array $rows ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Replacing rows in a plugin-owned custom table.
		$wpdb->delete( self::rows_table(), array( 'source_id' => $source_id ), array( '%d' ) );

		$now = current_time( 'mysql' );
		foreach ( $rows as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Tabloria stores imported spreadsheet rows in a plugin-owned custom table.
			$wpdb->insert(
				self::rows_table(),
				array(
					'source_id'  => $source_id,
					'row_index'  => absint( $index ),
					'row_json'   => wp_json_encode( $this->sanitizer->sanitize_untyped_row( $row ) ),
					'created_at' => $now,
				),
				array( '%d', '%d', '%s', '%s' )
			);
		}
	}

	public function get_source( int $source_id ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads from a plugin-owned custom table.
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::sources_table(), $source_id ),
			ARRAY_A
		);

		return $row ? $this->prepare_source( $row ) : null;
	}

	public function list_sources(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads from a plugin-owned custom table.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY updated_at DESC', self::sources_table() ), ARRAY_A );
		return array_map( array( $this, 'prepare_source' ), $rows ?: array() );
	}

	public function get_rows( int $source_id, int $limit = 0 ): array {
		global $wpdb;

		if ( $limit > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads from a plugin-owned custom table.
			$records = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT row_json FROM %i WHERE source_id = %d ORDER BY row_index ASC LIMIT %d',
					self::rows_table(),
					$source_id,
					$limit
				),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads from a plugin-owned custom table.
			$records = $wpdb->get_results(
				$wpdb->prepare( 'SELECT row_json FROM %i WHERE source_id = %d ORDER BY row_index ASC', self::rows_table(), $source_id ),
				ARRAY_A
			);
		}

		return array_map(
			function ( array $record ): array {
				$row = json_decode( $record['row_json'], true );
				return is_array( $row ) ? $this->sanitizer->sanitize_untyped_row( $row ) : array();
			},
			$records ?: array()
		);
	}

	public function create_display( array $display ): int {
		global $wpdb;

		$now = current_time( 'mysql' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Tabloria stores display definitions in a plugin-owned custom table.
		$wpdb->insert(
			self::displays_table(),
			array(
				'source_id'     => absint( $display['source_id'] ?? 0 ),
				'name'          => sanitize_text_field( $display['name'] ?? __( 'Untitled Display', 'tabloria-searchable-displays' ) ),
				'display_type'  => $this->sanitize_display_type( $display['display_type'] ?? 'searchable_listings' ),
				'config_json'   => wp_json_encode( $display['config'] ?? array() ),
				'status'        => $this->sanitize_status( $display['status'] ?? 'published' ),
				'created_by'    => get_current_user_id(),
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		return absint( $wpdb->insert_id );
	}

	public function update_display( int $display_id, array $display ): bool {
		global $wpdb;

		$data   = array( 'updated_at' => current_time( 'mysql' ) );
		$format = array( '%s' );

		if ( isset( $display['name'] ) ) {
			$data['name'] = sanitize_text_field( $display['name'] );
			$format[]     = '%s';
		}
		if ( isset( $display['display_type'] ) ) {
			$data['display_type'] = $this->sanitize_display_type( $display['display_type'] );
			$format[]             = '%s';
		}
		if ( isset( $display['config'] ) ) {
			$data['config_json'] = wp_json_encode( $display['config'] );
			$format[]            = '%s';
		}
		if ( isset( $display['status'] ) ) {
			$data['status'] = $this->sanitize_status( $display['status'] );
			$format[]       = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Tabloria updates display definitions in a plugin-owned custom table.
		$result = $wpdb->update( self::displays_table(), $data, array( 'id' => $display_id ), $format, array( '%d' ) );
		return false !== $result;
	}

	public function delete_display( int $display_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Tabloria deletes display definitions from a plugin-owned custom table.
		return false !== $wpdb->delete( self::displays_table(), array( 'id' => $display_id ), array( '%d' ) );
	}

	public function get_display( int $display_id ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads from a plugin-owned custom table.
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::displays_table(), $display_id ),
			ARRAY_A
		);

		return $row ? $this->prepare_display( $row ) : null;
	}

	public function list_displays(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads from a plugin-owned custom table.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY updated_at DESC', self::displays_table() ), ARRAY_A );
		return array_map( array( $this, 'prepare_display' ), $rows ?: array() );
	}

	private function prepare_source( array $row ): array {
		$row['id']          = absint( $row['id'] );
		$row['row_count']   = absint( $row['row_count'] );
		$row['created_by']  = absint( $row['created_by'] );
		$row['columns']     = json_decode( $row['columns_json'] ?? '[]', true );
		$row['columns']     = is_array( $row['columns'] ) ? $this->sanitizer->sanitize_columns( $row['columns'] ) : array();
		unset( $row['columns_json'] );

		return $row;
	}

	private function prepare_display( array $row ): array {
		$row['id']         = absint( $row['id'] );
		$row['source_id']  = absint( $row['source_id'] );
		$row['created_by'] = absint( $row['created_by'] );
		$row['display_type'] = $this->sanitize_display_type( $row['display_type'] ?? 'searchable_listings' );
		$row['status'] = $this->sanitize_status( $row['status'] ?? 'draft' );
		$row['config']     = json_decode( $row['config_json'] ?? '{}', true );
		$row['config']     = is_array( $row['config'] ) ? $row['config'] : array();
		unset( $row['config_json'] );

		$source = $this->get_source( $row['source_id'] );
		if ( $source ) {
			$row['source'] = $source;
		}

		return $row;
	}

	private function sanitize_display_type( mixed $display_type ): string {
		$display_type  = sanitize_key( $display_type );
		$allowed_types = array( 'searchable_listings', 'product_catalog', 'team_directory', 'event_schedule', 'pricing_table', 'simple_data_table' );

		return in_array( $display_type, $allowed_types, true ) ? $display_type : 'searchable_listings';
	}

	private function sanitize_status( mixed $status ): string {
		$status = sanitize_key( $status );
		return in_array( $status, array( 'draft', 'published' ), true ) ? $status : 'published';
	}
}
