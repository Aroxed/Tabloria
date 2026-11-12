<?php

namespace Tabloria\Services;

use DateTimeImmutable;
use Tabloria\Database;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SourceSync {
	private const HOOK = 'tabloria_sync_csv_source';
	private const INDEX_OPTION = 'tabloria_source_sync_ids';
	private const FREQUENCIES = array( 'six_hours', 'twelve_hours', 'daily', 'weekly' );

	private Database $database;
	private DataReplacementService $replacement;

	public function __construct( ?Database $database = null, ?DataReplacementService $replacement = null ) {
		$this->database    = $database ?: new Database();
		$this->replacement = $replacement ?: new DataReplacementService( $this->database );
	}

	public function register_hooks(): void {
		add_action( self::HOOK, array( $this, 'run_scheduled' ) );
		add_action( 'init', array( $this, 'ensure_schedules' ) );
	}

	public static function deactivate(): void {
		foreach ( (array) get_option( self::INDEX_OPTION, array() ) as $source_id ) {
			wp_clear_scheduled_hook( self::HOOK, array( absint( $source_id ) ) );
		}
	}

	public function import_directory(): string {
		return defined( 'TABLORIA_IMPORT_DIR' ) ? (string) TABLORIA_IMPORT_DIR : dirname( untrailingslashit( ABSPATH ) ) . '/tabloria-imports';
	}

	public function get_settings( int $source_id ): array {
		$stored = get_option( $this->option_key( $source_id ), array() );
		$stored = is_array( $stored ) ? $stored : array();
		$next_run = wp_next_scheduled( self::HOOK, array( $source_id ) ) ?: 0;
		return array(
			'path'            => (string) ( $stored['path'] ?? '' ),
			'enabled'         => ! empty( $stored['enabled'] ),
			'frequency'       => (string) ( $stored['frequency'] ?? 'daily' ),
			'time'            => (string) ( $stored['time'] ?? '03:00' ),
			'weekday'         => (int) ( $stored['weekday'] ?? 1 ),
			'lastCheck'       => (string) ( $stored['lastCheck'] ?? '' ),
			'lastSuccess'     => (string) ( $stored['lastSuccess'] ?? '' ),
			'lastError'       => (string) ( $stored['lastError'] ?? '' ),
			'nextRun'         => $next_run,
			'nextRunLabel'    => $next_run ? wp_date( 'Y-m-d H:i', $next_run, wp_timezone() ) : '',
			'importDirectory' => $this->import_directory(),
			'timezone'        => wp_timezone_string(),
		);
	}

	public function save_settings( int $source_id, array $input ): array|WP_Error {
		if ( ! $this->database->get_source( $source_id ) ) {
			return new WP_Error( 'tabloria_source_not_found', __( 'Source spreadsheet not found.', 'tabloria-searchable-displays' ), array( 'status' => 404 ) );
		}

		if ( ! is_string( $input['path'] ?? '' ) || ! is_string( $input['frequency'] ?? 'daily' ) || ! is_string( $input['time'] ?? '03:00' ) ) {
			return new WP_Error( 'tabloria_sync_settings_invalid', __( 'Enter a CSV filename and valid schedule settings.', 'tabloria-searchable-displays' ), array( 'status' => 400 ) );
		}
		$path = trim( $input['path'] ?? '' );
		if ( '' !== $path && ( strlen( $path ) > 255 || str_contains( $path, "\0" ) || str_contains( $path, '\\' ) || str_starts_with( $path, '/' ) || preg_match( '~(^|/)\.\.?(/|$)~', $path ) || 'csv' !== strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) ) {
			return new WP_Error( 'tabloria_sync_path_invalid', __( 'Enter a relative CSV filename inside the private import directory.', 'tabloria-searchable-displays' ), array( 'status' => 400 ) );
		}
		$frequency = sanitize_key( $input['frequency'] ?? 'daily' );
		$time      = (string) ( $input['time'] ?? '03:00' );
		$weekday   = absint( $input['weekday'] ?? 1 );
		$enabled   = ! empty( $input['enabled'] );
		if ( ! in_array( $frequency, self::FREQUENCIES, true ) || ! preg_match( '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $time ) || $weekday < 1 || $weekday > 7 ) {
			return new WP_Error( 'tabloria_sync_schedule_invalid', __( 'Choose a valid refresh interval, time, and weekday.', 'tabloria-searchable-displays' ), array( 'status' => 400 ) );
		}
		if ( $enabled ) {
			$resolved = $this->resolve_path( $path );
			if ( is_wp_error( $resolved ) ) {
				return $resolved;
			}
		}

		$previous = get_option( $this->option_key( $source_id ), null );
		$stored   = is_array( $previous ) ? $previous : array();
		if ( $path !== ( $stored['path'] ?? '' ) ) {
			unset( $stored['lastHash'] );
		}
		$stored['path']      = $path;
		$stored['enabled']   = $enabled;
		$stored['frequency'] = $frequency;
		$stored['time']      = $time;
		$stored['weekday']   = $weekday;
		update_option( $this->option_key( $source_id ), $stored, false );

		$previous_ids = (array) get_option( self::INDEX_OPTION, array() );
		$ids          = array_values( array_unique( array_merge( array_map( 'absint', $previous_ids ), array( $source_id ) ) ) );
		update_option( self::INDEX_OPTION, $ids, false );
		wp_clear_scheduled_hook( self::HOOK, array( $source_id ) );
		if ( $enabled && ! $this->schedule_next( $source_id ) ) {
			if ( null === $previous ) {
				delete_option( $this->option_key( $source_id ) );
			} else {
				update_option( $this->option_key( $source_id ), $previous, false );
			}
			update_option( self::INDEX_OPTION, $previous_ids, false );
			$this->schedule_next( $source_id );
			return new WP_Error( 'tabloria_sync_schedule_failed', __( 'The refresh schedule could not be saved. Check that WP-Cron is available and try again.', 'tabloria-searchable-displays' ), array( 'status' => 500 ) );
		}
		return $this->get_settings( $source_id );
	}

	public function refresh( int $source_id, bool $scheduled = false ): array|WP_Error {
		$source = $this->database->get_source( $source_id );
		if ( ! $source ) {
			return new WP_Error( 'tabloria_source_not_found', __( 'Source spreadsheet not found.', 'tabloria-searchable-displays' ), array( 'status' => 404 ) );
		}
		$stored = get_option( $this->option_key( $source_id ), array() );
		$stored = is_array( $stored ) ? $stored : array();
		$stored['lastCheck'] = current_time( 'mysql' );
		$path = $this->resolve_path( (string) ( $stored['path'] ?? '' ) );
		if ( is_wp_error( $path ) ) {
			return $this->record_error( $source_id, $stored, $path );
		}
		$hash = hash_file( 'sha256', $path );
		if ( false === $hash ) {
			return $this->record_error( $source_id, $stored, new WP_Error( 'tabloria_sync_read_failed', __( 'The server CSV file could not be read.', 'tabloria-searchable-displays' ), array( 'status' => 400 ) ) );
		}
		if ( $scheduled && $hash === ( $stored['lastHash'] ?? '' ) ) {
			$stored['lastError'] = '';
			update_option( $this->option_key( $source_id ), $stored, false );
			return array( 'unchanged' => true, 'source' => $source, 'sync' => $this->get_settings( $source_id ) );
		}

		$validated = ( new SpreadsheetSanitizer() )->validate_csv_file_path( $path );
		if ( is_wp_error( $validated ) ) {
			return $this->record_error( $source_id, $stored, $validated );
		}
		$snapshot = tempnam( sys_get_temp_dir(), 'tabloria-csv-' );
		if ( false === $snapshot ) {
			return $this->record_error( $source_id, $stored, new WP_Error( 'tabloria_sync_snapshot_failed', __( 'A private temporary copy of the CSV could not be created.', 'tabloria-searchable-displays' ), array( 'status' => 500 ) ) );
		}
		try {
			if ( ! copy( $path, $snapshot ) || $hash !== hash_file( 'sha256', $snapshot ) || $hash !== hash_file( 'sha256', $path ) ) {
				return $this->record_error( $source_id, $stored, new WP_Error( 'tabloria_sync_file_changed', __( 'The CSV changed while it was being read. Upload the new file under a temporary name, then rename it into place.', 'tabloria-searchable-displays' ), array( 'status' => 409 ) ) );
			}
			$result = $this->replacement->replace( $source, $snapshot, basename( $path ) );
			if ( is_wp_error( $result ) ) {
				return $this->record_error( $source_id, $stored, $result );
			}
		} finally {
			unlink( $snapshot ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Private temporary copy is removed immediately after import.
		}
		$stored['lastHash']    = $hash;
		$stored['lastSuccess'] = current_time( 'mysql' );
		$stored['lastError']   = '';
		update_option( $this->option_key( $source_id ), $stored, false );
		$result['sync'] = $this->get_settings( $source_id );
		return $result;
	}

	public function run_scheduled( int $source_id ): void {
		$stored = get_option( $this->option_key( $source_id ), array() );
		if ( ! is_array( $stored ) || empty( $stored['enabled'] ) ) {
			return;
		}
		$this->refresh( $source_id, true );
		$this->schedule_next( $source_id );
	}

	public function ensure_schedules(): void {
		foreach ( (array) get_option( self::INDEX_OPTION, array() ) as $source_id ) {
			$source_id = absint( $source_id );
			$stored    = get_option( $this->option_key( $source_id ), array() );
			if ( $source_id && is_array( $stored ) && ! empty( $stored['enabled'] ) && ! wp_next_scheduled( self::HOOK, array( $source_id ) ) ) {
				$this->schedule_next( $source_id );
			}
		}
	}

	public function calculate_next_run( array $settings, int $now ): int {
		$zone      = wp_timezone();
		$current   = ( new DateTimeImmutable( '@' . $now ) )->setTimezone( $zone );
		$time      = (string) ( $settings['time'] ?? '03:00' );
		$hour      = (int) substr( $time, 0, 2 );
		$minute    = (int) substr( $time, 3, 2 );
		$candidate = $current->setTime( $hour, $minute, 0 );
		$frequency = (string) ( $settings['frequency'] ?? 'daily' );
		if ( 'weekly' === $frequency ) {
			$days = ( (int) ( $settings['weekday'] ?? 1 ) - (int) $candidate->format( 'N' ) + 7 ) % 7;
			$candidate = $candidate->modify( '+' . $days . ' days' );
			if ( $candidate->getTimestamp() <= $now ) {
				$candidate = $candidate->modify( '+7 days' );
			}
		} elseif ( 'daily' === $frequency ) {
			if ( $candidate->getTimestamp() <= $now ) {
				$candidate = $candidate->modify( '+1 day' );
			}
		} else {
			$seconds = 'six_hours' === $frequency ? 6 * HOUR_IN_SECONDS : 12 * HOUR_IN_SECONDS;
			while ( $candidate->getTimestamp() <= $now ) {
				$candidate = $candidate->setTimestamp( $candidate->getTimestamp() + $seconds );
			}
		}
		return $candidate->getTimestamp();
	}

	private function schedule_next( int $source_id ): bool {
		$stored = get_option( $this->option_key( $source_id ), array() );
		if ( ! is_array( $stored ) || empty( $stored['enabled'] ) ) {
			return false;
		}
		if ( wp_next_scheduled( self::HOOK, array( $source_id ) ) ) {
			return true;
		}
		if ( ! $this->database->get_source( $source_id ) ) {
			return false;
		}
		$scheduled = wp_schedule_single_event( $this->calculate_next_run( $stored, time() ), self::HOOK, array( $source_id ) );
		return true === $scheduled && (bool) wp_next_scheduled( self::HOOK, array( $source_id ) );
	}

	private function resolve_path( string $relative ): string|WP_Error {
		if ( '' === $relative ) {
			return new WP_Error( 'tabloria_sync_path_required', __( 'Enter a CSV filename inside the private import directory.', 'tabloria-searchable-displays' ), array( 'status' => 400 ) );
		}
		$root = realpath( $this->import_directory() );
		if ( false === $root || ! is_dir( $root ) ) {
			return new WP_Error( 'tabloria_sync_directory_missing', __( 'Create the private import directory on your server before connecting a CSV file.', 'tabloria-searchable-displays' ), array( 'status' => 400 ) );
		}
		$public_roots = array( ABSPATH, $_SERVER['DOCUMENT_ROOT'] ?? '' );
		foreach ( $public_roots as $public_root ) {
			$public_root = $public_root ? realpath( $public_root ) : false;
			if ( $public_root && ( $root === $public_root || str_starts_with( $root, $public_root . DIRECTORY_SEPARATOR ) ) ) {
				return new WP_Error( 'tabloria_sync_directory_public', __( 'The import directory must be outside the public website directory. Set TABLORIA_IMPORT_DIR to a private server path.', 'tabloria-searchable-displays' ), array( 'status' => 400 ) );
			}
		}
		$path = realpath( $root . DIRECTORY_SEPARATOR . $relative );
		if ( false === $path || ! str_starts_with( $path, $root . DIRECTORY_SEPARATOR ) || ! is_file( $path ) || ! is_readable( $path ) || 'csv' !== strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			return new WP_Error( 'tabloria_sync_file_unreadable', __( 'The CSV file was not found inside the private import directory or cannot be read by WordPress.', 'tabloria-searchable-displays' ), array( 'status' => 400 ) );
		}
		foreach ( $public_roots as $public_root ) {
			$public_root = $public_root ? realpath( $public_root ) : false;
			if ( $public_root && ( $path === $public_root || str_starts_with( $path, $public_root . DIRECTORY_SEPARATOR ) ) ) {
				return new WP_Error( 'tabloria_sync_file_public', __( 'The CSV must stay outside the public website directory.', 'tabloria-searchable-displays' ), array( 'status' => 400 ) );
			}
		}
		return $path;
	}

	private function record_error( int $source_id, array $stored, WP_Error $error ): WP_Error {
		$stored['lastError'] = substr( $error->get_error_message(), 0, 300 );
		update_option( $this->option_key( $source_id ), $stored, false );
		return $error;
	}

	private function option_key( int $source_id ): string {
		return 'tabloria_source_sync_' . $source_id;
	}
}
