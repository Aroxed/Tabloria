<?php

use Tabloria\Database;
use Tabloria\Services\CsvParser;
use Tabloria\Services\SourceSync;

$import_dir = sys_get_temp_dir() . '/tabloria-sync-test-' . getmypid();
if ( ! mkdir( $import_dir, 0700 ) ) {
	throw new RuntimeException( 'Could not create private CSV test directory.' );
}
define( 'TABLORIA_IMPORT_DIR', $import_dir );

$wp_load = getenv( 'TABLORIA_WP_LOAD' ) ?: dirname( __DIR__, 4 ) . '/wp-load.php';
require_once $wp_load;

function sync_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$database = new Database();
$sync = new SourceSync( $database );
$csv = $import_dir . '/catalog.csv';
$outside_csv = sys_get_temp_dir() . '/tabloria-outside-' . getmypid() . '.csv';
$source_id = 0;
$old_timezone = get_option( 'timezone_string' );
$old_user = get_current_user_id();

try {
	file_put_contents( $csv, "Product,Price\nOriginal,10\n" );
	$parsed = ( new CsvParser() )->parse( $csv );
	sync_assert( ! is_wp_error( $parsed ), 'Initial CSV should parse.' );
	$source_id = $database->create_source(
		array( 'name' => 'Sync smoke source', 'original_filename' => 'catalog.csv', 'file_type' => 'csv', 'columns' => $parsed['columns'] ),
		$parsed['rows']
	);
	sync_assert( $source_id > 0, 'Source should be created.' );

	$body = array( 'path' => 'catalog.csv', 'enabled' => true, 'frequency' => 'daily', 'time' => '10:30', 'weekday' => 1 );
	$save_request = new WP_REST_Request( 'PUT', '/tabloria/v1/sources/' . $source_id . '/sync' );
	$save_request->set_header( 'Content-Type', 'application/json' );
	$save_request->set_body( wp_json_encode( $body ) );
	$denied = rest_do_request( $save_request );
	sync_assert( $denied->get_status() >= 400, 'Unauthenticated users must not configure sync.' );
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	sync_assert( ! empty( $admins ), 'A local administrator is required for REST smoke tests.' );
	wp_set_current_user( $admins[0]->ID );
	$saved = rest_do_request( $save_request );
	$settings = $saved->get_data();
	sync_assert( 200 === $saved->get_status() && $settings['nextRun'] > time(), 'Daily schedule should be saved through REST.' );
	$refresh_request = new WP_REST_Request( 'POST', '/tabloria/v1/sources/' . $source_id . '/refresh' );
	$refreshed = rest_do_request( $refresh_request );
	$result = $refreshed->get_data();
	sync_assert( 200 === $refreshed->get_status() && $result['source']['row_count'] === 1, 'Manual refresh should work through REST.' );
	wp_set_current_user( $old_user );
	sync_assert( ( $database->get_rows( $source_id ) )[0]['product'] === 'Original', 'Manual refresh should keep expected row.' );

	file_put_contents( $csv, "Product,Price\nScheduled,20\n" );
	$sync->run_scheduled( $source_id );
	sync_assert( ( $database->get_rows( $source_id ) )[0]['product'] === 'Scheduled', 'Scheduled refresh should replace rows.' );
	$last_success = $sync->get_settings( $source_id )['lastSuccess'];
	sync_assert( '' !== $last_success, 'Successful refresh time should be stored.' );
	$unchanged = $sync->refresh( $source_id, true );
	sync_assert( ! is_wp_error( $unchanged ) && ! empty( $unchanged['unchanged'] ), 'Scheduled refresh should skip unchanged files.' );

	file_put_contents( $csv, "Other,Price\nInvalid,30\n" );
	$error = $sync->refresh( $source_id );
	sync_assert( is_wp_error( $error ) && 'tabloria_csv_missing_columns' === $error->get_error_code(), 'Missing old columns should be rejected.' );
	sync_assert( ( $database->get_rows( $source_id ) )[0]['product'] === 'Scheduled', 'Failed refresh should keep previous rows.' );
	sync_assert( '' !== $sync->get_settings( $source_id )['lastError'], 'Failed refresh should store an error.' );
	sync_assert( $last_success === $sync->get_settings( $source_id )['lastSuccess'], 'Failed refresh should not change last success.' );

	$invalid_path = $sync->save_settings( $source_id, array( 'path' => '../catalog.csv', 'enabled' => true, 'frequency' => 'daily', 'time' => '10:30', 'weekday' => 1 ) );
	sync_assert( is_wp_error( $invalid_path ), 'Parent-directory path should be rejected.' );
	$invalid_time = $sync->save_settings( $source_id, array( 'path' => 'catalog.csv', 'enabled' => true, 'frequency' => 'daily', 'time' => '25:90', 'weekday' => 1 ) );
	sync_assert( is_wp_error( $invalid_time ), 'Invalid clock time should be rejected.' );
	file_put_contents( $outside_csv, "Product,Price\nOutside,99\n" );
	symlink( $outside_csv, $import_dir . '/linked.csv' );
	$linked = $sync->save_settings( $source_id, array( 'path' => 'linked.csv', 'enabled' => true, 'frequency' => 'daily', 'time' => '10:30', 'weekday' => 1 ) );
	sync_assert( is_wp_error( $linked ), 'Symlinks outside the private directory should be rejected.' );
	$public_sync = new class( $database ) extends SourceSync {
		public function import_directory(): string {
			return ABSPATH;
		}
	};
	$public = $public_sync->save_settings( $source_id, array( 'path' => 'catalog.csv', 'enabled' => true, 'frequency' => 'daily', 'time' => '10:30', 'weekday' => 1 ) );
	sync_assert( is_wp_error( $public ) && 'tabloria_sync_directory_public' === $public->get_error_code(), 'Public import directories should be rejected.' );

	update_option( 'timezone_string', 'Europe/Kyiv' );
	$now = ( new DateTimeImmutable( '2026-11-12 12:00', wp_timezone() ) )->getTimestamp();
	$daily = $sync->calculate_next_run( array( 'frequency' => 'daily', 'time' => '10:30' ), $now );
	$weekly = $sync->calculate_next_run( array( 'frequency' => 'weekly', 'time' => '09:00', 'weekday' => 1 ), $now );
	sync_assert( '2026-11-13 10:30' === wp_date( 'Y-m-d H:i', $daily, wp_timezone() ), 'Daily clock time should use the WordPress timezone.' );
	sync_assert( '2026-11-16 09:00' === wp_date( 'Y-m-d H:i', $weekly, wp_timezone() ), 'Weekly day and clock time should use the WordPress timezone.' );
	$disabled = $sync->save_settings( $source_id, array( 'path' => 'catalog.csv', 'enabled' => false, 'frequency' => 'weekly', 'time' => '09:00', 'weekday' => 1 ) );
	sync_assert( ! is_wp_error( $disabled ) && ! $disabled['enabled'] && ! $disabled['nextRun'], 'Disabling automatic refresh should remove its event.' );
	sync_assert( in_array( $source_id, (array) get_option( 'tabloria_source_sync_ids', array() ), true ), 'Disabled source should remain indexed for uninstall cleanup.' );

	echo "PASS server CSV manual and scheduled refresh smoke test\n";
} finally {
	wp_set_current_user( $old_user );
	update_option( 'timezone_string', $old_timezone );
	if ( $source_id ) {
		wp_clear_scheduled_hook( 'tabloria_sync_csv_source', array( $source_id ) );
		delete_option( 'tabloria_source_sync_' . $source_id );
		$ids = array_values( array_diff( (array) get_option( 'tabloria_source_sync_ids', array() ), array( $source_id ) ) );
		update_option( 'tabloria_source_sync_ids', $ids, false );
		global $wpdb;
		$wpdb->delete( Database::rows_table(), array( 'source_id' => $source_id ), array( '%d' ) );
		$wpdb->delete( Database::sources_table(), array( 'id' => $source_id ), array( '%d' ) );
	}
	if ( file_exists( $csv ) ) {
		unlink( $csv );
	}
	if ( is_link( $import_dir . '/linked.csv' ) ) {
		unlink( $import_dir . '/linked.csv' );
	}
	if ( file_exists( $outside_csv ) ) {
		unlink( $outside_csv );
	}
	rmdir( $import_dir );
}
