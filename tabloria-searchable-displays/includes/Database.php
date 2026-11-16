<?php
/**
 * Database access for sources, spreadsheet rows, and displays
 *
 * @package Tabloria
 */

namespace Tabloria;

use Tabloria\Services\SpreadsheetSanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes Tabloria sources, rows, and displays in custom tables.
 */
class Database {
	/**
	 * Spreadsheet sanitizer.
	 *
	 * @var SpreadsheetSanitizer
	 */
	private SpreadsheetSanitizer $sanitizer;

	/**
	 * Set up the database layer.
	 *
	 * @param SpreadsheetSanitizer|null $sanitizer Optional spreadsheet sanitizer.
	 */
	public function __construct( ?SpreadsheetSanitizer $sanitizer = null ) {
		$this->sanitizer = $sanitizer ?: new SpreadsheetSanitizer();
	}

	/**
	 * Get the sources table name.
	 *
	 * @return string Prefixed table name.
	 */
	public static function sources_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'tabloria_sources';
	}

	/**
	 * Get the spreadsheet rows table name.
	 *
	 * @return string Prefixed table name.
	 */
	public static function rows_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'tabloria_rows';
	}

	/**
	 * Get the displays table name.
	 *
	 * @return string Prefixed table name.
	 */
	public static function displays_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'tabloria_displays';
	}

	/**
	 * Create or update the plugin database tables.
	 */
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

	/**
	 * Save a source and its rows in one transaction.
	 *
	 * @param array $source Source data.
	 * @param array $rows   Spreadsheet rows.
	 *
	 * @return int New source ID, or 0 on failure.
	 */
	public function create_source( array $source, array $rows ): int {
		global $wpdb;

		$columns = $this->sanitizer->sanitize_columns( (array) ( $source['columns'] ?? array() ) );
		$rows    = $this->sanitizer->sanitize_rows( $rows, $columns );
		$now = current_time( 'mysql' );
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Keep source metadata and rows together.
			return 0;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Tabloria stores imported spreadsheets in plugin-owned custom tables.
		$inserted = $wpdb->insert(
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
		if ( false === $inserted || ! $source_id || ! $this->replace_rows( $source_id, $rows ) ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Undo an incomplete source import.
			return 0;
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Commit a complete source import.
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Best effort after failed commit.
			return 0;
		}

		return $source_id;
	}

	/**
	 * Replace the rows and metadata of an existing source in one transaction.
	 *
	 * @param int    $source_id Source ID.
	 * @param array  $columns   Column definitions.
	 * @param array  $rows      Spreadsheet rows.
	 * @param string $filename  Original filename.
	 * @param string $file_type File type.
	 *
	 * @return bool True when the replacement was committed.
	 */
	public function update_source_data( int $source_id, array $columns, array $rows, string $filename, string $file_type ): bool {
		global $wpdb;

		$columns = $this->sanitizer->sanitize_columns( $columns );
		$rows    = $this->sanitizer->sanitize_rows( $rows, $columns );
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Keep old data until the whole replacement is ready.
			return false;
		}

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
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Preserve previous source data.
			return false;
		}

		if ( ! $this->replace_rows( $source_id, $rows ) ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Preserve previous source data.
			return false;
		}

		if ( false === $wpdb->query( 'COMMIT' ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Commit the complete replacement.
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Best effort after failed commit.
			return false;
		}
		return true;
	}

	/**
	 * Delete a source's rows and insert the replacement rows.
	 *
	 * @param int   $source_id Source ID.
	 * @param array $rows      Spreadsheet rows.
	 *
	 * @return bool True when all rows were written.
	 */
	public function replace_rows( int $source_id, array $rows ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Replacing rows in a plugin-owned custom table.
		if ( false === $wpdb->delete( self::rows_table(), array( 'source_id' => $source_id ), array( '%d' ) ) ) {
			return false;
		}

		$now = current_time( 'mysql' );
		foreach ( $rows as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Tabloria stores imported spreadsheet rows in a plugin-owned custom table.
			$inserted = $wpdb->insert(
				self::rows_table(),
				array(
					'source_id'  => $source_id,
					'row_index'  => absint( $index ),
					'row_json'   => wp_json_encode( $this->sanitizer->sanitize_untyped_row( $row ) ),
					'created_at' => $now,
				),
				array( '%d', '%d', '%s', '%s' )
			);
			if ( false === $inserted ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Get a source by ID.
	 *
	 * @param int $source_id Source ID.
	 *
	 * @return array|null Source data, or null if it does not exist.
	 */
	public function get_source( int $source_id ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads from a plugin-owned custom table.
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::sources_table(), $source_id ),
			ARRAY_A
		);

		return $row ? $this->prepare_source( $row ) : null;
	}

	/**
	 * List all sources, newest first.
	 *
	 * @return array List of sources.
	 */
	public function list_sources(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads from a plugin-owned custom table.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY updated_at DESC', self::sources_table() ), ARRAY_A );
		return array_map( array( $this, 'prepare_source' ), $rows ?: array() );
	}

	/**
	 * Get the rows of a source in their original order.
	 *
	 * @param int $source_id Source ID.
	 * @param int $limit     Maximum rows to return, or 0 for all.
	 *
	 * @return array List of decoded rows.
	 */
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

	/**
	 * Save a new display.
	 *
	 * @param array $display Display data.
	 *
	 * @return int New display ID, or 0 on failure.
	 */
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

	/**
	 * Update an existing display.
	 *
	 * @param int   $display_id Display ID.
	 * @param array $display    Display data.
	 *
	 * @return bool True when the display was updated.
	 */
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

	/**
	 * Delete a display.
	 *
	 * @param int $display_id Display ID.
	 *
	 * @return bool True when a display was deleted.
	 */
	public function delete_display( int $display_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Tabloria deletes display definitions from a plugin-owned custom table.
		return false !== $wpdb->delete( self::displays_table(), array( 'id' => $display_id ), array( '%d' ) );
	}

	/**
	 * Get a display by ID.
	 *
	 * @param int $display_id Display ID.
	 *
	 * @return array|null Display data, or null if it does not exist.
	 */
	public function get_display( int $display_id ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads from a plugin-owned custom table.
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::displays_table(), $display_id ),
			ARRAY_A
		);

		return $row ? $this->prepare_display( $row ) : null;
	}

	/**
	 * List all displays with their source summaries.
	 *
	 * @return array List of displays.
	 */
	public function list_displays(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads from a plugin-owned custom table.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY updated_at DESC', self::displays_table() ), ARRAY_A );
		return array_map( array( $this, 'prepare_display' ), $rows ?: array() );
	}

	/**
	 * Decode a source database row for API output.
	 *
	 * @param array $row A single spreadsheet row.
	 *
	 * @return array Prepared source.
	 */
	private function prepare_source( array $row ): array {
		$row['id']          = absint( $row['id'] );
		$row['row_count']   = absint( $row['row_count'] );
		$row['created_by']  = absint( $row['created_by'] );
		$row['columns']     = json_decode( $row['columns_json'] ?? '[]', true );
		$row['columns']     = is_array( $row['columns'] ) ? $this->sanitizer->sanitize_columns( $row['columns'] ) : array();
		unset( $row['columns_json'] );

		return $row;
	}

	/**
	 * Decode a display database row for API output.
	 *
	 * @param array $row A single spreadsheet row.
	 *
	 * @return array Prepared display.
	 */
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

	/**
	 * Limit a display type to the known slugs.
	 *
	 * @param mixed $display_type Display type slug.
	 *
	 * @return string Valid display type slug.
	 */
	private function sanitize_display_type( mixed $display_type ): string {
		$display_type  = sanitize_key( $display_type );
		$allowed_types = array( 'searchable_listings', 'product_catalog', 'team_directory', 'event_schedule', 'pricing_table', 'coupon_display', 'simple_data_table' );

		return in_array( $display_type, $allowed_types, true ) ? $display_type : 'searchable_listings';
	}

	/**
	 * Limit a status to the known values.
	 *
	 * @param mixed $status Status value.
	 *
	 * @return string Valid status.
	 */
	private function sanitize_status( mixed $status ): string {
		$status = sanitize_key( $status );
		return in_array( $status, array( 'draft', 'published' ), true ) ? $status : 'published';
	}
}
